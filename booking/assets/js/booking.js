/**
 * Booking System - JavaScript
 * 
 * מאוכסן ב: booking/assets/js/booking.js
 * לוגיקת הלקוח למערכת הזמנת התורים
 */

// Global variables
let selectedServiceId = null;
let selectedServiceName = '';
let selectedDate = '';
let selectedTimeSlot = '';
let selectedStaffId = null;
let selectedStaffName = '';
let availableTimeSlots = [];
let calendar = null;

// לאחר טעינת העמוד
$(document).ready(function() {
    // גלילה לחלק ההזמנה בלחיצה על כפתור "קבע תור עכשיו"
    $('#bookNowBtn').click(function() {
        $('html, body').animate({
            scrollTop: $('#booking').offset().top - 50
        }, 1000);
    });
    
    // חשוב: אתחול ראשוני של staff_id - תיקון השגיאה
    if ($('.staff-card').length > 0) {
        selectedStaffId = $('.staff-card:first').data('staff-id');
        selectedStaffName = $('.staff-card:first').find('p').text();
    } else {
        // כאשר אין בחירת צוות בממשק, השתמש בערך ברירת מחדל מהשרת
        const defaultStaffId = $('meta[name="default-staff-id"]').attr('content');
        if (defaultStaffId) {
            selectedStaffId = parseInt(defaultStaffId);
        } else {
            // אם אין מידע, השתמש בערך 1 כברירת מחדל
            selectedStaffId = 1;
        }
    }
    
    // מגדיר את פעולות המעבר בין השלבים השונים
    setupStepNavigation();
    
    // מגדיר אירועים לטופס רשימת המתנה
    setupWaitlistHandlers();
    
    // עדכון פרטי השירות כאשר שירות נבחר
    const updateServiceDetails = function() {
        if (selectedServiceId) {
            // מצא את השירות שנבחר
            const serviceElement = $(`.service-card[onclick*="${selectedServiceId}"]`);
            if (serviceElement.length) {
                const serviceName = serviceElement.find('h4').text();
                const serviceDuration = serviceElement.find('.text-sm').text().trim();
                
                // עדכן את פרטי השירות בפאנל השמאלי
                $('#service-details-name').text(serviceName);
                $('#service-details-duration span').text(serviceDuration);
            }
        }
    };
    
    // עדכן את פרטי השירות כאשר בוחרים שירות
    const originalSelectService = window.selectService;
    if (typeof originalSelectService === 'function') {
        window.selectService = function(serviceId, serviceName) {
            // קרא לפונקציה המקורית אם היא קיימת
            originalSelectService(serviceId, serviceName);
            
            // עדכן את פרטי השירות
            setTimeout(updateServiceDetails, 100);
        };
    }
    
    // טיפול במיקום אלמנטים ב-responsive
    const handleResponsiveLayout = function() {
        // התאמת גובה במסכים קטנים
        if (window.innerWidth < 768) {
            $('.booking-info').appendTo('#step1');
            $('.booking-info').hide();
        } else {
            $('.booking-info').appendTo('.booking-columns').show();
        }
    };
    
    // קרא לפונקציה פעם אחת ואז בכל שינוי גודל מסך
    handleResponsiveLayout();
    $(window).resize(handleResponsiveLayout);
});

/**
 * בחירת שירות והצגתו בממשק
 */
function selectService(serviceId, serviceName) {
    selectedServiceId = serviceId;
    selectedServiceName = serviceName;
    
    // הצגת השירות שנבחר בממשק
    $('#selectedServiceName').text(serviceName);
    $('#selectedServiceId').val(serviceId);
    $('#selectedServiceContainer').removeClass('hidden');
    $('#nextToStep2').removeClass('hidden');
    
    // גלילה לטופס ההזמנה
    $('html, body').animate({
        scrollTop: $('#booking').offset().top - 50
    }, 1000);
}

/**
 * אתחול לוח השנה
 */
