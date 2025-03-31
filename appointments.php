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
$page_title = "יומן תורים";

// יצירת חיבור למסד הנתונים
$database = new Database();
$db = $database->getConnection();

// קבלת ה-tenant_id מהסשן
$tenant_id = $_SESSION['tenant_id'];

// שליפת רשימת נותני שירות לסינון
$query = "SELECT staff_id, name FROM staff WHERE tenant_id = :tenant_id AND is_active = 1 ORDER BY name";
$stmt = $db->prepare($query);
$stmt->bindParam(":tenant_id", $tenant_id);
$stmt->execute();
$staff_members = $stmt->fetchAll();

// שליפת רשימת שירותים לסינון
$query = "SELECT service_id, name FROM services WHERE tenant_id = :tenant_id AND is_active = 1 ORDER BY name";
$stmt = $db->prepare($query);
$stmt->bindParam(":tenant_id", $tenant_id);
$stmt->execute();
$services = $stmt->fetchAll();

// שליפת התאריך הנוכחי
$current_date = date('Y-m-d');
$current_month = date('m');
$current_year = date('Y');

// עיבוד פרמטרים מה-URL לסינון
$staff_filter = isset($_GET['staff']) ? intval($_GET['staff']) : 0;
$service_filter = isset($_GET['service']) ? intval($_GET['service']) : 0;
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$month_filter = isset($_GET['month']) ? intval($_GET['month']) : intval($current_month);
$year_filter = isset($_GET['year']) ? intval($_GET['year']) : intval($current_year);

// הגדרת תחילת וסוף החודש לתצוגה
$start_date = date('Y-m-d', strtotime("$year_filter-$month_filter-01"));
$end_date = date('Y-m-t', strtotime($start_date));

// שליפת חריגי שעות פעילות (חגים, חופשות)
$query = "SELECT * FROM business_hour_exceptions 
          WHERE tenant_id = :tenant_id 
          AND exception_date BETWEEN :start_date AND :end_date";
$stmt = $db->prepare($query);
$stmt->bindParam(":tenant_id", $tenant_id);
$stmt->bindParam(":start_date", $start_date);
$stmt->bindParam(":end_date", $end_date);
$stmt->execute();
$business_exceptions = [];

while ($row = $stmt->fetch()) {
    $business_exceptions[$row['exception_date']] = $row;
}

include_once "includes/header.php";
include_once "includes/sidebar.php";
?>

