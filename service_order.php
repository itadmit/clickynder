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

// התאמת טבלה במידת הצורך
try {
    // בדיקה אם קיים עמודת display_order בטבלת services
    $query = "SHOW COLUMNS FROM services LIKE 'display_order'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        // אם העמודה לא קיימת, הוסף אותה
        $query = "ALTER TABLE services ADD COLUMN display_order INT DEFAULT 0";
        $stmt = $db->prepare($query);
        $stmt->execute();
    }
} catch (PDOException $e) {
    $error_message = "שגיאה בבדיקת מבנה טבלה: " . $e->getMessage();
}

// שליפת כל השירותים מסודרים לפי קטגוריה וסדר תצוגה
$services_by_category = [];
try {
    // שליפת קטגוריות
    $query = "SELECT * FROM service_categories 
              WHERE tenant_id = :tenant_id 
              ORDER BY display_order ASC, name ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    $categories = $stmt->fetchAll();
    
    // שליפת כל השירותים
    $query = "SELECT * FROM services 
              WHERE tenant_id = :tenant_id 
              ORDER BY category_id, display_order ASC, name ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    $all_services = $stmt->fetchAll();
    
    // ארגון שירותים לפי קטגוריות
    $services_by_category = [];
    
    // קטגוריה "ללא קטגוריה"
    $services_by_category[0] = [
        'category' => [
            'category_id' => 0,
            'name' => 'שירותים ללא קטגוריה',
            'description' => ''
        ],
        'services' => []
    ];
    
    // ארגון שירותים לפי קטגוריות קיימות
    foreach ($categories as $category) {
        $services_by_category[$category['category_id']] = [
            'category' => $category,
            'services' => []
        ];
    }
    
    // הוספת השירותים לקטגוריות המתאימות
    foreach ($all_services as $service) {
        $cat_id = $service['category_id'] ?: 0;
        
        // אם הקטגוריה לא קיימת, הוסף לקטגוריית ברירת מחדל
        if (!isset($services_by_category[$cat_id])) {
            $cat_id = 0;
        }
        
        $services_by_category[$cat_id]['services'][] = $service;
    }
    
    // הסר קטגוריות ריקות
    foreach ($services_by_category as $cat_id => $category_data) {
        if (empty($category_data['services'])) {
            unset($services_by_category[$cat_id]);
        }
    }
    
} catch (PDOException $e) {
    $error_message = "שגיאת מסד נתונים: " . $e->getMessage();
}

// כותרת העמוד
$page_title = "סידור שירותים";
?>

<?php include_once "includes/header.php"; ?>
<?php include_once "includes/sidebar.php"; ?>

<!-- Main Content -->
<main class="flex-1 overflow-y-auto py-8">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">סידור סדר תצוגת שירותים</h1>
            
            <div>
                <a href="services.php" class="inline-block bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded-lg mr-2">
                    <i class="fas fa-arrow-right ml-1"></i> חזרה לרשימת השירותים
                </a>
                <a href="service_categories.php" class="inline-block bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded-lg">
                    <i class="fas fa-tags ml-1"></i> ניהול קטגוריות
                </a>
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
        
        <div class="bg-white shadow-sm rounded-2xl overflow-hidden p-6 mb-6">
            <div class="flex items-center text-gray-600 mb-6 bg-blue-50 p-4 rounded-lg">
                <div class="text-primary text-xl mr-2"><i class="fas fa-info-circle"></i></div>
                <p>גרור ושחרר את השירותים כדי לשנות את סדר ההצגה שלהם בדף הזמנת התורים.</p>
            </div>
            
            <?php if (empty($services_by_category)): ?>
                <div class="bg-gray-100 rounded-xl p-8 text-center">
                    <p class="text-gray-500 mb-4">אין שירותים להצגה</p>
                    <a href="services.php" class="bg-primary hover:bg-primary-dark text-white font-bold py-2 px-6 rounded-xl transition duration-300">
                        הוסף שירות חדש
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($services_by_category as $category_data): ?>
                    <div class="mb-8">
                        <h2 class="text-xl font-semibold mb-4 bg-gray-50 p-3 rounded-lg">
                            <?php echo htmlspecialchars($category_data['category']['name']); ?>
                        </h2>
                        
                        <div class="sortable-services border border-dashed border-gray-200 rounded-xl p-4 min-h-16" data-category-id="<?php echo $category_data['category']['category_id']; ?>">
                            <?php foreach ($category_data['services'] as $service): ?>
                                <div class="service-item bg-white shadow-sm rounded-lg p-4 mb-2 cursor-move border border-gray-100" data-service-id="<?php echo $service['service_id']; ?>">
                                    <div class="flex items-center">
                                        <div class="drag-handle text-gray-400 mr-2">
                                            <i class="fas fa-grip-vertical"></i>
                                        </div>
                                        <div class="h-4 w-4 rounded-full mr-2" style="background-color: <?php echo !empty($service['color']) ? htmlspecialchars($service['color']) : '#3498db'; ?>"></div>
                                        <div class="font-medium flex-grow"><?php echo htmlspecialchars($service['name']); ?></div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo $service['duration']; ?> דקות
                                            <?php if (!empty($service['price']) && $service['price'] > 0): ?>
                                                | <?php echo number_format($service['price'], 0); ?> ₪
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="text-left mt-3">
                            <button type="button" class="bg-primary hover:bg-primary-dark text-white font-bold py-2 px-4 rounded-lg save-order-btn" data-category-id="<?php echo $category_data['category']['category_id']; ?>">
                                <i class="fas fa-save ml-1"></i> שמור סדר
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- הוספת jQuery ו-jQuery UI לתמיכה בגרירה ומיון -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.1/jquery-ui.min.js"></script>

