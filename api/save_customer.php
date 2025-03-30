<?php
// קובץ זה מטפל בשמירת פרטי לקוח (הוספה/עדכון)

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

// בדיקת שיטת הבקשה
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'שיטת בקשה לא מורשית'
    ]);
    exit;
}

// קבלת ה-tenant_id מהסשן
$tenant_id = $_SESSION['tenant_id'];

// סניטציה וולידציה של קלט
$customer_id = isset($_POST['customer_id']) && !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
$first_name = sanitizeInput($_POST['first_name']);
$last_name = sanitizeInput($_POST['last_name']);
$phone = sanitizeInput($_POST['phone']);
$email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : '';
$notes = isset($_POST['notes']) ? sanitizeInput($_POST['notes']) : '';

// בדיקת שדות חובה
if (empty($first_name) || empty($last_name) || empty($phone)) {
    echo json_encode([
        'success' => false,
        'message' => 'יש למלא את כל שדות החובה'
    ]);
    exit;
}

// בדיקת תקינות מספר טלפון
$phone = preg_replace('/[^0-9]/', '', $phone); // השאר רק ספרות
if (!(preg_match('/^0[23489]\d{7}$/', $phone) || preg_match('/^05\d{8}$/', $phone))) {
    echo json_encode([
        'success' => false,
        'message' => 'מספר טלפון לא תקין'
    ]);
    exit;
}

// בדיקת תקינות אימייל (אם הוזן)
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'כתובת אימייל לא תקינה'
    ]);
    exit;
}

try {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();
    
    // בדיקה אם מספר הטלפון כבר קיים (ללקוח אחר)
    $query = "SELECT customer_id FROM customers 
              WHERE phone = :phone AND tenant_id = :tenant_id";
    
    if ($customer_id > 0) {
        $query .= " AND customer_id != :customer_id";
    }
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":phone", $phone);
    $stmt->bindParam(":tenant_id", $tenant_id);
    
    if ($customer_id > 0) {
        $stmt->bindParam(":customer_id", $customer_id);
    }
    
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'מספר הטלפון כבר קיים במערכת'
        ]);
        exit;
    }
    
    // עדכון לקוח קיים
    if ($customer_id > 0) {
        // בדיקה שהלקוח שייך לטננט
        $query = "SELECT customer_id FROM customers WHERE customer_id = :customer_id AND tenant_id = :tenant_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":customer_id", $customer_id);
        $stmt->bindParam(":tenant_id", $tenant_id);
        $stmt->execute();
        
        if ($stmt->rowCount() == 0) {
            echo json_encode([
                'success' => false,
                'message' => 'הלקוח לא נמצא או אינו שייך לעסק שלך'
            ]);
            exit;
        }
        
        // עדכון פרטי הלקוח
        $query = "UPDATE customers SET 
                 first_name = :first_name,
                 last_name = :last_name,
                 phone = :phone,
                 email = :email,
                 notes = :notes,
                 updated_at = NOW()
                 WHERE customer_id = :customer_id AND tenant_id = :tenant_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":first_name", $first_name);
        $stmt->bindParam(":last_name", $last_name);
        $stmt->bindParam(":phone", $phone);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":notes", $notes);
        $stmt->bindParam(":customer_id", $customer_id);
        $stmt->bindParam(":tenant_id", $tenant_id);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'הלקוח עודכן בהצלחה',
                'customer_id' => $customer_id
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'אירעה שגיאה בעדכון הלקוח'
            ]);
        }
    }
    // הוספת לקוח חדש
    else {
        $query = "INSERT INTO customers (tenant_id, first_name, last_name, phone, email, notes, created_at, updated_at) 
                 VALUES (:tenant_id, :first_name, :last_name, :phone, :email, :notes, NOW(), NOW())";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":tenant_id", $tenant_id);
        $stmt->bindParam(":first_name", $first_name);
        $stmt->bindParam(":last_name", $last_name);
        $stmt->bindParam(":phone", $phone);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":notes", $notes);
        
        if ($stmt->execute()) {
            $new_customer_id = $db->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'הלקוח נוסף בהצלחה',
                'customer_id' => $new_customer_id
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
?>