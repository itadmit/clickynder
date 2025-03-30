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
$page_title = "יומן תורים";

// יצירת חיבור למסד הנתונים
$database = new Database();
$db = $database->getConnection();

// קבלת פרמטרים מה-URL
$tenant_id = $_SESSION['tenant_id'];
$view_type = isset($_GET['view']) ? sanitizeInput($_GET['view']) : 'month';
$staff_id = isset($_GET['staff']) ? intval($_GET['staff']) : 0;
$current_date = isset($_GET['date']) ? sanitizeInput($_GET['date']) : date('Y-m-d');

// שליפת צוות העובדים (לסינון)
$query = "SELECT staff_id, name FROM staff WHERE tenant_id = :tenant_id AND is_active = 1 ORDER BY name ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(":tenant_id", $tenant_id);
$stmt->execute();
$staff_members = $stmt->fetchAll();

// שליפת שירותים (לסינון ולהוספת תור חדש)
$query = "SELECT service_id, name, duration, price FROM services WHERE tenant_id = :tenant_id AND is_active = 1 ORDER BY name ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(":tenant_id", $tenant_id);
$stmt->execute();
$services = $stmt->fetchAll();

// שליפת לקוחות (להוספת תור חדש)
$query = "SELECT customer_id, first_name, last_name, phone FROM customers WHERE tenant_id = :tenant_id ORDER BY first_name, last_name ASC";
$stmt = $db->prepare($query);
$stmt->bindParam(":tenant_id", $tenant_id);
$stmt->execute();
$customers = $stmt->fetchAll();

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
                <h1 class="text-2xl font-bold text-gray-800">יומן תורים</h1>
                
                <div class="flex items-center space-x-4 space-x-reverse mt-2 md:mt-0">
                    <!-- סינון לפי איש צוות -->
                    <div class="relative">
                        <select id="staff-filter" class="pl-10 pr-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary text-sm">
                            <option value="0">כל אנשי הצוות</option>
                            <?php foreach ($staff_members as $member): ?>
                                <option value="<?php echo $member['staff_id']; ?>" <?php echo ($staff_id == $member['staff_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($member['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-user-tie text-gray-400"></i>
                        </div>
                    </div>
                    
                    <!-- כפתור תור חדש -->
                    <button id="new-appointment-btn" class="bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-xl flex items-center">
                        <i class="fas fa-plus mr-2"></i>
                        <span>תור חדש</span>
                    </button>
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

    <!-- Calendar Container -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="bg-white p-4 rounded-2xl shadow-sm">
            <!-- Calendar View Type Buttons -->
            <div class="flex mb-4 space-x-2 space-x-reverse">
                <button class="view-button px-4 py-2 rounded-lg text-sm <?php echo $view_type == 'month' ? 'bg-primary text-white' : 'bg-gray-200 text-gray-600 hover:bg-gray-300'; ?>" data-view="month">חודש</button>
                <button class="view-button px-4 py-2 rounded-lg text-sm <?php echo $view_type == 'week' ? 'bg-primary text-white' : 'bg-gray-200 text-gray-600 hover:bg-gray-300'; ?>" data-view="week">שבוע</button>
                <button class="view-button px-4 py-2 rounded-lg text-sm <?php echo $view_type == 'day' ? 'bg-primary text-white' : 'bg-gray-200 text-gray-600 hover:bg-gray-300'; ?>" data-view="day">יום</button>
                <button class="view-button px-4 py-2 rounded-lg text-sm <?php echo $view_type == 'list' ? 'bg-primary text-white' : 'bg-gray-200 text-gray-600 hover:bg-gray-300'; ?>" data-view="list">רשימה</button>
            </div>
            
            <!-- Calendar -->
            <div id="calendar"></div>
        </div>
    </div>
    
    <!-- Modal: New/Edit Appointment -->
    <div id="appointment-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-2xl p-6 max-w-lg w-full mx-4">
            <h3 id="modal-title" class="text-xl font-bold mb-4">הוספת תור חדש</h3>
            
            <form id="appointment-form" class="space-y-4">
                <input type="hidden" id="appointment_id" name="appointment_id" value="">
                
                <!-- בחירת לקוח -->
                <div>
                    <label for="customer_id" class="block text-gray-700 font-medium mb-2">לקוח</label>
                    <div class="relative">
                        <select id="customer_id" name="customer_id" class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary" required>
                            <option value="">בחר לקוח</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo $customer['customer_id']; ?>">
                                    <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) . ' - ' . htmlspecialchars($customer['phone']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3">
                            <button type="button" id="add-new-customer" class="text-primary hover:text-primary-dark">
                                <i class="fas fa-user-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- בחירת שירות -->
                <div>
                    <label for="service_id" class="block text-gray-700 font-medium mb-2">שירות</label>
                    <select id="service_id" name="service_id" class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary" required>
                        <option value="">בחר שירות</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?php echo $service['service_id']; ?>" data-duration="<?php echo $service['duration']; ?>">
                                <?php echo htmlspecialchars($service['name']) . ' (' . $service['duration'] . ' דק\') - ' . number_format($service['price'], 0) . ' ₪'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- בחירת איש צוות -->
                <div>
                    <label for="staff_id" class="block text-gray-700 font-medium mb-2">נותן שירות</label>
                    <select id="staff_id" name="staff_id" class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary" required>
                        <option value="">בחר נותן שירות</option>
                        <?php foreach ($staff_members as $member): ?>
                            <option value="<?php echo $member['staff_id']; ?>">
                                <?php echo htmlspecialchars($member['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- תאריך ושעה -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="appointment_date" class="block text-gray-700 font-medium mb-2">תאריך</label>
                        <input type="date" id="appointment_date" name="appointment_date" 
                               class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                               required>
                    </div>
                    <div>
                        <label for="appointment_time" class="block text-gray-700 font-medium mb-2">שעה</label>
                        <input type="time" id="appointment_time" name="appointment_time" 
                               class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                               required>
                    </div>
                </div>
                
                <!-- הערות -->
                <div>
                    <label for="notes" class="block text-gray-700 font-medium mb-2">הערות</label>
                    <textarea id="notes" name="notes" rows="2"
                             class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"></textarea>
                </div>
                
                <!-- סטטוס התור -->
                <div>
                    <label for="status" class="block text-gray-700 font-medium mb-2">סטטוס</label>
                    <div class="flex space-x-4 space-x-reverse">
                        <label class="inline-flex items-center">
                            <input type="radio" name="status" value="pending" class="text-primary" checked>
                            <span class="mr-2">ממתין</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="status" value="confirmed" class="text-primary">
                            <span class="mr-2">מאושר</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="status" value="cancelled" class="text-primary">
                            <span class="mr-2">בוטל</span>
                        </label>
                    </div>
                </div>
                
                <!-- כפתורי פעולה -->
                <div class="flex justify-between mt-6">
                    <button type="button" id="delete-appointment" class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg hidden">
                        מחיקה
                    </button>
                    <div class="flex space-x-3 space-x-reverse">
                        <button type="button" id="cancel-appointment" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg">
                            ביטול
                        </button>
                        <button type="submit" id="save-appointment" class="px-4 py-2 bg-primary hover:bg-primary-dark text-white font-bold rounded-lg">
                            שמור
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal: New Customer -->
    <div id="new-customer-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-2xl p-6 max-w-lg w-full mx-4">
            <h3 class="text-xl font-bold mb-4">הוספת לקוח חדש</h3>
            
            <form id="customer-form" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="first_name" class="block text-gray-700 font-medium mb-2">שם פרטי</label>
                        <input type="text" id="first_name" name="first_name" 
                               class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                               required>
                    </div>
                    <div>
                        <label for="last_name" class="block text-gray-700 font-medium mb-2">שם משפחה</label>
                        <input type="text" id="last_name" name="last_name" 
                               class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                               required>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="phone" class="block text-gray-700 font-medium mb-2">טלפון</label>
                        <input type="tel" id="phone" name="phone" dir="ltr"
                               class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                               required>
                    </div>
                    <div>
                        <label for="email" class="block text-gray-700 font-medium mb-2">אימייל</label>
                        <input type="email" id="email" name="email" dir="ltr"
                               class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                    </div>
                </div>
                
                <div>
                    <label for="customer_notes" class="block text-gray-700 font-medium mb-2">הערות</label>
                    <textarea id="customer_notes" name="customer_notes" rows="2"
                              class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"></textarea>
                </div>
                
                <div class="flex justify-end space-x-3 space-x-reverse mt-6">
                    <button type="button" id="cancel-customer" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg">
                        ביטול
                    </button>
                    <button type="submit" id="save-customer" class="px-4 py-2 bg-primary hover:bg-primary-dark text-white font-bold rounded-lg">
                        הוסף לקוח
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // יצירת יומן
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        locale: 'he',
        initialView: '<?php echo $view_type; ?>',
        initialDate: '<?php echo $current_date; ?>',
        height: 'auto',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: ''
        },
        buttonText: {
            today: 'היום'
        },
        direction: 'rtl',
        firstDay: 0, // יום ראשון כיום ראשון בשבוע
        slotMinTime: '08:00:00',
        slotMaxTime: '20:00:00',
        allDaySlot: false,
        slotDuration: '00:15:00',
        selectable: true,
        selectMirror: true,
        nowIndicator: true,
        eventTimeFormat: {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        },
        views: {
            dayGridMonth: {
                dayMaxEvents: 4
            },
            timeGrid: {
                dayMaxEvents: true
            }
        },
        select: function(info) {
            // פתיחת מודל להוספת תור חדש
            document.getElementById('modal-title').textContent = 'הוספת תור חדש';
            document.getElementById('appointment_id').value = '';
            document.getElementById('appointment_date').value = info.startStr.substr(0, 10);
            document.getElementById('appointment_time').value = info.startStr.substr(11, 5);
            document.getElementById('notes').value = '';
            document.querySelector('input[name="status"][value="pending"]').checked = true;
            
            // מחיקת ערכים קודמים
            document.getElementById('customer_id').value = '';
            document.getElementById('service_id').value = '';
            document.getElementById('staff_id').value = '<?php echo $staff_id ?: ''; ?>';
            
            // הסתרת כפתור מחיקה
            document.getElementById('delete-appointment').classList.add('hidden');
            
            // הצגת המודל
            document.getElementById('appointment-modal').classList.remove('hidden');
        },
        eventClick: function(info) {
            // פתיחת מודל לעריכת תור קיים
            var appointmentId = info.event.id;
            
            // קריאה ל-API לקבלת פרטי התור
            fetch('api/get_appointment.php?id=' + appointmentId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        var appointment = data.appointment;
                        document.getElementById('modal-title').textContent = 'עריכת תור';
                        document.getElementById('appointment_id').value = appointment.appointment_id;
                        document.getElementById('customer_id').value = appointment.customer_id;
                        document.getElementById('service_id').value = appointment.service_id;
                        document.getElementById('staff_id').value = appointment.staff_id;
                        
                        // המרת תאריך ושעה
                        var startDate = new Date(appointment.start_datetime);
                        document.getElementById('appointment_date').value = startDate.toISOString().substr(0, 10);
                        document.getElementById('appointment_time').value = startDate.toTimeString().substr(0, 5);
                        
                        document.getElementById('notes').value = appointment.notes || '';
                        document.querySelector('input[name="status"][value="' + appointment.status + '"]').checked = true;
                        
                        // הצגת כפתור מחיקה
                        document.getElementById('delete-appointment').classList.remove('hidden');
                        
                        // הצגת המודל
                        document.getElementById('appointment-modal').classList.remove('hidden');
                    } else {
                        alert('שגיאה בטעינת פרטי התור: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('שגיאה בטעינת פרטי התור');
                });
        },
        events: {
            url: 'api/get_appointments.php',
            method: 'GET',
            extraParams: {
                staff_id: '<?php echo $staff_id; ?>'
            },
            failure: function() {
                alert('שגיאה בטעינת התורים');
            }
        }
    });
    
    calendar.render();
    
    // פתיחת מודל תור חדש
    document.getElementById('new-appointment-btn').addEventListener('click', function() {
        document.getElementById('modal-title').textContent = 'הוספת תור חדש';
        document.getElementById('appointment_id').value = '';
        document.getElementById('appointment_date').value = new Date().toISOString().substr(0, 10);
        document.getElementById('appointment_time').value = '09:00';
        document.getElementById('notes').value = '';
        document.querySelector('input[name="status"][value="pending"]').checked = true;
        
        // מחיקת ערכים קודמים
        document.getElementById('customer_id').value = '';
        document.getElementById('service_id').value = '';
        document.getElementById('staff_id').value = '<?php echo $staff_id ?: ''; ?>';
        
        // הסתרת כפתור מחיקה
        document.getElementById('delete-appointment').classList.add('hidden');
        
        // הצגת המודל
        document.getElementById('appointment-modal').classList.remove('hidden');
    });
    
    // סגירת מודל תור
    document.getElementById('cancel-appointment').addEventListener('click', function() {
        document.getElementById('appointment-modal').classList.add('hidden');
    });
    
    // פתיחת מודל לקוח חדש
    document.getElementById('add-new-customer').addEventListener('click', function() {
        // איפוס הטופס
        document.getElementById('customer-form').reset();
        
        // הצגת המודל
        document.getElementById('new-customer-modal').classList.remove('hidden');
    });
    
    // סגירת מודל לקוח חדש
    document.getElementById('cancel-customer').addEventListener('click', function() {
        document.getElementById('new-customer-modal').classList.add('hidden');
    });
    
    // שליחת טופס לקוח חדש
    document.getElementById('customer-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // יצירת אובייקט FormData
        var formData = new FormData(this);
        formData.append('tenant_id', '<?php echo $tenant_id; ?>');
        
        // שליחה ל-API
        fetch('api/add_customer.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // הוספת הלקוח החדש לרשימת הלקוחות
                var newCustomer = document.createElement('option');
                newCustomer.value = data.customer_id;
                newCustomer.text = data.first_name + ' ' + data.last_name + ' - ' + data.phone;
                document.getElementById('customer_id').appendChild(newCustomer);
                
                // בחירת הלקוח החדש
                document.getElementById('customer_id').value = data.customer_id;
                
                // סגירת המודל
                document.getElementById('new-customer-modal').classList.add('hidden');
                
                // הצגת הודעת הצלחה
                alert('הלקוח נוסף בהצלחה');
            } else {
                alert('שגיאה בהוספת הלקוח: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('שגיאה בהוספת הלקוח');
        });
    });
    
    // שליחת טופס תור
    document.getElementById('appointment-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // יצירת אובייקט FormData
        var formData = new FormData(this);
        formData.append('tenant_id', '<?php echo $tenant_id; ?>');
        
        // הוספת תאריך ושעה מלאים
        var dateStr = formData.get('appointment_date');
        var timeStr = formData.get('appointment_time');
        formData.append('start_datetime', dateStr + 'T' + timeStr + ':00');
        
        // קביעת ה-API המתאים (הוספה או עדכון)
        var apiUrl = formData.get('appointment_id') ? 'api/update_appointment.php' : 'api/add_appointment.php';
        
        // שליחה ל-API
        fetch(apiUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // סגירת המודל
                document.getElementById('appointment-modal').classList.add('hidden');
                
                // רענון היומן
                calendar.refetchEvents();
                
                // הצגת הודעת הצלחה
                alert(formData.get('appointment_id') ? 'התור עודכן בהצלחה' : 'התור נוסף בהצלחה');
            } else {
                alert('שגיאה: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('שגיאה בשמירת התור');
        });
    });
    
    // מחיקת תור
    document.getElementById('delete-appointment').addEventListener('click', function() {
        if (confirm('האם אתה בטוח שברצונך למחוק את התור?')) {
            var appointmentId = document.getElementById('appointment_id').value;
            
            fetch('api/delete_appointment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    appointment_id: appointmentId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // סגירת המודל
                    document.getElementById('appointment-modal').classList.add('hidden');
                    
                    // רענון היומן
                    calendar.refetchEvents();
                    
                    // הצגת הודעת הצלחה
                    alert('התור נמחק בהצלחה');
                } else {
                    alert('שגיאה במחיקת התור: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('שגיאה במחיקת התור');
            });
        }
    });
    
    // שינוי תצוגת היומן
    document.querySelectorAll('.view-button').forEach(button => {
        button.addEventListener('click', function() {
            var view = this.getAttribute('data-view');
            calendar.changeView(view);
            
            // עדכון ה-URL
            var url = new URL(window.location.href);
            url.searchParams.set('view', view);
            history.pushState({}, '', url);
            
            // עדכון כפתורי התצוגה
            document.querySelectorAll('.view-button').forEach(btn => {
                btn.classList.remove('bg-primary', 'text-white');
                btn.classList.add('bg-gray-200', 'text-gray-600', 'hover:bg-gray-300');
            });
            this.classList.remove('bg-gray-200', 'text-gray-600', 'hover:bg-gray-300');
            this.classList.add('bg-primary', 'text-white');
        });
    });
    
    // סינון לפי איש צוות
    document.getElementById('staff-filter').addEventListener('change', function() {
        var staffId = this.value;
        
        // עדכון הפרמטרים לאירועים
        calendar.getEventSources()[0].refetch({
            staff_id: staffId
        });
        
        // עדכון ה-URL
        var url = new URL(window.location.href);
        if (staffId == 0) {
            url.searchParams.delete('staff');
        } else {
            url.searchParams.set('staff', staffId);
        }
        history.pushState({}, '', url);
    });
    
    // סגירת מודלים בלחיצה על הרקע
    document.getElementById('appointment-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.add('hidden');
        }
    });
    
    document.getElementById('new-customer-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.add('hidden');
        }
    });
});
</script>

<?php include_once "includes/footer.php"; ?>