<?php
/**
 * Booking System - Sidebar Template
 * 
 * מאוכסן ב: booking/templates/sidebar.php
 * תבנית סיידבר - פרטי עסק, שעות, צוות
 */

// וידוא שהקובץ לא נטען ישירות
if (!defined('ACCESS_ALLOWED') && basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header("Location: ../../index.php");
    exit;
}
?>
            <!-- Sidebar Information -->
            <div class="col-span-1">
                <!-- Business Info Card -->
                <div class="bg-white rounded-2xl shadow-sm p-6 mb-6">
                    <h2 class="text-xl font-bold mb-4">פרטי העסק</h2>
                    
                    <?php if (!empty($tenant['address'])): ?>
                        <div class="flex items-start mb-4">
                            <div class="text-primary mt-1"><i class="fas fa-map-marker-alt"></i></div>
                            <div class="mr-3">
                                <h3 class="font-medium">כתובת</h3>
                                <p class="text-gray-600"><?php echo htmlspecialchars($tenant['address']); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($tenant['phone'])): ?>
                        <div class="flex items-start mb-4">
                            <div class="text-primary mt-1"><i class="fas fa-phone-alt"></i></div>
                            <div class="mr-3">
                                <h3 class="font-medium">טלפון</h3>
                                <p class="text-gray-600 dir-ltr">
                                    <a href="tel:<?php echo htmlspecialchars($tenant['phone']); ?>" class="hover:text-primary">
                                        <?php echo htmlspecialchars($tenant['phone']); ?>
                                    </a>
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($tenant['email'])): ?>
                        <div class="flex items-start mb-4">
                            <div class="text-primary mt-1"><i class="fas fa-envelope"></i></div>
                            <div class="mr-3">
                                <h3 class="font-medium">אימייל</h3>
                                <p class="text-gray-600 dir-ltr">
                                    <a href="mailto:<?php echo htmlspecialchars($tenant['email']); ?>" class="hover:text-primary">
                                        <?php echo htmlspecialchars($tenant['email']); ?>
                                    </a>
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Social Media Links -->
                    <?php if (!empty($tenant['facebook_url']) || !empty($tenant['instagram_url']) || !empty($tenant['website_url'])): ?>
                        <div class="flex mt-4 space-x-4 space-x-reverse">
                            <?php if (!empty($tenant['facebook_url'])): ?>
                                <a href="<?php echo htmlspecialchars($tenant['facebook_url']); ?>" target="_blank" class="text-gray-500 hover:text-primary">
                                    <i class="fab fa-facebook-f text-xl"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($tenant['instagram_url'])): ?>
                                <a href="<?php echo htmlspecialchars($tenant['instagram_url']); ?>" target="_blank" class="text-gray-500 hover:text-primary">
                                    <i class="fab fa-instagram text-xl"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php if (!empty($tenant['website_url'])): ?>
                                <a href="<?php echo htmlspecialchars($tenant['website_url']); ?>" target="_blank" class="text-gray-500 hover:text-primary">
                                    <i class="fas fa-globe text-xl"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Business Hours Card -->
                <div class="bg-white rounded-2xl shadow-sm p-6 mb-6">
                    <h2 class="text-xl font-bold mb-4">שעות פעילות</h2>
                    
                    <div class="space-y-3">
                        <?php for ($day = 0; $day <= 6; $day++): ?>
                            <div class="flex justify-between items-center <?php echo ($day == $current_day) ? 'font-bold text-primary' : ''; ?>">
                                <span>יום <?php echo getDayName($day); ?></span>
                                <?php if (isset($hours_by_day[$day]) && $hours_by_day[$day]['is_open']): ?>
                                    <span>
                                        <?php echo formatTime($hours_by_day[$day]['open_time']); ?> - 
                                        <?php echo formatTime($hours_by_day[$day]['close_time']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-gray-500">סגור</span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (isset($breaks_by_day[$day]) && !empty($breaks_by_day[$day])): ?>
                                <?php foreach ($breaks_by_day[$day] as $break): ?>
                                    <div class="text-sm text-gray-500 mr-4 -mt-2">
                                        <i class="fas fa-pause-circle mr-1"></i>
                                        הפסקה: <?php echo formatTime($break['start_time']); ?> - <?php echo formatTime($break['end_time']); ?>
                                        <?php if (!empty($break['description'])): ?>
                                            (<?php echo htmlspecialchars($break['description']); ?>)
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <!-- Team Members (if any) -->
                <?php if (count($staff) > 1): ?>
                    <div class="bg-white rounded-2xl shadow-sm p-6">
                        <h2 class="text-xl font-bold mb-4">הצוות שלנו</h2>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <?php foreach ($staff as $member): ?>
                                <div class="text-center">
                                    <div class="mb-2">
                                        <?php if (!empty($member['image_path'])): ?>
                                            <img src="<?php echo htmlspecialchars($member['image_path']); ?>" alt="<?php echo htmlspecialchars($member['name']); ?>" 
                                                 class="w-20 h-20 object-cover rounded-full mx-auto">
                                        <?php else: ?>
                                            <div class="w-20 h-20 rounded-full bg-primary text-white flex items-center justify-center mx-auto text-xl font-bold">
                                                <?php echo mb_substr($member['name'], 0, 1, 'UTF-8'); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <h3 class="font-medium"><?php echo htmlspecialchars($member['name']); ?></h3>
                                    <?php if (!empty($member['position'])): ?>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($member['position']); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>