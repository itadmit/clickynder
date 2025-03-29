<?php
// קובץ זה בודק ומחזיר את הסלוטים הפנויים לתאריך, שירות ונותן שירות מסוימים

// מניעת גישה ישירה ללא קריאת API
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('HTTP/1.0 403 Forbidden');
    exit('גישה נדחתה');
}

// כולל קבצי תצורה
require_once "../config/database.php";
require_once "../includes/functions.php";

// Set headers for JSON response
header('Content-Type: application/json');

// קבלת הפרמטרים
$date = isset($_GET['date']) ? sanitizeInput($_GET['date']) : '';
$service_id = isset($_GET['service_id']) ? intval($_GET['service_id']) : 0;
$staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : 0;
$tenant_id = isset($_GET['tenant_id']) ? intval($_GET['tenant_id']) : 0;

if (empty($date) || $service_id == 0 || $tenant_id == 0) {
    echo json_encode([
        'success' => false,
        'message' => 'חסרים פרמטרים חובה',
        'slots' => []
    ]);
    exit;
}

try {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();
    
    // קבלת פרטי השירות
    $query = "SELECT duration, buffer_time_before, buffer_time_after FROM services 
              WHERE service_id = :service_id AND tenant_id = :tenant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":service_id", $service_id);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'השירות לא נמצא',
            'slots' => []
        ]);
        exit;
    }
    
    $service = $stmt->fetch();
    $duration = intval($service['duration']);
    $buffer_before = intval($service['buffer_time_before']);
    $buffer_after = intval($service['buffer_time_after']);
    
    // קבלת יום בשבוע
    $day_of_week = date('w', strtotime($date));
    
    // בדיקת שעות פעילות
    $query = "SELECT * FROM business_hours 
              WHERE tenant_id = :tenant_id AND day_of_week = :day_of_week AND is_open = 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->bindParam(":day_of_week", $day_of_week);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        // העסק סגור ביום זה
        echo json_encode([
            'success' => false,
            'message' => 'העסק סגור ביום זה',
            'slots' => []
        ]);
        exit;
    }
    
    $business_hours = $stmt->fetch();
    $open_time = $business_hours['open_time'];
    $close_time = $business_hours['close_time'];
    
    // בדיקת תורים קיימים לנותן השירות באותו היום
    $staff_condition = '';
    if ($staff_id > 0) {
        $staff_condition = "AND staff_id = :staff_id";
    }
    
    $query = "SELECT start_datetime, end_datetime 
              FROM appointments 
              WHERE tenant_id = :tenant_id 
              AND DATE(start_datetime) = :date
              AND status IN ('pending', 'confirmed')
              $staff_condition
              ORDER BY start_datetime ASC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->bindParam(":date", $date);
    if ($staff_id > 0) {
        $stmt->bindParam(":staff_id", $staff_id);
    }
    $stmt->execute();
    
    $existing_appointments = $stmt->fetchAll();
    
    // קבלת הפסקות
    $query = "SELECT start_time, end_time 
              FROM breaks 
              WHERE tenant_id = :tenant_id AND day_of_week = :day_of_week
              ORDER BY start_time ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->bindParam(":day_of_week", $day_of_week);
    $stmt->execute();
    
    $breaks = $stmt->fetchAll();
    
    // חישוב סלוטים אפשריים
    $slots = [];
    $slot_interval = 30; // רווח דקות בין סלוטים
    
    $current_time = strtotime($open_time);
    $end_time = strtotime($close_time);
    
    while ($current_time < $end_time) {
        $slot_start_time = date('H:i:s', $current_time);
        $slot_end_time = date('H:i:s', $current_time + ($duration * 60));
        
        // וידוא שהתור יסתיים לפני סגירת העסק
        if (strtotime($slot_end_time) > $end_time) {
            break;
        }
        
        // בדיקה אם הסלוט נופל בהפסקה
        $in_break = false;
        foreach ($breaks as $break) {
            if (
                (strtotime($slot_start_time) >= strtotime($break['start_time']) && 
                 strtotime($slot_start_time) < strtotime($break['end_time'])) ||
                (strtotime($slot_end_time) > strtotime($break['start_time']) && 
                 strtotime($slot_end_time) <= strtotime($break['end_time'])) ||
                (strtotime($slot_start_time) <= strtotime($break['start_time']) && 
                 strtotime($slot_end_time) >= strtotime($break['end_time']))
            ) {
                $in_break = true;
                break;
            }
        }
        
        if (!$in_break) {
            // בדיקה אם הסלוט מתנגש עם תור קיים
            $is_available = true;
            $full_start_datetime = $date . ' ' . $slot_start_time;
            $full_end_datetime = $date . ' ' . $slot_end_time;
            
            $slot_start_with_buffer = date('Y-m-d H:i:s', strtotime($full_start_datetime) - ($buffer_before * 60));
            $slot_end_with_buffer = date('Y-m-d H:i:s', strtotime($full_end_datetime) + ($buffer_after * 60));
            
            foreach ($existing_appointments as $appointment) {
                if (
                    (strtotime($slot_start_with_buffer) >= strtotime($appointment['start_datetime']) && 
                     strtotime($slot_start_with_buffer) < strtotime($appointment['end_datetime'])) ||
                    (strtotime($slot_end_with_buffer) > strtotime($appointment['start_datetime']) && 
                     strtotime($slot_end_with_buffer) <= strtotime($appointment['end_datetime'])) ||
                    (strtotime($slot_start_with_buffer) <= strtotime($appointment['start_datetime']) && 
                     strtotime($slot_end_with_buffer) >= strtotime($appointment['end_datetime']))
                ) {
                    $is_available = false;
                    break;
                }
            }
            
            // אם זה היום הנוכחי, בדוק שהסלוט הוא בעתיד
            if ($date == date('Y-m-d')) {
                // הוסף שעה למניעת תורים בהתראה קצרה מדי
                if (strtotime($full_start_datetime) <= time() + 3600) {
                    $is_available = false;
                }
            }
            
            if ($is_available) {
                $slots[] = date('H:i', $current_time);
            }
        }
        
        $current_time += $slot_interval * 60;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'סלוטים פנויים נטענו בהצלחה',
        'slots' => $slots
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'שגיאת מסד נתונים: ' . $e->getMessage(),
        'slots' => []
    ]);
}