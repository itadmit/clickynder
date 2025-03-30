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

// בדיקה שנשלח מזהה איש צוות
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "מזהה איש צוות חסר";
    redirect("staff.php");
}

$staff_id = intval($_GET['id']);
$tenant_id = $_SESSION['tenant_id'];

// יצירת חיבור למסד הנתונים
$database = new Database();
$db = $database->getConnection();

// בדיקה שאיש הצוות שייך לטננט
$query = "SELECT * FROM staff WHERE staff_id = :staff_id AND tenant_id = :tenant_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":staff_id", $staff_id);
$stmt->bindParam(":tenant_id", $tenant_id);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    $_SESSION['error_message'] = "איש הצוות לא נמצא או אינו שייך לעסק שלך";
    redirect("staff.php");
}

$staff = $stmt->fetch();

// כותרת העמוד
$page_title = "שעות זמינות - " . $staff['name'];

// פעולת שמירת שעות זמינות
if (isset($_POST['save_availability'])) {
    try {
        // עדכון כל הימים
        for ($day = 0; $day <= 6; $day++) {
            $is_available = isset($_POST['is_available'][$day]) ? 1 : 0;
            $start_time = $is_available ? $_POST['start_time'][$day] : null;
            $end_time = $is_available ? $_POST['end_time'][$day] : null;
            
            // בדיקה אם יש כבר רשומה ליום זה
            $query = "SELECT id FROM staff_availability WHERE staff_id = :staff_id AND day_of_week = :day";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":staff_id", $staff_id);
            $stmt->bindParam(":day", $day);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                // עדכון רשומה קיימת
                $query = "UPDATE staff_availability SET is_available = :is_available, start_time = :start_time, end_time = :end_time
                          WHERE staff_id = :staff_id AND day_of_week = :day";
            } else {
                // יצירת רשומה חדשה
                $query = "INSERT INTO staff_availability (staff_id, day_of_week, is_available, start_time, end_time)
                          VALUES (:staff_id, :day, :is_available, :start_time, :end_time)";
            }
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(":staff_id", $staff_id);
            $stmt->bindParam(":day", $day);
            $stmt->bindParam(":is_available", $is_available, PDO::PARAM_BOOL);
            $stmt->bindParam(":start_time", $start_time);
            $stmt->bindParam(":end_time", $end_time);
            $stmt->execute();
        }
        
        $success_message = "שעות הזמינות נשמרו בהצלחה";
    } catch (PDOException $e) {
        $error_message = "שגיאת מסד נתונים: " . $e->getMessage();
    }
}

// פעולת הוספת יום חריג
if (isset($_POST['add_exception'])) {
    $exception_date = sanitizeInput($_POST['exception_date']);
    $is_available = isset($_POST['exception_is_available']) ? 1 : 0;
    $start_time = $is_available ? $_POST['exception_start_time'] : null;
    $end_time = $is_available ? $_POST['exception_end_time'] : null;
    $description = sanitizeInput($_POST['exception_description']);
    
    try {
        // בדיקה אם כבר קיים חריג לתאריך זה
        $query = "SELECT id FROM staff_availability_exceptions 
                  WHERE staff_id = :staff_id AND exception_date = :exception_date";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":staff_id", $staff_id);
        $stmt->bindParam(":exception_date", $exception_date);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // עדכון חריג קיים
            $exception_id = $stmt->fetch()['id'];
            $query = "UPDATE staff_availability_exceptions SET 
                      is_available = :is_available, 
                      start_time = :start_time, 
                      end_time = :end_time, 
                      description = :description 
                      WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":id", $exception_id);
        } else {
            // הוספת חריג חדש
            $query = "INSERT INTO staff_availability_exceptions (
                      staff_id, exception_date, is_available, start_time, end_time, description) 
                      VALUES (:staff_id, :exception_date, :is_available, :start_time, :end_time, :description)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":staff_id", $staff_id);
            $stmt->bindParam(":exception_date", $exception_date);
        }
        
        $stmt->bindParam(":is_available", $is_available, PDO::PARAM_BOOL);
        $stmt->bindParam(":start_time", $start_time);
        $stmt->bindParam(":end_time", $end_time);
        $stmt->bindParam(":description", $description);
        
        if ($stmt->execute()) {
            $success_message = "היום החריג נוסף בהצלחה";
        } else {
            $error_message = "שגיאה בהוספת היום החריג";
        }
    } catch (PDOException $e) {
        $error_message = "שגיאת מסד נתונים: " . $e->getMessage();
    }
}

