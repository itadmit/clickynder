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
    const calendarEl = document.getElementById('calendarContainer');
    
    calendar = new FullCalendar.Calendar(calendarEl, {
        locale: 'he',
        initialView: 'dayGridMonth',
        height: 'auto',
        selectable: true,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: ''
        },
        buttonText: {
            today: 'היום'
        },
        direction: 'rtl',
        firstDay: 0, // יום ראשון כיום ראשון בשבוע
        select: function(info) {
            handleDateSelection(info.startStr);
        },
        dateClick: function(info) {
            handleDateSelection(info.dateStr);
        },
        eventSources: [
            // טעינת ימי סגירה וחריגים מה-API
            {
                url: '../api/get_business_exceptions.php',
                method: 'GET',
                extraParams: {
                    tenant_id: getTenantId()
                },
                failure: function() {
                    console.error('שגיאה בטעינת ימי סגירה');
                }
            }
        ],
        
        // הגבלת בחירת תאריכים - רק מהיום והלאה
        validRange: {
            start: new Date()
        },
        
        // שינוי תצוגה לימים סגורים
        dayCellClassNames: function(arg) {
            const dayOfWeek = arg.date.getDay();
            
            // סגירת ימי שבת - דוגמה כללית
            if (dayOfWeek === 6) {
                return ['fc-day-disabled'];
            }
            
            return [];
        }
    });
}

/**
 * טיפול בבחירת תאריך בלוח השנה
 */
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
    
    selectedDate = dateStr;
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
    const container = $('#timeSlotsContainer');
    container.empty();
    
    // הצגת אנימציית טעינה
    container.html('<div class="col-span-full text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i> טוען זמנים פנויים...</div>');
    
    // מציאת tenant_id
    const tenantId = getTenantId();
    
    // קריאה ל-API לקבלת סלוטים פנויים
    $.get('../api/available_slots.php', {
        date: dateStr,
        service_id: serviceId,
        staff_id: staffId || 0,
        tenant_id: tenantId
    }, function(response) {
        container.empty();
        
        if (response.success && response.slots.length > 0) {
            // הצגת הסלוטים הפנויים
            availableTimeSlots = response.slots;
            
            response.slots.forEach(slot => {
                container.append(`
                    <div class="time-slot bg-white border-2 border-gray-300 rounded-lg py-2 text-center cursor-pointer hover:border-primary"
                         onclick="selectTimeSlot('${slot}')">${slot}</div>
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
        }
    }).fail(function() {
        container.html('<div class="col-span-full text-center py-4 text-red-500"><i class="fas fa-exclamation-circle mr-2"></i> אירעה שגיאה בטעינת הזמנים הפנויים</div>');
        
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
    $('.time-slot').removeClass('selected bg-primary text-white');
    $(`.time-slot:contains("${timeSlot}")`).addClass('selected bg-primary text-white');
    
    // הפעלת כפתור המשך
    $('#nextToStep3').removeClass('bg-gray-300 text-gray-500 cursor-not-allowed')
                     .addClass('bg-primary hover:bg-primary-dark text-white');
}

/**
 * הגדרת מעבר בין שלבים
 */
function setupStepNavigation() {
    // שלב 1 לשלב 2
    $('#nextToStep2').click(function() {
        if (!selectedServiceId) {
            alert('אנא בחר שירות תחילה');
            return;
        }
        
        // אתחול הלוח אם לא קיים
        if (!calendar) {
            initializeCalendar();
        }
        
        // הצגת הלוח
        calendar.render();
        
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
            // ... הקוד הקיים ...
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
 * הגדרת אירועים לטופס רשימת המתנה
 */
function setupWaitlistHandlers() {
    // פתיחת חלונית רשימת המתנה
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
    
    // סגירת חלונית רשימת המתנה
    $('#closeWaitlistModal').click(function() {
        $('#waitlistModal').removeClass('show');
        setTimeout(function() {
            $('#waitlistModal').addClass('hidden');
        }, 300);
    });
    
    // שליחת טופס רשימת המתנה
    $('#waitlistForm').submit(function(e) {
        e.preventDefault();
        
        // אנימציית טעינה בכפתור
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.text();
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> מעבד...');
        
        // שליחת הנתונים
        $.ajax({
            url: '../api/add_to_waitlist.php',
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.success) {
                    // הצגת הודעת הצלחה
                    alert('נרשמת בהצלחה לרשימת ההמתנה! נעדכן אותך כשיתפנה תור.');
                    
                    // סגירת החלונית
                    $('#waitlistModal').removeClass('show');
                    setTimeout(function() {
                        $('#waitlistModal').addClass('hidden');
                        // איפוס הטופס
                        $('#waitlistForm')[0].reset();
                    }, 300);
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
    // מציאת tenant_id מתוך מטא-תג או שדה נסתר (יש לשכתב לפי המבנה הספציפי שלך)
    return $('meta[name="tenant-id"]').attr('content') || $('input[name="tenant_id"]').val() || 1;
}