<?php
// API לקבלת פרטי תור מסוים

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

// בדיקה שנשלח מזהה תור
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'מזהה תור חסר'
    ]);
    exit;
}

// קבלת הפרמטרים
$appointment_id = intval($_GET['id']);
$tenant_id = $_SESSION['tenant_id'];

try {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();
    
    // שליפת פרטי התור
    $query = "SELECT a.*, c.first_name, c.last_name 
              FROM appointments a
              JOIN customers c ON a.customer_id = c.customer_id
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
    
    // בדיקה אם יש תשובות לשאלות מקדימות
    $query = "SELECT ia.question_id, ia.answer_text, iq.question_text 
              FROM intake_answers ia
              JOIN intake_questions iq ON ia.question_id = iq.question_id
              WHERE ia.appointment_id = :appointment_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":appointment_id", $appointment_id);
    $stmt->execute();
    $intake_answers = $stmt->fetchAll();
    
    // הכנת תשובה
    $response = [
        'success' => true,
        'appointment' => $appointment,
        'intake_answers' => $intake_answers
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'שגיאת מסד נתונים: ' . $e->getMessage()
    ]);
}
?>