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

// קבלת מזהה לקוח
$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($customer_id == 0) {
    redirect("customers.php");
}

// חיבור למסד הנתונים
$database = new Database();
$db = $database->getConnection();

// משתנים להודעות
$error_message = "";
$success_message = "";

// שליפת פרטי הלקוח
$customer = null;
try {
    $query = "SELECT * FROM customers WHERE customer_id = :customer_id AND tenant_id = :tenant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":customer_id", $customer_id);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        redirect("customers.php");
    }
    
    $customer = $stmt->fetch();
} catch (PDOException $e) {
    $error_message = "שגיאת מסד נתונים: " . $e->getMessage();
}

// הגדרות לעימוד התורים
$records_per_page = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $records_per_page;

// סינון תורים
$filter = isset($_GET['filter']) ? sanitizeInput($_GET['filter']) : 'all';

// שליפת התורים של הלקוח
$appointments = [];
$total_appointments = 0;

try {
    // בניית שאילתת סינון
    $filter_condition = '';
    switch ($filter) {
        case 'upcoming':
            $filter_condition = "AND a.start_datetime >= NOW()";
            break;
        case 'past':
            $filter_condition = "AND a.start_datetime < NOW()";
            break;
        case 'cancelled':
            $filter_condition = "AND a.status = 'cancelled'";
            break;
        case 'completed':
            $filter_condition = "AND a.status = 'completed'";
            break;
    }
    
    // שאילתת ספירה
    $query = "SELECT COUNT(*) as total FROM appointments a 
              WHERE a.customer_id = :customer_id 
              AND a.tenant_id = :tenant_id 
              $filter_condition";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":customer_id", $customer_id);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    $result = $stmt->fetch();
    $total_appointments = $result['total'];
    
    // שאילתת התורים
    $query = "SELECT a.*, 
              s.name as service_name, 
              s.duration as service_duration,
              st.name as staff_name
              FROM appointments a
              JOIN services s ON a.service_id = s.service_id
              JOIN staff st ON a.staff_id = st.staff_id
              WHERE a.customer_id = :customer_id 
              AND a.tenant_id = :tenant_id 
              $filter_condition
              ORDER BY a.start_datetime DESC
              LIMIT :offset, :limit";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":customer_id", $customer_id);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
    $stmt->bindParam(":limit", $records_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $appointments = $stmt->fetchAll();
    
    // חישוב סטטיסטיקות
    $query = "SELECT 
              COUNT(*) as total_appointments,
              SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_appointments,
              SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_appointments,
              SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show_appointments,
              COUNT(DISTINCT service_id) as unique_services
              FROM appointments
              WHERE customer_id = :customer_id 
              AND tenant_id = :tenant_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":customer_id", $customer_id);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    $stats = $stmt->fetch();
    
} catch (PDOException $e) {
    $error_message = "שגיאת מסד נתונים: " . $e->getMessage();
}

// חישוב עימוד
$total_pages = ceil($total_appointments / $records_per_page);

// פורמט תאריך בעברית
function formatHebrewDate($date) {
    $timestamp = strtotime($date);
    return date('d/m/Y', $timestamp);
}

// כותרת העמוד
$page_title = "פרטי לקוח: " . $customer['first_name'] . ' ' . $customer['last_name'];
?>

<?php include_once "includes/header.php"; ?>
<?php include_once "includes/sidebar.php"; ?>

