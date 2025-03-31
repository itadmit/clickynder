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

// קבלת פרמטרים מה-URL
$report_type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : 'summary';
$start_date = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date']) : date('Y-m-01'); // תחילת החודש הנוכחי
$end_date = isset($_GET['end_date']) ? sanitizeInput($_GET['end_date']) : date('Y-m-t'); // סוף החודש הנוכחי
$staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : 0;
$service_id = isset($_GET['service_id']) ? intval($_GET['service_id']) : 0;

// קבלת רשימת נותני שירות לפילטור
$query = "SELECT staff_id, name FROM staff WHERE tenant_id = :tenant_id AND is_active = 1 ORDER BY name ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(":tenant_id", $tenant_id);
$stmt->execute();
$staff_members = $stmt->fetchAll();

// קבלת רשימת שירותים לפילטור
$query = "SELECT service_id, name FROM services WHERE tenant_id = :tenant_id AND is_active = 1 ORDER BY name ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(":tenant_id", $tenant_id);
$stmt->execute();
$services = $stmt->fetchAll();

// הכנת פרמטרים לשאילתה
$where_clause = "WHERE a.tenant_id = :tenant_id";
$params = [":tenant_id" => $tenant_id];

if (!empty($start_date)) {
    $where_clause .= " AND DATE(a.start_datetime) >= :start_date";
    $params[":start_date"] = $start_date;
}

if (!empty($end_date)) {
    $where_clause .= " AND DATE(a.start_datetime) <= :end_date";
    $params[":end_date"] = $end_date;
}

if ($staff_id > 0) {
    $where_clause .= " AND a.staff_id = :staff_id";
    $params[":staff_id"] = $staff_id;
}

if ($service_id > 0) {
    $where_clause .= " AND a.service_id = :service_id";
    $params[":service_id"] = $service_id;
}

// נתונים לדוח סיכום
if ($report_type == 'summary' || $report_type == 'income') {
    // סך כל התורים בתקופה הנבחרת
    $query = "SELECT COUNT(*) as total_appointments FROM appointments a $where_clause";
    $stmt = $db->prepare($query);
    foreach ($params as $param => $value) {
        $stmt->bindValue($param, $value);
    }
    $stmt->execute();
    $total_appointments = $stmt->fetch()['total_appointments'];
    
    // חלוקה לפי סטטוס
    $query = "SELECT status, COUNT(*) as count FROM appointments a $where_clause GROUP BY status";
    $stmt = $db->prepare($query);
    foreach ($params as $param => $value) {
        $stmt->bindValue($param, $value);
    }
    $stmt->execute();
    $status_counts = [];
    while ($row = $stmt->fetch()) {
        $status_counts[$row['status']] = $row['count'];
    }
    
    // סטטיסטיקה כספית (רק מתורים שהושלמו או מאושרים)
    $financial_where = $where_clause . " AND a.status IN ('completed', 'confirmed')";
    $query = "SELECT COUNT(*) as count, SUM(s.price) as total_income 
              FROM appointments a 
              JOIN services s ON a.service_id = s.service_id 
              $financial_where";
    $stmt = $db->prepare($query);
    foreach ($params as $param => $value) {
        $stmt->bindValue($param, $value);
    }
    $stmt->execute();
    $financial = $stmt->fetch();
    $total_income = $financial['total_income'] ?: 0;
    $completed_count = $financial['count'] ?: 0;
    
    // ממוצע הכנסה פר תור
    $average_income = $completed_count > 0 ? $total_income / $completed_count : 0;
    
    // סטטיסטיקה לפי ימים בשבוע
    $query = "SELECT DAYOFWEEK(start_datetime) as day_of_week, COUNT(*) as count, 
              SUM(CASE WHEN a.status IN ('completed', 'confirmed') THEN s.price ELSE 0 END) as income
              FROM appointments a 
              JOIN services s ON a.service_id = s.service_id 
              $where_clause 
              GROUP BY day_of_week 
              ORDER BY day_of_week ASC";
    $stmt = $db->prepare($query);
    foreach ($params as $param => $value) {
        $stmt->bindValue($param, $value);
    }
    $stmt->execute();
    $day_stats = [];
    while ($row = $stmt->fetch()) {
        // MySQL's DAYOFWEEK returns 1 for Sunday, 2 for Monday, etc.
        // Convert to 0-based index (0 for Sunday)
        $day_index = ($row['day_of_week'] - 1) % 7;
        $day_stats[$day_index] = [
            'count' => $row['count'],
            'income' => $row['income'] ?: 0
        ];
    }
    
    // שירותים פופולריים
    $query = "SELECT s.name, COUNT(*) as count, SUM(s.price) as income
              FROM appointments a 
              JOIN services s ON a.service_id = s.service_id 
              $where_clause 
              GROUP BY a.service_id 
              ORDER BY count DESC 
              LIMIT 10";
    $stmt = $db->prepare($query);
    foreach ($params as $param => $value) {
        $stmt->bindValue($param, $value);
    }
    $stmt->execute();
    $popular_services = $stmt->fetchAll();
    
    // סטטיסטיקה לפי נותני שירות
    $query = "SELECT st.name, COUNT(*) as count, SUM(s.price) as income
              FROM appointments a 
              JOIN services s ON a.service_id = s.service_id 
              JOIN staff st ON a.staff_id = st.staff_id
              $where_clause 
              GROUP BY a.staff_id 
              ORDER BY count DESC";
    $stmt = $db->prepare($query);
    foreach ($params as $param => $value) {
        $stmt->bindValue($param, $value);
    }
    $stmt->execute();
    $staff_stats = $stmt->fetchAll();
}

