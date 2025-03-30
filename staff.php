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
$page_title = "ניהול צוות";

// יצירת חיבור למסד הנתונים
$database = new Database();
$db = $database->getConnection();

// קבלת ה-tenant_id מהסשן
$tenant_id = $_SESSION['tenant_id'];

// פעולת הוספת איש צוות
if (isset($_POST['add_staff'])) {
    $name = sanitizeInput($_POST['name']);
    $email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : null;
    $phone = isset($_POST['phone']) ? sanitizeInput($_POST['phone']) : null;
    $position = isset($_POST['position']) ? sanitizeInput($_POST['position']) : null;
    $bio = isset($_POST['bio']) ? sanitizeInput($_POST['bio']) : null;
    
    if (empty($name)) {
        $error_message = "שם איש הצוות הוא שדה חובה";
    } else {
        try {
            // הוספת איש הצוות
            $query = "INSERT INTO staff (tenant_id, name, email, phone, position, bio, is_active) 
                      VALUES (:tenant_id, :name, :email, :phone, :position, :bio, 1)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(":tenant_id", $tenant_id);
            $stmt->bindParam(":name", $name);
            $stmt->bindParam(":email", $email);
            $stmt->bindParam(":phone", $phone);
            $stmt->bindParam(":position", $position);
            $stmt->bindParam(":bio", $bio);
            
            if ($stmt->execute()) {
                $staff_id = $db->lastInsertId();
                
                // טיפול בתמונה אם הועלתה
                if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                    $image_path = processUploadedImage('image', 'staff', $staff_id, $tenant_id);
                    
                    if ($image_path) {
                        // עדכון נתיב התמונה במסד הנתונים
                        $query = "UPDATE staff SET image_path = :image_path WHERE staff_id = :staff_id";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(":image_path", $image_path);
                        $stmt->bindParam(":staff_id", $staff_id);
                        $stmt->execute();
                    }
                }
                
                // שיוך כל השירותים הפעילים לאיש הצוות החדש
                $query = "SELECT service_id FROM services WHERE tenant_id = :tenant_id AND is_active = 1";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":tenant_id", $tenant_id);
                $stmt->execute();
                $services = $stmt->fetchAll();
                
                if (!empty($services)) {
                    $query = "INSERT INTO service_staff (service_id, staff_id) VALUES (:service_id, :staff_id)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(":staff_id", $staff_id);
                    
                    foreach ($services as $service) {
                        $stmt->bindParam(":service_id", $service['service_id']);
                        $stmt->execute();
                    }
                }
                
                // הוספת שעות זמינות ברירת מחדל (כמו שעות העסק)
                $query = "SELECT * FROM business_hours WHERE tenant_id = :tenant_id ORDER BY day_of_week ASC";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":tenant_id", $tenant_id);
                $stmt->execute();
                $business_hours = $stmt->fetchAll();
                
                if (!empty($business_hours)) {
                    $query = "INSERT INTO staff_availability (staff_id, day_of_week, is_available, start_time, end_time) 
                             VALUES (:staff_id, :day_of_week, :is_available, :start_time, :end_time)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(":staff_id", $staff_id);
                    
                    foreach ($business_hours as $hours) {
                        $stmt->bindParam(":day_of_week", $hours['day_of_week']);
                        $stmt->bindParam(":is_available", $hours['is_open']);
                        $stmt->bindParam(":start_time", $hours['open_time']);
                        $stmt->bindParam(":end_time", $hours['close_time']);
                        $stmt->execute();
                    }
                }
                
                $success_message = "איש הצוות נוסף בהצלחה";
            } else {
                $error_message = "שגיאה בהוספת איש הצוות";
            }
        } catch (PDOException $e) {
            $error_message = "שגיאת מסד נתונים: " . $e->getMessage();
        }
    }
}

