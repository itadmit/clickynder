<?php
// Disable direct access to this file
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.0 403 Forbidden');
    echo 'גישה נדחתה';
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
    // אם אין מידע ב-JSON, ננסה לקחת מ-POST רגיל
    $data = $_POST;
}

// וידוא שכל השדות הנדרשים קיימים
$required_fields = ['tenant_id', 'service_id', 'staff_id', 'appointment_date', 'appointment_time', 
                    'first_name', 'last_name', 'phone'];

foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        echo json_encode([
            'success' => false,
            'message' => "השדה $field חסר או ריק",
            'field' => $field
        ]);
        exit;
    }
}

try {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();
    
    // סניטציה של שדות קלט
    $tenant_id = intval($data['tenant_id']);
    $service_id = intval($data['service_id']);
    $staff_id = intval($data['staff_id']);
    $appointment_date = sanitizeInput($data['appointment_date']);
    $appointment_time = sanitizeInput($data['appointment_time']);
    $first_name = sanitizeInput($data['first_name']);
    $last_name = sanitizeInput($data['last_name']);
    $phone = sanitizeInput($data['phone']);
    $email = isset($data['email']) ? sanitizeInput($data['email']) : null;
    $notes = isset($data['notes']) ? sanitizeInput($data['notes']) : null;
    
    // יצירת datetime מלא לתחילת התור
    $start_datetime = $appointment_date . ' ' . $appointment_time . ':00';
    
    // בדיקה שמדובר בתאריך ושעה תקינים ועתידיים
    $appointment_timestamp = strtotime($start_datetime);
    $current_timestamp = time();
    
    if ($appointment_timestamp === false) {
        echo json_encode([
            'success' => false,
            'message' => 'פורמט תאריך או שעה לא תקין'
        ]);
        exit;
    }
    
    if ($appointment_timestamp <= $current_timestamp) {
        echo json_encode([
            'success' => false,
            'message' => 'לא ניתן לקבוע תור בעבר או להרגע הנוכחי'
        ]);
        exit;
    }
    
    // קבלת פרטי השירות המבוקש (בעיקר כדי לחשב את זמן הסיום)
    $query = "SELECT duration, buffer_time_before, buffer_time_after FROM services 
              WHERE service_id = :service_id AND tenant_id = :tenant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":service_id", $service_id);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'השירות שנבחר אינו קיים או אינו שייך לעסק זה'
        ]);
        exit;
    }
    
    $service = $stmt->fetch();
    
    // חישוב זמן סיום התור
    $duration = intval($service['duration']);
    $buffer_before = intval($service['buffer_time_before']);
    $buffer_after = intval($service['buffer_time_after']);
    
    // התחלת התור כולל buffer_before
    $real_start = date('Y-m-d H:i:s', strtotime("-{$buffer_before} minutes", $appointment_timestamp));
    
    // סיום התור כולל buffer_after
    $end_datetime = date('Y-m-d H:i:s', strtotime("+{$duration} minutes +{$buffer_after} minutes", $appointment_timestamp));
    
    // בדיקה אם יש כבר תור באותו הזמן עם אותו נותן שירות
    $query = "SELECT appointment_id FROM appointments 
          WHERE staff_id = :staff_id 
          AND status IN ('pending', 'confirmed') 
          AND (
              (start_datetime <= :start_datetime AND end_datetime > :start_datetime) OR
              (start_datetime < :end_datetime AND end_datetime >= :end_datetime) OR
              (start_datetime >= :start_datetime AND end_datetime <= :end_datetime)
          )";
$stmt = $db->prepare($query);
$stmt->bindParam(":staff_id", $staff_id);
$stmt->bindParam(":start_datetime", $real_start);
$stmt->bindParam(":end_datetime", $end_datetime);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'המועד שנבחר כבר נתפס על ידי מישהו אחר. אנא בחר מועד אחר.'
    ]);
    exit;
}

    
    // בדיקה אם הלקוח כבר קיים במערכת (לפי טלפון)
    $query = "SELECT customer_id FROM customers 
              WHERE phone = :phone AND tenant_id = :tenant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":phone", $phone);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        // אם הלקוח קיים, קבל את ה-ID שלו
        $customer = $stmt->fetch();
        $customer_id = $customer['customer_id'];
        
        // עדכון פרטי לקוח במידת הצורך
        $query = "UPDATE customers SET 
                  first_name = :first_name,
                  last_name = :last_name,
                  email = :email,
                  updated_at = NOW()
                  WHERE customer_id = :customer_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":first_name", $first_name);
        $stmt->bindParam(":last_name", $last_name);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":customer_id", $customer_id);
        $stmt->execute();
    } else {
        // אם הלקוח לא קיים, צור לקוח חדש
        $query = "INSERT INTO customers (tenant_id, first_name, last_name, email, phone) 
                  VALUES (:tenant_id, :first_name, :last_name, :email, :phone)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":tenant_id", $tenant_id);
        $stmt->bindParam(":first_name", $first_name);
        $stmt->bindParam(":last_name", $last_name);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":phone", $phone);
        $stmt->execute();
        
        $customer_id = $db->lastInsertId();
    }
    
    // יצירת התור במסד הנתונים
    $query = "INSERT INTO appointments (tenant_id, customer_id, staff_id, service_id, 
              start_datetime, end_datetime, status, notes) 
              VALUES (:tenant_id, :customer_id, :staff_id, :service_id, 
              :start_datetime, :end_datetime, 'pending', :notes)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->bindParam(":customer_id", $customer_id);
    $stmt->bindParam(":staff_id", $staff_id);
    $stmt->bindParam(":service_id", $service_id);
    $stmt->bindParam(":start_datetime", $start_datetime);
    $stmt->bindParam(":end_datetime", $end_datetime);
    $stmt->bindParam(":notes", $notes);
    
    if ($stmt->execute()) {
        $appointment_id = $db->lastInsertId();
        
        // יצירת מזהה אסמכתא ייחודי
        $reference = generateBookingReference();
        
        // עדכון מזהה האסמכתא בטבלת התורים
        $query = "UPDATE appointments SET reference_id = :reference WHERE appointment_id = :appointment_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":reference", $reference);
        $stmt->bindParam(":appointment_id", $appointment_id);
        $stmt->execute();
        
        // בדיקה אם יש שאלות מקדימות והוספת תשובות
        if (isset($data['intake_answers']) && is_array($data['intake_answers'])) {
            foreach ($data['intake_answers'] as $question_id => $answer) {
                $query = "INSERT INTO intake_answers (appointment_id, question_id, answer_text) 
                          VALUES (:appointment_id, :question_id, :answer_text)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":appointment_id", $appointment_id);
                $stmt->bindParam(":question_id", $question_id);
                $stmt->bindParam(":answer_text", $answer);
                $stmt->execute();
            }
        }
        
        // שליחת אישור תור (יתווסף בהמשך)
        // sendBookingConfirmation($tenant_id, $appointment_id);
        
        // החזרת תשובה חיובית
        echo json_encode([
            'success' => true,
            'message' => 'התור נקבע בהצלחה',
            'appointment_id' => $appointment_id,
            'reference' => $reference,
            'start_datetime' => $start_datetime,
            'end_datetime' => $end_datetime
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'אירעה שגיאה ביצירת התור'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'שגיאת מסד נתונים: ' . $e->getMessage()
    ]);
}

// פונקציה ליצירת מזהה אסמכתא ייחודי
function generateBookingReference() {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $reference = '';
    for ($i = 0; i < 8; $i++) {
        $reference .= $chars[mt_rand(0, strlen($chars) - 1)];
    }
    return $reference;
}