<?php
/**
 * Booking System - Booking Form Template
 * 
 * מאוכסן ב: booking/templates/booking_form.php
 * תבנית טופס הזמנת תור
 */

// וידוא שהקובץ לא נטען ישירות
if (!defined('ACCESS_ALLOWED') && basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header("Location: ../../index.php");
    exit;
}

// שליפת רשימת הסניפים הפעילים
$query = "SELECT * FROM branches WHERE tenant_id = :tenant_id AND is_active = 1 ORDER BY is_main DESC, name";
$stmt = $db->prepare($query);
$stmt->bindParam(":tenant_id", $tenant_id);
$stmt->execute();
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
$has_multiple_branches = count($branches) > 1;
?>
                <!-- Booking Form Section -->
                <section id="booking" class="bg-white rounded-2xl shadow-sm p-6">
                    <h2 class="text-2xl font-bold mb-6">קביעת תור</h2>
                    
                    <!-- Booking Steps Indicator -->
                    <div class="mb-8">
                        <div class="flex justify-between mb-2">
                            <?php if ($has_multiple_branches): ?>
                            <div class="text-center">
                                <div class="step-indicator w-8 h-8 bg-primary text-white rounded-full flex items-center justify-center mx-auto mb-1" data-step="1">1</div>
                                <span class="text-xs">בחירת סניף</span>
                            </div>
                            <?php endif; ?>
                            <div class="text-center">
                                <div class="step-indicator w-8 h-8 <?php echo $has_multiple_branches ? 'bg-gray-300 text-gray-600' : 'bg-primary text-white'; ?> rounded-full flex items-center justify-center mx-auto mb-1" data-step="<?php echo $has_multiple_branches ? '2' : '1'; ?>">
                                    <?php echo $has_multiple_branches ? '2' : '1'; ?>
                                </div>
                                <span class="text-xs">בחירת שירות</span>
                            </div>
                            <div class="text-center">
                                <div class="step-indicator w-8 h-8 bg-gray-300 text-gray-600 rounded-full flex items-center justify-center mx-auto mb-1" data-step="<?php echo $has_multiple_branches ? '3' : '2'; ?>">
                                    <?php echo $has_multiple_branches ? '3' : '2'; ?>
                                </div>
                                <span class="text-xs">בחירת מועד</span>
                            </div>
                            <div class="text-center">
                                <div class="step-indicator w-8 h-8 bg-gray-300 text-gray-600 rounded-full flex items-center justify-center mx-auto mb-1" data-step="<?php echo $has_multiple_branches ? '4' : '3'; ?>">
                                    <?php echo $has_multiple_branches ? '4' : '3'; ?>
                                </div>
                                <span class="text-xs">פרטים אישיים</span>
                            </div>
                            <div class="text-center">
                                <div class="step-indicator w-8 h-8 bg-gray-300 text-gray-600 rounded-full flex items-center justify-center mx-auto mb-1" data-step="<?php echo $has_multiple_branches ? '5' : '4'; ?>">
                                    <?php echo $has_multiple_branches ? '5' : '4'; ?>
                                </div>
                                <span class="text-xs">אישור</span>
                            </div>
                        </div>
                        <div class="relative pt-1">
                            <div class="overflow-hidden h-2 text-xs flex rounded bg-gray-200">
                                <div id="progress-bar" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-primary" style="width: 25%"></div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($has_multiple_branches): ?>
                    <!-- Step 1: Branch Selection -->
                    <div id="step1" class="booking-step active">
                        <div class="border-2 border-dashed border-gray-300 rounded-xl p-6">
                            <h3 class="text-lg font-semibold mb-4 text-center">בחר סניף</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php foreach ($branches as $branch): ?>
                                <div class="branch-card cursor-pointer border-2 border-gray-300 hover:border-primary rounded-xl p-4 transition duration-300"
                                     data-branch-id="<?php echo $branch['branch_id']; ?>">
                                    <div class="flex items-start">
                                        <div class="flex-grow">
                                            <h4 class="font-semibold text-lg mb-1">
                                                <?php echo htmlspecialchars($branch['name']); ?>
                                                <?php if ($branch['is_main']): ?>
                                                    <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full mr-2">
                                                        סניף ראשי
                                                    </span>
                                                <?php endif; ?>
                                            </h4>
                                            <?php if ($branch['address']): ?>
                                                <p class="text-gray-600 mb-2">
                                                    <i class="fas fa-map-marker-alt ml-1"></i>
                                                    <?php echo htmlspecialchars($branch['address']); ?>
                                                </p>
                                            <?php endif; ?>
                                            <?php if ($branch['phone']): ?>
                                                <p class="text-gray-600">
                                                    <i class="fas fa-phone ml-1"></i>
                                                    <?php echo htmlspecialchars($branch['phone']); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="branch-check hidden text-primary">
                                            <i class="fas fa-check-circle text-2xl"></i>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <input type="hidden" name="branch_id" id="selected_branch_id" value="">
                            
                            <div class="flex justify-end mt-6">
                                <button id="nextToStep2" class="bg-gray-300 text-gray-500 cursor-not-allowed font-bold py-2 px-6 rounded-xl transition duration-300">
                                    המשך לבחירת שירות
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                        <input type="hidden" name="branch_id" value="<?php echo $branches[0]['branch_id']; ?>">
                    <?php endif; ?>
                    
                    <!-- Step 2: Service Selection -->
                    <div id="<?php echo $has_multiple_branches ? 'step2' : 'step1'; ?>" class="booking-step <?php echo $has_multiple_branches ? '' : 'active'; ?>">
                        <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center">
                            <h3 class="text-lg font-semibold mb-2">בחר שירות</h3>
                            <p class="text-gray-600 mb-4">לחץ על אחד השירותים למעלה כדי להתחיל</p>
                            
                            <div id="selectedServiceContainer" class="hidden bg-pastel-purple bg-opacity-30 p-4 rounded-xl text-center">
                                <p class="mb-2">השירות שנבחר:</p>
                                <h4 id="selectedServiceName" class="text-xl font-bold text-primary"></h4>
                                <input type="hidden" id="selectedServiceId" value="">
                            </div>
                            
                            <button id="nextToStep3" class="mt-4 bg-primary hover:bg-primary-dark text-white font-bold py-2 px-6 rounded-xl transition duration-300 hidden">
                                המשך לבחירת מועד
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 3: Date & Time Selection -->
                    <div id="step3" class="booking-step">
                        <!-- בחירת איש צוות - אם קיים -->
                        <?php if (count($staff) > 1): ?>
                        <div class="mb-6">
                            <label class="block text-gray-700 font-medium mb-2">בחר נותן שירות:</label>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3" id="staffSelector">
                                <?php foreach ($staff as $member): ?>
                                <div class="staff-card bg-white border-2 border-gray-300 rounded-xl p-3 text-center cursor-pointer hover:border-primary" 
                                     data-staff-id="<?php echo $member['staff_id']; ?>">
                                    <?php if (!empty($member['image_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($member['image_path']); ?>" class="w-16 h-16 object-cover rounded-full mx-auto mb-2">
                                    <?php else: ?>
                                        <div class="w-16 h-16 rounded-full bg-primary text-white flex items-center justify-center mx-auto mb-2 text-xl font-bold">
                                            <?php echo mb_substr($member['name'], 0, 1, 'UTF-8'); ?>
                                        </div>
                                    <?php endif; ?>
                                    <p class="font-medium"><?php echo htmlspecialchars($member['name']); ?></p>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- מיכל בסגנון Calendly -->
                        <div class="booking-columns">
                            <!-- עמודה שמאלית - מידע על השירות -->
                            <div class="booking-info">
                                <h3 class="text-lg font-semibold mb-4">פרטי השירות</h3>
                                
                                <div class="space-y-4">
                                    <div>
                                        <div class="font-medium text-gray-600">שירות:</div>
                                        <div id="service-details-name" class="text-lg font-semibold"></div>
                                    </div>
                                    
                                    <div>
                                        <div class="font-medium text-gray-600">משך:</div>
                                        <div id="service-details-duration" class="flex items-center">
                                            <i class="far fa-clock mr-1"></i>
                                            <span></span>
                                        </div>
                                    </div>
                                    
                                    <?php if (count($staff) <= 1 && !empty($staff)): ?>
                                    <div>
                                        <div class="font-medium text-gray-600">נותן שירות:</div>
                                        <div id="service-details-staff" class="flex items-center">
                                            <?php if (!empty($staff[0]['image_path'])): ?>
                                                <img src="<?php echo htmlspecialchars($staff[0]['image_path']); ?>" class="w-8 h-8 object-cover rounded-full mr-2">
                                            <?php endif; ?>
                                            <span><?php echo !empty($staff[0]) ? htmlspecialchars($staff[0]['name']) : ''; ?></span>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($tenant['phone'])): ?>
                                    <div class="mt-8 pt-4 border-t border-gray-200">
                                        <div class="font-medium">צריך עזרה?</div>
                                        <div class="text-sm">
                                            <a href="tel:<?php echo htmlspecialchars($tenant['phone']); ?>" class="text-primary flex items-center mt-1">
                                                <i class="fas fa-phone-alt mr-1"></i>
                                                <?php echo htmlspecialchars($tenant['phone']); ?>
                                            </a>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- עמודה ימנית - בחירת תאריך ושעה -->
                            <div class="booking-calendar">
                                <h3 class="text-lg font-semibold mb-4">בחר תאריך ושעה</h3>
                                
                                <div id="calendarContainer" class="mb-6">
                                    <!-- לוח השנה בסגנון Calendly יוצג כאן ע"י JavaScript -->
                                </div>
                                
                                <div id="timeSlots" class="hidden">
                                    <!-- רשימת השעות הפנויות תוצג כאן ע"י JavaScript -->
                                </div>
                                
                                <div id="noTimeSlotsMessage" class="text-center py-4 hidden">
                                    <p class="text-gray-600">אין זמנים פנויים ביום זה. אנא בחר יום אחר.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-between mt-6">
                            <button id="backToStep1" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-6 rounded-xl transition duration-300">
                                חזרה
                            </button>
                            <button id="nextToStep4" class="bg-gray-300 text-gray-500 cursor-not-allowed font-bold py-2 px-6 rounded-xl transition duration-300">
                                המשך
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 4: Customer Details -->
                    <div id="step4" class="booking-step">
                        <div class="mb-6">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                                <div>
                                    <label for="first_name" class="block text-gray-700 font-medium mb-1">שם פרטי *</label>
                                    <input type="text" id="first_name" name="first_name" 
                                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                           required>
                                </div>
                                <div>
                                    <label for="last_name" class="block text-gray-700 font-medium mb-1">שם משפחה *</label>
                                    <input type="text" id="last_name" name="last_name" 
                                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                           required>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="phone" class="block text-gray-700 font-medium mb-1">טלפון נייד *</label>
                                    <input type="tel" id="phone" name="phone" dir="ltr"
                                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                           required>
                                </div>
                                <div>
                                    <label for="email" class="block text-gray-700 font-medium mb-1">אימייל</label>
                                    <input type="email" id="email" name="email" dir="ltr"
                                           class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <label for="notes" class="block text-gray-700 font-medium mb-1">הערות (אופציונלי)</label>
                                <textarea id="notes" name="notes" rows="3"
                                         class="w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                         placeholder="הערות מיוחדות או בקשות"></textarea>
                            </div>
                            
                            <!-- שאלות מקדימות - אם הוגדרו -->
                            <?php if (!empty($general_questions)): ?>
                            <div class="mt-6 border-t pt-6">
                                <h4 class="font-semibold mb-4">שאלות מקדימות:</h4>
                                
                                <div class="space-y-4">
                                    <?php foreach ($general_questions as $question): ?>
                                    <div class="question-item">
                                        <label class="block text-gray-700 mb-1">
                                            <?php echo htmlspecialchars($question['question_text']); ?>
                                            <?php if ($question['is_required']): ?>
                                            <span class="text-red-500">*</span>
                                            <?php endif; ?>
                                        </label>
                                        
                                        <?php if ($question['question_type'] == 'text'): ?>
                                            <input type="text" class="intake-answer w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                                   data-question-id="<?php echo $question['question_id']; ?>"
                                                   <?php echo $question['is_required'] ? 'required' : ''; ?>>
                                                   
                                        <?php elseif ($question['question_type'] == 'textarea'): ?>
                                            <textarea class="intake-answer w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary" rows="3"
                                                      data-question-id="<?php echo $question['question_id']; ?>"
                                                      <?php echo $question['is_required'] ? 'required' : ''; ?>></textarea>
                                                      
                                        <?php elseif ($question['question_type'] == 'select'): ?>
                                            <?php $options = json_decode($question['options'], true); ?>
                                            <select class="intake-answer w-full px-4 py-2 border-2 border-gray-300 rounded-xl focus:outline-none focus:border-primary"
                                                    data-question-id="<?php echo $question['question_id']; ?>"
                                                    <?php echo $question['is_required'] ? 'required' : ''; ?>>
                                                <option value="">בחר...</option>
                                                <?php foreach ($options as $option): ?>
                                                    <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            
                                        <?php elseif ($question['question_type'] == 'radio'): ?>
                                            <?php $options = json_decode($question['options'], true); ?>
                                            <div class="flex flex-wrap gap-4">
                                                <?php foreach ($options as $index => $option): ?>
                                                    <label class="inline-flex items-center">
                                                        <input type="radio" class="intake-answer" name="radio_<?php echo $question['question_id']; ?>"
                                                               value="<?php echo htmlspecialchars($option); ?>"
                                                               data-question-id="<?php echo $question['question_id']; ?>"
                                                               <?php echo $question['is_required'] && $index == 0 ? 'required' : ''; ?>>
                                                        <span class="mr-2"><?php echo htmlspecialchars($option); ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                            
                                        <?php elseif ($question['question_type'] == 'checkbox'): ?>
                                            <?php $options = json_decode($question['options'], true); ?>
                                            <div class="flex flex-wrap gap-4">
                                                <?php foreach ($options as $option): ?>
                                                    <label class="inline-flex items-center">
                                                        <input type="checkbox" class="intake-answer" value="<?php echo htmlspecialchars($option); ?>"
                                                               data-question-id="<?php echo $question['question_id']; ?>">
                                                        <span class="mr-2"><?php echo htmlspecialchars($option); ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flex justify-between mt-6">
                            <button id="backToStep3" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-6 rounded-xl transition duration-300">
                                חזרה
                            </button>
                            <button id="nextToStep5" class="bg-primary hover:bg-primary-dark text-white font-bold py-2 px-6 rounded-xl transition duration-300">
                                המשך
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 5: Booking Confirmation -->
                    <div id="step5" class="booking-step">
                        <div class="border-2 border-gray-200 rounded-xl p-6 mb-6">
                            <h3 class="text-xl font-semibold mb-4 text-center">סיכום הזמנה</h3>
                            
                            <div class="space-y-4">
                                <div class="flex justify-between pb-2 border-b border-gray-200">
                                    <span class="font-medium">שירות:</span>
                                    <span id="summary_service"></span>
                                </div>
                                
                                <div class="flex justify-between pb-2 border-b border-gray-200">
                                    <span class="font-medium">תאריך:</span>
                                    <span id="summary_date"></span>
                                </div>
                                
                                <div class="flex justify-between pb-2 border-b border-gray-200">
                                    <span class="font-medium">שעה:</span>
                                    <span id="summary_time"></span>
                                </div>
                                
                                <div class="flex justify-between pb-2 border-b border-gray-200">
                                    <span class="font-medium">נותן שירות:</span>
                                    <span id="summary_staff"></span>
                                </div>
                                
                                <div class="flex justify-between pb-2 border-b border-gray-200">
                                    <span class="font-medium">שם מלא:</span>
                                    <span id="summary_name"></span>
                                </div>
                                
                                <div class="flex justify-between pb-2 border-b border-gray-200">
                                    <span class="font-medium">טלפון:</span>
                                    <span id="summary_phone"></span>
                                </div>
                                
                                <div id="summary_email_container" class="flex justify-between pb-2 border-b border-gray-200 hidden">
                                    <span class="font-medium">אימייל:</span>
                                    <span id="summary_email"></span>
                                </div>
                                
                                <div id="summary_notes_container" class="pb-2 border-b border-gray-200 hidden">
                                    <div class="font-medium mb-1">הערות:</div>
                                    <div id="summary_notes" class="text-gray-700"></div>
                                </div>
                            </div>
                            
                            <div class="mt-6 text-center">
                                <p class="text-sm text-gray-500 mb-2">בלחיצה על "אישור הזמנה" אתה מאשר שקראת והסכמת לתנאי השימוש שלנו</p>
                                
                                <div class="flex justify-center mt-4">
                                    <div class="bg-pastel-green bg-opacity-40 rounded-lg p-3 max-w-md">
                                        <p class="text-green-800">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            תקבל אישור לתור שלך באמצעות SMS ואימייל (אם הוזן)
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-between mt-6">
                            <button id="backToStep4" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-6 rounded-xl transition duration-300">
                                חזרה
                            </button>
                            <button id="confirmBooking" class="bg-primary hover:bg-primary-dark text-white font-bold py-2 px-6 rounded-xl transition duration-300">
                                אישור הזמנה
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 6: Success Message (shown after booking) -->
                    <div id="step6" class="booking-step">
                        <div class="text-center py-8">
                            <div class="w-20 h-20 bg-pastel-green text-green-600 rounded-full flex items-center justify-center mx-auto mb-6">
                                <i class="fas fa-check-circle text-4xl"></i>
                            </div>
                            
                            <h3 class="text-2xl font-bold mb-2">התור נקבע בהצלחה!</h3>
                            <p class="text-gray-600 mb-4 max-w-md mx-auto">התור הוזמן בהצלחה. שלחנו לך אישור בהודעת SMS ואימייל (אם הוזן) עם פרטי התור.</p>
                            
                            <div class="bg-gray-100 rounded-xl p-4 max-w-md mx-auto mb-6">
                                <div class="font-medium mb-2">פרטי התור:</div>
                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    <div class="text-right font-medium">מספר אסמכתא:</div>
                                    <div class="text-left" id="booking_reference"></div>
                                    
                                    <div class="text-right font-medium">שירות:</div>
                                    <div class="text-left" id="booking_service"></div>
                                    
                                    <div class="text-right font-medium">תאריך:</div>
                                    <div class="text-left" id="booking_date"></div>
                                    
                                    <div class="text-right font-medium">שעה:</div>
                                    <div class="text-left" id="booking_time"></div>
                                    
                                    <div class="text-right font-medium">נותן שירות:</div>
                                    <div class="text-left" id="booking_staff"></div>
                                </div>
                            </div>
                            
                            <div class="flex flex-col md:flex-row justify-center gap-4">
                                <a href="#" id="addToCalendarBtn" class="bg-primary hover:bg-primary-dark text-white font-bold py-2 px-6 rounded-xl transition duration-300">
                                    הוסף תור ליומן
                                </a>
                                <a href="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-6 rounded-xl transition duration-300">
                                    חזרה לדף הבית
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Waitlist Modal -->
                    <div id="waitlistModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
                        <div class="bg-white rounded-2xl max-w-md w-full max-h-screen overflow-y-auto">
                            <div class="flex justify-between items-center p-6 border-b">
                                <h2 class="text-xl font-bold">הצטרפות לרשימת המתנה</h2>
                                <button id="closeWaitlistModal" class="text-gray-500 hover:text-gray-700">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            
                            <div class="p-6">
                                <form id="waitlistForm" class="space-y-4">
                                    <input type="hidden" name="tenant_id" value="<?php echo $tenant_id; ?>">
                                    <input type="hidden" id="waitlist_service_id" name="service_id" value="">
                                    <input type="hidden" id="waitlist_staff_id" name="staff_id" value="">
                                    
                                    <!-- פרטי לקוח -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="waitlist_first_name" class="block text-gray-700 font-medium mb-2">שם פרטי *</label>
                                            <input type="text" id="waitlist_first_name" name="first_name" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-primary" required>
                                        </div>
                                        
                                        <div>
                                            <label for="waitlist_last_name" class="block text-gray-700 font-medium mb-2">שם משפחה *</label>
                                            <input type="text" id="waitlist_last_name" name="last_name" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-primary" required>
                                        </div>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="waitlist_phone" class="block text-gray-700 font-medium mb-2">טלפון *</label>
                                            <input type="tel" id="waitlist_phone" name="phone" dir="ltr" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-primary" required>
                                        </div>
                                        
                                        <div>
                                            <label for="waitlist_email" class="block text-gray-700 font-medium mb-2">אימייל</label>
                                            <input type="email" id="waitlist_email" name="email" dir="ltr" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                                        </div>
                                    </div>
                                    
                                    <!-- זמנים מועדפים -->
                                    <div>
                                        <label for="waitlist_preferred_date" class="block text-gray-700 font-medium mb-2">תאריך מועדף</label>
                                        <input type="date" id="waitlist_preferred_date" name="preferred_date" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label for="waitlist_preferred_time_start" class="block text-gray-700 font-medium mb-2">משעה</label>
                                            <input type="time" id="waitlist_preferred_time_start" name="preferred_time_start" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                                        </div>
                                        <div>
                                            <label for="waitlist_preferred_time_end" class="block text-gray-700 font-medium mb-2">עד שעה</label>
                                            <input type="time" id="waitlist_preferred_time_end" name="preferred_time_end" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-primary">
                                        </div>
                                    </div>
                                    
                                    <!-- הערות -->
                                    <div>
                                        <label for="waitlist_notes" class="block text-gray-700 font-medium mb-2">הערות נוספות</label>
                                        <textarea id="waitlist_notes" name="notes" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-xl focus:outline-none focus:border-primary"></textarea>
                                    </div>
                                    
                                    <div class="flex justify-end mt-6 space-x-4 space-x-reverse">
                                        <button type="button" id="closeWaitlistBtn" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100">ביטול</button>
                                        <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark">הצטרף לרשימת המתנה</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Branch Selection
    const branchCards = document.querySelectorAll('.branch-card');
    const selectedBranchInput = document.getElementById('selected_branch_id');
    const nextToStep2Button = document.getElementById('nextToStep2');
    
    branchCards.forEach(card => {
        card.addEventListener('click', function() {
            // הסרת הבחירה הקודמת
            branchCards.forEach(c => {
                c.classList.remove('border-primary');
                c.querySelector('.branch-check').classList.add('hidden');
            });
            
            // סימון הסניף הנבחר
            this.classList.add('border-primary');
            this.querySelector('.branch-check').classList.remove('hidden');
            
            // שמירת ה-ID של הסניף הנבחר
            selectedBranchInput.value = this.dataset.branchId;
            
            // הפעלת כפתור ההמשך
            nextToStep2Button.classList.remove('bg-gray-300', 'text-gray-500', 'cursor-not-allowed');
            nextToStep2Button.classList.add('bg-primary', 'hover:bg-primary-dark', 'text-white');
        });
    });
    
    // עדכון נותני שירות זמינים בהתאם לבחירת שירות ותאריך
    function updateAvailableStaff() {
        const service_id = document.getElementById('selectedServiceId').value;
        const date = document.getElementById('date').value;
        const branch_id = selectedBranchInput.value;
        
        if (!service_id || !date || !branch_id) return;
        
        // איפוס בחירת נותן שירות ושעה
        const staffSelect = document.getElementById('staff');
        const timeSelect = document.getElementById('time');
        staffSelect.innerHTML = '<option value="">בחר נותן שירות</option>';
        timeSelect.innerHTML = '<option value="">בחר שעה</option>';
        
        // שליפת נותני שירות זמינים
        fetch(`../booking/get_available_staff.php?service_id=${service_id}&date=${date}&branch_id=${branch_id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.staff.length > 0) {
                    data.staff.forEach(staff => {
                        const option = document.createElement('option');
                        option.value = staff.staff_id;
                        option.textContent = staff.name;
                        staffSelect.appendChild(option);
                    });
                }
            });
    }
    
    // Navigation between steps
    const steps = ['step1', 'step2', 'step3', 'step4', 'step5', 'step6'];
    let currentStep = 0;
    
    function updateProgressBar() {
        const progress = ((currentStep + 1) / (steps.length - 1)) * 100;
        document.getElementById('progress-bar').style.width = `${progress}%`;
    }
    
    function showStep(stepIndex) {
        steps.forEach((step, index) => {
            const stepElement = document.getElementById(step);
            if (stepElement) {
                if (index === stepIndex) {
                    stepElement.classList.add('active');
                } else {
                    stepElement.classList.remove('active');
                }
            }
        });
        
        updateProgressBar();
    }
    
    // Event Listeners for Navigation
    document.getElementById('nextToStep2')?.addEventListener('click', function() {
        if (selectedBranchInput.value) {
            currentStep = 1;
            showStep(currentStep);
        }
    });
    
    // ... rest of your existing event listeners ...
});
</script>