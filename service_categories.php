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

// משתנים להודעות
$error_message = "";
$success_message = "";

// טיפול במחיקת קטגוריה
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $category_id = intval($_GET['delete']);
    
    try {
        // בדיקה שהקטגוריה שייכת לטננט
        $query = "SELECT category_id FROM service_categories WHERE category_id = :category_id AND tenant_id = :tenant_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":category_id", $category_id);
        $stmt->bindParam(":tenant_id", $tenant_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // בדיקה אם יש שירותים בקטגוריה
            $query = "SELECT service_id FROM services WHERE category_id = :category_id AND tenant_id = :tenant_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":category_id", $category_id);
            $stmt->bindParam(":tenant_id", $tenant_id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $error_message = "לא ניתן למחוק קטגוריה שיש בה שירותים. יש לשייך את השירותים לקטגוריה אחרת או למחוק אותם תחילה.";
            } else {
                // מחיקת הקטגוריה
                $query = "DELETE FROM service_categories WHERE category_id = :category_id AND tenant_id = :tenant_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":category_id", $category_id);
                $stmt->bindParam(":tenant_id", $tenant_id);
                
                if ($stmt->execute()) {
                    $success_message = "הקטגוריה נמחקה בהצלחה";
                } else {
                    $error_message = "אירעה שגיאה במחיקת הקטגוריה";
                }
            }
        } else {
            $error_message = "הקטגוריה לא נמצאה או אינה שייכת לעסק שלך";
        }
    } catch (PDOException $e) {
        $error_message = "שגיאת מסד נתונים: " . $e->getMessage();
    }
}

// טיפול בהוספה או עדכון קטגוריה
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_category']) || isset($_POST['update_category'])) {
        $category_name = sanitizeInput($_POST['category_name']);
        $description = sanitizeInput($_POST['description']);
        $display_order = !empty($_POST['display_order']) ? intval($_POST['display_order']) : 0;
        
        if (empty($category_name)) {
            $error_message = "שם הקטגוריה לא יכול להיות ריק";
        } else {
            try {
                // אם זה עדכון של קטגוריה קיימת
                if (isset($_POST['update_category']) && !empty($_POST['category_id'])) {
                    $category_id = intval($_POST['category_id']);
                    
                    // בדיקה שהקטגוריה שייכת לטננט
                    $query = "SELECT category_id FROM service_categories WHERE category_id = :category_id AND tenant_id = :tenant_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(":category_id", $category_id);
                    $stmt->bindParam(":tenant_id", $tenant_id);
                    $stmt->execute();
                    
                    if ($stmt->rowCount() > 0) {
                        // עדכון הקטגוריה
                        $query = "UPDATE service_categories SET 
                                 name = :name,
                                 description = :description,
                                 display_order = :display_order
                                 WHERE category_id = :category_id AND tenant_id = :tenant_id";
                        
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(":name", $category_name);
                        $stmt->bindParam(":description", $description);
                        $stmt->bindParam(":display_order", $display_order);
                        $stmt->bindParam(":category_id", $category_id);
                        $stmt->bindParam(":tenant_id", $tenant_id);
                        
                        if ($stmt->execute()) {
                            $success_message = "הקטגוריה עודכנה בהצלחה";
                        } else {
                            $error_message = "אירעה שגיאה בעדכון הקטגוריה";
                        }
                    } else {
                        $error_message = "הקטגוריה לא נמצאה או אינה שייכת לעסק שלך";
                    }
                }
                // אם זו הוספת קטגוריה חדשה
                else if (isset($_POST['add_category'])) {
                    // הוספת קטגוריה חדשה
                    $query = "INSERT INTO service_categories (tenant_id, name, description, display_order) 
                             VALUES (:tenant_id, :name, :description, :display_order)";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(":tenant_id", $tenant_id);
                    $stmt->bindParam(":name", $category_name);
                    $stmt->bindParam(":description", $description);
                    $stmt->bindParam(":display_order", $display_order);
                    
                    if ($stmt->execute()) {
                        $success_message = "הקטגוריה נוספה בהצלחה";
                    } else {
                        $error_message = "אירעה שגיאה בהוספת הקטגוריה";
                    }
                }
            } catch (PDOException $e) {
                $error_message = "שגיאת מסד נתונים: " . $e->getMessage();
            }
        }
    }
}

// שליפת כל הקטגוריות
$categories = [];
try {
    $query = "SELECT c.*, COUNT(s.service_id) as service_count 
              FROM service_categories c 
              LEFT JOIN services s ON c.category_id = s.category_id 
              WHERE c.tenant_id = :tenant_id 
              GROUP BY c.category_id 
              ORDER BY c.display_order ASC, c.name ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "שגיאת מסד נתונים: " . $e->getMessage();
}

// כותרת העמוד
$page_title = "ניהול קטגוריות";
?>

<?php include_once "includes/header.php"; ?>
<?php include_once "includes/sidebar.php"; ?>

