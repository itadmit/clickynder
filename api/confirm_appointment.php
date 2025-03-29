<?php
// API לאישור הגעה לתור

// Set headers for JSON response
header('Content-Type: application/json');

// בדיקה שנשלח מזהה תור
if (!isset($_GET['appointment_id']) || empty($_GET['appointment_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'מזהה תור חסר'
    ]);
    exit;
}

// בדיקה שנשלח טוקן ייחודי לאישור (או מזהה לקוח)
if ((!isset($_GET['token']) || empty($_GET['token'])) && 
    (!isset($_GET['customer_id']) || empty($_GET['customer_id']))) {
    echo json_encode([
        'success' => false,
        'message' => 'נדרש טוקן אישור או מזהה לקוח'
    ]);
    exit;
}

// כולל קבצי תצורה
require_once "../config/database.php";
require_once "../includes/functions.php";

// קבלת הפרמטרים
$appointment_id = intval($_GET['appointment_id']);
$token = isset($_GET['token']) ? sanitizeInput($_GET['token']) : '';
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

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
        // טוקן יכול להיות מאוחסן בשדה נוסף בטבלת התורים או מחושב 
        // לצורך הדוגמה, נניח שהטוקן נוצר מהאש של מזהה התור ומזהה הלקוח
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
            'message' => 'התור כבר בוטל ולא ניתן לאשר אותו'
        ]);
        exit;
    }
    
    if ($appointment['status'] == 'completed') {
        echo json_encode([
            'success' => false,
            'message' => 'התור כבר הושלם ולא ניתן לאשר אותו שוב'
        ]);
        exit;
    }
    
    // עדכון סטטוס התור ל"מאושר"
    $query = "UPDATE appointments 
              SET status = 'confirmed', 
                  confirmation_sent = 1, 
                  confirmation_datetime = NOW()
              WHERE appointment_id = :appointment_id";
    
    $stmt = $db->prepare($query);
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
        
        // שליפת פרטי נותן השירות
        $query = "SELECT name FROM staff WHERE staff_id = :staff_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":staff_id", $appointment['staff_id']);
        $stmt->execute();
        $staff = $stmt->fetch();
        $staffName = $staff['name'];
        
        echo json_encode([
            'success' => true,
            'message' => 'התור אושר בהצלחה',
            'appointment' => [
                'id' => $appointment_id,
                'date' => $appointmentDate,
                'time' => $appointmentTime,
                'service' => $serviceName,
                'staff' => $staffName,
                'business' => $appointment['business_name']
            ]
        ]);
        
        // כאן ניתן להוסיף הודעת אישור בSMS או אימייל
        
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'אירעה שגיאה באישור התור'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'שגיאת מסד נתונים: ' . $e->getMessage()
    ]);
}
?>