function initializeCalendar() {
    // יצירת מיכל ללוח השנה בסגנון Calendly
    const calendarContainer = document.getElementById('calendarContainer');
    calendarContainer.innerHTML = '';
    calendarContainer.classList.add('calendly-style');
    
    // יצירת אזור כותרת החודש וניווט
    const calendarHeader = document.createElement('div');
    calendarHeader.className = 'calendar-header';
    
    // הגדרת החודש הנוכחי ושנה
    const currentDate = new Date();
    let currentMonth = currentDate.getMonth();
    let currentYear = currentDate.getFullYear();
    
    // יצירת כותרת החודש
    const monthTitle = document.createElement('div');
    monthTitle.className = 'month-title';
    updateMonthTitle();
    
    // יצירת כפתורי ניווט בין חודשים
    const monthNavigation = document.createElement('div');
    monthNavigation.className = 'month-navigation';
    
    const prevButton = document.createElement('button');
    prevButton.innerHTML = '<i class="fas fa-chevron-right"></i>';
    prevButton.addEventListener('click', previousMonth);
    
    const nextButton = document.createElement('button');
    nextButton.innerHTML = '<i class="fas fa-chevron-left"></i>';
    nextButton.addEventListener('click', nextMonth);
    
    const todayButton = document.createElement('button');
    todayButton.className = 'today-button';
    todayButton.innerText = 'היום';
    todayButton.addEventListener('click', goToToday);
    
    monthNavigation.appendChild(prevButton);
    monthNavigation.appendChild(todayButton);
    monthNavigation.appendChild(nextButton);
    
    calendarHeader.appendChild(monthTitle);
    calendarHeader.appendChild(monthNavigation);
    calendarContainer.appendChild(calendarHeader);
    
    // יצירת טבלת לוח השנה
    const calendarGrid = document.createElement('table');
    calendarGrid.className = 'calendar-grid';
    
    // יצירת כותרות ימי השבוע
    const daysOfWeek = ['יום א\'', 'יום ב\'', 'יום ג\'', 'יום ד\'', 'יום ה\'', 'יום ו\'', 'שבת'];
    const thead = document.createElement('thead');
    const headerRow = document.createElement('tr');
    
    daysOfWeek.forEach(day => {
        const th = document.createElement('th');
        th.textContent = day;
        headerRow.appendChild(th);
    });
    
    thead.appendChild(headerRow);
    calendarGrid.appendChild(thead);
    
    // יצירת גוף הטבלה
    const tbody = document.createElement('tbody');
    calendarGrid.appendChild(tbody);
    
    calendarContainer.appendChild(calendarGrid);
    
    // יצירת אזור הצגת סלוטים זמינים
    const timeSlotsContainer = document.createElement('div');
    timeSlotsContainer.className = 'time-slots-container';
    timeSlotsContainer.style.display = 'none';
    
    const timeSlotsTitle = document.createElement('div');
    timeSlotsTitle.className = 'time-slots-title';
    timeSlotsContainer.appendChild(timeSlotsTitle);
    
    const timeSlotsGrid = document.createElement('div');
    timeSlotsGrid.className = 'time-slots-grid';
    timeSlotsGrid.id = 'timeSlotsGrid';
    timeSlotsContainer.appendChild(timeSlotsGrid);
    
    calendarContainer.appendChild(timeSlotsContainer);
    
    // עדכון החודש הנוכחי בלוח
    updateCalendar();
    
    // פונקציות עזר
    
    // עדכון כותרת החודש
    function updateMonthTitle() {
        const months = ['ינואר', 'פברואר', 'מרץ', 'אפריל', 'מאי', 'יוני', 'יולי', 'אוגוסט', 'ספטמבר', 'אוקטובר', 'נובמבר', 'דצמבר'];
        monthTitle.textContent = months[currentMonth] + ' ' + currentYear;
    }
    
    // מעבר לחודש הקודם
    function previousMonth() {
        currentMonth--;
        if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
        }
        updateMonthTitle();
        updateCalendar();
    }
    
    // מעבר לחודש הבא
    function nextMonth() {
        currentMonth++;
        if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
        }
        updateMonthTitle();
        updateCalendar();
    }
    
    // מעבר לחודש הנוכחי
    function goToToday() {
        const today = new Date();
        currentMonth = today.getMonth();
        currentYear = today.getFullYear();
        updateMonthTitle();
        updateCalendar();
    }
    
    // עדכון תצוגת הלוח
    function updateCalendar() {
        // ניקוי הגוף הקיים
        tbody.innerHTML = '';
        
        // קבלת מספר הימים בחודש והיום הראשון בשבוע
        const firstDay = new Date(currentYear, currentMonth, 1).getDay();
        const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
        
        // היום הנוכחי
        const today = new Date();
        const currentDay = today.getDate();
        const currentMonthYear = today.getMonth() === currentMonth && today.getFullYear() === currentYear;
        
        // יצירת השורות והתאים
        let date = 1;
        for (let i = 0; i < 6; i++) {
            // יצירת שורה חדשה
            const row = document.createElement('tr');
            
            // יצירת תאים בשורה
            for (let j = 0; j < 7; j++) {
                const cell = document.createElement('td');
                
                if (i === 0 && j < firstDay) {
                    // תאים ריקים לפני תחילת החודש
                    row.appendChild(cell);
                } else if (date > daysInMonth) {
                    // יציאה אם הגענו לסוף החודש
                    break;
                } else {
                    // יצירת הקוביה ליום
                    const dayElement = document.createElement('div');
                    dayElement.className = 'calendar-day';
                    dayElement.textContent = date;
                    const specificDay = date; // שמירת הערך הנוכחי של date
                    // בדיקה האם זה היום הנוכחי
                    if (currentMonthYear && date === currentDay) {
                        dayElement.classList.add('day-today');
                    }
                    
                    // בדיקה האם היום בעבר (לא ניתן לבחירה)
                    const currentDate = new Date(currentYear, currentMonth, date);
                    const isInPast = currentDate < today && !(currentMonthYear && date === currentDay);
                    
                    if (isInPast) {
                        dayElement.classList.add('day-disabled');
                    } else {
                        // אירוע לחיצה על יום
                        dayElement.addEventListener('click', function() {
                            // מסירים בחירה קודמת
                            const selectedDays = document.querySelectorAll('.day-selected');
                            selectedDays.forEach(day => day.classList.remove('day-selected'));
                            
                            // מסמנים את היום הנוכחי
                            this.classList.add('day-selected');
                            
                            // מחרוזת תאריך בפורמט YYYY-MM-DD
                            const selectedMonth = (currentMonth + 1).toString().padStart(2, '0');
                            const selectedDay = specificDay.toString().padStart(2, '0'); // שימוש בערך השמור
                            const dateStr = `${currentYear}-${selectedMonth}-${selectedDay}`;
                            
                            // שמירת התאריך שנבחר
                            selectedDate = dateStr;
                            
                            // עדכון כותרת הסלוטים
                            const dayNames = ['ראשון', 'שני', 'שלישי', 'רביעי', 'חמישי', 'שישי', 'שבת'];
                            const selectedDayOfWeek = new Date(dateStr).getDay();
                            
                            timeSlotsTitle.textContent = `זמינות ליום ${dayNames[selectedDayOfWeek]}, ${selectedDay}/${selectedMonth}/${currentYear}`;
                            
                            // טעינת הסלוטים הזמינים
                            fetchAvailableTimeSlots(selectedServiceId, dateStr, selectedStaffId);
                            
                            // הצגת אזור הסלוטים
                            timeSlotsContainer.style.display = 'block';
                        });
                    }
                    
                    cell.appendChild(dayElement);
                    row.appendChild(cell);
                    date++;
                }
            }
            
            tbody.appendChild(row);
            
            // אם סיימנו את כל הימים בחודש
            if (date > daysInMonth) {
                break;
            }
        }
    }
}

