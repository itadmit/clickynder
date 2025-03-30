<?php
// API לקבלת תאריכים פנויים לשירות ואיש צוות מסוימים

// הגדרת סוג התוכן לJSON
header('Content-Type: application/json');

// כולל קבצי תצורה
require_once "../config/database.php";
require_once "../includes/functions.php";

// קבלת הפרמטרים
$tenant_id = isset($_GET['tenant_id']) ? intval($_GET['tenant_id']) : 0;
$service_id = isset($_GET['service_id']) ? intval($_GET['service_id']) : 0;
$staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : 0;
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

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
    
    // חישוב תחילת וסוף החודש
    $start_date = date('Y-m-01', strtotime("$year-$month-01"));
    $end_date = date('Y-m-t', strtotime("$year-$month-01"));
    
    // טווח תאריכים מכיל גם את החודש הבא (חלקית) לתצוגת לוח שנה
    $display_start = date('Y-m-d', strtotime('-7 days', strtotime($start_date)));
    $display_end = date('Y-m-d', strtotime('+7 days', strtotime($end_date)));
    
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
    
    // קבלת שעות פעילות העסק
    $query = "SELECT * FROM business_hours WHERE tenant_id = :tenant_id ORDER BY day_of_week";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    $business_hours = [];
    
    while ($row = $stmt->fetch()) {
        $business_hours[$row['day_of_week']] = $row;
    }
    
    // קבלת ימי חריגים של העסק
    $query = "SELECT * FROM business_hour_exceptions 
              WHERE tenant_id = :tenant_id 
              AND exception_date BETWEEN :start_date AND :end_date";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->bindParam(":start_date", $display_start);
    $stmt->bindParam(":end_date", $display_end);
    $stmt->execute();
    $business_exceptions = [];
    
    while ($row = $stmt->fetch()) {
        $business_exceptions[$row['exception_date']] = $row;
    }
    
    // אם נבחר איש צוות ספציפי
    $staff_availability = [];
    $staff_exceptions = [];
    
    if ($staff_id > 0) {
        // בדיקה שאיש הצוות שייך לטננט ומספק את השירות
        $query = "SELECT s.staff_id 
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
                'message' => 'איש הצוות לא נמצא, אינו פעיל, או אינו מספק את השירות המבוקש'
            ]);
            exit;
        }
        
        // קבלת שעות זמינות של איש הצוות
        $query = "SELECT * FROM staff_availability WHERE staff_id = :staff_id ORDER BY day_of_week";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":staff_id", $staff_id);
        $stmt->execute();
        
        while ($row = $stmt->fetch()) {
            $staff_availability[$row['day_of_week']] = $row;
        }
        
        // קבלת ימי חריגים של איש הצוות
        $query = "SELECT * FROM staff_availability_exceptions 
                  WHERE staff_id = :staff_id 
                  AND exception_date BETWEEN :start_date AND :end_date";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":staff_id", $staff_id);
        $stmt->bindParam(":start_date", $display_start);
        $stmt->bindParam(":end_date", $display_end);
        $stmt->execute();
        
        while ($row = $stmt->fetch()) {
            $staff_exceptions[$row['exception_date']] = $row;
        }
    } else {
        // אם לא נבחר איש צוות ספציפי, מצא את כל אנשי הצוות שמספקים את השירות
        $query = "SELECT s.staff_id 
                  FROM staff s 
                  JOIN service_staff ss ON s.staff_id = ss.staff_id 
                  WHERE s.tenant_id = :tenant_id 
                  AND s.is_active = 1 
                  AND ss.service_id = :service_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":tenant_id", $tenant_id);
        $stmt->bindParam(":service_id", $service_id);
        $stmt->execute();
        
        $available_staff = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($available_staff)) {
            echo json_encode([
                'success' => false,
                'message' => 'לא נמצאו אנשי צוות שמספקים את השירות המבוקש'
            ]);
            exit;
        }
    }
    
    // קבלת כל התורים הקיימים בטווח הזמן הנבחר
    $appointments_query = "SELECT start_datetime, end_datetime, staff_id 
                           FROM appointments 
                           WHERE tenant_id = :tenant_id 
                           AND DATE(start_datetime) BETWEEN :start_date AND :end_date
                           AND status IN ('pending', 'confirmed')";
    
    if ($staff_id > 0) {
        $appointments_query .= " AND staff_id = :staff_id";
    } elseif (!empty($available_staff)) {
        $staff_ids = implode(',', $available_staff);
        $appointments_query .= " AND staff_id IN ($staff_ids)";
    }
    
    $stmt = $db->prepare($appointments_query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->bindParam(":start_date", $display_start);
    $stmt->bindParam(":end_date", $display_end);
    
    if ($staff_id > 0) {
        $stmt->bindParam(":staff_id", $staff_id);
    }
    
    $stmt->execute();
    $appointments = $stmt->fetchAll();
    
    // ארגון התורים לפי תאריך ואיש צוות
    $booked_slots = [];
    foreach ($appointments as $appointment) {
        $date = date('Y-m-d', strtotime($appointment['start_datetime']));
        $staff = $appointment['staff_id'];
        
        if (!isset($booked_slots[$date])) {
            $booked_slots[$date] = [];
        }
        
        if (!isset($booked_slots[$date][$staff])) {
            $booked_slots[$date][$staff] = [];
        }
        
        $booked_slots[$date][$staff][] = [
            'start' => $appointment['start_datetime'],
            'end' => $appointment['end_datetime']
        ];
    }
    
    // עבור על כל התאריכים בטווח ובדוק אם יש סלוטים פנויים
    $available_dates = [];
    $date = $display_start;
    
    while ($date <= $display_end) {
        $day_of_week = date('w', strtotime($date));
        $month_num = date('n', strtotime($date));
        
        // הכנת נתוני התאריך
        $date_info = [
            'date' => $date,
            'day' => date('j', strtotime($date)),
            'month' => $month_num,
            'year' => date('Y', strtotime($date)),
            'day_of_week' => $day_of_week,
            'in_month' => ($month_num == $month),
            'available' => false,
            'reason' => 'unknown'
        ];
        
        // בדיקה אם התאריך בעבר
        if (strtotime($date) < strtotime(date('Y-m-d'))) {
            $date_info['available'] = false;
            $date_info['reason'] = 'past_date';
            $available_dates[] = $date_info;
            $date = date('Y-m-d', strtotime($date . ' +1 day'));
            continue;
        }
        
        // בדיקה אם יש חריג לעסק
        if (isset($business_exceptions[$date])) {
            $exception = $business_exceptions[$date];
            
            if (!$exception['is_open']) {
                $date_info['available'] = false;
                $date_info['reason'] = 'business_closed_exception';
                $available_dates[] = $date_info;
                $date = date('Y-m-d', strtotime($date . ' +1 day'));
                continue;
            }
            
            // אם העסק פתוח בחריג, נשתמש בשעות שהוגדרו בחריג
            $business_open = $exception['open_time'];
            $business_close = $exception['close_time'];
        } else {
            // בדיקה אם העסק פתוח ביום זה
            if (!isset($business_hours[$day_of_week]) || !$business_hours[$day_of_week]['is_open']) {
                $date_info['available'] = false;
                $date_info['reason'] = 'business_closed';
                $available_dates[] = $date_info;
                $date = date('Y-m-d', strtotime($date . ' +1 day'));
                continue;
            }
            
            $business_open = $business_hours[$day_of_week]['open_time'];
            $business_close = $business_hours[$day_of_week]['close_time'];
        }
        
        // אם נבחר איש צוות ספציפי
        if ($staff_id > 0) {
            // בדיקה אם יש חריג לאיש הצוות
            if (isset($staff_exceptions[$date])) {
                $exception = $staff_exceptions[$date];
                
                if (!$exception['is_available']) {
                    $date_info['available'] = false;
                    $date_info['reason'] = 'staff_unavailable_exception';
                    $available_dates[] = $date_info;
                    $date = date('Y-m-d', strtotime($date . ' +1 day'));
                    continue;
                }
                
                // אם איש הצוות זמין בחריג, נשתמש בשעות שהוגדרו בחריג
                $staff_start = $exception['start_time'];
                $staff_end = $exception['end_time'];
            } else {
                // בדיקה אם איש הצוות זמין ביום זה
                if (!isset($staff_availability[$day_of_week]) || !$staff_availability[$day_of_week]['is_available']) {
                    $date_info['available'] = false;
                    $date_info['reason'] = 'staff_unavailable';
                    $available_dates[] = $date_info;
                    $date = date('Y-m-d', strtotime($date . ' +1 day'));
                    continue;
                }
                
                $staff_start = $staff_availability[$day_of_week]['start_time'];
                $staff_end = $staff_availability[$day_of_week]['end_time'];
            }
            
            // חישוב זמן עבודה אפקטיבי - חיתוך בין שעות העסק לשעות איש הצוות
            $effective_start = max(strtotime($business_open), strtotime($staff_start));
            $effective_end = min(strtotime($business_close), strtotime($staff_end));
            
            // בדיקה שיש מספיק זמן לתור
            $service_length = ($service['duration'] + $service['buffer_time_before'] + $service['buffer_time_after']) * 60;
            
            if (($effective_end - $effective_start) < $service_length) {
                $date_info['available'] = false;
                $date_info['reason'] = 'not_enough_time';
                $available_dates[] = $date_info;
                $date = date('Y-m-d', strtotime($date . ' +1 day'));
                continue;
            }
            
            // בדיקה אם יש סלוטים פנויים, בהתבסס על התורים הקיימים
            $has_slots = !isset($booked_slots[$date][$staff_id]) || count($booked_slots[$date][$staff_id]) == 0;
            
            if (!$has_slots) {
                // אם יש תורים, בדוק אם יש סלוטים פנויים בין התורים
                $has_slots = hasAvailableSlots(
                    $effective_start,
                    $effective_end,
                    $booked_slots[$date][$staff_id],
                    $service['duration'] * 60,
                    ($service['buffer_time_before'] + $service['buffer_time_after']) * 60
                );
            }
            
            $date_info['available'] = $has_slots;
            if (!$has_slots) {
                $date_info['reason'] = 'fully_booked';
            }
        } else {
            // אם לא נבחר איש צוות ספציפי, בדוק אם יש לפחות איש צוות אחד זמין
            $date_available = false;
            
            foreach ($available_staff as $staff) {
                // בדיקה אם יש חריג לאיש הצוות
                if (isset($staff_exceptions[$date][$staff])) {
                    $exception = $staff_exceptions[$date][$staff];
                    
                    if (!$exception['is_available']) {
                        continue; // איש הצוות לא זמין בתאריך זה
                    }
                    
                    $staff_start = $exception['start_time'];
                    $staff_end = $exception['end_time'];
                } else {
                    // בדיקה אם איש הצוות זמין ביום זה
                    if (!isset($staff_availability[$day_of_week][$staff]) || !$staff_availability[$day_of_week][$staff]['is_available']) {
                        continue; // איש הצוות לא זמין ביום זה
                    }
                    
                    $staff_start = $staff_availability[$day_of_week][$staff]['start_time'];
                    $staff_end = $staff_availability[$day_of_week][$staff]['end_time'];
                }
                
                // חישוב זמן עבודה אפקטיבי
                $effective_start = max(strtotime($business_open), strtotime($staff_start));
                $effective_end = min(strtotime($business_close), strtotime($staff_end));
                
                // בדיקה שיש מספיק זמן לתור
                $service_length = ($service['duration'] + $service['buffer_time_before'] + $service['buffer_time_after']) * 60;
                
                if (($effective_end - $effective_start) < $service_length) {
                    continue; // אין מספיק זמן
                }
                
                // בדיקה אם יש סלוטים פנויים
                $has_slots = !isset($booked_slots[$date][$staff]) || count($booked_slots[$date][$staff]) == 0;
                
                if (!$has_slots) {
                    $has_slots = hasAvailableSlots(
                        $effective_start,
                        $effective_end,
                        $booked_slots[$date][$staff],
                        $service['duration'] * 60,
                        ($service['buffer_time_before'] + $service['buffer_time_after']) * 60
                    );
                }
                
                if ($has_slots) {
                    $date_available = true;
                    break;
                }
            }
            
            $date_info['available'] = $date_available;
            if (!$date_available) {
                $date_info['reason'] = 'no_staff_available';
            }
        }
        
        $available_dates[] = $date_info;
        $date = date('Y-m-d', strtotime($date . ' +1 day'));
    }
    
    echo json_encode([
        'success' => true,
        'service_id' => $service_id,
        'staff_id' => $staff_id,
        'month' => $month,
        'year' => $year,
        'dates' => $available_dates
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'שגיאת מסד נתונים: ' . $e->getMessage()
    ]);
}

