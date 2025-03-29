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

// כותרת העמוד
$page_title = "לוח בקרה";

// יצירת חיבור למסד הנתונים
$database = new Database();
$db = $database->getConnection();

// שליפת מידע על בעל העסק
$tenant_id = $_SESSION['tenant_id'];

try {
    // שליפת פרטי העסק
    $query = "SELECT * FROM tenants WHERE tenant_id = :tenant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    $tenant = $stmt->fetch();
    
    // שליפת פרטי התורים להיום
    $today = date('Y-m-d');
    $query = "
        SELECT a.*, c.first_name, c.last_name, c.phone, s.name as service_name, st.name as staff_name 
        FROM appointments a
        JOIN customers c ON a.customer_id = c.customer_id
        JOIN services s ON a.service_id = s.service_id
        JOIN staff st ON a.staff_id = st.staff_id
        WHERE a.tenant_id = :tenant_id 
        AND DATE(a.start_datetime) = :today
        ORDER BY a.start_datetime ASC
    ";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->bindParam(":today", $today);
    $stmt->execute();
    $todays_appointments = $stmt->fetchAll();
    
    // ספירת כל התורים של היום
    $query = "
        SELECT COUNT(*) as total_count 
        FROM appointments 
        WHERE tenant_id = :tenant_id 
        AND DATE(start_datetime) = :today
    ";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->bindParam(":today", $today);
    $stmt->execute();
    $appointments_count = $stmt->fetch()['total_count'];
    
    // חישוב הכנסות השבוע
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $week_end = date('Y-m-d', strtotime('sunday this week'));
    $query = "
        SELECT SUM(s.price) as weekly_revenue
        FROM appointments a
        JOIN services s ON a.service_id = s.service_id
        WHERE a.tenant_id = :tenant_id 
        AND DATE(a.start_datetime) BETWEEN :week_start AND :week_end
        AND a.status IN ('confirmed', 'completed')
    ";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->bindParam(":week_start", $week_start);
    $stmt->bindParam(":week_end", $week_end);
    $stmt->execute();
    $weekly_revenue = $stmt->fetch()['weekly_revenue'] ?? 0;
    
    // ספירת לקוחות חדשים החודש
    $month_start = date('Y-m-01');
    $query = "
        SELECT COUNT(*) as new_customers
        FROM customers
        WHERE tenant_id = :tenant_id 
        AND DATE(created_at) >= :month_start
    ";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->bindParam(":month_start", $month_start);
    $stmt->execute();
    $new_customers = $stmt->fetch()['new_customers'];
    
    // שליפת פעילות אחרונה - 5 תורים אחרונים
    $query = "
        SELECT a.*, c.first_name, c.last_name, s.name as service_name, a.created_at
        FROM appointments a
        JOIN customers c ON a.customer_id = c.customer_id
        JOIN services s ON a.service_id = s.service_id
        WHERE a.tenant_id = :tenant_id
        ORDER BY a.created_at DESC
        LIMIT 5
    ";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    $recent_activities = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_message = "שגיאת מערכת: " . $e->getMessage();
}

// נתונים לצורך בדיקה
$sample_data = [
    'appointments_count' => $appointments_count,
    'weekly_revenue' => $weekly_revenue,
    'new_customers' => $new_customers
];
?>

<?php include_once "includes/header.php"; ?>
<?php include_once "includes/sidebar.php"; ?>

