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

// הגדרות לעימוד (pagination)
$records_per_page = 20;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $records_per_page;

// סינון וחיפוש
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$filter = isset($_GET['filter']) ? sanitizeInput($_GET['filter']) : '';

// משתנים להודעות
$error_message = "";
$success_message = "";

// בדיקת מגבלת לקוחות
try {
    // בדיקה אם העמודה max_customers קיימת
    $stmt = $db->query("SHOW COLUMNS FROM subscriptions LIKE 'max_customers'");
    if ($stmt->rowCount() == 0) {
        // אם העמודה לא קיימת, נציג את המסלול הבסיסי
        $query = "SELECT * FROM subscriptions WHERE name = 'רגיל'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $subscription_data = $stmt->fetch();
        
        if ($subscription_data) {
            $customer_limit = [
                'max_customers' => 10, // ערך ברירת מחדל
                'current_customers' => 0
            ];
        } else {
            $customer_limit = [
                'max_customers' => 0,
                'current_customers' => 0
            ];
        }
    } else {
        // אם העמודה קיימת, נמשיך עם השאילתה הרגילה
        $query = "SELECT s.*, COUNT(c.customer_id) as current_customers 
                FROM subscriptions s 
                JOIN tenant_subscriptions ts ON ts.subscription_id = s.id 
                LEFT JOIN customers c ON c.tenant_id = :tenant_id1 
                WHERE ts.tenant_id = :tenant_id2 
                GROUP BY s.id, s.name, s.price, s.max_staff_members, s.max_customers, s.features, s.created_at";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":tenant_id1", $_SESSION['tenant_id']);
        $stmt->bindParam(":tenant_id2", $_SESSION['tenant_id']);
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result) {
            $subscription_data = [
                'id' => $result['id'],
                'name' => $result['name'],
                'price' => $result['price'],
                'max_staff_members' => $result['max_staff_members'],
                'max_customers' => $result['max_customers'],
                'features' => $result['features']
            ];
            $customer_limit = [
                'max_customers' => $result['max_customers'],
                'current_customers' => $result['current_customers']
            ];
        } else {
            // אם אין מסלול פעיל, נציג את המסלול הבסיסי
            $query = "SELECT * FROM subscriptions WHERE name = 'רגיל'";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $subscription_data = $stmt->fetch();
            
            if ($subscription_data) {
                // הוספת המסלול הבסיסי ל-tenant_subscriptions
                $query = "INSERT INTO tenant_subscriptions (tenant_id, subscription_id) VALUES (:tenant_id, :subscription_id)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":tenant_id", $_SESSION['tenant_id']);
                $stmt->bindParam(":subscription_id", $subscription_data['id']);
                $stmt->execute();
                
                // שליפת מספר הלקוחות הנוכחי
                $query = "SELECT COUNT(*) as current_customers FROM customers WHERE tenant_id = :tenant_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":tenant_id", $_SESSION['tenant_id']);
                $stmt->execute();
                $customer_count = $stmt->fetch();
                $customer_limit = [
                    'max_customers' => $subscription_data['max_customers'] ?? 10,
                    'current_customers' => $customer_count['current_customers']
                ];
            } else {
                // אם אין אפילו מסלול בסיסי, נציג הודעת שגיאה
                echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6" role="alert">
                        לא נמצא מסלול פעיל. אנא צור קשר עם התמיכה.
                      </div>';
                $subscription_data = [
                    'name' => 'לא זמין',
                    'max_customers' => 0
                ];
                $customer_limit = [
                    'max_customers' => 0,
                    'current_customers' => 0
                ];
            }
        }
    }
} catch (PDOException $e) {
    // במקרה של שגיאה, נציג את המסלול הבסיסי
    $subscription_data = [
        'name' => 'רגיל',
        'max_customers' => 10
    ];
    $customer_limit = [
        'max_customers' => 10,
        'current_customers' => 0
    ];
}

