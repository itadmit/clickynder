<?php
// וידוא שהקובץ לא נטען ישירות
if (!defined('ACCESS_ALLOWED')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

// קבלת שם העמוד הנוכחי
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// בדיקה האם המשתמש נמצא בדף שירותים או בדף משני שלו
$is_services_section = in_array($current_page, ['services', 'service_categories', 'service_staff', 'service_order']);

// שליפת פרטי העסק
$business_name = $_SESSION['business_name'] ?? "העסק שלי";
$business_logo = $_SESSION['logo_path'] ?? "https://via.placeholder.com/50";
$business_slug = $_SESSION['slug'] ?? "";
?>
<style>
.mb-1 .mx-2{
    margin-right:0
}
</style>
<!-- Sidebar -->
<aside class="w-64 bg-white shadow-lg flex-shrink-0 hidden md:block">
    <div class="flex items-center justify-center py-6 border-b">
        <h1 class="text-2xl font-bold text-primary">קליקינדר</h1>
    </div>
    
    <div class="p-4 border-b">
        <div class="flex items-center">
            <img src="<?php echo !empty($tenant['logo_path']) ? htmlspecialchars($tenant['logo_path']) : 'https://via.placeholder.com/40'; ?>" alt="<?php echo htmlspecialchars($business_name); ?>" class="w-10 h-10 rounded-full">
            <div class="mr-3">
                <p class="font-bold text-sm"><?php echo htmlspecialchars($business_name); ?></p>
                <a href="booking/<?php echo htmlspecialchars($business_slug); ?>" class="text-xs text-primary hover:underline">צפה בדף הנחיתה</a>
            </div>
        </div>
    </div>
    
    <nav class="py-4">
        <ul>
            <li>
                <a href="dashboard.php" class="flex items-center px-4 py-3 <?php echo ($current_page == 'dashboard') ? 'text-primary bg-pastel-purple bg-opacity-30' : 'text-gray-600 hover:bg-gray-100'; ?> rounded-lg mx-2">
                    <i class="fas fa-home w-5 text-center"></i>
                    <span class="mr-3">לוח בקרה</span>
                </a>
            </li>
            <li>
                <a href="appointments.php" class="flex items-center px-4 py-3 <?php echo ($current_page == 'appointments') ? 'text-primary bg-pastel-purple bg-opacity-30' : 'text-gray-600 hover:bg-gray-100'; ?> rounded-lg mx-2">
                    <i class="fas fa-calendar-alt w-5 text-center"></i>
                    <span class="mr-3">יומן תורים</span>
                </a>
            </li>
            <li>
                <a href="customers.php" class="flex items-center px-4 py-3 <?php echo ($current_page == 'customers') ? 'text-primary bg-pastel-purple bg-opacity-30' : 'text-gray-600 hover:bg-gray-100'; ?> rounded-lg mx-2">
                    <i class="fas fa-users w-5 text-center"></i>
                    <span class="mr-3">לקוחות</span>
                </a>
            </li>
            <li>
                <a href="staff.php" class="flex items-center px-4 py-3 <?php echo ($current_page == 'staff') ? 'text-primary bg-pastel-purple bg-opacity-30' : 'text-gray-600 hover:bg-gray-100'; ?> rounded-lg mx-2">
                    <i class="fas fa-user-tie w-5 text-center"></i>
                    <span class="mr-3">צוות</span>
                </a>
            </li>

            <li>
                <a href="waitlist.php" class="flex items-center px-4 py-3 <?php echo ($current_page == 'waitlist') ? 'text-primary bg-pastel-purple bg-opacity-30' : 'text-gray-600 hover:bg-gray-100'; ?> rounded-lg mx-2">
                    <i class="fas fa-clipboard-list w-5 text-center"></i>
                    <span class="mr-3">רשימות המתנה</span>
                </a>
            </li>
            
            <!-- תפריט שירותים עם אקורדיון -->
            <li class="mb-1">
                <button type="button" id="servicesDropdownBtn" 
                        class="flex items-center justify-between w-full px-4 py-3 <?php echo ($is_services_section) ? 'text-primary bg-pastel-purple bg-opacity-30' : 'text-gray-600 hover:bg-gray-100'; ?> rounded-lg mx-2"
                        onclick="toggleServicesDropdown()">
                    <div class="flex items-center">
                        <i class="fas fa-concierge-bell w-5 text-center"></i>
                        <span class="mr-3">שירותים</span>
                    </div>
                    <i id="servicesDropdownIcon" class="fas <?php echo ($is_services_section) ? 'fa-chevron-down' : 'fa-chevron-left'; ?>"></i>
                </button>
                
                <div id="servicesDropdown" class="<?php echo ($is_services_section) ? '' : 'hidden'; ?> mt-1 mr-6 space-y-1">
                    <a href="services.php" class="block py-2 px-4 rounded-lg <?php echo ($current_page == 'services') ? 'text-primary bg-pastel-purple bg-opacity-20' : 'text-gray-600 hover:bg-gray-100'; ?>">
                        <i class="fas fa-list-ul w-5 text-center"></i>
                        <span class="mr-2">רשימת שירותים</span>
                    </a>
                    <a href="service_categories.php" class="block py-2 px-4 rounded-lg <?php echo ($current_page == 'service_categories') ? 'text-primary bg-pastel-purple bg-opacity-20' : 'text-gray-600 hover:bg-gray-100'; ?>">
                        <i class="fas fa-tags w-5 text-center"></i>
                        <span class="mr-2">קטגוריות</span>
                    </a>
                    <a href="service_order.php" class="block py-2 px-4 rounded-lg <?php echo ($current_page == 'service_order') ? 'text-primary bg-pastel-purple bg-opacity-20' : 'text-gray-600 hover:bg-gray-100'; ?>">
                        <i class="fas fa-sort w-5 text-center"></i>
                        <span class="mr-2">סדר תצוגה</span>
                    </a>
                </div>
            </li>
            
            <li>
                <a href="settings.php" class="flex items-center px-4 py-3 <?php echo ($current_page == 'settings') ? 'text-primary bg-pastel-purple bg-opacity-30' : 'text-gray-600 hover:bg-gray-100'; ?> rounded-lg mx-2">
                    <i class="fas fa-cog w-5 text-center"></i>
                    <span class="mr-3">הגדרות</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="absolute bottom-0 left-0 right-0 p-4 border-t">
        <a href="logout.php" class="flex items-center text-gray-600 hover:text-primary">
            <i class="fas fa-sign-out-alt"></i>
            <span class="mr-2">התנתק</span>
        </a>
    </div>
</aside>

<!-- הוסף את התסריט JavaScript הזה לסוף הקובץ -->
<script>
function toggleServicesDropdown() {
    const dropdown = document.getElementById('servicesDropdown');
    const icon = document.getElementById('servicesDropdownIcon');
    
    if (dropdown.classList.contains('hidden')) {
        dropdown.classList.remove('hidden');
        icon.classList.remove('fa-chevron-left');
        icon.classList.add('fa-chevron-down');
    } else {
        dropdown.classList.add('hidden');
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-left');
    }
}

// שמירת מצב האקורדיון במקרה של רענון דף
document.addEventListener('DOMContentLoaded', function() {
    // אם היינו בעמוד שירותים, האקורדיון יהיה פתוח
    const isServicesSection = <?php echo $is_services_section ? 'true' : 'false'; ?>;
    
    if (isServicesSection) {
        const dropdown = document.getElementById('servicesDropdown');
        const icon = document.getElementById('servicesDropdownIcon');
        
        dropdown.classList.remove('hidden');
        icon.classList.remove('fa-chevron-left');
        icon.classList.add('fa-chevron-down');
    }
});
</script>

<!-- Mobile Sidebar Toggle -->
<div class="md:hidden fixed bottom-4 right-4 z-50">
    <button id="sidebarToggle" class="bg-primary text-white p-3 rounded-full shadow-lg">
        <i class="fas fa-bars"></i>
    </button>
</div>



<!-- Mobile Sidebar -->
<div id="mobileSidebar" class="fixed inset-0 bg-gray-800 bg-opacity-50 z-40 hidden">
    <div class="bg-white w-64 h-full overflow-y-auto">
        <div class="flex items-center justify-between p-4 border-b">
            <h1 class="text-2xl font-bold text-primary">קליקינדר</h1>
            <button id="closeSidebar" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="p-4 border-b">
            <div class="flex items-center">
                <img src="<?php echo htmlspecialchars($business_logo); ?>" alt="<?php echo htmlspecialchars($business_name); ?>" class="w-10 h-10 rounded-full">
                <div class="mr-3">
                    <p class="font-bold text-sm"><?php echo htmlspecialchars($business_name); ?></p>
                    <a href="booking/<?php echo htmlspecialchars($business_slug); ?>" class="text-xs text-primary hover:underline">צפה בדף הנחיתה</a>
                </div>
            </div>
        </div>
        
        <nav class="py-4">
            <ul>
                <li>
                    <a href="dashboard.php" class="flex items-center px-4 py-3 <?php echo ($current_page == 'dashboard') ? 'text-primary bg-pastel-purple bg-opacity-30' : 'text-gray-600 hover:bg-gray-100'; ?> rounded-lg mx-2">
                        <i class="fas fa-home w-5 text-center"></i>
                        <span class="mr-3">לוח בקרה</span>
                    </a>
                </li>
                <li>
                    <a href="appointments.php" class="flex items-center px-4 py-3 <?php echo ($current_page == 'appointments') ? 'text-primary bg-pastel-purple bg-opacity-30' : 'text-gray-600 hover:bg-gray-100'; ?> rounded-lg mx-2">
                        <i class="fas fa-calendar-alt w-5 text-center"></i>
                        <span class="mr-3">יומן תורים</span>
                    </a>
                </li>
                <li>
                    <a href="customers.php" class="flex items-center px-4 py-3 <?php echo ($current_page == 'customers') ? 'text-primary bg-pastel-purple bg-opacity-30' : 'text-gray-600 hover:bg-gray-100'; ?> rounded-lg mx-2">
                        <i class="fas fa-users w-5 text-center"></i>
                        <span class="mr-3">לקוחות</span>
                    </a>
                </li>
                <li>
                    <a href="staff.php" class="flex items-center px-4 py-3 <?php echo ($current_page == 'staff') ? 'text-primary bg-pastel-purple bg-opacity-30' : 'text-gray-600 hover:bg-gray-100'; ?> rounded-lg mx-2">
                        <i class="fas fa-user-tie w-5 text-center"></i>
                        <span class="mr-3">צוות</span>
                    </a>
                </li>
                <li>
                    <a href="services.php" class="flex items-center px-4 py-3 <?php echo ($current_page == 'services') ? 'text-primary bg-pastel-purple bg-opacity-30' : 'text-gray-600 hover:bg-gray-100'; ?> rounded-lg mx-2">
                        <i class="fas fa-concierge-bell w-5 text-center"></i>
                        <span class="mr-3">שירותים</span>
                    </a>
                </li>
                <li>
                    <a href="settings.php" class="flex items-center px-4 py-3 <?php echo ($current_page == 'settings') ? 'text-primary bg-pastel-purple bg-opacity-30' : 'text-gray-600 hover:bg-gray-100'; ?> rounded-lg mx-2">
                        <i class="fas fa-cog w-5 text-center"></i>
                        <span class="mr-3">הגדרות</span>
                    </a>
                </li>
            </ul>
        </nav>
        
        <div class="p-4 border-t">
            <a href="logout.php" class="flex items-center text-gray-600 hover:text-primary">
                <i class="fas fa-sign-out-alt"></i>
                <span class="mr-2">התנתק</span>
            </a>
        </div>
    </div>
</div>