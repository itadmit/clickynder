<?php
// התחלת או המשך סשן
session_start();

// טעינת קבצי התצורה
require_once "config/database.php";
require_once "includes/functions.php";

// הגדרת קבוע שמאפשר גישה לקבצי ה-includes
define('ACCESS_ALLOWED', true);

// בדיקה האם המשתמש מחובר
if (!isLoggedIn()) {
    redirect("login.php");
}

// קבלת ה-tenant_id מהסשן
$tenant_id = $_SESSION['tenant_id'];

// חיבור למסד הנתונים
$database = new Database();
$db = $database->getConnection();

// פונקציה לטיפול בהעלאת תמונות וכיווץ ל-WebP
function processAndSaveImage($file, $tenant_id, $type = 'general') {
    // בדיקה אם קיימת תיקייה של הבעלים, אם לא - יצירה
    $tenant_dir = "uploads/tenants/" . $tenant_id;
    if (!file_exists($tenant_dir)) {
        mkdir($tenant_dir, 0755, true);
    }
    
    // קידומות לסוגי תמונות
    $prefix = '';
    switch($type) {
        case 'logo':
            $prefix = 'logo_';
            break;
        case 'cover':
            $prefix = 'cover_';
            break;
        case 'staff':
            $prefix = 'staff_';
            break;
        default:
            $prefix = 'img_';
    }
    
    // יצירת שם קובץ ייחודי
    $filename = $prefix . time() . '_' . preg_replace('/[^a-zA-Z0-9]/', '', pathinfo($file['name'], PATHINFO_FILENAME)) . '.webp';
    $target_path = $tenant_dir . '/' . $filename;
    
    // שליפת סוג המימטיפ של הקובץ
    $mime_type = $file['type'];
    
    // טיפול בקובץ בהתאם לסוג שלו
    $source_image = null;
    if (strpos($mime_type, 'jpeg') !== false || strpos($mime_type, 'jpg') !== false) {
        $source_image = imagecreatefromjpeg($file['tmp_name']);
    } elseif (strpos($mime_type, 'png') !== false) {
        $source_image = imagecreatefrompng($file['tmp_name']);
    } elseif (strpos($mime_type, 'gif') !== false) {
        $source_image = imagecreatefromgif($file['tmp_name']);
    } elseif (strpos($mime_type, 'webp') !== false) {
        $source_image = imagecreatefromwebp($file['tmp_name']);
    } else {
        return false; // סוג קובץ לא נתמך
    }
    
    // אם לא הצלחנו ליצור תמונת מקור
    if (!$source_image) {
        return false;
    }
    
    // שמירה כקובץ WebP
    if ($type === 'logo') {
        // לוגו - נכווץ לגודל 200x200
        $width = imagesx($source_image);
        $height = imagesy($source_image);
        $max_dimension = 200;
        
        if ($width > $max_dimension || $height > $max_dimension) {
            // חישוב יחס התמונה ויצירת תמונה חדשה
            if ($width > $height) {
                $new_width = $max_dimension;
                $new_height = floor($height * ($max_dimension / $width));
            } else {
                $new_height = $max_dimension;
                $new_width = floor($width * ($max_dimension / $height));
            }
            
            $resized_image = imagecreatetruecolor($new_width, $new_height);
            // שמירה על שקיפות במידת הצורך
            imagecolortransparent($resized_image, imagecolorallocatealpha($resized_image, 0, 0, 0, 127));
            imagealphablending($resized_image, false);
            imagesavealpha($resized_image, true);
            
            imagecopyresampled($resized_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
            imagewebp($resized_image, $target_path, 90);
            imagedestroy($resized_image);
        } else {
            // אם התמונה כבר בגודל הנכון, שמור אותה כמו שהיא
            imagewebp($source_image, $target_path, 90);
        }
    } elseif ($type === 'cover') {
        // תמונת כיסוי - נכווץ לרוחב 1200 ונשמור על היחס
        $width = imagesx($source_image);
        $height = imagesy($source_image);
        $max_width = 1200;
        
        if ($width > $max_width) {
            $new_width = $max_width;
            $new_height = floor($height * ($max_width / $width));
            
            $resized_image = imagecreatetruecolor($new_width, $new_height);
            // שמירה על שקיפות במידת הצורך
            imagecolortransparent($resized_image, imagecolorallocatealpha($resized_image, 0, 0, 0, 127));
            imagealphablending($resized_image, false);
            imagesavealpha($resized_image, true);
            
            imagecopyresampled($resized_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
            imagewebp($resized_image, $target_path, 85);
            imagedestroy($resized_image);
        } else {
            // אם התמונה כבר בגודל הנכון, שמור אותה כמו שהיא
            imagewebp($source_image, $target_path, 85);
        }
    } else {
        // סוגי תמונות אחרים
        imagewebp($source_image, $target_path, 85);
    }
    
    // שחרור משאבים
    imagedestroy($source_image);
    
    return $target_path;
}

// קבלת פרטי העסק הקיימים
$query = "SELECT * FROM tenants WHERE tenant_id = :tenant_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":tenant_id", $tenant_id);
$stmt->execute();
$tenant = $stmt->fetch();

// הגדרת משתנים לתבניות הודעה
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
$error_message = '';
$success_message = '';

// טיפול בטפסים
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // ========== עדכון פרטים כלליים ==========
    if (isset($_POST['update_general'])) {
        $business_name = sanitizeInput($_POST['business_name']);
        $slug = sanitizeInput($_POST['slug']);
        $email = sanitizeInput($_POST['email']);
        $phone = sanitizeInput($_POST['phone']);
        $address = sanitizeInput($_POST['address']);
        $business_description = sanitizeInput($_POST['business_description']);
        $facebook_url = sanitizeInput($_POST['facebook_url']);
        $instagram_url = sanitizeInput($_POST['instagram_url']);
        $website_url = sanitizeInput($_POST['website_url']);
        
        // בדיקת תקינות אימייל
        if (!isValidEmail($email)) {
            $error_message = "כתובת האימייל אינה תקינה";
        } 
        // בדיקה שהסלאג מכיל רק אותיות לטיניות קטנות, מספרים ומקפים
        elseif (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            $error_message = "הסלאג יכול להכיל רק אותיות לטיניות קטנות, מספרים ומקפים";
        }
        else {
            try {
                // בדיקה האם הסלאג כבר תפוס על ידי עסק אחר
                $query = "SELECT tenant_id FROM tenants WHERE slug = :slug AND tenant_id != :tenant_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":slug", $slug);
                $stmt->bindParam(":tenant_id", $tenant_id);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $error_message = "הסלאג כבר תפוס, אנא בחר סלאג אחר";
                } else {
                    // עדכון פרטי העסק
                    $query = "UPDATE tenants SET 
                              business_name = :business_name,
                              slug = :slug,
                              email = :email,
                              phone = :phone,
                              address = :address,
                              business_description = :business_description,
                              facebook_url = :facebook_url,
                              instagram_url = :instagram_url,
                              website_url = :website_url,
                              updated_at = NOW()
                              WHERE tenant_id = :tenant_id";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(":business_name", $business_name);
                    $stmt->bindParam(":slug", $slug);
                    $stmt->bindParam(":email", $email);
                    $stmt->bindParam(":phone", $phone);
                    $stmt->bindParam(":address", $address);
                    $stmt->bindParam(":business_description", $business_description);
                    $stmt->bindParam(":facebook_url", $facebook_url);
                    $stmt->bindParam(":instagram_url", $instagram_url);
                    $stmt->bindParam(":website_url", $website_url);
                    $stmt->bindParam(":tenant_id", $tenant_id);
                    
                    if ($stmt->execute()) {
                        $_SESSION['business_name'] = $business_name;
                        $_SESSION['email'] = $email;
                        $success_message = "הפרטים עודכנו בהצלחה";
                        
                        // עדכון הפרטים המקומיים
                        $tenant['business_name'] = $business_name;
                        $tenant['slug'] = $slug;
                        $tenant['email'] = $email;
                        $tenant['phone'] = $phone;
                        $tenant['address'] = $address;
                        $tenant['business_description'] = $business_description;
                        $tenant['facebook_url'] = $facebook_url;
                        $tenant['instagram_url'] = $instagram_url;
                        $tenant['website_url'] = $website_url;
                    } else {
                        $error_message = "אירעה שגיאה בעדכון הפרטים";
                    }
                }
            } catch (PDOException $e) {
                $error_message = "שגיאת מסד נתונים: " . $e->getMessage();
            }
        }
    }
    
    // ========== עדכון סיסמה ==========
    else if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // בדיקה שהסיסמאות החדשות תואמות
        if ($new_password !== $confirm_password) {
            $error_message = "הסיסמאות החדשות אינן תואמות";
        }
        // בדיקה שהסיסמה החדשה באורך מינימלי
        elseif (strlen($new_password) < 6) {
            $error_message = "הסיסמה החדשה חייבת להכיל לפחות 6 תווים";
        }
        else {
            try {
                // קבלת הסיסמה הנוכחית מהמסד
                $query = "SELECT password FROM tenants WHERE tenant_id = :tenant_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":tenant_id", $tenant_id);
                $stmt->execute();
                $current_hash = $stmt->fetch()['password'];
                
                // אימות הסיסמה הנוכחית
                if (verifyPassword($current_password, $current_hash) || $current_password === $current_hash) {
                    // הצפנת הסיסמה החדשה
                    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    // עדכון הסיסמה
                    $query = "UPDATE tenants SET password = :password WHERE tenant_id = :tenant_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(":password", $new_hash);
                    $stmt->bindParam(":tenant_id", $tenant_id);
                    
                    if ($stmt->execute()) {
                        $success_message = "הסיסמה עודכנה בהצלחה";
                    } else {
                        $error_message = "אירעה שגיאה בעדכון הסיסמה";
                    }
                } else {
                    $error_message = "הסיסמה הנוכחית אינה נכונה";
                }
            } catch (PDOException $e) {
                $error_message = "שגיאת מסד נתונים: " . $e->getMessage();
            }
        }
    }
    
    // ========== העלאת תמונות ==========
    else if (isset($_POST['update_images'])) {
        // טיפול בהעלאת לוגו
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0 && $_FILES['logo']['size'] > 0) {
            $logo_path = processAndSaveImage($_FILES['logo'], $tenant_id, 'logo');
            
            if ($logo_path) {
                $query = "UPDATE tenants SET logo_path = :logo_path WHERE tenant_id = :tenant_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":logo_path", $logo_path);
                $stmt->bindParam(":tenant_id", $tenant_id);
                
                if ($stmt->execute()) {
                    $success_message = "הלוגו עודכן בהצלחה";
                    $tenant['logo_path'] = $logo_path;
                } else {
                    $error_message = "אירעה שגיאה בעדכון הלוגו";
                }
            } else {
                $error_message = "אירעה שגיאה בהעלאת הלוגו. ודא שהקובץ הוא תמונה בפורמט תקין (JPG, PNG, GIF).";
            }
        }
        
        // טיפול בהעלאת תמונת כיסוי
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] == 0 && $_FILES['cover_image']['size'] > 0) {
            $cover_path = processAndSaveImage($_FILES['cover_image'], $tenant_id, 'cover');
            
            if ($cover_path) {
                $query = "UPDATE tenants SET cover_image_path = :cover_path WHERE tenant_id = :tenant_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":cover_path", $cover_path);
                $stmt->bindParam(":tenant_id", $tenant_id);
                
                if ($stmt->execute()) {
                    $success_message = "תמונת הרקע עודכנה בהצלחה";
                    $tenant['cover_image_path'] = $cover_path;
                } else {
                    $error_message = "אירעה שגיאה בעדכון תמונת הרקע";
                }
            } else {
                $error_message = "אירעה שגיאה בהעלאת תמונת הרקע. ודא שהקובץ הוא תמונה בפורמט תקין (JPG, PNG, GIF).";
            }
        }
    }
    
    // ========== עדכון תבניות הודעה ==========
    else if (isset($_POST['update_templates'])) {
        $booking_subject = sanitizeInput($_POST['booking_subject']);
        $booking_message = $_POST['booking_message'];
        $reminder_subject = sanitizeInput($_POST['reminder_subject']);
        $reminder_message = $_POST['reminder_message'];
        
        try {
            // עדכון תבנית אישור הזמנה
            $query = "UPDATE message_templates SET 
                      subject = :subject, 
                      message_text = :message_text
                      WHERE tenant_id = :tenant_id AND type = 'booking_confirmation'";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":subject", $booking_subject);
            $stmt->bindParam(":message_text", $booking_message);
            $stmt->bindParam(":tenant_id", $tenant_id);
            $stmt->execute();
            
            // עדכון תבנית תזכורת
            $query = "UPDATE message_templates SET 
                      subject = :subject, 
                      message_text = :message_text
                      WHERE tenant_id = :tenant_id AND type = 'appointment_reminder'";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":subject", $reminder_subject);
            $stmt->bindParam(":message_text", $reminder_message);
            $stmt->bindParam(":tenant_id", $tenant_id);
            $stmt->execute();
            
            $success_message = "תבניות ההודעות עודכנו בהצלחה";
        } catch (PDOException $e) {
            $error_message = "שגיאת מסד נתונים: " . $e->getMessage();
        }
    }
}

