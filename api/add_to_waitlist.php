<?php
// API להוספת לקוח לרשימת המתנה

// Disable direct access to this file
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.0 403 Forbidden');
    echo 'גישה נדחתה';
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

// וידוא שכל השדות הנדרשים קיימים
$required_fields = ['tenant_id', 'service_id', 'first_name', 'last_name', 'phone'];

foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        echo json_encode([
            'success' => false,
            'message' => "השדה $field חסר או ריק",
            'field' => $field
        ]);
        exit;
    }
}

try {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();
    
    // סניטציה של שדות קלט
    $tenant_id = intval($data['tenant_id']);
    $service_id = intval($data['service_id']);
    $staff_id = isset($data['staff_id']) ? intval($data['staff_id']) : null;
    $first_name = sanitizeInput($data['first_name']);
    $last_name = sanitizeInput($data['last_name']);
    $phone = sanitizeInput($data['phone']);
    $email = isset($data['email']) ? sanitizeInput($data['email']) : null;
    $notes = isset($data['notes']) ? sanitizeInput($data['notes']) : null;
    $preferred_date = isset($data['preferred_date']) ? sanitizeInput($data['preferred_date']) : null;
    $preferred_time_start = isset($data['preferred_time_start']) ? sanitizeInput($data['preferred_time_start']) : null;
    $preferred_time_end = isset($data['preferred_time_end']) ? sanitizeInput($data['preferred_time_end']) : null;
    
    // בדיקה האם הלקוח כבר קיים במערכת (לפי טלפון)
    $query = "SELECT customer_id FROM customers 
              WHERE phone = :phone AND tenant_id = :tenant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":phone", $phone);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        // אם הלקוח קיים, קבל את ה-ID שלו
        $customer = $stmt->fetch();
        $customer_id = $customer['customer_id'];
        
        // עדכון פרטי לקוח במידת הצורך
        $query = "UPDATE customers SET 
                  first_name = :first_name,
                  last_name = :last_name,
                  email = :email,
                  updated_at = NOW()
                  WHERE customer_id = :customer_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":first_name", $first_name);
        $stmt->bindParam(":last_name", $last_name);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":customer_id", $customer_id);
        $stmt->execute();
    } else {
        // אם הלקוח לא קיים, צור לקוח חדש
        $query = "INSERT INTO customers (tenant_id, first_name, last_name, email, phone) 
                  VALUES (:tenant_id, :first_name, :last_name, :email, :phone)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":tenant_id", $tenant_id);
        $stmt->bindParam(":first_name", $first_name);
        $stmt->bindParam(":last_name", $last_name);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":phone", $phone);
        $stmt->execute();
        
        $customer_id = $db->lastInsertId();
    }
    
    // בדיקה האם הלקוח כבר נמצא ברשימת המתנה לשירות זה
    $query = "SELECT id FROM waitlist 
              WHERE tenant_id = :tenant_id 
              AND customer_id = :customer_id 
              AND service_id = :service_id 
              AND status = 'waiting'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->bindParam(":customer_id", $customer_id);
    $stmt->bindParam(":service_id", $service_id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'הלקוח כבר נמצא ברשימת ההמתנה לשירות זה'
        ]);
        exit;
    }
    
    // הוספה לרשימת המתנה
    $query = "INSERT INTO waitlist (tenant_id, customer_id, service_id, staff_id, 
              preferred_date, preferred_time_start, preferred_time_end, status, notes) 
              VALUES (:tenant_id, :customer_id, :service_id, :staff_id, 
              :preferred_date, :preferred_time_start, :preferred_time_end, 'waiting', :notes)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->bindParam(":customer_id", $customer_id);
    $stmt->bindParam(":service_id", $service_id);
    $stmt->bindParam(":staff_id", $staff_id);
    $stmt->bindParam(":preferred_date", $preferred_date);
    $stmt->bindParam(":preferred_time_start", $preferred_time_start);
    $stmt->bindParam(":preferred_time_end", $preferred_time_end);
    $stmt->bindParam(":notes", $notes);
    
    if ($stmt->execute()) {
        $waitlist_id = $db->lastInsertId();
        
        // TODO: שליחת הודעת אישור (יתווסף בהמשך)
        
        echo json_encode([
            'success' => true,
            'message' => 'הבקשה נוספה בהצלחה לרשימת ההמתנה',
            'waitlist_id' => $waitlist_id
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'אירעה שגיאה בהוספה לרשימת ההמתנה'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'שגיאת מסד נתונים: ' . $e->getMessage()
    ]);
}
?>