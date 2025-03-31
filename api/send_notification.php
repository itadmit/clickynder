<?php
// API לשליחת הודעות ותזכורות ללקוחות

// בדיקת התחברות
session_start();
if (!isset($_SESSION['tenant_id'])) {
    header('HTTP/1.0 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'לא מחובר']);
    exit;
}

// כולל קבצי תצורה
require_once "../config/database.php";
require_once "../includes/functions.php";

// Set headers for JSON response
header('Content-Type: application/json');

// קבלת נתונים מה-POST
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

// בדיקת פרמטרים חובה
if (!isset($data['notification_type']) || !isset($data['appointment_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'חסרים פרמטרים חובה: סוג הודעה ומזהה תור'
    ]);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$notification_type = sanitizeInput($data['notification_type']); // confirmation, reminder, cancellation
$appointment_id = intval($data['appointment_id']);
$custom_message = isset($data['custom_message']) ? sanitizeInput($data['custom_message']) : '';
$send_email = isset($data['send_email']) ? (bool)$data['send_email'] : true;
$send_sms = isset($data['send_sms']) ? (bool)$data['send_sms'] : true;

try {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();
    
    // קבלת פרטי התור
    $query = "SELECT a.*, 
              c.first_name, c.last_name, c.phone, c.email, 
              s.name as service_name, s.duration,
              st.name as staff_name
              FROM appointments a
              JOIN customers c ON a.customer_id = c.customer_id
              JOIN services s ON a.service_id = s.service_id
              JOIN staff st ON a.staff_id = st.staff_id
              WHERE a.appointment_id = :appointment_id AND a.tenant_id = :tenant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":appointment_id", $appointment_id);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'התור לא נמצא או אינו שייך לעסק שלך'
        ]);
        exit;
    }
    
    $appointment = $stmt->fetch();
    
    // קבלת פרטי העסק
    $query = "SELECT * FROM tenants WHERE tenant_id = :tenant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    $tenant = $stmt->fetch();
    
    // קבלת תבנית ההודעה המתאימה
    $template_type = '';
    switch ($notification_type) {
        case 'confirmation':
            $template_type = 'booking_confirmation';
            break;
        case 'reminder':
            $template_type = 'appointment_reminder';
            break;
        case 'cancellation':
            $template_type = 'appointment_cancelled';
            break;
        case 'waitlist':
            $template_type = 'waitlist_notification';
            break;
        default:
            $template_type = 'booking_confirmation';
    }
    
    $query = "SELECT * FROM message_templates WHERE tenant_id = :tenant_id AND type = :type";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->bindParam(":type", $template_type);
    $stmt->execute();
    
    // אם לא נמצאה תבנית מותאמת, השתמש בברירת מחדל
    if ($stmt->rowCount() == 0) {
        $query = "SELECT * FROM message_templates WHERE type = :type AND is_default = 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":type", $template_type);
        $stmt->execute();
    }
    
    $template = $stmt->fetch();
    
    // עיבוד תבנית ההודעה
    $message_text = !empty($custom_message) ? $custom_message : $template['message_text'];
    $subject = $template['subject'];
    
    // החלפת פלייסהולדרים בטקסט
    $appointment_date = date('d/m/Y', strtotime($appointment['start_datetime']));
    $appointment_time = date('H:i', strtotime($appointment['start_datetime']));
    
    $confirmation_link = "https://" . $_SERVER['HTTP_HOST'] . "/confirm_appointment.php?id=" . $appointment_id . "&token=" . md5($appointment_id . '_' . $appointment['customer_id']);
    
    $placeholders = [
        '{first_name}' => $appointment['first_name'],
        '{service_name}' => $appointment['service_name'],
        '{appointment_date}' => $appointment_date,
        '{appointment_time}' => $appointment_time,
        '{staff_name}' => $appointment['staff_name'],
        '{confirmation_link}' => $confirmation_link
    ];
    
    foreach ($placeholders as $placeholder => $value) {
        $message_text = str_replace($placeholder, $value, $message_text);
        $subject = str_replace($placeholder, $value, $subject);
    }
    
    // שליחת הודעות
    $email_sent = false;
    $sms_sent = false;
    
    // שליחת אימייל (במידה והמשתמש בחר באפשרות זו וקיימת כתובת אימייל)
    if ($send_email && !empty($appointment['email'])) {
        // לוגיקת שליחת אימייל - בסביבה אמיתית נשתמש ב-PHPMailer או ספריית מייל אחרת
        /*
        require_once "../includes/PHPMailer/PHPMailer.php";
        require_once "../includes/PHPMailer/SMTP.php";
        require_once "../includes/PHPMailer/Exception.php";
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            // הגדרות שרת
            $mail->isSMTP();
            $mail->Host = 'smtp.example.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'user@example.com';
            $mail->Password = 'secret';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;
            $mail->CharSet = 'UTF-8';
            
            // נמענים
            $mail->setFrom('noreply@yourbusiness.com', $tenant['business_name']);
            $mail->addAddress($appointment['email'], $appointment['first_name'] . ' ' . $appointment['last_name']);
            
            // תוכן
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message_text;
            $mail->AltBody = strip_tags($message_text);
            
            $mail->send();
            $email_sent = true;
        } catch (Exception $e) {
            $email_sent = false;
        }
        */
        
        // לצורך הדוגמה, נניח ששליחת האימייל הצליחה
        $email_sent = true;
    }
    
    // שליחת SMS (במידה והמשתמש בחר באפשרות זו)
    if ($send_sms && !empty($appointment['phone'])) {
        // לוגיקת שליחת SMS - בסביבה אמיתית נשתמש בשירות SMS חיצוני
        /*
        $sms_api = new SMSProvider($api_key);
        $sms_sent = $sms_api->send([
            'to' => $appointment['phone'],
            'message' => strip_tags($message_text),
            'from' => $tenant['business_name']
        ]);
        */
        
        // לצורך הדוגמה, נניח ששליחת ה-SMS הצליחה
        $sms_sent = true;
    }
    
    // עדכון סטטוס התזכורת/אישור בטבלת התורים
    if ($notification_type == 'confirmation') {
        $query = "UPDATE appointments SET confirmation_sent = 1, confirmation_datetime = NOW() WHERE appointment_id = :appointment_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":appointment_id", $appointment_id);
        $stmt->execute();
    } elseif ($notification_type == 'reminder') {
        $query = "UPDATE appointments SET reminder_sent = 1, reminder_datetime = NOW() WHERE appointment_id = :appointment_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":appointment_id", $appointment_id);
        $stmt->execute();
    }
    
    // הוספת רשומה ללוג הודעות
    // בהנחה שיש טבלה בשם message_logs
    /*
    $query = "INSERT INTO message_logs (tenant_id, appointment_id, customer_id, type, message, email_sent, sms_sent, created_at)
              VALUES (:tenant_id, :appointment_id, :customer_id, :type, :message, :email_sent, :sms_sent, NOW())";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->bindParam(":appointment_id", $appointment_id);
    $stmt->bindParam(":customer_id", $appointment['customer_id']);
    $stmt->bindParam(":type", $notification_type);
    $stmt->bindParam(":message", $message_text);
    $stmt->bindParam(":email_sent", $email_sent, PDO::PARAM_BOOL);
    $stmt->bindParam(":sms_sent", $sms_sent, PDO::PARAM_BOOL);
    $stmt->execute();
    */
    
    echo json_encode([
        'success' => true,
        'message' => 'ההודעה נשלחה בהצלחה',
        'details' => [
            'email_sent' => $email_sent,
            'sms_sent' => $sms_sent,
            'recipient' => [
                'name' => $appointment['first_name'] . ' ' . $appointment['last_name'],
                'phone' => $appointment['phone'],
                'email' => $appointment['email']
            ],
            'notification_type' => $notification_type,
            'appointment_id' => $appointment_id
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'שגיאת מסד נתונים: ' . $e->getMessage()
    ]);
}