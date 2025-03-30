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

// בדיקה שנשלח מזהה תור
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "מזהה תור חסר";
    redirect("appointments.php");
}

$appointment_id = intval($_GET['id']);
$tenant_id = $_SESSION['tenant_id'];

// יצירת חיבור למסד הנתונים
$database = new Database();
$db = $database->getConnection();

// שליפת פרטי התור
$query = "SELECT a.*, 
          c.first_name, c.last_name, c.phone, c.email, 
          s.name as service_name, s.price, s.duration,
          st.name as staff_name
          FROM appointments a
          JOIN customers c ON a.customer_id = c.customer_id
          JOIN services s ON a.service_id = s.service_id
          JOIN staff st ON a.staff_id = st.staff_id
          WHERE a.appointment_id = :appointment_id AND a.tenant_id = :tenant_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":appointment_id", $appointment_id);
$stmt->bindParam(":tenant_id", $tenant_id);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    $_SESSION['error_message'] = "התור לא נמצא";
    redirect("appointments.php");
}

$appointment = $stmt->fetch();

// שליפת תשובות לשאלות מקדימות (אם יש)
$query = "SELECT iq.question_text, ia.answer_text 
          FROM intake_answers ia
          JOIN intake_questions iq ON ia.question_id = iq.question_id
          WHERE ia.appointment_id = :appointment_id
          ORDER BY iq.display_order ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(":appointment_id", $appointment_id);
$stmt->execute();
$intake_answers = $stmt->fetchAll();

// שליפת תשלומים (אם יש)
$has_payments_table = false;
try {
    $query = "SHOW TABLES LIKE 'payments'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $has_payments_table = ($stmt->rowCount() > 0);
} catch (PDOException $e) {
    // לא קריטי, נתעלם מהשגיאה
}

$payments = [];
if ($has_payments_table) {
    $query = "SELECT * FROM payments WHERE appointment_id = :appointment_id ORDER BY payment_date DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":appointment_id", $appointment_id);
    $stmt->execute();
    $payments = $stmt->fetchAll();
}

// פורמטים לתצוגה
$status_text = '';
$status_class = '';
switch ($appointment['status']) {
    case 'pending':
        $status_text = 'ממתין לאישור';
        $status_class = 'bg-yellow-100 text-yellow-800 border-yellow-200';
        break;
    case 'confirmed':
        $status_text = 'מאושר';
        $status_class = 'bg-green-100 text-green-800 border-green-200';
        break;
    case 'cancelled':
        $status_text = 'בוטל';
        $status_class = 'bg-red-100 text-red-800 border-red-200';
        break;
    case 'completed':
        $status_text = 'הושלם';
        $status_class = 'bg-blue-100 text-blue-800 border-blue-200';
        break;
    case 'no_show':
        $status_text = 'לא הגיע';
        $status_class = 'bg-gray-100 text-gray-800 border-gray-200';
        break;
}

// כותרת העמוד
$page_title = "פרטי תור #" . $appointment_id;

// משתנים להודעות שגיאה והצלחה
$error_message = "";
$success_message = "";

// אם יש הודעות בסשן, מציגים אותן ומוחקים
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
?>

<?php include_once "includes/header.php"; ?>
<?php include_once "includes/sidebar.php"; ?>

