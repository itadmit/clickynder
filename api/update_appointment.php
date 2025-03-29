<?php
// קובץ זה מעדכן תור קיים

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

// Set headers for JSON response
header('Content-Type: application/json');

// קבלת נתונים מה-POST
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    // אם אין מידע ב-JSON, ננסה לקחת מ-POST רגיל
    $data = $_POST;
}

// בדיקת פרמטרים חובה
if (!isset($data['appointment_id']) || empty($data['appointment_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'מזהה תור חסר'
    ]);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$appointment_id = intval($data['appointment_id']);

try {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();
    
    // בדיקה שהתור שייך לטננט הנוכחי
    $query = "SELECT * FROM appointments WHERE appointment_id = :appointment_id AND tenant_id = :tenant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":appointment_id", $appointment_id);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'התור לא נמצא או אינו שייך לעסק שלך'
        ]);
        exit;
    }
    
    $current_appointment = $stmt->fetch();
    
    // הגדרת ערכים ברירת מחדל לעדכון
    $update_values = [];
    $update_fields = [];
    
    // עדכון מועד התור
    if (isset($data['start_datetime']) && !empty($data['start_datetime'])) {
        $start_datetime = sanitizeInput($data['start_datetime']);
        
        // אם רק תאריך התחלה סופק, נתמך בתבנית לשעה
        if (strlen($start_datetime) === 10) { // תבנית YYYY-MM-DD
            $start_time = isset($data['start_time']) ? sanitizeInput($data['start_time']) : date('H:i:s', strtotime($current_appointment['start_datetime']));
            $start_datetime .= ' ' . $start_time;
        }
        
        $update_values[':start_datetime'] = $start_datetime;
        $update_fields[] = "start_datetime = :start_datetime";
        
        // חישוב זמן סיום חדש אם משך התור או זמן ההתחלה השתנה
        if (isset($data['service_id']) || isset($data['start_datetime'])) {
            $service_id = isset($data['service_id']) ? intval($data['service_id']) : $current_appointment['service_id'];
            
            // קבלת משך השירות
            $query = "SELECT duration FROM services WHERE service_id = :service_id AND tenant_id = :tenant_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":service_id", $service_id);
            $stmt->bindParam(":tenant_id", $tenant_id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $service = $stmt->fetch();
                $duration = intval($service['duration']);
                
                // חישוב זמן סיום
                $end_datetime = date('Y-m-d H:i:s', strtotime($start_datetime) + ($duration * 60));
                $update_values[':end_datetime'] = $end_datetime;
                $update_fields[] = "end_datetime = :end_datetime";
            }
        }
    }
    
    // עדכון נותן שירות
    if (isset($data['staff_id'])) {
        $staff_id = intval($data['staff_id']);
        
        // בדיקה שנותן השירות שייך לטננט
        $query = "SELECT staff_id FROM staff WHERE staff_id = :staff_id AND tenant_id = :tenant_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":staff_id", $staff_id);
        $stmt->bindParam(":tenant_id", $tenant_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $update_values[':staff_id'] = $staff_id;
            $update_fields[] = "staff_id = :staff_id";
        }
    }
    
    // עדכון שירות
    if (isset($data['service_id'])) {
        $service_id = intval($data['service_id']);
        
        // בדיקה שהשירות שייך לטננט
        $query = "SELECT service_id, duration FROM services WHERE service_id = :service_id AND tenant_id = :tenant_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":service_id", $service_id);
        $stmt->bindParam(":tenant_id", $tenant_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $service = $stmt->fetch();
            $update_values[':service_id'] = $service_id;
            $update_fields[] = "service_id = :service_id";
            
            // אם לא עדכנו כבר את זמן הסיום בגלל שינוי בזמן התחלה
            if (!isset($update_values[':end_datetime']) && isset($update_values[':start_datetime'])) {
                $duration = intval($service['duration']);
                $end_datetime = date('Y-m-d H:i:s', strtotime($update_values[':start_datetime']) + ($duration * 60));
                $update_values[':end_datetime'] = $end_datetime;
                $update_fields[] = "end_datetime = :end_datetime";
            }
            // אם לא עדכנו את זמן ההתחלה, אבל שינינו את השירות - צריך לעדכן את זמן הסיום בהתאם למשך השירות החדש
            elseif (!isset($update_values[':start_datetime']) && !isset($update_values[':end_datetime'])) {
                $duration = intval($service['duration']);
                $end_datetime = date('Y-m-d H:i:s', strtotime($current_appointment['start_datetime']) + ($duration * 60));
                $update_values[':end_datetime'] = $end_datetime;
                $update_fields[] = "end_datetime = :end_datetime";
            }
        }
    }
    
    // עדכון סטטוס
    if (isset($data['status']) && in_array($data['status'], ['pending', 'confirmed', 'cancelled', 'completed', 'no_show'])) {
        $status = sanitizeInput($data['status']);
        $update_values[':status'] = $status;
        $update_fields[] = "status = :status";
    }
    
    // עדכון הערות
    if (isset($data['notes'])) {
        $notes = sanitizeInput($data['notes']);
        $update_values[':notes'] = $notes;
        $update_fields[] = "notes = :notes";
    }
    
    // אם אין שדות לעדכון
    if (empty($update_fields)) {
        echo json_encode([
            'success' => false,
            'message' => 'לא סופקו נתונים לעדכון'
        ]);
        exit;
    }
    
    // בדיקת זמינות - אם מעדכנים זמן התחלה או נותן שירות
    if (isset($update_values[':start_datetime']) || isset($update_values[':staff_id'])) {
        $check_staff_id = isset($update_values[':staff_id']) ? $update_values[':staff_id'] : $current_appointment['staff_id'];
        $check_start = isset($update_values[':start_datetime']) ? $update_values[':start_datetime'] : $current_appointment['start_datetime'];
        $check_end = isset($update_values[':end_datetime']) ? $update_values[':end_datetime'] : $current_appointment['end_datetime'];
        
        // בדיקה שאין תורים מתנגשים
        $query = "SELECT appointment_id FROM appointments 
                  WHERE tenant_id = :tenant_id
                  AND staff_id = :staff_id
                  AND appointment_id != :current_id
                  AND status IN ('pending', 'confirmed')
                  AND (
                      (start_datetime <= :start_datetime AND end_datetime > :start_datetime) OR
                      (start_datetime < :end_datetime AND end_datetime >= :end_datetime) OR
                      (start_datetime >= :start_datetime AND end_datetime <= :end_datetime)
                  )";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":tenant_id", $tenant_id);
        $stmt->bindParam(":staff_id", $check_staff_id);
        $stmt->bindParam(":current_id", $appointment_id);
        $stmt->bindParam(":start_datetime", $check_start);
        $stmt->bindParam(":end_datetime", $check_end);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'המועד שנבחר כבר תפוס על ידי תור אחר'
            ]);
            exit;
        }
    }
    
    // ביצוע העדכון
    $query = "UPDATE appointments SET " . implode(', ', $update_fields) . ", updated_at = NOW() WHERE appointment_id = :appointment_id";
    $stmt = $db->prepare($query);
    
    // הוספת פרמטרים לשאילתה
    foreach ($update_values as $param => $value) {
        $stmt->bindValue($param, $value);
    }
    $stmt->bindParam(":appointment_id", $appointment_id);
    
    if ($stmt->execute()) {
        // שליפת הנתונים המעודכנים
        $query = "SELECT a.*, 
                  CONCAT(c.first_name, ' ', c.last_name) as customer_name, 
                  c.phone as customer_phone, 
                  c.email as customer_email,
                  s.name as service_name, 
                  s.color as service_color,
                  s.duration as service_duration,
                  st.name as staff_name
                  FROM appointments a
                  JOIN customers c ON a.customer_id = c.customer_id
                  JOIN services s ON a.service_id = s.service_id
                  JOIN staff st ON a.staff_id = st.staff_id
                  WHERE a.appointment_id = :appointment_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":appointment_id", $appointment_id);
        $stmt->execute();
        
        $updated_appointment = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'message' => 'התור עודכן בהצלחה',
            'appointment' => [
                'id' => $updated_appointment['appointment_id'],
                'title' => $updated_appointment['customer_name'] . ' - ' . $updated_appointment['service_name'],
                'start' => $updated_appointment['start_datetime'],
                'end' => $updated_appointment['end_datetime'],
                'backgroundColor' => $updated_appointment['service_color'],
                'borderColor' => $updated_appointment['service_color'],
                'customer' => [
                    'name' => $updated_appointment['customer_name'],
                    'phone' => $updated_appointment['customer_phone'],
                    'email' => $updated_appointment['customer_email']
                ],
                'service' => [
                    'name' => $updated_appointment['service_name'],
                    'duration' => $updated_appointment['service_duration']
                ],
                'staff' => $updated_appointment['staff_name'],
                'status' => $updated_appointment['status'],
                'notes' => $updated_appointment['notes']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'שגיאה בעדכון התור'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'שגיאת מסד נתונים: ' . $e->getMessage()
    ]);
}