// פעולת עדכון איש צוות
if (isset($_POST['update_staff'])) {
    $staff_id = intval($_POST['staff_id']);
    $name = sanitizeInput($_POST['name']);
    $email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : null;
    $phone = isset($_POST['phone']) ? sanitizeInput($_POST['phone']) : null;
    $position = isset($_POST['position']) ? sanitizeInput($_POST['position']) : null;
    $bio = isset($_POST['bio']) ? sanitizeInput($_POST['bio']) : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($name)) {
        $error_message = "שם איש הצוות הוא שדה חובה";
    } else {
        try {
            // עדכון איש הצוות
            $query = "UPDATE staff SET 
                      name = :name, 
                      email = :email, 
                      phone = :phone, 
                      position = :position, 
                      bio = :bio, 
                      is_active = :is_active 
                      WHERE staff_id = :staff_id AND tenant_id = :tenant_id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(":name", $name);
            $stmt->bindParam(":email", $email);
            $stmt->bindParam(":phone", $phone);
            $stmt->bindParam(":position", $position);
            $stmt->bindParam(":bio", $bio);
            $stmt->bindParam(":is_active", $is_active);
            $stmt->bindParam(":staff_id", $staff_id);
            $stmt->bindParam(":tenant_id", $tenant_id);
            
            if ($stmt->execute()) {
                // טיפול בתמונה אם הועלתה
                if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                    $image_path = processUploadedImage('image', 'staff', $staff_id, $tenant_id);
                    
                    if ($image_path) {
                        // עדכון נתיב התמונה במסד הנתונים
                        $query = "UPDATE staff SET image_path = :image_path WHERE staff_id = :staff_id";
                        $stmt = $db->prepare($query);
                        $stmt->bindParam(":image_path", $image_path);
                        $stmt->bindParam(":staff_id", $staff_id);
                        $stmt->execute();
                    }
                }
                
                $success_message = "פרטי איש הצוות עודכנו בהצלחה";
            } else {
                $error_message = "שגיאה בעדכון פרטי איש הצוות";
            }
        } catch (PDOException $e) {
            $error_message = "שגיאת מסד נתונים: " . $e->getMessage();
        }
    }
}

// פעולת מחיקת איש צוות
if (isset($_POST['delete_staff'])) {
    $staff_id = intval($_POST['staff_id']);
    
    try {
        // בדיקה אם לאיש הצוות יש תורים עתידיים
        $today = date('Y-m-d');
        $query = "SELECT COUNT(*) as count FROM appointments 
                  WHERE staff_id = :staff_id AND tenant_id = :tenant_id AND DATE(start_datetime) >= :today";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":staff_id", $staff_id);
        $stmt->bindParam(":tenant_id", $tenant_id);
        $stmt->bindParam(":today", $today);
        $stmt->execute();
        $future_appointments = $stmt->fetch()['count'];
        
        if ($future_appointments > 0) {
            $error_message = "לא ניתן למחוק איש צוות עם תורים עתידיים. יש לשנות או לבטל את התורים תחילה.";
        } else {
            // מחיקת איש הצוות
            $query = "DELETE FROM staff WHERE staff_id = :staff_id AND tenant_id = :tenant_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":staff_id", $staff_id);
            $stmt->bindParam(":tenant_id", $tenant_id);
            
            if ($stmt->execute()) {
                // מחיקת הקשרים לשירותים
                $query = "DELETE FROM service_staff WHERE staff_id = :staff_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":staff_id", $staff_id);
                $stmt->execute();
                
                // מחיקת שעות זמינות
                $query = "DELETE FROM staff_availability WHERE staff_id = :staff_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":staff_id", $staff_id);
                $stmt->execute();
                
                // מחיקת חריגים בזמינות
                $query = "DELETE FROM staff_availability_exceptions WHERE staff_id = :staff_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":staff_id", $staff_id);
                $stmt->execute();
                
                $success_message = "איש הצוות נמחק בהצלחה";
            } else {
                $error_message = "שגיאה במחיקת איש הצוות";
            }
        }
    } catch (PDOException $e) {
        $error_message = "שגיאת מסד נתונים: " . $e->getMessage();
    }
}

// שליפת אנשי הצוות של העסק
$query = "SELECT * FROM staff WHERE tenant_id = :tenant_id ORDER BY name ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(":tenant_id", $tenant_id);
$stmt->execute();
$staff_members = $stmt->fetchAll();

// פונקציה לטיפול בהעלאת תמונות
function processUploadedImage($file_key, $type, $id, $tenant_id) {
    $upload_dir = "uploads/tenants/" . $tenant_id . "/";
    
    // יצירת תיקיה אם לא קיימת
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_name = basename($_FILES[$file_key]["name"]);
    $target_file = $upload_dir . $type . "_" . $id . "_" . time() . "." . pathinfo($file_name, PATHINFO_EXTENSION);
    $file_tmp = $_FILES[$file_key]["tmp_name"];
    $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // בדיקת סוג הקובץ
    $allowed_types = ["jpg", "jpeg", "png", "gif"];
    if (!in_array($file_type, $allowed_types)) {
        return false;
    }
    
    // העברת הקובץ למיקום הסופי
    if (move_uploaded_file($file_tmp, $target_file)) {
        return $target_file;
    }
    
    return false;
}
?>

