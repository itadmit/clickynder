<?php
// קובץ זה מחזיר את פרטי השירות לפי ID

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

// קבלת פרמטר service_id
if (!isset($_GET['service_id']) || empty($_GET['service_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'מזהה שירות חסר'
    ]);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$service_id = intval($_GET['service_id']);

try {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();
    
    // שליפת פרטי השירות
    $query = "SELECT * FROM services WHERE service_id = :service_id AND tenant_id = :tenant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":service_id", $service_id);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'השירות לא נמצא או אינו שייך לעסק שלך'
        ]);
        exit;
    }
    
    $service = $stmt->fetch();
    
    // שליפת אנשי צוות שמספקים את השירות
    $staff = [];
    
    $query = "SELECT staff_id FROM service_staff WHERE service_id = :service_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":service_id", $service_id);
    $stmt->execute();
    
    while ($row = $stmt->fetch()) {
        $staff[] = $row['staff_id'];
    }
    
    // הוספת מערך אנשי צוות לתוצאה
    $service['staff'] = $staff;
    
    // החזרת התשובה
    echo json_encode([
        'success' => true,
        'service' => $service
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'שגיאת מסד נתונים: ' . $e->getMessage()
    ]);
}
?>