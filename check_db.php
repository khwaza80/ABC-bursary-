<?php
require_once 'db.php';

// Check table structure
$result = $conn->query("SHOW COLUMNS FROM applications");
echo "Table structure:\n";
while ($row = $result->fetch_assoc()) {
    echo print_r($row, true) . "\n";
}

// Check distinct status values
$result = $conn->query("SELECT DISTINCT status FROM applications");
echo "\nDistinct status values:\n";
while ($row = $result->fetch_assoc()) {
    echo $row['status'] . "\n";
}

// Check a specific application
$id = 1; // assuming there's at least one application
$result = $conn->query("SELECT id, status FROM applications WHERE id = $id");
echo "\nSample application:\n";
$row = $result->fetch_assoc();
echo print_r($row, true) . "\n";
?>