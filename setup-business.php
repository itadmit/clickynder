<?php
// התחלת או המשך סשן
session_start();

// טעינת קבצי התצורה
require_once "config/database.php";
require_once "includes/functions.php";

// בדיקה האם המשתמש מחובר
if (!isLoggedIn()) {
    redirect("login.php");
}

// משתנים להודעות שגיאה והצלחה
$error_message = "";
$success_message = "";

// חיבור למסד הנתונים
$database = new Database();
$db = $database->getConnection();

// קבלת פרטי העסק הקיימים
$tenant_id = $_SESSION['tenant_id'];
$query = "SELECT * FROM tenants WHERE tenant_id = :tenant_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":tenant_id", $tenant_id);
$stmt->execute();
$tenant = $stmt->fetch();

// שמירת פרטי העסק במשתני סשן
$_SESSION['business_name'] = $tenant['business_name'];
$_SESSION['slug'] = $tenant['slug'];

// בדיקה האם הטופס נשלח
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // קבלת הנתונים מהטופס
    $address = sanitizeInput($_POST['address']);
    $business_description = sanitizeInput($_POST['business_description']);
    $facebook_url = sanitizeInput($_POST['facebook_url']);
    $instagram_url = sanitizeInput($_POST['instagram_url']);
    $website_url = sanitizeInput($_POST['website_url']);
    
    // עדכון פרטי העסק
    try {
        $query = "UPDATE tenants SET address = :address, business_description = :business_description, 
                  facebook_url = :facebook_url, instagram_url = :instagram_url, website_url = :website_url 
                  WHERE tenant_id = :tenant_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":address", $address);
        $stmt->bindParam(":business_description", $business_description);
        $stmt->bindParam(":facebook_url", $facebook_url);
        $stmt->bindParam(":instagram_url", $instagram_url);
        $stmt->bindParam(":website_url", $website_url);
        $stmt->bindParam(":tenant_id", $tenant_id);
        
        if ($stmt->execute()) {
            // עדכון תמונת לוגו או רקע אם הועלו
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
                $logo_path = processUploadedImage('logo', $tenant_id);
                if ($logo_path) {
                    $query = "UPDATE tenants SET logo_path = :logo_path WHERE tenant_id = :tenant_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(":logo_path", $logo_path);
                    $stmt->bindParam(":tenant_id", $tenant_id);
                    $stmt->execute();
                }
            }
            
            if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] == 0) {
                $cover_path = processUploadedImage('cover_image', $tenant_id);
                if ($cover_path) {
                    $query = "UPDATE tenants SET cover_image_path = :cover_path WHERE tenant_id = :tenant_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(":cover_path", $cover_path);
                    $stmt->bindParam(":tenant_id", $tenant_id);
                    $stmt->execute();
                }
            }
            
            $success_message = "פרטי העסק עודכנו בהצלחה!";
            
            // הפניה לדף הבא
            header("Refresh: 2; URL=setup-services.php");
        } else {
            $error_message = "שגיאה בעדכון פרטי העסק, אנא נסה שנית";
        }
    } catch (PDOException $e) {
        $error_message = "שגיאת מערכת: " . $e->getMessage();
    }
}

