<?php
// טעינת קבצי התצורה
require_once "config/database.php";
require_once "includes/functions.php";

// קבלת הסלאג מה-URL
$slug = isset($_GET['slug']) ? sanitizeInput($_GET['slug']) : '';

if (empty($slug)) {
    // אם אין סלאג, הפניה לדף שגיאה או דף ראשי
    header("Location: index.php");
    exit;
}

// חיבור למסד הנתונים
$database = new Database();
$db = $database->getConnection();

// שליפת פרטי העסק לפי הסלאג
try {
    $query = "SELECT * FROM tenants WHERE slug = :slug";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":slug", $slug);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        // אם העסק לא נמצא, הפניה לדף שגיאה או דף ראשי
        header("Location: index.php?error=business_not_found");
        exit;
    }
    
    $tenant = $stmt->fetch();
    $tenant_id = $tenant['tenant_id'];
    
    // שליפת קטגוריות שירות
    $query = "SELECT * FROM service_categories WHERE tenant_id = :tenant_id ORDER BY display_order ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    $categories = $stmt->fetchAll();
    
    // שליפת שירותים
    $query = "SELECT s.*, c.name as category_name 
             FROM services s 
             LEFT JOIN service_categories c ON s.category_id = c.category_id 
             WHERE s.tenant_id = :tenant_id AND s.is_active = 1 
             ORDER BY s.category_id ASC, s.name ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    $services = $stmt->fetchAll();
    
    // שליפת אנשי צוות
    $query = "SELECT * FROM staff WHERE tenant_id = :tenant_id AND is_active = 1 ORDER BY name ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    $staff = $stmt->fetchAll();
    
    // שליפת שעות פעילות
    $query = "SELECT * FROM business_hours WHERE tenant_id = :tenant_id ORDER BY day_of_week ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    $business_hours = $stmt->fetchAll();
    
    // ארגון שעות פעילות לפי ימים
    $hours_by_day = [];
    foreach ($business_hours as $hours) {
        $hours_by_day[$hours['day_of_week']] = $hours;
    }
    
    // שליפת הפסקות
    $query = "SELECT * FROM breaks WHERE tenant_id = :tenant_id ORDER BY day_of_week ASC, start_time ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    $breaks = $stmt->fetchAll();
    
    // ארגון הפסקות לפי ימים
    $breaks_by_day = [];
    foreach ($breaks as $break) {
        if (!isset($breaks_by_day[$break['day_of_week']])) {
            $breaks_by_day[$break['day_of_week']] = [];
        }
        $breaks_by_day[$break['day_of_week']][] = $break;
    }
    
    // ארגון שירותים לפי קטגוריות
    $services_by_category = [];
    foreach ($services as $service) {
        $category_id = $service['category_id'];
        if (!isset($services_by_category[$category_id])) {
            $services_by_category[$category_id] = [];
        }
        $services_by_category[$category_id][] = $service;
    }
    
} catch (PDOException $e) {
    // טיפול בשגיאות מסד נתונים
    echo "שגיאה: " . $e->getMessage();
    exit;
}

// פונקציה להצגת שעות בפורמט קריא
function formatTime($time) {
    return date("H:i", strtotime($time));
}

// פונקציה להצגת שם יום בעברית
function getDayName($day) {
    $days = ["ראשון", "שני", "שלישי", "רביעי", "חמישי", "שישי", "שבת"];
    return $days[$day];
}

