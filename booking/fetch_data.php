<?php
/**
 * Booking System - Data Fetching File
 * 
 * מאוכסן ב: booking/fetch_data.php
 * שליפת כל המידע הדרוש להצגת דף הזמנת התורים
 */

// וידוא שהקובץ לא נטען ישירות
if (!defined('ACCESS_ALLOWED') && basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header("Location: ../index.php");
    exit;
}

// חיבור למסד הנתונים
$database = new Database();
$db = $database->getConnection();

try {
    // שליפת פרטי העסק לפי הסלאג
    $query = "SELECT * FROM tenants WHERE slug = :slug";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":slug", $slug);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        // אם העסק לא נמצא, הפניה לדף שגיאה או דף ראשי
        header("Location: ../index.php?error=business_not_found");
        exit;
    }
    
    $tenant = $stmt->fetch();
    $tenant_id = $tenant['tenant_id'];
    
    // שליפת קטגוריות שירות
    $query = "SELECT * FROM service_categories 
             WHERE tenant_id = :tenant_id 
             ORDER BY display_order ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    $categories = $stmt->fetchAll();
    
    // שליפת שירותים
    $query = "SELECT s.*, c.name as category_name 
             FROM services s 
             LEFT JOIN service_categories c ON s.category_id = c.category_id 
             WHERE s.tenant_id = :tenant_id AND s.is_active = 1 
             ORDER BY s.category_id ASC, s.name ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    $services = $stmt->fetchAll();
    
    // שליפת אנשי צוות
    $query = "SELECT * FROM staff 
             WHERE tenant_id = :tenant_id AND is_active = 1 
             ORDER BY name ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    $staff = $stmt->fetchAll();
    
    // שליפת שעות פעילות
    $query = "SELECT * FROM business_hours 
             WHERE tenant_id = :tenant_id 
             ORDER BY day_of_week ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    $business_hours = $stmt->fetchAll();
    
    // ארגון שעות פעילות לפי ימים
    $hours_by_day = [];
    foreach ($business_hours as $hours) {
        $hours_by_day[$hours['day_of_week']] = $hours;
    }
    
    // שליפת הפסקות
    $query = "SELECT * FROM breaks 
             WHERE tenant_id = :tenant_id 
             ORDER BY day_of_week ASC, start_time ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    $breaks = $stmt->fetchAll();
    
    // ארגון הפסקות לפי ימים
    $breaks_by_day = [];
    foreach ($breaks as $break) {
        if (!isset($breaks_by_day[$break['day_of_week']])) {
            $breaks_by_day[$break['day_of_week']] = [];
        }
        $breaks_by_day[$break['day_of_week']][] = $break;
    }
    
    // ארגון שירותים לפי קטגוריות
    $services_by_category = [];
    foreach ($services as $service) {
        $category_id = $service['category_id'];
        if (!isset($services_by_category[$category_id])) {
            $services_by_category[$category_id] = [];
        }
        $services_by_category[$category_id][] = $service;
    }
    
    // שליפת שאלות מקדימות כלליות
    $query = "SELECT * FROM intake_questions 
             WHERE tenant_id = :tenant_id 
             AND (service_id IS NULL OR service_id = 0)
             ORDER BY display_order ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->execute();
    $general_questions = $stmt->fetchAll();

    // קבלת היום הנוכחי (0 = ראשון, 6 = שבת)
    $current_day = date('w');
    
    // פונקציה להצגת שעות בפורמט קריא
    function formatTime($time) {
        return date("H:i", strtotime($time));
    }
    
    // פונקציה להצגת שם יום בעברית
    function getDayName($day) {
        $days = ["ראשון", "שני", "שלישי", "רביעי", "חמישי", "שישי", "שבת"];
        return $days[$day];
    }
    
} catch (PDOException $e) {
    // טיפול בשגיאות מסד נתונים
    echo "שגיאה: " . $e->getMessage();
    exit;
}