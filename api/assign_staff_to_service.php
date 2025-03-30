<?php
// קובץ זה מטפל בשיוך אנשי צוות לשירות

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
if (!isset($data['service_id']) || empty($data['service_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'מזהה שירות חסר'
    ]);
    exit;
}

// בדיקה שיש מערך אנשי צוות
if (!isset($data['staff_ids']) || !is_array($data['staff_ids'])) {
    echo json_encode([
        'success' => false,
        'message' => 'פרמטר staff_ids חסר או לא תקין'
    ]);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$service_id = intval($data['service_id']);
$staff_ids = array_map('intval', $data['staff_ids']);

try {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();
    
    // בדיקה שהשירות שייך לטננט
    $query = "SELECT service_id FROM services WHERE service_id = :service_id AND tenant_id = :tenant_id";
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
    
    // מחיקת כל השיוכים הקיימים
    $query = "DELETE FROM service_staff WHERE service_id = :service_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":service_id", $service_id);
    $stmt->execute();
    
    // הוספת השיוכים החדשים
    $success = true;
    foreach ($staff_ids as $staff_id) {
        // בדיקה שאיש הצוות שייך לטננט
        $query = "SELECT staff_id FROM staff WHERE staff_id = :staff_id AND tenant_id = :tenant_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":staff_id", $staff_id);
        $stmt->bindParam(":tenant_id", $tenant_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $query = "INSERT INTO service_staff (service_id, staff_id) VALUES (:service_id, :staff_id)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":service_id", $service_id);
            $stmt->bindParam(":staff_id", $staff_id);
            
            if (!$stmt->execute()) {
                $success = false;
            }
        }
    }
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'אנשי הצוות שויכו לשירות בהצלחה',
            'staff_count' => count($staff_ids)
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'אירעה שגיאה בשיוך חלק מאנשי הצוות'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'שגיאת מסד נתונים: ' . $e->getMessage()
    ]);
}
?>