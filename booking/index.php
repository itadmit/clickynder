<?php
// קובץ זה משמש להפניית לקוחות לדף העסק שלהם לפי ה-slug

// טעינת קבצי התצורה
require_once "../config/database.php";
require_once "../includes/functions.php";

// קבלת הסלאג מה-URL
$slug = isset($_GET['slug']) ? sanitizeInput($_GET['slug']) : '';

if (empty($slug)) {
    // אם אין סלאג, הפניה לדף הראשי
    header("Location: ../index.php");
    exit;
}

// הפניה לדף המתאים
header("Location: ../booking.php?slug=$slug");
exit;