<!-- Main Content -->
<main class="flex-1 overflow-y-auto py-8">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <div class="flex items-center">
                <a href="customers.php" class="text-gray-600 hover:text-gray-800 ml-2">
                    <i class="fas fa-arrow-right"></i>
                </a>
                <h1 class="text-3xl font-bold text-gray-800">
                    <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                </h1>
            </div>
            
            <div>
                <a href="appointments.php?new=1&customer_id=<?php echo $customer_id; ?>" class="bg-primary hover:bg-primary-dark text-white font-bold py-2 px-4 rounded-xl transition duration-300">
                    <i class="fas fa-calendar-plus mr-1"></i> קבע תור חדש
                </a>
                <button type="button" class="bg-secondary hover:bg-secondary-dark text-white font-bold py-2 px-4 rounded-xl transition duration-300 mr-2" onclick="editCustomer(<?php echo $customer_id; ?>)">
                    <i class="fas fa-edit mr-1"></i> ערוך פרטים
                </button>
            </div>
        </div>
        
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
        
        <!-- Customer Info & Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <!-- Customer Details -->
            <div class="bg-white shadow-sm rounded-2xl overflow-hidden p-6">
                <h2 class="text-xl font-bold mb-4 pb-2 border-b border-gray-200">פרטי לקוח</h2>
                
                <div class="space-y-4">
                    <div>
                        <span class="text-gray-500">שם מלא:</span>
                        <p class="font-medium"><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></p>
                    </div>
                    
                    <div>
                        <span class="text-gray-500">טלפון:</span>
                        <p class="font-medium dir-ltr">
                            <a href="tel:<?php echo htmlspecialchars($customer['phone']); ?>" class="text-primary hover:underline">
                                <?php echo htmlspecialchars($customer['phone']); ?>
                            </a>
                        </p>
                    </div>
                    
                    <?php if (!empty($customer['email'])): ?>
                    <div>
                        <span class="text-gray-500">אימייל:</span>
                        <p class="font-medium dir-ltr">
                            <a href="mailto:<?php echo htmlspecialchars($customer['email']); ?>" class="text-primary hover:underline">
                                <?php echo htmlspecialchars($customer['email']); ?>
                            </a>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($customer['notes'])): ?>
                    <div>
                        <span class="text-gray-500">הערות:</span>
                        <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($customer['notes'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div>
                        <span class="text-gray-500">תאריך הצטרפות:</span>
                        <p><?php echo formatHebrewDate($customer['created_at']); ?></p>
                    </div>
                </div>
                
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <button type="button" class="text-primary hover:text-primary-dark font-medium" onclick="editCustomer(<?php echo $customer_id; ?>)">
                        <i class="fas fa-edit mr-1"></i> ערוך פרטים
                    </button>
                </div>
            </div>
            
            <!-- Stats -->
            <div class="bg-white shadow-sm rounded-2xl overflow-hidden p-6">
                <h2 class="text-xl font-bold mb-4 pb-2 border-b border-gray-200">סטטיסטיקות</h2>
                
                <div class="space-y-4">
                    <div class="flex justify-between">
                        <span class="text-gray-500">סך הכל תורים:</span>
                        <span class="font-medium"><?php echo $stats['total_appointments']; ?></span>
                    </div>
                    
                    <div class="flex justify-between">
                        <span class="text-gray-500">תורים שהושלמו:</span>
                        <span class="font-medium text-green-600"><?php echo $stats['completed_appointments']; ?></span>
                    </div>
                    
                    <div class="flex justify-between">
                        <span class="text-gray-500">תורים שבוטלו:</span>
                        <span class="font-medium text-red-600"><?php echo $stats['cancelled_appointments']; ?></span>
                    </div>
                    
                    <div class="flex justify-between">
                        <span class="text-gray-500">לא הגיע:</span>
                        <span class="font-medium text-yellow-600"><?php echo $stats['no_show_appointments']; ?></span>
                    </div>
                    
                    <div class="flex justify-between">
                        <span class="text-gray-500">שירותים שונים:</span>
                        <span class="font-medium"><?php echo $stats['unique_services']; ?></span>
                    </div>
                    
                    <?php
                    // חישוב אחוז ביטולים
                    $cancellation_rate = 0;
                    if ($stats['total_appointments'] > 0) {
                        $cancellation_rate = round(($stats['cancelled_appointments'] / $stats['total_appointments']) * 100);
                    }
                    ?>
                    
                    <div class="flex justify-between">
                        <span class="text-gray-500">אחוז ביטולים:</span>
                        <span class="font-medium <?php echo ($cancellation_rate > 20) ? 'text-red-600' : 'text-gray-600'; ?>">
                            <?php echo $cancellation_rate; ?>%
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="bg-white shadow-sm rounded-2xl overflow-hidden p-6">
                <h2 class="text-xl font-bold mb-4 pb-2 border-b border-gray-200">פעולות מהירות</h2>
                
                <div class="space-y-3">
                    <a href="appointments.php?new=1&customer_id=<?php echo $customer_id; ?>" class="block w-full py-2 px-4 bg-primary text-white rounded-lg hover:bg-primary-dark text-center">
                        <i class="fas fa-calendar-plus mr-1"></i> קבע תור חדש
                    </a>
                    
                    <a href="sms:<?php echo $customer['phone']; ?>" class="block w-full py-2 px-4 bg-blue-500 text-white rounded-lg hover:bg-blue-600 text-center">
                        <i class="fas fa-sms mr-1"></i> שלח SMS
                    </a>
                    
                    <?php if (!empty($customer['email'])): ?>
                    <a href="mailto:<?php echo $customer['email']; ?>" class="block w-full py-2 px-4 bg-gray-500 text-white rounded-lg hover:bg-gray-600 text-center">
                        <i class="fas fa-envelope mr-1"></i> שלח אימייל
                    </a>
                    <?php endif; ?>
                    
                    <a href="tel:<?php echo $customer['phone']; ?>" class="block w-full py-2 px-4 bg-green-500 text-white rounded-lg hover:bg-green-600 text-center">
                        <i class="fas fa-phone-alt mr-1"></i> התקשר
                    </a>
                    
                    <?php if ($stats['total_appointments'] == 0): ?>
                    <button type="button" class="block w-full py-2 px-4 bg-red-500 text-white rounded-lg hover:bg-red-600 text-center" onclick="confirmDelete(<?php echo $customer_id; ?>, '<?php echo htmlspecialchars(addslashes($customer['first_name'] . ' ' . $customer['last_name'])); ?>')">
                        <i class="fas fa-trash-alt mr-1"></i> מחק לקוח
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Appointments History -->
        <div class="bg-white shadow-sm rounded-2xl overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <div class="flex flex-wrap justify-between items-center">
                    <h2 class="text-xl font-bold">היסטוריית תורים</h2>
                    
                    <div class="flex space-x-2 space-x-reverse">
                        <a href="?id=<?php echo $customer_id; ?>&filter=all" class="px-3 py-1 rounded-lg <?php echo $filter == 'all' ? 'bg-primary text-white' : 'bg-gray-200 text-gray-600 hover:bg-gray-300'; ?>">
                            הכל
                        </a>
                        <a href="?id=<?php echo $customer_id; ?>&filter=upcoming" class="px-3 py-1 rounded-lg <?php echo $filter == 'upcoming' ? 'bg-primary text-white' : 'bg-gray-200 text-gray-600 hover:bg-gray-300'; ?>">
                            עתידיים
                        </a>
                        <a href="?id=<?php echo $customer_id; ?>&filter=past" class="px-3 py-1 rounded-lg <?php echo $filter == 'past' ? 'bg-primary text-white' : 'bg-gray-200 text-gray-600 hover:bg-gray-300'; ?>">
                            עבר
                        </a>
                        <a href="?id=<?php echo $customer_id; ?>&filter=completed" class="px-3 py-1 rounded-lg <?php echo $filter == 'completed' ? 'bg-primary text-white' : 'bg-gray-200 text-gray-600 hover:bg-gray-300'; ?>">
                            הושלמו
                        </a>
                        <a href="?id=<?php echo $customer_id; ?>&filter=cancelled" class="px-3 py-1 rounded-lg <?php echo $filter == 'cancelled' ? 'bg-primary text-white' : 'bg-gray-200 text-gray-600 hover:bg-gray-300'; ?>">
                            בוטלו
                        </a>
                    </div>
                </div>
            </div>
            
            <?php if (empty($appointments)): ?>
                <div class="p-8 text-center">
                    <p class="text-gray-500 mb-4">אין תורים להצגה</p>
                    <a href="appointments.php?new=1&customer_id=<?php echo $customer_id; ?>" class="text-primary hover:text-primary-dark font-medium">
                        <i class="fas fa-calendar-plus mr-1"></i> קבע תור חדש
                    </a>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">תאריך ושעה</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">שירות</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">איש צוות</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">סטטוס</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">פעולות</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($appointments as $appointment): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-medium">
                                            <?php echo date('d/m/Y', strtotime($appointment['start_datetime'])); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo date('H:i', strtotime($appointment['start_datetime'])); ?> - 
                                            <?php echo date('H:i', strtotime($appointment['end_datetime'])); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo htmlspecialchars($appointment['service_name']); ?>
                                        <div class="text-sm text-gray-500">
                                            <?php echo $appointment['service_duration']; ?> דקות
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo htmlspecialchars($appointment['staff_name']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $status_class = 'bg-gray-100 text-gray-800';
                                        $status_text = '';
                                        
                                        switch ($appointment['status']) {
                                            case 'pending':
                                                $status_class = 'bg-yellow-100 text-yellow-800';
                                                $status_text = 'ממתין לאישור';
                                                break;
                                            case 'confirmed':
                                                $status_class = 'bg-blue-100 text-blue-800';
                                                $status_text = 'מאושר';
                                                break;
                                            case 'completed':
                                                $status_class = 'bg-green-100 text-green-800';
                                                $status_text = 'הושלם';
                                                break;
                                            case 'cancelled':
                                                $status_class = 'bg-red-100 text-red-800';
                                                $status_text = 'בוטל';
                                                break;
                                            case 'no_show':
                                                $status_class = 'bg-gray-100 text-gray-800';
                                                $status_text = 'לא הגיע';
                                                break;
                                        }
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="appointment_details.php?id=<?php echo $appointment['appointment_id']; ?>" class="text-primary hover:text-primary-dark mr-2" title="צפייה בפרטי תור">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if (in_array($appointment['status'], ['pending', 'confirmed']) && strtotime($appointment['start_datetime']) > time()): ?>
                                            <a href="appointments.php?edit=<?php echo $appointment['appointment_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-2" title="ערוך תור">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="text-red-600 hover:text-red-900" onclick="confirmCancelAppointment(<?php echo $appointment['appointment_id']; ?>, '<?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($appointment['start_datetime']))); ?>')" title="בטל תור">
                                                <i class="fas fa-times-circle"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="px-6 py-4 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    מציג <?php echo min($total_appointments, $offset + 1); ?> עד <?php echo min($total_appointments, $offset + count($appointments)); ?> מתוך <?php echo $total_appointments; ?> תורים
                                </p>
                            </div>
                            <div>
                                <nav class="flex items-center">
                                    <?php if ($page > 1): ?>
                                        <a href="?id=<?php echo $customer_id; ?>&page=<?php echo $page - 1; ?>&filter=<?php echo urlencode($filter); ?>" class="px-3 py-1 rounded-md bg-gray-200 text-gray-600 hover:bg-gray-300 mr-2">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="px-3 py-1 rounded-md bg-gray-100 text-gray-400 mr-2">
                                            <i class="fas fa-chevron-right"></i>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <?php if ($i == $page): ?>
                                            <span class="px-3 py-1 rounded-md bg-primary text-white mx-1"><?php echo $i; ?></span>
                                        <?php else: ?>
                                            <a href="?id=<?php echo $customer_id; ?>&page=<?php echo $i; ?>&filter=<?php echo urlencode($filter); ?>" class="px-3 py-1 rounded-md text-gray-600 hover:bg-gray-200 mx-1"><?php echo $i; ?></a>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <a href="?id=<?php echo $customer_id; ?>&page=<?php echo $page + 1; ?>&filter=<?php echo urlencode($filter); ?>" class="px-3 py-1 rounded-md bg-gray-200 text-gray-600 hover:bg-gray-300 ml-2">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="px-3 py-1 rounded-md bg-gray-100 text-gray-400 ml-2">
                                            <i class="fas fa-chevron-left"></i>
                                        </span>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal לעריכת לקוח -->
    <div id="customerModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-2xl p-6 max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-6">
                <h2 id="modalTitle" class="text-2xl font-bold">עריכת פרטי לקוח</h2>
                <button type="button" class="text-gray-500 hover:text-gray-700" onclick="closeModal()">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="customerForm" method="POST" action="api/save_customer.php">
                <input type="hidden" id="customer_id" name="customer_id" value="<?php echo $customer_id; ?>">
                <input type="hidden" name="redirect" value="customer_details.php?id=<?php echo $customer_id; ?>">
                
                <div class="grid grid-cols-1 gap-4 mb-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="first_name" class="block text-gray-700 font-medium mb-2">שם פרטי *</label>
                            <input type="text" id="first_name" name="first_name" 
                                   class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                   value="<?php echo htmlspecialchars($customer['first_name']); ?>"
                                   required>
                        </div>
                        <div>
                            <label for="last_name" class="block text-gray-700 font-medium mb-2">שם משפחה *</label>
                            <input type="text" id="last_name" name="last_name" 
                                   class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                   value="<?php echo htmlspecialchars($customer['last_name']); ?>"
                                   required>
                        </div>
                    </div>
                    
                    <div>
                        <label for="phone" class="block text-gray-700 font-medium mb-2">טלפון נייד *</label>
                        <input type="tel" id="phone" name="phone" dir="ltr"
                               class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                               value="<?php echo htmlspecialchars($customer['phone']); ?>"
                               required>
                    </div>
                    
                    <div>
                        <label for="email" class="block text-gray-700 font-medium mb-2">אימייל</label>
                        <input type="email" id="email" name="email" dir="ltr"
                               class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                               value="<?php echo htmlspecialchars($customer['email']); ?>">
                    </div>
                    
                    <div>
                        <label for="notes" class="block text-gray-700 font-medium mb-2">הערות</label>
                        <textarea id="notes" name="notes" rows="3"
                                 class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"><?php echo htmlspecialchars($customer['notes']); ?></textarea>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-4 space-x-reverse">
                    <button type="button" class="px-4 py-2 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-100 focus:outline-none" onclick="closeModal()">ביטול</button>
                    <button type="submit" id="saveCustomerBtn" class="px-4 py-2 bg-primary text-white rounded-xl hover:bg-primary-dark focus:outline-none">עדכן פרטים</button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
    // פתיחת מודל לעריכת לקוח
    function editCustomer(customerId) {
        document.getElementById('customerModal').classList.remove('hidden');
    }
    
    // סגירת המודל
    function closeModal() {
        document.getElementById('customerModal').classList.add('hidden');
    }
    
    // אישור מחיקה
    function confirmDelete(customerId, customerName) {
        if (confirm('האם אתה בטוח שברצונך למחוק את הלקוח "' + customerName + '"?')) {
            window.location.href = 'customers.php?delete=' + customerId;
        }
    }
    
    // אישור ביטול תור
    function confirmCancelAppointment(appointmentId, appointmentDateTime) {
        if (confirm('האם אתה בטוח שברצונך לבטל את התור בתאריך ' + appointmentDateTime + '?')) {
            window.location.href = 'appointments.php?cancel=' + appointmentId + '&return=customer_details.php?id=<?php echo $customer_id; ?>';
        }
    }
    
    // טיפול בשליחת הטופס
    document.getElementById('customerForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        // בדיקת תקינות טלפון
        const phone = document.getElementById('phone').value;
        if (!isValidPhoneNumber(phone)) {
            alert('אנא הזן מספר טלפון תקין');
            return;
        }
        
        // בדיקת תקינות אימייל אם הוזן
        const email = document.getElementById('email').value;
        if (email && !isValidEmail(email)) {
            alert('אנא הזן כתובת אימייל תקינה');
            return;
        }
        
        // שינוי טקסט כפתור לאנימציית טעינה
        const submitBtn = document.getElementById('saveCustomerBtn');
        const originalText = submitBtn.innerText;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> שומר...';
        
        fetch('api/save_customer.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // רענון העמוד להצגת השינויים
                window.location.reload();
            } else {
                alert('אירעה שגיאה: ' + data.message);
                submitBtn.disabled = false;
                submitBtn.innerText = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('אירעה שגיאה בשמירת הלקוח');
            submitBtn.disabled = false;
            submitBtn.innerText = originalText;
        });
    });
    
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
</script>

<?php include_once "includes/footer.php"; ?>