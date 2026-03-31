<?php
require_once 'db.php';
header('Content-Type: application/json');
$out = [];
// users columns
$res = $conn->query("SHOW COLUMNS FROM users");
$cols = [];
if ($res) { while($r = $res->fetch_assoc()) $cols[] = $r; }
$out['users_columns'] = $cols;
// applications columns
$res = $conn->query("SHOW COLUMNS FROM applications");
$cols = [];
if ($res) { while($r = $res->fetch_assoc()) $cols[] = $r; }
$out['applications_columns'] = $cols;
// sample row
$res = $conn->query("SELECT id, status, user_id FROM applications LIMIT 5");
$rows = [];
if ($res) { while($r = $res->fetch_assoc()) $rows[] = $r; }
$out['sample_applications'] = $rows;
echo json_encode($out, JSON_PRETTY_PRINT);
?>