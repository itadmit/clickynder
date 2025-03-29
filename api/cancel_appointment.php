<?php
// API לביטול תור

// Set headers for JSON response
header('Content-Type: application/json');

// בדיקת שיטת בקשה (GET או POST)
$is_post = ($_SERVER['REQUEST_METHOD'] === 'POST');

// קבלת פרמטרים (מ-GET או POST)
if ($is_post) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        $data = $_POST;
    }
    
    $appointment_id = isset($data['appointment_id']) ? intval($data['appointment_id']) : 0;
    $customer_id = isset($data['customer_id']) ? intval($data['customer_id']) : 0;
    $token = isset($data['token']) ? sanitizeInput($data['token']) : '';
    $reason = isset($data['reason']) ? sanitizeInput($data['reason']) : '';
} else {
    $appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;
    $customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
    $token = isset($_GET['token']) ? sanitizeInput($_GET['token']) : '';
    $reason = isset($_GET['reason']) ? sanitizeInput($_GET['reason']) : '';
}

// בדיקת פרמטרים חובה
if ($appointment_id == 0) {
    echo json_encode([
        'success' => false,
        'message' => 'מזהה תור חסר'
    ]);
    exit;
}

// בדיקה שנשלח טוקן או מזהה לקוח
if (empty($token) && $customer_id == 0) {
    echo json_encode([
        'success' => false,
        'message' => 'נדרש טוקן אישור או מזהה לקוח'
    ]);
    exit;
}

// כולל קבצי תצורה
require_once "../config/database.php";
require_once "../includes/functions.php";

try {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();
    
    // שליפת התור
    $query = "SELECT a.*, c.phone, c.email, c.first_name, t.business_name 
              FROM appointments a
              JOIN customers c ON a.customer_id = c.customer_id
              JOIN tenants t ON a.tenant_id = t.tenant_id
              WHERE a.appointment_id = :appointment_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":appointment_id", $appointment_id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'התור לא נמצא'
        ]);
        exit;
    }
    
    $appointment = $stmt->fetch();
    
    // בדיקת תקינות הטוקן או מזהה הלקוח
    $is_valid = false;
    
    if (!empty($token)) {
        // בדיקת טוקן
        $expected_token = md5($appointment_id . '_' . $appointment['customer_id']);
        $is_valid = ($token === $expected_token);
    } else if ($customer_id > 0) {
        // בדיקת מזהה לקוח
        $is_valid = ($customer_id == $appointment['customer_id']);
    }
    
    if (!$is_valid) {
        echo json_encode([
            'success' => false,
            'message' => 'אימות נכשל. טוקן או מזהה לקוח לא תקינים.'
        ]);
        exit;
    }
    
    // בדיקה שהתור עדיין פעיל (לא בוטל או הושלם)
    if ($appointment['status'] == 'cancelled') {
        echo json_encode([
            'success' => false,
            'message' => 'התור כבר בוטל'
        ]);
        exit;
    }
    
    if ($appointment['status'] == 'completed') {
        echo json_encode([
            'success' => false,
            'message' => 'התור כבר הושלם ולא ניתן לבטל אותו'
        ]);
        exit;
    }
    
    if ($appointment['status'] == 'no_show') {
        echo json_encode([
            'success' => false,
            'message' => 'התור מסומן כ"לא הגיע" ולא ניתן לבטל אותו'
        ]);
        exit;
    }
    
    // בדיקה שהביטול מתבצע בזמן סביר לפני התור
    $appointmentTime = strtotime($appointment['start_datetime']);
    $currentTime = time();
    $hoursLeft = ($appointmentTime - $currentTime) / 3600;
    
    // למשל, בדיקה שנותרו לפחות 4 שעות עד התור
    $minHoursBeforeCancellation = 4;
    
    if ($hoursLeft < $minHoursBeforeCancellation) {
        echo json_encode([
            'success' => false,
            'message' => "לא ניתן לבטל תור פחות מ-$minHoursBeforeCancellation שעות לפני מועד התור. אנא צור קשר טלפוני עם העסק."
        ]);
        exit;
    }
    
    // עדכון סטטוס התור ל"מבוטל"
    $query = "UPDATE appointments 
              SET status = 'cancelled', 
                  notes = CONCAT(IFNULL(notes, ''), '\nביטול: ', :reason)
              WHERE appointment_id = :appointment_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":reason", $reason);
    $stmt->bindParam(":appointment_id", $appointment_id);
    
    if ($stmt->execute()) {
        // יצירת נתוני התור לתצוגה
        $appointmentDate = date('d/m/Y', strtotime($appointment['start_datetime']));
        $appointmentTime = date('H:i', strtotime($appointment['start_datetime']));
        
        // שליפת פרטי השירות
        $query = "SELECT name FROM services WHERE service_id = :service_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":service_id", $appointment['service_id']);
        $stmt->execute();
        $service = $stmt->fetch();
        $serviceName = $service['name'];
        
        echo json_encode([
            'success' => true,
            'message' => 'התור בוטל בהצלחה',
            'appointment' => [
                'id' => $appointment_id,
                'date' => $appointmentDate,
                'time' => $appointmentTime,
                'service' => $serviceName,
                'business' => $appointment['business_name'],
                'cancelled_at' => date('Y-m-d H:i:s')
            ]
        ]);
        
        // כאן ניתן להוסיף הודעת ביטול בSMS או אימייל
        
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'אירעה שגיאה בביטול התור'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'שגיאת מסד נתונים: ' . $e->getMessage()
    ]);
}
?>