// פעולת מחיקת יום חריג
if (isset($_POST['delete_exception'])) {
    $exception_id = intval($_POST['exception_id']);
    
    try {
        $query = "DELETE FROM staff_availability_exceptions 
                  WHERE id = :id AND staff_id = :staff_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id", $exception_id);
        $stmt->bindParam(":staff_id", $staff_id);
        
        if ($stmt->execute()) {
            $success_message = "היום החריג נמחק בהצלחה";
        } else {
            $error_message = "שגיאה במחיקת היום החריג";
        }
    } catch (PDOException $e) {
        $error_message = "שגיאת מסד נתונים: " . $e->getMessage();
    }
}

// שליפת שעות הזמינות
$query = "SELECT * FROM staff_availability WHERE staff_id = :staff_id ORDER BY day_of_week ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(":staff_id", $staff_id);
$stmt->execute();
$availability = $stmt->fetchAll();

// ארגון שעות זמינות לפי ימים
$availability_by_day = [];
foreach ($availability as $day) {
    $availability_by_day[$day['day_of_week']] = $day;
}

// שליפת ימים חריגים
$query = "SELECT * FROM staff_availability_exceptions 
          WHERE staff_id = :staff_id 
          AND exception_date >= CURDATE() 
          ORDER BY exception_date ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(":staff_id", $staff_id);
$stmt->execute();
$exceptions = $stmt->fetchAll();

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

// פונקציה להצגת שם יום בעברית
function getDayName($day) {
    $days = ["ראשון", "שני", "שלישי", "רביעי", "חמישי", "שישי", "שבת"];
    return $days[$day];
}
?>

<?php include_once "includes/header.php"; ?>
<?php include_once "includes/sidebar.php"; ?>

