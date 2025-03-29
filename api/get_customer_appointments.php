<?php
// מחזיר את התורים של לקוח ספציפי

// Set headers for JSON response
header('Content-Type: application/json');

// בדיקת פרמטרים נדרשים
if (!isset($_GET['tenant_id']) || empty($_GET['tenant_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'מזהה עסק חסר'
    ]);
    exit;
}

if ((!isset($_GET['customer_id']) || empty($_GET['customer_id'])) && 
    (!isset($_GET['phone']) || empty($_GET['phone']))) {
    echo json_encode([
        'success' => false,
        'message' => 'נדרש מזהה לקוח או מספר טלפון'
    ]);
    exit;
}

// כולל קבצי תצורה
require_once "../config/database.php";
require_once "../includes/functions.php";

// קבלת פרמטרים
$tenant_id = intval($_GET['tenant_id']);
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
$phone = isset($_GET['phone']) ? sanitizeInput($_GET['phone']) : '';

// פרמטרים אופציונליים לסינון
$start_date = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date']) : date('Y-m-d');
$only_future = isset($_GET['only_future']) ? (bool)$_GET['only_future'] : true;
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

try {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();
    
    // אם סופק מספר טלפון, חפש את מזהה הלקוח
    if (empty($customer_id) && !empty($phone)) {
        $query = "SELECT customer_id FROM customers 
                  WHERE phone = :phone AND tenant_id = :tenant_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":phone", $phone);
        $stmt->bindParam(":tenant_id", $tenant_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $customer = $stmt->fetch();
            $customer_id = $customer['customer_id'];
        } else {
            // אם לא נמצא לקוח עם מספר הטלפון
            echo json_encode([
                'success' => false,
                'message' => 'לא נמצא לקוח עם מספר טלפון זה',
                'appointments' => []
            ]);
            exit;
        }
    }
    
    // בניית השאילתה עם תנאים
    $query = "SELECT a.*, 
              s.name as service_name, 
              s.duration as service_duration,
              s.price as service_price,
              st.name as staff_name,
              st.image_path as staff_image
              FROM appointments a
              JOIN services s ON a.service_id = s.service_id
              JOIN staff st ON a.staff_id = st.staff_id
              WHERE a.tenant_id = :tenant_id 
              AND a.customer_id = :customer_id";
    
    if ($only_future) {
        $query .= " AND a.start_datetime >= :start_date";
    }
    
    if (!empty($status_filter)) {
        $query .= " AND a.status = :status";
    }
    
    $query .= " ORDER BY a.start_datetime ASC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->bindParam(":customer_id", $customer_id);
    
    if ($only_future) {
        $stmt->bindParam(":start_date", $start_date);
    }
    
    if (!empty($status_filter)) {
        $stmt->bindParam(":status", $status_filter);
    }
    
    $stmt->execute();
    
    $appointments = [];
    
    while ($row = $stmt->fetch()) {
        // עיבוד נתוני התור
        $start_date = date('Y-m-d', strtotime($row['start_datetime']));
        $start_time = date('H:i', strtotime($row['start_datetime']));
        $end_time = date('H:i', strtotime($row['end_datetime']));
        
        // מידע נוסף על התור
        $status_text = '';
        $status_color = '';
        
        switch ($row['status']) {
            case 'pending':
                $status_text = 'ממתין לאישור';
                $status_color = '#f59e0b'; // כתום
                break;
            case 'confirmed':
                $status_text = 'מאושר';
                $status_color = '#10b981'; // ירוק
                break;
            case 'cancelled':
                $status_text = 'בוטל';
                $status_color = '#ef4444'; // אדום
                break;
            case 'completed':
                $status_text = 'הושלם';
                $status_color = '#3b82f6'; // כחול
                break;
            case 'no_show':
                $status_text = 'לא הגיע';
                $status_color = '#6b7280'; // אפור
                break;
            default:
                $status_text = $row['status'];
                $status_color = '#9ca3af'; // אפור בהיר
        }
        
        // בנה אובייקט תור
        $appointment = [
            'id' => $row['appointment_id'],
            'service_name' => $row['service_name'],
            'start_datetime' => $row['start_datetime'],
            'end_datetime' => $row['end_datetime'],
            'date' => $start_date,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'duration' => $row['service_duration'],
            'status' => $row['status'],
            'status_text' => $status_text,
            'status_color' => $status_color,
            'staff_name' => $row['staff_name'],
            'staff_image' => $row['staff_image'],
            'price' => $row['service_price'],
            'notes' => $row['notes'],
            'can_cancel' => canCancelAppointment($row['start_datetime'], $row['status']),
            'can_reschedule' => canRescheduleAppointment($row['start_datetime'], $row['status']),
            'confirmation_sent' => (bool)$row['confirmation_sent'],
            'reminder_sent' => (bool)$row['reminder_sent'],
            'created_at' => $row['created_at']
        ];
        
        $appointments[] = $appointment;
    }
    
    // קבלת פרטי הלקוח
    $query = "SELECT first_name, last_name, email, phone FROM customers 
              WHERE customer_id = :customer_id AND tenant_id = :tenant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":customer_id", $customer_id);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    
    $customer_info = null;
    if ($stmt->rowCount() > 0) {
        $customer_info = $stmt->fetch();
    }
    
    // מבנה התשובה
    $response = [
        'success' => true,
        'appointments' => $appointments,
        'customer' => $customer_info,
        'count' => count($appointments)
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'שגיאת מסד נתונים: ' . $e->getMessage(),
        'appointments' => []
    ]);
}

// בדיקה האם ניתן לבטל את התור (לפי זמן שנותר עד התור ומדיניות העסק)
function canCancelAppointment($start_datetime, $status) {
    // אם התור כבר בוטל או הושלם, לא ניתן לבטל
    if (in_array($status, ['cancelled', 'completed', 'no_show'])) {
        return false;
    }
    
    // המרה לזמן Unix
    $appointment_time = strtotime($start_datetime);
    $current_time = time();
    $hours_left = ($appointment_time - $current_time) / 3600;
    
    // לדוגמה: אפשר לבטל עד 4 שעות לפני התור
    return ($hours_left >= 4);
}

// בדיקה האם ניתן לשנות את התור
function canRescheduleAppointment($start_datetime, $status) {
    // אם התור בוטל, הושלם או הלקוח לא הגיע, לא ניתן לשנות
    if (in_array($status, ['cancelled', 'completed', 'no_show'])) {
        return false;
    }
    
    // המרה לזמן Unix
    $appointment_time = strtotime($start_datetime);
    $current_time = time();
    $hours_left = ($appointment_time - $current_time) / 3600;
    
    // לדוגמה: אפשר לשנות עד 12 שעות לפני התור
    return ($hours_left >= 12);
}
?>