// בדיקה אם הגענו למגבלת הלקוחות
if ($customer_limit['current_customers'] >= $customer_limit['max_customers']) {
    $error_message = "הגעת למגבלת הלקוחות במסלול שלך. כדי להוסיף לקוחות נוספים, יש לשדרג את המסלול.";
}

// טיפול במחיקת לקוח
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $customer_id = intval($_GET['delete']);
    
    try {
        // בדיקה שהלקוח שייך לטננט
        $query = "SELECT customer_id FROM customers WHERE customer_id = :customer_id AND tenant_id = :tenant_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":customer_id", $customer_id);
        $stmt->bindParam(":tenant_id", $tenant_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // בדיקה אם יש תורים עתידיים ללקוח
            $query = "SELECT appointment_id FROM appointments 
                    WHERE customer_id = :customer_id 
                    AND tenant_id = :tenant_id 
                    AND start_datetime > NOW()
                    AND status IN ('pending', 'confirmed')";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":customer_id", $customer_id);
            $stmt->bindParam(":tenant_id", $tenant_id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $error_message = "לא ניתן למחוק לקוח עם תורים עתידיים. יש לבטל את התורים תחילה.";
            } else {
                // מחיקת הלקוח
                $query = "DELETE FROM customers WHERE customer_id = :customer_id AND tenant_id = :tenant_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":customer_id", $customer_id);
                $stmt->bindParam(":tenant_id", $tenant_id);
                
                if ($stmt->execute()) {
                    $success_message = "הלקוח נמחק בהצלחה";
                } else {
                    $error_message = "אירעה שגיאה במחיקת הלקוח";
                }
            }
        } else {
            $error_message = "הלקוח לא נמצא או אינו שייך לעסק שלך";
        }
    } catch (PDOException $e) {
        $error_message = "שגיאת מסד נתונים: " . $e->getMessage();
    }
}

// שליפת הלקוחות
$customers = [];
$total_records = 0;

try {
    // בניית שאילתת חיפוש
    $search_condition = '';
    $search_params = [];
    
    if (!empty($search)) {
        $search_condition = "AND (first_name LIKE :search OR last_name LIKE :search OR phone LIKE :search OR email LIKE :search)";
        $search_params[':search'] = "%$search%";
    }
    
    // בניית שאילתת סינון
    $filter_condition = '';
    switch ($filter) {
        case 'recent':
            $filter_condition = "ORDER BY created_at DESC";
            break;
        case 'active':
            $filter_condition = "ORDER BY (SELECT COUNT(*) FROM appointments a WHERE a.customer_id = c.customer_id AND a.tenant_id = c.tenant_id) DESC";
            break;
        default:
            $filter_condition = "ORDER BY last_name ASC, first_name ASC";
    }
    
    // שאילתת ספירה לעימוד
    $query = "SELECT COUNT(*) as total FROM customers c 
              WHERE tenant_id = :tenant_id $search_condition";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    
    foreach ($search_params as $param => $value) {
        $stmt->bindValue($param, $value);
    }
    
    $stmt->execute();
    $result = $stmt->fetch();
    $total_records = $result['total'];
    
    // שאילתת הנתונים
    $query = "SELECT c.*, 
              (SELECT COUNT(*) FROM appointments a WHERE a.customer_id = c.customer_id AND a.tenant_id = c.tenant_id) as appointment_count,
              (SELECT MAX(start_datetime) FROM appointments a WHERE a.customer_id = c.customer_id AND a.tenant_id = c.tenant_id) as last_appointment
              FROM customers c 
              WHERE c.tenant_id = :tenant_id $search_condition 
              $filter_condition
              LIMIT :offset, :limit";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
    $stmt->bindParam(":limit", $records_per_page, PDO::PARAM_INT);
    
    foreach ($search_params as $param => $value) {
        $stmt->bindValue($param, $value);
    }
    
    $stmt->execute();
    $customers = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_message = "שגיאת מסד נתונים: " . $e->getMessage();
}

// חישוב עימוד
$total_pages = ceil($total_records / $records_per_page);