/**
 * טיפול בבחירת תאריך בלוח השנה
 */
// בקובץ המקורי - בפונקציה handleDateSelection או בפונקציה שמטפלת בלחיצה על תאריך
function handleDateSelection(dateStr) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    const selectedDay = new Date(dateStr);
    selectedDay.setHours(0, 0, 0, 0);
    
    // בדיקה שלא נבחר תאריך בעבר
    if (selectedDay < today) {
        alert('לא ניתן לבחור תאריך בעבר');
        return;
    }
    
    // בדיקה שהיום פתוח (דוגמה כללית)
    const dayOfWeek = selectedDay.getDay();
    if (dayOfWeek === 6) { // שבת
        alert('העסק סגור ביום זה. אנא בחר יום אחר.');
        return;
    }
    
    selectedDate = dateStr;  // <-- כאן התאריך נקבע
    $('#selectedDate').text(formatDateHebrew(selectedDate));
    
    // ניקוי בחירה קודמת
    $('.fc-day').removeClass('selected-date');
    $('.fc-day[data-date="' + dateStr + '"]').addClass('selected-date');
    
    // טעינת סלוטים פנויים ליום ושירות שנבחרו
    fetchAvailableTimeSlots(selectedServiceId, dateStr, selectedStaffId);
}

/**
 * פורמט תאריך לעברית
 */
