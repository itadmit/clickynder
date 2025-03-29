<?php
// וידוא שלא ניתן לגשת לקובץ ישירות
if (!defined('ACCESS_ALLOWED')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}
?>

    </div> <!-- סוגר את ה-flex container שנפתח ב-header -->
    
    <script>
        // Mobile sidebar toggle
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const closeSidebar = document.getElementById('closeSidebar');
            const mobileSidebar = document.getElementById('mobileSidebar');
            
            if (sidebarToggle && closeSidebar && mobileSidebar) {
                sidebarToggle.addEventListener('click', function() {
                    mobileSidebar.classList.remove('hidden');
                });
                
                closeSidebar.addEventListener('click', function() {
                    mobileSidebar.classList.add('hidden');
                });
                
                // Close sidebar when clicking outside
                mobileSidebar.addEventListener('click', function(e) {
                    if (e.target === mobileSidebar) {
                        mobileSidebar.classList.add('hidden');
                    }
                });
            }
        });
    </script>
</body>
</html>