// כותרת העמוד
$page_title = "ניהול לקוחות";
?>

<?php include_once "includes/header.php"; ?>
<?php include_once "includes/sidebar.php"; ?>

<!-- Main Content -->
<main class="flex-1 overflow-y-auto py-8">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">ניהול לקוחות</h1>
            
            <div class="flex items-center">
                <div class="ml-4 text-sm">
                    <span class="font-semibold">מסלול נוכחי:</span>
                    <span class="text-primary"><?php echo htmlspecialchars($subscription_data['name']); ?></span>
                    <br>
                    <span class="font-semibold">מגבלת לקוחות:</span>
                    <span class="<?php echo ($customer_limit['current_customers'] >= $customer_limit['max_customers']) ? 'text-red-500' : 'text-green-500'; ?>">
                        <?php echo $customer_limit['current_customers']; ?> / <?php echo $customer_limit['max_customers']; ?>
                    </span>
                    <?php if ($customer_limit['current_customers'] >= $customer_limit['max_customers']): ?>
                        <br>
                        <a href="settings.php#subscription" class="text-primary hover:text-primary-dark font-semibold">
                            <i class="fas fa-arrow-circle-up ml-1"></i> שדרג את המסלול שלך
                        </a>
                    <?php endif; ?>
                </div>
                
                <button type="button" class="bg-primary hover:bg-primary-dark text-white font-bold py-2 px-4 rounded-xl transition duration-300" onclick="openAddModal()" <?php echo ($customer_limit['current_customers'] >= $customer_limit['max_customers']) ? 'disabled' : ''; ?>>
                    <i class="fas fa-user-plus ml-1"></i> הוסף לקוח
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
        
        <!-- Search and Filters -->
        <div class="bg-white shadow-sm rounded-2xl overflow-hidden p-6 mb-6">
            <div class="flex flex-wrap items-center justify-between">
                <form action="customers.php" method="GET" class="flex items-center flex-grow mb-4 sm:mb-0">
                    <div class="relative flex-grow">
                        <input type="text" name="search" id="search" placeholder="חיפוש לקוח לפי שם, טלפון או אימייל..." 
                            class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                            value="<?php echo htmlspecialchars($search); ?>">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center">
                            <button type="submit" class="text-gray-500 focus:outline-none">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="ml-4">
                        <select name="filter" id="filter" class="px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                              onchange="this.form.submit()">
                            <option value="" <?php echo $filter == '' ? 'selected' : ''; ?>>מיון לפי שם</option>
                            <option value="recent" <?php echo $filter == 'recent' ? 'selected' : ''; ?>>נוספו לאחרונה</option>
                            <option value="active" <?php echo $filter == 'active' ? 'selected' : ''; ?>>לקוחות פעילים</option>
                        </select>
                    </div>
                </form>
                
                <div class="flex items-center">
                    <a href="customers.php" class="text-primary hover:text-primary-dark font-medium">
                        <i class="fas fa-sync-alt mr-1"></i> איפוס סינון
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Customers List -->
        <div class="bg-white shadow-sm rounded-2xl overflow-hidden">
            <?php if (empty($customers)): ?>
                <div class="p-8 text-center">
                    <?php if (!empty($search)): ?>
                        <p class="text-gray-500 mb-4">לא נמצאו לקוחות התואמים את החיפוש "<?php echo htmlspecialchars($search); ?>"</p>
                        <a href="customers.php" class="text-primary hover:text-primary-dark font-medium">נקה חיפוש</a>
                    <?php else: ?>
                        <p class="text-gray-500 mb-4">עדיין אין לקוחות במערכת</p>
                        <button type="button" class="bg-primary hover:bg-primary-dark text-white font-bold py-2 px-4 rounded-xl transition duration-300" onclick="openAddModal()">
                            הוסף לקוח חדש
                        </button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">שם לקוח</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">טלפון</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">אימייל</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">תורים</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">תור אחרון</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">פעולות</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <a href="customer_details.php?id=<?php echo $customer['customer_id']; ?>" class="font-medium text-primary hover:text-primary-dark">
                                            <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm dir-ltr">
                                        <a href="tel:<?php echo htmlspecialchars($customer['phone']); ?>" class="text-gray-600 hover:text-primary">
                                            <?php echo htmlspecialchars($customer['phone']); ?>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm dir-ltr">
                                        <?php if (!empty($customer['email'])): ?>
                                            <a href="mailto:<?php echo htmlspecialchars($customer['email']); ?>" class="text-gray-600 hover:text-primary">
                                                <?php echo htmlspecialchars($customer['email']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <a href="appointments.php?customer_id=<?php echo $customer['customer_id']; ?>" class="text-primary hover:text-primary-dark">
                                            <?php echo $customer['appointment_count']; ?>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                        <?php if (!empty($customer['last_appointment'])): ?>
                                            <?php echo date('d/m/Y', strtotime($customer['last_appointment'])); ?>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="appointments.php?new=1&customer_id=<?php echo $customer['customer_id']; ?>" class="text-green-600 hover:text-green-900 mr-3" title="קבע תור חדש">
                                            <i class="fas fa-calendar-plus"></i>
                                        </a>
                                        <button type="button" class="text-primary hover:text-primary-dark mr-3" onclick="editCustomer(<?php echo $customer['customer_id']; ?>)" title="ערוך לקוח">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($customer['appointment_count'] == 0): ?>
                                            <button type="button" class="text-red-600 hover:text-red-900" onclick="confirmDelete(<?php echo $customer['customer_id']; ?>, '<?php echo htmlspecialchars(addslashes($customer['first_name'] . ' ' . $customer['last_name'])); ?>')" title="מחק לקוח">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="text-gray-400 cursor-not-allowed" title="לא ניתן למחוק לקוח עם היסטוריית תורים">
                                                <i class="fas fa-trash-alt"></i>
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
                                    מציג <?php echo min($total_records, $offset + 1); ?> עד <?php echo min($total_records, $offset + count($customers)); ?> מתוך <?php echo $total_records; ?> לקוחות
                                </p>
                            </div>
                            <div>
                                <nav class="flex items-center">
                                    <?php if ($page > 1): ?>
                                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>" class="px-3 py-1 rounded-md bg-gray-200 text-gray-600 hover:bg-gray-300 mr-2">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="px-3 py-1 rounded-md bg-gray-100 text-gray-400 mr-2">
                                            <i class="fas fa-chevron-right"></i>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php
                                    // הצגת מספרי עמודים עם דילוג
                                    $range = 2; // מספר העמודים שיוצגו לפני ואחרי העמוד הנוכחי
                                    
                                    // התחלת טווח
                                    $start_page = max(1, $page - $range);
                                    // סוף טווח
                                    $end_page = min($total_pages, $page + $range);
                                    
                                    // הצגת עמוד 1 ו"..."
                                    if ($start_page > 1) {
                                        echo '<a href="?page=1&search=' . urlencode($search) . '&filter=' . urlencode($filter) . '" class="px-3 py-1 rounded-md text-gray-600 hover:bg-gray-200 mx-1">1</a>';
                                        if ($start_page > 2) {
                                            echo '<span class="px-2 text-gray-500">...</span>';
                                        }
                                    }
                                    
                                    // עמודים בטווח
                                    for ($i = $start_page; $i <= $end_page; $i++) {
                                        if ($i == $page) {
                                            echo '<span class="px-3 py-1 rounded-md bg-primary text-white mx-1">' . $i . '</span>';
                                        } else {
                                            echo '<a href="?page=' . $i . '&search=' . urlencode($search) . '&filter=' . urlencode($filter) . '" class="px-3 py-1 rounded-md text-gray-600 hover:bg-gray-200 mx-1">' . $i . '</a>';
                                        }
                                    }
                                    
                                    // הצגת "..." ועמוד אחרון
                                    if ($end_page < $total_pages) {
                                        if ($end_page < $total_pages - 1) {
                                            echo '<span class="px-2 text-gray-500">...</span>';
                                        }
                                        echo '<a href="?page=' . $total_pages . '&search=' . urlencode($search) . '&filter=' . urlencode($filter) . '" class="px-3 py-1 rounded-md text-gray-600 hover:bg-gray-200 mx-1">' . $total_pages . '</a>';
                                    }
                                    ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>" class="px-3 py-1 rounded-md bg-gray-200 text-gray-600 hover:bg-gray-300 ml-2">
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
    
    <!-- Modal להוספת/עריכת לקוח -->
    <div id="customerModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-2xl p-6 max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-6">
                <h2 id="modalTitle" class="text-2xl font-bold">הוספת לקוח חדש</h2>
                <button type="button" class="text-gray-500 hover:text-gray-700" onclick="closeModal()">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="customerForm" method="POST" action="api/save_customer.php">
                <input type="hidden" id="customer_id" name="customer_id" value="">
                
                <div class="grid grid-cols-1 gap-4 mb-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="first_name" class="block text-gray-700 font-medium mb-2">שם פרטי *</label>
                            <input type="text" id="first_name" name="first_name" 
                                   class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                   required>
                        </div>
                        <div>
                            <label for="last_name" class="block text-gray-700 font-medium mb-2">שם משפחה *</label>
                            <input type="text" id="last_name" name="last_name" 
                                   class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                   required>
                        </div>
                    </div>
                    
                    <div>
                        <label for="phone" class="block text-gray-700 font-medium mb-2">טלפון נייד *</label>
                        <input type="tel" id="phone" name="phone" dir="ltr"
                               class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                               required>
                    </div>
                    
                    <div>
                        <label for="email" class="block text-gray-700 font-medium mb-2">אימייל</label>
                        <input type="email" id="email" name="email" dir="ltr"
                               class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                    </div>
                    
                    <div>
                        <label for="notes" class="block text-gray-700 font-medium mb-2">הערות</label>
                        <textarea id="notes" name="notes" rows="3"
                                 class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"></textarea>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-4 space-x-reverse">
                    <button type="button" class="px-4 py-2 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-100 focus:outline-none" onclick="closeModal()">ביטול</button>
                    <button type="submit" id="saveCustomerBtn" class="px-4 py-2 bg-primary text-white rounded-xl hover:bg-primary-dark focus:outline-none">שמור לקוח</button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
    // פתיחת מודל להוספת לקוח
    function openAddModal() {
        // איפוס הטופס
        document.getElementById('customerForm').reset();
        document.getElementById('customer_id').value = '';
        document.getElementById('modalTitle').innerText = 'הוספת לקוח חדש';
        document.getElementById('saveCustomerBtn').innerText = 'הוסף לקוח';
        
        // פתיחת המודל
        document.getElementById('customerModal').classList.remove('hidden');
    }
    
    // פתיחת מודל לעריכת לקוח
    function editCustomer(customerId) {
        // שליפת פרטי הלקוח מהשרת
        fetch('api/get_customer.php?id=' + customerId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const customer = data.customer;
                    
                    // מילוי הטופס בנתונים
                    document.getElementById('customer_id').value = customer.customer_id;
                    document.getElementById('first_name').value = customer.first_name;
                    document.getElementById('last_name').value = customer.last_name;
                    document.getElementById('phone').value = customer.phone;
                    document.getElementById('email').value = customer.email || '';
                    document.getElementById('notes').value = customer.notes || '';
                    
                    // שינוי כותרת וכפתור
                    document.getElementById('modalTitle').innerText = 'עריכת לקוח';
                    document.getElementById('saveCustomerBtn').innerText = 'עדכן לקוח';
                    
                    // פתיחת המודל
                    document.getElementById('customerModal').classList.remove('hidden');
                } else {
                    alert('אירעה שגיאה: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('אירעה שגיאה בטעינת פרטי הלקוח');
            });
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