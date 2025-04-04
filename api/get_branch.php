<?php
// התחלת או המשך סשן
session_start();

// טעינת קבצי התצורה
require_once "../config/database.php";
require_once "../includes/functions.php";

// בדיקה האם המשתמש מחובר
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'משתמש לא מחובר']);
    exit;
}

// קבלת ה-tenant_id מהסשן
$tenant_id = $_SESSION['tenant_id'];

// קבלת מזהה הסניף מה-URL
$branch_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($branch_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'מזהה סניף לא תקין']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // שליפת פרטי הסניף
    $query = "SELECT * FROM branches WHERE branch_id = :branch_id AND tenant_id = :tenant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":branch_id", $branch_id);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$branch) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'הסניף לא נמצא']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'branch' => $branch
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'שגיאה בשליפת פרטי הסניף: ' . $e->getMessage()
    ]);
} 