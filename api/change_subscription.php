<?php
// API לשינוי מסלול מנוי

// התחלת או המשך סשן
session_start();

// טעינת קבצי התצורה
require_once "../config/database.php";
require_once "../includes/functions.php";

// בדיקה האם המשתמש מחובר
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'משתמש לא מחובר'
    ]);
    exit;
}

// הגדרת הדר ל-JSON
header('Content-Type: application/json');

// קבלת ה-tenant_id מהסשן
$tenant_id = $_SESSION['tenant_id'];

// קבלת מזהה המסלול החדש
$subscription_id = isset($_POST['subscription_id']) ? intval($_POST['subscription_id']) : 0;

if ($subscription_id === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'מזהה מסלול לא תקין'
    ]);
    exit;
}

try {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();

    // בדיקה שהמסלול קיים
    $query = "SELECT * FROM subscriptions WHERE id = :subscription_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":subscription_id", $subscription_id);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'המסלול המבוקש לא נמצא'
        ]);
        exit;
    }

    $new_subscription = $stmt->fetch();

    // בדיקת מגבלת אנשי צוות
    $query = "SELECT COUNT(*) as staff_count FROM staff WHERE tenant_id = :tenant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    $staff_count = $stmt->fetch()['staff_count'];

    if ($staff_count > $new_subscription['max_staff_members']) {
        echo json_encode([
            'success' => false,
            'message' => 'לא ניתן לעבור למסלול זה - יש לך יותר אנשי צוות ממה שהמסלול מאפשר'
        ]);
        exit;
    }

    // עדכון המסלול
    $query = "UPDATE tenant_subscriptions 
              SET subscription_id = :subscription_id,
                  updated_at = NOW()
              WHERE tenant_id = :tenant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":subscription_id", $subscription_id);
    $stmt->bindParam(":tenant_id", $tenant_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'המסלול עודכן בהצלחה'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'אירעה שגיאה בעדכון המסלול'
        ]);
    }

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'שגיאת מסד נתונים: ' . $e->getMessage()
    ]);
} 