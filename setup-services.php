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

// קבלת קטגוריות שירותים קיימות
$query = "SELECT * FROM service_categories WHERE tenant_id = :tenant_id ORDER BY display_order ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(":tenant_id", $tenant_id);
$stmt->execute();
$categories = $stmt->fetchAll();

// קבלת שירותים קיימים
$query = "SELECT * FROM services WHERE tenant_id = :tenant_id ORDER BY category_id, name ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(":tenant_id", $tenant_id);
$stmt->execute();
$services = $stmt->fetchAll();

// פעולה: הוספת קטגוריה
if (isset($_POST['add_category'])) {
    $category_name = sanitizeInput($_POST['category_name']);
    $category_description = sanitizeInput($_POST['category_description']);
    
    if (empty($category_name)) {
        $error_message = "נא להזין שם קטגוריה";
    } else {
        try {
            // קבלת מספר סידורי הבא
            $query = "SELECT MAX(display_order) as max_order FROM service_categories WHERE tenant_id = :tenant_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":tenant_id", $tenant_id);
            $stmt->execute();
            $max_order = $stmt->fetch()['max_order'] ?? 0;
            $new_order = $max_order + 1;
            
            // הוספת הקטגוריה
            $query = "INSERT INTO service_categories (tenant_id, name, description, display_order) 
                      VALUES (:tenant_id, :name, :description, :display_order)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":tenant_id", $tenant_id);
            $stmt->bindParam(":name", $category_name);
            $stmt->bindParam(":description", $category_description);
            $stmt->bindParam(":display_order", $new_order);
            
            if ($stmt->execute()) {
                $success_message = "הקטגוריה נוספה בהצלחה";
                // רענון הקטגוריות
                $query = "SELECT * FROM service_categories WHERE tenant_id = :tenant_id ORDER BY display_order ASC";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":tenant_id", $tenant_id);
                $stmt->execute();
                $categories = $stmt->fetchAll();
            } else {
                $error_message = "שגיאה בהוספת הקטגוריה";
            }
        } catch (PDOException $e) {
            $error_message = "שגיאת מערכת: " . $e->getMessage();
        }
    }
}

// פעולה: הוספת שירות
if (isset($_POST['add_service'])) {
    $service_name = sanitizeInput($_POST['service_name']);
    $service_description = sanitizeInput($_POST['service_description']);
    $service_duration = intval($_POST['service_duration']);
    $service_price = floatval(str_replace(',', '', $_POST['service_price']));
    $service_category = intval($_POST['service_category']);
    $buffer_before = intval($_POST['buffer_before']);
    $buffer_after = intval($_POST['buffer_after']);
    $service_color = sanitizeInput($_POST['service_color']);
    
    if (empty($service_name) || $service_duration <= 0) {
        $error_message = "נא למלא את כל השדות החובה";
    } else {
        try {
            $query = "INSERT INTO services (tenant_id, category_id, name, description, duration, price, 
                     buffer_time_before, buffer_time_after, color, is_active) 
                     VALUES (:tenant_id, :category_id, :name, :description, :duration, :price, 
                     :buffer_before, :buffer_after, :color, 1)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":tenant_id", $tenant_id);
            $stmt->bindParam(":category_id", $service_category);
            $stmt->bindParam(":name", $service_name);
            $stmt->bindParam(":description", $service_description);
            $stmt->bindParam(":duration", $service_duration);
            $stmt->bindParam(":price", $service_price);
            $stmt->bindParam(":buffer_before", $buffer_before);
            $stmt->bindParam(":buffer_after", $buffer_after);
            $stmt->bindParam(":color", $service_color);
            
            if ($stmt->execute()) {
                $service_id = $db->lastInsertId();
                
                // שיוך השירות לכל אנשי הצוות
                $query = "SELECT staff_id FROM staff WHERE tenant_id = :tenant_id AND is_active = 1";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":tenant_id", $tenant_id);
                $stmt->execute();
                $staff_members = $stmt->fetchAll();
                
                if (!empty($staff_members)) {
                    $query = "INSERT INTO service_staff (service_id, staff_id) VALUES (:service_id, :staff_id)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(":service_id", $service_id);
                    
                    foreach ($staff_members as $staff) {
                        $stmt->bindParam(":staff_id", $staff['staff_id']);
                        $stmt->execute();
                    }
                }
                
                $success_message = "השירות נוסף בהצלחה";
                
                // רענון השירותים
                $query = "SELECT * FROM services WHERE tenant_id = :tenant_id ORDER BY category_id, name ASC";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":tenant_id", $tenant_id);
                $stmt->execute();
                $services = $stmt->fetchAll();
            } else {
                $error_message = "שגיאה בהוספת השירות";
            }
        } catch (PDOException $e) {
            $error_message = "שגיאת מערכת: " . $e->getMessage();
        }
    }
}

