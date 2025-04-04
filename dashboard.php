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

// 1. קבלת מספר התורים להיום
$today = date('Y-m-d');
$query = "SELECT COUNT(*) as count FROM appointments 
          WHERE tenant_id = :tenant_id 
          AND DATE(start_datetime) = :today
          AND status IN ('pending', 'confirmed')";
$stmt = $db->prepare($query);
$stmt->bindParam(":tenant_id", $tenant_id);
$stmt->bindParam(":today", $today);
$stmt->execute();
$today_appointments_count = $stmt->fetch()['count'];

// 2. קבלת מספר התורים לשבוע הקרוב
$week_start = date('Y-m-d');
$week_end = date('Y-m-d', strtotime('+7 days'));
$query = "SELECT COUNT(*) as count FROM appointments 
          WHERE tenant_id = :tenant_id 
          AND DATE(start_datetime) BETWEEN :week_start AND :week_end
          AND status IN ('pending', 'confirmed')";
$stmt = $db->prepare($query);
$stmt->bindParam(":tenant_id", $tenant_id);
$stmt->bindParam(":week_start", $week_start);
$stmt->bindParam(":week_end", $week_end);
$stmt->execute();
$week_appointments_count = $stmt->fetch()['count'];

// 3. קבלת מספר התורים המחכים לאישור
$query = "SELECT COUNT(*) as count FROM appointments 
          WHERE tenant_id = :tenant_id AND status = 'pending'";
$stmt = $db->prepare($query);
$stmt->bindParam(":tenant_id", $tenant_id);
$stmt->execute();
$pending_appointments_count = $stmt->fetch()['count'];

// 4. קבלת מספר הלקוחות הכולל
$query = "SELECT COUNT(*) as count FROM customers WHERE tenant_id = :tenant_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":tenant_id", $tenant_id);
$stmt->execute();
$customers_count = $stmt->fetch()['count'];

// 5. חישוב סיכום הכנסות לחודש הנוכחי
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$query = "SELECT COUNT(*) as count, SUM(s.price) as income 
          FROM appointments a
          JOIN services s ON a.service_id = s.service_id
          WHERE a.tenant_id = :tenant_id 
          AND DATE(a.start_datetime) BETWEEN :month_start AND :month_end
          AND a.status IN ('completed', 'confirmed')";
$stmt = $db->prepare($query);
$stmt->bindParam(":tenant_id", $tenant_id);
$stmt->bindParam(":month_start", $month_start);
$stmt->bindParam(":month_end", $month_end);
$stmt->execute();
$monthly_stats = $stmt->fetch();
$monthly_income = $monthly_stats['income'] ?: 0;
$monthly_completed_count = $monthly_stats['count'] ?: 0;

// 6. קבלת סטטיסטיקה יומית לשבוע האחרון (לגרף)
$days_back = 7;
$start_date = date('Y-m-d', strtotime("-$days_back days"));
$query = "SELECT DATE(start_datetime) as date, COUNT(*) as count
          FROM appointments 
          WHERE tenant_id = :tenant_id 
          AND DATE(start_datetime) BETWEEN :start_date AND :today
          GROUP BY DATE(start_datetime)
          ORDER BY date ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(":tenant_id", $tenant_id);
$stmt->bindParam(":start_date", $start_date);
$stmt->bindParam(":today", $today);
$stmt->execute();
$daily_stats = $stmt->fetchAll();

// יצירת מערך עם כל הימים בטווח, כולל ימים ללא תורים
$daily_data = [];
for ($i = $days_back; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i days"));
    $daily_data[$day] = 0;
}
foreach ($daily_stats as $stat) {
    $daily_data[$stat['date']] = intval($stat['count']);
}

// 7. קבלת התורים הקרובים להיום
$query = "SELECT a.*, 
          CONCAT(c.first_name, ' ', c.last_name) as customer_name,
          c.phone as customer_phone,
          s.name as service_name,
          st.name as staff_name
          FROM appointments a
          JOIN customers c ON a.customer_id = c.customer_id
          JOIN services s ON a.service_id = s.service_id
          JOIN staff st ON a.staff_id = st.staff_id
          WHERE a.tenant_id = :tenant_id 
          AND DATE(a.start_datetime) = :today
          AND a.status IN ('pending', 'confirmed')
          ORDER BY a.start_datetime ASC
          LIMIT 5";
