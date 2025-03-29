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
?>
                <!-- Booking Form Section -->
                <section id="booking" class="bg-white rounded-2xl shadow-sm p-6">
                    <h2 class="text-2xl font-bold mb-6">קביעת תור</h2>
                    
                    <!-- Booking Steps Indicator -->
                    <div class="mb-8">
                        <div class="flex justify-between mb-2">
                            <div class="text-center">
                                <div class="step-indicator w-8 h-8 bg-primary text-white rounded-full flex items-center justify-center mx-auto mb-1" data-step="1">1</div>
                                <span class="text-xs">בחירת שירות</span>
                            </div>
                            <div class="text-center">
                                <div class="step-indicator w-8 h-8 bg-gray-300 text-gray-600 rounded-full flex items-center justify-center mx-auto mb-1" data-step="2">2</div>
                                <span class="text-xs">בחירת מועד</span>
                            </div>
                            <div class="text-center">
                                <div class="step-indicator w-8 h-8 bg-gray-300 text-gray-600 rounded-full flex items-center justify-center mx-auto mb-1" data-step="3">3</div>
                                <span class="text-xs">פרטים אישיים</span>
                            </div>
                            <div class="text-center">
                                <div class="step-indicator w-8 h-8 bg-gray-300 text-gray-600 rounded-full flex items-center justify-center mx-auto mb-1" data-step="4">4</div>
                                <span class="text-xs">אישור</span>
                            </div>
                        </div>
                        <div class="relative pt-1">
                            <div class="overflow-hidden h-2 text-xs flex rounded bg-gray-200">
                                <div id="progress-bar" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-primary" style="width: 25%"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 1: Service Selection -->
                    <div id="step1" class="booking-step active">
                        <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center">
                            <h3 class="text-lg font-semibold mb-2">בחר שירות</h3>
                            <p class="text-gray-600 mb-4">לחץ על אחד השירותים למעלה כדי להתחיל</p>
                            
                            <div id="selectedServiceContainer" class="hidden bg-pastel-purple bg-opacity-30 p-4 rounded-xl text-center">
                                <p class="mb-2">השירות שנבחר:</p>
                                <h4 id="selectedServiceName" class="text-xl font-bold text-primary"></h4>
                                <input type="hidden" id="selectedServiceId" value="">
                            </div>
                            
                            <button id="nextToStep2" class="mt-4 bg-primary hover:bg-primary-dark text-white font-bold py-2 px-6 rounded-xl transition duration-300 hidden">
                                המשך לבחירת מועד
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 2: Date & Time Selection -->
                    <div id="step2" class="booking-step">
                        <!-- אם יש מספר אנשי צוות, נציג בחירת איש צוות -->
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
                        
                        <div id="calendarContainer" class="mb-6">
                            <!-- כאן יוצג לוח השנה -->
                        </div>
                        
                        <div id="timeSlots" class="hidden border-t pt-4">
                            <h3 class="text-lg font-semibold mb-4">בחר שעה ליום <span id="selectedDate"></span></h3>
                            
                            <div id="timeSlotsContainer" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-2"></div>
                            
                            <div id="noTimeSlotsMessage" class="text-center py-8 hidden">
                                <p class="text-gray-600">אין זמנים פנויים ביום זה. אנא בחר יום אחר.</p>
                                
                                <!-- אופציה להירשם לרשימת המתנה -->
                                <button id="joinWaitlistBtn" class="mt-4 bg-secondary hover:bg-secondary-dark text-white font-bold py-2 px-6 rounded-xl transition duration-300">
                                    הירשם לרשימת המתנה
                                </button>
                            </div>
                        </div>
                        
                        <div class="flex justify-between mt-6">
                            <button id="backToStep1" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-6 rounded-xl transition duration-300">
                                חזרה
                            </button>
                            <button id="nextToStep3" class="bg-gray-300 text-gray-500 cursor-not-allowed font-bold py-2 px-6 rounded-xl transition duration-300">
                                המשך
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 3: Customer Details -->
                    <div id="step3" class="booking-step">
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
                            <button id="backToStep2" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-6 rounded-xl transition duration-300">
                                חזרה
                            </button>
                            <button id="nextToStep4" class="bg-primary hover:bg-primary-dark text-white font-bold py-2 px-6 rounded-xl transition duration-300">
                                המשך
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 4: Booking Confirmation -->
                    <div id="step4" class="booking-step">
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
                            <button id="backToStep3" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-6 rounded-xl transition duration-300">
                                חזרה
                            </button>
                            <button id="confirmBooking" class="bg-primary hover:bg-primary-dark text-white font-bold py-2 px-6 rounded-xl transition duration-300">
                                אישור הזמנה
                            </button>
                        </div>
                    </div>
                    
                    <!-- Step 5: Success Message (shown after booking) -->
                    <div id="step5" class="booking-step">
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
                        <div class="bg-white rounded-2xl p-6 max-w-md w-full mx-4">
                            <h3 class="text-xl font-bold mb-4">רשימת המתנה</h3>
                            <p class="text-gray-600 mb-4">אין תורים פנויים כרגע. השאר פרטים ונעדכן אותך כשיתפנה תור.</p>
                            
                            <form id="waitlistForm" class="space-y-4">
                                <input type="hidden" id="waitlist_service_id" name="service_id">
                                <input type="hidden" id="waitlist_staff_id" name="staff_id">
                                <input type="hidden" name="tenant_id" value="<?php echo $tenant_id; ?>">
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="waitlist_first_name" class="block text-gray-700 text-sm font-medium mb-1">שם פרטי *</label>
                                        <input type="text" id="waitlist_first_name" name="first_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                                    </div>
                                    <div>
                                        <label for="waitlist_last_name" class="block text-gray-700 text-sm font-medium mb-1">שם משפחה *</label>
                                        <input type="text" id="waitlist_last_name" name="last_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                                    </div>
                                </div>
                                
                                <div>
                                    <label for="waitlist_phone" class="block text-gray-700 text-sm font-medium mb-1">טלפון נייד *</label>
                                    <input type="tel" id="waitlist_phone" name="phone" dir="ltr" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                                </div>
                                
                                <div>
                                    <label for="waitlist_email" class="block text-gray-700 text-sm font-medium mb-1">אימייל</label>
                                    <input type="email" id="waitlist_email" name="email" dir="ltr" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                </div>
                                
                                <div>
                                    <label for="waitlist_preferred_date" class="block text-gray-700 text-sm font-medium mb-1">תאריך מועדף</label>
                                    <input type="date" id="waitlist_preferred_date" name="preferred_date" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="waitlist_preferred_time_start" class="block text-gray-700 text-sm font-medium mb-1">משעה</label>
                                        <input type="time" id="waitlist_preferred_time_start" name="preferred_time_start" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                    </div>
                                    <div>
                                        <label for="waitlist_preferred_time_end" class="block text-gray-700 text-sm font-medium mb-1">עד שעה</label>
                                        <input type="time" id="waitlist_preferred_time_end" name="preferred_time_end" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                    </div>
                                </div>
                                
                                <div>
                                    <label for="waitlist_notes" class="block text-gray-700 text-sm font-medium mb-1">הערות נוספות</label>
                                    <textarea id="waitlist_notes" name="notes" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg"></textarea>
                                </div>
                                
                                <div class="flex justify-end space-x-4 space-x-reverse">
                                    <button type="button" id="closeWaitlistModal" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100">ביטול</button>
                                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark">הירשם לרשימת המתנה</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>