// נתונים לדוח הכנסות לפי חודשים
if ($report_type == 'income' || $report_type == 'monthly') {
    // הכנסות לפי חודשים
    $query = "SELECT DATE_FORMAT(start_datetime, '%Y-%m') as month, 
              COUNT(*) as count, 
              SUM(CASE WHEN a.status IN ('completed', 'confirmed') THEN s.price ELSE 0 END) as income
              FROM appointments a 
              JOIN services s ON a.service_id = s.service_id 
              $where_clause 
              GROUP BY month 
              ORDER BY month ASC";
    $stmt = $db->prepare($query);
    foreach ($params as $param => $value) {
        $stmt->bindValue($param, $value);
    }
    $stmt->execute();
    $monthly_stats = $stmt->fetchAll();
}

// נתונים לדוח אי-הגעות
if ($report_type == 'no_show') {
    // אחוז אי-הגעות
    $query = "SELECT COUNT(*) as total, 
              SUM(CASE WHEN a.status = 'no_show' THEN 1 ELSE 0 END) as no_show_count,
              SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count
              FROM appointments a 
              $where_clause";
    $stmt = $db->prepare($query);
    foreach ($params as $param => $value) {
        $stmt->bindValue($param, $value);
    }
    $stmt->execute();
    $no_show_stats = $stmt->fetch();
    
    // חישוב אחוזים
    $total_appointments = $no_show_stats['total'] ?: 1; // מניעת חלוקה באפס
    $no_show_percentage = ($no_show_stats['no_show_count'] / $total_appointments) * 100;
    $cancelled_percentage = ($no_show_stats['cancelled_count'] / $total_appointments) * 100;
    
    // נתונים לפי חודשים
    $query = "SELECT DATE_FORMAT(start_datetime, '%Y-%m') as month, 
              COUNT(*) as total, 
              SUM(CASE WHEN a.status = 'no_show' THEN 1 ELSE 0 END) as no_show_count,
              SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count
              FROM appointments a 
              $where_clause 
              GROUP BY month 
              ORDER BY month ASC";
    $stmt = $db->prepare($query);
    foreach ($params as $param => $value) {
        $stmt->bindValue($param, $value);
    }
    $stmt->execute();
    $monthly_no_show = $stmt->fetchAll();
}

// כותרת העמוד
$page_title = "דוחות וסטטיסטיקות";

// שמות הדוחות
$report_names = [
    'summary' => 'סיכום פעילות',
    'income' => 'דוח הכנסות',
    'monthly' => 'ניתוח חודשי',
    'no_show' => 'אי-הגעות וביטולים'
];
?>

<?php include_once "includes/header.php"; ?>
<?php include_once "includes/sidebar.php"; ?>

