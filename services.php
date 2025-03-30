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

// טיפול במחיקת שירות
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $service_id = intval($_GET['delete']);
    
    try {
        // בדיקה שהשירות שייך לטננט
        $query = "SELECT service_id FROM services WHERE service_id = :service_id AND tenant_id = :tenant_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":service_id", $service_id);
        $stmt->bindParam(":tenant_id", $tenant_id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // בדיקה אם יש תורים עתידיים עם השירות הזה
            $query = "SELECT appointment_id FROM appointments 
                    WHERE service_id = :service_id 
                    AND tenant_id = :tenant_id 
                    AND start_datetime > NOW()
                    AND status IN ('pending', 'confirmed')";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":service_id", $service_id);
            $stmt->bindParam(":tenant_id", $tenant_id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $error_message = "לא ניתן למחוק שירות עם תורים עתידיים. יש לבטל את התורים תחילה.";
            } else {
                // ניסיון למחוק את השירות
                $query = "DELETE FROM services WHERE service_id = :service_id AND tenant_id = :tenant_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":service_id", $service_id);
                $stmt->bindParam(":tenant_id", $tenant_id);
                
                if ($stmt->execute()) {
                    $success_message = "השירות נמחק בהצלחה";
                } else {
                    $error_message = "אירעה שגיאה במחיקת השירות";
                }
            }
        } else {
            $error_message = "השירות לא נמצא או אינו שייך לעסק שלך";
        }
    } catch (PDOException $e) {
        $error_message = "שגיאת מסד נתונים: " . $e->getMessage();
    }
}

// טיפול בעדכון או הוספת שירות
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_service']) || isset($_POST['update_service'])) {
        // קבלת הנתונים מהטופס
        $service_name = sanitizeInput($_POST['service_name']);
        $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
        $duration = intval($_POST['duration']);
        $price = !empty($_POST['price']) ? floatval($_POST['price']) : 0;
        $buffer_before = !empty($_POST['buffer_before']) ? intval($_POST['buffer_before']) : 0;
        $buffer_after = !empty($_POST['buffer_after']) ? intval($_POST['buffer_after']) : 0;
        $description = sanitizeInput($_POST['description']);
        $color = sanitizeInput($_POST['color']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // בדיקה שהשם לא ריק
        if (empty($service_name)) {
            $error_message = "שם השירות לא יכול להיות ריק";
        }
        // בדיקה שהמשך לא קטן או שווה לאפס
        else if ($duration <= 0) {
            $error_message = "משך השירות חייב להיות גדול מאפס";
        }
        else {
            try {
                // אם זה עדכון של שירות קיים
                if (isset($_POST['update_service']) && !empty($_POST['service_id'])) {
                    $service_id = intval($_POST['service_id']);
                    
                    // בדיקה שהשירות שייך לטננט
                    $query = "SELECT service_id FROM services WHERE service_id = :service_id AND tenant_id = :tenant_id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(":service_id", $service_id);
                    $stmt->bindParam(":tenant_id", $tenant_id);
                    $stmt->execute();
                    
                    if ($stmt->rowCount() > 0) {
                        // עדכון השירות
                        $query = "UPDATE services SET 
                                name = :name,
                                category_id = :category_id,
                                description = :description,
                                duration = :duration,
                                price = :price,
                                buffer_time_before = :buffer_before,
                                buffer_time_after = :buffer_after,
                                color = :color,
                                is_active = :is_active
                                WHERE service_id = :service_id AND tenant_id = :tenant_id";
                        
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(":name", $service_name);
                        $stmt->bindParam(":category_id", $category_id);
                        $stmt->bindParam(":description", $description);
                        $stmt->bindParam(":duration", $duration);
                        $stmt->bindParam(":price", $price);
                        $stmt->bindParam(":buffer_before", $buffer_before);
                        $stmt->bindParam(":buffer_after", $buffer_after);
                        $stmt->bindParam(":color", $color);
                        $stmt->bindParam(":is_active", $is_active);
                        $stmt->bindParam(":service_id", $service_id);
                        $stmt->bindParam(":tenant_id", $tenant_id);
                        
                        if ($stmt->execute()) {
                            $success_message = "השירות עודכן בהצלחה";
                        } else {
                            $error_message = "אירעה שגיאה בעדכון השירות";
                        }
                    } else {
                        $error_message = "השירות לא נמצא או אינו שייך לעסק שלך";
                    }
                }
                // אם זו הוספת שירות חדש
                else if (isset($_POST['add_service'])) {
                    // הוספת שירות חדש
                    $query = "INSERT INTO services (tenant_id, name, category_id, description, duration, price, buffer_time_before, buffer_time_after, color, is_active) 
                            VALUES (:tenant_id, :name, :category_id, :description, :duration, :price, :buffer_before, :buffer_after, :color, :is_active)";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(":tenant_id", $tenant_id);
                    $stmt->bindParam(":name", $service_name);
                    $stmt->bindParam(":category_id", $category_id);
                    $stmt->bindParam(":description", $description);
                    $stmt->bindParam(":duration", $duration);
                    $stmt->bindParam(":price", $price);
                    $stmt->bindParam(":buffer_before", $buffer_before);
                    $stmt->bindParam(":buffer_after", $buffer_after);
                    $stmt->bindParam(":color", $color);
                    $stmt->bindParam(":is_active", $is_active);
                    
                    if ($stmt->execute()) {
                        $service_id = $db->lastInsertId();
                        
                        // אם יש אנשי צוות לשייך
                        if (isset($_POST['staff']) && is_array($_POST['staff'])) {
                            foreach ($_POST['staff'] as $staff_id) {
                                $query = "INSERT INTO service_staff (service_id, staff_id) VALUES (:service_id, :staff_id)";
                                $stmt = $db->prepare($query);
                                $stmt->bindParam(":service_id", $service_id);
                                $stmt->bindParam(":staff_id", $staff_id);
                                $stmt->execute();
                            }
                        }
                        
                        $success_message = "השירות נוסף בהצלחה";
                    } else {
                        $error_message = "אירעה שגיאה בהוספת השירות";
                    }
                }
            } catch (PDOException $e) {
                $error_message = "שגיאת מסד נתונים: " . $e->getMessage();
            }
        }
    }
}