<!-- Main Content -->
<main class="flex-1 overflow-y-auto pb-10">
    <!-- Top Navigation Bar -->
    <header class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-gray-800">יומן תורים</h1>
            
            <div class="flex items-center space-x-4 space-x-reverse">
                <a href="#" class="relative inline-flex items-center" id="addAppointmentBtn">
                    <span class="bg-primary hover:bg-primary-dark text-white text-sm px-4 py-2 rounded-lg transition duration-300 inline-flex items-center">
                        <i class="fas fa-plus ml-2"></i>
                        <span>הוסף תור</span>
                    </span>
                </a>
            </div>
        </div>
    </header>

    <!-- Calendar Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Filters Section -->
        <div class="bg-white shadow-sm rounded-2xl p-4 mb-6">
            <form action="appointments.php" method="GET" class="md:flex flex-wrap items-end justify-between gap-4">
                <!-- Left Side Filters -->
                <div class="flex flex-wrap gap-4 flex-1 items-end">
                    <div>
                        <label for="staff" class="block text-sm font-medium text-gray-700 mb-1">נותן שירות</label>
                        <select id="staff" name="staff" class="w-full md:w-48 px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="0">כל נותני השירות</option>
                            <?php foreach ($staff_members as $staff): ?>
                                <option value="<?php echo $staff['staff_id']; ?>" <?php echo ($staff_filter == $staff['staff_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($staff['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="service" class="block text-sm font-medium text-gray-700 mb-1">שירות</label>
                        <select id="service" name="service" class="w-full md:w-48 px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="0">כל השירותים</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?php echo $service['service_id']; ?>" <?php echo ($service_filter == $service['service_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($service['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">סטטוס</label>
                        <select id="status" name="status" class="w-full md:w-48 px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">כל הסטטוסים</option>
                            <option value="pending" <?php echo ($status_filter === 'pending') ? 'selected' : ''; ?>>ממתין לאישור</option>
                            <option value="confirmed" <?php echo ($status_filter === 'confirmed') ? 'selected' : ''; ?>>מאושר</option>
                            <option value="cancelled" <?php echo ($status_filter === 'cancelled') ? 'selected' : ''; ?>>בוטל</option>
                            <option value="completed" <?php echo ($status_filter === 'completed') ? 'selected' : ''; ?>>הושלם</option>
                            <option value="no_show" <?php echo ($status_filter === 'no_show') ? 'selected' : ''; ?>>לא הגיע</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="monthYear" class="block text-sm font-medium text-gray-700 mb-1">חודש ושנה</label>
                        <div class="flex items-center gap-2">
                            <select id="month" name="month" class="px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo ($month_filter == $m) ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0, 0, 0, $m, 1, date('Y'))); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            
                            <select id="year" name="year" class="px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                <?php for ($y = date('Y') - 1; $y <= date('Y') + 2; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php echo ($year_filter == $y) ? 'selected' : ''; ?>>
                                        <?php echo $y; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Right Side Buttons -->
                <div class="flex gap-2 mt-4 md:mt-0">
                    <button type="submit" class="bg-primary hover:bg-primary-dark text-white px-6 py-2 rounded-xl transition duration-300">
                        <i class="fas fa-filter ml-2"></i>
                        סנן
                    </button>
                    
                    <a href="appointments.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-xl transition duration-300">
                        <i class="fas fa-times ml-2"></i>
                        נקה
                    </a>
                    
                    <button type="button" id="todayButton" class="bg-secondary hover:bg-secondary-dark text-white px-4 py-2 rounded-xl transition duration-300">
                        היום
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Calendar Container -->
        <div class="bg-white shadow-sm rounded-2xl p-6 mb-6">
            <!-- Calendar Navigation -->
            <div class="flex justify-between items-center mb-8">
                <div class="text-xl font-bold">
                    <?php 
                    // שם החודש בעברית
                    $month_names = [
                        1 => 'ינואר', 2 => 'פברואר', 3 => 'מרץ', 4 => 'אפריל',
                        5 => 'מאי', 6 => 'יוני', 7 => 'יולי', 8 => 'אוגוסט',
                        9 => 'ספטמבר', 10 => 'אוקטובר', 11 => 'נובמבר', 12 => 'דצמבר'
                    ];
                    echo $month_names[$month_filter] . ' ' . $year_filter;
                    ?>
                </div>
                
                <div class="flex space-x-2 space-x-reverse">
                    <!-- כפתורי ניווט -->
                    <?php
                    // חישוב החודש הקודם והבא
                    $prev_month = $month_filter - 1;
                    $prev_year = $year_filter;
                    if ($prev_month < 1) {
                        $prev_month = 12;
                        $prev_year--;
                    }
                    
                    $next_month = $month_filter + 1;
                    $next_year = $year_filter;
                    if ($next_month > 12) {
                        $next_month = 1;
                        $next_year++;
                    }
                    ?>
                    <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>&staff=<?php echo $staff_filter; ?>&service=<?php echo $service_filter; ?>&status=<?php echo $status_filter; ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded-xl transition duration-300">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    
                    <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>&staff=<?php echo $staff_filter; ?>&service=<?php echo $service_filter; ?>&status=<?php echo $status_filter; ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 px-4 rounded-xl transition duration-300">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </div>
            </div>
            
            <!-- Calendar Grid -->
            <div class="calendar-grid">
                <!-- Days of Week Header -->
                <div class="calendar-grid-header">
                    <div class="calendar-header-cell">ראשון</div>
                    <div class="calendar-header-cell">שני</div>
                    <div class="calendar-header-cell">שלישי</div>
                    <div class="calendar-header-cell">רביעי</div>
                    <div class="calendar-header-cell">חמישי</div>
                    <div class="calendar-header-cell">שישי</div>
                    <div class="calendar-header-cell">שבת</div>
                </div>
                
                <!-- Calendar Days -->
                <div class="calendar-grid-days">
                    <?php
                    // מציאת היום הראשון בחודש
                    $first_day_of_month = date('N', strtotime($start_date));
                    
                    // התאמה לפורמט שמתחיל ביום ראשון (0-6 במקום 1-7)
                    $first_day_of_month = ($first_day_of_month % 7);
                    
                    // מספר הימים בחודש
                    $days_in_month = date('t', strtotime($start_date));
                    
                    // מציאת התאריך של היום האחרון בחודש הקודם
                    $prev_month_last_day = date('t', strtotime($start_date . ' -1 month'));
                    
                    // מילוי ימים מהחודש הקודם
                    for ($i = 0; $i < $first_day_of_month; $i++) {
                        $day_num = $prev_month_last_day - ($first_day_of_month - $i - 1);
                        echo '<div class="calendar-day calendar-day-other-month">';
                        echo '<div class="calendar-day-number">' . $day_num . '</div>';
                        echo '</div>';
                    }
                    
                    // מילוי ימי החודש הנוכחי
                    for ($day = 1; $day <= $days_in_month; $day++) {
                        $date = sprintf('%04d-%02d-%02d', $year_filter, $month_filter, $day);
                        $is_today = ($date == $current_date);
                        $is_weekend = (date('N', strtotime($date)) >= 6); // יום שישי ושבת
                        $has_exception = isset($business_exceptions[$date]);
                        
                        $day_class = 'calendar-day';
                        if ($is_today) $day_class .= ' calendar-day-today';
                        if ($is_weekend) $day_class .= ' calendar-day-weekend';
                        if ($has_exception) {
                            $exception = $business_exceptions[$date];
                            if (!$exception['is_open']) {
                                $day_class .= ' calendar-day-closed';
                            } else {
                                $day_class .= ' calendar-day-special';
                            }
                        }
                        
                        echo '<div class="' . $day_class . '" data-date="' . $date . '">';
                        echo '<div class="calendar-day-number">' . $day . '</div>';
                        
                        // חריג עסקי - סגור או שעות מיוחדות
                        if ($has_exception) {
                            $exception = $business_exceptions[$date];
                            if (!$exception['is_open']) {
                                echo '<div class="calendar-day-closed-tag">סגור</div>';
                            } else {
                                echo '<div class="calendar-day-special-hours">';
                                if (!empty($exception['description'])) {
                                    echo htmlspecialchars($exception['description']);
                                } else {
                                    echo 'שעות מיוחדות';
                                }
                                echo '</div>';
                            }
                        }
                        
                        // כאן יוצגו התורים ליום זה
                        echo '<div class="calendar-appointments" id="appointments-' . $date . '"></div>';
                        
                        echo '</div>';
                    }
                    
                    // מילוי ימים מהחודש הבא
                    $total_cells = 42; // 6 שורות * 7 ימים
                    $remaining_cells = $total_cells - ($first_day_of_month + $days_in_month);
                    
                    for ($i = 1; $i <= $remaining_cells; $i++) {
                        echo '<div class="calendar-day calendar-day-other-month">';
                        echo '<div class="calendar-day-number">' . $i . '</div>';
                        echo '</div>';
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <!-- Legend -->
        <div class="bg-white shadow-sm rounded-2xl p-4 mb-6">
            <h3 class="font-bold mb-3">מקרא סטטוסים:</h3>
            <div class="flex flex-wrap gap-4">
                <div class="flex items-center">
                    <div class="w-4 h-4 bg-yellow-400 rounded-full mr-2"></div>
                    <span>ממתין לאישור</span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 bg-green-500 rounded-full mr-2"></div>
                    <span>מאושר</span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 bg-blue-500 rounded-full mr-2"></div>
                    <span>הושלם</span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 bg-red-500 rounded-full mr-2"></div>
                    <span>בוטל</span>
                </div>
                <div class="flex items-center">
                    <div class="w-4 h-4 bg-gray-500 rounded-full mr-2"></div>
                    <span>לא הגיע</span>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal for appointment details -->
<div id="appointmentModal" class="fixed inset-0 bg-gray-800 bg-opacity-50 z-50 hidden flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-lg max-w-lg w-full p-6 max-h-90vh overflow-y-auto">
        <div class="flex justify-between items-center border-b pb-4 mb-4">
            <h3 class="text-xl font-bold text-gray-800">פרטי תור</h3>
            <button id="closeAppointmentModal" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div id="appointmentDetails">
            <!-- תוכן יוזן דינמית -->
            <div class="flex justify-center">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
            </div>
        </div>
        
        <div class="flex justify-end mt-6 gap-2">
            <button id="deleteAppointmentBtn" class="bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded-xl transition duration-300 hidden">
                <i class="fas fa-trash-alt ml-2"></i>
                מחק תור
            </button>
            
            <button id="editAppointmentBtn" class="bg-secondary hover:bg-secondary-dark text-white py-2 px-4 rounded-xl transition duration-300 hidden">
                <i class="fas fa-edit ml-2"></i>
                ערוך תור
            </button>
            
            <button id="closeModalBtn" class="bg-gray-200 hover:bg-gray-300 text-gray-800 py-2 px-4 rounded-xl transition duration-300">
                סגור
            </button>
        </div>
    </div>
</div>

<!-- Modal for adding new appointment -->
<div id="newAppointmentModal" class="fixed inset-0 bg-gray-800 bg-opacity-50 z-50 hidden flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-lg max-w-lg w-full p-6 max-h-90vh overflow-y-auto">
        <div class="flex justify-between items-center border-b pb-4 mb-4">
            <h3 class="text-xl font-bold text-gray-800">הוספת תור חדש</h3>
            <button id="closeNewAppointmentModal" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div id="newAppointmentForm">
            <form id="appointmentForm">
                <div class="mb-4">
                    <label for="appointmentDate" class="block text-sm font-medium text-gray-700 mb-1">תאריך</label>
                    <input type="date" id="appointmentDate" name="appointmentDate" class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" required>
                </div>
                
                <div class="mb-4">
                    <label for="appointmentTime" class="block text-sm font-medium text-gray-700 mb-1">שעה</label>
                    <input type="time" id="appointmentTime" name="appointmentTime" class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" required>
                </div>
                
                <div class="mb-4">
                    <label for="appointmentCustomer" class="block text-sm font-medium text-gray-700 mb-1">לקוח</label>
                    <select id="appointmentCustomer" name="appointmentCustomer" class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" required>
                        <option value="">בחר לקוח</option>
                        <!-- אפשרויות ייטענו דינמית -->
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="appointmentService" class="block text-sm font-medium text-gray-700 mb-1">שירות</label>
                    <select id="appointmentService" name="appointmentService" class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" required>
                        <option value="">בחר שירות</option>
                        <!-- אפשרויות ייטענו דינמית -->
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="appointmentStaff" class="block text-sm font-medium text-gray-700 mb-1">נותן שירות</label>
                    <select id="appointmentStaff" name="appointmentStaff" class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" required>
                        <option value="">בחר נותן שירות</option>
                        <!-- אפשרויות ייטענו דינמית -->
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="appointmentStatus" class="block text-sm font-medium text-gray-700 mb-1">סטטוס</label>
                    <select id="appointmentStatus" name="appointmentStatus" class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" required>
                        <option value="pending">ממתין לאישור</option>
                        <option value="confirmed">מאושר</option>
                        <option value="cancelled">בוטל</option>
                        <option value="completed">הושלם</option>
                        <option value="no_show">לא הגיע</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="appointmentNotes" class="block text-sm font-medium text-gray-700 mb-1">הערות</label>
                    <textarea id="appointmentNotes" name="appointmentNotes" rows="3" class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"></textarea>
                </div>
            </form>
        </div>
        
        <div class="flex justify-end mt-6 gap-2">
            <button id="saveAppointmentBtn" class="bg-primary hover:bg-primary-dark text-white py-2 px-4 rounded-xl transition duration-300">
                <i class="fas fa-save ml-2"></i>
                שמור תור
            </button>
            
            <button id="cancelNewAppointmentBtn" class="bg-gray-200 hover:bg-gray-300 text-gray-800 py-2 px-4 rounded-xl transition duration-300">
                ביטול
            </button>
        </div>
    </div>
</div>

<!-- JavaScript לטיפול בלוח השנה -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // פונקציית טעינת תורים לחודש
    function loadAppointments() {
        const startDate = '<?php echo $start_date; ?>';
        const endDate = '<?php echo $end_date; ?>';
        const staffFilter = <?php echo $staff_filter; ?>;
        const serviceFilter = <?php echo $service_filter; ?>;
        const statusFilter = '<?php echo $status_filter; ?>';
        
        // שליחת בקשת AJAX
        fetch(`api/get_appointments.php?start=${startDate}&end=${endDate}&staff_id=${staffFilter}&service_id=${serviceFilter}&status=${statusFilter}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // מיון התורים לפי תאריך
                    const appointmentsByDate = {};
                    
                    data.appointments.forEach(appointment => {
                        const date = appointment.start.split(' ')[0];
                        if (!appointmentsByDate[date]) {
                            appointmentsByDate[date] = [];
                        }
                        appointmentsByDate[date].push(appointment);
                    });
                    
                    // הצגת התורים בכל יום
                    for (const date in appointmentsByDate) {
                        const appointmentsContainer = document.getElementById(`appointments-${date}`);
                        if (appointmentsContainer) {
                            appointmentsContainer.innerHTML = '';
                            
                            // מיון התורים לפי שעה
                            const sortedAppointments = appointmentsByDate[date].sort((a, b) => {
                                return new Date(a.start) - new Date(b.start);
                            });
                            
                            // הצגת התורים
                            sortedAppointments.forEach(appointment => {
                                const startTime = appointment.start.split(' ')[1].substr(0, 5);
                                const customerName = appointment.customer.name;
                                
                                // בחירת צבע לפי סטטוס
                                let statusColor = '';
                                switch (appointment.status) {
                                    case 'pending': statusColor = 'bg-yellow-400'; break;
                                    case 'confirmed': statusColor = 'bg-green-500'; break;
                                    case 'cancelled': statusColor = 'bg-red-500'; break;
                                    case 'completed': statusColor = 'bg-blue-500'; break;
                                    case 'no_show': statusColor = 'bg-gray-500'; break;
                                    default: statusColor = 'bg-gray-400';
                                }
                                
                                const appointmentElement = document.createElement('div');
                                appointmentElement.className = `calendar-appointment ${statusColor} text-white rounded-lg p-1 mb-1 cursor-pointer text-xs truncate`;
                                appointmentElement.setAttribute('data-appointment-id', appointment.id);
                                appointmentElement.innerHTML = `${startTime} - ${customerName}`;
                                
                                appointmentElement.addEventListener('click', function() {
                                    showAppointmentDetails(appointment.id);
                                });
                                
                                appointmentsContainer.appendChild(appointmentElement);
                            });
                        }
                    }
                } else {
                    console.error('שגיאה בטעינת תורים:', data.message);
                }
            })
            .catch(error => {
                console.error('שגיאה בשליחת בקשה:', error);
            });
    }
    
    // פונקציה להצגת פרטי תור
    function showAppointmentDetails(appointmentId) {
        const detailsContainer = document.getElementById('appointmentDetails');
        const modal = document.getElementById('appointmentModal');
        const editBtn = document.getElementById('editAppointmentBtn');
        const deleteBtn = document.getElementById('deleteAppointmentBtn');
        
        // איפוס והצגת טעינה
        detailsContainer.innerHTML = `
            <div class="flex justify-center">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
            </div>
        `;
        
        modal.classList.remove('hidden');
        
        // טעינת פרטי התור
        fetch(`api/get_appointment.php?id=${appointmentId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const appointment = data.appointment;
                    const startDate = new Date(appointment.start_datetime);
                    const endDate = new Date(appointment.end_datetime);
                    
                    // פורמט תאריך ושעה
                    const date = startDate.toLocaleDateString('he-IL');
                    const startTime = startDate.toLocaleTimeString('he-IL', { hour: '2-digit', minute: '2-digit' });
                    const endTime = endDate.toLocaleTimeString('he-IL', { hour: '2-digit', minute: '2-digit' });
                    
                    // יצירת תצוגת סטטוס
                    let statusText = '';
                    let statusClass = '';
                    
                    switch (appointment.status) {
                        case 'pending':
                            statusText = 'ממתין לאישור';
                            statusClass = 'bg-yellow-100 text-yellow-800';
                            break;
                        case 'confirmed':
                            statusText = 'מאושר';
                            statusClass = 'bg-green-100 text-green-800';
                            break;
                        case 'cancelled':
                            statusText = 'בוטל';
                            statusClass = 'bg-red-100 text-red-800';
                            break;
                        case 'completed':
                            statusText = 'הושלם';
                            statusClass = 'bg-blue-100 text-blue-800';
                            break;
                        case 'no_show':
                            statusText = 'לא הגיע';
                            statusClass = 'bg-gray-100 text-gray-800';
                            break;
                    }
                    
                    // בניית תצוגת פרטי התור
                    detailsContainer.innerHTML = `
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <div>
                                <h4 class="text-sm text-gray-500 mb-1">לקוח</h4>
                                <p class="font-medium">${appointment.first_name} ${appointment.last_name}</p>
                            </div>
                            
                            <div>
                                <h4 class="text-sm text-gray-500 mb-1">סטטוס</h4>
                                <span class="px-3 py-1 ${statusClass} rounded-full text-sm">${statusText}</span>
                            </div>
                            
                            <div>
                                <h4 class="text-sm text-gray-500 mb-1">תאריך</h4>
                                <p>${date}</p>
                            </div>
                            
                            <div>
                                <h4 class="text-sm text-gray-500 mb-1">שעה</h4>
                                <p>${startTime} - ${endTime}</p>
                            </div>
                        </div>
                    `;
                    
                    // שליפת פרטי השירות ונותן השירות
                    fetch(`api/get_service.php?service_id=${appointment.service_id}`)
                        .then(response => response.json())
                        .then(serviceData => {
                            if (serviceData.success) {
                                const service = serviceData.service;
                                
                                // הוספת פרטי השירות
                                const serviceDiv = document.createElement('div');
                                serviceDiv.className = 'mb-6';
                                serviceDiv.innerHTML = `
                                    <h4 class="text-sm text-gray-500 mb-1">שירות</h4>
                                    <p class="font-medium">${service.name}</p>
                                    <p class="text-sm text-gray-500 mt-1">משך: ${service.duration} דקות</p>
                                    <p class="text-sm text-gray-500">מחיר: ₪${service.price}</p>
                                `;
                                detailsContainer.appendChild(serviceDiv);
                                
                                fetch(`api/get_staff.php?id=${appointment.staff_id}`)
                                    .then(response => response.json())
                                    .then(staffData => {
                                        if (staffData.success) {
                                            const staff = staffData.staff;
                                            
                                            // הוספת פרטי נותן השירות
                                            const staffDiv = document.createElement('div');
                                            staffDiv.className = 'mb-6';
                                            staffDiv.innerHTML = `
                                                <h4 class="text-sm text-gray-500 mb-1">נותן שירות</h4>
                                                <p class="font-medium">${staff.name}</p>
                                            `;
                                            detailsContainer.appendChild(staffDiv);
                                            
                                            // הוספת הערות אם יש
                                            if (appointment.notes) {
                                                const notesDiv = document.createElement('div');
                                                notesDiv.className = 'mb-6';
                                                notesDiv.innerHTML = `
                                                    <h4 class="text-sm text-gray-500 mb-1">הערות</h4>
                                                    <p class="text-sm bg-gray-50 p-3 rounded-lg">${appointment.notes}</p>
                                                `;
                                                detailsContainer.appendChild(notesDiv);
                                            }
                                            
                                            // שליפת תשובות לשאלות מקדימות אם יש
                                            if (data.intake_answers && data.intake_answers.length > 0) {
                                                const answersDiv = document.createElement('div');
                                                answersDiv.className = 'mb-6';
                                                answersDiv.innerHTML = `
                                                    <h4 class="text-sm text-gray-500 mb-2">תשובות לשאלות מקדימות</h4>
                                                    <div class="bg-gray-50 p-4 rounded-lg">
                                                        ${data.intake_answers.map(answer => `
                                                            <div class="mb-3">
                                                                <p class="text-sm font-medium">${answer.question_text}</p>
                                                                <p class="text-sm">${answer.answer_text}</p>
                                                            </div>
                                                        `).join('')}
                                                    </div>
                                                `;
                                                detailsContainer.appendChild(answersDiv);
                                            }
                                        }
                                    });
                            }
                        });
                    
                    // הצגת כפתורי עריכה ומחיקה
                    editBtn.classList.remove('hidden');
                    deleteBtn.classList.remove('hidden');
                    
                    // הוספת אירועים לכפתורים
                    editBtn.onclick = function() {
                        // TODO: יצירת טופס עריכה 
                        alert('עריכת תור ' + appointmentId);
                    };
                    
                    deleteBtn.onclick = function() {
                        if (confirm('האם אתה בטוח שברצונך למחוק את התור?')) {
                            deleteAppointment(appointmentId);
                        }
                    };
                    
                } else {
                    detailsContainer.innerHTML = `
                        <div class="text-center text-red-500 p-4">
                            <p>שגיאה בטעינת פרטי התור</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                detailsContainer.innerHTML = `
                    <div class="text-center text-red-500 p-4">
                        <p>שגיאה בטעינת פרטי התור: ${error.message}</p>
                    </div>
                `;
            });
    }
    
    // פונקציית מחיקת תור
    function deleteAppointment(appointmentId) {
        fetch('api/delete_appointment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                appointment_id: appointmentId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // סגירת המודל וטעינת התורים מחדש
                document.getElementById('appointmentModal').classList.add('hidden');
                loadAppointments();
                
                // הצגת הודעת הצלחה
                alert('התור נמחק בהצלחה');
            } else {
                alert('שגיאה במחיקת התור: ' + data.message);
            }
        })
        .catch(error => {
            alert('שגיאה בשליחת הבקשה: ' + error.message);
        });
    }
    
    // פונקציית טעינת לקוחות לטופס הוספת תור
    function loadCustomers() {
        const select = document.getElementById('appointmentCustomer');
        
        fetch('api/get_customers.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    select.innerHTML = '<option value="">בחר לקוח</option>';
                    
                    data.customers.forEach(customer => {
                        const option = document.createElement('option');
                        option.value = customer.customer_id;
                        option.textContent = `${customer.first_name} ${customer.last_name} - ${customer.phone}`;
                        select.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('שגיאה בטעינת לקוחות:', error);
            });
    }
    
    // פונקציית טעינת שירותים לטופס הוספת תור
    function loadServices() {
        const select = document.getElementById('appointmentService');
        
        fetch('api/get_services.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    select.innerHTML = '<option value="">בחר שירות</option>';
                    
                    data.services.forEach(service => {
                        const option = document.createElement('option');
                        option.value = service.service_id;
                        option.textContent = `${service.name} (${service.duration} דק', ₪${service.price})`;
                        select.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('שגיאה בטעינת שירותים:', error);
            });
    }
    
    // פונקציית טעינת נותני שירות לטופס הוספת תור
    function loadStaff() {
        const select = document.getElementById('appointmentStaff');
        
        fetch('api/get_staff_list.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    select.innerHTML = '<option value="">בחר נותן שירות</option>';
                    
                    data.staff.forEach(staff => {
                        const option = document.createElement('option');
                        option.value = staff.staff_id;
                        option.textContent = staff.name;
                        select.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('שגיאה בטעינת נותני שירות:', error);
            });
    }
    
    // פונקציית שמירת תור חדש
    function saveNewAppointment() {
        const form = document.getElementById('appointmentForm');
        const date = document.getElementById('appointmentDate').value;
        const time = document.getElementById('appointmentTime').value;
        const customer_id = document.getElementById('appointmentCustomer').value;
        const service_id = document.getElementById('appointmentService').value;
        const staff_id = document.getElementById('appointmentStaff').value;
        const status = document.getElementById('appointmentStatus').value;
        const notes = document.getElementById('appointmentNotes').value;
        
        // בדיקת תקינות
        if (!date || !time || !customer_id || !service_id || !staff_id) {
            alert('יש למלא את כל השדות הנדרשים');
            return;
        }
        
        // שליחת הבקשה
        fetch('api/add_appointment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                start_datetime: `${date} ${time}:00`,
                customer_id: customer_id,
                service_id: service_id,
                staff_id: staff_id,
                status: status,
                notes: notes
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // סגירת המודל וטעינת התורים מחדש
                document.getElementById('newAppointmentModal').classList.add('hidden');
                loadAppointments();
                
                // איפוס הטופס
                form.reset();
                
                // הצגת הודעת הצלחה
                alert('התור נוסף בהצלחה');
            } else {
                alert('שגיאה בהוספת התור: ' + data.message);
            }
        })
        .catch(error => {
            alert('שגיאה בשליחת הבקשה: ' + error.message);
        });
    }
    
    // טעינת תורים עם טעינת העמוד
    loadAppointments();
    
    // אירועים לכפתורי סגירת מודלים
    document.getElementById('closeAppointmentModal').addEventListener('click', function() {
        document.getElementById('appointmentModal').classList.add('hidden');
    });
    
    document.getElementById('closeModalBtn').addEventListener('click', function() {
        document.getElementById('appointmentModal').classList.add('hidden');
    });
    
    document.getElementById('closeNewAppointmentModal').addEventListener('click', function() {
        document.getElementById('newAppointmentModal').classList.add('hidden');
    });
    
    document.getElementById('cancelNewAppointmentBtn').addEventListener('click', function() {
        document.getElementById('newAppointmentModal').classList.add('hidden');
    });
    
    // אירוע לכפתור הוספת תור
    document.getElementById('addAppointmentBtn').addEventListener('click', function() {
        // טעינת נתונים לטופס
        loadCustomers();
        loadServices();
        loadStaff();
        
        // איפוס הטופס
        document.getElementById('appointmentForm').reset();
        
        // הגדרת תאריך ברירת מחדל (היום)
        document.getElementById('appointmentDate').value = new Date().toISOString().split('T')[0];
        
        // הצגת המודל
        document.getElementById('newAppointmentModal').classList.remove('hidden');
    });
    
    // אירוע לשמירת תור חדש
    document.getElementById('saveAppointmentBtn').addEventListener('click', saveNewAppointment);
    
    // אירוע לכפתור "היום"
    document.getElementById('todayButton').addEventListener('click', function() {
        window.location.href = `appointments.php?month=<?php echo date('n'); ?>&year=<?php echo date('Y'); ?>&staff=<?php echo $staff_filter; ?>&service=<?php echo $service_filter; ?>&status=<?php echo $status_filter; ?>`;
    });
    
    // סגירת המודל בלחיצה מחוץ לתוכן
    window.addEventListener('click', function(event) {
        const appointmentModal = document.getElementById('appointmentModal');
        const newAppointmentModal = document.getElementById('newAppointmentModal');
        
        if (event.target === appointmentModal) {
            appointmentModal.classList.add('hidden');
        }
        
        if (event.target === newAppointmentModal) {
            newAppointmentModal.classList.add('hidden');
        }
    });
});
</script>

<?php include_once "includes/footer.php"; ?>