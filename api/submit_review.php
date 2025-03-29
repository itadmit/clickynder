<?php
// API לשליחת דירוג וביקורת על שירות

// Disable direct access to this file
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.0 403 Forbidden');
    echo 'גישה נדחתה';
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

// וידוא שכל השדות הנדרשים קיימים
$required_fields = ['tenant_id', 'customer_id', 'rating'];

foreach ($required_fields as $field) {
    if (!isset($data[$field]) || ($field === 'rating' && !is_numeric($data[$field]))) {
        echo json_encode([
            'success' => false,
            'message' => "השדה $field חסר או אינו תקין",
            'field' => $field
        ]);
        exit;
    }
}

try {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();
    
    // סניטציה של שדות קלט
    $tenant_id = intval($data['tenant_id']);
    $customer_id = intval($data['customer_id']);
    $appointment_id = isset($data['appointment_id']) ? intval($data['appointment_id']) : null;
    $rating = intval($data['rating']);
    $review_text = isset($data['review_text']) ? sanitizeInput($data['review_text']) : null;
    $is_public = isset($data['is_public']) ? (bool)$data['is_public'] : true;
    
    // בדיקת תקינות הדירוג (1-5)
    if ($rating < 1 || $rating > 5) {
        echo json_encode([
            'success' => false,
            'message' => 'הדירוג חייב להיות בין 1 ל-5'
        ]);
        exit;
    }
    
    // בדיקה שהתור והלקוח שייכים לאותו טננט
    if ($appointment_id) {
        $query = "SELECT tenant_id FROM appointments WHERE appointment_id = :appointment_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":appointment_id", $appointment_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $appointment = $stmt->fetch();
            if ($appointment['tenant_id'] != $tenant_id) {
                echo json_encode([
                    'success' => false,
                    'message' => 'התור אינו שייך לעסק זה'
                ]);
                exit;
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'התור לא נמצא'
            ]);
            exit;
        }
    }
    
    // בדיקה האם כבר קיימת ביקורת לתור זה
    if ($appointment_id) {
        $query = "SELECT review_id FROM reviews 
                 WHERE appointment_id = :appointment_id 
                 AND customer_id = :customer_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":appointment_id", $appointment_id);
        $stmt->bindParam(":customer_id", $customer_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $review = $stmt->fetch();
            $review_id = $review['review_id'];
            
            // עדכון ביקורת קיימת
            $query = "UPDATE reviews SET 
                     rating = :rating,
                     review_text = :review_text,
                     is_public = :is_public,
                     created_at = NOW()
                     WHERE review_id = :review_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":rating", $rating);
            $stmt->bindParam(":review_text", $review_text);
            $stmt->bindParam(":is_public", $is_public, PDO::PARAM_BOOL);
            $stmt->bindParam(":review_id", $review_id);
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'הביקורת עודכנה בהצלחה',
                    'review_id' => $review_id
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'אירעה שגיאה בעדכון הביקורת'
                ]);
            }
            exit;
        }
    }
    
    // הוספת ביקורת חדשה
    $query = "INSERT INTO reviews (tenant_id, customer_id, appointment_id, rating, review_text, is_public) 
              VALUES (:tenant_id, :customer_id, :appointment_id, :rating, :review_text, :is_public)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->bindParam(":customer_id", $customer_id);
    $stmt->bindParam(":appointment_id", $appointment_id);
    $stmt->bindParam(":rating", $rating);
    $stmt->bindParam(":review_text", $review_text);
    $stmt->bindParam(":is_public", $is_public, PDO::PARAM_BOOL);
    
    if ($stmt->execute()) {
        $review_id = $db->lastInsertId();
        
        // חישוב דירוג ממוצע מעודכן
        $query = "SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews 
                 FROM reviews 
                 WHERE tenant_id = :tenant_id AND is_public = 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":tenant_id", $tenant_id);
        $stmt->execute();
        $stats = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'message' => 'הביקורת נשלחה בהצלחה',
            'review_id' => $review_id,
            'avg_rating' => round($stats['avg_rating'], 1),
            'total_reviews' => $stats['total_reviews']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'אירעה שגיאה בשליחת הביקורת'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'שגיאת מסד נתונים: ' . $e->getMessage()
    ]);
}
?>