<!-- Main Content -->
<main class="flex-1 overflow-y-auto py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap justify-between items-center mb-6">
            <div class="flex items-center">
                <a href="staff.php" class="text-gray-400 hover:text-primary ml-2">
                    <i class="fas fa-arrow-right"></i>
                </a>
                <h1 class="text-3xl font-bold text-gray-800">
                    שעות זמינות - <?php echo htmlspecialchars($staff['name']); ?>
                </h1>
            </div>
            
            <button id="add-exception-btn" class="bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-xl flex items-center mt-2 md:mt-0">
                <i class="fas fa-plus mr-2"></i>
                <span>הוסף יום חריג</span>
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
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- שעות זמינות רגילות -->
            <div class="md:col-span-2 bg-white p-6 rounded-2xl shadow-sm">
                <h2 class="text-xl font-bold mb-6">שעות זמינות שבועיות</h2>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $staff_id); ?>" method="POST">
                    <div class="space-y-5">
                        <?php for ($day = 0; $day <= 6; $day++): ?>
                            <?php 
                            $is_available = isset($availability_by_day[$day]) ? $availability_by_day[$day]['is_available'] : 0;
                            $start_time = isset($availability_by_day[$day]) ? $availability_by_day[$day]['start_time'] : "09:00:00";
                            $end_time = isset($availability_by_day[$day]) ? $availability_by_day[$day]['end_time'] : "18:00:00";
                            ?>
                            <div class="border border-gray-200 rounded-xl p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center">
                                        <input type="checkbox" id="is_available_<?php echo $day; ?>" name="is_available[<?php echo $day; ?>]" 
                                               class="day-toggle w-5 h-5 text-primary border-2 border-gray-300 rounded focus:ring-primary"
                                               data-day="<?php echo $day; ?>"
                                               <?php echo $is_available ? "checked" : ""; ?>>
                                        <label for="is_available_<?php echo $day; ?>" class="mr-2 font-medium">יום <?php echo getDayName($day); ?></label>
                                    </div>
                                    <div class="text-sm">
                                        <?php if ($is_available): ?>
                                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded-lg">זמין</span>
                                        <?php else: ?>
                                            <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded-lg">לא זמין</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div id="hours_container_<?php echo $day; ?>" class="<?php echo !$is_available ? "hidden" : ""; ?>">
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label for="start_time_<?php echo $day; ?>" class="block text-gray-700 text-sm mb-1">שעת התחלה</label>
                                            <input type="time" id="start_time_<?php echo $day; ?>" name="start_time[<?php echo $day; ?>]" 
                                                   class="w-full px-3 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                                   value="<?php echo substr($start_time, 0, 5); ?>">
                                        </div>
                                        <div>
                                            <label for="end_time_<?php echo $day; ?>" class="block text-gray-700 text-sm mb-1">שעת סיום</label>
                                            <input type="time" id="end_time_<?php echo $day; ?>" name="end_time[<?php echo $day; ?>]" 
                                                   class="w-full px-3 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                                   value="<?php echo substr($end_time, 0, 5); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                    
                    <div class="mt-6">
                        <button type="submit" name="save_availability" class="bg-primary hover:bg-primary-dark text-white font-bold py-2 px-6 rounded-xl">
                            שמור שינויים
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- ימים חריגים -->
            <div class="bg-white p-6 rounded-2xl shadow-sm">
                <h2 class="text-xl font-bold mb-6">ימים חריגים</h2>
                
                <?php if (empty($exceptions)): ?>
                    <div class="text-center py-8 border-2 border-dashed border-gray-300 rounded-xl">
                        <div class="text-gray-400 mb-3"><i class="fas fa-calendar-times text-4xl"></i></div>
                        <p class="text-gray-600 mb-2">אין ימים חריגים</p>
                        <p class="text-sm text-gray-500">ימים חריגים מאפשרים לך להגדיר זמינות שונה מהרגיל בתאריכים ספציפיים (חופשות, חגים וכו')</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4 mb-6">
                        <?php foreach ($exceptions as $exception): ?>
                            <div class="border border-gray-200 rounded-xl p-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <div class="flex items-center">
                                            <span class="font-medium"><?php echo date('d/m/Y', strtotime($exception['exception_date'])); ?></span>
                                            <?php if ($exception['is_available']): ?>
                                                <span class="mr-2 bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">זמין</span>
                                            <?php else: ?>
                                                <span class="mr-2 bg-red-100 text-red-800 text-xs px-2 py-1 rounded-full">לא זמין</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($exception['is_available']): ?>
                                            <p class="text-sm text-gray-600 mt-1">
                                                שעות: <?php echo substr($exception['start_time'], 0, 5); ?> - <?php echo substr($exception['end_time'], 0, 5); ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($exception['description'])): ?>
                                            <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($exception['description']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <form method="POST" onsubmit="return confirm('האם למחוק יום חריג זה?');">
                                        <input type="hidden" name="exception_id" value="<?php echo $exception['id']; ?>">
                                        <button type="submit" name="delete_exception" class="text-red-500 hover:text-red-700">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <button id="add-exception-btn-2" class="w-full border-2 border-dashed border-gray-300 rounded-xl py-3 text-primary hover:bg-gray-50 transition duration-150">
                    <i class="fas fa-plus mr-2"></i> הוסף יום חריג
                </button>
            </div>
        </div>
    </div>
    
    <!-- Modal: Add Exception -->
    <div id="exception-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-2xl p-6 max-w-md w-full mx-4">
            <h3 class="text-xl font-bold mb-4">הוספת יום חריג</h3>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $staff_id); ?>" method="POST" class="space-y-4">
                <div>
                    <label for="exception_date" class="block text-gray-700 font-medium mb-2">תאריך</label>
                    <input type="date" id="exception_date" name="exception_date" 
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                           min="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div>
                    <label class="inline-flex items-center">
                        <input type="checkbox" id="exception_is_available" name="exception_is_available" 
                               class="exception-toggle w-5 h-5 text-primary border-2 border-gray-300 rounded focus:ring-primary" checked>
                        <span class="mr-2">זמין ביום זה</span>
                    </label>
                </div>
                
                <div id="exception_hours_container">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="exception_start_time" class="block text-gray-700 font-medium mb-2">שעת התחלה</label>
                            <input type="time" id="exception_start_time" name="exception_start_time" 
                                   class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                   value="09:00">
                        </div>
                        <div>
                            <label for="exception_end_time" class="block text-gray-700 font-medium mb-2">שעת סיום</label>
                            <input type="time" id="exception_end_time" name="exception_end_time" 
                                   class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                   value="17:00">
                        </div>
                    </div>
                </div>
                
                <div>
                    <label for="exception_description" class="block text-gray-700 font-medium mb-2">תיאור</label>
                    <input type="text" id="exception_description" name="exception_description" 
                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                           placeholder="לדוגמה: חופשה, חג, יום הכשרה">
                </div>
                
                <div class="flex justify-end space-x-3 space-x-reverse pt-4">
                    <button type="button" id="cancel-exception" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg">
                        ביטול
                    </button>
                    <button type="submit" name="add_exception" class="px-4 py-2 bg-primary hover:bg-primary-dark text-white font-bold rounded-lg">
                        הוסף
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle שעות זמינות לפי יום
    const dayToggles = document.querySelectorAll('.day-toggle');
    dayToggles.forEach(toggle => {
        toggle.addEventListener('change', function() {
            const day = this.getAttribute('data-day');
            const hoursContainer = document.getElementById(`hours_container_${day}`);
            
            if (this.checked) {
                hoursContainer.classList.remove('hidden');
            } else {
                hoursContainer.classList.add('hidden');
            }
        });
    });
    
    // Toggle שעות זמינות ביום חריג
    const exceptionToggle = document.getElementById('exception_is_available');
    const exceptionHoursContainer = document.getElementById('exception_hours_container');
    
    exceptionToggle.addEventListener('change', function() {
        if (this.checked) {
            exceptionHoursContainer.classList.remove('hidden');
        } else {
            exceptionHoursContainer.classList.add('hidden');
        }
    });
    
    // פתיחת מודל להוספת יום חריג
    const addExceptionBtns = document.querySelectorAll('#add-exception-btn, #add-exception-btn-2');
    const exceptionModal = document.getElementById('exception-modal');
    const cancelExceptionBtn = document.getElementById('cancel-exception');
    
    addExceptionBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            // איפוס הטופס
            document.getElementById('exception_date').value = '';
            document.getElementById('exception_is_available').checked = true;
            document.getElementById('exception_start_time').value = '09:00';
            document.getElementById('exception_end_time').value = '17:00';
            document.getElementById('exception_description').value = '';
            exceptionHoursContainer.classList.remove('hidden');
            
            // הצגת המודל
            exceptionModal.classList.remove('hidden');
        });
    });
    
    // סגירת מודל
    cancelExceptionBtn.addEventListener('click', function() {
        exceptionModal.classList.add('hidden');
    });
    
    // סגירת מודל בלחיצה על הרקע
    exceptionModal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.add('hidden');
        }
    });
});
</script>

<?php include_once "includes/footer.php"; ?>