<!-- Main Content -->
<main class="flex-1 overflow-y-auto py-8">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">ניהול קטגוריות שירותים</h1>
            
            <button type="button" class="bg-primary hover:bg-primary-dark text-white font-bold py-2 px-4 rounded-xl focus:outline-none" onclick="openAddModal()">
                <i class="fas fa-plus mr-1"></i> הוסף קטגוריה חדשה
            </button>
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
        
        <!-- רשימת קטגוריות -->
        <div class="bg-white shadow-sm rounded-2xl overflow-hidden">
            <?php if (empty($categories)): ?>
                <div class="p-6 text-center">
                    <p class="text-gray-500 mb-4">עדיין אין קטגוריות במערכת</p>
                    <button type="button" class="bg-primary hover:bg-primary-dark text-white font-bold py-2 px-4 rounded-xl focus:outline-none" onclick="openAddModal()">
                        <i class="fas fa-plus mr-1"></i> הוסף קטגוריה חדשה
                    </button>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">שם הקטגוריה</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">תיאור</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">סדר תצוגה</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">מספר שירותים</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">פעולות</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-medium"><?php echo htmlspecialchars($category['name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-600 max-w-xs truncate"><?php echo !empty($category['description']) ? htmlspecialchars($category['description']) : '-'; ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo htmlspecialchars($category['display_order']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo $category['service_count']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <button type="button" class="text-primary hover:text-primary-dark mr-2" onclick="editCategory(<?php echo $category['category_id']; ?>, '<?php echo htmlspecialchars(addslashes($category['name'])); ?>', '<?php echo htmlspecialchars(addslashes($category['description'])); ?>', '<?php echo $category['display_order']; ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($category['service_count'] == 0): ?>
                                            <button type="button" class="text-red-600 hover:text-red-900" onclick="confirmDelete(<?php echo $category['category_id']; ?>, '<?php echo htmlspecialchars(addslashes($category['name'])); ?>')">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="text-gray-400 cursor-not-allowed" title="לא ניתן למחוק קטגוריה עם שירותים">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal להוספת/עריכת קטגוריה -->
    <div id="categoryModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-2xl p-6 max-w-lg w-full mx-4">
            <div class="flex justify-between items-center mb-6">
                <h2 id="modalTitle" class="text-2xl font-bold">הוספת קטגוריה חדשה</h2>
                <button type="button" class="text-gray-500 hover:text-gray-700" onclick="closeModal()">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="categoryForm" method="POST" action="service_categories.php">
                <input type="hidden" id="category_id" name="category_id" value="">
                
                <div class="space-y-4">
                    <div>
                        <label for="category_name" class="block text-gray-700 font-medium mb-2">שם הקטגוריה *</label>
                        <input type="text" id="category_name" name="category_name" 
                               class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                               required>
                    </div>
                    
                    <div>
                        <label for="description" class="block text-gray-700 font-medium mb-2">תיאור</label>
                        <textarea id="description" name="description" rows="3"
                        class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"></textarea>
                    </div>
                    
                    <div>
                        <label for="display_order" class="block text-gray-700 font-medium mb-2">סדר תצוגה</label>
                        <input type="number" id="display_order" name="display_order" min="0" 
                               class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                               value="0">
                        <p class="text-xs text-gray-500 mt-1">ערך נמוך יותר יוצג ראשון</p>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-4 space-x-reverse mt-6">
                    <button type="button" class="px-4 py-2 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-100 focus:outline-none" onclick="closeModal()">ביטול</button>
                    <button type="submit" id="addCategoryBtn" name="add_category" class="px-4 py-2 bg-primary text-white rounded-xl hover:bg-primary-dark focus:outline-none">הוסף קטגוריה</button>
                    <button type="submit" id="updateCategoryBtn" name="update_category" class="px-4 py-2 bg-primary text-white rounded-xl hover:bg-primary-dark focus:outline-none hidden">עדכן קטגוריה</button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
    // פתיחת מודל להוספת קטגוריה
    function openAddModal() {
        // איפוס הטופס
        document.getElementById('categoryForm').reset();
        document.getElementById('category_id').value = '';
        document.getElementById('modalTitle').innerText = 'הוספת קטגוריה חדשה';
        
        // הצגת כפתור ההוספה והסתרת כפתור העדכון
        document.getElementById('addCategoryBtn').classList.remove('hidden');
        document.getElementById('updateCategoryBtn').classList.add('hidden');
        
        // פתיחת המודל
        document.getElementById('categoryModal').classList.remove('hidden');
    }
    
    // פתיחת מודל לעריכת קטגוריה
    function editCategory(categoryId, name, description, displayOrder) {
        // מילוי הטופס בנתונים
        document.getElementById('category_id').value = categoryId;
        document.getElementById('category_name').value = name;
        document.getElementById('description').value = description;
        document.getElementById('display_order').value = displayOrder;
        
        // שינוי כותרת וכפתורים
        document.getElementById('modalTitle').innerText = 'עריכת קטגוריה';
        document.getElementById('addCategoryBtn').classList.add('hidden');
        document.getElementById('updateCategoryBtn').classList.remove('hidden');
        
        // פתיחת המודל
        document.getElementById('categoryModal').classList.remove('hidden');
    }
    
    // סגירת המודל
    function closeModal() {
        document.getElementById('categoryModal').classList.add('hidden');
    }
    
    // אישור מחיקה
    function confirmDelete(categoryId, categoryName) {
        if (confirm('האם אתה בטוח שברצונך למחוק את הקטגוריה "' + categoryName + '"?')) {
            window.location.href = 'service_categories.php?delete=' + categoryId;
        }
    }
</script>

<?php include_once "includes/footer.php"; ?>