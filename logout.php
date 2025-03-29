<?php
// התחלת סשן
session_start();

// מחיקת עוגיית זכור אותי
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// מחיקת משתני הסשן
session_unset();

// הריסת הסשן
session_destroy();

// הפניה לדף ההתחברות
header("Location: login.php");
exit;
?>