<?php
/**
 * Booking System - Main Entry File
 * 
 * מאוכסן ב: booking/index.php
 * קובץ ראשי למערכת הזמנת תורים
 */

// טעינת קבצי התצורה
require_once dirname(__DIR__) . "/config/database.php";
require_once dirname(__DIR__) . "/includes/functions.php";

// הגדרת קבוע שמאפשר גישה לקבצי ה-includes
define('ACCESS_ALLOWED', true);

// קבלת הסלאג מה-URL
$slug = isset($_GET['slug']) ? sanitizeInput($_GET['slug']) : '';

if (empty($slug)) {
    // אם אין סלאג, הפנייה לדף שגיאה או דף ראשי
    header("Location: ../index.php");
    exit;
}

// שליפת כל הנתונים הדרושים להצגת הדף
require_once "fetch_data.php";

// טעינת התבניות לבניית העמוד
include_once "templates/header.php";  // כותרת HTML + חלק עליון
include_once "templates/sidebar.php"; // סיידבר - פרטי עסק, שעות, צוות
include_once "templates/services.php"; // חלק השירותים
include_once "templates/booking_form.php"; // טופס הזמנת תור
include_once "templates/footer.php"; // פוטר + סגירת HTML

// סגירת החיבור למסד הנתונים
$db = null;