<?php include_once "includes/header.php"; ?>
<?php include_once "includes/sidebar.php"; ?>

<!-- Main Content -->
<main class="flex-1 overflow-y-auto py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">ניהול צוות</h1>
            
            <button id="add-staff-btn" class="bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-xl flex items-center">
                <i class="fas fa-plus mr-2"></i>
                <span>הוסף איש צוות</span>
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
        
        <!-- Staff Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (empty($staff_members)): ?>
                <div class="col-span-full text-center py-12 bg-white rounded-2xl shadow-sm">
                    <div class="text-gray-400 mb-3"><i class="fas fa-user-tie text-6xl"></i></div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">אין אנשי צוות</h3>
                    <p class="text-gray-600 mb-6">לא נמצאו אנשי צוות במערכת. הוסף אנשי צוות כדי לנהל את לוח הזמנים שלהם ולאפשר ללקוחות לקבוע תורים.</p>
                    <button id="no-staff-add-btn" class="bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-xl">
                        <i class="fas fa-plus mr-2"></i>
                        <span>הוסף איש צוות ראשון</span>
                    </button>
                </div>
            <?php else: ?>
                <?php foreach ($staff_members as $staff): ?>
                    <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
                        <div class="relative">
                            <!-- תמונת רקע -->
                            <div class="h-32 bg-gray-200"></div>
                            
                            <!-- תמונת פרופיל -->
                            <div class="absolute bottom-0 inset-x-0 transform translate-y-1/2 flex justify-center">
                                <?php if (!empty($staff['image_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($staff['image_path']); ?>" 
                                         alt="<?php echo htmlspecialchars($staff['name']); ?>" 
                                         class="w-24 h-24 rounded-full object-cover border-4 border-white">
                                <?php else: ?>
                                    <div class="w-24 h-24 rounded-full bg-primary text-white flex items-center justify-center border-4 border-white text-4xl font-bold">
                                        <?php echo mb_substr($staff['name'], 0, 1, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- סטטוס פעיל/לא פעיל -->
                            <div class="absolute top-4 right-4">
                                <?php if ($staff['is_active']): ?>
                                    <span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded-full">פעיל</span>
                                <?php else: ?>
                                    <span class="bg-gray-100 text-gray-800 text-xs font-medium px-2.5 py-0.5 rounded-full">לא פעיל</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="pt-14 p-6">
                            <h2 class="text-xl font-bold text-center text-gray-800 mb-1"><?php echo htmlspecialchars($staff['name']); ?></h2>
                            <?php if (!empty($staff['position'])): ?>
                                <p class="text-center text-gray-600 mb-4"><?php echo htmlspecialchars($staff['position']); ?></p>
                            <?php else: ?>
                                <div class="mb-4"></div>
                            <?php endif; ?>
                            
                            <div class="space-y-2 mb-6">
                                <?php if (!empty($staff['phone'])): ?>
                                    <div class="flex">
                                        <i class="fas fa-phone-alt text-primary w-5 ml-2"></i>
                                        <a href="tel:<?php echo htmlspecialchars($staff['phone']); ?>" class="text-gray-600">
                                            <?php echo htmlspecialchars($staff['phone']); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($staff['email'])): ?>
                                    <div class="flex">
                                        <i class="fas fa-envelope text-primary w-5 ml-2"></i>
                                        <a href="mailto:<?php echo htmlspecialchars($staff['email']); ?>" class="text-gray-600 break-all">
                                            <?php echo htmlspecialchars($staff['email']); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($staff['bio'])): ?>
                                <div class="text-gray-600 text-sm mb-6">
                                    <?php echo nl2br(htmlspecialchars($staff['bio'])); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="flex justify-between pt-3 border-t">
                                <a href="staff_availability.php?id=<?php echo $staff['staff_id']; ?>" class="text-primary hover:text-primary-dark text-sm">
                                    <i class="fas fa-calendar-alt mr-1"></i>
                                    שעות זמינות
                                </a>
                                
                                <div class="flex space-x-2 space-x-reverse">
                                    <button class="edit-staff-btn text-blue-500 hover:text-blue-700" 
                                            data-id="<?php echo $staff['staff_id']; ?>"
                                            data-name="<?php echo htmlspecialchars($staff['name']); ?>"
                                            data-email="<?php echo htmlspecialchars($staff['email']); ?>"
                                            data-phone="<?php echo htmlspecialchars($staff['phone']); ?>"
                                            data-position="<?php echo htmlspecialchars($staff['position']); ?>"
                                            data-bio="<?php echo htmlspecialchars($staff['bio']); ?>"
                                            data-active="<?php echo $staff['is_active']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <button class="delete-staff-btn text-red-500 hover:text-red-700" 
                                            data-id="<?php echo $staff['staff_id']; ?>"
                                            data-name="<?php echo htmlspecialchars($staff['name']); ?>">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal: Add/Edit Staff -->
    <div id="staff-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-2xl p-6 max-w-lg w-full mx-4">
            <h3 id="modal-title" class="text-xl font-bold mb-4">הוספת איש צוות</h3>
            
            <form id="staff-form" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" id="staff_id" name="staff_id" value="">
                
                <div>
                    <label for="name" class="block text-gray-700 font-medium mb-2">שם *</label>
                    <input type="text" id="name" name="name" 
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                           required>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="phone" class="block text-gray-700 font-medium mb-2">טלפון</label>
                        <input type="tel" id="phone" name="phone" dir="ltr"
                               class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                    </div>
                    
                    <div>
                        <label for="email" class="block text-gray-700 font-medium mb-2">אימייל</label>
                        <input type="email" id="email" name="email" dir="ltr"
                               class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                    </div>
                </div>
                
                <div>
                    <label for="position" class="block text-gray-700 font-medium mb-2">תפקיד</label>
                    <input type="text" id="position" name="position" 
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                </div>
                
                <div>
                    <label for="bio" class="block text-gray-700 font-medium mb-2">תיאור</label>
                    <textarea id="bio" name="bio" rows="3"
                             class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"></textarea>
                </div>
                
                <div>
                    <label for="image" class="block text-gray-700 font-medium mb-2">תמונה</label>
                    <div class="relative border-2 border-dashed border-gray-300 rounded-xl p-4 text-center">
                        <input type="file" id="image" name="image" accept="image/*" class="hidden">
                        <label for="image" class="cursor-pointer block">
                            <div id="image-preview" class="mb-2 hidden">
                                <img id="preview-img" src="" alt="Image Preview" class="max-h-40 mx-auto">
                            </div>
                            <div class="text-primary"><i class="fas fa-cloud-upload-alt text-2xl"></i></div>
                            <p id="image-text" class="text-gray-500">לחץ להעלאת תמונה</p>
                            <p class="text-xs text-gray-400 mt-1">פורמטים מותרים: JPG, PNG, GIF</p>
                        </label>
                    </div>
                </div>
                
                <div id="active-container" class="hidden">
                    <label class="inline-flex items-center">
                        <input type="checkbox" id="is_active" name="is_active" class="w-5 h-5 text-primary border-2 border-gray-300 rounded focus:ring-primary">
                        <span class="mr-2">פעיל</span>
                    </label>
                </div>
                
                <div class="flex justify-end space-x-3 space-x-reverse pt-4">
                    <button type="button" id="cancel-staff" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg">
                        ביטול
                    </button>
                    <button type="submit" id="submit-staff" class="px-4 py-2 bg-primary hover:bg-primary-dark text-white font-bold rounded-lg">
                        שמור
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal: Delete Staff -->
    <div id="delete-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-2xl p-6 max-w-md w-full mx-4">
            <h3 class="text-xl font-bold mb-4">מחיקת איש צוות</h3>
            
            <p class="mb-6">האם אתה בטוח שברצונך למחוק את איש הצוות <span id="delete-staff-name" class="font-bold"></span>?</p>
            
            <form method="POST" class="flex justify-end space-x-3 space-x-reverse">
                <input type="hidden" id="delete_staff_id" name="staff_id">
                <button type="button" id="cancel-delete" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg">
                    ביטול
                </button>
                <button type="submit" name="delete_staff" class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white font-bold rounded-lg">
                    מחק
                </button>
            </form>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // פתיחת מודל להוספת איש צוות
    const addStaffBtn = document.getElementById('add-staff-btn');
    const noStaffAddBtn = document.getElementById('no-staff-add-btn');
    const staffModal = document.getElementById('staff-modal');
    const modalTitle = document.getElementById('modal-title');
    const staffForm = document.getElementById('staff-form');
    const cancelStaffBtn = document.getElementById('cancel-staff');
    const activeContainer = document.getElementById('active-container');
    
    function openAddStaffModal() {
        modalTitle.textContent = 'הוספת איש צוות';
        staffForm.reset();
        document.getElementById('staff_id').value = '';
        document.getElementById('image-preview').classList.add('hidden');
        document.getElementById('image-text').textContent = 'לחץ להעלאת תמונה';
        activeContainer.classList.add('hidden');
        staffForm.action = '';
        staffForm.removeAttribute('data-edit');
        staffModal.classList.remove('hidden');
    }
    
    if (addStaffBtn) {
        addStaffBtn.addEventListener('click', openAddStaffModal);
    }
    
    if (noStaffAddBtn) {
        noStaffAddBtn.addEventListener('click', openAddStaffModal);
    }
    
    // סגירת מודל
    cancelStaffBtn.addEventListener('click', function() {
        staffModal.classList.add('hidden');
    });
    
    // טיפול בהעלאת תמונה - תצוגה מקדימה
    const imageInput = document.getElementById('image');
    const imagePreview = document.getElementById('image-preview');
    const previewImg = document.getElementById('preview-img');
    const imageText = document.getElementById('image-text');
    
    imageInput.addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            const file = e.target.files[0];
            const reader = new FileReader();
            
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                imagePreview.classList.remove('hidden');
                imageText.textContent = file.name;
            };
            
            reader.readAsDataURL(file);
        }
    });
    
    // פתיחת מודל לעריכת איש צוות
    const editStaffBtns = document.querySelectorAll('.edit-staff-btn');
    
    editStaffBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const email = this.getAttribute('data-email');
            const phone = this.getAttribute('data-phone');
            const position = this.getAttribute('data-position');
            const bio = this.getAttribute('data-bio');
            const active = this.getAttribute('data-active');
            
            modalTitle.textContent = 'עריכת איש צוות';
            
            document.getElementById('staff_id').value = id;
            document.getElementById('name').value = name;
            document.getElementById('email').value = email;
            document.getElementById('phone').value = phone;
            document.getElementById('position').value = position;
            document.getElementById('bio').value = bio;
            document.getElementById('is_active').checked = (active === '1');
            
            document.getElementById('image-preview').classList.add('hidden');
            document.getElementById('image-text').textContent = 'לחץ להעלאת תמונה';
            
            activeContainer.classList.remove('hidden');
            
            staffForm.setAttribute('data-edit', 'true');
            staffModal.classList.remove('hidden');
        });
    });
    
    // שליחת טופס איש צוות
    staffForm.addEventListener('submit', function(e) {
        const isEdit = this.hasAttribute('data-edit');
        
        if (isEdit) {
            this.action = ''; // השתמש ב-action ברירת מחדל של הטופס
            this.querySelector('#submit-staff').name = 'update_staff';
        } else {
            this.action = ''; // השתמש ב-action ברירת מחדל של הטופס
            this.querySelector('#submit-staff').name = 'add_staff';
        }
    });
    
    // פתיחת מודל למחיקת איש צוות
    const deleteStaffBtns = document.querySelectorAll('.delete-staff-btn');
    const deleteModal = document.getElementById('delete-modal');
    const cancelDeleteBtn = document.getElementById('cancel-delete');
    const deleteStaffName = document.getElementById('delete-staff-name');
    const deleteStaffId = document.getElementById('delete_staff_id');
    
    deleteStaffBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            
            deleteStaffName.textContent = name;
            deleteStaffId.value = id;
            
            deleteModal.classList.remove('hidden');
        });
    });
    
    // סגירת מודל מחיקה
    cancelDeleteBtn.addEventListener('click', function() {
        deleteModal.classList.add('hidden');
    });
    
    // סגירת מודלים בלחיצה על הרקע
    staffModal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.add('hidden');
        }
    });
    
    deleteModal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.add('hidden');
        }
    });
});
</script>

<?php include_once "includes/footer.php"; ?>