$stmt = $db->prepare($query);
$stmt->bindParam(":tenant_id", $tenant_id);
$stmt->bindParam(":today", $today);
$stmt->execute();
$today_appointments = $stmt->fetchAll();

// 8. קבלת השירותים הפופולריים ביותר
$query = "SELECT s.name, COUNT(*) as count
          FROM appointments a
          JOIN services s ON a.service_id = s.service_id
          WHERE a.tenant_id = :tenant_id 
          AND a.status IN ('completed', 'confirmed')
          GROUP BY a.service_id
          ORDER BY count DESC
          LIMIT 5";
$stmt = $db->prepare($query);
$stmt->bindParam(":tenant_id", $tenant_id);
$stmt->execute();
$popular_services = $stmt->fetchAll();

// 9. סטטיסטיקה לפי נותני שירות
$query = "SELECT st.name, COUNT(*) as count
          FROM appointments a
          JOIN staff st ON a.staff_id = st.staff_id
          WHERE a.tenant_id = :tenant_id 
          AND a.status IN ('completed', 'confirmed')
          GROUP BY a.staff_id
          ORDER BY count DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(":tenant_id", $tenant_id);
$stmt->execute();
$staff_stats = $stmt->fetchAll();

// שליפת כמות הפריטים ברשימת ההמתנה
$query = "
    SELECT COUNT(*) as waitlist_count 
    FROM waitlist 
    WHERE tenant_id = :tenant_id AND status = 'waiting'
";
$stmt = $db->prepare($query);
$stmt->bindParam(":tenant_id", $tenant_id);
$stmt->execute();
$waitlist_count = $stmt->fetch()['waitlist_count'];

// הוספה לנתוני הדף
$sample_data['waitlist_count'] = $waitlist_count;



// כותרת העמוד
$page_title = "לוח בקרה";
?>

<?php include_once "includes/header.php"; ?>
<?php include_once "includes/sidebar.php"; ?>

<!-- Main Content -->
<main class="flex-1 overflow-y-auto py-8">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">לוח בקרה</h1>
        
        <!-- קלפי סטטיסטיקה -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <!-- תורים להיום -->
            <div class="bg-white p-6 rounded-2xl shadow-sm">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-pastel-blue text-secondary-dark">
                        <i class="fas fa-calendar-day text-xl"></i>
                    </div>
                    <div class="mr-4">
                        <h2 class="text-gray-500 text-sm">תורים להיום</h2>
                        <div class="flex items-center">
                            <span class="text-2xl font-bold text-gray-800"><?php echo $today_appointments_count; ?></span>
                        </div>
                    </div>
                </div>
                <a href="appointments.php?date=<?php echo $today; ?>" class="text-sm text-primary font-medium hover:underline block mt-2">צפה בכל התורים של היום</a>
            </div>
            
            <!-- <div class="bg-white p-6 rounded-2xl shadow-sm">
    <div class="flex justify-between items-start">
        <div>
            <p class="text-gray-500 text-sm mb-1">רשימת המתנה</p>
            <h3 class="text-3xl font-bold"><?php echo $sample_data['waitlist_count']; ?></h3>
        </div>
        <div class="bg-pastel-orange p-3 rounded-xl">
            <i class="fas fa-clipboard-list text-orange-600"></i>
        </div>
    </div>
    <div class="mt-4">
        <a href="waitlist.php" class="text-primary hover:underline flex items-center text-sm">
            <span>צפה ברשימת המתנה</span>
            <i class="fas fa-arrow-left mr-1"></i>
        </a>
    </div>