function formatDateHebrew(dateStr) {
    const date = new Date(dateStr);
    const day = date.getDate();
    const month = date.getMonth() + 1;
    const year = date.getFullYear();
    
    // קבלת שם היום
    const dayNames = ["ראשון", "שני", "שלישי", "רביעי", "חמישי", "שישי", "שבת"];
    const dayName = dayNames[date.getDay()];
    
    return `יום ${dayName}, ${day}/${month}/${year}`;
}

/**
 * קבלת סלוטים פנויים מה-API
 */
function fetchAvailableTimeSlots(serviceId, dateStr, staffId) {
    $('#timeSlots').removeClass('hidden');
    const timeSlotsGrid = $('#timeSlotsGrid');
    timeSlotsGrid.empty();
    
    // הצגת אנימציית טעינה
    timeSlotsGrid.html('<div class="col-span-full text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i> טוען זמנים פנויים...</div>');
    
    // מציאת tenant_id
    const tenantId = getTenantId();
    
    // קריאה ל-API לקבלת סלוטים פנויים
    $.get('../api/available_slots.php', {
        date: dateStr,
        service_id: serviceId,
        staff_id: staffId || 0,
        tenant_id: tenantId
    }, function(response) {
        timeSlotsGrid.empty();
        
        if (response.success && response.slots.length > 0) {
            // הצגת הסלוטים הפנויים
            availableTimeSlots = response.slots;
            
            response.slots.forEach(slot => {
                timeSlotsGrid.append(`
                    <div class="time-slot" onclick="selectTimeSlot('${slot}')">${slot}</div>
                `);
            });
            
            $('#noTimeSlotsMessage').addClass('hidden');
            // עדכון כפתור המשך להיות לא פעיל עד לבחירת שעה
            $('#nextToStep3').addClass('bg-gray-300 text-gray-500 cursor-not-allowed')
                             .removeClass('bg-primary hover:bg-primary-dark text-white');
        } else {
            // אין סלוטים פנויים
            $('#noTimeSlotsMessage').removeClass('hidden');
            if (response.message) {
                $('#noTimeSlotsMessage p').text(response.message);
            }
            
            // כפתור המשך לא פעיל
            $('#nextToStep3').addClass('bg-gray-300 text-gray-500 cursor-not-allowed')
                             .removeClass('bg-primary hover:bg-primary-dark text-white');
            
            // הצג את כפתור רשימת ההמתנה
            if ($('#joinWaitlistBtn').length === 0) {
                timeSlotsGrid.append(`
                    <div class="text-center col-span-full py-4">
                        <p class="text-gray-600 mb-4">אין זמנים פנויים בתאריך שנבחר.</p>
                        <button id="joinWaitlistBtn" class="bg-secondary hover:bg-secondary-dark text-white font-bold py-2 px-6 rounded-xl transition duration-300">
                            הירשם לרשימת המתנה
                        </button>
                    </div>
                `);
                
                // הוספת אירוע לחיצה על כפתור רשימת המתנה
                $('#joinWaitlistBtn').click(function() {
                    $('#waitlist_service_id').val(selectedServiceId);
                    $('#waitlist_staff_id').val(selectedStaffId);
                    
                    // אם יש תאריך נבחר, ממלאים אותו
                    if (selectedDate) {
                        $('#waitlist_preferred_date').val(selectedDate);
                    }
                    
                    // חלונית עולה
                    $('#waitlistModal').removeClass('hidden').addClass('show');
                });
            }
        }
    }).fail(function() {
        timeSlotsGrid.html('<div class="col-span-full text-center py-4 text-red-500"><i class="fas fa-exclamation-circle mr-2"></i> אירעה שגיאה בטעינת הזמנים הפנויים</div>');
        
        // כפתור המשך לא פעיל
        $('#nextToStep3').addClass('bg-gray-300 text-gray-500 cursor-not-allowed')
                         .removeClass('bg-primary hover:bg-primary-dark text-white');
    });
}