// קבלת היום הנוכחי (0 = ראשון, 6 = שבת)
$current_day = date('w');
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($tenant['business_name']); ?> - קביעת תור</title>
    <!-- טעינת גופן Noto Sans Hebrew מ-Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Hebrew:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- טעינת Tailwind CSS מ-CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- FullCalendar -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/locales/he.js"></script>
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
            scroll-behavior: smooth;
        }
        
        .hero-section {
            min-height: 450px;
            background-color: rgba(0, 0, 0, 0.3);
            background-blend-mode: overlay;
            background-size: cover;
            background-position: center;
        }
        
        .service-card:hover {
            border-color: #ec4899;
            transform: translateY(-2px);
        }
        
        /* Scrollbar styling */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #ec4899;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #db2777;
        }
        
        /* Styling for booking steps */
        .booking-step {
            display: none;
        }
        
        .booking-step.active {
            display: block;
        }
        
        .step-indicator {
            transition: all 0.3s ease;
        }
        
        .time-slot {
            transition: all 0.15s ease;
        }
        
        .time-slot:hover {
            background-color: #f9d4ea;
            color: #db2777;
        }
        
        .time-slot.selected {
            background-color: #ec4899;
            color: white;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Hero Section -->
    <div class="hero-section flex flex-col justify-center items-center text-white text-center px-4 relative" 
         style="    background-color: #000000d6;background-image: url('<?php echo !empty($tenant['cover_image_path']) ? htmlspecialchars($tenant['cover_image_path']) : 'assets/img/default-cover.jpg'; ?>');">
        
        <?php if (!empty($tenant['logo_path'])): ?>
            <div class="mb-6">
                <img src="<?php echo htmlspecialchars($tenant['logo_path']); ?>" alt="<?php echo htmlspecialchars($tenant['business_name']); ?>" class="w-24 h-24 rounded-full object-cover border-4 border-white">
            </div>
        <?php endif; ?>
        
        <h1 class="text-4xl md:text-5xl font-bold mb-2"><?php echo htmlspecialchars($tenant['business_name']); ?></h1>
        
        <?php if (!empty($tenant['business_description'])): ?>
            <p class="text-lg max-w-2xl mb-8"><?php echo htmlspecialchars($tenant['business_description']); ?></p>
        <?php endif; ?>
        
        <button id="bookNowBtn" class="bg-primary hover:bg-primary-dark text-white font-bold py-3 px-8 rounded-xl transition duration-300">
            קבע תור עכשיו
        </button>
    </div>
    
    <!-- Main Content -->
    <div class="max-w-6xl mx-auto px-4 py-12">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Sidebar Information -->
            <div class="col-span-1">
                <!-- Business Info Card -->
                <div class="bg-white rounded-2xl shadow-sm p-6 mb-6">
                    <h2 class="text-xl font-bold mb-4">פרטי העסק</h2>
                    
                    <?php if (!empty($tenant['address'])): ?>
                        <div class="flex items-start mb-4">
                            <div class="text-primary mt-1"><i class="fas fa-map-marker-alt"></i></div>
                            <div class="mr-3">
                                <h3 class="font-medium">כתובת</h3>
                                <p class="text-gray-600"><?php echo htmlspecialchars($tenant['address']); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($tenant['phone'])): ?>
                        <div class="flex items-start mb-4">
                            <div class="text-primary mt-1"><i class="fas fa-phone-alt"></i></div>
                            <div class="mr-3">
                                <h3 class="font-medium">טלפון</h3>
                                <p class="text-gray-600 dir-ltr">
                                    <a href="tel:<?php echo htmlspecialchars($tenant['phone']); ?>" class="hover:text-primary">
                                        <?php echo htmlspecialchars($tenant['phone']); ?>
                                    </a>
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($tenant['email'])): ?>
                        <div class="flex items-start mb-4">
                            <div class="text-primary mt-1"><i class="fas fa-envelope"></i></div>
                            <div class="mr-3">
                                <h3 class="font-medium">אימייל</h3>
                                <p class="text-gray-600 dir-ltr">
                                    <a href="mailto:<?php echo htmlspecialchars($tenant['email']); ?>" class="hover:text-primary">
                                        <?php echo htmlspecialchars($tenant['email']); ?>
                                    </a>
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Social Media Links -->
                    <?php if (!empty($tenant['facebook_url']) || !empty($tenant['instagram_url']) || !empty($tenant['website_url'])): ?>
                        <div class="flex mt-4 space-x-4 space-x-reverse">
                            <?php if (!empty($tenant['facebook_url'])): ?>
                                <a href="<?php echo htmlspecialchars($tenant['facebook_url']); ?>" target="_blank" class="text-gray-500 hover:text-primary">
                                    <i class="fab fa-facebook-f text-xl"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($tenant['instagram_url'])): ?>
                                <a href="<?php echo htmlspecialchars($tenant['instagram_url']); ?>" target="_blank" class="text-gray-500 hover:text-primary">
                                    <i class="fab fa-instagram text-xl"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($tenant['website_url'])): ?>
                                <a href="<?php echo htmlspecialchars($tenant['website_url']); ?>" target="_blank" class="text-gray-500 hover:text-primary">
                                    <i class="fas fa-globe text-xl"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Business Hours Card -->
                <div class="bg-white rounded-2xl shadow-sm p-6 mb-6">
                    <h2 class="text-xl font-bold mb-4">שעות פעילות</h2>
                    
                    <div class="space-y-3">
                        <?php for ($day = 0; $day <= 6; $day++): ?>
                            <div class="flex justify-between items-center <?php echo ($day == $current_day) ? 'font-bold text-primary' : ''; ?>">
                                <span>יום <?php echo getDayName($day); ?></span>
                                <?php if (isset($hours_by_day[$day]) && $hours_by_day[$day]['is_open']): ?>
                                    <span>
                                        <?php echo formatTime($hours_by_day[$day]['open_time']); ?> - 
                                        <?php echo formatTime($hours_by_day[$day]['close_time']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-500">סגור</span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (isset($breaks_by_day[$day]) && !empty($breaks_by_day[$day])): ?>
                                <?php foreach ($breaks_by_day[$day] as $break): ?>
                                    <div class="text-sm text-gray-500 mr-4 -mt-2">
                                        <i class="fas fa-pause-circle mr-1"></i>
                                        הפסקה: <?php echo formatTime($break['start_time']); ?> - <?php echo formatTime($break['end_time']); ?>
                                        <?php if (!empty($break['description'])): ?>
                                            (<?php echo htmlspecialchars($break['description']); ?>)
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <!-- Team Members (if any) -->
                <?php if (count($staff) > 1): ?>
                    <div class="bg-white rounded-2xl shadow-sm p-6">
                        <h2 class="text-xl font-bold mb-4">הצוות שלנו</h2>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <?php foreach ($staff as $member): ?>
                                <div class="text-center">
                                    <div class="mb-2">
                                        <?php if (!empty($member['image_path'])): ?>
                                            <img src="<?php echo htmlspecialchars($member['image_path']); ?>" alt="<?php echo htmlspecialchars($member['name']); ?>" 
                                                 class="w-20 h-20 object-cover rounded-full mx-auto">
                                        <?php else: ?>
                                            <div class="w-20 h-20 rounded-full bg-primary text-white flex items-center justify-center mx-auto text-xl font-bold">
                                                <?php echo mb_substr($member['name'], 0, 1, 'UTF-8'); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="font-medium"><?php echo htmlspecialchars($member['name']); ?></h3>
                                    <?php if (!empty($member['position'])): ?>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($member['position']); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Services and Booking Section -->
            <div class="md:col-span-2">
                <!-- Services Section -->
                <section id="services" class="mb-12">
                    <h2 class="text-2xl font-bold mb-6">השירותים שלנו</h2>
                    
                    <?php if (empty($services)): ?>
                        <div class="bg-gray-100 p-8 rounded-2xl text-center">
                            <p class="text-gray-500">אין שירותים זמינים כרגע</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($categories as $category): ?>
                            <?php if (isset($services_by_category[$category['category_id']])): ?>
                                <div class="mb-8">
                                    <h3 class="text-xl font-semibold mb-4"><?php echo htmlspecialchars($category['name']); ?></h3>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <?php foreach ($services_by_category[$category['category_id']] as $service): ?>
                                            <div class="service-card bg-white rounded-xl p-4 border-2 border-transparent hover:shadow-md transition duration-300 cursor-pointer"
                                                 onclick="selectService(<?php echo $service['service_id']; ?>, '<?php echo htmlspecialchars(addslashes($service['name'])); ?>')">
                                                <div class="flex justify-between items-start">
                                                    <div>
                                                        <h4 class="font-semibold text-lg"><?php echo htmlspecialchars($service['name']); ?></h4>
                                                        <?php if (!empty($service['description'])): ?>
                                                            <p class="text-gray-600 text-sm mt-1"><?php echo htmlspecialchars($service['description']); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <?php if ($service['price'] > 0): ?>
                                                            <span class="bg-gray-100 rounded-lg px-2 py-1 text-gray-800 font-medium">
                                                                <?php echo number_format($service['price'], 0); ?> ₪
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="mt-2 text-sm text-gray-500">
                                                    <i class="far fa-clock mr-1"></i> <?php echo $service['duration']; ?> דקות
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                        <!-- שירותים ללא קטגוריה -->
                        <?php if (isset($services_by_category[null]) || isset($services_by_category[''])): ?>
                            <div class="mb-8">
                                <h3 class="text-xl font-semibold mb-4">שירותים נוספים</h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <?php 
                                    $uncategorized = isset($services_by_category[null]) ? $services_by_category[null] : 
                                                    (isset($services_by_category['']) ? $services_by_category[''] : []);
                                    ?>
                                    
                                    <?php foreach ($uncategorized as $service): ?>
                                        <div class="service-card bg-white rounded-xl p-4 border-2 border-transparent hover:shadow-md transition duration-300 cursor-pointer"
                                             onclick="selectService(<?php echo $service['service_id']; ?>, '<?php echo htmlspecialchars(addslashes($service['name'])); ?>')">
                                            <div class="flex justify-between items-start">
                                                <div>
                                                    <h4 class="font-semibold text-lg"><?php echo htmlspecialchars($service['name']); ?></h4>
                                                    <?php if (!empty($service['description'])): ?>
                                                        <p class="text-gray-600 text-sm mt-1"><?php echo htmlspecialchars($service['description']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <?php if ($service['price'] > 0): ?>
                                                        <span class="bg-gray-100 rounded-lg px-2 py-1 text-gray-800 font-medium">
                                                            <?php echo number_format($service['price'], 0); ?> ₪
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="mt-2 text-sm text-gray-500">
                                                <i class="far fa-clock mr-1"></i> <?php echo $service['duration']; ?> דקות
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
                
                <!-- Booking Form Section -->
                <section id="booking" class="bg-white rounded-2xl shadow-sm p-6">
                    <h2 class="text-2xl font-bold mb-6">קביעת תור</h2>
                    
                    <!-- Booking Steps Indicator -->
                    <div class="mb-8">
                        <div class="flex justify-between mb-2">
                            <div class="text-center">
                                <div class="step-indicator w-8 h-8 bg-primary text-white rounded-full flex items-center justify-center mx-auto mb-1" data-step="1">1</div>
                                <span class="text-xs">בחירת שירות</span>
                            </div>
                            <div class="text-center">
                                <div class="step-indicator w-8 h-8 bg-gray-300 text-gray-600 rounded-full flex items-center justify-center mx-auto mb-1" data-step="2">2</div>
                                <span class="text-xs">בחירת מועד</span>
                            </div>
                            <div class="text-center">
                                <div class="step-indicator w-8 h-8 bg-gray-300 text-gray-600 rounded-full flex items-center justify-center mx-auto mb-1" data-step="3">3</div>
                                <span class="text-xs">פרטים אישיים</span>
                            </div>
                            <div class="text-center">
                                <div class="step-indicator w-8 h-8 bg-gray-300 text-gray-600 rounded-full flex items-center justify-center mx-auto mb-1" data-step="4">4</div>
                                <span class="text-xs">אישור</span>
                            </div>
                        </div>
                        <div class="relative pt-1">
                            <div class="overflow-hidden h-2 text-xs flex rounded bg-gray-200">
                                <div id="progress-bar" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-primary" style="width: 25%"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 1: Service Selection -->
                    <div id="step1" class="booking-step active">
                        <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center">
                            <h3 class="text-lg font-semibold mb-2">בחר שירות</h3>
                            <p class="text-gray-600 mb-4">לחץ על אחד השירותים למעלה כדי להתחיל</p>
                            
                            <div id="selectedServiceContainer" class="hidden bg-pastel-purple bg-opacity-30 p-4 rounded-xl text-center">
                                <p class="mb-2">השירות שנבחר:</p>
                                <h4 id="selectedServiceName" class="text-xl font-bold text-primary"></h4>
                                <input type="hidden" id="selectedServiceId" value="">
                            </div>
                            
                            <button id="nextToStep2" class="mt-4 bg-primary hover:bg-primary-dark text-white font-bold py-2 px-6 rounded-xl transition duration-300 hidden">
                                המשך לבחירת מועד
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 2: Date & Time Selection -->
                    <div id="step2" class="booking-step">
                        <div id="calendarContainer" class="mb-6">
                            <!-- כאן יוצג לוח השנה -->
                        </div>
                        
                        <div id="timeSlots" class="hidden border-t pt-4">
                            <h3 class="text-lg font-semibold mb-4">בחר שעה ליום <span id="selectedDate"></span></h3>
                            
                            <div id="timeSlotsContainer" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-2"></div>
                            
                            <div id="noTimeSlotsMessage" class="text-center py-8 hidden">
                                <p class="text-gray-600">אין זמנים פנויים ביום זה. אנא בחר יום אחר.</p>
                            </div>
                        </div>
                        
                        <div class="flex justify-between mt-6">
                            <button id="backToStep1" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-6 rounded-xl transition duration-300">
                                חזרה
                            </button>
                            <button id="nextToStep3" class="bg-gray-300 text-gray-500 cursor-not-allowed font-bold py-2 px-6 rounded-xl transition duration-300">
                                המשך
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 3: Customer Details -->
                    <div id="step3" class="booking-step">
                        <div class="mb-6">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                                <div>
                                    <label for="first_name" class="block text-gray-700 font-medium mb-1">שם פרטי *</label>
                                    <input type="text" id="first_name" name="first_name" 
                                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                           required>
                                </div>
                                <div>
                                    <label for="last_name" class="block text-gray-700 font-medium mb-1">שם משפחה *</label>
                                    <input type="text" id="last_name" name="last_name" 
                                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                           required>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="phone" class="block text-gray-700 font-medium mb-1">טלפון נייד *</label>
                                    <input type="tel" id="phone" name="phone" dir="ltr"
                                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                           required>
                                </div>
                                <div>
                                    <label for="email" class="block text-gray-700 font-medium mb-1">אימייל</label>
                                    <input type="email" id="email" name="email" dir="ltr"
                                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <label for="notes" class="block text-gray-700 font-medium mb-1">הערות (אופציונלי)</label>
                                <textarea id="notes" name="notes" rows="3"
                                         class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                         placeholder="הערות מיוחדות או בקשות"></textarea>
                            </div>
                        </div>
                        
                        <div class="flex justify-between mt-6">
                            <button id="backToStep2" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-6 rounded-xl transition duration-300">
                                חזרה
                            </button>
                            <button id="nextToStep4" class="bg-primary hover:bg-primary-dark text-white font-bold py-2 px-6 rounded-xl transition duration-300">
                                המשך
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 4: Booking Confirmation -->
                    <div id="step4" class="booking-step">
                        <div class="border-2 border-gray-200 rounded-xl p-6 mb-6">
                            <h3 class="text-xl font-semibold mb-4 text-center">סיכום הזמנה</h3>
                            
                            <div class="space-y-4">
                                <div class="flex justify-between pb-2 border-b border-gray-200">
                                    <span class="font-medium">שירות:</span>
                                    <span id="summary_service"></span>
                                </div>
                                
                                <div class="flex justify-between pb-2 border-b border-gray-200">
                                    <span class="font-medium">תאריך:</span>
                                    <span id="summary_date"></span>
                                </div>
                                
                                <div class="flex justify-between pb-2 border-b border-gray-200">
                                    <span class="font-medium">שעה:</span>
                                    <span id="summary_time"></span>
                                </div>
                                
                                <div class="flex justify-between pb-2 border-b border-gray-200">
                                    <span class="font-medium">שם מלא:</span>
                                    <span id="summary_name"></span>
                                </div>
                                
                                <div class="flex justify-between pb-2 border-b border-gray-200">
                                    <span class="font-medium">טלפון:</span>
                                    <span id="summary_phone"></span>
                                </div>
                                
                                <div id="summary_notes_container" class="pb-2 border-b border-gray-200 hidden">
                                    <div class="font-medium mb-1">הערות:</div>
                                    <div id="summary_notes" class="text-gray-700"></div>
                                </div>
                            </div>
                            
                            <div class="mt-6 text-center">
                                <p class="text-sm text-gray-500 mb-2">בלחיצה על "אישור הזמנה" אתה מאשר שקראת והסכמת לתנאי השימוש שלנו</p>
                                
                                <div class="flex justify-center mt-4">
                                    <div class="bg-pastel-green bg-opacity-40 rounded-lg p-3 max-w-md">
                                        <p class="text-green-800">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            תקבל אישור לתור שלך באמצעות SMS ואימייל (אם הוזן)
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-between mt-6">
                            <button id="backToStep3" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-6 rounded-xl transition duration-300">
                                חזרה
                            </button>
                            <button id="confirmBooking" class="bg-primary hover:bg-primary-dark text-white font-bold py-2 px-6 rounded-xl transition duration-300">
                                אישור הזמנה
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 5: Success Message (shown after booking) -->
                    <div id="step5" class="booking-step">
                        <div class="text-center py-8">
                            <div class="w-20 h-20 bg-pastel-green text-green-600 rounded-full flex items-center justify-center mx-auto mb-6">
                                <i class="fas fa-check-circle text-4xl"></i>
                            </div>
                            
                            <h3 class="text-2xl font-bold mb-2">התור נקבע בהצלחה!</h3>
                            <p class="text-gray-600 mb-4 max-w-md mx-auto">התור הוזמן בהצלחה. שלחנו לך אישור בהודעת SMS ואימייל (אם הוזן) עם פרטי התור.</p>
                            
                            <div class="bg-gray-100 rounded-xl p-4 max-w-md mx-auto mb-6">
                                <div class="font-medium mb-2">פרטי התור:</div>
                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    <div class="text-right font-medium">מספר אסמכתא:</div>
                                    <div class="text-left" id="booking_reference"></div>
                                    
                                    <div class="text-right font-medium">שירות:</div>
                                    <div class="text-left" id="booking_service"></div>
                                    
                                    <div class="text-right font-medium">תאריך:</div>
                                    <div class="text-left" id="booking_date"></div>
                                    
                                    <div class="text-right font-medium">שעה:</div>
                                    <div class="text-left" id="booking_time"></div>
                                </div>
                            </div>
                            
                            <div class="flex flex-col md:flex-row justify-center gap-4">
                                <a href="#" class="bg-primary hover:bg-primary-dark text-white font-bold py-2 px-6 rounded-xl transition duration-300">
                                    הוסף תור ליומן
                                </a>
                                <a href="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-6 rounded-xl transition duration-300">
                                    חזרה לדף הבית
                                </a>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8 mt-12">
        <div class="max-w-6xl mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <h3 class="text-xl font-bold mb-2"><?php echo htmlspecialchars($tenant['business_name']); ?></h3>
                    <p class="text-gray-400">&copy; <?php echo date('Y'); ?> כל הזכויות שמורות</p>
                </div>
                
                <div class="flex space-x-4 space-x-reverse">
                    <?php if (!empty($tenant['facebook_url'])): ?>
                        <a href="<?php echo htmlspecialchars($tenant['facebook_url']); ?>" target="_blank" class="text-gray-400 hover:text-white">
                            <i class="fab fa-facebook-f text-xl"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($tenant['instagram_url'])): ?>
                        <a href="<?php echo htmlspecialchars($tenant['instagram_url']); ?>" target="_blank" class="text-gray-400 hover:text-white">
                            <i class="fab fa-instagram text-xl"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($tenant['website_url'])): ?>
                        <a href="<?php echo htmlspecialchars($tenant['website_url']); ?>" target="_blank" class="text-gray-400 hover:text-white">
                            <i class="fas fa-globe text-xl"></i>
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="mt-4 md:mt-0 text-sm text-gray-400">
                    <p>מופעל ע"י <a href="index.php" class="text-primary hover:text-primary-light">קליקינדר</a></p>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- JS Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Global variables
        let selectedServiceId = null;
        let selectedServiceName = '';
        let selectedDate = '';
        let selectedTimeSlot = '';
        let availableTimeSlots = [];
        let calendar = null;
        
        $(document).ready(function() {
            // Scroll to booking on click
            $('#bookNowBtn').click(function() {
                $('html, body').animate({
                    scrollTop: $('#booking').offset().top - 50
                }, 1000);
            });
            
            // Initialize calendar
            initializeCalendar();
            
            // Navigation between steps
            setupStepNavigation();
        });
        
        // Select a service
        function selectService(serviceId, serviceName) {
            selectedServiceId = serviceId;
            selectedServiceName = serviceName;
            
            // Show selected service in UI
            $('#selectedServiceName').text(serviceName);
            $('#selectedServiceId').val(serviceId);
            $('#selectedServiceContainer').removeClass('hidden');
            $('#nextToStep2').removeClass('hidden');
            
            // Scroll to the booking form
            $('html, body').animate({
                scrollTop: $('#booking').offset().top - 50
            }, 1000);
        }
        
        // Initialize FullCalendar
        function initializeCalendar() {
            const calendarEl = document.getElementById('calendarContainer');
            
            calendar = new FullCalendar.Calendar(calendarEl, {
                locale: 'he',
                initialView: 'dayGridMonth',
                height: 'auto',
                selectable: true,
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: ''
                },
                buttonText: {
                    today: 'היום'
                },
                direction: 'rtl',
                firstDay: 0, // יום ראשון כיום ראשון בשבוע
                select: function(info) {
                    handleDateSelection(info.startStr);
                },
                dateClick: function(info) {
                    handleDateSelection(info.dateStr);
                },
                eventSources: [
                    // Here we would fetch business closed days, but for now we'll keep it empty
                ]
            });
        }
        
        // Handle date selection in calendar
        function handleDateSelection(dateStr) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            const selectedDay = new Date(dateStr);
            selectedDay.setHours(0, 0, 0, 0);
            
            // Don't allow selecting dates in the past
            if (selectedDay < today) {
                alert('לא ניתן לבחור תאריך בעבר');
                return;
            }
            
            selectedDate = dateStr;
            $('#selectedDate').text(formatDateHebrew(selectedDate));
            
            // Clear previous selection
            $('.fc-day').removeClass('selected-date');
            $('.fc-day[data-date="' + dateStr + '"]').addClass('selected-date');
            
            // Fetch available time slots for this date and service
            fetchAvailableTimeSlots(selectedServiceId, dateStr);
        }
        
        // Format date in Hebrew
        function formatDateHebrew(dateStr) {
            const date = new Date(dateStr);
            const day = date.getDate();
            const month = date.getMonth() + 1;
            const year = date.getFullYear();
            
            return `${day}/${month}/${year}`;
        }
        
        // Fetch available time slots (in a real implementation, this would make an AJAX call to the server)
        // עדכון הפונקציה fetchAvailableTimeSlots בדף booking.php
