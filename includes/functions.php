<?php
// פונקציות משותפות למערכת

// בדיקה האם המשתמש מחובר
function isLoggedIn() {
    if (isset($_SESSION['tenant_id']) && !empty($_SESSION['tenant_id'])) {
        return true;
    }
    return false;
}

// הפניה לדף אחר
function redirect($url) {
    header("Location: " . $url);
    exit;
}

// בדיקת תקינות אימייל
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// הצפנת סיסמה
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// אימות סיסמה
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// טיפול בטקסט - למניעת XSS
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// יצירת סלאג ייחודי
function createSlug($string) {
    // מחליף רווחים בקווים מפרידים
    $slug = preg_replace('/[^A-Za-z0-9-]+/', '-', $string);
    // מסיר קווים מפרידים בתחילת ובסוף המחרוזת
    $slug = trim($slug, '-');
    // הופך לאותיות קטנות
    $slug = strtolower($slug);
    return $slug;
}

// פונקציה ליצירת מזהה ייחודי
function generateUniqueId() {
    return uniqid(mt_rand(), true);
}

// הצגת הודעת הצלחה
function showSuccess($message) {
    return '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">' . $message . '</span>
            </div>';
}

// הצגת הודעת שגיאה
function showError($message) {
    return '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">' . $message . '</span>
            </div>';
}

// פונקציה להצגת התאריך בפורמט עברי
function hebrewDate($date) {
    $timestamp = strtotime($date);
    $day = date('j', $timestamp);
    $month = date('n', $timestamp);
    $year = date('Y', $timestamp);
    
    $hebMonth = '';
    switch($month) {
        case 1: $hebMonth = 'ינואר'; break;
        case 2: $hebMonth = 'פברואר'; break;
        case 3: $hebMonth = 'מרץ'; break;
        case 4: $hebMonth = 'אפריל'; break;
        case 5: $hebMonth = 'מאי'; break;
        case 6: $hebMonth = 'יוני'; break;
        case 7: $hebMonth = 'יולי'; break;
        case 8: $hebMonth = 'אוגוסט'; break;
        case 9: $hebMonth = 'ספטמבר'; break;
        case 10: $hebMonth = 'אוקטובר'; break;
        case 11: $hebMonth = 'נובמבר'; break;
        case 12: $hebMonth = 'דצמבר'; break;
    }
    
    return $day . ' ב' . $hebMonth . ' ' . $year;
}

// פונקציה להצגת היום בשבוע בעברית
function hebrewDayOfWeek($date) {
    $timestamp = strtotime($date);
    $dayOfWeek = date('w', $timestamp);
    
    $hebDay = '';
    switch($dayOfWeek) {
        case 0: $hebDay = 'יום ראשון'; break;
        case 1: $hebDay = 'יום שני'; break;
        case 2: $hebDay = 'יום שלישי'; break;
        case 3: $hebDay = 'יום רביעי'; break;
        case 4: $hebDay = 'יום חמישי'; break;
        case 5: $hebDay = 'יום שישי'; break;
        case 6: $hebDay = 'יום שבת'; break;
    }
    
    return $hebDay;
}