// שליפת קטגוריות
$categories = [];
try {
    $query = "SELECT * FROM service_categories WHERE tenant_id = :tenant_id ORDER BY display_order ASC, name ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "שגיאת מסד נתונים: " . $e->getMessage();
}

// שליפת אנשי צוות
$staff_members = [];
try {
    $query = "SELECT * FROM staff WHERE tenant_id = :tenant_id AND is_active = 1 ORDER BY name ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    $staff_members = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "שגיאת מסד נתונים: " . $e->getMessage();
}

// שליפת כל השירותים
$services = [];
try {
    $query = "SELECT s.*, c.name as category_name 
             FROM services s 
             LEFT JOIN service_categories c ON s.category_id = c.category_id 
             WHERE s.tenant_id = :tenant_id 
             ORDER BY s.category_id, s.name ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    $services = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "שגיאת מסד נתונים: " . $e->getMessage();
}

// כותרת העמוד
$page_title = "ניהול שירותים";
?>

<?php include_once "includes/header.php"; ?>
<?php include_once "includes/sidebar.php"; ?>

<!-- Main Content -->
<main class="flex-1 overflow-y-auto py-8">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center mb-6">
    <h1 class="text-3xl font-bold text-gray-800">ניהול שירותים</h1>
    
    <div class="flex">
        <a href="service_categories.php" class="bg-secondary hover:bg-secondary-dark text-white font-bold py-2 px-4 rounded-xl ml-2">
            <i class="fas fa-tags mr-1"></i> ניהול קטגוריות
        </a>
        
        <a href="service_order.php" class="bg-secondary hover:bg-secondary-dark text-white font-bold py-2 px-4 rounded-xl ml-2">
            <i class="fas fa-sort mr-1"></i> סדר תצוגה
        </a>
        
        <button type="button" class="bg-primary hover:bg-primary-dark text-white font-bold py-2 px-4 rounded-xl ml-2" onclick="openAddModal()">
            <i class="fas fa-plus mr-1"></i> הוסף שירות
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
        
        <!-- קטגוריות ושירותים -->
        <div class="bg-white shadow-sm rounded-2xl overflow-hidden">
            <?php if (empty($services)): ?>
                <div class="p-6 text-center">
                    <p class="text-gray-500 mb-4">עדיין אין שירותים במערכת</p>
                    <button type="button" class="bg-primary hover:bg-primary-dark text-white font-bold py-2 px-4 rounded-xl focus:outline-none" onclick="openAddModal()">
                        <i class="fas fa-plus mr-1"></i> הוסף שירות חדש
                    </button>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">שם השירות</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">קטגוריה</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">משך (דקות)</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">מחיר</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">סטטוס</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">פעולות</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($services as $service): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="h-4 w-4 rounded-full" style="background-color: <?php echo htmlspecialchars($service['color']); ?>"></div>
                                            <div class="mr-3 font-medium"><?php echo htmlspecialchars($service['name']); ?></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo !empty($service['category_name']) ? htmlspecialchars($service['category_name']) : '-'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo htmlspecialchars($service['duration']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo ($service['price'] > 0) ? htmlspecialchars(number_format($service['price'], 0)) . ' ₪' : '-'; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($service['is_active']): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">פעיל</span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">לא פעיל</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
    <a href="service_staff.php?service_id=<?php echo $service['service_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-2" title="שיוך אנשי צוות">
        <i class="fas fa-user-friends"></i>
    </a>
    <button type="button" class="text-primary hover:text-primary-dark mr-2" onclick="editService(<?php echo $service['service_id']; ?>)">
        <i class="fas fa-edit"></i>
    </button>
    <button type="button" class="text-red-600 hover:text-red-900" onclick="confirmDelete(<?php echo $service['service_id']; ?>, '<?php echo htmlspecialchars(addslashes($service['name'])); ?>')">
        <i class="fas fa-trash-alt"></i>
    </button>
</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal להוספת/עריכת שירות -->
    <div id="serviceModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-2xl p-6 max-w-2xl w-full mx-4">
            <div class="flex justify-between items-center mb-6">
                <h2 id="modalTitle" class="text-2xl font-bold">הוספת שירות חדש</h2>
                <button type="button" class="text-gray-500 hover:text-gray-700" onclick="closeModal()">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="serviceForm" method="POST" action="services.php">
                <input type="hidden" id="service_id" name="service_id" value="">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div class="md:col-span-2">
                        <label for="service_name" class="block text-gray-700 font-medium mb-2">שם השירות *</label>
                        <input type="text" id="service_name" name="service_name" 
                               class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                               required>
                    </div>
                    
                    <div>
                        <label for="category_id" class="block text-gray-700 font-medium mb-2">קטגוריה</label>
                        <select id="category_id" name="category_id" class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                            <option value="">ללא קטגוריה</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['category_id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="duration" class="block text-gray-700 font-medium mb-2">משך בדקות *</label>
                        <input type="number" id="duration" name="duration" min="5" step="5" 
                               class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                               required value="30">
                    </div>
                    
                    <div>
                        <label for="price" class="block text-gray-700 font-medium mb-2">מחיר</label>
                        <div class="relative">
                            <input type="number" id="price" name="price" min="0" step="1" 
                                   class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-500">₪</span>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label for="color" class="block text-gray-700 font-medium mb-2">צבע</label>
                        <input type="color" id="color" name="color" 
                               class="w-full h-10 px-2 py-1 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                               value="#3498db">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label for="description" class="block text-gray-700 font-medium mb-2">תיאור</label>
                        <textarea id="description" name="description" rows="3"
                                  class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"></textarea>
                    </div>
                    
                    <div>
                        <label for="buffer_before" class="block text-gray-700 font-medium mb-2">זמן חציצה לפני (דקות)</label>
                        <input type="number" id="buffer_before" name="buffer_before" min="0" step="5" 
                               class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                               value="0">
                    </div>
                    
                    <div>
                        <label for="buffer_after" class="block text-gray-700 font-medium mb-2">זמן חציצה אחרי (דקות)</label>
                        <input type="number" id="buffer_after" name="buffer_after" min="0" step="5" 
                               class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                               value="0">
                    </div>
                    
                    <div class="md:col-span-2 flex items-center">
                        <input type="checkbox" id="is_active" name="is_active" class="w-5 h-5 text-primary border-2 border-gray-300 rounded focus:ring-primary" checked>
                        <label for="is_active" class="mr-2 text-gray-700">שירות פעיל</label>
                    </div>
                    
                    <div id="staff_selection" class="md:col-span-2">
                        <label class="block text-gray-700 font-medium mb-2">נותני שירות</label>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                            <?php foreach ($staff_members as $member): ?>
                            <div class="flex items-center">
                                <input type="checkbox" id="staff_<?php echo $member['staff_id']; ?>" name="staff[]" value="<?php echo $member['staff_id']; ?>" class="w-5 h-5 text-primary border-2 border-gray-300 rounded focus:ring-primary">
                                <label for="staff_<?php echo $member['staff_id']; ?>" class="mr-2 text-gray-700"><?php echo htmlspecialchars($member['name']); ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-4 space-x-reverse">
                    <button type="button" class="px-4 py-2 border border-gray-300 rounded-xl text-gray-700 hover:bg-gray-100 focus:outline-none" onclick="closeModal()">ביטול</button>
                    <button type="submit" id="addServiceBtn" name="add_service" class="px-4 py-2 bg-primary text-white rounded-xl hover:bg-primary-dark focus:outline-none">הוסף שירות</button>
                    <button type="submit" id="updateServiceBtn" name="update_service" class="px-4 py-2 bg-primary text-white rounded-xl hover:bg-primary-dark focus:outline-none hidden">עדכן שירות</button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
    // פתיחת מודל להוספת שירות
    function openAddModal() {
        // איפוס הטופס
        document.getElementById('serviceForm').reset();
        document.getElementById('service_id').value = '';
        document.getElementById('modalTitle').innerText = 'הוספת שירות חדש';
        
        // הצגת כפתור ההוספה והסתרת כפתור העדכון
        document.getElementById('addServiceBtn').classList.remove('hidden');
        document.getElementById('updateServiceBtn').classList.add('hidden');
        
        // הצגת בחירת צוות
        document.getElementById('staff_selection').classList.remove('hidden');
        
        // פתיחת המודל
        document.getElementById('serviceModal').classList.remove('hidden');
    }
    
    // פתיחת מודל לעריכת שירות
    function editService(serviceId) {
        // שליפת פרטי השירות מהשרת
        fetch('api/get_service.php?service_id=' + serviceId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const service = data.service;
                    
                    // מילוי הטופס בנתונים
                    document.getElementById('service_id').value = service.service_id;
                    document.getElementById('service_name').value = service.name;
                    document.getElementById('category_id').value = service.category_id || '';
                    document.getElementById('duration').value = service.duration;
                    document.getElementById('price').value = service.price;
                    document.getElementById('buffer_before').value = service.buffer_time_before;
                    document.getElementById('buffer_after').value = service.buffer_time_after;
                    document.getElementById('color').value = service.color;
                    document.getElementById('description').value = service.description;
                    document.getElementById('is_active').checked = service.is_active == 1;
                    
                    // סימון נותני שירות רלוונטיים
                    if (service.staff && Array.isArray(service.staff)) {
                        // איפוס כל הצ'קבוקסים
                        document.querySelectorAll('input[name="staff[]"]').forEach(checkbox => {
                            checkbox.checked = false;
                        });
                        
                        // סימון הרלוונטיים
                        service.staff.forEach(staffId => {
                            const checkbox = document.getElementById('staff_' + staffId);
                            if (checkbox) checkbox.checked = true;
                        });
                    }
                    
                    // שינוי כותרת וכפתורים
                    document.getElementById('modalTitle').innerText = 'עריכת שירות';
                    document.getElementById('addServiceBtn').classList.add('hidden');
                    document.getElementById('updateServiceBtn').classList.remove('hidden');
                    
                    // הסתרת בחירת צוות בעריכה (מתבצע בעמוד נפרד)
                    document.getElementById('staff_selection').classList.add('hidden');
                    
                    // פתיחת המודל
                    document.getElementById('serviceModal').classList.remove('hidden');
                } else {
                    alert('אירעה שגיאה: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('אירעה שגיאה בטעינת פרטי השירות');
            });
    }
    
    // סגירת המודל
    function closeModal() {
        document.getElementById('serviceModal').classList.add('hidden');
    }
    
    // אישור מחיקה
    function confirmDelete(serviceId, serviceName) {
        if (confirm('האם אתה בטוח שברצונך למחוק את השירות "' + serviceName + '"?')) {
            window.location.href = 'services.php?delete=' + serviceId;
        }
    }
</script>

<?php include_once "includes/footer.php"; ?>