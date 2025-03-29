<?php
// API לשינוי פרטי תור

// בדיקת שיטת הבקשה
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.0 405 Method Not Allowed');
    echo json_encode(['success' => false, 'message' => 'שיטת בקשה לא מורשית']);
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

// בדיקת פרמטרים חובה
if (!isset($data['appointment_id']) || empty($data['appointment_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'מזהה תור חסר'
    ]);
    exit;
}

if ((!isset($data['customer_id']) || empty($data['customer_id'])) && 
    (!isset($data['token']) || empty($data['token']))) {
    echo json_encode([
        'success' => false,
        'message' => 'נדרש מזהה לקוח או טוקן אישור'
    ]);
    exit;
}

// הגדרות הפרמטרים לשינוי תור
$appointment_id = intval($data['appointment_id']);
$customer_id = isset($data['customer_id']) ? intval($data['customer_id']) : 0;
$token = isset($data['token']) ? sanitizeInput($data['token']) : '';

// פרמטרים אופציונליים לעדכון
$new_date = isset($data['new_date']) ? sanitizeInput($data['new_date']) : null;
$new_time = isset($data['new_time']) ? sanitizeInput($data['new_time']) : null;
$new_service_id = isset($data['new_service_id']) ? intval($data['new_service_id']) : 0;
$new_staff_id = isset($data['new_staff_id']) ? intval($data['new_staff_id']) : 0;
$notes = isset($data['notes']) ? sanitizeInput($data['notes']) : null;

