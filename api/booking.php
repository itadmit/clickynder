<?php
// API להזמנת תור

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Disable direct access to this file
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.0 403 Forbidden');
    echo 'גישה נדחתה';
    exit;
}

// כולל קבצי תצורה
require_once "../config/database.php";
require_once "../includes/functions.php";

// כתיבת לוג
function writeToLog($message, $data = null) {
    $logFile = __DIR__ . '/booking_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    
    if ($data !== null) {
        $logMessage .= ": " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    
    file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND);
}

// Set headers for JSON response
header('Content-Type: application/json');

// קבלת נתונים מה-POST
$raw_data = file_get_contents('php://input');
writeToLog("Raw input data", $raw_data);

$data = json_decode($raw_data, true);

if (!$data) {
    // אם אין מידע ב-JSON, ננסה לקחת מ-POST רגיל
    $data = $_POST;
    writeToLog("Using POST data", $data);
}

writeToLog("Processed input data", $data);

// וידוא שכל השדות הנדרשים קיימים
$required_fields = ['tenant_id', 'service_id', 'appointment_date', 'appointment_time', 
                    'first_name', 'last_name', 'phone'];

foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        $error = [
            'success' => false,
            'message' => "השדה $field חסר או ריק",
            'field' => $field
        ];
        writeToLog("Missing required field", $error);
        echo json_encode($error);
        exit;
    }
}