/**
 * בחירת שעה פנויה
 */
function selectTimeSlot(timeSlot) {
    selectedTimeSlot = timeSlot;
    
    // עדכון ממשק
    $('.time-slot').removeClass('selected');
    $(`.time-slot:contains("${timeSlot}")`).addClass('selected');
    
    // הפעלת כפתור המשך
    $('#nextToStep3').removeClass('bg-gray-300 text-gray-500 cursor-not-allowed')
                     .addClass('bg-primary hover:bg-primary-dark text-white');
}

/**
 * הגדרת מעבר בין שלבים
 */
/**
 * הגדרת מעבר בין שלבים - גרסה מתוקנת 
 * להחליף את הפונקציה הקיימת במלואה
 */
function setupStepNavigation() {
    // שלב 1 לשלב 2
    $('#nextToStep2').click(function() {
        if (!selectedServiceId) {
            alert('אנא בחר שירות תחילה');
            return;
        }
        
        // אתחול הלוח - הפונקציה החדשה מבצעת אתחול ולא דורשת קריאה ל-render
        initializeCalendar();
        
        // מעבר לשלב 2
        $('.booking-step').removeClass('active');
        $('#step2').addClass('active');
        
        // עדכון סמני התקדמות
        updateProgressIndicators(2);
        
        // הגדרת אירוע בחירת איש צוות
        $('.staff-card').click(function() {
            $('.staff-card').removeClass('border-primary selected').addClass('border-gray-300');
            $(this).removeClass('border-gray-300').addClass('border-primary selected');
            selectedStaffId = $(this).data('staff-id');
            selectedStaffName = $(this).find('p').text();
            
            // עדכון התאריכים והזמנים בהתאם לאיש הצוות שנבחר
            if (selectedDate) {
                fetchAvailableTimeSlots(selectedServiceId, selectedDate, selectedStaffId);
            }
        });
        
        // בחירת איש צוות ראשון כברירת מחדל
        if ($('.staff-card').length > 0) {
            $('.staff-card:first').addClass('border-primary selected').removeClass('border-gray-300');
            selectedStaffId = $('.staff-card:first').data('staff-id');
            selectedStaffName = $('.staff-card:first').find('p').text();
        }
    });
    
    // חזרה לשלב 1
    $('#backToStep1').click(function() {
        $('.booking-step').removeClass('active');
        $('#step1').addClass('active');
        updateProgressIndicators(1);
    });
    
    // שלב 2 לשלב 3
    $('#nextToStep3').click(function() {
        if (!selectedTimeSlot) {
            alert('אנא בחר מועד תחילה');
            return;
        }
        
        $('.booking-step').removeClass('active');
        $('#step3').addClass('active');
        updateProgressIndicators(3);
    });
    
    // חזרה לשלב 2
    $('#backToStep2').click(function() {
        $('.booking-step').removeClass('active');
        $('#step2').addClass('active');
        updateProgressIndicators(2);
    });
    
    // שלב 3 לשלב 4 (סיכום)
    $('#nextToStep4').click(function() {
        // ולידציה של הטופס
        const firstName = $('#first_name').val();
        const lastName = $('#last_name').val();
        const phone = $('#phone').val();
        
        if (!firstName || !lastName || !phone) {
            alert('אנא מלא את כל שדות החובה');
            return;
        }
        
        // בדיקת תקינות מספר טלפון
        if (!isValidPhoneNumber(phone)) {
            alert('אנא הזן מספר טלפון תקין');
            return;
        }
        
        // בדיקת תקינות אימייל אם הוזן
        const email = $('#email').val();
        if (email && !isValidEmail(email)) {
            alert('אנא הזן כתובת אימייל תקינה');
            return;
        }
        
        // בדיקת שאלות חובה
        let missingRequired = false;
        $('.intake-answer[required]').each(function() {
            if (!$(this).val()) {
                missingRequired = true;
                return false; // break the loop
            }
        });
        
        if (missingRequired) {
            alert('אנא ענה על כל שאלות החובה');
            return;
        }
        
        // עדכון סיכום
        $('#summary_service').text(selectedServiceName);
        $('#summary_date').text(formatDateHebrew(selectedDate));
        $('#summary_time').text(selectedTimeSlot);
        $('#summary_staff').text(selectedStaffName);
        $('#summary_name').text(firstName + ' ' + lastName);
        $('#summary_phone').text(phone);
        
        if (email) {
            $('#summary_email').text(email);
            $('#summary_email_container').removeClass('hidden');
        } else {
            $('#summary_email_container').addClass('hidden');
        }
        
        const notes = $('#notes').val();
        if (notes) {
            $('#summary_notes').text(notes);
            $('#summary_notes_container').removeClass('hidden');
        } else {
            $('#summary_notes_container').addClass('hidden');
        }
        
        $('.booking-step').removeClass('active');
        $('#step4').addClass('active');
        updateProgressIndicators(4);
    });
    
    // חזרה לשלב 3
    $('#backToStep3').click(function() {
        $('.booking-step').removeClass('active');
        $('#step3').addClass('active');
        updateProgressIndicators(3);
    });
    
    // אישור הזמנה (שליחת הטופס)
    $('#confirmBooking').click(function() {
        // הצגת אנימציית טעינה
        const button = $(this);
        button.html('<div class="loading-spinner mr-2"></div> מעבד...');
        button.prop('disabled', true);
        
        // איסוף תשובות לשאלות מקדימות
        const intakeAnswers = {};
        $('.intake-answer').each(function() {
            const questionId = $(this).data('question-id');
            if (questionId) {
                if ($(this).attr('type') === 'checkbox') {
                    if ($(this).is(':checked')) {
                        if (!intakeAnswers[questionId]) {
                            intakeAnswers[questionId] = [];
                        }
                        intakeAnswers[questionId].push($(this).val());
                    }
                } else if ($(this).attr('type') === 'radio') {
                    if ($(this).is(':checked')) {
                        intakeAnswers[questionId] = $(this).val();
                    }
                } else {
                    intakeAnswers[questionId] = $(this).val();
                }
            }
        });
        
        // וידוא שיש לנו staff_id - חשוב!
        if (!selectedStaffId || selectedStaffId === 0 || isNaN(selectedStaffId)) {
            // אם לא הוגדר staff_id, השתמש בברירת מחדל
            const defaultStaffId = $('meta[name="default-staff-id"]').attr('content');
            if (defaultStaffId) {
                selectedStaffId = parseInt(defaultStaffId);
            } else {
                selectedStaffId = 1; // ברירת מחדל
            }
        }
        
        // בניית אובייקט הנתונים לשליחה
        const bookingData = {
            tenant_id: getTenantId(),
            service_id: selectedServiceId,
            staff_id: selectedStaffId, 
            appointment_date: selectedDate,
            appointment_time: selectedTimeSlot,
            first_name: $('#first_name').val(),
            last_name: $('#last_name').val(),
            phone: $('#phone').val(),
            email: $('#email').val(),
            notes: $('#notes').val(),
            intake_answers: intakeAnswers
        };
    
        
        // שליחה ל-API
        $.ajax({
            url: '../api/booking.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(bookingData),
            success: function(response) {
                if (response.success) {
                    // עדכון פרטי התור במסך ההצלחה
                    $('#booking_reference').text(response.reference || '');
                    $('#booking_service').text(selectedServiceName);
                    $('#booking_date').text(formatDateHebrew(selectedDate));
                    $('#booking_time').text(selectedTimeSlot);
                    $('#booking_staff').text(selectedStaffName);
                    
                    // יצירת קישור להוספה ליומן
                    const calendarEvent = generateCalendarLink(
                        selectedServiceName, 
                        response.start_datetime || `${selectedDate}T${selectedTimeSlot}:00`,
                        response.end_datetime || `${selectedDate}T${selectedTimeSlot}:00`,
                        document.title.split('-')[0].trim(),
                        $('meta[name="business-address"]').attr('content') || ''
                    );
                    $('#addToCalendarBtn').attr('href', calendarEvent);
                    
                    // הצגת מסך הצלחה
                    $('.booking-step').removeClass('active');
                    $('#step5').addClass('active');
                    
                    // הסתרת סמן התקדמות
                    $('.step-indicator').parent().addClass('hidden');
                    $('#progress-bar').parent().addClass('hidden');
                } else {
                    // טיפול בשגיאה
                    alert('אירעה שגיאה: ' + (response.message || 'אנא נסה שנית'));
                    button.html('אישור הזמנה');
                    button.prop('disabled', false);
                }
            },
            error: function() {
                alert('אירעה שגיאה בעת שליחת הבקשה');
                button.html('אישור הזמנה');
                button.prop('disabled', false);
            }
        });
    });
}
/**
 * מגדיר אירועים לטופס רשימת המתנה
 */
