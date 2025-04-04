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

// קבלת הנתונים שנשלחו
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['branch_id']) || empty($data['branch_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'מזהה סניף הוא שדה חובה']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // בדיקה שהסניף לא ראשי
    $query = "SELECT is_main FROM branches WHERE branch_id = :branch_id AND tenant_id = :tenant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":branch_id", $data['branch_id']);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$branch) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'הסניף לא נמצא']);
        exit;
    }
    
    if ($branch['is_main']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'לא ניתן למחוק את הסניף הראשי']);
        exit;
    }
    
    // התחלת טרנזקציה
    $db->beginTransaction();
    
    // מחיקת הסניף
    $query = "DELETE FROM branches WHERE branch_id = :branch_id AND tenant_id = :tenant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":branch_id", $data['branch_id']);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    
    // אישור הטרנזקציה
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'הסניף נמחק בהצלחה'
    ]);
    
} catch (Exception $e) {
    // ביטול הטרנזקציה במקרה של שגיאה
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'שגיאה במחיקת הסניף: ' . $e->getMessage()
    ]);
} 