<!-- Main Content -->
<main class="flex-1 overflow-y-auto py-8">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">דוחות וסטטיסטיקות</h1>
        
        <!-- פילטר וטאבים -->
        <div class="bg-white p-6 rounded-2xl shadow-sm mb-6">
            <!-- טאבים של סוגי דוחות -->
            <div class="flex border-b mb-6 overflow-x-auto">
                <?php foreach ($report_names as $type => $name): ?>
                    <a href="?type=<?php echo $type; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&staff_id=<?php echo $staff_id; ?>&service_id=<?php echo $service_id; ?>" 
                       class="px-4 py-2 border-b-2 whitespace-nowrap <?php echo $report_type == $type ? 'border-primary text-primary font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                        <?php echo $name; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <!-- פילטרים -->
            <form action="reports.php" method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <input type="hidden" name="type" value="<?php echo $report_type; ?>">
                
                <div>
                    <label for="start_date" class="block text-gray-700 font-medium mb-1">מתאריך</label>
                    <input type="date" id="start_date" name="start_date" 
                           value="<?php echo $start_date; ?>"
                           class="w-full px-3 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                </div>
                
                <div>
                    <label for="end_date" class="block text-gray-700 font-medium mb-1">עד תאריך</label>
                    <input type="date" id="end_date" name="end_date" 
                           value="<?php echo $end_date; ?>"
                           class="w-full px-3 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                </div>
                
                <div>
                    <label for="staff_id" class="block text-gray-700 font-medium mb-1">נותן שירות</label>
                    <select id="staff_id" name="staff_id"
                           class="w-full px-3 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                        <option value="0">כל נותני השירות</option>
                        <?php foreach ($staff_members as $staff): ?>
                            <option value="<?php echo $staff['staff_id']; ?>" <?php echo ($staff_id == $staff['staff_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($staff['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="service_id" class="block text-gray-700 font-medium mb-1">שירות</label>
                    <select id="service_id" name="service_id"
                           class="w-full px-3 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                        <option value="0">כל השירותים</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?php echo $service['service_id']; ?>" <?php echo ($service_id == $service['service_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($service['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-primary hover:bg-primary-dark text-white font-bold py-2 px-4 rounded-xl transition duration-300">
                        <i class="fas fa-filter ml-1"></i> סנן
                    </button>
                </div>
            </form>
        </div>
        
        <!-- תוכן הדוח - סיכום פעילות -->
        <?php if ($report_type == 'summary'): ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <!-- קלפי סיכום -->
                <div class="bg-white p-6 rounded-2xl shadow-sm">
                    <h2 class="text-lg font-semibold mb-4">סיכום תורים</h2>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center border-b pb-2">
                            <span class="text-gray-600">סה"כ תורים</span>
                            <span class="text-xl font-bold"><?php echo $total_appointments; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">מאושרים</span>
                            <span class="text-green-600 font-medium"><?php echo $status_counts['confirmed'] ?? 0; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">ממתינים לאישור</span>
                            <span class="text-yellow-600 font-medium"><?php echo $status_counts['pending'] ?? 0; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">הושלמו</span>
                            <span class="text-blue-600 font-medium"><?php echo $status_counts['completed'] ?? 0; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">בוטלו</span>
                            <span class="text-red-600 font-medium"><?php echo $status_counts['cancelled'] ?? 0; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">לא הגיעו</span>
                            <span class="text-gray-600 font-medium"><?php echo $status_counts['no_show'] ?? 0; ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- נתונים כספיים -->
                <div class="bg-white p-6 rounded-2xl shadow-sm">
                    <h2 class="text-lg font-semibold mb-4">סיכום כספי</h2>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center border-b pb-2">
                            <span class="text-gray-600">סה"כ הכנסות</span>
                            <span class="text-xl font-bold"><?php echo number_format($total_income, 0); ?> ₪</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">ממוצע לתור</span>
                            <span class="text-primary font-medium"><?php echo number_format($average_income, 0); ?> ₪</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">תורים ששולמו</span>
                            <span class="text-gray-800 font-medium"><?php echo $completed_count; ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- התפלגות לפי ימים בשבוע -->
                <div class="bg-white p-6 rounded-2xl shadow-sm">
                    <h2 class="text-lg font-semibold mb-4">התפלגות לפי ימים</h2>
                    <?php
                    $days = ['ראשון', 'שני', 'שלישי', 'רביעי', 'חמישי', 'שישי', 'שבת'];
                    $max_count = 1; // מניעת חלוקה באפס
                    
                    // מציאת הערך המקסימלי לנרמול הגרף
                    foreach ($day_stats as $day => $stat) {
                        if ($stat['count'] > $max_count) {
                            $max_count = $stat['count'];
                        }
                    }
                    ?>
                    <div class="space-y-3">
                        <?php for ($i = 0; $i < 7; $i++): ?>
                            <?php
                            $count = isset($day_stats[$i]) ? $day_stats[$i]['count'] : 0;
                            $percentage = ($count / $max_count) * 100;
                            ?>
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-gray-700"><?php echo $days[$i]; ?></span>
                                    <span class="text-gray-500"><?php echo $count; ?> תורים</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-primary h-2 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- שירותים פופולריים -->
                <div class="bg-white p-6 rounded-2xl shadow-sm">
                    <h2 class="text-lg font-semibold mb-4">שירותים פופולריים</h2>
                    <?php if (empty($popular_services)): ?>
                        <div class="text-center py-8 bg-gray-50 rounded-xl">
                            <div class="text-gray-400 mb-2"><i class="fas fa-chart-bar text-5xl"></i></div>
                            <p class="text-gray-500">אין מספיק נתונים להצגה</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">שירות</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">כמות תורים</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">הכנסות</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach ($popular_services as $service): ?>
                                        <tr>
                                            <td class="px-4 py-2 whitespace-nowrap"><?php echo htmlspecialchars($service['name']); ?></td>
                                            <td class="px-4 py-2 whitespace-nowrap"><?php echo $service['count']; ?></td>
                                            <td class="px-4 py-2 whitespace-nowrap"><?php echo number_format($service['income'], 0); ?> ₪</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- סטטיסטיקה לפי נותני שירות -->
                <div class="bg-white p-6 rounded-2xl shadow-sm">
                    <h2 class="text-lg font-semibold mb-4">ביצועי צוות</h2>
                    <?php if (empty($staff_stats)): ?>
                        <div class="text-center py-8 bg-gray-50 rounded-xl">
                            <div class="text-gray-400 mb-2"><i class="fas fa-user-tie text-5xl"></i></div>
                            <p class="text-gray-500">אין מספיק נתונים להצגה</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">נותן שירות</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">כמות תורים</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">הכנסות</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach ($staff_stats as $staff): ?>
                                        <tr>
                                            <td class="px-4 py-2 whitespace-nowrap"><?php echo htmlspecialchars($staff['name']); ?></td>
                                            <td class="px-4 py-2 whitespace-nowrap"><?php echo $staff['count']; ?></td>
                                            <td class="px-4 py-2 whitespace-nowrap"><?php echo number_format($staff['income'], 0); ?> ₪</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- תוכן הדוח - דוח הכנסות -->
        <?php if ($report_type == 'income'): ?>
            <div class="grid grid-cols-1 gap-6">
                <!-- גרף הכנסות חודשי -->
                <div class="bg-white p-6 rounded-2xl shadow-sm">
                    <h2 class="text-lg font-semibold mb-4">הכנסות חודשיות</h2>
                    <div class="h-80">
                        <canvas id="incomeChart"></canvas>
                    </div>
                </div>
                
                <!-- טבלת הכנסות חודשית -->
                <div class="bg-white p-6 rounded-2xl shadow-sm">
                    <h2 class="text-lg font-semibold mb-4">פירוט הכנסות חודשיות</h2>
                    <?php if (empty($monthly_stats)): ?>
                        <div class="text-center py-8 bg-gray-50 rounded-xl">
                            <div class="text-gray-400 mb-2"><i class="fas fa-chart-line text-5xl"></i></div>
                            <p class="text-gray-500">אין מספיק נתונים להצגה</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">חודש</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">כמות תורים</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">הכנסות</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">ממוצע לתור</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach ($monthly_stats as $month): 
                                        $month_display = date('m/Y', strtotime($month['month'] . '-01'));
                                        $average = $month['count'] > 0 ? $month['income'] / $month['count'] : 0;
                                    ?>
                                        <tr>
                                            <td class="px-4 py-2 whitespace-nowrap"><?php echo $month_display; ?></td>
                                            <td class="px-4 py-2 whitespace-nowrap"><?php echo $month['count']; ?></td>
                                            <td class="px-4 py-2 whitespace-nowrap"><?php echo number_format($month['income'], 0); ?> ₪</td>
                                            <td class="px-4 py-2 whitespace-nowrap"><?php echo number_format($average, 0); ?> ₪</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-right">סה"כ</th>
                                        <th class="px-4 py-2 text-right">
                                            <?php
                                            $total_count = array_sum(array_column($monthly_stats, 'count'));
                                            echo $total_count;
                                            ?>
                                        </th>
                                        <th class="px-4 py-2 text-right">
                                            <?php
                                            $total_income = array_sum(array_column($monthly_stats, 'income'));
                                            echo number_format($total_income, 0) . ' ₪';
                                            ?>
                                        </th>
                                        <th class="px-4 py-2 text-right">
                                            <?php
                                            $overall_average = $total_count > 0 ? $total_income / $total_count : 0;
                                            echo number_format($overall_average, 0) . ' ₪';
                                            ?>
                                        </th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- תוכן הדוח - אי-הגעות וביטולים -->
        <?php if ($report_type == 'no_show'): ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <!-- סיכום כללי -->
                <div class="bg-white p-6 rounded-2xl shadow-sm">
                    <h2 class="text-lg font-semibold mb-4">סיכום אי-הגעות וביטולים</h2>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center border-b pb-2">
                            <span class="text-gray-600">סה"כ תורים</span>
                            <span class="text-xl font-bold"><?php echo $no_show_stats['total']; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">לא הגיעו</span>
                            <span class="text-red-600 font-medium">
                                <?php echo $no_show_stats['no_show_count']; ?> 
                                (<?php echo number_format($no_show_percentage, 1); ?>%)
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">ביטולים</span>
                            <span class="text-yellow-600 font-medium">
                                <?php echo $no_show_stats['cancelled_count']; ?> 
                                (<?php echo number_format($cancelled_percentage, 1); ?>%)
                            </span>
                        </div>
                    </div>
                    
                    <!-- תרשים עוגה -->
                    <div class="mt-6 h-40">
                        <canvas id="noShowPieChart"></canvas>
                    </div>
                </div>
                
                <!-- גרף חודשי -->
                <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-sm">
                    <h2 class="text-lg font-semibold mb-4">מגמות חודשיות</h2>
                    <div class="h-60">
                        <canvas id="noShowMonthlyChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- טבלת אי-הגעות לפי חודשים -->
            <div class="bg-white p-6 rounded-2xl shadow-sm">
                <h2 class="text-lg font-semibold mb-4">פירוט חודשי</h2>
                <?php if (empty($monthly_no_show)): ?>
                    <div class="text-center py-8 bg-gray-50 rounded-xl">
                        <div class="text-gray-400 mb-2"><i class="fas fa-chart-line text-5xl"></i></div>
                        <p class="text-gray-500">אין מספיק נתונים להצגה</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">חודש</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">סה"כ תורים</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">לא הגיעו</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">אחוז אי-הגעה</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">ביטולים</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">אחוז ביטולים</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($monthly_no_show as $month): 
                                    $month_display = date('m/Y', strtotime($month['month'] . '-01'));
                                    $no_show_pct = $month['total'] > 0 ? ($month['no_show_count'] / $month['total']) * 100 : 0;
                                    $cancelled_pct = $month['total'] > 0 ? ($month['cancelled_count'] / $month['total']) * 100 : 0;
                                ?>
                                    <tr>
                                        <td class="px-4 py-2 whitespace-nowrap"><?php echo $month_display; ?></td>
                                        <td class="px-4 py-2 whitespace-nowrap"><?php echo $month['total']; ?></td>
                                        <td class="px-4 py-2 whitespace-nowrap"><?php echo $month['no_show_count']; ?></td>
                                        <td class="px-4 py-2 whitespace-nowrap"><?php echo number_format($no_show_pct, 1); ?>%</td>
                                        <td class="px-4 py-2 whitespace-nowrap"><?php echo $month['cancelled_count']; ?></td>
                                        <td class="px-4 py-2 whitespace-nowrap"><?php echo number_format($cancelled_pct, 1); ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- תוכן הדוח - ניתוח חודשי -->
        <?php if ($report_type == 'monthly'): ?>
            <div class="bg-white p-6 rounded-2xl shadow-sm">
                <h2 class="text-lg font-semibold mb-4">השוואת חודשים</h2>
                <div class="h-80">
                    <canvas id="monthlyComparisonChart"></canvas>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    <?php if ($report_type == 'income'): ?>
    // גרף הכנסות חודשי
    var incomeCtx = document.getElementById('incomeChart').getContext('2d');
    
    var incomeData = <?php 
        $months = [];
        $incomes = [];
        $counts = [];
        
        foreach ($monthly_stats as $month) {
            $months[] = date('m/Y', strtotime($month['month'] . '-01'));
            $incomes[] = $month['income'];
            $counts[] = $month['count'];
        }
        
        echo json_encode([
            'months' => $months,
            'incomes' => $incomes,
            'counts' => $counts
        ]);
    ?>;
    
    new Chart(incomeCtx, {
        type: 'bar',
        data: {
            labels: incomeData.months,
            datasets: [
                {
                    label: 'הכנסות (₪)',
                    data: incomeData.incomes,
                    backgroundColor: '#ec4899',
                    borderColor: '#ec4899',
                    borderWidth: 1,
                    order: 1
                },
                {
                    label: 'מספר תורים',
                    data: incomeData.counts,
                    type: 'line',
                    backgroundColor: 'rgba(59, 130, 246, 0.5)',
                    borderColor: '#3b82f6',
                    borderWidth: 2,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#3b82f6',
                    pointRadius: 4,
                    tension: 0.2,
                    order: 0
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        display: true,
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString() + ' ₪';
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                    rtl: true,
                    labels: {
                        font: {
                            family: "'Noto Sans Hebrew', sans-serif"
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.7)',
                    padding: 10,
                    titleFont: {
                        size: 14,
                        family: "'Noto Sans Hebrew', sans-serif"
                    },
                    bodyFont: {
                        size: 14,
                        family: "'Noto Sans Hebrew', sans-serif"
                    },
                    rtl: true,
                    titleAlign: 'right',
                    bodyAlign: 'right',
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                if (context.dataset.label === 'הכנסות (₪)') {
                                    label += context.parsed.y.toLocaleString() + ' ₪';
                                } else {
                                    label += context.parsed.y;
                                }
                            }
                            return label;
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
    
    <?php if ($report_type == 'no_show'): ?>
    // גרף עוגה לאי-הגעות
    var pieCtx = document.getElementById('noShowPieChart').getContext('2d');
    
    new Chart(pieCtx, {
        type: 'pie',
        data: {
            labels: ['הגיעו', 'לא הגיעו', 'ביטלו'],
            datasets: [{
                data: [
                    <?php 
                        $attended = $no_show_stats['total'] - $no_show_stats['no_show_count'] - $no_show_stats['cancelled_count']; 
                        echo $attended . ', ' . $no_show_stats['no_show_count'] . ', ' . $no_show_stats['cancelled_count'];
                    ?>
                ],
                backgroundColor: [
                    '#10b981', // ירוק - הגיעו
                    '#ef4444', // אדום - לא הגיעו
                    '#f59e0b'  // כתום - ביטלו
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    rtl: true,
                    labels: {
                        font: {
                            family: "'Noto Sans Hebrew', sans-serif"
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.7)',
                    rtl: true,
                    titleAlign: 'right',
                    bodyAlign: 'right',
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.raw !== null) {
                                label += context.raw + ' תורים';
                                label += ' (' + (context.raw / <?php echo $no_show_stats['total']; ?> * 100).toFixed(1) + '%)';
                            }
                            return label;
                        }
                    }
                }
            }
        }
    });
    
    // גרף חודשי לאי-הגעות
    var monthlyCtx = document.getElementById('noShowMonthlyChart').getContext('2d');
    
    var monthlyData = <?php 
        $months = [];
        $no_show_percents = [];
        $cancelled_percents = [];
        
        foreach ($monthly_no_show as $month) {
            $months[] = date('m/Y', strtotime($month['month'] . '-01'));
            $no_show_pct = $month['total'] > 0 ? ($month['no_show_count'] / $month['total']) * 100 : 0;
            $cancelled_pct = $month['total'] > 0 ? ($month['cancelled_count'] / $month['total']) * 100 : 0;
            $no_show_percents[] = round($no_show_pct, 1);
            $cancelled_percents[] = round($cancelled_pct, 1);
        }
        
        echo json_encode([
            'months' => $months,
            'no_show' => $no_show_percents,
            'cancelled' => $cancelled_percents
        ]);
    ?>;
    
    new Chart(monthlyCtx, {
        type: 'line',
        data: {
            labels: monthlyData.months,
            datasets: [
                {
                    label: 'אחוז אי-הגעות',
                    data: monthlyData.no_show,
                    backgroundColor: 'rgba(239, 68, 68, 0.2)',
                    borderColor: '#ef4444',
                    borderWidth: 2,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#ef4444',
                    pointRadius: 4,
                    tension: 0.2,
                    fill: true
                },
                {
                    label: 'אחוז ביטולים',
                    data: monthlyData.cancelled,
                    backgroundColor: 'rgba(245, 158, 11, 0.2)',
                    borderColor: '#f59e0b',
                    borderWidth: 2,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#f59e0b',
                    pointRadius: 4,
                    tension: 0.2,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        display: true,
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                    rtl: true,
                    labels: {
                        font: {
                            family: "'Noto Sans Hebrew', sans-serif"
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.7)',
                    rtl: true,
                    titleAlign: 'right',
                    bodyAlign: 'right',
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                label += context.parsed.y + '%';
                            }
                            return label;
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
    
    <?php if ($report_type == 'monthly'): ?>
    // גרף השוואת חודשים
    var monthlyComparisonCtx = document.getElementById('monthlyComparisonChart').getContext('2d');
    
    var monthlyComparisonData = <?php 
        $months = [];
        $appointments = [];
        $incomes = [];
        
        foreach ($monthly_stats as $month) {
            $months[] = date('m/Y', strtotime($month['month'] . '-01'));
            $appointments[] = $month['count'];
            $incomes[] = $month['income'];
        }
        
        echo json_encode([
            'months' => $months,
            'appointments' => $appointments,
            'incomes' => $incomes
        ]);
    ?>;
    
    new Chart(monthlyComparisonCtx, {
        type: 'bar',
        data: {
            labels: monthlyComparisonData.months,
            datasets: [
                {
                    label: 'מספר תורים',
                    data: monthlyComparisonData.appointments,
                    backgroundColor: '#ec4899',
                    borderColor: '#ec4899',
                    borderWidth: 1,
                    yAxisID: 'y',
                    order: 1
                },
                {
                    label: 'הכנסות (₪)',
                    data: monthlyComparisonData.incomes,
                    type: 'line',
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: '#3b82f6',
                    borderWidth: 2,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#3b82f6',
                    pointRadius: 4,
                    tension: 0.2,
                    yAxisID: 'y1',
                    order: 0
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'מספר תורים',
                        color: '#ec4899',
                        font: {
                            family: "'Noto Sans Hebrew', sans-serif",
                            weight: 'bold'
                        }
                    },
                    grid: {
                        display: false
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'הכנסות (₪)',
                        color: '#3b82f6',
                        font: {
                            family: "'Noto Sans Hebrew', sans-serif",
                            weight: 'bold'
                        }
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString() + ' ₪';
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                    rtl: true,
                    labels: {
                        font: {
                            family: "'Noto Sans Hebrew', sans-serif"
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.7)',
                    rtl: true,
                    titleAlign: 'right',
                    bodyAlign: 'right',
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                if (context.dataset.yAxisID === 'y1') {
                                    label += context.parsed.y.toLocaleString() + ' ₪';
                                } else {
                                    label += context.parsed.y;
                                }
                            }
                            return label;
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
});
</script>

<?php include_once "includes/footer.php"; ?><?php
