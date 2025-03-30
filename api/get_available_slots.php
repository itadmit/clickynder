<?php
// API לקבלת סלוטים פנויים ליום ספציפי

// הגדרת סוג התוכן לJSON
header('Content-Type: application/json');

// כולל קבצי תצורה
require_once "../config/database.php";
require_once "../includes/functions.php";

// קבלת הפרמטרים
$tenant_id = isset($_GET['tenant_id']) ? intval($_GET['tenant_id']) : 0;
$service_id = isset($_GET['service_id']) ? intval($_GET['service_id']) : 0;
$staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : 0;
$date = isset($_GET['date']) ? sanitizeInput($_GET['date']) : date('Y-m-d');

// בדיקת פרמטרים חובה
if ($tenant_id == 0 || $service_id == 0) {
    echo json_encode([
        'success' => false,
        'message' => 'חסרים פרמטרים חובה (tenant_id, service_id)'
    ]);
    exit;
}

try {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();
    
    // קבלת פרטי השירות
    $query = "SELECT duration, buffer_time_before, buffer_time_after FROM services 
              WHERE service_id = :service_id AND tenant_id = :tenant_id AND is_active = 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":service_id", $service_id);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'השירות לא נמצא או אינו פעיל'
        ]);
        exit;
    }
    
    $service = $stmt->fetch();
    $service_duration = intval($service['duration']);
    $buffer_before = intval($service['buffer_time_before']);
    $buffer_after = intval($service['buffer_time_after']);
    
    // קבלת היום בשבוע
    $day_of_week = date('w', strtotime($date));
    
    // בדיקת שעות פעילות העסק
    $business_hours_query = "SELECT * FROM business_hours 
                            WHERE tenant_id = :tenant_id AND day_of_week = :day_of_week AND is_open = 1";
    $stmt = $db->prepare($business_hours_query);
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
    
    // בדיקה אם יש חריג לעסק
    $query = "SELECT * FROM business_hour_exceptions 
              WHERE tenant_id = :tenant_id AND exception_date = :date";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->bindParam(":date", $date);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $exception = $stmt->fetch();
        
        if (!$exception['is_open']) {
            // העסק סגור בתאריך זה
            echo json_encode([
                'success' => false,
                'message' => 'העסק סגור בתאריך זה',
                'slots' => []
            ]);
            exit;
        }
        
        // שימוש בשעות שהוגדרו בחריג
        $business_open = $exception['open_time'];
        $business_close = $exception['close_time'];
    } else {
        // שימוש בשעות רגילות
        $business_open = $business_hours['open_time'];
        $business_close = $business_hours['close_time'];
    }
    
    // הכנת רשימת אנשי צוות וזמינותם
    $staff_list = [];
    
    // אם נבחר איש צוות ספציפי
    if ($staff_id > 0) {
        // בדיקה שאיש הצוות שייך לטננט ומספק את השירות
        $query = "SELECT s.staff_id, s.name 
                  FROM staff s 
                  JOIN service_staff ss ON s.staff_id = ss.staff_id 
                  WHERE s.staff_id = :staff_id 
                  AND s.tenant_id = :tenant_id 
                  AND s.is_active = 1 
                  AND ss.service_id = :service_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":staff_id", $staff_id);
        $stmt->bindParam(":tenant_id", $tenant_id);
        $stmt->bindParam(":service_id", $service_id);
        $stmt->execute();
        
        if ($stmt->rowCount() == 0) {
            echo json_encode([
                'success' => false,
                'message' => 'איש הצוות לא נמצא, אינו פעיל, או אינו מספק את השירות המבוקש',
                'slots' => []
            ]);
            exit;
        }
        
        $staff_info = $stmt->fetch();
        $staff_list[] = $staff_info;
    } else {
        // מצא את כל אנשי הצוות שמספקים את השירות
        $query = "SELECT s.staff_id, s.name 
                  FROM staff s 
                  JOIN service_staff ss ON s.staff_id = ss.staff_id 
                  WHERE s.tenant_id = :tenant_id 
                  AND s.is_active = 1 
                  AND ss.service_id = :service_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":tenant_id", $tenant_id);
        $stmt->bindParam(":service_id", $service_id);
        $stmt->execute();
        
        $staff_list = $stmt->fetchAll();
        
        if (empty($staff_list)) {
            echo json_encode([
                'success' => false,
                'message' => 'לא נמצאו אנשי צוות שמספקים את השירות המבוקש',
                'slots' => []
            ]);
            exit;
        }
    }
    
    // קבלת זמינות לכל איש צוות ברשימה
    $staff_availability = [];
    foreach ($staff_list as $staff) {
        $current_staff_id = $staff['staff_id'];
        
        // בדיקה אם יש חריג לאיש הצוות
        $query = "SELECT * FROM staff_availability_exceptions 
                  WHERE staff_id = :staff_id AND exception_date = :date";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":staff_id", $current_staff_id);
        $stmt->bindParam(":date", $date);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $exception = $stmt->fetch();
            
            if (!$exception['is_available']) {
                // איש הצוות לא זמין בתאריך זה
                continue;
            }
            
            // שימוש בשעות שהוגדרו בחריג
            $staff_start = $exception['start_time'];
            $staff_end = $exception['end_time'];
        } else {
            // בדיקה אם איש הצוות זמין ביום זה
            $query = "SELECT * FROM staff_availability 
                      WHERE staff_id = :staff_id AND day_of_week = :day_of_week AND is_available = 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":staff_id", $current_staff_id);
            $stmt->bindParam(":day_of_week", $day_of_week);
            $stmt->execute();
            
            if ($stmt->rowCount() == 0) {
                // איש הצוות לא זמין ביום זה
                continue;
            }
            
            $availability = $stmt->fetch();
            $staff_start = $availability['start_time'];
            $staff_end = $availability['end_time'];
        }
        
        // חישוב זמן עבודה אפקטיבי - חיתוך בין שעות העסק לשעות איש הצוות
        $effective_start = max(strtotime($business_open), strtotime($staff_start));
        $effective_end = min(strtotime($business_close), strtotime($staff_end));
        
        // בדיקה שיש מספיק זמן לתור
        $service_length = ($service_duration + $buffer_before + $buffer_after) * 60;
        
        if (($effective_end - $effective_start) < $service_length) {
            // אין מספיק זמן לתור
            continue;
        }
        
        $staff_availability[$current_staff_id] = [
            'start' => date('H:i:s', $effective_start),
            'end' => date('H:i:s', $effective_end)
        ];
    }
    
    if (empty($staff_availability)) {
        echo json_encode([
'success' => false,
           'message' => 'אין אנשי צוות זמינים בתאריך זה',
           'slots' => []
       ]);
       exit;
   }
   
   // קבלת כל התורים הקיימים ליום זה
   $query = "SELECT start_datetime, end_datetime, staff_id 
             FROM appointments 
             WHERE tenant_id = :tenant_id 
             AND DATE(start_datetime) = :date
             AND status IN ('pending', 'confirmed')";
   
   if ($staff_id > 0) {
       $query .= " AND staff_id = :staff_id";
   } else {
       $staff_ids = implode(',', array_keys($staff_availability));
       if (!empty($staff_ids)) {
           $query .= " AND staff_id IN ($staff_ids)";
       }
   }
   
   $stmt = $db->prepare($query);
   $stmt->bindParam(":tenant_id", $tenant_id);
   $stmt->bindParam(":date", $date);
   
   if ($staff_id > 0) {
       $stmt->bindParam(":staff_id", $staff_id);
   }
   
   $stmt->execute();
   $existing_appointments = $stmt->fetchAll();
   
   // ארגון התורים לפי איש צוות
   $booked_slots = [];
   foreach ($existing_appointments as $appointment) {
       $staff = $appointment['staff_id'];
       
       if (!isset($booked_slots[$staff])) {
           $booked_slots[$staff] = [];
       }
       
       $booked_slots[$staff][] = [
           'start' => $appointment['start_datetime'],
           'end' => $appointment['end_datetime']
       ];
   }
   
   // חישוב סלוטים פנויים
   $available_slots = [];
   $slot_interval = 15; // רווח בין סלוטים (דקות)
   
   foreach ($staff_availability as $current_staff_id => $hours) {
       $start_time = strtotime($date . ' ' . $hours['start']);
       $end_time = strtotime($date . ' ' . $hours['end']);
       
       // הוסף רווח להתחלה בשביל buffer_before
       $start_time += $buffer_before * 60;
       
       // הורד רווח לסוף בשביל service_duration ו-buffer_after
       $end_time -= ($service_duration + $buffer_after) * 60;
       
       // חישוב סלוטים אפשריים
       $current_time = $start_time;
       
       while ($current_time <= $end_time) {
           $slot_start_time = date('H:i:s', $current_time);
           $slot_end_time = date('H:i:s', $current_time + ($service_duration * 60));
           
           // בדיקה אם הסלוט פנוי
           $is_available = true;
           
           if (isset($booked_slots[$current_staff_id])) {
               foreach ($booked_slots[$current_staff_id] as $booked) {
                   $booked_start = strtotime($booked['start']);
                   $booked_end = strtotime($booked['end']);
                   
                   // בדיקת חפיפה עם buffer
                   $slot_start_with_buffer = $current_time - ($buffer_before * 60);
                   $slot_end_with_buffer = $current_time + ($service_duration * 60) + ($buffer_after * 60);
                   
                   if (
                       ($slot_start_with_buffer >= $booked_start && $slot_start_with_buffer < $booked_end) ||
                       ($slot_end_with_buffer > $booked_start && $slot_end_with_buffer <= $booked_end) ||
                       ($slot_start_with_buffer <= $booked_start && $slot_end_with_buffer >= $booked_end)
                   ) {
                       $is_available = false;
                       break;
                   }
               }
           }
           
           // אם זה היום הנוכחי, בדוק שהסלוט הוא בעתיד
           if ($date == date('Y-m-d')) {
               // הוסף שעה למניעת תורים בהתראה קצרה מדי
               if ($current_time <= time() + 3600) {
                   $is_available = false;
               }
           }
           
           if ($is_available) {
               $slot_time = date('H:i', $current_time);
               
               if (!isset($available_slots[$slot_time])) {
                   $available_slots[$slot_time] = [];
               }
               
               $available_slots[$slot_time][] = [
                   'staff_id' => $current_staff_id,
                   'staff_name' => getStaffName($current_staff_id, $staff_list),
                   'start_time' => $slot_time,
                   'end_time' => date('H:i', $current_time + ($service_duration * 60))
               ];
           }
           
           $current_time += $slot_interval * 60;
       }
   }
   
   // מיון הסלוטים לפי זמן
   ksort($available_slots);
   
   // שטוח את מערך הסלוטים
   $flat_slots = [];
   foreach ($available_slots as $time => $staff_slots) {
       $flat_slots[] = [
           'time' => $time,
           'staff_options' => $staff_slots
       ];
   }
   
   echo json_encode([
       'success' => true,
       'date' => $date,
       'service_id' => $service_id,
       'service_duration' => $service_duration,
       'slots' => $flat_slots
   ]);
   
} catch (PDOException $e) {
   echo json_encode([
       'success' => false,
       'message' => 'שגיאת מסד נתונים: ' . $e->getMessage(),
       'slots' => []
   ]);
}

// פונקציה למציאת שם איש צוות לפי מזהה
function getStaffName($staff_id, $staff_list) {
   foreach ($staff_list as $staff) {
       if ($staff['staff_id'] == $staff_id) {
           return $staff['name'];
       }
   }
   return '';
}
?>