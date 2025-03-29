<?php
// התחלת או המשך סשן
session_start();

// טעינת קבצי התצורה
require_once "config/database.php";
require_once "includes/functions.php";

// בדיקה האם המשתמש מחובר
if (!isLoggedIn()) {
    redirect("login.php");
}

// משתנים להודעות שגיאה והצלחה
$error_message = "";
$success_message = "";

// חיבור למסד הנתונים
$database = new Database();
$db = $database->getConnection();

// קבלת פרטי העסק הקיימים
$tenant_id = $_SESSION['tenant_id'];
$query = "SELECT * FROM tenants WHERE tenant_id = :tenant_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":tenant_id", $tenant_id);
$stmt->execute();
$tenant = $stmt->fetch();

// קבלת שעות פעילות קיימות
$query = "SELECT * FROM business_hours WHERE tenant_id = :tenant_id ORDER BY day_of_week ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(":tenant_id", $tenant_id);
$stmt->execute();
$business_hours = $stmt->fetchAll();

// ארגון שעות פעילות לפי ימים
$hours_by_day = [];
foreach ($business_hours as $hours) {
    $hours_by_day[$hours['day_of_week']] = $hours;
}

// קבלת הפסקות קיימות
$query = "SELECT * FROM breaks WHERE tenant_id = :tenant_id ORDER BY day_of_week ASC, start_time ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(":tenant_id", $tenant_id);
$stmt->execute();
$breaks = $stmt->fetchAll();

// ארגון הפסקות לפי ימים
$breaks_by_day = [];
foreach ($breaks as $break) {
    if (!isset($breaks_by_day[$break['day_of_week']])) {
        $breaks_by_day[$break['day_of_week']] = [];
    }
    $breaks_by_day[$break['day_of_week']][] = $break;
}

// פעולה: עדכון שעות פעילות
if (isset($_POST['update_hours'])) {
    try {
        // עדכון כל הימים
        for ($day = 0; $day <= 6; $day++) {
            $is_open = isset($_POST['is_open'][$day]) ? 1 : 0;
            $open_time = $is_open ? $_POST['open_time'][$day] : null;
            $close_time = $is_open ? $_POST['close_time'][$day] : null;
            
            // בדיקה אם יש כבר רשומה ליום זה
            if (isset($hours_by_day[$day])) {
                // עדכון רשומה קיימת
                $query = "UPDATE business_hours SET is_open = :is_open, open_time = :open_time, close_time = :close_time
                          WHERE tenant_id = :tenant_id AND day_of_week = :day";
            } else {
                // יצירת רשומה חדשה
                $query = "INSERT INTO business_hours (tenant_id, day_of_week, is_open, open_time, close_time)
                          VALUES (:tenant_id, :day, :is_open, :open_time, :close_time)";
            }
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(":tenant_id", $tenant_id);
            $stmt->bindParam(":day", $day);
            $stmt->bindParam(":is_open", $is_open, PDO::PARAM_BOOL);
            $stmt->bindParam(":open_time", $open_time);
            $stmt->bindParam(":close_time", $close_time);
            $stmt->execute();
        }
        
        // מחיקת כל ההפסקות הקיימות (נוסיף חדשות בהמשך)
        $query = "DELETE FROM breaks WHERE tenant_id = :tenant_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":tenant_id", $tenant_id);
        $stmt->execute();
        
        // הוספת הפסקות חדשות (אם יש)
        if (isset($_POST['break_start']) && isset($_POST['break_end'])) {
            $break_starts = $_POST['break_start'];
            $break_ends = $_POST['break_end'];
            $break_days = $_POST['break_day'];
            $break_descriptions = $_POST['break_description'];
            
            for ($i = 0; $i < count($break_starts); $i++) {
                if (!empty($break_starts[$i]) && !empty($break_ends[$i])) {
                    $query = "INSERT INTO breaks (tenant_id, day_of_week, start_time, end_time, description)
                              VALUES (:tenant_id, :day, :start_time, :end_time, :description)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(":tenant_id", $tenant_id);
                    $stmt->bindParam(":day", $break_days[$i]);
                    $stmt->bindParam(":start_time", $break_starts[$i]);
                    $stmt->bindParam(":end_time", $break_ends[$i]);
                    $stmt->bindParam(":description", $break_descriptions[$i]);
                    $stmt->execute();
                }
            }
        }
        
        $success_message = "שעות הפעילות עודכנו בהצלחה!";
        
        // רענון שעות פעילות והפסקות
        $query = "SELECT * FROM business_hours WHERE tenant_id = :tenant_id ORDER BY day_of_week ASC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":tenant_id", $tenant_id);
        $stmt->execute();
        $business_hours = $stmt->fetchAll();
        
        // ארגון שעות פעילות לפי ימים
        $hours_by_day = [];
        foreach ($business_hours as $hours) {
            $hours_by_day[$hours['day_of_week']] = $hours;
        }
        
        $query = "SELECT * FROM breaks WHERE tenant_id = :tenant_id ORDER BY day_of_week ASC, start_time ASC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":tenant_id", $tenant_id);
        $stmt->execute();
        $breaks = $stmt->fetchAll();
        
        // ארגון הפסקות לפי ימים
        $breaks_by_day = [];
        foreach ($breaks as $break) {
            if (!isset($breaks_by_day[$break['day_of_week']])) {
                $breaks_by_day[$break['day_of_week']] = [];
            }
            $breaks_by_day[$break['day_of_week']][] = $break;
        }
    } catch (PDOException $e) {
        $error_message = "שגיאת מערכת: " . $e->getMessage();
    }
}

