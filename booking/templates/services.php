<?php
/**
 * Booking System - Services Template
 * 
 * מאוכסן ב: booking/templates/services.php
 * תבנית תצוגת השירותים
 */

// וידוא שהקובץ לא נטען ישירות
if (!defined('ACCESS_ALLOWED') && basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header("Location: ../../index.php");
    exit;
}
?>
            <!-- Services and Booking Section -->
            <div class="md:col-span-2">
                <!-- Services Section -->
                <section id="services" class="mb-12">
                    <h2 class="text-2xl font-bold mb-6">השירותים שלנו</h2>
                    
                    <?php if (empty($services)): ?>
                        <div class="bg-gray-100 p-8 rounded-2xl text-center">
                            <p class="text-gray-500">אין שירותים זמינים כרגע</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($categories as $category): ?>
                            <?php if (isset($services_by_category[$category['category_id']])): ?>
                                <div class="mb-8">
                                    <h3 class="text-xl font-semibold mb-4"><?php echo htmlspecialchars($category['name']); ?></h3>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <?php foreach ($services_by_category[$category['category_id']] as $service): ?>
                                            <div class="service-card bg-white rounded-xl p-4 border-2 border-transparent hover:shadow-md transition duration-300 cursor-pointer"
                                                 onclick="selectService(<?php echo $service['service_id']; ?>, '<?php echo htmlspecialchars(addslashes($service['name'])); ?>')">
                                                <div class="flex justify-between items-start">
                                                    <div>
                                                        <h4 class="font-semibold text-lg"><?php echo htmlspecialchars($service['name']); ?></h4>
                                                        <?php if (!empty($service['description'])): ?>
                                                            <p class="text-gray-600 text-sm mt-1"><?php echo htmlspecialchars($service['description']); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <?php if ($service['price'] > 0): ?>
                                                            <span class="bg-gray-100 rounded-lg px-2 py-1 text-gray-800 font-medium">
                                                                <?php echo number_format($service['price'], 0); ?> ₪
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="mt-2 text-sm text-gray-500">
                                                    <i class="far fa-clock mr-1"></i> <?php echo $service['duration']; ?> דקות
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                        <!-- שירותים ללא קטגוריה -->
                        <?php if (isset($services_by_category[null]) || isset($services_by_category[''])): ?>
                            <div class="mb-8">
                                <h3 class="text-xl font-semibold mb-4">שירותים נוספים</h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <?php 
                                    $uncategorized = isset($services_by_category[null]) ? $services_by_category[null] : 
                                                    (isset($services_by_category['']) ? $services_by_category[''] : []);
                                    ?>
                                    
                                    <?php foreach ($uncategorized as $service): ?>
                                        <div class="service-card bg-white rounded-xl p-4 border-2 border-transparent hover:shadow-md transition duration-300 cursor-pointer"
                                             onclick="selectService(<?php echo $service['service_id']; ?>, '<?php echo htmlspecialchars(addslashes($service['name'])); ?>')">
                                            <div class="flex justify-between items-start">
                                                <div>
                                                    <h4 class="font-semibold text-lg"><?php echo htmlspecialchars($service['name']); ?></h4>
                                                    <?php if (!empty($service['description'])): ?>
                                                        <p class="text-gray-600 text-sm mt-1"><?php echo htmlspecialchars($service['description']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <?php if ($service['price'] > 0): ?>
                                                        <span class="bg-gray-100 rounded-lg px-2 py-1 text-gray-800 font-medium">
                                                            <?php echo number_format($service['price'], 0); ?> ₪
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="mt-2 text-sm text-gray-500">
                                                <i class="far fa-clock mr-1"></i> <?php echo $service['duration']; ?> דקות
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>