<?php
// API לטיפול בתשלומים מקוונים

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
$required_fields = ['tenant_id', 'appointment_id', 'amount', 'payment_method'];

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

// פרמטרים בסיסיים
$tenant_id = intval($data['tenant_id']);
$appointment_id = intval($data['appointment_id']);
$amount = floatval($data['amount']);
$payment_method = sanitizeInput($data['payment_method']);

// פרטי כרטיס אשראי (מקודדים או token)
$card_token = isset($data['card_token']) ? sanitizeInput($data['card_token']) : null;
$card_last_digits = isset($data['card_last_digits']) ? sanitizeInput($data['card_last_digits']) : null;
$card_expiry = isset($data['card_expiry']) ? sanitizeInput($data['card_expiry']) : null;
$is_deposit = isset($data['is_deposit']) ? (bool)$data['is_deposit'] : false;

// פרטי התשלום הנוספים
$payment_reference = isset($data['payment_reference']) ? sanitizeInput($data['payment_reference']) : null;
$payment_notes = isset($data['payment_notes']) ? sanitizeInput($data['payment_notes']) : null;

try {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();
    
    // בדיקה שהתור קיים ושייך לטננט
    $query = "SELECT a.*, c.first_name, c.last_name, c.email, s.name as service_name, s.price 
              FROM appointments a 
              JOIN customers c ON a.customer_id = c.customer_id 
              JOIN services s ON a.service_id = s.service_id 
              WHERE a.appointment_id = :appointment_id AND a.tenant_id = :tenant_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":appointment_id", $appointment_id);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'התור לא נמצא או שאינו שייך לבית העסק שלך'
        ]);
        exit;
    }
    
    $appointment = $stmt->fetch();
    
    // חיבור לשער התשלומים
    // הקוד הבא הוא רק לדוגמה - יש להחליף אותו בחיבור אמיתי לשער תשלומים
    
    $payment_success = false;
    $transaction_id = '';
    $error_message = '';
    
    // הדמיית תשלום - במציאות תהיה קריאה לשער תשלומים
    if ($payment_method === 'credit_card' && $card_token) {
        /*
         * קוד לדוגמה - חיבור לשער תשלומים:
         * 
         * $payment_api = new PaymentGateway($api_key, $api_secret);
         * $response = $payment_api->charge([
         *     'amount' => $amount,
         *     'currency' => 'ILS',
         *     'token' => $card_token,
         *     'description' => 'תשלום עבור תור ב' . $business_name,
         *     'metadata' => [
         *         'appointment_id' => $appointment_id,
         *         'customer_name' => $appointment['first_name'] . ' ' . $appointment['last_name']
         *     ]
         * ]);
         * 
         * if ($response->success) {
         *     $payment_success = true;
         *     $transaction_id = $response->transaction_id;
         * } else {
         *     $error_message = $response->error_message;
         * }
         */
        
        // לצורך הדוגמה - נניח שהתשלום הצליח
        $payment_success = true;
        $transaction_id = 'TRANS_' . time() . '_' . mt_rand(1000, 9999);
    }
    else if ($payment_method === 'paypal') {
        /*
         * קוד לדוגמה - חיבור ל-PayPal:
         * 
         * $paypal = new PayPalClient($client_id, $client_secret);
         * $response = $paypal->capturePayment($data['paypal_order_id']);
         * 
         * if ($response->status === 'COMPLETED') {
         *     $payment_success = true;
         *     $transaction_id = $response->id;
         * } else {
         *     $error_message = $response->message;
         * }
         */
        
        // לצורך הדוגמה - נניח שהתשלום הצליח
        $payment_success = true;
        $transaction_id = 'PP_' . time() . '_' . mt_rand(1000, 9999);
    }
    else {
        $error_message = 'אמצעי תשלום לא נתמך';
    }
    
    // עדכון מסד הנתונים עם פרטי התשלום
    if ($payment_success) {
        // בדיקה אם יש טבלת תשלומים - אם לא, נניח שיש (פשוט לצורך הדוגמה)
        $has_payments_table = true;
        
        if ($has_payments_table) {
            // הוספת התשלום לטבלת תשלומים
            $query = "INSERT INTO payments (tenant_id, appointment_id, amount, payment_method, 
                      transaction_id, card_last_digits, card_expiry, is_deposit, payment_date, 
                      payment_reference, notes) 
                      VALUES (:tenant_id, :appointment_id, :amount, :payment_method, 
                      :transaction_id, :card_last_digits, :card_expiry, :is_deposit, NOW(), 
                      :payment_reference, :notes)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(":tenant_id", $tenant_id);
            $stmt->bindParam(":appointment_id", $appointment_id);
            $stmt->bindParam(":amount", $amount);
            $stmt->bindParam(":payment_method", $payment_method);
            $stmt->bindParam(":transaction_id", $transaction_id);
            $stmt->bindParam(":card_last_digits", $card_last_digits);
            $stmt->bindParam(":card_expiry", $card_expiry);
            $stmt->bindParam(":is_deposit", $is_deposit, PDO::PARAM_BOOL);
            $stmt->bindParam(":payment_reference", $payment_reference);
            $stmt->bindParam(":notes", $payment_notes);
            
            if ($stmt->execute()) {
                $payment_id = $db->lastInsertId();
                
                // עדכון סטטוס התור ל"מאושר" אם זהו תשלום מלא
                if (!$is_deposit) {
                    $query = "UPDATE appointments 
                              SET status = 'confirmed', 
                                  confirmation_sent = 1, 
                                  confirmation_datetime = NOW(), 
                                  is_paid = 1
                              WHERE appointment_id = :appointment_id";
                } else {
                    $query = "UPDATE appointments 
                              SET status = 'confirmed', 
                                  confirmation_sent = 1, 
                                  confirmation_datetime = NOW(), 
                                  deposit_paid = 1
                              WHERE appointment_id = :appointment_id";
                }
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(":appointment_id", $appointment_id);
                $stmt->execute();
                
                // החזרת תשובה חיובית
                echo json_encode([
                    'success' => true,
                    'message' => 'התשלום התקבל בהצלחה',
                    'payment_id' => $payment_id,
                    'transaction_id' => $transaction_id,
                    'amount' => $amount,
                    'is_deposit' => $is_deposit,
                    'appointment_id' => $appointment_id,
                    'date' => date('Y-m-d H:i:s')
                ]);
                
                // כאן ניתן להוסיף שליחת אישור תשלום במייל או SMS
                
                exit;
            }
        }
        
        // עדכון סטטוס התור גם אם אין טבלת תשלומים
        $query = "UPDATE appointments 
                  SET status = 'confirmed', 
                      confirmation_sent = 1, 
                      confirmation_datetime = NOW()
                  WHERE appointment_id = :appointment_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":appointment_id", $appointment_id);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'התשלום התקבל בהצלחה',
            'transaction_id' => $transaction_id,
            'amount' => $amount,
            'is_deposit' => $is_deposit,
            'appointment_id' => $appointment_id,
            'date' => date('Y-m-d H:i:s')
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'התשלום נכשל: ' . $error_message
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'שגיאת מסד נתונים: ' . $e->getMessage()
    ]);
}
?>