// פעולה: סיום הגדרת שעות פעילות
if (isset($_POST['finish_setup'])) {
    redirect("dashboard.php");
}

// תרגום מספר יום לשם יום בעברית
function getDayName($day) {
    $days = ["ראשון", "שני", "שלישי", "רביעי", "חמישי", "שישי", "שבת"];
    return $days[$day];
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>קליקינדר - הגדרת שעות פעילות</title>
    <!-- טעינת גופן Noto Sans Hebrew מ-Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Hebrew:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- טעינת Tailwind CSS מ-CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            light: '#f687b3', // ורוד בהיר
                            DEFAULT: '#ec4899', // ורוד
                            dark: '#db2777', // ורוד כהה
                        },
                        secondary: {
                            light: '#93c5fd', // כחול בהיר
                            DEFAULT: '#3b82f6', // כחול
                            dark: '#2563eb', // כחול כהה
                        },
                        pastel: {
                            purple: '#d8b4fe', // סגול פסטל
                            blue: '#bfdbfe',   // כחול פסטל
                            green: '#bbf7d0',  // ירוק פסטל
                            yellow: '#fef08a', // צהוב פסטל
                            orange: '#fed7aa', // כתום פסטל
                            red: '#fecaca',    // אדום פסטל
                        }
                    },
                    fontFamily: {
                        'noto': ['"Noto Sans Hebrew"', 'sans-serif'],
                    },
                    borderRadius: {
                        'xl': '1rem',
                        '2xl': '1.5rem',
                        '3xl': '2rem',
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Noto Sans Hebrew', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="text-center mb-10">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">קליקינדר</h1>
            <p class="text-gray-600">מערכת ניהול תורים חכמה</p>
        </div>
        
        <!-- Progress Bar -->
        <div class="max-w-3xl mx-auto mb-8">
            <div class="flex justify-between mb-2">
                <div class="text-center">
                    <div class="w-10 h-10 bg-primary text-white rounded-full flex items-center justify-center mx-auto mb-1">1</div>
                    <span class="text-sm text-primary font-medium">הרשמה</span>
                </div>
                <div class="text-center">
                    <div class="w-10 h-10 bg-primary text-white rounded-full flex items-center justify-center mx-auto mb-1">2</div>
                    <span class="text-sm text-primary font-medium">פרטי עסק</span>
                </div>
                <div class="text-center">
                    <div class="w-10 h-10 bg-primary text-white rounded-full flex items-center justify-center mx-auto mb-1">3</div>
                    <span class="text-sm text-primary font-medium">הגדרת שירותים</span>
                </div>
                <div class="text-center">
                    <div class="w-10 h-10 bg-primary text-white rounded-full flex items-center justify-center mx-auto mb-1">4</div>
                    <span class="text-sm text-primary font-medium">שעות פעילות</span>
                </div>
            </div>
            <div class="relative pt-1">
                <div class="overflow-hidden h-2 text-xs flex rounded bg-primary-light">
                    <div style="width:100%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-primary"></div>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="max-w-4xl mx-auto">
            <div class="bg-white p-8 rounded-2xl shadow-lg">
                <h2 class="text-2xl font-bold mb-6">הגדרת שעות פעילות</h2>
                
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
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" id="hoursForm">
                    <!-- שעות פעילות -->
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold mb-4">שעות פעילות שבועיות</h3>
                        <div class="grid gap-4">
                            <?php for ($day = 0; $day <= 6; $day++): ?>
                                <?php 
                                $is_open = isset($hours_by_day[$day]) ? $hours_by_day[$day]['is_open'] : ($day < 6 ? 1 : 0);
                                $open_time = isset($hours_by_day[$day]) ? $hours_by_day[$day]['open_time'] : "09:00:00";
                                $close_time = isset($hours_by_day[$day]) ? $hours_by_day[$day]['close_time'] : "18:00:00";
                                ?>
                                <div class="border border-gray-200 rounded-xl p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex items-center">
                                            <input type="checkbox" id="is_open_<?php echo $day; ?>" name="is_open[<?php echo $day; ?>]" 
                                                   class="day-toggle w-5 h-5 text-primary border-2 border-gray-300 rounded focus:ring-primary"
                                                   data-day="<?php echo $day; ?>"
                                                   <?php echo $is_open ? "checked" : ""; ?>>
                                            <label for="is_open_<?php echo $day; ?>" class="mr-2 font-medium">יום <?php echo getDayName($day); ?></label>
                                        </div>
                                        <div class="text-sm">
                                            <?php if ($is_open): ?>
                                                <span class="bg-green-100 text-green-800 px-2 py-1 rounded-lg">פתוח</span>
                                            <?php else: ?>
                                                <span class="bg-gray-100 text-gray-800 px-2 py-1 rounded-lg">סגור</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div id="hours_container_<?php echo $day; ?>" class="<?php echo !$is_open ? "hidden" : ""; ?>">
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label for="open_time_<?php echo $day; ?>" class="block text-gray-700 text-sm mb-1">שעת פתיחה</label>
                                                <input type="time" id="open_time_<?php echo $day; ?>" name="open_time[<?php echo $day; ?>]" 
                                                       class="w-full px-3 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                                       value="<?php echo substr($open_time, 0, 5); ?>">
                                            </div>
                                            <div>
                                                <label for="close_time_<?php echo $day; ?>" class="block text-gray-700 text-sm mb-1">שעת סגירה</label>
                                                <input type="time" id="close_time_<?php echo $day; ?>" name="close_time[<?php echo $day; ?>]" 
                                                       class="w-full px-3 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                                       value="<?php echo substr($close_time, 0, 5); ?>">
                                            </div>
                                        </div>
                                        
                                        <!-- הפסקות ליום זה -->
                                        <div class="mt-4">
                                            <div class="flex justify-between items-center mb-2">
                                                <label class="text-gray-700 text-sm">הפסקות</label>
                                                <button type="button" class="add-break text-primary text-sm hover:underline" data-day="<?php echo $day; ?>">
                                                    <i class="fas fa-plus"></i> הוסף הפסקה
                                                </button>
                                            </div>
                                            
                                            <div id="breaks_container_<?php echo $day; ?>" class="space-y-2">
                                                <?php if (isset($breaks_by_day[$day])): ?>
                                                    <?php foreach ($breaks_by_day[$day] as $index => $break): ?>
                                                        <div class="break-item grid grid-cols-5 gap-2 items-center">
                                                            <div class="col-span-2">
                                                                <input type="time" name="break_start[]" 
                                                                       class="w-full px-3 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                                                       value="<?php echo substr($break['start_time'], 0, 5); ?>">
                                                                <input type="hidden" name="break_day[]" value="<?php echo $day; ?>">
                                                            </div>
                                                            <div class="col-span-2">
                                                                <input type="time" name="break_end[]" 
                                                                       class="w-full px-3 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                                                       value="<?php echo substr($break['end_time'], 0, 5); ?>">
                                                            </div>
                                                            <div class="col-span-1 flex justify-end">
                                                                <button type="button" class="remove-break text-gray-400 hover:text-red-500">
                                                                    <i class="fas fa-times"></i>
                                                                </button>
                                                            </div>
                                                            <div class="col-span-5">
                                                                <input type="text" name="break_description[]" 
                                                                       class="w-full px-3 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                                                       placeholder="תיאור ההפסקה (לדוגמה: הפסקת צהריים)"
                                                                       value="<?php echo htmlspecialchars($break['description']); ?>">
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <!-- הודעה על שעות חריגות -->
                    <div class="bg-blue-50 border border-blue-200 text-blue-700 p-4 rounded-lg mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-info-circle text-blue-500"></i>
                            </div>
                            <div class="mr-3">
                                <p>הערה: באפשרותך להגדיר שעות חריגות לימים מסוימים (כמו חגים או חופשות) מתוך מסך ההגדרות לאחר השלמת תהליך ההרשמה.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- כפתורי פעולה -->
                    <div class="flex justify-between pt-6 mt-4 border-t">
                        <button type="button" id="saveButton" class="px-6 py-3 bg-primary-light hover:bg-primary text-white rounded-xl transition duration-300">
                            שמור שינויים
                        </button>
                        <button type="submit" name="finish_setup" class="px-8 py-3 bg-primary hover:bg-primary-dark text-white font-bold rounded-xl transition duration-300 flex items-center">
                            סיים וצפה בלוח הבקרה
                            <i class="fas fa-check mr-2"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- תבנית להפסקה חדשה -->
    <template id="break-template">
        <div class="break-item grid grid-cols-5 gap-2 items-center">
            <div class="col-span-2">
                <input type="time" name="break_start[]" 
                       class="w-full px-3 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                <input type="hidden" name="break_day[]" value="">
            </div>
            <div class="col-span-2">
                <input type="time" name="break_end[]" 
                       class="w-full px-3 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
            </div>
            <div class="col-span-1 flex justify-end">
                <button type="button" class="remove-break text-gray-400 hover:text-red-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="col-span-5">
                <input type="text" name="break_description[]" 
                       class="w-full px-3 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                       placeholder="תיאור ההפסקה (לדוגמה: הפסקת צהריים)">
            </div>
        </div>
    </template>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle hours containers based on checkbox
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
            
            // Add break functionality
            const addBreakButtons = document.querySelectorAll('.add-break');
            const breakTemplate = document.getElementById('break-template');
            
            addBreakButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const day = this.getAttribute('data-day');
                    const breakContainer = document.getElementById(`breaks_container_${day}`);
                    
                    // Clone the template
                    const newBreak = document.importNode(breakTemplate.content, true);
                    
                    // Set the day value
                    newBreak.querySelector('input[name="break_day[]"]').value = day;
                    
                    // Append the new break
                    breakContainer.appendChild(newBreak);
                    
                    // Add event listener to the new remove button
                    const removeButton = breakContainer.lastElementChild.querySelector('.remove-break');
                    removeButton.addEventListener('click', function() {
                        this.closest('.break-item').remove();
                    });
                });
            });
            
            // Remove break functionality for existing breaks
            const removeBreakButtons = document.querySelectorAll('.remove-break');
            removeBreakButtons.forEach(button => {
                button.addEventListener('click', function() {
                    this.closest('.break-item').remove();
                });
            });
            
            // Save button submits the form with update_hours
            document.getElementById('saveButton').addEventListener('click', function() {
                const updateInput = document.createElement('input');
                updateInput.type = 'hidden';
                updateInput.name = 'update_hours';
                updateInput.value = '1';
                document.getElementById('hoursForm').appendChild(updateInput);
                document.getElementById('hoursForm').submit();
            });
        });
    </script>
</body>
</html>