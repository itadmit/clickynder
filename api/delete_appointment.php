<?php
// קובץ זה מוחק תור קיים

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
if (!isset($data['appointment_id']) || empty($data['appointment_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'מזהה תור חסר'
    ]);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$appointment_id = intval($data['appointment_id']);

try {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();
    
    // בדיקה שהתור שייך לטננט הנוכחי
    $query = "SELECT * FROM appointments WHERE appointment_id = :appointment_id AND tenant_id = :tenant_id";
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
    
    // מחיקת התור
    $query = "DELETE FROM appointments WHERE appointment_id = :appointment_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":appointment_id", $appointment_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'התור נמחק בהצלחה'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'שגיאה במחיקת התור'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'שגיאת מסד נתונים: ' . $e->getMessage()
    ]);
}
?>