<!-- Main Content -->
<main class="flex-1 overflow-y-auto pb-10">
    <!-- Top Navigation Bar -->
    <header class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-gray-800">לוח בקרה</h1>
            
            <div class="flex items-center space-x-4 space-x-reverse">
                <a href="#" class="relative">
                    <i class="fas fa-bell text-gray-600 text-lg"></i>
                    <span class="absolute -top-1 -right-1 bg-primary text-white text-xs rounded-full h-4 w-4 flex items-center justify-center">3</span>
                </a>
                
                <div class="flex items-center space-x-3 space-x-reverse">
                    <img src="<?php echo !empty($tenant['logo_path']) ? htmlspecialchars($tenant['logo_path']) : 'https://via.placeholder.com/40'; ?>" alt="Profile" class="w-8 h-8 rounded-full">
                    <span class="text-sm font-medium hidden md:block"><?php echo htmlspecialchars($tenant['business_name']); ?></span>
                </div>
            </div>
        </div>
    </header>

    <!-- Dashboard Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Greeting and Date -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-800">שלום, <?php echo explode(' ', $tenant['business_name'])[0]; ?>! 👋</h2>
            <p class="text-gray-600">היום <?php echo hebrewDayOfWeek(date('Y-m-d')); ?>, <?php echo date('d.m.Y'); ?></p>
        </div>
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white p-6 rounded-2xl shadow-sm">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-gray-500 text-sm mb-1">תורים להיום</p>
                        <h3 class="text-3xl font-bold"><?php echo $sample_data['appointments_count']; ?></h3>
                    </div>
                    <div class="bg-pastel-purple p-3 rounded-xl">
                        <i class="fas fa-calendar-day text-primary"></i>
                    </div>
                </div>
                <div class="flex items-center mt-4 text-sm">
                    <span class="text-green-500 flex items-center">
                        <i class="fas fa-arrow-up mr-1"></i>
                        20%
                    </span>
                    <span class="text-gray-500 mr-2">משבוע שעבר</span>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-2xl shadow-sm">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-gray-500 text-sm mb-1">הכנסות השבוע</p>
                        <h3 class="text-3xl font-bold">₪<?php echo number_format($sample_data['weekly_revenue'], 0); ?></h3>
                    </div>
                    <div class="bg-pastel-green p-3 rounded-xl">
                        <i class="fas fa-shekel-sign text-green-600"></i>
                    </div>
                </div>
                <div class="flex items-center mt-4 text-sm">
                    <span class="text-green-500 flex items-center">
                        <i class="fas fa-arrow-up mr-1"></i>
                        15%
                    </span>
                    <span class="text-gray-500 mr-2">משבוע שעבר</span>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-2xl shadow-sm">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-gray-500 text-sm mb-1">לקוחות חדשים</p>
                        <h3 class="text-3xl font-bold"><?php echo $sample_data['new_customers']; ?></h3>
                    </div>
                    <div class="bg-pastel-blue p-3 rounded-xl">
                        <i class="fas fa-users text-secondary"></i>
                    </div>
                </div>
                <div class="flex items-center mt-4 text-sm">
                    <span class="text-green-500 flex items-center">
                        <i class="fas fa-arrow-up mr-1"></i>
                        8%
                    </span>
                    <span class="text-gray-500 mr-2">מחודש קודם</span>
                </div>
            </div>
        </div>
        
        <!-- Today's Appointments and Calendar Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Today's Appointments -->
            <div class="bg-white rounded-2xl shadow-sm overflow-hidden lg:col-span-1">
                <div class="p-6 border-b">
                    <h3 class="text-lg font-bold">התורים של היום</h3>
                </div>
                
                <div class="overflow-y-auto max-h-[400px]">
                    <?php if (!empty($todays_appointments)): ?>
                        <?php foreach ($todays_appointments as $appointment): ?>
                            <!-- Appointment Item -->
                            <div class="p-4 border-b hover:bg-gray-50">
                                <div class="flex justify-between items-center mb-2">
                                    <?php 
                                    $status_class = '';
                                    $status_text = '';
                                    
                                    switch ($appointment['status']) {
                                        case 'pending':
                                            $status_class = 'bg-pastel-yellow text-yellow-800';
                                            $status_text = 'ממתין';
                                            break;
                                        case 'confirmed':
                                            $status_class = 'bg-pastel-green text-green-800';
                                            $status_text = 'מאושר';
                                            break;
                                        case 'cancelled':
                                            $status_class = 'bg-pastel-red text-red-800';
                                            $status_text = 'בוטל';
                                            break;
                                        case 'completed':
                                            $status_class = 'bg-pastel-blue text-blue-800';
                                            $status_text = 'הושלם';
                                            break;
                                        case 'no_show':
                                            $status_class = 'bg-gray-200 text-gray-800';
                                            $status_text = 'לא הגיע';
                                            break;
                                    }
                                    ?>
                                    <span class="<?php echo $status_class; ?> px-2 py-1 rounded-lg text-sm"><?php echo $status_text; ?></span>
                                    <span class="text-sm text-gray-500">
                                        <?php 
                                        echo date('H:i', strtotime($appointment['start_datetime']));
                                        echo ' - ';
                                        echo date('H:i', strtotime($appointment['end_datetime']));
                                        ?>
                                    </span>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-gray-600 font-bold">
                                        <?php echo mb_substr($appointment['first_name'], 0, 1, 'UTF-8') . mb_substr($appointment['last_name'], 0, 1, 'UTF-8'); ?>
                                    </div>
                                    <div class="mr-3">
                                        <h4 class="font-medium"><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></h4>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($appointment['service_name']); ?></p>
                                    </div>
                                </div>
                                <div class="flex justify-between mt-3">
                                    <span class="text-sm text-gray-500"><?php echo htmlspecialchars($appointment['staff_name']); ?></span>
                                    <div>
                                        <a href="tel:<?php echo htmlspecialchars($appointment['phone']); ?>" class="text-gray-400 hover:text-primary mr-2">
                                            <i class="fas fa-phone-alt"></i>
                                        </a>
                                        <button class="text-gray-400 hover:text-primary appointment-options" data-id="<?php echo $appointment['appointment_id']; ?>">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-6 text-center text-gray-500">
                            <i class="fas fa-calendar-day text-gray-300 text-4xl mb-3"></i>
                            <p>אין תורים מתוכננים להיום</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="p-4 text-center">
                    <a href="appointments.php" class="text-primary font-medium hover:underline">כל התורים</a>
                </div>
            </div>
            
            <!-- Calendar Section -->
            <div class="bg-white rounded-2xl shadow-sm overflow-hidden lg:col-span-2">
                <div class="p-6 border-b">
                    <h3 class="text-lg font-bold">לוח שנה</h3>
                </div>
                <div class="p-4">
                    <div id="calendar" class="min-h-[400px]"></div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activities and Confirmation Status -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Recent Activities -->
            <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                <div class="p-6 border-b">
                    <h3 class="text-lg font-bold">פעילות אחרונה</h3>
                </div>
                
                <div class="overflow-y-auto max-h-[350px]">
                    <?php if (!empty($recent_activities)): ?>
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="p-4 border-b">
                                <div class="flex items-start">
                                    <div class="relative">
                                        <?php 
                                        $icon_class = '';
                                        $bg_class = '';
                                        $status_indicator = '';
                                        
                                        switch ($activity['status']) {
                                            case 'pending':
                                                $icon_class = 'fas fa-calendar-plus text-secondary';
                                                $bg_class = 'bg-pastel-blue';
                                                $status_indicator = 'bg-yellow-500';
                                                $activity_text = 'תור חדש נקבע';
                                                break;
                                            case 'confirmed':
                                                $icon_class = 'fas fa-check text-green-600';
                                                $bg_class = 'bg-pastel-green';
                                                $status_indicator = 'bg-green-500';
                                                $activity_text = 'תור אושר';
                                                break;
                                            case 'cancelled':
                                                $icon_class = 'fas fa-times text-red-600';
                                                $bg_class = 'bg-pastel-red';
                                                $status_indicator = 'bg-red-500';
                                                $activity_text = 'תור בוטל';
                                                break;
                                            case 'completed':
                                                $icon_class = 'fas fa-check-double text-blue-600';
                                                $bg_class = 'bg-pastel-blue';
                                                $status_indicator = 'bg-blue-500';
                                                $activity_text = 'תור הושלם';
                                                break;
                                            default:
                                                $icon_class = 'fas fa-calendar-day text-gray-600';
                                                $bg_class = 'bg-gray-200';
                                                $status_indicator = 'bg-gray-500';
                                                $activity_text = 'עדכון תור';
                                        }
                                        ?>
                                        <div class="<?php echo $bg_class; ?> p-2 rounded-lg">
                                            <i class="<?php echo $icon_class; ?>"></i>
                                        </div>
                                        <div class="absolute top-0 right-0 transform translate-x-1/3 -translate-y-1/3 bg-white p-1 rounded-full border">
                                            <div class="<?php echo $status_indicator; ?> w-2 h-2 rounded-full"></div>
                                        </div>
                                    </div>
                                    <div class="mr-4">
                                        <p class="font-medium"><?php echo $activity_text; ?></p>
                                        <p class="text-gray-500 text-sm">
                                            <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?> 
                                            <?php
                                            $activity_description = '';
                                            switch ($activity['status']) {
                                                case 'pending':
                                                    $activity_description = 'קבע/ה תור ל' . $activity['service_name'];
                                                    break;
                                                case 'confirmed':
                                                    $activity_description = 'אישר/ה את הגעתו/ה לתור';
                                                    break;
                                                case 'cancelled':
                                                    $activity_description = 'ביטל/ה את התור';
                                                    break;
                                                case 'completed':
                                                    $activity_description = 'השלים/ה את הפגישה';
                                                    break;
                                                default:
                                                    $activity_description = 'עדכן/ה את התור';
                                            }
                                            echo $activity_description;
                                            ?>
                                        </p>
                                        <p class="text-gray-400 text-xs mt-1">
                                            <?php 
                                            $time_diff = time() - strtotime($activity['created_at']);
                                            if ($time_diff < 60) {
                                                echo 'לפני פחות מדקה';
                                            } elseif ($time_diff < 3600) {
                                                echo 'לפני ' . floor($time_diff / 60) . ' דקות';
                                            } elseif ($time_diff < 86400) {
                                                echo 'לפני ' . floor($time_diff / 3600) . ' שעות';
                                            } else {
                                                echo 'לפני ' . floor($time_diff / 86400) . ' ימים';
                                            }
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-6 text-center text-gray-500">
                            <p>אין פעילות אחרונה להצגה</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Confirmation Status -->
            <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                <div class="p-6 border-b">
                    <h3 class="text-lg font-bold">סטטוס אישורי הגעה</h3>
                </div>
                
                <div class="p-6">
                    <!-- Appointments Confirmation Distribution -->
                    <div class="flex items-center justify-center mb-6">
                        <div class="w-full max-w-xs">
                            <div class="relative pt-1">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="text-xs font-semibold inline-block py-1 px-2 uppercase rounded-full text-green-600 bg-green-200">
                                            מאושרים
                                        </span>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs font-semibold inline-block text-green-600">
                                            70%
                                        </span>
                                    </div>
                                </div>
                                <div class="overflow-hidden h-2 mb-4 text-xs flex rounded bg-green-200">
                                    <div style="width:70%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-green-500"></div>
                                </div>
                            </div>
                            
                            <div class="relative pt-1">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="text-xs font-semibold inline-block py-1 px-2 uppercase rounded-full text-yellow-600 bg-yellow-200">
                                            ממתינים
                                        </span>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs font-semibold inline-block text-yellow-600">
                                            20%
                                        </span>
                                    </div>
                                </div>
                                <div class="overflow-hidden h-2 mb-4 text-xs flex rounded bg-yellow-200">
                                    <div style="width:20%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-yellow-500"></div>
                                </div>
                            </div>
                            
                            <div class="relative pt-1">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="text-xs font-semibold inline-block py-1 px-2 uppercase rounded-full text-red-600 bg-red-200">
                                            מבוטלים
                                        </span>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs font-semibold inline-block text-red-600">
                                            10%
                                        </span>
                                    </div>
                                </div>
                                <div class="overflow-hidden h-2 mb-4 text-xs flex rounded bg-red-200">
                                    <div style="width:10%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-red-500"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-6">
                        <p class="text-sm text-gray-500 mb-4">7 תורים מתוכננים ליומיים הקרובים מחכים לאישור</p>
                        <a href="appointments.php?filter=pending" class="inline-block bg-primary hover:bg-primary-dark text-white font-bold py-2 px-6 rounded-xl transition duration-300">
                            שלח תזכורות
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // יצירת לוח השנה
        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            locale: 'he',
            initialView: 'dayGridMonth',
            height: 'auto',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            buttonText: {
                today: 'היום',
                month: 'חודש',
                week: 'שבוע',
                day: 'יום'
            },
            direction: 'rtl',
            firstDay: 0, // יום ראשון כיום ראשון בשבוע
            events: [
                // כאן יהיו נתוני התורים האמיתיים - לדוגמה בינתיים
                <?php foreach ($todays_appointments as $appointment): ?>
                {
                    title: '<?php echo addslashes($appointment['service_name'] . ' - ' . $appointment['first_name'] . ' ' . $appointment['last_name']); ?>',
                    start: '<?php echo $appointment['start_datetime']; ?>',
                    end: '<?php echo $appointment['end_datetime']; ?>',
                    <?php if ($appointment['status'] == 'confirmed'): ?>
                    backgroundColor: '#10b981',
                    borderColor: '#10b981',
                    <?php elseif ($appointment['status'] == 'pending'): ?>
                    backgroundColor: '#f59e0b',
                    borderColor: '#f59e0b',
                    <?php elseif ($appointment['status'] == 'cancelled'): ?>
                    backgroundColor: '#ef4444',
                    borderColor: '#ef4444',
                    <?php endif; ?>
                    url: 'appointment_details.php?id=<?php echo $appointment['appointment_id']; ?>'
                },
                <?php endforeach; ?>
            ]
        });
        calendar.render();
    });
</script>

<?php include_once "includes/footer.php"; ?>