<?php
// וידוא שלא ניתן לגשת לקובץ ישירות
if (!defined('ACCESS_ALLOWED')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

// קבלת שם העמוד הנוכחי
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// שליפת פרטי העסק
$business_name = $_SESSION['business_name'] ?? "העסק שלי";
$business_logo = $_SESSION['logo_path'] ?? "https://via.placeholder.com/50";
$business_slug = $_SESSION['slug'] ?? "";
?>

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
    
    <div class="absolute bottom-0 left-0 right-0 p-4 border-t">
        <a href="logout.php" class="flex items-center text-gray-600 hover:text-primary">
            <i class="fas fa-sign-out-alt"></i>
            <span class="mr-2">התנתק</span>
        </a>
    </div>
</aside>

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