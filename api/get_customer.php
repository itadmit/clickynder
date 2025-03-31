<?php
// API לחיפוש וקבלת רשימת לקוחות עבור autocomplete

// בדיקת התחברות
session_start();
if (!isset($_SESSION['tenant_id']) && !isset($_GET['tenant_id'])) {
    header('HTTP/1.0 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'לא מחובר או חסר מזהה עסק']);
    exit;
}

// כולל קבצי תצורה
require_once "../config/database.php";
require_once "../includes/functions.php";

// Set headers for JSON response
header('Content-Type: application/json');

// קביעת tenant_id
$tenant_id = isset($_SESSION['tenant_id']) ? $_SESSION['tenant_id'] : intval($_GET['tenant_id']);

// קבלת פרמטר חיפוש
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;

try {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();
    
    // בניית שאילתת חיפוש
    $query = "SELECT * FROM customers 
              WHERE tenant_id = :tenant_id 
              AND (
                  first_name LIKE :search OR 
                  last_name LIKE :search OR 
                  CONCAT(first_name, ' ', last_name) LIKE :search OR
                  phone LIKE :search
              ) 
              ORDER BY first_name, last_name ASC 
              LIMIT :limit";
    
    $stmt = $db->prepare($query);
    $search_param = "%$search%";
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->bindParam(":search", $search_param);
    $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $customers = $stmt->fetchAll();
    
    // מבנה התשובה
    $response = [
        'success' => true,
        'customers' => []
    ];
    
    foreach ($customers as $customer) {
        $response['customers'][] = [
            'customer_id' => $customer['customer_id'],
            'first_name' => $customer['first_name'],
            'last_name' => $customer['last_name'],
            'phone' => $customer['phone'],
            'email' => $customer['email'],
            'notes' => $customer['notes']
        ];
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'שגיאת מסד נתונים: ' . $e->getMessage(),
        'customers' => []
    ]);
}
?>