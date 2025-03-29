<?php
// קובץ זה מחזיר את רשימת התורים עבור תאריך או טווח תאריכים מסוים

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

// קבלת פרמטרים מה-GET
$tenant_id = $_SESSION['tenant_id'];
$start_date = isset($_GET['start']) ? sanitizeInput($_GET['start']) : date('Y-m-d');
$end_date = isset($_GET['end']) ? sanitizeInput($_GET['end']) : date('Y-m-d');
$staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : 0;
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

try {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();
    
    // בניית שאילתה עם תנאים
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
              WHERE a.tenant_id = :tenant_id
              AND DATE(a.start_datetime) >= :start_date
              AND DATE(a.start_datetime) <= :end_date";
    
    if ($staff_id > 0) {
        $query .= " AND a.staff_id = :staff_id";
    }
    
    if (!empty($status)) {
        $query .= " AND a.status = :status";
    }
    
    $query .= " ORDER BY a.start_datetime ASC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->bindParam(":start_date", $start_date);
    $stmt->bindParam(":end_date", $end_date);
    
    if ($staff_id > 0) {
        $stmt->bindParam(":staff_id", $staff_id);
    }
    
    if (!empty($status)) {
        $stmt->bindParam(":status", $status);
    }
    
    $stmt->execute();
    
    $appointments = [];
    
    while ($row = $stmt->fetch()) {
        // עיבוד הנתונים לפורמט אחיד
        $appointment = [
            'id' => $row['appointment_id'],
            'title' => $row['customer_name'] . ' - ' . $row['service_name'],
            'start' => $row['start_datetime'],
            'end' => $row['end_datetime'],
            'backgroundColor' => $row['service_color'],
            'borderColor' => $row['service_color'],
            'customer' => [
                'name' => $row['customer_name'],
                'phone' => $row['customer_phone'],
                'email' => $row['customer_email']
            ],
            'service' => [
                'name' => $row['service_name'],
                'duration' => $row['service_duration']
            ],
            'staff' => $row['staff_name'],
            'status' => $row['status'],
            'notes' => $row['notes']
        ];
        
        // הוסף סטטוס ויזואלי
        switch ($row['status']) {
            case 'pending':
                $appointment['classNames'] = 'appointment-pending';
                break;
            case 'confirmed':
                $appointment['classNames'] = 'appointment-confirmed';
                break;
            case 'cancelled':
                $appointment['backgroundColor'] = '#aaaaaa';
                $appointment['borderColor'] = '#777777';
                $appointment['classNames'] = 'appointment-cancelled';
                $appointment['textColor'] = '#777777';
                break;
            case 'completed':
                $appointment['classNames'] = 'appointment-completed';
                break;
            case 'no_show':
                $appointment['backgroundColor'] = '#ffaaaa';
                $appointment['borderColor'] = '#cc0000';
                $appointment['classNames'] = 'appointment-no-show';
                break;
        }
        
        $appointments[] = $appointment;
    }
    
    echo json_encode([
        'success' => true,
        'appointments' => $appointments
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'שגיאת מסד נתונים: ' . $e->getMessage()
    ]);
}