// פונקציה לבדיקה אם יש סלוטים פנויים בהתבסס על תורים קיימים
function hasAvailableSlots($start_time, $end_time, $existing_appointments, $service_duration, $buffer_time) {
    if (empty($existing_appointments)) {
        return true;
    }
    
    // מיון התורים לפי זמן התחלה
    usort($existing_appointments, function($a, $b) {
        return strtotime($a['start']) - strtotime($b['start']);
    });
    
    // בדיקה אם יש מקום לפני התור הראשון
    $first_appointment = $existing_appointments[0];
    $first_start = strtotime($first_appointment['start']);
    
    if (($first_start - $start_time) >= ($service_duration + $buffer_time)) {
        return true;
    }
    
    // בדיקה אם יש מקום בין תורים
    $count = count($existing_appointments);
    for ($i = 0; $i < $count - 1; $i++) {
        $current_end = strtotime($existing_appointments[$i]['end']);
        $next_start = strtotime($existing_appointments[$i + 1]['start']);
        
        if (($next_start - $current_end) >= ($service_duration + $buffer_time)) {
            return true;
        }
    }
    
    // בדיקה אם יש מקום אחרי התור האחרון
    $last_appointment = end($existing_appointments);
    $last_end = strtotime($last_appointment['end']);
    
    if (($end_time - $last_end) >= ($service_duration + $buffer_time)) {
        return true;
    }
    
    return false;
}
?>