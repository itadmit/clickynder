<?php
/**
 * API לשליחת הודעה ללקוחות ברשימת המתנה על פינוי תור
 * מאוחסן ב: api/notify_waitlist.php
 */

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

// הגדרת headers עבור תשובת JSON
header('Content-Type: application/json');

// קבלת נתונים מה-POST
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    // אם אין מידע ב-JSON, ננסה לקחת מ-POST רגיל
    $data = $_POST;
}

// בדיקת פרמטרים חובה
if (!isset($data['waitlist_id']) || empty($data['waitlist_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'מזהה רשימת המתנה הוא שדה חובה'
    ]);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$waitlist_id = intval($data['waitlist_id']);
$custom_message = isset($data['message']) ? sanitizeInput($data['message']) : '';

try {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();
    
    // שליפת פרטי הרשומה ברשימת המתנה
    $query = "SELECT w.*, c.first_name, c.last_name, c.phone, c.email, s.name as service_name 
              FROM waitlist w 
              JOIN customers c ON w.customer_id = c.customer_id 
              JOIN services s ON w.service_id = s.service_id 
              WHERE w.id = :waitlist_id AND w.tenant_id = :tenant_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":waitlist_id", $waitlist_id);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'הפריט לא נמצא או אינו שייך לעסק שלך'
        ]);
        exit;
    }
    
    $waitlist_item = $stmt->fetch();
    
    // שליפת פרטי העסק
    $query = "SELECT business_name, phone, email FROM tenants WHERE tenant_id = :tenant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    $tenant = $stmt->fetch();
    
    // בניית ההודעה
    $message = $custom_message;
    if (empty($message)) {
        $message = "שלום {$waitlist_item['first_name']},\n\n";
        $message .= "אנו שמחים להודיע לך שהתפנה תור עבור השירות {$waitlist_item['service_name']}.\n";
        $message .= "נא ליצור קשר בהקדם כדי לקבוע תור.\n\n";
        $message .= "בברכה,\n{$tenant['business_name']}\n";
        $message .= "טלפון: {$tenant['phone']}";
    }
    
    // כאן יש להוסיף את קוד השליחה של SMS או WhatsApp
    // דוגמה פשוטה - הדמיית שליחה מוצלחת:
    $notification_sent = true;
    
    // אם ההודעה נשלחה, עדכון הסטטוס
    if ($notification_sent) {
        $query = "UPDATE waitlist SET status = 'notified' WHERE id = :waitlist_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":waitlist_id", $waitlist_id);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'ההודעה נשלחה בהצלחה',
            'notification_details' => [
                'customer' => $waitlist_item['first_name'] . ' ' . $waitlist_item['last_name'],
                'phone' => $waitlist_item['phone'],
                'service' => $waitlist_item['service_name']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'אירעה שגיאה בשליחת ההודעה'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'שגיאת מסד נתונים: ' . $e->getMessage()
    ]);
}
?>