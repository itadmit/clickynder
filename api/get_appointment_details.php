<?php
// קובץ המחזיר פרטים מלאים של תור ספציפי

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

// בדיקת פרמטרים חובה
if (!isset($_GET['appointment_id']) || empty($_GET['appointment_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'מזהה תור חסר'
    ]);
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$appointment_id = intval($_GET['appointment_id']);

try {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();
    
    // שליפת פרטי התור
    $query = "SELECT a.*, 
              c.first_name, c.last_name, c.phone, c.email, 
              s.name as service_name, s.service_id, s.duration as service_duration, s.price,
              st.name as staff_name, st.staff_id
              FROM appointments a
              JOIN customers c ON a.customer_id = c.customer_id
              JOIN services s ON a.service_id = s.service_id
              JOIN staff st ON a.staff_id = st.staff_id
              WHERE a.appointment_id = :appointment_id AND a.tenant_id = :tenant_id";
    
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
    
    $appointment = $stmt->fetch();
    
    // בניית תשובה מובנית
    $response = [
        'success' => true,
        'appointment' => [
            'id' => $appointment['appointment_id'],
            'start' => $appointment['start_datetime'],
            'end' => $appointment['end_datetime'],
            'customer' => [
                'id' => $appointment['customer_id'],
                'name' => $appointment['first_name'] . ' ' . $appointment['last_name'],
                'phone' => $appointment['phone'],
                'email' => $appointment['email']
            ],
            'service' => [
                'id' => $appointment['service_id'],
                'name' => $appointment['service_name'],
                'duration' => $appointment['service_duration'],
                'price' => $appointment['price']
            ],
            'service_id' => $appointment['service_id'],
            'staff_id' => $appointment['staff_id'],
            'staff' => $appointment['staff_name'],
            'status' => $appointment['status'],
            'notes' => $appointment['notes'],
            'created_at' => $appointment['created_at'],
            'updated_at' => $appointment['updated_at']
        ]
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'שגיאת מסד נתונים: ' . $e->getMessage()
    ]);
}