</div> -->

            <!-- תורים לשבוע הקרוב -->
            <div class="bg-white p-6 rounded-2xl shadow-sm">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-pastel-green text-green-700">
                        <i class="fas fa-calendar-week text-xl"></i>
                    </div>
                    <div class="mr-4">
                        <h2 class="text-gray-500 text-sm">תורים לשבוע הקרוב</h2>
                        <div class="flex items-center">
                            <span class="text-2xl font-bold text-gray-800"><?php echo $week_appointments_count; ?></span>
                        </div>
                    </div>
                </div>
                <a href="appointments.php?view=calendar" class="text-sm text-primary font-medium hover:underline block mt-2">צפה ביומן</a>
            </div>
            
            <!-- הכנסות חודשיות -->
            <div class="bg-white p-6 rounded-2xl shadow-sm">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-pastel-purple text-purple-700">
                        <i class="fas fa-shekel-sign text-xl"></i>
                    </div>
                    <div class="mr-4">
                        <h2 class="text-gray-500 text-sm">הכנסות החודש</h2>
                        <div class="flex items-center">
                            <span class="text-2xl font-bold text-gray-800"><?php echo number_format($monthly_income, 0); ?> ₪</span>
                        </div>
                    </div>
                </div>
                <a href="reports.php?type=income" class="text-sm text-primary font-medium hover:underline block mt-2">צפה בדוחות הכנסה</a>
            </div>
            
            <!-- לקוחות כולל -->
            <div class="bg-white p-6 rounded-2xl shadow-sm">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-pastel-orange text-orange-700">
                        <i class="fas fa-users text-xl"></i>
                    </div>
                    <div class="mr-4">
                        <h2 class="text-gray-500 text-sm">לקוחות רשומים</h2>
                        <div class="flex items-center">
                            <span class="text-2xl font-bold text-gray-800"><?php echo $customers_count; ?></span>
                        </div>
                    </div>
                </div>
                <a href="customers.php" class="text-sm text-primary font-medium hover:underline block mt-2">ניהול לקוחות</a>
            </div>
        </div>
        
        <!-- גרף סטטיסטיקה והתראות -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- גרף סטטיסטיקה -->
            <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-sm">
                <h2 class="text-lg font-semibold mb-4">פעילות בשבוע האחרון</h2>
                <div class="h-80">
                    <canvas id="appointmentsChart"></canvas>
                </div>
            </div>
            
            <!-- התראות וממתינים לאישור -->
            <div class="bg-white p-6 rounded-2xl shadow-sm">
                <h2 class="text-lg font-semibold mb-4">התראות</h2>
                
                <?php if ($pending_appointments_count > 0): ?>
                    <div class="bg-yellow-50 border-r-4 border-yellow-400 p-4 mb-4 rounded">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-clock text-yellow-400"></i>
                            </div>
                            <div class="mr-3">
                                <p class="font-medium">יש <?php echo $pending_appointments_count; ?> תורים ממתינים לאישור</p>
                                <a href="appointments.php?status=pending" class="text-sm text-primary hover:underline">צפה בתורים</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="mb-4">
                    <a href="appointments.php?new=1" class="block w-full bg-primary hover:bg-primary-dark text-white text-center py-2 px-4 rounded-xl transition duration-300">
                        <i class="fas fa-plus ml-1"></i> קבע תור חדש
                    </a>
                </div>
                
                <h3 class="font-medium text-gray-700 mb-2 mt-6">קישורים מהירים</h3>
                <div class="space-y-2">
                    <a href="settings.php" class="block hover:bg-gray-100 p-2 rounded">
                        <i class="fas fa-cog text-gray-500 ml-2"></i> הגדרות
                    </a>
                    <a href="staff.php" class="block hover:bg-gray-100 p-2 rounded">
                        <i class="fas fa-user-tie text-gray-500 ml-2"></i> ניהול צוות
                    </a>
                    <a href="services.php" class="block hover:bg-gray-100 p-2 rounded">
                        <i class="fas fa-concierge-bell text-gray-500 ml-2"></i> ניהול שירותים
                    </a>
                    <a href="reports.php" class="block hover:bg-gray-100 p-2 rounded">
                        <i class="fas fa-chart-bar text-gray-500 ml-2"></i> דוחות וסטטיסטיקות
                    </a>
                </div>
            </div>
        </div>
        
        <!-- תורים הקרובים ושירותים פופולריים -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- תורים קרובים -->
            <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-sm">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-semibold">תורים להיום</h2>
                    <a href="appointments.php?date=<?php echo $today; ?>" class="text-sm text-primary hover:underline">
                        ראה הכל
                    </a>
                </div>
                
                <?php if (empty($today_appointments)): ?>
                    <div class="text-center py-8 bg-gray-50 rounded-xl">
                        <div class="text-gray-400 mb-2"><i class="fas fa-calendar-check text-5xl"></i></div>
                        <p class="text-gray-500">אין תורים מתוכננים להיום</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($today_appointments as $appointment): 
                            // קביעת קלאס CSS לפי סטטוס
                            $statusClass = '';
                            $statusText = '';
                            
                            switch($appointment['status']) {
                                case 'pending':
                                    $statusClass = 'bg-yellow-100 text-yellow-800';
                                    $statusText = 'ממתין';
                                    break;
                                case 'confirmed':
                                    $statusClass = 'bg-green-100 text-green-800';
                                    $statusText = 'מאושר';
                                    break;
                            }
                        ?>
                            <div class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                                <div class="text-center w-16">
                                    <div class="text-lg font-bold text-gray-800"><?php echo date('H:i', strtotime($appointment['start_datetime'])); ?></div>
                                </div>
                                <div class="mr-4 flex-grow">
                                    <div class="font-medium"><?php echo htmlspecialchars($appointment['customer_name']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($appointment['service_name']); ?> | <?php echo htmlspecialchars($appointment['staff_name']); ?></div>
                                </div>
                                <div class="text-left">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                        <?php echo $statusText; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- שירותים פופולריים -->
            <div class="bg-white p-6 rounded-2xl shadow-sm">
                <h2 class="text-lg font-semibold mb-4">שירותים פופולריים</h2>
                
                <?php if (empty($popular_services)): ?>
                    <div class="text-center py-8 bg-gray-50 rounded-xl">
                        <div class="text-gray-400 mb-2"><i class="fas fa-concierge-bell text-5xl"></i></div>
                        <p class="text-gray-500">אין מספיק נתונים להצגה</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($popular_services as $index => $service): 
                            // צבעים שונים לכל שירות
                            $colors = ['bg-primary', 'bg-secondary', 'bg-green-500', 'bg-yellow-500', 'bg-red-500'];
                            $color = $colors[$index % count($colors)];
                        ?>
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-gray-700"><?php echo htmlspecialchars($service['name']); ?></span>
                                    <span class="text-gray-500"><?php echo $service['count']; ?> תורים</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="<?php echo $color; ?> h-2 rounded-full" style="width: <?php echo min(100, ($service['count'] / 10) * 100); ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                
                
                <!-- צוות -->
                <h2 class="text-lg font-semibold mt-8 mb-4">ביצועי צוות</h2>
                
                <?php if (empty($staff_stats)): ?>
                    <div class="text-center py-8 bg-gray-50 rounded-xl">
                        <div class="text-gray-400 mb-2"><i class="fas fa-user-tie text-5xl"></i></div>
                        <p class="text-gray-500">אין מספיק נתונים להצגה</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($staff_stats as $index => $staff): 
                            // הצגת רק ה-3 הראשונים
                            if ($index >= 3) break;
                            // צבעים שונים לכל עובד
                            $colors = ['bg-green-500', 'bg-blue-500', 'bg-purple-500'];
                            $color = $colors[$index % count($colors)];
                        ?>
                            <div>
                                <div class="flex justify-between mb-1">
                                    <span class="text-gray-700"><?php echo htmlspecialchars($staff['name']); ?></span>
                                    <span class="text-gray-500"><?php echo $staff['count']; ?> תורים</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="<?php echo $color; ?> h-2 rounded-full" style="width: <?php echo min(100, ($staff['count'] / 10) * 100); ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // גרף תורים בשבוע האחרון
    var ctx = document.getElementById('appointmentsChart').getContext('2d');
    
    var dailyData = <?php 
        $labels = [];
        $values = [];
        
        foreach ($daily_data as $date => $count) {
            // Format date for display: "יום א' 01/01"
            $day_num = date('w', strtotime($date));
            $day_names = ['א\'', 'ב\'', 'ג\'', 'ד\'', 'ה\'', 'ו\'', 'ש\''];
            $formatted_date = 'יום ' . $day_names[$day_num] . ' ' . date('d/m', strtotime($date));
            
            $labels[] = $formatted_date;
            $values[] = $count;
        }
        
        echo json_encode([
            'labels' => $labels,
            'values' => $values
        ]);
    ?>;
    
    var chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: dailyData.labels,
            datasets: [{
                label: 'מספר תורים',
                data: dailyData.values,
                fill: false,
                borderColor: '#ec4899', // primary color
                backgroundColor: '#ec4899',
                tension: 0.4,
                borderWidth: 3,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#ec4899',
                pointBorderWidth: 2,
                pointRadius: 4
            }]
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
                        precision: 0
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
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.7)',
                    padding: 10,
                    titleFont: {
                        size: 14
                    },
                    bodyFont: {
                        size: 14
                    },
                    rtl: true,
                    titleAlign: 'right',
                    bodyAlign: 'right'
                }
            }
        }
    });
});
</script>

<?php include_once "includes/footer.php"; ?>