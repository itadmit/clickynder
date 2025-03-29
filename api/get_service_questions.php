<?php
// מחזיר את השאלות המקדימות לשירות ספציפי וגם שאלות כלליות של בית העסק

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

// Service ID הוא אופציונלי - אם לא סופק, מחזיר רק שאלות כלליות
$tenant_id = intval($_GET['tenant_id']);
$service_id = isset($_GET['service_id']) ? intval($_GET['service_id']) : 0;

// כולל קבצי תצורה
require_once "../config/database.php";
require_once "../includes/functions.php";

try {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();
    
    $questions = [];
    
    // שליפת שאלות כלליות (לא מקושרות לשירות ספציפי)
    $query = "SELECT * FROM intake_questions 
              WHERE tenant_id = :tenant_id 
              AND (service_id IS NULL OR service_id = 0)
              ORDER BY display_order ASC";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    
    while ($row = $stmt->fetch()) {
        $questions[] = [
            'question_id' => $row['question_id'],
            'question_text' => $row['question_text'],
            'question_type' => $row['question_type'],
            'options' => json_decode($row['options']),
            'is_required' => (bool)$row['is_required'],
            'display_order' => $row['display_order'],
            'service_id' => null,
            'question_group' => 'general'
        ];
    }
    
    // אם סופק מזהה שירות, שלוף גם שאלות ספציפיות לשירות
    if ($service_id > 0) {
        $query = "SELECT * FROM intake_questions 
                  WHERE tenant_id = :tenant_id 
                  AND service_id = :service_id
                  ORDER BY display_order ASC";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":tenant_id", $tenant_id);
        $stmt->bindParam(":service_id", $service_id);
        $stmt->execute();
        
        while ($row = $stmt->fetch()) {
            $questions[] = [
                'question_id' => $row['question_id'],
                'question_text' => $row['question_text'],
                'question_type' => $row['question_type'],
                'options' => json_decode($row['options']),
                'is_required' => (bool)$row['is_required'],
                'display_order' => $row['display_order'],
                'service_id' => $row['service_id'],
                'question_group' => 'service_specific'
            ];
        }
    }
    
    // שלוף את פרטי השירות, אם סופק
    $service_info = null;
    if ($service_id > 0) {
        $query = "SELECT name, description, duration, price FROM services 
                  WHERE service_id = :service_id AND tenant_id = :tenant_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":service_id", $service_id);
        $stmt->bindParam(":tenant_id", $tenant_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $service_info = $stmt->fetch();
        }
    }
    
    // מבנה התשובה
    $response = [
        'success' => true,
        'questions' => $questions,
        'service' => $service_info,
        'total_questions' => count($questions)
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'שגיאת מסד נתונים: ' . $e->getMessage(),
        'questions' => []
    ]);
}
?>