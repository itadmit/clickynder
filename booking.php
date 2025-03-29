<?php
// טעינת קבצי התצורה
require_once "config/database.php";
require_once "includes/functions.php";

// קבלת הסלאג מה-URL
$slug = isset($_GET['slug']) ? sanitizeInput($_GET['slug']) : '';

if (empty($slug)) {
    // אם אין סלאג, הפנייה לדף שגיאה או דף ראשי
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
    
    // שליפת שאלות מקדימות
    $query = "SELECT * FROM intake_questions 
             WHERE tenant_id = :tenant_id 
             AND (service_id IS NULL OR service_id = 0)
             ORDER BY display_order ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    $general_questions = $stmt->fetchAll();
    
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
        
        .staff-card {
            transition: all 0.2s ease;
        }
        
        .staff-card:hover {
            transform: translateY(-2px);
        }
        
        .staff-card.selected {
            border-color: #ec4899;
            background-color: #fdf2f8;
        }
        
        /* Calendar styling */
        .fc-day-today {
            background-color: #fdf2f8 !important;
        }
        
        .fc-day:not(.fc-day-disabled) {
            cursor: pointer;
        }
        
        .fc-day:not(.fc-day-disabled):hover {
            background-color: #fce7f3 !important;
        }
        
        .fc-day.selected-date {
            background-color: #fbcfe8 !important;
        }
        
        /* Loading spinner */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Hero Section -->
    <div class="hero-section flex flex-col justify-center items-center text-white text-center px-4 relative" 
         style="background-color: #000000d6; background-image: url('<?php echo !empty($tenant['cover_image_path']) ? htmlspecialchars($tenant['cover_image_path']) : 'assets/img/default-cover.jpg'; ?>');">
        
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
                        <!-- אם יש מספר אנשי צוות, נציג בחירת איש צוות -->
                        <?php if (count($staff) > 1): ?>
                        <div class="mb-6">
                            <label class="block text-gray-700 font-medium mb-2">בחר נותן שירות:</label>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3" id="staffSelector">
                                <?php foreach ($staff as $member): ?>
                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3" id="staffSelector">
                                <?php foreach ($staff as $member): ?>
                                <div class="staff-card bg-white border-2 border-gray-300 rounded-xl p-3 text-center cursor-pointer hover:border-primary" 
                                     data-staff-id="<?php echo $member['staff_id']; ?>">
                                    <?php if (!empty($member['image_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($member['image_path']); ?>" class="w-16 h-16 object-cover rounded-full mx-auto mb-2">
                                    <?php else: ?>
                                        <div class="w-16 h-16 rounded-full bg-primary text-white flex items-center justify-center mx-auto mb-2 text-xl font-bold">
                                            <?php echo mb_substr($member['name'], 0, 1, 'UTF-8'); ?>
                                        </div>
                                    <?php endif; ?>
                                    <p class="font-medium"><?php echo htmlspecialchars($member['name']); ?></p>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
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
                            
                            <!-- שאלות מקדימות - אם הוגדרו -->
                            <?php if (!empty($general_questions)): ?>
                            <div class="mt-6 border-t pt-6">
                                <h4 class="font-semibold mb-4">שאלות מקדימות:</h4>
                                
                                <div class="space-y-4">
                                    <?php foreach ($general_questions as $question): ?>
                                    <div class="question-item">
                                        <label class="block text-gray-700 mb-1">
                                            <?php echo htmlspecialchars($question['question_text']); ?>
                                            <?php if ($question['is_required']): ?>
                                            <span class="text-red-500">*</span>
                                            <?php endif; ?>
                                        </label>
                                        
                                        <?php if ($question['question_type'] == 'text'): ?>
                                            <input type="text" class="intake-answer w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                                   data-question-id="<?php echo $question['question_id']; ?>"
                                                   <?php echo $question['is_required'] ? 'required' : ''; ?>>
                                                   
                                        <?php elseif ($question['question_type'] == 'textarea'): ?>
                                            <textarea class="intake-answer w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary" rows="3"
                                                      data-question-id="<?php echo $question['question_id']; ?>"
                                                      <?php echo $question['is_required'] ? 'required' : ''; ?>></textarea>
                                                      
                                        <?php elseif ($question['question_type'] == 'select'): ?>
                                            <?php $options = json_decode($question['options'], true); ?>
                                            <select class="intake-answer w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                                    data-question-id="<?php echo $question['question_id']; ?>"
                                                    <?php echo $question['is_required'] ? 'required' : ''; ?>>
                                                <option value="">בחר...</option>
                                                <?php foreach ($options as $option): ?>
                                                    <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            
                                        <?php elseif ($question['question_type'] == 'radio'): ?>
                                            <?php $options = json_decode($question['options'], true); ?>
                                            <div class="flex flex-wrap gap-4">
                                                <?php foreach ($options as $index => $option): ?>
                                                    <label class="inline-flex items-center">
                                                        <input type="radio" class="intake-answer" name="radio_<?php echo $question['question_id']; ?>"
                                                               value="<?php echo htmlspecialchars($option); ?>"
                                                               data-question-id="<?php echo $question['question_id']; ?>"
                                                               <?php echo $question['is_required'] && $index == 0 ? 'required' : ''; ?>>
                                                        <span class="mr-2"><?php echo htmlspecialchars($option); ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                            
                                        <?php elseif ($question['question_type'] == 'checkbox'): ?>
                                            <?php $options = json_decode($question['options'], true); ?>
                                            <div class="flex flex-wrap gap-4">
                                                <?php foreach ($options as $option): ?>
                                                    <label class="inline-flex items-center">
                                                        <input type="checkbox" class="intake-answer" value="<?php echo htmlspecialchars($option); ?>"
                                                               data-question-id="<?php echo $question['question_id']; ?>">
                                                        <span class="mr-2"><?php echo htmlspecialchars($option); ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
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
                                    <span class="font-medium">נותן שירות:</span>
                                    <span id="summary_staff"></span>
                                </div>
                                
                                <div class="flex justify-between pb-2 border-b border-gray-200">
                                    <span class="font-medium">שם מלא:</span>
                                    <span id="summary_name"></span>
                                </div>
                                
                                <div class="flex justify-between pb-2 border-b border-gray-200">
                                    <span class="font-medium">טלפון:</span>
                                    <span id="summary_phone"></span>
                                </div>
                                
                                <div id="summary_email_container" class="flex justify-between pb-2 border-b border-gray-200 hidden">
                                    <span class="font-medium">אימייל:</span>
                                    <span id="summary_email"></span>
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
                                    
                                    <div class="text-right font-medium">נותן שירות:</div>
                                    <div class="text-left" id="booking_staff"></div>
                                </div>
                            </div>
                            
                            <div class="flex flex-col md:flex-row justify-center gap-4">
                                <a href="#" id="addToCalendarBtn" class="bg-primary hover:bg-primary-dark text-white font-bold py-2 px-6 rounded-xl transition duration-300">
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
        let selectedStaffId = <?php echo isset($staff[0]) ? $staff[0]['staff_id'] : 0; ?>; // ברירת מחדל - איש צוות ראשון
        let selectedStaffName = '<?php echo isset($staff[0]) ? htmlspecialchars(addslashes($staff[0]['name'])) : ''; ?>'; // ברירת מחדל - איש צוות ראשון
        let availableTimeSlots = [];
        let calendar = null;
        
        $(document).ready(function() {
            // Scroll to booking on click
            $('#bookNowBtn').click(function() {
                $('html, body').animate({
                    scrollTop: $('#booking').offset().top - 50
                }, 1000);
            });
            
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
                    // יש לטעון חריגי ימים (חגים, ימי סגירה) מה-API
                    {
                        url: 'api/get_business_exceptions.php',
                        method: 'GET',
                        extraParams: {
                            tenant_id: <?php echo $tenant_id; ?>
                        },
                        failure: function() {
                            console.error('שגיאה בטעינת ימי סגירה');
                        }
                    }
                ],
                
                // Restrict selectable dates - disable past dates
                validRange: {
                    start: new Date()
                },
                
                // Change display for closed days
                dayCellClassNames: function(arg) {
                    const dayOfWeek = arg.date.getDay();
                    
                    <?php foreach ($hours_by_day as $day => $hours): ?>
                        <?php if (!$hours['is_open']): ?>
                            if (dayOfWeek === <?php echo $day; ?>) {
                                return ['fc-day-disabled'];
                            }
                        <?php endif; ?>
                    <?php endforeach; ?>
                    
                    return [];
                }
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
            
            // Check if the selected day is a business day
            const dayOfWeek = selectedDay.getDay();
            
            <?php foreach ($hours_by_day as $day => $hours): ?>
                <?php if (!$hours['is_open']): ?>
                    if (dayOfWeek === <?php echo $day; ?>) {
                        alert('העסק סגור ביום זה. אנא בחר יום אחר.');
                        return;
                    }
                <?php endif; ?>
            <?php endforeach; ?>
            
            selectedDate = dateStr;
            $('#selectedDate').text(formatDateHebrew(selectedDate));
            
            // Clear previous selection
            $('.fc-day').removeClass('selected-date');
            $('.fc-day[data-date="' + dateStr + '"]').addClass('selected-date');
            
            // Fetch available time slots for this date and service
            fetchAvailableTimeSlots(selectedServiceId, dateStr, selectedStaffId);
        }
        
        // Format date in Hebrew
        function formatDateHebrew(dateStr) {
            const date = new Date(dateStr);
            const day = date.getDate();
            const month = date.getMonth() + 1;
            const year = date.getFullYear();
            
            // Get day name
            const dayNames = ["ראשון", "שני", "שלישי", "רביעי", "חמישי", "שישי", "שבת"];
            const dayName = dayNames[date.getDay()];
            
            return `יום ${dayName}, ${day}/${month}/${year}`;
        }
        
        // Fetch available time slots from API
        function fetchAvailableTimeSlots(serviceId, dateStr, staffId) {
            $('#timeSlots').removeClass('hidden');
            const container = $('#timeSlotsContainer');
            container.empty();
            
            // הצגת אנימציית טעינה
            container.html('<div class="col-span-full text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i> טוען זמנים פנויים...</div>');
            
            // קריאה ל-API לקבלת סלוטים פנויים
            $.get('api/available_slots.php', {
                date: dateStr,
                service_id: serviceId,
                staff_id: staffId,
                tenant_id: <?php echo $tenant_id; ?>
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
                    // עדכון כפתור המשך להיות לא פעיל עד לבחירת שעה
                    $('#nextToStep3').addClass('bg-gray-300 text-gray-500 cursor-not-allowed')
                                     .removeClass('bg-primary hover:bg-primary-dark text-white');
                } else {
                    // אין סלוטים פנויים
                    $('#noTimeSlotsMessage').removeClass('hidden');
                    if (response.message) {
                        $('#noTimeSlotsMessage p').text(response.message);
                    }
                    
                    // כפתור המשך לא פעיל
                    $('#nextToStep3').addClass('bg-gray-300 text-gray-500 cursor-not-allowed')
                                     .removeClass('bg-primary hover:bg-primary-dark text-white');
                }
            }).fail(function() {
                container.html('<div class="col-span-full text-center py-4 text-red-500"><i class="fas fa-exclamation-circle mr-2"></i> אירעה שגיאה בטעינת הזמנים הפנויים</div>');
                
                // כפתור המשך לא פעיל
                $('#nextToStep3').addClass('bg-gray-300 text-gray-500 cursor-not-allowed')
                                 .removeClass('bg-primary hover:bg-primary-dark text-white');
            });
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
                
// הגדרת אירוע בחירת איש צוות
$('.staff-card').click(function() {
                    $('.staff-card').removeClass('border-primary selected').addClass('border-gray-300');
                    $(this).removeClass('border-gray-300').addClass('border-primary selected');
                    selectedStaffId = $(this).data('staff-id');
                    selectedStaffName = $(this).find('p').text();
                    
                    // עדכון התאריכים והזמנים בהתאם לאיש הצוות שנבחר
                    if (selectedDate) {
                        fetchAvailableTimeSlots(selectedServiceId, selectedDate, selectedStaffId);
                    }
                });
                
                // בחירת איש צוות ראשון כברירת מחדל
                if ($('.staff-card').length > 0) {
                    $('.staff-card:first').addClass('border-primary selected').removeClass('border-gray-300');
                    selectedStaffId = $('.staff-card:first').data('staff-id');
                    selectedStaffName = $('.staff-card:first').find('p').text();
                }
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
                
                // בדיקת תקינות מספר טלפון
                if (!isValidPhoneNumber(phone)) {
                    alert('אנא הזן מספר טלפון תקין');
                    return;
                }
                
                // בדיקת תקינות אימייל אם הוזן
                const email = $('#email').val();
                if (email && !isValidEmail(email)) {
                    alert('אנא הזן כתובת אימייל תקינה');
                    return;
                }
                
                // בדיקת שאלות חובה
                let missingRequired = false;
                $('.intake-answer[required]').each(function() {
                    if (!$(this).val()) {
                        missingRequired = true;
                        return false; // break the loop
                    }
                });
                
                if (missingRequired) {
                    alert('אנא ענה על כל שאלות החובה');
                    return;
                }
                
                // Update summary
                $('#summary_service').text(selectedServiceName);
                $('#summary_date').text(formatDateHebrew(selectedDate));
                $('#summary_time').text(selectedTimeSlot);
                $('#summary_staff').text(selectedStaffName);
                $('#summary_name').text(firstName + ' ' + lastName);
                $('#summary_phone').text(phone);
                
                if (email) {
                    $('#summary_email').text(email);
                    $('#summary_email_container').removeClass('hidden');
                } else {
                    $('#summary_email_container').addClass('hidden');
                }
                
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
                // הצגת אנימציית טעינה
                const button = $(this);
                button.html('<div class="loading-spinner mr-2"></div> מעבד...');
                button.prop('disabled', true);
                
                // איסוף תשובות לשאלות מקדימות
                const intakeAnswers = {};
                $('.intake-answer').each(function() {
                    const questionId = $(this).data('question-id');
                    if (questionId) {
                        if ($(this).attr('type') === 'checkbox') {
                            if ($(this).is(':checked')) {
                                if (!intakeAnswers[questionId]) {
                                    intakeAnswers[questionId] = [];
                                }
                                intakeAnswers[questionId].push($(this).val());
                            }
                        } else if ($(this).attr('type') === 'radio') {
                            if ($(this).is(':checked')) {
                                intakeAnswers[questionId] = $(this).val();
                            }
                        } else {
                            intakeAnswers[questionId] = $(this).val();
                        }
                    }
                });
                
                // בניית אובייקט הנתונים לשליחה
                const bookingData = {
                    tenant_id: <?php echo $tenant_id; ?>,
                    service_id: selectedServiceId,
                    staff_id: selectedStaffId, 
                    appointment_date: selectedDate,
                    appointment_time: selectedTimeSlot,
                    first_name: $('#first_name').val(),
                    last_name: $('#last_name').val(),
                    phone: $('#phone').val(),
                    email: $('#email').val(),
                    notes: $('#notes').val(),
                    intake_answers: intakeAnswers
                };
                
                // שליחה ל-API
                $.ajax({
                    url: 'api/booking.php',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(bookingData),
                    success: function(response) {
                        if (response.success) {
                            // עדכון פרטי התור במסך ההצלחה
                            $('#booking_reference').text(response.reference || '');
                            $('#booking_service').text(selectedServiceName);
                            $('#booking_date').text(formatDateHebrew(selectedDate));
                            $('#booking_time').text(selectedTimeSlot);
                            $('#booking_staff').text(selectedStaffName);
                            
                            // יצירת קישור להוספה ליומן
                            const calendarEvent = generateCalendarLink(
                                selectedServiceName, 
                                response.start_datetime || `${selectedDate}T${selectedTimeSlot}:00`,
                                response.end_datetime || `${selectedDate}T${selectedTimeSlot}:00`,
                                '<?php echo htmlspecialchars($tenant['business_name']); ?>',
                                '<?php echo htmlspecialchars($tenant['address'] ?? ''); ?>'
                            );
                            $('#addToCalendarBtn').attr('href', calendarEvent);
                            
                            // הצגת מסך הצלחה
                            $('.booking-step').removeClass('active');
                            $('#step5').addClass('active');
                            
                            // הסתרת סמן התקדמות
                            $('.step-indicator').parent().addClass('hidden');
                            $('#progress-bar').parent().addClass('hidden');
                        } else {
                            // טיפול בשגיאה
                            alert('אירעה שגיאה: ' + (response.message || 'אנא נסה שנית'));
                            button.html('אישור הזמנה');
                            button.prop('disabled', false);
                        }
                    },
                    error: function() {
                        alert('אירעה שגיאה בעת שליחת הבקשה');
                        button.html('אישור הזמנה');
                        button.prop('disabled', false);
                    }
                });
            });
        }
        
        // בדיקת תקינות מספר טלפון
        function isValidPhoneNumber(phone) {
            // ניקוי מקפים ורווחים
            const cleanPhone = phone.replace(/[\s-]/g, '');
            // בדיקת תבנית מספר טלפון ישראלי
            return /^(0[23489]\d{7}|05\d{8})$/.test(cleanPhone);
        }
        
        // בדיקת תקינות אימייל
        function isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
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
        
        // יצירת קישור להוספה ליומן גוגל
        function generateCalendarLink(title, start, end, location, address) {
            // המרת תאריכים לפורמט RFC5545
            const formatDateTime = (dateTimeStr) => {
                const dt = new Date(dateTimeStr);
                return dt.toISOString().replace(/-|:|\.\d+/g, '');
            };
            
            const startFormatted = formatDateTime(start);
            const endFormatted = formatDateTime(end);
            
            // יצירת התיאור
            const description = "תור שנקבע ב" + location;
            
            // בניית הקישור
            const googleCalendarUrl = 'https://www.google.com/calendar/render?action=TEMPLATE' +
                '&text=' + encodeURIComponent(title) +
                '&dates=' + startFormatted + '/' + endFormatted +
                '&details=' + encodeURIComponent(description) +
                '&location=' + encodeURIComponent(address) +
                '&sprop=&sprop=name:';
                
            return googleCalendarUrl;
        }
    </script>
</body>
</html>

<?php
// סגירת החיבור למסד הנתונים
$db = null;
?>