<?php
// API להוספת תור חדש

// בדיקת התחברות
session_start();
if (!isset($_SESSION['tenant_id'])) {
    header('HTTP/1.0 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'לא מחובר']);
    exit;
}

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
$required_fields = ['customer_id', 'service_id', 'staff_id', 'start_datetime'];

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

// לקיחת ערכים מהבקשה
$tenant_id = $_SESSION['tenant_id'];
$customer_id = intval($data['customer_id']);
$service_id = intval($data['service_id']);
$staff_id = intval($data['staff_id']);
$start_datetime = sanitizeInput($data['start_datetime']);
$notes = isset($data['notes']) ? sanitizeInput($data['notes']) : null;
$status = isset($data['status']) ? sanitizeInput($data['status']) : 'pending';

try {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();
    
    // בדיקה שהלקוח, השירות ונותן השירות שייכים לטננט
    $query = "SELECT COUNT(*) as count FROM customers WHERE customer_id = :customer_id AND tenant_id = :tenant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":customer_id", $customer_id);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    $customer_exists = ($stmt->fetch()['count'] > 0);
    
    if (!$customer_exists) {
        echo json_encode([
            'success' => false,
            'message' => 'הלקוח שנבחר אינו שייך לעסק שלך'
        ]);
        exit;
    }
    
    $query = "SELECT duration FROM services WHERE service_id = :service_id AND tenant_id = :tenant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":service_id", $service_id);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'השירות שנבחר אינו שייך לעסק שלך'
        ]);
        exit;
    }
    
    $service = $stmt->fetch();
    $duration = intval($service['duration']);
    
    $query = "SELECT staff_id FROM staff WHERE staff_id = :staff_id AND tenant_id = :tenant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":staff_id", $staff_id);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'נותן השירות שנבחר אינו שייך לעסק שלך'
        ]);
        exit;
    }
    
    // חישוב זמן סיום התור
    $end_datetime = date('Y-m-d H:i:s', strtotime($start_datetime) + ($duration * 60));
    
    // בדיקה שאין תורים מתנגשים
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
    $stmt->bindParam(":start_datetime", $start_datetime);
    $stmt->bindParam(":end_datetime", $end_datetime);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'המועד שנבחר כבר תפוס על ידי תור אחר'
        ]);
        exit;
    }
    
    // הוספת התור לבסיס הנתונים
    $query = "INSERT INTO appointments (tenant_id, customer_id, staff_id, service_id, 
              start_datetime, end_datetime, status, notes) 
              VALUES (:tenant_id, :customer_id, :staff_id, :service_id, 
              :start_datetime, :end_datetime, :status, :notes)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->bindParam(":customer_id", $customer_id);
    $stmt->bindParam(":staff_id", $staff_id);
    $stmt->bindParam(":service_id", $service_id);
    $stmt->bindParam(":start_datetime", $start_datetime);
    $stmt->bindParam(":end_datetime", $end_datetime);
    $stmt->bindParam(":status", $status);
    $stmt->bindParam(":notes", $notes);
    
    if ($stmt->execute()) {
        $appointment_id = $db->lastInsertId();
        
        // אם יש שאלות מקדימות, שמור את התשובות
        if (isset($data['intake_answers']) && is_array($data['intake_answers']) && !empty($data['intake_answers'])) {
            foreach ($data['intake_answers'] as $question_id => $answer) {
                $question_id = intval($question_id);
                $answer_text = sanitizeInput($answer);
                
                $query = "INSERT INTO intake_answers (appointment_id, question_id, answer_text) 
                          VALUES (:appointment_id, :question_id, :answer_text)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":appointment_id", $appointment_id);
                $stmt->bindParam(":question_id", $question_id);
                $stmt->bindParam(":answer_text", $answer_text);
                $stmt->execute();
            }
        }
        
        // שליפת פרטי התור שנוצר
        $query = "SELECT a.*, c.first_name, c.last_name, s.name as service_name, st.name as staff_name 
                  FROM appointments a
                  JOIN customers c ON a.customer_id = c.customer_id
                  JOIN services s ON a.service_id = s.service_id
                  JOIN staff st ON a.staff_id = st.staff_id
                  WHERE a.appointment_id = :appointment_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":appointment_id", $appointment_id);
        $stmt->execute();
        $appointment = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'message' => 'התור נוסף בהצלחה',
            'appointment_id' => $appointment_id,
            'appointment' => [
                'id' => $appointment_id,
                'customer_name' => $appointment['first_name'] . ' ' . $appointment['last_name'],
                'service_name' => $appointment['service_name'],
                'staff_name' => $appointment['staff_name'],
                'start_datetime' => $appointment['start_datetime'],
                'end_datetime' => $appointment['end_datetime'],
                'status' => $appointment['status']
            ]
        ]);
        
        // כאן ניתן להוסיף קוד לשליחת אישור תור ללקוח
        
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'שגיאה בהוספת התור'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'שגיאת מסד נתונים: ' . $e->getMessage()
    ]);
}
?>