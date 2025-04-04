<?php
// קובץ זה מחזיר את רשימת ההמתנה לפי פרמטרים שונים

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

// קבלת פרמטרים
$tenant_id = $_SESSION['tenant_id'];
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$service_id = isset($_GET['service_id']) ? intval($_GET['service_id']) : 0;
$staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : 0;
$sort = isset($_GET['sort']) ? sanitizeInput($_GET['sort']) : 'created_at';
$order = isset($_GET['order']) ? sanitizeInput($_GET['order']) : 'DESC';

try {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();
    
    // בניית שאילתה
    $query = "SELECT w.*, c.first_name, c.last_name, c.phone, c.email, s.name as service_name, 
              st.name as staff_name 
              FROM waitlist w 
              JOIN customers c ON w.customer_id = c.customer_id 
              JOIN services s ON w.service_id = s.service_id 
              LEFT JOIN staff st ON w.staff_id = st.staff_id 
              WHERE w.tenant_id = :tenant_id";
    
    // הוספת תנאי סינון
    if (!empty($status)) {
        $query .= " AND w.status = :status";
    }
    
    if ($service_id > 0) {
        $query .= " AND w.service_id = :service_id";
    }
    
    if ($staff_id > 0) {
        $query .= " AND w.staff_id = :staff_id";
    }
    
    // סידור התוצאות
    $query .= " ORDER BY w.$sort $order";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    
    if (!empty($status)) {
        $stmt->bindParam(":status", $status);
    }
    
    if ($service_id > 0) {
        $stmt->bindParam(":service_id", $service_id);
    }
    
    if ($staff_id > 0) {
        $stmt->bindParam(":staff_id", $staff_id);
    }
    
    $stmt->execute();
    $waitlist_items = $stmt->fetchAll();
    
    // עיבוד הנתונים להצגה נוחה יותר
    $processed_items = [];
    foreach ($waitlist_items as $item) {
        $processed_item = [
            'id' => $item['id'],
            'customer' => [
                'id' => $item['customer_id'],
                'name' => $item['first_name'] . ' ' . $item['last_name'],
                'phone' => $item['phone'],
                'email' => $item['email']
            ],
            'service' => [
                'id' => $item['service_id'],
                'name' => $item['service_name']
            ],
            'staff' => null,
            'preferred_date' => $item['preferred_date'],
            'preferred_time' => [
                'start' => $item['preferred_time_start'],
                'end' => $item['preferred_time_end']
            ],
            'status' => $item['status'],
            'notes' => $item['notes'],
            'created_at' => $item['created_at']
        ];
        
        // הוסף פרטי איש צוות אם נבחר
        if (!empty($item['staff_id'])) {
            $processed_item['staff'] = [
                'id' => $item['staff_id'],
                'name' => $item['staff_name']
            ];
        }
        
        $processed_items[] = $processed_item;
    }
    
    echo json_encode([
        'success' => true,
        'waitlist' => $processed_items,
        'count' => count($processed_items)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'שגיאת מסד נתונים: ' . $e->getMessage()
    ]);
}
?>