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
$page_title = "רשימות המתנה";

// יצירת חיבור למסד הנתונים
$database = new Database();
$db = $database->getConnection();

// קבלת ה-tenant_id מהסשן
$tenant_id = $_SESSION['tenant_id'];

// סינון והגדרת סדר התוצאות
$status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$service_id = isset($_GET['service_id']) ? intval($_GET['service_id']) : 0;
$staff_id = isset($_GET['staff_id']) ? intval($_GET['staff_id']) : 0;
$sort = isset($_GET['sort']) ? sanitizeInput($_GET['sort']) : 'created_at';
$order = isset($_GET['order']) ? sanitizeInput($_GET['order']) : 'DESC';

// שליפת רשימת ההמתנה
try {
    // בניית שאילתה עם סינון
    $query = "SELECT w.*, c.first_name, c.last_name, c.phone, c.email, s.name as service_name, 
              st.name as staff_name 
              FROM waitlist w 
              JOIN customers c ON w.customer_id = c.customer_id 
              JOIN services s ON w.service_id = s.service_id 
              LEFT JOIN staff st ON w.staff_id = st.staff_id 
              WHERE w.tenant_id = :tenant_id";
    
    // הוספת תנאי סינון
    if (!empty($status)) {
        $query .= " AND w.status = :status";
    }
    
    if ($service_id > 0) {
        $query .= " AND w.service_id = :service_id";
    }
    
    if ($staff_id > 0) {
        $query .= " AND w.staff_id = :staff_id";
    }
    
    // סידור התוצאות
    $query .= " ORDER BY w.$sort $order";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    
    if (!empty($status)) {
        $stmt->bindParam(":status", $status);
    }
    
    if ($service_id > 0) {
        $stmt->bindParam(":service_id", $service_id);
    }
    
    if ($staff_id > 0) {
        $stmt->bindParam(":staff_id", $staff_id);
    }
    
    $stmt->execute();
    $waitlist_items = $stmt->fetchAll();
    
    // שליפת רשימת שירותים לסינון
    $query = "SELECT service_id, name FROM services WHERE tenant_id = :tenant_id AND is_active = 1 ORDER BY name";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    $services = $stmt->fetchAll();
    
    // שליפת רשימת אנשי צוות לסינון
    $query = "SELECT staff_id, name FROM staff WHERE tenant_id = :tenant_id AND is_active = 1 ORDER BY name";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    $staff_members = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_message = "שגיאת מסד נתונים: " . $e->getMessage();
}

// טיפול בהסרה מרשימת המתנה (אם המזהה נשלח)
if (isset($_GET['remove']) && !empty($_GET['remove'])) {
    $id = intval($_GET['remove']);
    
    try {
        $query = "DELETE FROM waitlist WHERE id = :id AND tenant_id = :tenant_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->bindParam(":tenant_id", $tenant_id);
        
        if ($stmt->execute()) {
            // הפניה מחדש כדי להימנע משליחה חוזרת של הטופס
            redirect("waitlist.php?status=removed");
        }
    } catch (PDOException $e) {
        $error_message = "שגיאה בהסרה מרשימת ההמתנה: " . $e->getMessage();
    }
}

// טיפול בשינוי סטטוס (אם נשלח)
if (isset($_GET['notify']) && !empty($_GET['notify'])) {
    $id = intval($_GET['notify']);
    
    try {
        $query = "UPDATE waitlist SET status = 'notified' WHERE id = :id AND tenant_id = :tenant_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->bindParam(":tenant_id", $tenant_id);
        
        if ($stmt->execute()) {
            // הפניה מחדש כדי להימנע משליחה חוזרת של הטופס
            redirect("waitlist.php?status=notified");
        }
    } catch (PDOException $e) {
        $error_message = "שגיאה בעדכון סטטוס: " . $e->getMessage();
    }
}

// מחרוזות הודעות משוב למשתמש
$status_messages = [
    'removed' => 'פריט הוסר בהצלחה מרשימת ההמתנה',
    'notified' => 'הלקוח סומן כ"קיבל הודעה" בהצלחה',
    'added' => 'הלקוח נוסף בהצלחה לרשימת ההמתנה'
];

// הודעת סטטוס, אם קיימת
$status_message = '';
if (isset($_GET['status']) && array_key_exists($_GET['status'], $status_messages)) {
    $status_message = $status_messages[$_GET['status']];
}

?>

<?php include_once "includes/header.php"; ?>
<?php include_once "includes/sidebar.php"; ?>