try {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();
    writeToLog("Database connection established");
    
    // סניטציה של שדות קלט
    $tenant_id = intval($data['tenant_id']);
    $service_id = intval($data['service_id']);
    $staff_id = isset($data['staff_id']) && !empty($data['staff_id']) ? intval($data['staff_id']) : null;
    $appointment_date = sanitizeInput($data['appointment_date']);
    $appointment_time = sanitizeInput($data['appointment_time']);
    $first_name = sanitizeInput($data['first_name']);
    $last_name = sanitizeInput($data['last_name']);
    $phone = sanitizeInput($data['phone']);
    $email = isset($data['email']) ? sanitizeInput($data['email']) : null;
    $notes = isset($data['notes']) ? sanitizeInput($data['notes']) : null;
    
    writeToLog("Sanitized data", [
        'tenant_id' => $tenant_id,
        'service_id' => $service_id,
        'staff_id' => $staff_id,
        'appointment_date' => $appointment_date,
        'appointment_time' => $appointment_time,
        'email' => $email,
        'notes' => $notes
    ]);
    
    // אם אין staff_id, נמצא את האיש צוות הראשון שמספק את השירות הזה
    if (!$staff_id) {
        writeToLog("No staff_id provided, trying to find one");
        
        try {
            $query = "SELECT ss.staff_id FROM service_staff ss
                    JOIN staff st ON ss.staff_id = st.staff_id
                    WHERE ss.service_id = :service_id 
                    AND st.tenant_id = :tenant_id 
                    AND st.is_active = 1
                    LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":service_id", $service_id);
            $stmt->bindParam(":tenant_id", $tenant_id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $result = $stmt->fetch();
                $staff_id = $result['staff_id'];
                writeToLog("Found staff from service_staff", $staff_id);
            } else {
                // אם אין קשר מוגדר בין שירות לצוות, קח את איש הצוות הראשון של העסק
                $query = "SELECT staff_id FROM staff WHERE tenant_id = :tenant_id AND is_active = 1 LIMIT 1";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":tenant_id", $tenant_id);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $result = $stmt->fetch();
                    $staff_id = $result['staff_id'];
                    writeToLog("Found staff from tenant's staff", $staff_id);
                } else {
                    // אם אין אנשי צוות פעילים בכלל, שגיאה
                    $error = [
                        'success' => false,
                        'message' => 'לא נמצאו אנשי צוות זמינים לשירות זה'
                    ];
                    writeToLog("No staff found", $error);
                    echo json_encode($error);
                    exit;
                }
            }
        } catch (PDOException $e) {
            writeToLog("Error finding staff", $e->getMessage());
            throw $e; // רמיית השגיאה למעלה לטיפול כללי
        }
    }
    
    // יצירת datetime מלא לתחילת התור
    $start_datetime = $appointment_date . ' ' . $appointment_time . ':00';
    
    // בדיקה שמדובר בתאריך ושעה תקינים ועתידיים
    $appointment_timestamp = strtotime($start_datetime);
    $current_timestamp = time();
    
    if ($appointment_timestamp === false) {
        $error = [
            'success' => false,
            'message' => 'פורמט תאריך או שעה לא תקין'
        ];
        writeToLog("Invalid date format", $error);
        echo json_encode($error);
        exit;
    }
    
    if ($appointment_timestamp <= $current_timestamp) {
        $error = [
            'success' => false,
            'message' => 'לא ניתן לקבוע תור בעבר או להרגע הנוכחי'
        ];
        writeToLog("Date in past", $error);
        echo json_encode($error);
        exit;
    }
    
    // קבלת פרטי השירות המבוקש (בעיקר כדי לחשב את זמן הסיום)
    try {
        $query = "SELECT duration, buffer_time_before, buffer_time_after FROM services 
                WHERE service_id = :service_id AND tenant_id = :tenant_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":service_id", $service_id);
        $stmt->bindParam(":tenant_id", $tenant_id);
        $stmt->execute();
        
        if ($stmt->rowCount() == 0) {
            $error = [
                'success' => false,
                'message' => 'השירות שנבחר אינו קיים או אינו שייך לעסק זה'
            ];
            writeToLog("Service not found", $error);
            echo json_encode($error);
            exit;
        }
        
        $service = $stmt->fetch();
        writeToLog("Service details retrieved", $service);
    } catch (PDOException $e) {
        writeToLog("Error fetching service", $e->getMessage());
        throw $e;
    }
    
    // חישוב זמן סיום התור
    $duration = intval($service['duration']);
    $buffer_before = intval($service['buffer_time_before']);
    $buffer_after = intval($service['buffer_time_after']);
    
    // התחלת התור כולל buffer_before
    $real_start = date('Y-m-d H:i:s', strtotime("-{$buffer_before} minutes", $appointment_timestamp));
    
    // סיום התור כולל buffer_after
    $end_datetime = date('Y-m-d H:i:s', strtotime("+{$duration} minutes +{$buffer_after} minutes", $appointment_timestamp));
    
    writeToLog("Appointment times calculated", [
        'real_start' => $real_start,
        'start_datetime' => $start_datetime,
        'end_datetime' => $end_datetime
    ]);
    
    // בדיקה אם יש כבר תור באותו הזמן עם אותו נותן שירות
    try {
        // שימו לב! התיקון החשוב הוא כאן - שמות הפרמטרים צריכים להיות זהים בשאילתה ובעת הקישור
        $query = "SELECT appointment_id FROM appointments 
              WHERE staff_id = :staff_id 
              AND status IN ('pending', 'confirmed') 
              AND (
                  (start_datetime <= :start_time AND end_datetime > :start_time) OR
                  (start_datetime < :end_time AND end_datetime >= :end_time) OR
                  (start_datetime >= :start_time AND end_datetime <= :end_time)
              )";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":staff_id", $staff_id);
        $stmt->bindParam(":start_time", $real_start); // שים לב לשינוי כאן מ-:start_datetime ל-:start_time
        $stmt->bindParam(":end_time", $end_datetime); // שים לב לשינוי כאן מ-:end_datetime ל-:end_time
        writeToLog("Checking for conflicts with params", [
            'staff_id' => $staff_id,
            'start_time' => $real_start,
            'end_time' => $end_datetime
        ]);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $error = [
                'success' => false,
                'message' => 'המועד שנבחר כבר נתפס על ידי מישהו אחר. אנא בחר מועד אחר.'
            ];
            writeToLog("Time slot conflict found", $error);
            echo json_encode($error);
            exit;
        }
    } catch (PDOException $e) {
        writeToLog("Error checking conflicts", $e->getMessage());
        throw $e;
    }
    
    // בדיקה אם הלקוח כבר קיים במערכת (לפי טלפון)
    $customer_id = null;
    try {
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
            writeToLog("Existing customer found", $customer_id);
            
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
            writeToLog("Updated existing customer");
        } else {
            // אם הלקוח לא קיים, צור לקוח חדש
            writeToLog("Creating new customer");
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
            writeToLog("New customer created", $customer_id);
        }
    } catch (PDOException $e) {
        writeToLog("Error handling customer", $e->getMessage());
        throw $e;
    }
    
    // יצירת התור במסד הנתונים
    try {
        writeToLog("Creating appointment with params", [
            'tenant_id' => $tenant_id,
            'customer_id' => $customer_id,
            'staff_id' => $staff_id,
            'service_id' => $service_id,
            'start_datetime' => $start_datetime,
            'end_datetime' => $end_datetime,
            'notes' => $notes
        ]);
        
        $query = "INSERT INTO appointments (tenant_id, customer_id, staff_id, service_id, 
                start_datetime, end_datetime, status, notes) 
                VALUES (:tenant_id, :customer_id, :staff_id, :service_id, 
                :start_datetime, :end_datetime, 'pending', :notes)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":tenant_id", $tenant_id);
        $stmt->bindParam(":customer_id", $customer_id);
        $stmt->bindParam(":staff_id", $staff_id);
        $stmt->bindParam(":service_id", $service_id);
        $stmt->bindParam(":start_datetime", $start_datetime);
        $stmt->bindParam(":end_datetime", $end_datetime);
        $stmt->bindParam(":notes", $notes);
        
        $stmt->execute();
        $appointment_id = $db->lastInsertId();
        writeToLog("Appointment created", $appointment_id);
        
        // יצירת מזהה אסמכתא ייחודי
        $reference = generateBookingReference();
        writeToLog("Generated reference", $reference);
        
        // עדכון מזהה האסמכתא בטבלת התורים
        // בדיקה אם השדה reference_id קיים בטבלה
        try {
            $query = "SHOW COLUMNS FROM appointments LIKE 'reference_id'";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $reference_field_exists = ($stmt->rowCount() > 0);
            
            if ($reference_field_exists) {
                $query = "UPDATE appointments SET reference_id = :reference WHERE appointment_id = :appointment_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":reference", $reference);
                $stmt->bindParam(":appointment_id", $appointment_id);
                $stmt->execute();
                writeToLog("Updated appointment with reference");
            } else {
                writeToLog("Warning: reference_id field does not exist in appointments table");
            }
        } catch (PDOException $e) {
            writeToLog("Warning: Could not update reference_id: " . $e->getMessage());
            // ממשיך בכל מקרה, זה לא קריטי
        }
        
        // בדיקה אם יש שאלות מקדימות והוספת תשובות
        if (isset($data['intake_answers']) && is_array($data['intake_answers']) && !empty($data['intake_answers'])) {
            writeToLog("Processing intake answers", count($data['intake_answers']));
            foreach ($data['intake_answers'] as $question_id => $answer) {
                $query = "INSERT INTO intake_answers (appointment_id, question_id, answer_text) 
                          VALUES (:appointment_id, :question_id, :answer_text)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":appointment_id", $appointment_id);
                $stmt->bindParam(":question_id", $question_id);
                $stmt->bindParam(":answer_text", $answer);
                $stmt->execute();
                writeToLog("Added answer for question", $question_id);
            }
        }
        
        // החזרת תשובה חיובית
        $success = [
            'success' => true,
            'message' => 'התור נקבע בהצלחה',
            'appointment_id' => $appointment_id,
            'reference' => $reference,
            'start_datetime' => $start_datetime,
            'end_datetime' => $end_datetime
        ];
        writeToLog("Success response", $success);
        echo json_encode($success);
    } catch (PDOException $e) {
        writeToLog("Error creating appointment", $e->getMessage());
        throw $e;
    }
} catch (PDOException $e) {
    $error = [
        'success' => false,
        'message' => 'שגיאת מסד נתונים: ' . $e->getMessage()
    ];
    writeToLog("Database error", $error);
    echo json_encode($error);
}

// פונקציה ליצירת מזהה אסמכתא ייחודי
function generateBookingReference() {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $reference = '';
    for ($i = 0; $i < 8; $i++) {
        $reference .= $chars[mt_rand(0, strlen($chars) - 1)];
    }
    return $reference;
}
?>