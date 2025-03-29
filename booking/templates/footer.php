<?php
/**
 * Booking System - Footer Template
 * 
 * מאוכסן ב: booking/templates/footer.php
 * תבנית תחתית העמוד וסקריפטים
 */

// וידוא שהקובץ לא נטען ישירות
if (!defined('ACCESS_ALLOWED') && basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header("Location: ../../index.php");
    exit;
}
?>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8 mt-12">
        <div class="max-w-6xl mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <h3 class="text-xl font-bold mb-2"><?php echo htmlspecialchars($tenant['business_name']); ?></h3>
                    <p class="text-gray-400">&copy; <?php echo date('Y'); ?> כל הזכויות שמורות</p>
                </div>
                
                <div class="flex space-x-4 space-x-reverse">
                    <?php if (!empty($tenant['facebook_url'])): ?>
                        <a href="<?php echo htmlspecialchars($tenant['facebook_url']); ?>" target="_blank" class="text-gray-400 hover:text-white">
                            <i class="fab fa-facebook-f text-xl"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($tenant['instagram_url'])): ?>
                        <a href="<?php echo htmlspecialchars($tenant['instagram_url']); ?>" target="_blank" class="text-gray-400 hover:text-white">
                            <i class="fab fa-instagram text-xl"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($tenant['website_url'])): ?>
                        <a href="<?php echo htmlspecialchars($tenant['website_url']); ?>" target="_blank" class="text-gray-400 hover:text-white">
                            <i class="fas fa-globe text-xl"></i>
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="mt-4 md:mt-0 text-sm text-gray-400">
                    <p>מופעל ע"י <a href="../index.php" class="text-primary hover:text-primary-light">קליקינדר</a></p>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- jQuery Library -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="assets/js/booking.js"></script>
</body>
</html>