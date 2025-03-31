<?php
// API להוספת לקוח חדש

// בדיקת התחברות
session_start();
if (!isset($_SESSION['tenant_id']) && !isset($_POST['tenant_id'])) {
    header('HTTP/1.0 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'לא מחובר או חסר מזהה עסק']);
    exit;
}

// כולל קבצי תצורה
require_once "../config/database.php";
require_once "../includes/functions.php";

// Set headers for JSON response
header('Content-Type: application/json');

// קבלת נתונים מה-POST
$first_name = isset($_POST['first_name']) ? sanitizeInput($_POST['first_name']) : '';
$last_name = isset($_POST['last_name']) ? sanitizeInput($_POST['last_name']) : '';
$phone = isset($_POST['phone']) ? sanitizeInput($_POST['phone']) : '';
$email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : null;
$notes = isset($_POST['notes']) ? sanitizeInput($_POST['notes']) : null;

// בדיקת שדות חובה
if (empty($first_name) || empty($last_name) || empty($phone)) {
    echo json_encode([
        'success' => false,
        'message' => 'חסרים שדות חובה (שם פרטי, שם משפחה וטלפון)'
    ]);
    exit;
}

// קביעת tenant_id
$tenant_id = isset($_SESSION['tenant_id']) ? $_SESSION['tenant_id'] : intval($_POST['tenant_id']);

try {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();
    
    // בדיקה אם לקוח עם מספר טלפון זה כבר קיים
    $query = "SELECT customer_id FROM customers WHERE phone = :phone AND tenant_id = :tenant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":phone", $phone);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $customer = $stmt->fetch();
        $customer_id = $customer['customer_id'];
        
        // עדכון פרטי לקוח קיים
        $query = "UPDATE customers SET 
                 first_name = :first_name,
                 last_name = :last_name,
                 email = :email,
                 notes = :notes,
                 updated_at = NOW()
                 WHERE customer_id = :customer_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":first_name", $first_name);
        $stmt->bindParam(":last_name", $last_name);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":notes", $notes);
        $stmt->bindParam(":customer_id", $customer_id);
        
        if ($stmt->execute()) {
            // קבלת פרטי הלקוח המעודכנים
            $query = "SELECT * FROM customers WHERE customer_id = :customer_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":customer_id", $customer_id);
            $stmt->execute();
            $updated_customer = $stmt->fetch();
            
            echo json_encode([
                'success' => true,
                'message' => 'פרטי הלקוח עודכנו בהצלחה',
                'customer_id' => $customer_id,
                'customer' => [
                    'first_name' => $updated_customer['first_name'],
                    'last_name' => $updated_customer['last_name'],
                    'phone' => $updated_customer['phone'],
                    'email' => $updated_customer['email'],
                    'notes' => $updated_customer['notes']
                ],
                'is_update' => true
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'אירעה שגיאה בעדכון פרטי הלקוח'
            ]);
        }
    } else {
        // הוספת לקוח חדש
        $query = "INSERT INTO customers (tenant_id, first_name, last_name, phone, email, notes) 
                 VALUES (:tenant_id, :first_name, :last_name, :phone, :email, :notes)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":tenant_id", $tenant_id);
        $stmt->bindParam(":first_name", $first_name);
        $stmt->bindParam(":last_name", $last_name);
        $stmt->bindParam(":phone", $phone);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":notes", $notes);
        
        if ($stmt->execute()) {
            $customer_id = $db->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'הלקוח נוסף בהצלחה',
                'customer_id' => $customer_id,
                'customer' => [
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'phone' => $phone,
                    'email' => $email,
                    'notes' => $notes
                ],
                'is_update' => false
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'אירעה שגיאה בהוספת הלקוח'
            ]);
        }
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'שגיאת מסד נתונים: ' . $e->getMessage()
    ]);
}