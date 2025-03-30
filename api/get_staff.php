<?php
// קובץ זה מחזיר את פרטי איש הצוות לפי ID

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

// קבלת פרמטר id
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'מזהה איש צוות חסר'
    ]);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$staff_id = intval($_GET['id']);

try {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();
    
    // שליפת פרטי איש הצוות
    $query = "SELECT * FROM staff WHERE staff_id = :staff_id AND tenant_id = :tenant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":staff_id", $staff_id);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'איש הצוות לא נמצא או אינו שייך לעסק שלך'
        ]);
        exit;
    }
    
    $staff = $stmt->fetch();
    
    // החזרת התשובה
    echo json_encode([
        'success' => true,
        'staff' => $staff
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'שגיאת מסד נתונים: ' . $e->getMessage()
    ]);
}
?>