<script>
    $(document).ready(function() {
        // הפעלת מיון לכל קבוצת שירותים
        $(".sortable-services").sortable({
            handle: ".drag-handle",
            placeholder: "service-placeholder",
            forcePlaceholderSize: true,
            update: function(event, ui) {
                // כשהמיון מסתיים, סמן שיש שינויים לשמור
                $(this).next().find(".save-order-btn").addClass("unsaved-changes");
            }
        }).disableSelection();
        
        // אירוע לחיצה על כפתור שמירת סדר
        $(".save-order-btn").click(function() {
            const btn = $(this);
            const categoryId = btn.data("category-id");
            const container = $(`.sortable-services[data-category-id="${categoryId}"]`);
            const services = [];
            
            // איסוף מזהי שירותים לפי הסדר הנוכחי
            container.find(".service-item").each(function() {
                services.push($(this).data("service-id"));
            });
            
            // הוספת אנימציית טעינה
            const originalText = btn.html();
            btn.html('<i class="fas fa-spinner fa-spin ml-1"></i> שומר...');
            btn.prop('disabled', true);
            
            // שליחת הנתונים לשמירה
            $.ajax({
                url: "api/update_service_order.php",
                type: "POST",
                contentType: "application/json",
                data: JSON.stringify({ services: services }),
                success: function(response) {
                    if (response.success) {
                        // הודעת הצלחה
                        btn.removeClass("unsaved-changes");
                        btn.removeClass("bg-primary").addClass("bg-green-500");
                        btn.html('<i class="fas fa-check ml-1"></i> נשמר בהצלחה');
                        
                        setTimeout(function() {
                            btn.html(originalText);
                            btn.removeClass("bg-green-500").addClass("bg-primary");
                            btn.prop('disabled', false);
                        }, 2000);
                    } else {
                        btn.html(originalText);
                        btn.prop('disabled', false);
                        alert("אירעה שגיאה: " + response.message);
                    }
                },
                error: function() {
                    btn.html(originalText);
                    btn.prop('disabled', false);
                    alert("אירעה שגיאה בעת שמירת הסדר");
                }
            });
        });
        
        // סגנון עבור כפתור עם שינויים שלא נשמרו
        $("<style>")
            .prop("type", "text/css")
            .html(`
                .unsaved-changes {
                    background-color: #ef4444 !important;
                }
                .unsaved-changes:hover {
                    background-color: #dc2626 !important;
                }
                .service-placeholder {
                    border: 2px dashed #ec4899;
                    border-radius: 0.5rem;
                    background-color: #fce7f3;
                    margin-bottom: 0.5rem;
                    height: 4rem;
                }
            `)
            .appendTo("head");
    });
</script>

<?php include_once "includes/footer.php"; ?>