function setupWaitlistHandlers() {
    // פתיחת חלונית רשימת המתנה
    $(document).on('click', '#joinWaitlistBtn', function() {
        $('#waitlist_service_id').val(selectedServiceId);
        $('#waitlist_staff_id').val(selectedStaffId);
        
        // אם יש תאריך נבחר, ממלאים אותו
        if (selectedDate) {
            $('#waitlist_preferred_date').val(selectedDate);
        }
        
        // העתקת פרטי הלקוח מהטופס הראשי אם כבר מולאו
        if ($('#first_name').val()) {
            $('#waitlist_first_name').val($('#first_name').val());
        }
        if ($('#last_name').val()) {
            $('#waitlist_last_name').val($('#last_name').val());
        }
        if ($('#phone').val()) {
            $('#waitlist_phone').val($('#phone').val());
        }
        if ($('#email').val()) {
            $('#waitlist_email').val($('#email').val());
        }
        
        // חלונית עולה
        $('#waitlistModal').removeClass('hidden').addClass('show');
        $('body').css('overflow', 'hidden'); // מונע גלילה
    });
    
    // סגירת חלונית רשימת המתנה
    $('#closeWaitlistModal, #closeWaitlistBtn').click(function() {
        $('#waitlistModal').removeClass('show');
        setTimeout(function() {
            $('#waitlistModal').addClass('hidden');
            $('body').css('overflow', 'auto'); // מחזיר גלילה
        }, 300);
    });
    
    // סגירת המודל בלחיצה על הרקע
    $('#waitlistModal').click(function(e) {
        if (e.target === this) {
            $(this).removeClass('show');
            setTimeout(function() {
                $('#waitlistModal').addClass('hidden');
                $('body').css('overflow', 'auto');
            }, 300);
        }
    });
    
    // שליחת טופס רשימת המתנה
    $('#waitlistForm').submit(function(e) {
        e.preventDefault();
        
        // אנימציית טעינה בכפתור
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.text();
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> מעבד...');
        
        // המרת הטופס לאובייקט JSON
        const formData = {};
        $(this).serializeArray().forEach(item => {
            formData[item.name] = item.value;
        });
        
        // שליחת הנתונים
        $.ajax({
            url: '../api/add_to_waitlist.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(formData),
            success: function(response) {
                if (response.success) {
                    // הצגת הודעת הצלחה
                    $('#waitlistModal').removeClass('show').addClass('hidden');
                    $('body').css('overflow', 'auto');
                    
                    // יצירת הודעת הצלחה
                    const successMessage = `
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 mt-4 fade-in">
                            <div class="flex">
                                <div class="py-1"><svg class="fill-current h-6 w-6 text-green-500 mr-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M2.93 17.07A10 10 0 1 1 17.07 2.93 10 10 0 0 1 2.93 17.07zm12.73-1.41A8 8 0 1 0 4.34 4.34a8 8 0 0 0 11.32 11.32zM9 11V9h2v6H9v-4zm0-6h2v2H9V5z"/></svg></div>
                                <div>
                                    <p class="font-bold">נרשמת בהצלחה לרשימת ההמתנה!</p>
                                    <p class="text-sm">נעדכן אותך כשיתפנה תור.</p>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // הוספת ההודעה לדף
                    $('#noTimeSlotsMessage').after(successMessage);
                    
                    // איפוס הטופס
                    $('#waitlistForm')[0].reset();
                    
                    // הסתרת ההודעה אחרי 5 שניות
                    setTimeout(function() {
                        $('.fade-in').fadeOut('slow', function() {
                            $(this).remove();
                        });
                    }, 5000);
                } else {
                    // הצגת שגיאה
                    alert('אירעה שגיאה: ' + (response.message || 'אנא נסה שנית'));
                    submitBtn.prop('disabled', false).text(originalText);
                }
            },
            error: function() {
                alert('אירעה שגיאה בעת שליחת הבקשה');
                submitBtn.prop('disabled', false).text(originalText);
            }
        });
    });
}

/**
 * בדיקת תקינות מספר טלפון
 */
function isValidPhoneNumber(phone) {
    // ניקוי מקפים ורווחים
    const cleanPhone = phone.replace(/[\s-]/g, '');
    // בדיקת תבנית מספר טלפון ישראלי
    return /^(0[23489]\d{7}|05\d{8})$/.test(cleanPhone);
}

/**
 * בדיקת תקינות אימייל
 */
function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

/**
 * עדכון סמני התקדמות
 */
function updateProgressIndicators(step) {
    // עדכון סרגל התקדמות
    $('#progress-bar').css('width', (step * 25) + '%');
    
    // עדכון סמן שלב
    $('.step-indicator').removeClass('bg-primary text-white').addClass('bg-gray-300 text-gray-600');
    
    for (let i = 1; i <= step; i++) {
        $(`.step-indicator[data-step="${i}"]`).removeClass('bg-gray-300 text-gray-600').addClass('bg-primary text-white');
    }
}

/**
 * יצירת קישור להוספה ליומן גוגל
 */
function generateCalendarLink(title, start, end, location, address) {
    // המרת תאריכים לפורמט RFC5545
    const formatDateTime = (dateTimeStr) => {
        const dt = new Date(dateTimeStr);
        return dt.toISOString().replace(/-|:|\.\d+/g, '');
    };
    
    const startFormatted = formatDateTime(start);
    const endFormatted = formatDateTime(end);
    
    // יצירת התיאור
    const description = "תור שנקבע ב" + location;
    
    // בניית הקישור
    const googleCalendarUrl = 'https://www.google.com/calendar/render?action=TEMPLATE' +
        '&text=' + encodeURIComponent(title) +
        '&dates=' + startFormatted + '/' + endFormatted +
        '&details=' + encodeURIComponent(description) +
        '&location=' + encodeURIComponent(address) +
        '&sprop=&sprop=name:';
        
    return googleCalendarUrl;
}

/**
 * קבלת ה-tenant_id מה-URL
 */
function getTenantId() {
    // מציאת tenant_id מתוך מטא-תג או שדה נסתר
    return $('meta[name="tenant-id"]').attr('content') || $('input[name="tenant_id"]').val() || 1;
}

/**
 * תמיכה באימות צד לקוח ב-waitlistForm
 */
$(document).ready(function() {
    $('#waitlistForm').on('submit', function(e) {
        const firstName = $('#waitlist_first_name').val();
        const lastName = $('#waitlist_last_name').val();
        const phone = $('#waitlist_phone').val();
        
        // בדיקה שהשדות החשובים מלאים
        if (!firstName || !lastName || !phone) {
            e.preventDefault();
            alert('אנא מלא את כל שדות החובה');
            return false;
        }
        
        // בדיקת תקינות טלפון בסיסית
        const phonePattern = /^0[2-9]\d{7,8}$/;
        if (!phonePattern.test(phone.replace(/[- ]/g, ''))) {
            e.preventDefault();
            alert('מספר הטלפון אינו תקין');
            return false;
        }
        
        // בדיקת תקינות אימייל אופציונלי
        const email = $('#waitlist_email').val();
        if (email) {
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(email)) {
                e.preventDefault();
                alert('כתובת האימייל אינה תקינה');
                return false;
            }
        }
        
        return true;
    });
});

// הוספת CSS עבור אנימציית הופעה
$("<style>")
    .prop("type", "text/css")
    .html(`
        .fade-in {
            animation: fadeIn 0.5s;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        #waitlistModal {
            transition: opacity 0.3s ease;
        }
        
        #waitlistModal.show {
            opacity: 1;
        }
        
        #waitlistModal:not(.show) {
            opacity: 0;
        }
    `)
    .appendTo("head");