function fetchAvailableTimeSlots(serviceId, dateStr) {
    $('#timeSlots').removeClass('hidden');
    const container = $('#timeSlotsContainer');
    container.empty();
    
    // הצגת אנימציית טעינה
    container.html('<div class="col-span-full text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i> טוען זמנים פנויים...</div>');
    
    // קבלת מזהה ה-tenant מה-URL או משתנה גלובלי
    const tenantId = <?php echo $tenant_id; ?>;
    
    // קריאה ל-API לקבלת סלוטים פנויים
    $.get('api/available_slots.php', {
        date: dateStr,
        service_id: serviceId,
        staff_id: 0, // אם אין בחירת נותן שירות ספציפי
        tenant_id: tenantId
    }, function(response) {
        container.empty();
        
        if (response.success && response.slots.length > 0) {
            // הצגת הסלוטים הפנויים
            availableTimeSlots = response.slots;
            
            response.slots.forEach(slot => {
                container.append(`
                    <div class="time-slot bg-white border-2 border-gray-300 rounded-lg py-2 text-center cursor-pointer hover:border-primary"
                         onclick="selectTimeSlot('${slot}')">${slot}</div>
                `);
            });
            
            $('#noTimeSlotsMessage').addClass('hidden');
        } else {
            // אין סלוטים פנויים
            $('#noTimeSlotsMessage').removeClass('hidden');
            if (response.message) {
                $('#noTimeSlotsMessage p').text(response.message);
            }
        }
    }).fail(function() {
        container.html('<div class="col-span-full text-center py-4 text-red-500"><i class="fas fa-exclamation-circle mr-2"></i> אירעה שגיאה בטעינת הזמנים הפנויים</div>');
    });
}
        
        // Generate time slots between open and close times
        function generateTimeSlots(openTime, closeTime, intervalMinutes) {
            const slots = [];
            const interval = intervalMinutes * 60 * 1000; // convert to milliseconds
            
            const start = new Date(`2000-01-01T${openTime}`);
            const end = new Date(`2000-01-01T${closeTime}`);
            
            let current = new Date(start);
            
            while (current < end) {
                // Format the time as HH:MM
                const hours = current.getHours().toString().padStart(2, '0');
                const minutes = current.getMinutes().toString().padStart(2, '0');
                const timeStr = `${hours}:${minutes}`;
                
                slots.push(timeStr);
                
                // Move to next slot
                current = new Date(current.getTime() + interval);
            }
            
            return slots;
        }
        
        // Remove time slots that fall within break times
        function removeBreakTimeSlots(slots, breaks) {
            return slots.filter(slot => {
                const slotTime = new Date(`2000-01-01T${slot}:00`);
                
                // Check if slot is within any break time
                for (const breakItem of breaks) {
                    const breakStart = new Date(`2000-01-01T${breakItem.start_time}`);
                    const breakEnd = new Date(`2000-01-01T${breakItem.end_time}`);
                    
                    if (slotTime >= breakStart && slotTime < breakEnd) {
                        return false; // Remove this slot
                    }
                }
                
                return true; // Keep this slot
            });
        }
        
        // Remove time slots that are in the past if the selected date is today
        function removePastTimeSlots(slots) {
            const now = new Date();
            const currentHour = now.getHours();
            const currentMinute = now.getMinutes();
            
            return slots.filter(slot => {
                const [hour, minute] = slot.split(':').map(Number);
                
                // Compare with current time
                if (hour > currentHour || (hour === currentHour && minute > currentMinute)) {
                    return true; // Keep slots in the future
                }
                
                return false; // Remove slots in the past
            });
        }
        
        // Check if date is today
        function isToday(dateStr) {
            const today = new Date();
            const check = new Date(dateStr);
            
            return today.getDate() === check.getDate() &&
                   today.getMonth() === check.getMonth() &&
                   today.getFullYear() === check.getFullYear();
        }
        
        // Handle time slot selection
        function selectTimeSlot(timeSlot) {
            selectedTimeSlot = timeSlot;
            
            // Update UI
            $('.time-slot').removeClass('selected bg-primary text-white');
            $(`.time-slot:contains("${timeSlot}")`).addClass('selected bg-primary text-white');
            
            // Enable next button
            $('#nextToStep3').removeClass('bg-gray-300 text-gray-500 cursor-not-allowed')
                             .addClass('bg-primary hover:bg-primary-dark text-white');
        }
        
        // Set up navigation between steps
        function setupStepNavigation() {
            // Step 1 to Step 2
            $('#nextToStep2').click(function() {
                if (!selectedServiceId) {
                    alert('אנא בחר שירות תחילה');
                    return;
                }
                
                // Initialize calendar if not done yet
                if (!calendar) {
                    initializeCalendar();
                }
                
                // Render calendar
                calendar.render();
                
                // Show Step 2
                $('.booking-step').removeClass('active');
                $('#step2').addClass('active');
                
                // Update progress indicators
                updateProgressIndicators(2);
            });
            
            // Back to Step 1
            $('#backToStep1').click(function() {
                $('.booking-step').removeClass('active');
                $('#step1').addClass('active');
                updateProgressIndicators(1);
            });
            
            // Step 2 to Step 3
            $('#nextToStep3').click(function() {
                if (!selectedTimeSlot) {
                    alert('אנא בחר מועד תחילה');
                    return;
                }
                
                $('.booking-step').removeClass('active');
                $('#step3').addClass('active');
                updateProgressIndicators(3);
            });
            
            // Back to Step 2
            $('#backToStep2').click(function() {
                $('.booking-step').removeClass('active');
                $('#step2').addClass('active');
                updateProgressIndicators(2);
            });
            
            // Step 3 to Step 4 (Summary)
            $('#nextToStep4').click(function() {
                // Validate form
                const firstName = $('#first_name').val();
                const lastName = $('#last_name').val();
                const phone = $('#phone').val();
                
                if (!firstName || !lastName || !phone) {
                    alert('אנא מלא את כל שדות החובה');
                    return;
                }
                
                // Update summary
                $('#summary_service').text(selectedServiceName);
                $('#summary_date').text(formatDateHebrew(selectedDate));
                $('#summary_time').text(selectedTimeSlot);
                $('#summary_name').text(firstName + ' ' + lastName);
                $('#summary_phone').text(phone);
                
                const notes = $('#notes').val();
                if (notes) {
                    $('#summary_notes').text(notes);
                    $('#summary_notes_container').removeClass('hidden');
                } else {
                    $('#summary_notes_container').addClass('hidden');
                }
                
                $('.booking-step').removeClass('active');
                $('#step4').addClass('active');
                updateProgressIndicators(4);
            });
            
            // Back to Step 3
            $('#backToStep3').click(function() {
                $('.booking-step').removeClass('active');
                $('#step3').addClass('active');
                updateProgressIndicators(3);
            });
            
            // Confirm Booking (Submit)
            $('#confirmBooking').click(function() {
                // In a real implementation, this would submit the form data to the server
                // For the demo, we'll just show the success message
                
                // Generate a random booking reference
                const bookingRef = generateBookingReference();
                
                // Update booking details in success screen
                $('#booking_reference').text(bookingRef);
                $('#booking_service').text(selectedServiceName);
                $('#booking_date').text(formatDateHebrew(selectedDate));
                $('#booking_time').text(selectedTimeSlot);
                
                // Show success message
                $('.booking-step').removeClass('active');
                $('#step5').addClass('active');
                
                // Hide progress indicators
                $('.step-indicator').parent().addClass('hidden');
                $('#progress-bar').parent().addClass('hidden');
            });
        }
        
        // Update progress indicators
        function updateProgressIndicators(step) {
            // Update progress bar
            $('#progress-bar').css('width', (step * 25) + '%');
            
            // Update step indicators
            $('.step-indicator').removeClass('bg-primary text-white').addClass('bg-gray-300 text-gray-600');
            
            for (let i = 1; i <= step; i++) {
                $(`.step-indicator[data-step="${i}"]`).removeClass('bg-gray-300 text-gray-600').addClass('bg-primary text-white');
            }
        }
        
        // Generate a random booking reference
        function generateBookingReference() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let result = '';
            for (let i = 0; i < 8; i++) {
                result += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            return result;
        }
    </script>
</body>
</html>