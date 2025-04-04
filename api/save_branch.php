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

// בדיקת תקינות הנתונים
if (!isset($data['name']) || empty(trim($data['name']))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'שם הסניף הוא שדה חובה']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // בדיקת מגבלת מסלול
    $query = "SELECT s.max_branches 
              FROM subscriptions s 
              INNER JOIN tenant_subscriptions ts ON s.subscription_id = ts.subscription_id 
              WHERE ts.tenant_id = :tenant_id AND ts.is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    $max_branches = $subscription['max_branches'] ?? 1;
    
    // ספירת סניפים קיימים
    $query = "SELECT COUNT(*) as count FROM branches WHERE tenant_id = :tenant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $current_branches = $result['count'];
    
    // אם זה סניף חדש, בדיקה שלא חורגים ממגבלת המסלול
    if (empty($data['branch_id']) && $current_branches >= $max_branches) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'הגעת למגבלת הסניפים במסלול שלך']);
        exit;
    }
    
    // התחלת טרנזקציה
    $db->beginTransaction();
    
    if (empty($data['branch_id'])) {
        // הוספת סניף חדש
        $query = "INSERT INTO branches (tenant_id, name, address, phone, email, description, is_active) 
                  VALUES (:tenant_id, :name, :address, :phone, :email, :description, :is_active)";
    } else {
        // עדכון סניף קיים
        $query = "UPDATE branches 
                  SET name = :name, 
                      address = :address, 
                      phone = :phone, 
                      email = :email, 
                      description = :description, 
                      is_active = :is_active 
                  WHERE branch_id = :branch_id AND tenant_id = :tenant_id";
    }
    
    $stmt = $db->prepare($query);
    
    // הוספת הפרמטרים
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->bindParam(":name", $data['name']);
    $stmt->bindParam(":address", $data['address']);
    $stmt->bindParam(":phone", $data['phone']);
    $stmt->bindParam(":email", $data['email']);
    $stmt->bindParam(":description", $data['description']);
    $stmt->bindParam(":is_active", $data['is_active']);
    
    if (!empty($data['branch_id'])) {
        $stmt->bindParam(":branch_id", $data['branch_id']);
    }
    
    $stmt->execute();
    
    // אם זה סניף חדש, נשמור את ה-ID שלו
    $branch_id = empty($data['branch_id']) ? $db->lastInsertId() : $data['branch_id'];
    
    // אישור הטרנזקציה
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => empty($data['branch_id']) ? 'הסניף נוצר בהצלחה' : 'הסניף עודכן בהצלחה',
        'branch_id' => $branch_id
    ]);
    
} catch (Exception $e) {
    // ביטול הטרנזקציה במקרה של שגיאה
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'שגיאה בשמירת הסניף: ' . $e->getMessage()
    ]);
} 