// קבלת תבניות הודעה
$templates = [];
try {
    $query = "SELECT * FROM message_templates WHERE tenant_id = :tenant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    
    while ($row = $stmt->fetch()) {
        $templates[$row['type']] = $row;
    }
} catch (PDOException $e) {
    $error_message = "שגיאת מסד נתונים: " . $e->getMessage();
}

// שליפת פרטי המסלול הנוכחי
$query = "SELECT s.*, COUNT(st.staff_id) as current_staff_count 
         FROM subscriptions s 
         JOIN tenant_subscriptions ts ON ts.subscription_id = s.id
         LEFT JOIN staff st ON st.tenant_id = :tenant_id1 
         WHERE ts.tenant_id = :tenant_id2 
         GROUP BY s.id, s.max_staff_members";
$stmt = $db->prepare($query);
$stmt->bindParam(":tenant_id1", $tenant_id);
$stmt->bindParam(":tenant_id2", $tenant_id);
$stmt->execute();
$current_subscription = $stmt->fetch();

if (!$current_subscription) {
    // אם אין מסלול פעיל, נקבל את המסלול הבסיסי
    $query = "SELECT * FROM subscriptions WHERE name = 'מסלול בסיסי'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $current_subscription = $stmt->fetch();

    if ($current_subscription) {
        // הוספת המסלול הבסיסי לדייר
        $query = "INSERT INTO tenant_subscriptions (tenant_id, subscription_id) VALUES (:tenant_id, :subscription_id)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":tenant_id", $tenant_id);
        $stmt->bindParam(":subscription_id", $current_subscription['id']);
        $stmt->execute();

        // שליפת מספר אנשי הצוות הנוכחי
        $query = "SELECT COUNT(*) as current_staff_count FROM staff WHERE tenant_id = :tenant_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":tenant_id", $tenant_id);
        $stmt->execute();
        $staff_count = $stmt->fetch();
        $current_subscription['current_staff_count'] = $staff_count['current_staff_count'];
    }
}