try {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();
    
    // שליפת התור הקיים
    $query = "SELECT a.*, t.tenant_id 
              FROM appointments a
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
    $tenant_id = $appointment['tenant_id'];
    
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
    if (in_array($appointment['status'], ['cancelled', 'completed', 'no_show'])) {
        $status_text = '';
        switch ($appointment['status']) {
            case 'cancelled': $status_text = 'בוטל'; break;
            case 'completed': $status_text = 'הושלם'; break;
            case 'no_show': $status_text = 'לא הגיע'; break;
        }
        
        echo json_encode([
            'success' => false,
            'message' => 'לא ניתן לשנות תור ש' . $status_text
        ]);
        exit;
    }
    
    // בדיקה שהשינוי מתבצע בזמן סביר לפני התור
    $appointmentTime = strtotime($appointment['start_datetime']);
    $currentTime = time();
    $hoursLeft = ($appointmentTime - $currentTime) / 3600;
    
    // למשל, בדיקה שנותרו לפחות 12 שעות עד התור
    $minHoursBeforeReschedule = 12;
    
    if ($hoursLeft < $minHoursBeforeReschedule) {
        echo json_encode([
            'success' => false,
            'message' => "לא ניתן לשנות תור פחות מ-$minHoursBeforeReschedule שעות לפני מועד התור. אנא צור קשר טלפוני עם העסק."
        ]);
        exit;
    }
    
    // שינוי פרטי התור - בדיקה אילו פרטים יש לשנות
    $updateFields = [];
    $updateParams = [];
    
    // אם יש תאריך ו/או שעה חדשים
    if ($new_date && $new_time) {
        $new_start_datetime = $new_date . ' ' . $new_time . ':00';
        $updateFields[] = "start_datetime = :start_datetime";
        $updateParams[':start_datetime'] = $new_start_datetime;
        
        // חישוב זמן סיום חדש
        if ($new_service_id > 0) {
            // אם גם השירות משתנה
            $query = "SELECT duration FROM services WHERE service_id = :service_id AND tenant_id = :tenant_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":service_id", $new_service_id);
            $stmt->bindParam(":tenant_id", $tenant_id);
        } else {
            // אם רק המועד משתנה
            $query = "SELECT duration FROM services WHERE service_id = :service_id AND tenant_id = :tenant_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":service_id", $appointment['service_id']);
            $stmt->bindParam(":tenant_id", $tenant_id);
        }
        
        $stmt->execute();
        $service = $stmt->fetch();
        $duration = intval($service['duration']);
        
        $end_datetime = date('Y-m-d H:i:s', strtotime($new_start_datetime) + ($duration * 60));
        $updateFields[] = "end_datetime = :end_datetime";
        $updateParams[':end_datetime'] = $end_datetime;
        
        // בדיקה שהזמן החדש פנוי
        // (כאן תוכל להוסיף בדיקה דומה לזו שמתבצעת ב-available_slots.php)
    }
    // אם רק התאריך משתנה
    else if ($new_date) {
        $old_time = date('H:i:s', strtotime($appointment['start_datetime']));
        $new_start_datetime = $new_date . ' ' . $old_time;
        $updateFields[] = "start_datetime = :start_datetime";
        $updateParams[':start_datetime'] = $new_start_datetime;
        
        // חישוב זמן סיום חדש
        $duration = 0;
        if ($new_service_id > 0) {
            $query = "SELECT duration FROM services WHERE service_id = :service_id AND tenant_id = :tenant_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":service_id", $new_service_id);
            $stmt->bindParam(":tenant_id", $tenant_id);
            $stmt->execute();
            $service = $stmt->fetch();
            $duration = intval($service['duration']);
        } else {
            $duration = (strtotime($appointment['end_datetime']) - strtotime($appointment['start_datetime'])) / 60;
        }
        
        $end_datetime = date('Y-m-d H:i:s', strtotime($new_start_datetime) + ($duration * 60));
        $updateFields[] = "end_datetime = :end_datetime";
        $updateParams[':end_datetime'] = $end_datetime;
    }
    // אם רק השעה משתנה
    else if ($new_time) {
        $old_date = date('Y-m-d', strtotime($appointment['start_datetime']));
        $new_start_datetime = $old_date . ' ' . $new_time . ':00';
        $updateFields[] = "start_datetime = :start_datetime";
        $updateParams[':start_datetime'] = $new_start_datetime;
        
        // חישוב זמן סיום חדש
        $duration = 0;
        if ($new_service_id > 0) {
            $query = "SELECT duration FROM services WHERE service_id = :service_id AND tenant_id = :tenant_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":service_id", $new_service_id);
            $stmt->bindParam(":tenant_id", $tenant_id);
            $stmt->execute();
            $service = $stmt->fetch();
            $duration = intval($service['duration']);
        } else {
            $duration = (strtotime($appointment['end_datetime']) - strtotime($appointment['start_datetime'])) / 60;
        }
        
        $end_datetime = date('Y-m-d H:i:s', strtotime($new_start_datetime) + ($duration * 60));
        $updateFields[] = "end_datetime = :end_datetime";
        $updateParams[':end_datetime'] = $end_datetime;
    }
    
    // אם יש שירות חדש
    if ($new_service_id > 0) {
        $updateFields[] = "service_id = :service_id";
        $updateParams[':service_id'] = $new_service_id;
    }
    
    // אם יש נותן שירות חדש
    if ($new_staff_id > 0) {
        $updateFields[] = "staff_id = :staff_id";
        $updateParams[':staff_id'] = $new_staff_id;
    }
    
    // אם יש הערות
    if ($notes !== null) {
        $updateFields[] = "notes = :notes";
        $updateParams[':notes'] = $notes;
    }
    
    // אם אין שום שדה לעדכון
    if (empty($updateFields)) {
        echo json_encode([
            'success' => false,
            'message' => 'לא סופקו נתונים לעדכון'
        ]);
        exit;
    }
    
    // עדכון התור
    $query = "UPDATE appointments 
              SET " . implode(', ', $updateFields) . ", 
                  updated_at = NOW(), 
                  status = 'confirmed'
              WHERE appointment_id = :appointment_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":appointment_id", $appointment_id);
    
    // הוספת כל הפרמטרים לשאילתה
    foreach ($updateParams as $param => $value) {
        $stmt->bindValue($param, $value);
    }
    
    if ($stmt->execute()) {
        // שליפת התור המעודכן
        $query = "SELECT a.*, s.name as service_name, st.name as staff_name
                  FROM appointments a
                  JOIN services s ON a.service_id = s.service_id
                  JOIN staff st ON a.staff_id = st.staff_id
                  WHERE a.appointment_id = :appointment_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":appointment_id", $appointment_id);
        $stmt->execute();
        $updated_appointment = $stmt->fetch();
        
        // פורמט התאריך והשעה לתצוגה
        $date = date('d/m/Y', strtotime($updated_appointment['start_datetime']));
        $time = date('H:i', strtotime($updated_appointment['start_datetime']));
        
        echo json_encode([
            'success' => true,
            'message' => 'התור עודכן בהצלחה',
            'appointment' => [
                'id' => $appointment_id,
                'date' => $date,
                'time' => $time,
                'service' => $updated_appointment['service_name'],
                'staff' => $updated_appointment['staff_name'],
                'start_datetime' => $updated_appointment['start_datetime'],
                'end_datetime' => $updated_appointment['end_datetime']
            ]
        ]);
        
        // כאן ניתן להוסיף הודעת עדכון בSMS או אימייל
        
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'אירעה שגיאה בעדכון התור'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'שגיאת מסד נתונים: ' . $e->getMessage()
    ]);
}
?>