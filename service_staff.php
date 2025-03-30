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

// קבלת מזהה שירות מה-URL
$service_id = isset($_GET['service_id']) ? intval($_GET['service_id']) : 0;

if ($service_id == 0) {
    redirect("services.php");
}

// שליפת פרטי השירות
$service = null;
try {
    $query = "SELECT * FROM services WHERE service_id = :service_id AND tenant_id = :tenant_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":service_id", $service_id);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        redirect("services.php");
    }
    
    $service = $stmt->fetch();
} catch (PDOException $e) {
    $error_message = "שגיאת מסד נתונים: " . $e->getMessage();
}

// עדכון שיוכי אנשי צוות
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_staff'])) {
    try {
        // קבלת מערך אנשי צוות מהטופס
        $selected_staff = isset($_POST['staff']) ? $_POST['staff'] : [];
        
        // מחיקת כל השיוכים הקיימים
        $query = "DELETE FROM service_staff WHERE service_id = :service_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":service_id", $service_id);
        $stmt->execute();
        
        // הוספת השיוכים החדשים
        foreach ($selected_staff as $staff_id) {
            $staff_id = intval($staff_id);
            
            // בדיקה שאיש הצוות שייך לטננט
            $query = "SELECT staff_id FROM staff WHERE staff_id = :staff_id AND tenant_id = :tenant_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":staff_id", $staff_id);
            $stmt->bindParam(":tenant_id", $tenant_id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $query = "INSERT INTO service_staff (service_id, staff_id) VALUES (:service_id, :staff_id)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":service_id", $service_id);
                $stmt->bindParam(":staff_id", $staff_id);
                $stmt->execute();
            }
        }
        
        $success_message = "שיוכי אנשי הצוות עודכנו בהצלחה";
    } catch (PDOException $e) {
        $error_message = "שגיאת מסד נתונים: " . $e->getMessage();
    }
}

// שליפת כל אנשי הצוות
$staff_members = [];
try {
    $query = "SELECT * FROM staff WHERE tenant_id = :tenant_id ORDER BY name ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    $staff_members = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "שגיאת מסד נתונים: " . $e->getMessage();
}

// שליפת אנשי צוות שכבר משויכים לשירות
$assigned_staff = [];
try {
    $query = "SELECT staff_id FROM service_staff WHERE service_id = :service_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":service_id", $service_id);
    $stmt->execute();
    
    while ($row = $stmt->fetch()) {
        $assigned_staff[] = $row['staff_id'];
    }
} catch (PDOException $e) {
    $error_message = "שגיאת מסד נתונים: " . $e->getMessage();
}

// כותרת העמוד
$page_title = "שיוך אנשי צוות ל" . $service['name'];
?>

<?php include_once "includes/header.php"; ?>
<?php include_once "includes/sidebar.php"; ?>

<!-- Main Content -->
<main class="flex-1 overflow-y-auto py-8">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">שיוך אנשי צוות ל<?php echo htmlspecialchars($service['name']); ?></h1>
            
            <a href="services.php" class="text-primary hover:text-primary-dark font-medium">
                <i class="fas fa-arrow-right ml-1"></i> חזרה לרשימת השירותים
            </a>
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
        
        <!-- טופס שיוך אנשי צוות -->
        <div class="bg-white shadow-sm rounded-2xl overflow-hidden p-6">
            <form method="POST">
                <div class="mb-6">
                    <h2 class="text-xl font-semibold mb-4">בחר אנשי צוות שיכולים לספק את השירות:</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php if (empty($staff_members)): ?>
                            <p class="col-span-2 text-gray-500">אין אנשי צוות במערכת. <a href="staff.php" class="text-primary hover:underline">הוסף אנשי צוות</a></p>
                        <?php else: ?>
                            <?php foreach ($staff_members as $staff): ?>
                                <div class="bg-gray-50 rounded-xl p-4 flex items-center">
                                    <div class="flex-shrink-0">
                                        <?php if (!empty($staff['image_path'])): ?>
                                            <img src="<?php echo htmlspecialchars($staff['image_path']); ?>" class="w-12 h-12 object-cover rounded-full">
                                        <?php else: ?>
                                            <div class="w-12 h-12 rounded-full bg-primary text-white flex items-center justify-center font-bold">
                                                <?php echo mb_substr($staff['name'], 0, 1, 'UTF-8'); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ml-4 flex-grow">
                                        <div class="font-medium"><?php echo htmlspecialchars($staff['name']); ?></div>
                                        <?php if (!empty($staff['position'])): ?>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($staff['position']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <input type="checkbox" id="staff_<?php echo $staff['staff_id']; ?>" name="staff[]" value="<?php echo $staff['staff_id']; ?>" 
                                               class="w-5 h-5 text-primary border-2 border-gray-300 rounded focus:ring-primary"
                                               <?php echo in_array($staff['staff_id'], $assigned_staff) ? 'checked' : ''; ?>>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" name="update_staff" class="bg-primary hover:bg-primary-dark text-white font-bold py-2 px-6 rounded-xl transition duration-300">
                        שמור שינויים
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include_once "includes/footer.php"; ?>