<?php
// מחזיר את חריגי שעות הפעילות (חגים, חופשות וימי סגירה מיוחדים)

// Set headers for JSON response
header('Content-Type: application/json');

// בדיקת פרמטרים נדרשים
if (!isset($_GET['tenant_id']) || empty($_GET['tenant_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'מזהה עסק חסר'
    ]);
    exit;
}

// כולל קבצי תצורה
require_once "../config/database.php";
require_once "../includes/functions.php";

// קבלת פרמטרים
$tenant_id = intval($_GET['tenant_id']);
$start = isset($_GET['start']) ? sanitizeInput($_GET['start']) : date('Y-m-d', strtotime('-1 month'));
$end = isset($_GET['end']) ? sanitizeInput($_GET['end']) : date('Y-m-d', strtotime('+3 months')); 

try {
    // חיבור למסד הנתונים
    $database = new Database();
    $db = $database->getConnection();
    
    // שליפת חריגי שעות פעילות
    $query = "SELECT * FROM business_hour_exceptions 
              WHERE tenant_id = :tenant_id 
              AND exception_date BETWEEN :start AND :end";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":tenant_id", $tenant_id);
    $stmt->bindParam(":start", $start);
    $stmt->bindParam(":end", $end);
    $stmt->execute();
    
    $exceptions = [];
    
    // עיבוד התוצאות לפורמט של FullCalendar
    while ($row = $stmt->fetch()) {
        // המרה לאובייקט אירוע לקלנדר
        $event = [
            'id' => 'exception_' . $row['id'],
            'title' => !empty($row['description']) ? $row['description'] : 'סגור',
            'start' => $row['exception_date'],
            'allDay' => true,
            'display' => 'background',
            'backgroundColor' => '#ff000033', // אדום שקוף
            'classNames' => ['business-exception', 'business-closed'],
            'extendedProps' => [
                'is_open' => $row['is_open'] ? true : false
            ]
        ];
        
        // אם העסק פתוח, אבל בשעות שונות - נוסיף את השעות לכותרת
        if ($row['is_open'] && !empty($row['open_time']) && !empty($row['close_time'])) {
            $start_time = date('H:i', strtotime($row['open_time']));
            $end_time = date('H:i', strtotime($row['close_time']));
            $event['title'] = (!empty($row['description']) ? $row['description'] . ': ' : '') . $start_time . '-' . $end_time;
            $event['backgroundColor'] = '#0000ff33'; // כחול שקוף
            $event['classNames'] = ['business-exception', 'business-special-hours'];
        }
        
        $exceptions[] = $event;
    }
    
    echo json_encode($exceptions);
    
} catch (PDOException $e) {
    // בשגיאה, נחזיר מערך ריק
    echo json_encode([]);
}
?>