// שליפת כל המסלולים האפשריים
$query = "SELECT * FROM subscriptions ORDER BY price ASC";
$stmt = $db->prepare($query);
$stmt->execute();
$all_subscriptions = $stmt->fetchAll();

// כותרת העמוד
$page_title = "הגדרות";
?>

<?php include_once "includes/header.php"; ?>
<?php include_once "includes/sidebar.php"; ?>

<!-- Main Content -->
<main class="flex-1 overflow-y-auto py-8">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">הגדרות</h1>
        
        <?php if (!empty($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6" role="alert">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6" role="alert">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="bg-white shadow-sm rounded-2xl overflow-hidden">
            <!-- Tabs -->
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex" aria-label="Tabs">
                    <a href="?tab=general" 
                       class="<?php echo $current_tab == 'general' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-8 border-b-2 font-medium text-sm">
                        פרטי העסק
                    </a>
                    <a href="?tab=images" 
                       class="<?php echo $current_tab == 'images' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-8 border-b-2 font-medium text-sm">
                        תמונות
                    </a>
                    <a href="?tab=password" 
                       class="<?php echo $current_tab == 'password' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-8 border-b-2 font-medium text-sm">
                        סיסמה
                    </a>
                    <a href="?tab=subscription" 
                       class="<?php echo $current_tab == 'subscription' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-8 border-b-2 font-medium text-sm">
                        מסלול
                    </a>
                    <a href="?tab=templates" 
                       class="<?php echo $current_tab == 'templates' ? 'border-primary text-primary' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-8 border-b-2 font-medium text-sm">
                        תבניות הודעה
                    </a>
                </nav>
            </div>
            
            <!-- Tab Content -->
            <div class="p-6">
                <?php if ($current_tab == 'general'): ?>
                    <!-- General Info Tab -->
                    <form method="POST" action="settings.php?tab=general">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="business_name" class="block text-gray-700 font-medium mb-2">שם העסק</label>
                                <input type="text" id="business_name" name="business_name" 
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                       value="<?php echo htmlspecialchars($tenant['business_name']); ?>" required>
                            </div>
                            
                            <div>
                                <label for="slug" class="block text-gray-700 font-medium mb-2">סלאג (כתובת URL)</label>
                                <div class="flex items-center">
                                    <span class="bg-gray-100 text-gray-500 px-3 py-3 border-2 border-r-0 border-gray-300 rounded-r-xl">
                                        <?php echo htmlspecialchars($_SERVER['HTTP_HOST']); ?>/booking/
                                    </span>
                                    <input type="text" id="slug" name="slug" dir="ltr"
                                       class="flex-grow px-4 py-3 border-2 border-l-0 border-gray-300 rounded-l-xl focus:outline-none focus:border-primary"
                                       value="<?php echo htmlspecialchars($tenant['slug']); ?>" required>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">רק אותיות באנגלית קטנות, מספרים ומקפים</p>
                            </div>
                            
                            <div>
                                <label for="email" class="block text-gray-700 font-medium mb-2">כתובת אימייל</label>
                                <input type="email" id="email" name="email" dir="ltr"
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                       value="<?php echo htmlspecialchars($tenant['email']); ?>" required>
                            </div>
                            
                            <div>
                                <label for="phone" class="block text-gray-700 font-medium mb-2">טלפון</label>
                                <input type="tel" id="phone" name="phone" dir="ltr"
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                       value="<?php echo htmlspecialchars($tenant['phone']); ?>" required>
                            </div>
                            
                            <div class="md:col-span-2">
                                <label for="address" class="block text-gray-700 font-medium mb-2">כתובת העסק</label>
                                <input type="text" id="address" name="address" 
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                       value="<?php echo htmlspecialchars($tenant['address']); ?>">
                            </div>
                            
                            <div class="md:col-span-2">
                                <label for="business_description" class="block text-gray-700 font-medium mb-2">תיאור העסק</label>
                                <textarea id="business_description" name="business_description" rows="3"
                                         class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"><?php echo htmlspecialchars($tenant['business_description']); ?></textarea>
                                <p class="text-xs text-gray-500 mt-1">תיאור קצר שיוצג בדף הנחיתה של העסק</p>
                            </div>
                            
                            <div>
                                <label for="facebook_url" class="block text-gray-700 font-medium mb-2">קישור לפייסבוק</label>
                                <input type="url" id="facebook_url" name="facebook_url" dir="ltr"
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                       value="<?php echo htmlspecialchars($tenant['facebook_url']); ?>">
                            </div>
                            
                            <div>
                                <label for="instagram_url" class="block text-gray-700 font-medium mb-2">קישור לאינסטגרם</label>
                                <input type="url" id="instagram_url" name="instagram_url" dir="ltr"
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                       value="<?php echo htmlspecialchars($tenant['instagram_url']); ?>">
                            </div>
                            
                            <div class="md:col-span-2">
                                <label for="website_url" class="block text-gray-700 font-medium mb-2">כתובת אתר האינטרנט</label>
                                <input type="url" id="website_url" name="website_url" dir="ltr"
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                       value="<?php echo htmlspecialchars($tenant['website_url']); ?>">
                            </div>
                        </div>
                        
                        <div class="mt-8 flex justify-end">
                            <button type="submit" name="update_general" class="bg-primary hover:bg-primary-dark text-white font-bold py-3 px-6 rounded-xl transition duration-300">
                                שמור שינויים
                            </button>
                        </div>
                    </form>
                
                <?php elseif ($current_tab == 'images'): ?>
                    <!-- Images Tab -->
                    <form method="POST" action="settings.php?tab=images" enctype="multipart/form-data">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                            <div>
                                <h3 class="text-lg font-semibold mb-4">לוגו העסק</h3>
                                <div class="mb-4 flex justify-center">
                                    <?php if (!empty($tenant['logo_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($tenant['logo_path']); ?>" 
                                             alt="לוגו העסק" class="w-32 h-32 object-cover border-2 border-gray-200 rounded-full">
                                    <?php else: ?>
                                        <div class="w-32 h-32 bg-gray-200 rounded-full flex items-center justify-center">
                                            <i class="fas fa-image text-gray-400 text-4xl"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="relative border-2 border-dashed border-gray-300 rounded-xl p-6 text-center">
                                    <input type="file" id="logo" name="logo" accept="image/*" class="hidden">
                                    <label for="logo" class="cursor-pointer">
                                        <div class="text-primary mb-2"><i class="fas fa-upload text-xl"></i></div>
                                        <p class="text-gray-500">לחץ כדי להעלות לוגו</p>
                                        <p class="text-xs text-gray-400 mt-1">מומלץ: תמונה מרובעת באיכות גבוהה</p>
                                        <p class="text-xs text-gray-400">פורמטים מותרים: JPG, PNG, GIF</p>
                                    </label>
                                </div>
                                <p class="text-sm text-gray-500 mt-2">הלוגו יוצג בדף העסק ובחלק העליון של ההודעות הנשלחות ללקוחות.</p>
                            </div>
                            
                            <div>
                                <h3 class="text-lg font-semibold mb-4">תמונת רקע (כיסוי)</h3>
                                <div class="mb-4 flex justify-center h-40 overflow-hidden rounded-xl bg-gray-200">
                                    <?php if (!empty($tenant['cover_image_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($tenant['cover_image_path']); ?>" 
                                             alt="תמונת רקע" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center">
                                            <i class="fas fa-image text-gray-400 text-4xl"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="relative border-2 border-dashed border-gray-300 rounded-xl p-6 text-center">
                                    <input type="file" id="cover_image" name="cover_image" accept="image/*" class="hidden">
                                    <label for="cover_image" class="cursor-pointer">
                                        <div class="text-primary mb-2"><i class="fas fa-upload text-xl"></i></div>
                                        <p class="text-gray-500">לחץ כדי להעלות תמונת רקע</p>
                                        <p class="text-xs text-gray-400 mt-1">מומלץ: תמונה באיכות גבוהה ברוחב 1200px</p>
                                        <p class="text-xs text-gray-400">פורמטים מותרים: JPG, PNG, GIF</p>
                                    </label>
                                </div>
                                <p class="text-sm text-gray-500 mt-2">תמונת הרקע תוצג בחלק העליון של דף הנחיתה של העסק.</p>
                            </div>
                        </div>
                        
                        <div class="mt-8 flex justify-end">
                            <button type="submit" name="update_images" class="bg-primary hover:bg-primary-dark text-white font-bold py-3 px-6 rounded-xl transition duration-300">
                                שמור שינויים
                            </button>
                        </div>
                    </form>
                
                <?php elseif ($current_tab == 'password'): ?>
                    <!-- Password Tab -->
                    <form method="POST" action="settings.php?tab=password">
                        <div class="max-w-md mx-auto">
                            <div class="mb-6">
                                <label for="current_password" class="block text-gray-700 font-medium mb-2">סיסמה נוכחית</label>
                                <input type="password" id="current_password" name="current_password" 
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                       required>
                            </div>
                            
                            <div class="mb-6">
                                <label for="new_password" class="block text-gray-700 font-medium mb-2">סיסמה חדשה</label>
                                <input type="password" id="new_password" name="new_password" 
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                       required>
                                <p class="text-xs text-gray-500 mt-1">לפחות 6 תווים</p>
                            </div>
                            
                            <div class="mb-6">
                                <label for="confirm_password" class="block text-gray-700 font-medium mb-2">אימות סיסמה חדשה</label>
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                       required>
                            </div>
                            
                            <div class="mt-8 flex justify-end">
                                <button type="submit" name="update_password" class="bg-primary hover:bg-primary-dark text-white font-bold py-3 px-6 rounded-xl transition duration-300">
                                    עדכן סיסמה
                                </button>
                            </div>
                        </div>
                    </form>
                
                <?php elseif ($current_tab == 'subscription'): ?>
                    <!-- תוכן טאב מסלול -->
                    <div class="bg-white">
                        <h2 class="text-2xl font-bold text-gray-800 mb-6">המסלול שלך</h2>
                        
                        <?php
                        // שליפת פרטי המסלול הנוכחי
                        $query = "SELECT s.*, COUNT(st.staff_id) as current_staff_count 
                                 FROM subscriptions s 
                                 JOIN tenant_subscriptions ts ON ts.subscription_id = s.id
                                 LEFT JOIN staff st ON st.tenant_id = :tenant_id1 
                                 WHERE ts.tenant_id = :tenant_id2 
                                 GROUP BY s.id, s.max_staff_members";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(":tenant_id1", $tenant_id);
                        $stmt->bindParam(":tenant_id2", $tenant_id);
                        $stmt->execute();
                        $current_subscription = $stmt->fetch();

                        if (!$current_subscription) {
                            // אם אין מסלול פעיל, נקבל את המסלול הבסיסי
                            $query = "SELECT * FROM subscriptions WHERE name = 'מסלול בסיסי'";
                            $stmt = $db->prepare($query);
                            $stmt->execute();
                            $current_subscription = $stmt->fetch();

                            if ($current_subscription) {
                                // הוספת המסלול הבסיסי לדייר
                                $query = "INSERT INTO tenant_subscriptions (tenant_id, subscription_id) VALUES (:tenant_id, :subscription_id)";
                                $stmt = $db->prepare($query);
                                $stmt->bindParam(":tenant_id", $tenant_id);
                                $stmt->bindParam(":subscription_id", $current_subscription['id']);
                                $stmt->execute();

                                // שליפת מספר אנשי הצוות הנוכחי
                                $query = "SELECT COUNT(*) as current_staff_count FROM staff WHERE tenant_id = :tenant_id";
                                $stmt = $db->prepare($query);
                                $stmt->bindParam(":tenant_id", $tenant_id);
                                $stmt->execute();
                                $staff_count = $stmt->fetch();
                                $current_subscription['current_staff_count'] = $staff_count['current_staff_count'];
                            }
                        }
                        
                        // שליפת כל המסלולים האפשריים
                        $query = "SELECT * FROM subscriptions ORDER BY price ASC";
                        $stmt = $db->prepare($query);
                        $stmt->execute();
                        $all_subscriptions = $stmt->fetchAll();
                        ?>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <?php foreach ($all_subscriptions as $subscription): ?>
                                <div class="border rounded-xl p-6 <?php echo ($current_subscription && $subscription['id'] == $current_subscription['id']) ? 'border-primary bg-primary bg-opacity-5' : 'border-gray-200'; ?>">
                                    <h3 class="text-xl font-bold mb-2"><?php echo htmlspecialchars($subscription['name']); ?></h3>
                                    <div class="text-3xl font-bold mb-4">
                                        ₪<?php echo number_format($subscription['price'], 2); ?>
                                        <span class="text-sm font-normal text-gray-500">/ חודש</span>
                                    </div>
                                    
                                    <ul class="mb-6 space-y-2">
                                        <li class="flex items-center">
                                            <i class="fas fa-users text-primary ml-2"></i>
                                            עד <?php echo $subscription['max_staff_members']; ?> אנשי צוות
                                            <?php if ($current_subscription && $subscription['id'] == $current_subscription['id']): ?>
                                                <span class="text-sm text-gray-500 mr-2">(<?php echo $current_subscription['current_staff_count']; ?> בשימוש)</span>
                                            <?php endif; ?>
                                        </li>
                                        <?php 
                                        $features = json_decode($subscription['features'], true);
                                        if (isset($features['features'])):
                                            foreach ($features['features'] as $feature): 
                                        ?>
                                            <li class="flex items-center">
                                                <i class="fas fa-check text-primary ml-2"></i>
                                                <?php echo htmlspecialchars($feature); ?>
                                            </li>
                                        <?php 
                                            endforeach;
                                        endif;
                                        ?>
                                    </ul>
                                    
                                    <?php if ($current_subscription && $subscription['id'] == $current_subscription['id']): ?>
                                        <button class="w-full bg-primary text-white font-bold py-2 px-4 rounded-xl opacity-50 cursor-not-allowed">
                                            המסלול הנוכחי שלך
                                        </button>
                                    <?php elseif ($current_subscription && $subscription['price'] > $current_subscription['price']): ?>
                                        <button class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-2 px-4 rounded-xl transition duration-300" 
                                                onclick="upgradeSubscription(<?php echo $subscription['id']; ?>)">
                                            שדרג למסלול זה
                                        </button>
                                    <?php else: ?>
                                        <button class="w-full bg-secondary hover:bg-secondary-dark text-white font-bold py-2 px-4 rounded-xl transition duration-300" 
                                                onclick="changeSubscription(<?php echo $subscription['id']; ?>)">
                                            שנה למסלול זה
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                <?php elseif ($current_tab == 'templates'): ?>
                    <!-- תוכן טאב תבניות הודעה -->
                    <form method="POST" action="settings.php?tab=templates">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <!-- Booking Confirmation Template -->
                            <div>
                                <h3 class="text-lg font-semibold mb-4">תבנית אישור הזמנה</h3>
                                
                                <div class="mb-4">
                                    <label for="booking_subject" class="block text-gray-700 font-medium mb-2">נושא ההודעה</label>
                                    <input type="text" id="booking_subject" name="booking_subject" 
                                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                           value="<?php echo isset($templates['booking_confirmation']) ? htmlspecialchars($templates['booking_confirmation']['subject']) : ''; ?>" required>
                                </div>
                                
                                <div>
                                    <label for="booking_message" class="block text-gray-700 font-medium mb-2">תוכן ההודעה</label>
                                    <textarea id="booking_message" name="booking_message" rows="12"
                                              class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                              required><?php echo isset($templates['booking_confirmation']) ? htmlspecialchars($templates['booking_confirmation']['message_text']) : ''; ?></textarea>
                                </div>
                                
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">משתנים לשימוש:</p>
                                    <ul class="text-xs text-gray-500 list-disc list-inside space-y-1 mt-1">
                                        <li>{first_name} - שם פרטי של הלקוח</li>
                                        <li>{service_name} - שם השירות</li>
                                        <li>{appointment_date} - תאריך התור</li>
                                        <li>{appointment_time} - שעת התור</li>
                                        <li>{staff_name} - שם נותן השירות</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <!-- Appointment Reminder Template -->
                            <div>
                                <h3 class="text-lg font-semibold mb-4">תבנית תזכורת לתור</h3>
                                
                                <div class="mb-4">
                                    <label for="reminder_subject" class="block text-gray-700 font-medium mb-2">נושא ההודעה</label>
                                    <input type="text" id="reminder_subject" name="reminder_subject" 
                                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                           value="<?php echo isset($templates['appointment_reminder']) ? htmlspecialchars($templates['appointment_reminder']['subject']) : ''; ?>" required>
                                </div>
                                
                                <div>
                                    <label for="reminder_message" class="block text-gray-700 font-medium mb-2">תוכן ההודעה</label>
                                    <textarea id="reminder_message" name="reminder_message" rows="12"
                                              class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                              required><?php echo isset($templates['appointment_reminder']) ? htmlspecialchars($templates['appointment_reminder']['message_text']) : ''; ?></textarea>
                                </div>
                                
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">משתנים לשימוש:</p>
                                    <ul class="text-xs text-gray-500 list-disc list-inside space-y-1 mt-1">
                                        <li>{first_name} - שם פרטי של הלקוח</li>
                                        <li>{service_name} - שם השירות</li>
                                        <li>{appointment_date} - תאריך התור</li>
                                        <li>{appointment_time} - שעת התור</li>
                                        <li>{staff_name} - שם נותן השירות</li>
                                        <li>{confirmation_link} - קישור לאישור התור</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-8 flex justify-end">
                            <button type="submit" name="update_templates" class="bg-primary hover:bg-primary-dark text-white font-bold py-3 px-6 rounded-xl transition duration-300">
                                שמור שינויים
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
function upgradeSubscription(subscriptionId) {
    if (confirm('האם אתה בטוח שברצונך לשדרג למסלול זה?')) {
        changeSubscription(subscriptionId);
    }
}

function changeSubscription(subscriptionId) {
    fetch('api/change_subscription.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'subscription_id=' + subscriptionId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'אירעה שגיאה בשינוי המסלול');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('אירעה שגיאה בשינוי המסלול');
    });
}

// תצוגה מקדימה של תמונות בעת בחירתן
document.addEventListener('DOMContentLoaded', function() {
    const logoInput = document.getElementById('logo');
    const coverInput = document.getElementById('cover_image');
    
    if (logoInput) {
        logoInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const logoPreview = logoInput.parentElement.parentElement.previousElementSibling;
                    logoPreview.innerHTML = `<img src="${e.target.result}" alt="תצוגה מקדימה של לוגו" class="w-32 h-32 object-cover border-2 border-gray-200 rounded-full">`;
                };
                
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
    
    if (coverInput) {
        coverInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const coverPreview = coverInput.parentElement.parentElement.previousElementSibling;
                    coverPreview.innerHTML = `<img src="${e.target.result}" alt="תצוגה מקדימה של תמונת רקע" class="w-full h-full object-cover">`;
                };
                
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
});
</script>

<?php include_once "includes/footer.php"; ?>