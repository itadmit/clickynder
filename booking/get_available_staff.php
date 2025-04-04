<?php
// קבלת הפרמטרים מהבקשה
$service_id = $_GET['service_id'];
$date = $_GET['date'];
$branch_id = $_GET['branch_id'];

// חיבור למסד הנתונים
require_once "../config/database.php";
$database = new Database();
$db = $database->getConnection();

// שליפת נותני השירות הזמינים
$query = "SELECT DISTINCT s.staff_id, s.name 
          FROM staff s 
          INNER JOIN staff_services ss ON s.staff_id = ss.staff_id 
          WHERE s.tenant_id = :tenant_id 
          AND s.branch_id = :branch_id
          AND s.is_active = 1 
          AND ss.service_id = :service_id";

$stmt = $db->prepare($query);
$stmt->bindParam(":tenant_id", $_SESSION['tenant_id']);
$stmt->bindParam(":branch_id", $branch_id);
$stmt->bindParam(":service_id", $service_id);
$stmt->execute();

$staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

// החזרת התוצאות כ-JSON
header('Content-Type: application/json');
echo json_encode(['success' => true, 'staff' => $staff]);
?> 