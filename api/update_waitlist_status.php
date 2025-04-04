<?php
// קובץ זה מטפל בעדכון סטטוס של פריט ברשימת המתנה

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
if (!isset($data['id']) || empty($data['id']) || !isset($data['status']) || empty($data['status'])) {
    echo json_encode([
        'success' => false,
        'message' => 'מזהה וסטטוס הם שדות חובה'
    ]);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$id = intval($data['id']);
$status = sanitizeInput($data['status']);

// בדיקת תקינות הסטטוס
$valid_statuses = ['waiting', 'notified', 'booked', 'expired'];
if (!in_array($status, $valid_statuses)) {
    echo json_encode([
        'success' => false,
        'message' => 'סטטוס לא תקין'
    ]);
    exit;
}

try {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();
    
    // בדיקה שהפריט שייך לטננט
    $query = "SELECT id FROM waitlist WHERE id = :id AND tenant_id = :tenant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $id);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'הפריט לא נמצא או אינו שייך לעסק שלך'
        ]);
        exit;
    }
    
    // עדכון הסטטוס
    $query = "UPDATE waitlist SET status = :status WHERE id = :id AND tenant_id = :tenant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":status", $status);
    $stmt->bindParam(":id", $id);
    $stmt->bindParam(":tenant_id", $tenant_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'סטטוס עודכן בהצלחה',
            'new_status' => $status
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'אירעה שגיאה בעדכון הסטטוס'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'שגיאת מסד נתונים: ' . $e->getMessage()
    ]);
}
?>