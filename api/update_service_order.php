<?php
// קובץ זה מעדכן את סדר התצוגה של שירותים

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
if (!isset($data['services']) || !is_array($data['services']) || empty($data['services'])) {
    echo json_encode([
        'success' => false,
        'message' => 'מערך שירותים חסר או לא תקין'
    ]);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$services = $data['services'];

try {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();
    
    // עדכון סדר השירותים
    $success = true;
    $updated_count = 0;
    
    foreach ($services as $index => $service_id) {
        $service_id = intval($service_id);
        $order = intval($index) + 1;  // התחל מ-1
        
        // בדיקה שהשירות שייך לטננט
        $query = "SELECT service_id FROM services WHERE service_id = :service_id AND tenant_id = :tenant_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":service_id", $service_id);
        $stmt->bindParam(":tenant_id", $tenant_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // עדכון סדר התצוגה
            $query = "UPDATE services SET display_order = :display_order WHERE service_id = :service_id AND tenant_id = :tenant_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":display_order", $order);
            $stmt->bindParam(":service_id", $service_id);
            $stmt->bindParam(":tenant_id", $tenant_id);
            
            if ($stmt->execute()) {
                $updated_count++;
            } else {
                $success = false;
            }
        }
    }
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'סדר השירותים עודכן בהצלחה',
            'updated_count' => $updated_count
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'אירעה שגיאה בעדכון סדר השירותים'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'שגיאת מסד נתונים: ' . $e->getMessage()
    ]);
}
?>