<!-- Main Content -->
<main class="flex-1 overflow-y-auto py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">רשימות המתנה</h1>
            
            <button id="addToWaitlistBtn" class="bg-primary hover:bg-primary-dark text-white font-bold py-2 px-4 rounded-xl transition duration-300 flex items-center">
                <i class="fas fa-plus ml-2"></i>
                הוסף לקוח לרשימת המתנה
            </button>
        </div>
        
        <?php if (!empty($status_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6" role="alert">
                <?php echo $status_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6" role="alert">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <!-- סינון רשימת המתנה -->
        <div class="bg-white shadow-sm rounded-2xl p-6 mb-8">
            <h2 class="text-lg font-semibold mb-4">סינון רשימת המתנה</h2>
            
            <form action="waitlist.php" method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="status" class="block text-gray-700 font-medium mb-2">סטטוס</label>
                    <select id="status" name="status" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                        <option value="">כל הסטטוסים</option>
                        <option value="waiting" <?php echo $status === 'waiting' ? 'selected' : ''; ?>>ממתין</option>
                        <option value="notified" <?php echo $status === 'notified' ? 'selected' : ''; ?>>קיבל הודעה</option>
                        <option value="booked" <?php echo $status === 'booked' ? 'selected' : ''; ?>>הוזמן תור</option>
                        <option value="expired" <?php echo $status === 'expired' ? 'selected' : ''; ?>>פג תוקף</option>
                    </select>
                </div>
                
                <div>
                    <label for="service_id" class="block text-gray-700 font-medium mb-2">שירות</label>
                    <select id="service_id" name="service_id" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                        <option value="0">כל השירותים</option>
                        <?php foreach($services as $service): ?>
                            <option value="<?php echo $service['service_id']; ?>" <?php echo $service_id == $service['service_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($service['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="staff_id" class="block text-gray-700 font-medium mb-2">נותן שירות</label>
                    <select id="staff_id" name="staff_id" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                        <option value="0">כל נותני השירות</option>
                        <?php foreach($staff_members as $member): ?>
                            <option value="<?php echo $member['staff_id']; ?>" <?php echo $staff_id == $member['staff_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($member['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="sort" class="block text-gray-700 font-medium mb-2">מיון לפי</label>
                    <select id="sort" name="sort" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                        <option value="created_at" <?php echo $sort === 'created_at' ? 'selected' : ''; ?>>תאריך הוספה</option>
                        <option value="preferred_date" <?php echo $sort === 'preferred_date' ? 'selected' : ''; ?>>תאריך מועדף</option>
                    </select>
                </div>
                
                <div class="md:col-span-4 mt-4">
                    <button type="submit" class="bg-primary hover:bg-primary-dark text-white font-bold py-2 px-6 rounded-xl transition duration-300">
                        סנן
                    </button>
                    <a href="waitlist.php" class="mr-2 text-gray-500 hover:text-gray-700 font-medium">נקה סינון</a>
                </div>
            </form>
        </div>
        
        <!-- טבלת רשימת המתנה -->
        <div class="bg-white shadow-sm rounded-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">לקוח</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">שירות</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">נותן שירות</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">תאריך מועדף</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">שעות מועדפות</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">סטטוס</th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">פעולות</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($waitlist_items)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-10 text-center text-gray-500">
                                    <i class="fas fa-clipboard-list text-gray-300 text-4xl mb-3"></i>
                                    <p>לא נמצאו פריטים ברשימת ההמתנה</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($waitlist_items as $item): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div>
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($item['first_name'] . ' ' . $item['last_name']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500 flex items-center">
                                                    <a href="tel:<?php echo htmlspecialchars($item['phone']); ?>" class="hover:text-primary">
                                                        <i class="fas fa-phone-alt text-xs ml-1"></i>
                                                        <?php echo htmlspecialchars($item['phone']); ?>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($item['service_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php echo !empty($item['staff_name']) ? htmlspecialchars($item['staff_name']) : 'כל נותן שירות'; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php 
                                            if (!empty($item['preferred_date'])) {
                                                echo date('d/m/Y', strtotime($item['preferred_date']));
                                            } else {
                                                echo 'כל תאריך';
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php 
                                            if (!empty($item['preferred_time_start']) && !empty($item['preferred_time_end'])) {
                                                echo date('H:i', strtotime($item['preferred_time_start'])) . ' - ' . 
                                                     date('H:i', strtotime($item['preferred_time_end']));
                                            } else {
                                                echo 'כל שעה';
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $status_class = '';
                                        $status_text = '';
                                        
                                        switch ($item['status']) {
                                            case 'waiting':
                                                $status_class = 'bg-yellow-100 text-yellow-800';
                                                $status_text = 'ממתין';
                                                break;
                                            case 'notified':
                                                $status_class = 'bg-blue-100 text-blue-800';
                                                $status_text = 'קיבל הודעה';
                                                break;
                                            case 'booked':
                                                $status_class = 'bg-green-100 text-green-800';
                                                $status_text = 'הוזמן תור';
                                                break;
                                            case 'expired':
                                                $status_class = 'bg-gray-100 text-gray-800';
                                                $status_text = 'פג תוקף';
                                                break;
                                        }
                                        ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <div class="flex justify-center space-x-2 space-x-reverse">
                                            <?php if ($item['status'] === 'waiting'): ?>
                                                <a href="?notify=<?php echo $item['id']; ?>" class="text-blue-600 hover:text-blue-900" title="סמן כ'קיבל הודעה'">
                                                    <i class="fas fa-bell"></i>
                                                </a>
                                            <?php endif; ?>
                                                
                                            <a href="appointments.php?customer_id=<?php echo $item['customer_id']; ?>&service_id=<?php echo $item['service_id']; ?>" class="text-green-600 hover:text-green-900" title="קבע תור">
                                                <i class="fas fa-calendar-plus"></i>
                                            </a>
                                            
                                            <a href="#" class="text-gray-600 hover:text-gray-900 view-notes" data-notes="<?php echo htmlspecialchars($item['notes'] ?? ''); ?>" title="הצג הערות">
                                                <i class="fas fa-sticky-note"></i>
                                            </a>
                                            
                                            <a href="?remove=<?php echo $item['id']; ?>" onclick="return confirm('האם אתה בטוח שברצונך להסיר פריט זה מרשימת ההמתנה?');" class="text-red-600 hover:text-red-900" title="הסר מרשימה">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal: הוספה לרשימת המתנה -->
    <div id="addToWaitlistModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-2xl max-w-2xl w-full max-h-screen overflow-y-auto">
            <div class="flex justify-between items-center p-6 border-b">
                <h2 class="text-xl font-bold">הוספת לקוח לרשימת המתנה</h2>
                <button id="closeAddWaitlistModal" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="p-6">
                <form id="addToWaitlistForm" class="space-y-4">
                    <input type="hidden" name="tenant_id" value="<?php echo $tenant_id; ?>">
                    
                    <!-- פרטי לקוח -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="first_name" class="block text-gray-700 font-medium mb-2">שם פרטי</label>
                            <input type="text" id="first_name" name="first_name" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-primary" required>
                        </div>
                        
                        <div>
                            <label for="last_name" class="block text-gray-700 font-medium mb-2">שם משפחה</label>
                            <input type="text" id="last_name" name="last_name" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-primary" required>
                        </div>
                        
                        <div>
                            <label for="phone" class="block text-gray-700 font-medium mb-2">טלפון</label>
                            <input type="tel" id="phone" name="phone" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-primary" required>
                        </div>
                        
                        <div>
                            <label for="email" class="block text-gray-700 font-medium mb-2">אימייל</label>
                            <input type="email" id="email" name="email" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                        </div>
                    </div>
                    
                    <!-- פרטי השירות -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="modal_service_id" class="block text-gray-700 font-medium mb-2">שירות</label>
                            <select id="modal_service_id" name="service_id" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-primary" required>
                                <option value="">בחר שירות</option>
                                <?php foreach($services as $service): ?>
                                    <option value="<?php echo $service['service_id']; ?>">
                                        <?php echo htmlspecialchars($service['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="modal_staff_id" class="block text-gray-700 font-medium mb-2">נותן שירות מועדף (אופציונלי)</label>
                            <select id="modal_staff_id" name="staff_id" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                                <option value="">כל נותן שירות</option>
                                <?php foreach($staff_members as $member): ?>
                                    <option value="<?php echo $member['staff_id']; ?>">
                                        <?php echo htmlspecialchars($member['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- זמן מועדף -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="preferred_date" class="block text-gray-700 font-medium mb-2">תאריך מועדף (אופציונלי)</label>
                            <input type="date" id="preferred_date" name="preferred_date" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                        </div>
                        
                        <div>
                            <label for="preferred_time_start" class="block text-gray-700 font-medium mb-2">משעה (אופציונלי)</label>
                            <input type="time" id="preferred_time_start" name="preferred_time_start" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                        </div>
                        
                        <div>
                            <label for="preferred_time_end" class="block text-gray-700 font-medium mb-2">עד שעה (אופציונלי)</label>
                            <input type="time" id="preferred_time_end" name="preferred_time_end" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                        </div>
                    </div>
                    
                    <!-- הערות -->
                    <div>
                        <label for="notes" class="block text-gray-700 font-medium mb-2">הערות</label>
                        <textarea id="notes" name="notes" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-primary"></textarea>
                    </div>
                    
                    <div class="flex justify-end mt-6">
                        <button type="button" id="cancelAddWaitlist" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-medium py-2 px-6 rounded-xl transition duration-300 ml-2">
                            ביטול
                        </button>
                        <button type="submit" id="submitAddWaitlist" class="bg-primary hover:bg-primary-dark text-white font-bold py-2 px-6 rounded-xl transition duration-300">
                            הוסף לרשימת המתנה
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal: הצגת הערות -->
    <div id="viewNotesModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-2xl max-w-lg w-full">
            <div class="flex justify-between items-center p-6 border-b">
                <h2 class="text-xl font-bold">הערות</h2>
                <button id="closeNotesModal" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="p-6">
                <p id="notesContent" class="text-gray-700 whitespace-pre-line"></p>
                
                <div class="mt-6 text-center">
                    <button id="closeNotesModalBtn" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-medium py-2 px-6 rounded-xl transition duration-300">
                        סגור
                    </button>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // פתיחת וסגירת מודל הוספה לרשימת המתנה
        const addToWaitlistBtn = document.getElementById('addToWaitlistBtn');
        const addToWaitlistModal = document.getElementById('addToWaitlistModal');
        const closeAddWaitlistModal = document.getElementById('closeAddWaitlistModal');
        const cancelAddWaitlist = document.getElementById('cancelAddWaitlist');
        
        addToWaitlistBtn.addEventListener('click', function() {
            addToWaitlistModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden'; // מונע גלילה של העמוד ברקע
        });
        
        closeAddWaitlistModal.addEventListener('click', function() {
            addToWaitlistModal.classList.add('hidden');
            document.body.style.overflow = 'auto'; // מחזיר את הגלילה
        });
        
        cancelAddWaitlist.addEventListener('click', function() {
            addToWaitlistModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        });
        
        // סגירת המודל בלחיצה על הרקע
        addToWaitlistModal.addEventListener('click', function(e) {
            if (e.target === addToWaitlistModal) {
                addToWaitlistModal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        });
        
        // פתיחת וסגירת מודל הצגת הערות
        const viewNotesButtons = document.querySelectorAll('.view-notes');
        const viewNotesModal = document.getElementById('viewNotesModal');
        const closeNotesModal = document.getElementById('closeNotesModal');
        const closeNotesModalBtn = document.getElementById('closeNotesModalBtn');
        const notesContent = document.getElementById('notesContent');
        
        viewNotesButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const notes = this.getAttribute('data-notes') || 'אין הערות';
                notesContent.textContent = notes;
                viewNotesModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            });
        });
        
        closeNotesModal.addEventListener('click', function() {
            viewNotesModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        });
        
        closeNotesModalBtn.addEventListener('click', function() {
            viewNotesModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        });
        
        // סגירת המודל בלחיצה על הרקע
        viewNotesModal.addEventListener('click', function(e) {
            if (e.target === viewNotesModal) {
                viewNotesModal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }
        });
        
        // שליחת טופס הוספה לרשימת המתנה
        const addToWaitlistForm = document.getElementById('addToWaitlistForm');
        
        addToWaitlistForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(addToWaitlistForm);
            const submitBtn = document.getElementById('submitAddWaitlist');
            
            // המרת הפורמט לאובייקט JSON
            const data = {};
            formData.forEach((value, key) => {
                data[key] = value;
            });
            
            // שינוי טקסט הכפתור ומניעת לחיצות נוספות
            submitBtn.disabled = true;
            submitBtn.textContent = 'מוסיף...';
            
            // שליחת הנתונים ל-API
            fetch('api/add_to_waitlist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    // הצלחה - העברה לדף עם הודעת הצלחה
                    window.location.href = 'waitlist.php?status=added';
                } else {
                    // כישלון - הצגת הודעת שגיאה
                    alert('שגיאה: ' + result.message);
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'הוסף לרשימת המתנה';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('אירעה שגיאה בשליחת הבקשה');
                submitBtn.disabled = false;
                submitBtn.textContent = 'הוסף לרשימת המתנה';
            });
        });
    });