// פעולה: מחיקת שירות
if (isset($_POST['delete_service'])) {
    $service_id = intval($_POST['service_id']);
    
    try {
        // מחיקת השירות
        $query = "DELETE FROM services WHERE service_id = :service_id AND tenant_id = :tenant_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":service_id", $service_id);
        $stmt->bindParam(":tenant_id", $tenant_id);
        
        if ($stmt->execute()) {
            $success_message = "השירות נמחק בהצלחה";
            
            // רענון השירותים
            $query = "SELECT * FROM services WHERE tenant_id = :tenant_id ORDER BY category_id, name ASC";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":tenant_id", $tenant_id);
            $stmt->execute();
            $services = $stmt->fetchAll();
        } else {
            $error_message = "שגיאה במחיקת השירות";
        }
    } catch (PDOException $e) {
        $error_message = "שגיאת מערכת: " . $e->getMessage();
    }
}

// פעולה: סיום הגדרת שירותים
if (isset($_POST['finish_setup'])) {
    redirect("setup-hours.php");
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>קליקינדר - הגדרת שירותים</title>
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
        
        /* Styling for color picker */
        input[type="color"] {
            -webkit-appearance: none;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
        }
        input[type="color"]::-webkit-color-swatch-wrapper {
            padding: 0;
            border-radius: 50%;
        }
        input[type="color"]::-webkit-color-swatch {
            border: none;
            border-radius: 50%;
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
                    <div class="w-10 h-10 bg-primary text-white rounded-full flex items-center justify-center mx-auto mb-1">3</div>
                    <span class="text-sm text-primary font-medium">הגדרת שירותים</span>
                </div>
                <div class="text-center">
                    <div class="w-10 h-10 bg-gray-300 text-gray-600 rounded-full flex items-center justify-center mx-auto mb-1">4</div>
                    <span class="text-sm text-gray-600">שעות פעילות</span>
                </div>
            </div>
            <div class="relative pt-1">
                <div class="overflow-hidden h-2 text-xs flex rounded bg-primary-light">
                    <div style="width:75%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-primary"></div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="max-w-4xl mx-auto">
            <div class="bg-white p-8 rounded-2xl shadow-lg">
                <h2 class="text-2xl font-bold mb-6">הגדרת השירותים שלך</h2>
                
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
                
                <!-- Tabs -->
                <div class="mb-6">
                    <ul class="flex border-b">
                        <li class="mr-1">
                            <a href="#categories" class="tab-link active inline-block py-2 px-4 text-primary font-semibold" 
                               data-tab="categories">קטגוריות</a>
                        </li>
                        <li class="mr-1">
                            <a href="#services" class="tab-link inline-block py-2 px-4 text-gray-500 hover:text-primary" 
                               data-tab="services">שירותים</a>
                        </li>
                    </ul>
                </div>
                
                <!-- Tab Content -->
                <div class="tab-content">
                    <!-- קטגוריות -->
                    <div id="categories-content" class="tab-pane active">
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold mb-4">הוספת קטגוריה חדשה</h3>
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="md:col-span-2">
                                    <label for="category_name" class="block text-gray-700 font-medium mb-1">שם הקטגוריה *</label>
                                    <input type="text" id="category_name" name="category_name" required
                                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                           placeholder="לדוגמה: תספורות, טיפולי פנים, וכו'">
                                </div>
                                <div class="md:col-span-1">
                                    <label class="block text-gray-700 font-medium mb-1 md:opacity-0">הוספה</label>
                                    <button type="submit" name="add_category" 
                                            class="w-full py-2 px-4 bg-primary hover:bg-primary-dark text-white font-bold rounded-xl transition duration-300">
                                        <i class="fas fa-plus mr-1"></i> הוסף קטגוריה
                                    </button>
                                </div>
                                <div class="md:col-span-3">
                                    <label for="category_description" class="block text-gray-700 font-medium mb-1">תיאור (אופציונלי)</label>
                                    <textarea id="category_description" name="category_description" rows="2"
                                              class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                              placeholder="תיאור קצר של הקטגוריה"></textarea>
                                </div>
                            </form>
                        </div>
                        
                        <hr class="my-6">
                        
                        <div>
                            <h3 class="text-lg font-semibold mb-4">הקטגוריות שלך</h3>
                            
                            <?php if (empty($categories)): ?>
                                <div class="text-center py-12 border-2 border-dashed border-gray-300 rounded-xl">
                                    <div class="text-gray-400 mb-3"><i class="fas fa-folder-open text-5xl"></i></div>
                                    <p class="text-gray-500">עוד אין לך קטגוריות</p>
                                    <p class="text-gray-500 text-sm">הוסף קטגוריות כדי לארגן את השירותים שלך</p>
                                </div>
                            <?php else: ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <?php foreach ($categories as $category): ?>
                                        <div class="p-4 border border-gray-200 rounded-xl hover:border-primary transition duration-300">
                                            <div class="flex justify-between items-start">
                                                <div>
                                                    <h4 class="font-semibold text-lg"><?php echo htmlspecialchars($category['name']); ?></h4>
                                                    <?php if (!empty($category['description'])): ?>
                                                        <p class="text-gray-600 text-sm mt-1"><?php echo htmlspecialchars($category['description']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex space-x-2 space-x-reverse">
                                                    <button class="text-gray-400 hover:text-primary edit-category" 
                                                            data-id="<?php echo $category['category_id']; ?>"
                                                            data-name="<?php echo htmlspecialchars($category['name']); ?>"
                                                            data-description="<?php echo htmlspecialchars($category['description']); ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="text-gray-400 hover:text-red-500 delete-category" 
                                                            data-id="<?php echo $category['category_id']; ?>"
                                                            data-name="<?php echo htmlspecialchars($category['name']); ?>">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            
                                            <?php
                                            // מספר השירותים בקטגוריה
                                            $count = 0;
                                            foreach ($services as $service) {
                                                if ($service['category_id'] == $category['category_id']) {
                                                    $count++;
                                                }
                                            }
                                            ?>
                                            <div class="mt-3 text-sm text-gray-500">
                                                <i class="fas fa-list-ul mr-1"></i> <?php echo $count; ?> שירותים
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- שירותים -->
                    <div id="services-content" class="tab-pane hidden">
                        <div class="mb-6">
                            <h3 class="text-lg font-semibold mb-4">הוספת שירות חדש</h3>
                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="service_name" class="block text-gray-700 font-medium mb-1">שם השירות *</label>
                                    <input type="text" id="service_name" name="service_name" required
                                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                           placeholder="לדוגמה: תספורת נשים">
                                </div>
                                
                                <div>
                                    <label for="service_category" class="block text-gray-700 font-medium mb-1">קטגוריה *</label>
                                    <select id="service_category" name="service_category" required
                                            class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                                        <?php if (empty($categories)): ?>
                                            <option value="">אין קטגוריות זמינות</option>
                                        <?php else: ?>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo $category['category_id']; ?>">
                                                    <?php echo htmlspecialchars($category['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                    <?php if (empty($categories)): ?>
                                        <p class="text-sm text-red-500 mt-1">הוסף קטגוריה לפני הוספת שירותים</p>
                                    <?php endif; ?>
                                </div>
                                
                                <div>
                                    <label for="service_duration" class="block text-gray-700 font-medium mb-1">משך זמן (בדקות) *</label>
                                    <input type="number" id="service_duration" name="service_duration" min="5" required
                                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                           placeholder="30">
                                </div>
                                
                                <div>
                                    <label for="service_price" class="block text-gray-700 font-medium mb-1">מחיר (₪)</label>
                                    <input type="text" id="service_price" name="service_price"
                                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                           placeholder="100">
                                </div>
                                
                                <div class="md:col-span-2">
                                    <label for="service_description" class="block text-gray-700 font-medium mb-1">תיאור השירות</label>
                                    <textarea id="service_description" name="service_description" rows="2"
                                              class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                              placeholder="תיאור קצר של השירות"></textarea>
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 font-medium mb-1">זמני מעבר (דקות)</label>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label for="buffer_before" class="block text-sm text-gray-600 mb-1">לפני</label>
                                            <input type="number" id="buffer_before" name="buffer_before" min="0" value="0"
                                                   class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                                        </div>
                                        <div>
                                            <label for="buffer_after" class="block text-sm text-gray-600 mb-1">אחרי</label>
                                            <input type="number" id="buffer_after" name="buffer_after" min="0" value="0"
                                                   class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <label for="service_color" class="block text-gray-700 font-medium mb-1">צבע ביומן</label>
                                    <div class="flex items-center">
                                        <input type="color" id="service_color" name="service_color" value="#3498db"
                                               class="flex-none mr-2">
                                        <input type="text" id="color_display" readonly value="#3498db"
                                               class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                                    </div>
                                </div>
                                
                                <div class="md:col-span-2 flex justify-end mt-2">
                                    <button type="submit" name="add_service" <?php echo empty($categories) ? 'disabled' : ''; ?>
                                            class="py-2 px-6 bg-primary hover:bg-primary-dark text-white font-bold rounded-xl transition duration-300 <?php echo empty($categories) ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                                        <i class="fas fa-plus mr-1"></i> הוסף שירות
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <hr class="my-6">
                        
                        <div>
                            <h3 class="text-lg font-semibold mb-4">השירותים שלך</h3>
                            
                            <?php if (empty($services)): ?>
                                <div class="text-center py-12 border-2 border-dashed border-gray-300 rounded-xl">
                                    <div class="text-gray-400 mb-3"><i class="fas fa-concierge-bell text-5xl"></i></div>
                                    <p class="text-gray-500">עוד אין לך שירותים</p>
                                    <p class="text-gray-500 text-sm">הוסף שירותים שהעסק שלך מציע</p>
                                </div>
                            <?php else: ?>
                                <?php
                                // ארגון שירותים לפי קטגוריות
                                $services_by_category = [];
                                foreach ($services as $service) {
                                    $category_id = $service['category_id'];
                                    if (!isset($services_by_category[$category_id])) {
                                        $services_by_category[$category_id] = [];
                                    }
                                    $services_by_category[$category_id][] = $service;
                                }
                                
                                // מיפוי שמות קטגוריות
                                $category_names = [];
                                foreach ($categories as $category) {
                                    $category_names[$category['category_id']] = $category['name'];
                                }
                                ?>
                                
                                <?php foreach ($services_by_category as $category_id => $category_services): ?>
                                    <div class="mb-6">
                                        <h4 class="font-medium text-lg mb-3"><?php echo htmlspecialchars($category_names[$category_id] ?? 'ללא קטגוריה'); ?></h4>
                                        <div class="space-y-3">
                                            <?php foreach ($category_services as $service): ?>
                                                <div class="p-4 border border-gray-200 rounded-xl hover:border-primary transition duration-300">
                                                    <div class="flex justify-between items-start">
                                                        <div class="flex items-start">
                                                            <div class="w-4 h-4 rounded-full mt-1 flex-shrink-0" style="background-color: <?php echo htmlspecialchars($service['color']); ?>"></div>
                                                            <div class="mr-3">
                                                                <h5 class="font-semibold"><?php echo htmlspecialchars($service['name']); ?></h5>
                                                                <?php if (!empty($service['description'])): ?>
                                                                    <p class="text-gray-600 text-sm mt-1"><?php echo htmlspecialchars($service['description']); ?></p>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div class="flex space-x-2 space-x-reverse">
                                                            <button class="text-gray-400 hover:text-primary edit-service" 
                                                                    data-id="<?php echo $service['service_id']; ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="inline">
                                                                <input type="hidden" name="service_id" value="<?php echo $service['service_id']; ?>">
                                                                <button type="submit" name="delete_service" class="text-gray-400 hover:text-red-500"
                                                                        onclick="return confirm('האם אתה בטוח שברצונך למחוק את השירות הזה?');">
                                                                    <i class="fas fa-trash-alt"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                    <div class="mt-3 grid grid-cols-3 gap-2 text-sm">
                                                        <div>
                                                            <span class="text-gray-500">משך זמן:</span>
                                                            <span class="font-medium"><?php echo $service['duration']; ?> דק'</span>
                                                        </div>
                                                        <div>
                                                            <span class="text-gray-500">מחיר:</span>
                                                            <span class="font-medium"><?php echo number_format($service['price'], 0); ?> ₪</span>
                                                        </div>
                                                        <div>
                                                            <span class="text-gray-500">זמני מעבר:</span>
                                                            <span class="font-medium">
                                                                <?php 
                                                                if ($service['buffer_time_before'] > 0 || $service['buffer_time_after'] > 0) {
                                                                    echo $service['buffer_time_before'] . '/' . $service['buffer_time_after'] . ' דק\'';
                                                                } else {
                                                                    echo '-';
                                                                }
                                                                ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- כפתורי פעולה -->
                <div class="flex justify-between pt-6 mt-4 border-t">
                    <a href="dashboard.php" class="px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-xl transition duration-300">
                        דלג בינתיים
                    </a>
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                        <button type="submit" name="finish_setup" class="px-8 py-3 bg-primary hover:bg-primary-dark text-white font-bold rounded-xl transition duration-300 flex items-center">
                            <?php echo empty($services) ? 'דלג להגדרת שעות עבודה' : 'המשך להגדרת שעות עבודה'; ?>
                            <i class="fas fa-arrow-left mr-2"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Service Modal -->
    <div id="editServiceModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-2xl p-6 w-full max-w-lg mx-4">
            <h3 class="text-xl font-bold mb-4">עריכת שירות</h3>
            
            <form id="editServiceForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-4">
                <input type="hidden" id="edit_service_id" name="edit_service_id">
                
                <div>
                    <label for="edit_service_name" class="block text-gray-700 font-medium mb-1">שם השירות *</label>
                    <input type="text" id="edit_service_name" name="edit_service_name" required
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                </div>
                
                <div>
                    <label for="edit_service_category" class="block text-gray-700 font-medium mb-1">קטגוריה *</label>
                    <select id="edit_service_category" name="edit_service_category" required
                            class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['category_id']; ?>">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="edit_service_duration" class="block text-gray-700 font-medium mb-1">משך זמן (בדקות) *</label>
                        <input type="number" id="edit_service_duration" name="edit_service_duration" min="5" required
                               class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                    </div>
                    
                    <div>
                        <label for="edit_service_price" class="block text-gray-700 font-medium mb-1">מחיר (₪)</label>
                        <input type="text" id="edit_service_price" name="edit_service_price"
                               class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                    </div>
                </div>
                
                <div>
                    <label for="edit_service_description" class="block text-gray-700 font-medium mb-1">תיאור השירות</label>
                    <textarea id="edit_service_description" name="edit_service_description" rows="2"
                              class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"></textarea>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 font-medium mb-1">זמני מעבר (דקות)</label>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label for="edit_buffer_before" class="block text-sm text-gray-600 mb-1">לפני</label>
                                <input type="number" id="edit_buffer_before" name="edit_buffer_before" min="0"
                                       class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                            </div>
                            <div>
                                <label for="edit_buffer_after" class="block text-sm text-gray-600 mb-1">אחרי</label>
                                <input type="number" id="edit_buffer_after" name="edit_buffer_after" min="0"
                                       class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label for="edit_service_color" class="block text-gray-700 font-medium mb-1">צבע ביומן</label>
                        <div class="flex items-center">
                            <input type="color" id="edit_service_color" name="edit_service_color"
                                   class="flex-none mr-2">
                            <input type="text" id="edit_color_display" readonly
                                   class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 space-x-reverse pt-4">
                    <button type="button" id="cancelEditService" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg">
                        ביטול
                    </button>
                    <button type="submit" name="update_service" class="px-4 py-2 bg-primary hover:bg-primary-dark text-white font-bold rounded-lg">
                        שמור שינויים
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Category Modal -->
    <div id="editCategoryModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-2xl p-6 w-full max-w-lg mx-4">
            <h3 class="text-xl font-bold mb-4">עריכת קטגוריה</h3>
            
            <form id="editCategoryForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-4">
                <input type="hidden" id="edit_category_id" name="edit_category_id">
                
                <div>
                    <label for="edit_category_name" class="block text-gray-700 font-medium mb-1">שם הקטגוריה *</label>
                    <input type="text" id="edit_category_name" name="edit_category_name" required
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                </div>
                
                <div>
                    <label for="edit_category_description" class="block text-gray-700 font-medium mb-1">תיאור (אופציונלי)</label>
                    <textarea id="edit_category_description" name="edit_category_description" rows="2"
                              class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"></textarea>
                </div>
                
                <div class="flex justify-end space-x-3 space-x-reverse pt-4">
                    <button type="button" id="cancelEditCategory" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg">
                        ביטול
                    </button>
                    <button type="submit" name="update_category" class="px-4 py-2 bg-primary hover:bg-primary-dark text-white font-bold rounded-lg">
                        שמור שינויים
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Color picker synchronization
        document.getElementById('service_color').addEventListener('input', function() {
            document.getElementById('color_display').value = this.value;
        });
        
        document.getElementById('edit_service_color').addEventListener('input', function() {
            document.getElementById('edit_color_display').value = this.value;
        });
        
        // Tab switching
        const tabLinks = document.querySelectorAll('.tab-link');
        const tabPanes = document.querySelectorAll('.tab-pane');
        
        tabLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                const tabId = this.getAttribute('data-tab');
                
                // Remove active class from all tabs and panes
                tabLinks.forEach(tab => tab.classList.remove('active', 'text-primary', 'font-semibold'));
                tabLinks.forEach(tab => tab.classList.add('text-gray-500'));
                tabPanes.forEach(pane => pane.classList.add('hidden'));
                
                // Add active class to current tab and pane
                this.classList.add('active', 'text-primary', 'font-semibold');
                this.classList.remove('text-gray-500');
                document.getElementById(tabId + '-content').classList.remove('hidden');
            });
        });
        
        // Edit category modal
        const editCategoryButtons = document.querySelectorAll('.edit-category');
        const editCategoryModal = document.getElementById('editCategoryModal');
        const cancelEditCategory = document.getElementById('cancelEditCategory');
        
        editCategoryButtons.forEach(button => {
            button.addEventListener('click', function() {
                const categoryId = this.getAttribute('data-id');
                const categoryName = this.getAttribute('data-name');
                const categoryDescription = this.getAttribute('data-description');
                
                document.getElementById('edit_category_id').value = categoryId;
                document.getElementById('edit_category_name').value = categoryName;
                document.getElementById('edit_category_description').value = categoryDescription;
                
                editCategoryModal.classList.remove('hidden');
            });
        });
        
        cancelEditCategory.addEventListener('click', function() {
            editCategoryModal.classList.add('hidden');
        });
        
        // Edit service modal
        // Note: In a real implementation, you would make an AJAX call to get service details
        // For now, we'll handle this client-side by adding data attributes to the edit buttons
        
        const editServiceButtons = document.querySelectorAll('.edit-service');
        const editServiceModal = document.getElementById('editServiceModal');
        const cancelEditService = document.getElementById('cancelEditService');
        
        editServiceButtons.forEach(button => {
            button.addEventListener('click', function() {
                const serviceId = this.getAttribute('data-id');
                
                // In a real implementation, you would fetch service details via AJAX
                // and populate the form. For now, we'll show the modal with empty fields.
                document.getElementById('edit_service_id').value = serviceId;
                
                editServiceModal.classList.remove('hidden');
            });
        });
        
        cancelEditService.addEventListener('click', function() {
            editServiceModal.classList.add('hidden');
        });
        
        // Close modals when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target === editCategoryModal) {
                editCategoryModal.classList.add('hidden');
            }
            if (e.target === editServiceModal) {
                editServiceModal.classList.add('hidden');
            }
        });
    </script>
</body>
</html>