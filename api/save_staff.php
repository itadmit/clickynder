<?php
// קובץ זה מטפל בשמירת פרטי איש צוות (הוספה/עדכון)

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

// בדיקת שיטת הבקשה
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'שיטת בקשה לא מורשית'
    ]);
    exit;
}

// קבלת ה-tenant_id מהסשן
$tenant_id = $_SESSION['tenant_id'];

// סניטציה וולידציה של קלט
$staff_id = isset($_POST['staff_id']) && !empty($_POST['staff_id']) ? intval($_POST['staff_id']) : 0;
$name = sanitizeInput($_POST['name']);
$position = isset($_POST['position']) ? sanitizeInput($_POST['position']) : '';
$email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : '';
$phone = isset($_POST['phone']) ? sanitizeInput($_POST['phone']) : '';
$bio = isset($_POST['bio']) ? sanitizeInput($_POST['bio']) : '';
$is_active = isset($_POST['is_active']) ? 1 : 0;

// בדיקת שדות חובה
if (empty($name)) {
    echo json_encode([
        'success' => false,
        'message' => 'יש להזין שם לאיש הצוות'
    ]);
    exit;
}

// בדיקת תקינות אימייל (אם הוזן)
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'כתובת אימייל לא תקינה'
    ]);
    exit;
}

// בדיקת תקינות מספר טלפון (אם הוזן)
if (!empty($phone)) {
    $phone = preg_replace('/[^0-9]/', '', $phone); // השאר רק ספרות
    if (!(preg_match('/^0[23489]\d{7}$/', $phone) || preg_match('/^05\d{8}$/', $phone))) {
        echo json_encode([
            'success' => false,
            'message' => 'מספר טלפון לא תקין'
        ]);
        exit;
    }
}

try {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();
    
    // טיפול בתמונה אם הועלתה
    $image_path = null;
    if (isset($_FILES['image']) && $_FILES['image']['size'] > 0) {
        $upload_dir = "../uploads/staff/";
        
        // יצירת תיקיה אם לא קיימת
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '.' . $file_extension;
        $upload_path = $upload_dir . $file_name;
        
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array(strtolower($file_extension), $allowed_extensions)) {
            echo json_encode([
                'success' => false,
                'message' => 'סוג קובץ לא נתמך. אנא העלה קובץ תמונה (jpg, jpeg, png, gif)'
            ]);
            exit;
        }
        
        if ($_FILES['image']['size'] > 5000000) { // 5MB
            echo json_encode([
                'success' => false,
                'message' => 'גודל הקובץ חורג מהמותר (מקסימום 5MB)'
            ]);
            exit;
        }
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
            $image_path = 'uploads/staff/' . $file_name;
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'אירעה שגיאה בהעלאת התמונה'
            ]);
            exit;
        }
    }
    
    // עדכון איש צוות קיים
    if ($staff_id > 0) {
        // בדיקה שאיש הצוות שייך לטננט
        $query = "SELECT staff_id, image_path FROM staff WHERE staff_id = :staff_id AND tenant_id = :tenant_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":staff_id", $staff_id);
        $stmt->bindParam(":tenant_id", $tenant_id);
        $stmt->execute();
        
        if ($stmt->rowCount() == 0) {
            echo json_encode([
                'success' => false,
                'message' => 'איש הצוות לא נמצא או אינו שייך לעסק שלך'
            ]);
            exit;
        }
        
        $existing_staff = $stmt->fetch();
        
        // עדכון פרטי איש הצוות
        $query = "UPDATE staff SET 
                 name = :name,
                 position = :position,
                 email = :email,
                 phone = :phone,
                 bio = :bio,
                 is_active = :is_active";
        
        // הוסף שדה תמונה רק אם הועלתה תמונה חדשה
        if ($image_path !== null) {
            $query .= ", image_path = :image_path";
        }
        
        $query .= " WHERE staff_id = :staff_id AND tenant_id = :tenant_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":position", $position);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":phone", $phone);
        $stmt->bindParam(":bio", $bio);
        $stmt->bindParam(":is_active", $is_active);
        
        if ($image_path !== null) {
            $stmt->bindParam(":image_path", $image_path);
            
            // מחיקת התמונה הישנה אם קיימת
            if (!empty($existing_staff['image_path'])) {
                $old_image_path = "../" . $existing_staff['image_path'];
                if (file_exists($old_image_path)) {
                    unlink($old_image_path);
                }
            }
        }
        
        $stmt->bindParam(":staff_id", $staff_id);
        $stmt->bindParam(":tenant_id", $tenant_id);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'איש הצוות עודכן בהצלחה',
                'staff_id' => $staff_id
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'אירעה שגיאה בעדכון איש הצוות'
            ]);
        }
    }
    // הוספת איש צוות חדש
    else {
        $query = "INSERT INTO staff (tenant_id, name, position, email, phone, bio, image_path, is_active) 
                 VALUES (:tenant_id, :name, :position, :email, :phone, :bio, :image_path, :is_active)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":tenant_id", $tenant_id);
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":position", $position);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":phone", $phone);
        $stmt->bindParam(":bio", $bio);
        $stmt->bindParam(":image_path", $image_path);
        $stmt->bindParam(":is_active", $is_active);
        
        if ($stmt->execute()) {
            $new_staff_id = $db->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'איש הצוות נוסף בהצלחה',
                'staff_id' => $new_staff_id
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'אירעה שגיאה בהוספת איש הצוות'
            ]);
        }
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'שגיאת מסד נתונים: ' . $e->getMessage()
    ]);
}
?>