// פונקציה לטיפול בהעלאת תמונות
function processUploadedImage($file_key, $tenant_id) {
    $upload_dir = "uploads/tenants/" . $tenant_id . "/";
    
    // יצירת תיקייה אם לא קיימת
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_name = basename($_FILES[$file_key]["name"]);
    $target_file = $upload_dir . time() . "_" . $file_name;
    $file_tmp = $_FILES[$file_key]["tmp_name"];
    $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // בדיקת סוג הקובץ
    $allowed_types = ["jpg", "jpeg", "png", "gif"];
    if (!in_array($file_type, $allowed_types)) {
        return false;
    }
    
    // העברת הקובץ למיקום הסופי
    if (move_uploaded_file($file_tmp, $target_file)) {
        return $target_file;
    }
    
    return false;
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>קליקינדר - הגדרת פרטי עסק</title>
    <!-- טעינת גופן Noto Sans Hebrew מ-Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Hebrew:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- טעינת Tailwind CSS מ-CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            light: '#f687b3', // ורוד בהיר
                            DEFAULT: '#ec4899', // ורוד
                            dark: '#db2777', // ורוד כהה
                        },
                        secondary: {
                            light: '#93c5fd', // כחול בהיר
                            DEFAULT: '#3b82f6', // כחול
                            dark: '#2563eb', // כחול כהה
                        },
                        pastel: {
                            purple: '#d8b4fe', // סגול פסטל
                            blue: '#bfdbfe',   // כחול פסטל
                            green: '#bbf7d0',  // ירוק פסטל
                            yellow: '#fef08a', // צהוב פסטל
                            orange: '#fed7aa', // כתום פסטל
                            red: '#fecaca',    // אדום פסטל
                        }
                    },
                    fontFamily: {
                        'noto': ['"Noto Sans Hebrew"', 'sans-serif'],
                    },
                    borderRadius: {
                        'xl': '1rem',
                        '2xl': '1.5rem',
                        '3xl': '2rem',
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Noto Sans Hebrew', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="text-center mb-10">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">קליקינדר</h1>
            <p class="text-gray-600">מערכת ניהול תורים חכמה</p>
        </div>
        
        <!-- Progress Bar -->
        <div class="max-w-3xl mx-auto mb-8">
            <div class="flex justify-between mb-2">
                <div class="text-center">
                    <div class="w-10 h-10 bg-primary text-white rounded-full flex items-center justify-center mx-auto mb-1">1</div>
                    <span class="text-sm text-primary font-medium">הרשמה</span>
                </div>
                <div class="text-center">
                    <div class="w-10 h-10 bg-primary text-white rounded-full flex items-center justify-center mx-auto mb-1">2</div>
                    <span class="text-sm text-primary font-medium">פרטי עסק</span>
                </div>
                <div class="text-center">
                    <div class="w-10 h-10 bg-gray-300 text-gray-600 rounded-full flex items-center justify-center mx-auto mb-1">3</div>
                    <span class="text-sm text-gray-600">הגדרת שירותים</span>
                </div>
                <div class="text-center">
                    <div class="w-10 h-10 bg-gray-300 text-gray-600 rounded-full flex items-center justify-center mx-auto mb-1">4</div>
                    <span class="text-sm text-gray-600">שעות פעילות</span>
                </div>
            </div>
            <div class="relative pt-1">
                <div class="overflow-hidden h-2 text-xs flex rounded bg-primary-light">
                    <div style="width:50%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-primary"></div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="max-w-3xl mx-auto">
            <div class="bg-white p-8 rounded-2xl shadow-lg">
                <h2 class="text-2xl font-bold mb-6">הגדרת פרטי העסק</h2>
                
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
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data" class="space-y-6">
                    <!-- פרטי מיקום -->
                    <div>
                        <h3 class="text-lg font-semibold mb-4">מיקום העסק</h3>
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label for="address" class="block text-gray-700 font-medium mb-2">כתובת העסק</label>
                                <input type="text" id="address" name="address" 
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                       placeholder="שם הרחוב, מספר בית, עיר"
                                       value="<?php echo htmlspecialchars($tenant['address'] ?? ''); ?>">
                                <p class="text-sm text-gray-500 mt-1">הכתובת תוצג בדף העסק שלך</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- תיאור העסק -->
                    <div>
                        <h3 class="text-lg font-semibold mb-4">תיאור העסק</h3>
                        <div>
                            <label for="business_description" class="block text-gray-700 font-medium mb-2">תיאור העסק</label>
                            <textarea id="business_description" name="business_description" rows="4"
                                      class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                      placeholder="ספר ללקוחות על העסק שלך, מה מייחד אותו, מה השירותים שאתה מציע וכו'"><?php echo htmlspecialchars($tenant['business_description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <!-- תמונות -->
                    <div>
                        <h3 class="text-lg font-semibold mb-4">תמונות</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="logo" class="block text-gray-700 font-medium mb-2">לוגו העסק</label>
                                <div class="relative border-2 border-dashed border-gray-300 rounded-xl p-6 text-center">
                                    <input type="file" id="logo" name="logo" accept="image/*" class="hidden">
                                    <div id="logo-preview" class="mb-4 flex justify-center <?php echo !empty($tenant['logo_path']) ? '' : 'hidden'; ?>">
                                        <img src="<?php echo htmlspecialchars($tenant['logo_path'] ?? ''); ?>" class="max-h-32 max-w-full">
                                    </div>
                                    <label for="logo" class="cursor-pointer">
                                        <div class="text-primary mb-2"><i class="fas fa-upload text-xl"></i></div>
                                        <p id="logo-text" class="text-gray-500">לחץ כדי להעלות לוגו</p>
                                        <p class="text-xs text-gray-400 mt-1">פורמטים מותרים: JPG, PNG, GIF</p>
                                    </label>
                                </div>
                            </div>
                            
                            <div>
                                <label for="cover_image" class="block text-gray-700 font-medium mb-2">תמונת רקע</label>
                                <div class="relative border-2 border-dashed border-gray-300 rounded-xl p-6 text-center">
                                    <input type="file" id="cover_image" name="cover_image" accept="image/*" class="hidden">
                                    <div id="cover-preview" class="mb-4 flex justify-center <?php echo !empty($tenant['cover_image_path']) ? '' : 'hidden'; ?>">
                                        <img src="<?php echo htmlspecialchars($tenant['cover_image_path'] ?? ''); ?>" class="max-h-32 max-w-full">
                                    </div>
                                    <label for="cover_image" class="cursor-pointer">
                                        <div class="text-primary mb-2"><i class="fas fa-image text-xl"></i></div>
                                        <p id="cover-text" class="text-gray-500">לחץ כדי להעלות תמונת רקע</p>
                                        <p class="text-xs text-gray-400 mt-1">מומלץ: תמונה באיכות גבוהה ברוחב 1200px</p>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- פרטי קשר ורשתות חברתיות -->
                    <div>
                        <h3 class="text-lg font-semibold mb-4">רשתות חברתיות (אופציונלי)</h3>
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label for="facebook_url" class="block text-gray-700 font-medium mb-2">פייסבוק</label>
                                <div class="flex items-center">
                                    <span class="bg-gray-100 p-3 text-primary border-2 border-l-0 border-gray-300 rounded-r-xl">
                                        <i class="fab fa-facebook-f"></i>
                                    </span>
                                    <input type="url" id="facebook_url" name="facebook_url" 
                                           class="flex-grow px-4 py-3 border-2 border-r-0 border-gray-300 rounded-l-xl focus:outline-none focus:border-primary"
                                           placeholder="https://www.facebook.com/your-page" dir="ltr"
                                           value="<?php echo htmlspecialchars($tenant['facebook_url'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div>
                                <label for="instagram_url" class="block text-gray-700 font-medium mb-2">אינסטגרם</label>
                                <div class="flex items-center">
                                    <span class="bg-gray-100 p-3 text-primary border-2 border-l-0 border-gray-300 rounded-r-xl">
                                        <i class="fab fa-instagram"></i>
                                    </span>
                                    <input type="url" id="instagram_url" name="instagram_url" 
                                           class="flex-grow px-4 py-3 border-2 border-r-0 border-gray-300 rounded-l-xl focus:outline-none focus:border-primary"
                                           placeholder="https://www.instagram.com/your-username" dir="ltr"
                                           value="<?php echo htmlspecialchars($tenant['instagram_url'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div>
                                <label for="website_url" class="block text-gray-700 font-medium mb-2">אתר אינטרנט</label>
                                <div class="flex items-center">
                                    <span class="bg-gray-100 p-3 text-primary border-2 border-l-0 border-gray-300 rounded-r-xl">
                                        <i class="fas fa-globe"></i>
                                    </span>
                                    <input type="url" id="website_url" name="website_url" 
                                           class="flex-grow px-4 py-3 border-2 border-r-0 border-gray-300 rounded-l-xl focus:outline-none focus:border-primary"
                                           placeholder="https://www.your-website.com" dir="ltr"
                                           value="<?php echo htmlspecialchars($tenant['website_url'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- כפתורי פעולה -->
                    <div class="flex justify-between pt-6">
                        <a href="dashboard.php" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-xl transition duration-300">
                            דלג בינתיים
                        </a>
                        <button type="submit" class="px-8 py-3 bg-primary hover:bg-primary-dark text-white font-bold rounded-xl transition duration-300 flex items-center">
                            המשך
                            <i class="fas fa-arrow-left mr-2"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // תצוגה מקדימה של תמונות
        document.getElementById('logo').addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                const file = e.target.files[0];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const preview = document.getElementById('logo-preview');
                    preview.innerHTML = `<img src="${e.target.result}" class="max-h-32 max-w-full">`;
                    preview.classList.remove('hidden');
                    document.getElementById('logo-text').textContent = file.name;
                }
                
                reader.readAsDataURL(file);
            }
        });
        
        document.getElementById('cover_image').addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                const file = e.target.files[0];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const preview = document.getElementById('cover-preview');
                    preview.innerHTML = `<img src="${e.target.result}" class="max-h-32 max-w-full">`;
                    preview.classList.remove('hidden');
                    document.getElementById('cover-text').textContent = file.name;
                }
                
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>