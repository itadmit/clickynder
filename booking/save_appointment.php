<?php

$service_id = $_POST['service_id'];
$staff_id = $_POST['staff_id'];
$branch_id = $_POST['branch_id'];
$date = $_POST['date'];
$time = $_POST['time'];
$customer_name = $_POST['customer_name'];
$customer_phone = $_POST['customer_phone'];
$customer_email = $_POST['customer_email'] ?? null;
$notes = $_POST['notes'] ?? '';

// הכנסת התור למסד הנתונים
$query = "INSERT INTO appointments (tenant_id, branch_id, service_id, staff_id, customer_name, customer_phone, customer_email, appointment_date, appointment_time, notes, status) 
          VALUES (:tenant_id, :branch_id, :service_id, :staff_id, :customer_name, :customer_phone, :customer_email, :appointment_date, :appointment_time, :notes, 'pending')";
$stmt = $db->prepare($query);

$stmt->bindParam(":tenant_id", $tenant_id);
$stmt->bindParam(":branch_id", $branch_id);
$stmt->bindParam(":service_id", $service_id);
$stmt->bindParam(":staff_id", $staff_id);
$stmt->bindParam(":customer_name", $customer_name);
$stmt->bindParam(":customer_phone", $customer_phone);
$stmt->bindParam(":customer_email", $customer_email);
$stmt->bindParam(":appointment_date", $date);
$stmt->bindParam(":appointment_time", $time);
$stmt->bindParam(":notes", $notes);

$stmt->execute(); 