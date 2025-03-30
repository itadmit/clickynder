<?php
// קובץ זה מחזיר את פרטי הלקוח לפי ID

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
        'message' => 'מזהה לקוח חסר'
    ]);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$customer_id = intval($_GET['id']);

try {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();
    
    // שליפת פרטי הלקוח
    $query = "SELECT * FROM customers WHERE customer_id = :customer_id AND tenant_id = :tenant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":customer_id", $customer_id);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'הלקוח לא נמצא או אינו שייך לעסק שלך'
        ]);
        exit;
    }
    
    $customer = $stmt->fetch();
    
    // החזרת התשובה
    echo json_encode([
        'success' => true,
        'customer' => $customer
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'שגיאת מסד נתונים: ' . $e->getMessage()
    ]);
}
?>