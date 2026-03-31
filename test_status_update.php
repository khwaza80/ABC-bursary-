<?php
require_once 'db.php';

// Function to check if a status exists in the applications table
function checkStatus($conn, $status) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM applications WHERE status = ?");
    $stmt->bind_param("s", $status);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'];
}

// Function to get an application's current status
function getApplicationStatus($conn, $appId) {
    $stmt = $conn->prepare("SELECT status FROM applications WHERE id = ?");
    $stmt->bind_param("i", $appId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['status'] ?? null;
}

// Test database connection
echo "Testing database connection...\n";
if ($conn->connect_error) {
    echo "Connection failed: " . $conn->connect_error . "\n";
    exit;
}
echo "Database connection successful!\n\n";

// Check what status values exist in the database
echo "Checking existing status values:\n";
$result = $conn->query("SELECT DISTINCT status FROM applications");
echo "Found these status values:\n";
while ($row = $result->fetch_assoc()) {
    echo "- " . $row['status'] . "\n";
}
echo "\n";

// Check counts for specific statuses
echo "Checking status counts:\n";
echo "Processing: " . checkStatus($conn, 'Processing') . "\n";
echo "Approved: " . checkStatus($conn, 'Approved') . "\n";
echo "Rejected: " . checkStatus($conn, 'Rejected') . "\n\n";

// Get a sample application
$result = $conn->query("SELECT id FROM applications LIMIT 1");
if ($row = $result->fetch_assoc()) {
    $sampleId = $row['id'];
    echo "Sample application #{$sampleId} status: " . getApplicationStatus($conn, $sampleId) . "\n";
    
    // Try to update the status
    $newStatus = 'Approved';
    $stmt = $conn->prepare("UPDATE applications SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $newStatus, $sampleId);
    
    echo "Attempting to update status to 'Approved'...\n";
    if ($stmt->execute()) {
        echo "Update successful! New status: " . getApplicationStatus($conn, $sampleId) . "\n";
    } else {
        echo "Update failed: " . $stmt->error . "\n";
    }
}

$conn->close();
?>