<!-- Main Content -->
<main class="flex-1 overflow-y-auto pb-10">
    <!-- Top Navigation Bar -->
    <header class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex flex-wrap justify-between items-center">
                <div class="flex items-center">
                    <a href="appointments.php" class="text-gray-400 hover:text-primary ml-2">
                        <i class="fas fa-arrow-right"></i>
                    </a>
                    <h1 class="text-2xl font-bold text-gray-800">פרטי תור #<?php echo $appointment_id; ?></h1>
                </div>
                
                <div class="flex items-center space-x-4 space-x-reverse mt-2 md:mt-0">
                    <!-- אפשרויות פעולה -->
                    <div class="relative">
                        <button id="action-menu-button" class="bg-white border-2 border-gray-300 px-4 py-2 rounded-xl hover:bg-gray-50 flex items-center">
                            <span>פעולות</span>
                            <i class="fas fa-chevron-down mr-2 text-sm"></i>
                        </button>
                        <div id="action-menu" class="absolute left-0 top-full mt-1 bg-white shadow-lg rounded-xl border border-gray-200 z-10 w-40 hidden">
                            <ul class="py-2">
                                <li>
                                    <a href="api/send_notification.php?id=<?php echo $appointment_id; ?>&type=reminder" class="block px-4 py-2 hover:bg-gray-100">
                                        <i class="fas fa-bell mr-2 text-primary"></i>
                                        שלח תזכורת
                                    </a>
                                </li>
                                <li>
                                    <button id="edit-appointment-btn" class="block w-full text-right px-4 py-2 hover:bg-gray-100">
                                        <i class="fas fa-edit mr-2 text-primary"></i>
                                        ערוך תור
                                    </button>
                                </li>
                                <?php if ($appointment['status'] != 'cancelled'): ?>
                                <li>
                                    <button id="cancel-appointment-btn" class="block w-full text-right px-4 py-2 hover:bg-gray-100">
                                        <i class="fas fa-times mr-2 text-red-500"></i>
                                        בטל תור
                                    </button>
                                </li>
                                <?php endif; ?>
                                <li>
                                    <button id="delete-appointment-btn" class="block w-full text-right px-4 py-2 hover:bg-gray-100">
                                        <i class="fas fa-trash-alt mr-2 text-red-500"></i>
                                        מחק תור
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- כפתור לשינוי סטטוס -->
                    <?php if ($appointment['status'] != 'completed'): ?>
                    <button id="mark-completed-btn" class="bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-xl">
                        <i class="fas fa-check mr-2"></i>
                        <span>סמן כהושלם</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Alerts -->
    <?php if (!empty($error_message)): ?>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg" role="alert">
                <?php echo $error_message; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($success_message)): ?>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4">
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg" role="alert">
                <?php echo $success_message; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Appointment Details -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Main Details -->
            <div class="md:col-span-2 space-y-6">
                <!-- Basic Information -->
                <div class="bg-white p-6 rounded-2xl shadow-sm">
                    <div class="flex justify-between items-start">
                        <h2 class="text-xl font-bold mb-4">פרטי תור</h2>
                        <span class="px-4 py-1 rounded-full text-sm <?php echo $status_class; ?> border">
                            <?php echo $status_text; ?>
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-6">
                        <div>
                            <h3 class="text-gray-500 text-sm">תאריך ושעה</h3>
                            <p class="font-medium">
                                <?php echo date('d/m/Y', strtotime($appointment['start_datetime'])); ?>,
                                <?php echo date('H:i', strtotime($appointment['start_datetime'])); ?> - 
                                <?php echo date('H:i', strtotime($appointment['end_datetime'])); ?>
                            </p>
                        </div>
                        
                        <div>
                            <h3 class="text-gray-500 text-sm">שירות</h3>
                            <p class="font-medium"><?php echo htmlspecialchars($appointment['service_name']); ?></p>
                            <p class="text-sm text-gray-500"><?php echo $appointment['duration']; ?> דקות</p>
                        </div>
                        
                        <div>
                            <h3 class="text-gray-500 text-sm">נותן שירות</h3>
                            <p class="font-medium"><?php echo htmlspecialchars($appointment['staff_name']); ?></p>
                        </div>
                        
                        <div>
                            <h3 class="text-gray-500 text-sm">מחיר</h3>
                            <p class="font-medium"><?php echo number_format($appointment['price'], 0); ?> ₪</p>
                        </div>
                        
                        <div class="md:col-span-2">
                            <h3 class="text-gray-500 text-sm">הערות</h3>
                            <p class="font-medium"><?php echo !empty($appointment['notes']) ? nl2br(htmlspecialchars($appointment['notes'])) : 'אין הערות'; ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Information -->
                <?php if ($has_payments_table): ?>
                <div class="bg-white p-6 rounded-2xl shadow-sm">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold">תשלומים</h2>
                        
                        <button id="add-payment-btn" class="text-primary hover:text-primary-dark text-sm">
                            <i class="fas fa-plus mr-1"></i> הוסף תשלום
                        </button>
                    </div>
                    
                    <?php if (empty($payments)): ?>
                        <div class="text-center py-6 text-gray-500">
                            <i class="fas fa-money-bill text-gray-300 text-3xl mb-2"></i>
                            <p>אין תשלומים להצגה</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="px-4 py-2 text-right">תאריך</th>
                                        <th class="px-4 py-2 text-right">סכום</th>
                                        <th class="px-4 py-2 text-right">אמצעי תשלום</th>
                                        <th class="px-4 py-2 text-right">אסמכתא</th>
                                        <th class="px-4 py-2 text-right">הערות</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                                            <td class="px-4 py-3"><?php echo date('d/m/Y H:i', strtotime($payment['payment_date'])); ?></td>
                                            <td class="px-4 py-3 font-medium"><?php echo number_format($payment['amount'], 0); ?> ₪</td>
                                            <td class="px-4 py-3">
                                                <?php 
                                                    $payment_method_text = '';
                                                    switch ($payment['payment_method']) {
                                                        case 'credit_card': $payment_method_text = 'כרטיס אשראי'; break;
                                                        case 'cash': $payment_method_text = 'מזומן'; break;
                                                        case 'bank_transfer': $payment_method_text = 'העברה בנקאית'; break;
                                                        case 'paypal': $payment_method_text = 'PayPal'; break;
                                                        default: $payment_method_text = $payment['payment_method'];
                                                    }
                                                    echo $payment_method_text;
                                                    
                                                    if (!empty($payment['card_last_digits'])) {
                                                        echo ' (' . $payment['card_last_digits'] . ')';
                                                    }
                                                ?>
                                            </td>
                                            <td class="px-4 py-3"><?php echo !empty($payment['transaction_id']) ? $payment['transaction_id'] : '-'; ?></td>
                                            <td class="px-4 py-3"><?php echo !empty($payment['notes']) ? $payment['notes'] : '-'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Intake Questions & Answers -->
                <?php if (!empty($intake_answers)): ?>
                <div class="bg-white p-6 rounded-2xl shadow-sm">
                    <h2 class="text-xl font-bold mb-4">שאלות מקדימות</h2>
                    
                    <div class="space-y-4">
                        <?php foreach ($intake_answers as $answer): ?>
                            <div>
                                <h3 class="text-gray-700 font-medium"><?php echo htmlspecialchars($answer['question_text']); ?></h3>
                                <p class="mt-1 text-gray-600"><?php echo nl2br(htmlspecialchars($answer['answer_text'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Customer Information -->
            <div class="space-y-6">
                <div class="bg-white p-6 rounded-2xl shadow-sm">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold">פרטי לקוח</h2>
                        
                        <a href="customer_details.php?id=<?php echo $appointment['customer_id']; ?>" class="text-primary hover:text-primary-dark text-sm">
                            <i class="fas fa-external-link-alt mr-1"></i> צפה בכרטיס לקוח
                        </a>
                    </div>
                    
                    <div class="space-y-3">
                        <div>
                            <h3 class="text-gray-500 text-sm">שם</h3>
                            <p class="font-medium"><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></p>
                        </div>
                        
                        <div>
                            <h3 class="text-gray-500 text-sm">טלפון</h3>
                            <p class="font-medium">
                                <a href="tel:<?php echo htmlspecialchars($appointment['phone']); ?>" class="text-primary">
                                    <?php echo htmlspecialchars($appointment['phone']); ?>
                                </a>
                            </p>
                        </div>
                        
                        <?php if (!empty($appointment['email'])): ?>
                        <div>
                            <h3 class="text-gray-500 text-sm">אימייל</h3>
                            <p class="font-medium">
                                <a href="mailto:<?php echo htmlspecialchars($appointment['email']); ?>" class="text-primary">
                                    <?php echo htmlspecialchars($appointment['email']); ?>
                                </a>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="pt-3 border-t border-gray-100">
                            <div class="flex space-x-2 space-x-reverse">
                                <a href="tel:<?php echo htmlspecialchars($appointment['phone']); ?>" class="flex-1 bg-green-500 hover:bg-green-600 text-white py-2 rounded-lg text-center">
                                    <i class="fas fa-phone-alt mr-1"></i> חייג
                                </a>
                                <a href="https://wa.me/<?php echo str_replace(['-', ' '], '', $appointment['phone']); ?>" target="_blank" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-2 rounded-lg text-center">
                                    <i class="fab fa-whatsapp mr-1"></i> WhatsApp
                                </a>
                                <?php if (!empty($appointment['email'])): ?>
                                <a href="mailto:<?php echo htmlspecialchars($appointment['email']); ?>" class="flex-1 bg-blue-500 hover:bg-blue-600 text-white py-2 rounded-lg text-center">
                                    <i class="fas fa-envelope mr-1"></i> אימייל
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Appointment Activity -->
                <div class="bg-white p-6 rounded-2xl shadow-sm">
                    <h2 class="text-xl font-bold mb-4">היסטוריית תור</h2>
                    
                    <div class="space-y-4">
                        <div class="flex">
                            <div class="flex flex-col items-center ml-4">
                                <div class="w-8 h-8 bg-green-100 text-green-600 rounded-full flex items-center justify-center">
                                    <i class="fas fa-calendar-plus"></i>
                                </div>
                                <div class="flex-1 h-full border-r border-gray-200 my-1"></div>
                            </div>
                            <div>
                                <p class="font-medium">התור נוצר</p>
                                <p class="text-sm text-gray-500"><?php echo date('d/m/Y H:i', strtotime($appointment['created_at'])); ?></p>
                            </div>
                        </div>
                        
                        <?php if ($appointment['confirmation_sent']): ?>
                        <div class="flex">
                            <div class="flex flex-col items-center ml-4">
                                <div class="w-8 h-8 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="flex-1 h-full border-r border-gray-200 my-1"></div>
                            </div>
                            <div>
                                <p class="font-medium">נשלח אישור</p>
                                <p class="text-sm text-gray-500">
                                    <?php echo !empty($appointment['confirmation_datetime']) ? 
                                          date('d/m/Y H:i', strtotime($appointment['confirmation_datetime'])) : '-'; ?>
                                </p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($appointment['reminder_sent']): ?>
                        <div class="flex">
                            <div class="flex flex-col items-center ml-4">
                                <div class="w-8 h-8 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center">
                                    <i class="fas fa-bell"></i>
                                </div>
                                <div class="flex-1 h-full border-r border-gray-200 my-1"></div>
                            </div>
                            <div>
                                <p class="font-medium">נשלחה תזכורת</p>
                                <p class="text-sm text-gray-500">
                                    <?php echo !empty($appointment['reminder_datetime']) ? 
                                          date('d/m/Y H:i', strtotime($appointment['reminder_datetime'])) : '-'; ?>
                                </p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($appointment['status'] == 'completed'): ?>
                        <div class="flex">
                            <div class="flex flex-col items-center ml-4">
                                <div class="w-8 h-8 bg-green-100 text-green-600 rounded-full flex items-center justify-center">
                                    <i class="fas fa-check"></i>
                                </div>
                            </div>
                            <div>
                                <p class="font-medium">התור הושלם</p>
                                <p class="text-sm text-gray-500"><?php echo date('d/m/Y H:i', strtotime($appointment['updated_at'])); ?></p>
                            </div>
                        </div>
                        <?php elseif ($appointment['status'] == 'cancelled'): ?>
                        <div class="flex">
                            <div class="flex flex-col items-center ml-4">
                                <div class="w-8 h-8 bg-red-100 text-red-600 rounded-full flex items-center justify-center">
                                    <i class="fas fa-times"></i>
                                </div>
                            </div>
                            <div>
                                <p class="font-medium">התור בוטל</p>
                                <p class="text-sm text-gray-500"><?php echo date('d/m/Y H:i', strtotime($appointment['updated_at'])); ?></p>
                            </div>
                        </div>
                        <?php elseif ($appointment['status'] == 'no_show'): ?>
                        <div class="flex">
                            <div class="flex flex-col items-center ml-4">
                                <div class="w-8 h-8 bg-gray-100 text-gray-600 rounded-full flex items-center justify-center">
                                    <i class="fas fa-user-slash"></i>
                                </div>
                            </div>
                            <div>
                                <p class="font-medium">הלקוח לא הגיע</p>
                                <p class="text-sm text-gray-500"><?php echo date('d/m/Y H:i', strtotime($appointment['updated_at'])); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal: Add Payment -->
    <?php if ($has_payments_table): ?>
    <div id="payment-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-2xl p-6 max-w-lg w-full mx-4">
            <h3 class="text-xl font-bold mb-4">הוספת תשלום</h3>
            
            <form id="payment-form" class="space-y-4">
                <input type="hidden" id="appointment_id" name="appointment_id" value="<?php echo $appointment_id; ?>">
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="amount" class="block text-gray-700 font-medium mb-2">סכום</label>
                        <div class="relative">
                            <input type="number" id="amount" name="amount" min="1" step="0.01"
                                   class="w-full pl-8 pr-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                   value="<?php echo $appointment['price']; ?>" required>
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-500">₪</span>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label for="payment_method" class="block text-gray-700 font-medium mb-2">אמצעי תשלום</label>
                        <select id="payment_method" name="payment_method" 
                               class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary" required>
                            <option value="credit_card">כרטיס אשראי</option>
                            <option value="cash">מזומן</option>
                            <option value="bank_transfer">העברה בנקאית</option>
                            <option value="paypal">PayPal</option>
                            <option value="other">אחר</option>
                        </select>
                    </div>
                </div>
                
                <!-- פרטי כרטיס אשראי - יוצגו רק אם נבחר אשראי -->
                <div id="credit-card-details" class="grid grid-cols-2 gap-4">
                    <div>
                    <label for="card_last_digits" class="block text-gray-700 font-medium mb-2">4 ספרות אחרונות</label>
                        <input type="text" id="card_last_digits" name="card_last_digits" maxlength="4" pattern="\d{4}"
                               class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                    </div>
                    
                    <div>
                        <label for="card_expiry" class="block text-gray-700 font-medium mb-2">תוקף (MM/YY)</label>
                        <input type="text" id="card_expiry" name="card_expiry" placeholder="MM/YY" maxlength="5" pattern="\d{2}/\d{2}"
                               class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                    </div>
                </div>
                
                <div>
                    <label for="transaction_id" class="block text-gray-700 font-medium mb-2">מס' אסמכתא</label>
                    <input type="text" id="transaction_id" name="transaction_id"
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                </div>
                
                <div>
                    <label for="payment_notes" class="block text-gray-700 font-medium mb-2">הערות</label>
                    <textarea id="payment_notes" name="payment_notes" rows="2"
                              class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"></textarea>
                </div>
                
                <div class="flex justify-end space-x-3 space-x-reverse mt-6">
                    <button type="button" id="cancel-payment" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg">
                        ביטול
                    </button>
                    <button type="submit" id="save-payment" class="px-4 py-2 bg-primary hover:bg-primary-dark text-white font-bold rounded-lg">
                        שמור תשלום
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Modal: Edit Appointment -->
    <div id="edit-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-2xl p-6 max-w-lg w-full mx-4">
            <h3 class="text-xl font-bold mb-4">עריכת תור</h3>
            
            <form id="edit-form" class="space-y-4">
                <input type="hidden" name="appointment_id" value="<?php echo $appointment_id; ?>">
                
                <!-- נותן שירות -->
                <div>
                    <label for="edit_staff_id" class="block text-gray-700 font-medium mb-2">נותן שירות</label>
                    <select id="edit_staff_id" name="staff_id" 
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary" required>
                        <?php
                        // שליפת כל אנשי הצוות
                        $query = "SELECT staff_id, name FROM staff WHERE tenant_id = :tenant_id AND is_active = 1 ORDER BY name ASC";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(":tenant_id", $tenant_id);
                        $stmt->execute();
                        $all_staff = $stmt->fetchAll();
                        
                        foreach ($all_staff as $staff_member):
                        ?>
                            <option value="<?php echo $staff_member['staff_id']; ?>" <?php echo ($staff_member['staff_id'] == $appointment['staff_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($staff_member['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- שירות -->
                <div>
                    <label for="edit_service_id" class="block text-gray-700 font-medium mb-2">שירות</label>
                    <select id="edit_service_id" name="service_id" 
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary" required>
                        <?php
                        // שליפת כל השירותים
                        $query = "SELECT service_id, name, duration, price FROM services WHERE tenant_id = :tenant_id AND is_active = 1 ORDER BY name ASC";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(":tenant_id", $tenant_id);
                        $stmt->execute();
                        $all_services = $stmt->fetchAll();
                        
                        foreach ($all_services as $service):
                        ?>
                            <option value="<?php echo $service['service_id']; ?>" <?php echo ($service['service_id'] == $appointment['service_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($service['name']) . ' (' . $service['duration'] . ' דק\') - ' . number_format($service['price'], 0) . ' ₪'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- תאריך ושעה -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="edit_date" class="block text-gray-700 font-medium mb-2">תאריך</label>
                        <input type="date" id="edit_date" name="appointment_date" 
                               value="<?php echo date('Y-m-d', strtotime($appointment['start_datetime'])); ?>"
                               class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                               required>
                    </div>
                    <div>
                        <label for="edit_time" class="block text-gray-700 font-medium mb-2">שעה</label>
                        <input type="time" id="edit_time" name="appointment_time" 
                               value="<?php echo date('H:i', strtotime($appointment['start_datetime'])); ?>"
                               class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                               required>
                    </div>
                </div>
                
                <!-- הערות -->
                <div>
                    <label for="edit_notes" class="block text-gray-700 font-medium mb-2">הערות</label>
                    <textarea id="edit_notes" name="notes" rows="3"
                             class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"><?php echo htmlspecialchars($appointment['notes']); ?></textarea>
                </div>
                
                <!-- סטטוס התור -->
                <div>
                    <label class="block text-gray-700 font-medium mb-2">סטטוס</label>
                    <div class="flex flex-wrap gap-4">
                        <label class="inline-flex items-center">
                            <input type="radio" name="status" value="pending" class="text-primary" <?php echo ($appointment['status'] == 'pending') ? 'checked' : ''; ?>>
                            <span class="mr-2">ממתין</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="status" value="confirmed" class="text-primary" <?php echo ($appointment['status'] == 'confirmed') ? 'checked' : ''; ?>>
                            <span class="mr-2">מאושר</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="status" value="cancelled" class="text-primary" <?php echo ($appointment['status'] == 'cancelled') ? 'checked' : ''; ?>>
                            <span class="mr-2">בוטל</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="status" value="completed" class="text-primary" <?php echo ($appointment['status'] == 'completed') ? 'checked' : ''; ?>>
                            <span class="mr-2">הושלם</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="status" value="no_show" class="text-primary" <?php echo ($appointment['status'] == 'no_show') ? 'checked' : ''; ?>>
                            <span class="mr-2">לא הגיע</span>
                        </label>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 space-x-reverse mt-6">
                    <button type="button" id="cancel-edit" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg">
                        ביטול
                    </button>
                    <button type="submit" class="px-4 py-2 bg-primary hover:bg-primary-dark text-white font-bold rounded-lg">
                        שמור שינויים
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // תפריט פעולות
    const actionMenuButton = document.getElementById('action-menu-button');
    const actionMenu = document.getElementById('action-menu');
    
    actionMenuButton.addEventListener('click', function() {
        actionMenu.classList.toggle('hidden');
    });
    
    // סגירת התפריט בלחיצה מחוץ אליו
    document.addEventListener('click', function(e) {
        if (!actionMenuButton.contains(e.target) && !actionMenu.contains(e.target)) {
            actionMenu.classList.add('hidden');
        }
    });
    
    // סימון התור כהושלם
    const markCompletedBtn = document.getElementById('mark-completed-btn');
    if (markCompletedBtn) {
        markCompletedBtn.addEventListener('click', function() {
            if (confirm('האם אתה בטוח שברצונך לסמן את התור כהושלם?')) {
                // שליחת בקשה לעדכון סטטוס התור
                fetch('api/update_appointment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        appointment_id: <?php echo $appointment_id; ?>,
                        status: 'completed'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // רענון העמוד
                        location.reload();
                    } else {
                        alert('שגיאה: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('שגיאה בעדכון התור');
                });
            }
        });
    }
    
    // מחיקת תור
    const deleteAppointmentBtn = document.getElementById('delete-appointment-btn');
    deleteAppointmentBtn.addEventListener('click', function() {
        if (confirm('האם אתה בטוח שברצונך למחוק את התור? פעולה זו אינה ניתנת לביטול.')) {
            fetch('api/delete_appointment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    appointment_id: <?php echo $appointment_id; ?>
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // מעבר לדף התורים
                    window.location.href = 'appointments.php';
                } else {
                    alert('שגיאה: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('שגיאה במחיקת התור');
            });
        }
    });
    
    // ביטול תור
    const cancelAppointmentBtn = document.getElementById('cancel-appointment-btn');
    if (cancelAppointmentBtn) {
        cancelAppointmentBtn.addEventListener('click', function() {
            if (confirm('האם אתה בטוח שברצונך לבטל את התור?')) {
                // שליחת בקשה לעדכון סטטוס התור
                fetch('api/update_appointment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        appointment_id: <?php echo $appointment_id; ?>,
                        status: 'cancelled'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // רענון העמוד
                        location.reload();
                    } else {
                        alert('שגיאה: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('שגיאה בביטול התור');
                });
            }
        });
    }
    
    // עריכת תור
    const editAppointmentBtn = document.getElementById('edit-appointment-btn');
    const editModal = document.getElementById('edit-modal');
    const cancelEditBtn = document.getElementById('cancel-edit');
    
    editAppointmentBtn.addEventListener('click', function() {
        editModal.classList.remove('hidden');
        actionMenu.classList.add('hidden');
    });
    
    cancelEditBtn.addEventListener('click', function() {
        editModal.classList.add('hidden');
    });
    
    // שליחת טופס עריכת תור
    document.getElementById('edit-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // יצירת אובייקט FormData
        const formData = new FormData(this);
        
        // בניית תאריך ושעה מלאים
        const dateStr = formData.get('appointment_date');
        const timeStr = formData.get('appointment_time');
        formData.append('start_datetime', dateStr + 'T' + timeStr + ':00');
        
        // המרה לאובייקט רגיל
        const formObject = {};
        formData.forEach((value, key) => {
            formObject[key] = value;
        });
        
        // שליחת הנתונים
        fetch('api/update_appointment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formObject)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // רענון העמוד
                location.reload();
            } else {
                alert('שגיאה: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('שגיאה בעדכון התור');
        });
    });
    
    <?php if ($has_payments_table): ?>
    // הוספת תשלום
    const addPaymentBtn = document.getElementById('add-payment-btn');
    const paymentModal = document.getElementById('payment-modal');
    const cancelPaymentBtn = document.getElementById('cancel-payment');
    const paymentMethodSelect = document.getElementById('payment_method');
    const creditCardDetails = document.getElementById('credit-card-details');
    
    addPaymentBtn.addEventListener('click', function() {
        paymentModal.classList.remove('hidden');
    });
    
    cancelPaymentBtn.addEventListener('click', function() {
        paymentModal.classList.add('hidden');
    });
    
    // הצגה/הסתרה של פרטי כרטיס אשראי
    paymentMethodSelect.addEventListener('change', function() {
        if (this.value === 'credit_card') {
            creditCardDetails.classList.remove('hidden');
        } else {
            creditCardDetails.classList.add('hidden');
        }
    });
    
    // שליחת טופס תשלום
    document.getElementById('payment-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // יצירת אובייקט FormData
        const formData = new FormData(this);
        formData.append('tenant_id', '<?php echo $tenant_id; ?>');
        
        // המרה לאובייקט רגיל
        const formObject = {};
        formData.forEach((value, key) => {
            formObject[key] = value;
        });
        
        // שליחת הנתונים
        fetch('api/record_payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formObject)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // רענון העמוד
                location.reload();
            } else {
                alert('שגיאה: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('שגיאה בשמירת התשלום');
        });
    });
    <?php endif; ?>
    
    // סגירת מודלים בלחיצה על הרקע
    <?php if ($has_payments_table): ?>
    paymentModal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.add('hidden');
        }
    });
    <?php endif; ?>
    
    editModal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.add('hidden');
        }
    });
});
</script>

<?php include_once "includes/footer.php"; ?>