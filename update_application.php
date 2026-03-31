
<?php
session_start();
require_once 'db.php';

// Security: Only allow admins
// if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
//     $_SESSION['message'] = "Access denied. Admins only.";
//     header("Location: login.php");
//     exit();
// }

// Debug log
error_log("POST data received: " . print_r($_POST, true));

require_once 'send_email.php';

if (
    isset($_POST['application_id'], $_POST['new_status']) &&
    is_numeric($_POST['application_id']) &&
    in_array($_POST['new_status'], ['Processing', 'Accepted', 'Rejected'])
) {
    // Debug log
    error_log("Starting to process application update...");
    $appId = intval($_POST['application_id']);
    $newStatus = $_POST['new_status'];
    $reviewComments = $_POST['review_comments'] ?? '';

    // Get student details first
    $studentQuery = $conn->prepare("SELECT u.email, u.full_name as fullname FROM users u JOIN applications a ON u.id = a.user_id WHERE a.id = ?");
    if (!$studentQuery) {
        error_log("SQL Error: " . $conn->error);
        $_SESSION['message'] = "Database error while fetching user details.";
        header("Location: admin_review.php");
        exit();
    }
    
    $studentQuery->bind_param("i", $appId);
    $studentQuery->execute();
    $studentResult = $studentQuery->get_result();
    $student = $studentResult->fetch_assoc();
    $studentQuery->close();
    
    if (!$student) {
        $_SESSION['message'] = "Could not find student details.";
        header("Location: admin_review.php");
        exit();
    }

    // If rejected, store the comments in the rescue_pool table
    if ($newStatus === 'Rejected') {
        error_log("Application rejected - storing comments in rescue_pool");
        $rescue = $conn->prepare("INSERT INTO rescue_pool (user_id, application_id, notes) SELECT user_id, id, ? FROM applications WHERE id = ?");
        if ($rescue) {
            $rescue->bind_param("si", $reviewComments, $appId);
            $rescue->execute();
            $rescue->close();
        }
    }

    // Update application status
    error_log("Attempting to update application ID: $appId to status: $newStatus");
    
    // Debug: Check current status
    $checkStmt = $conn->prepare("SELECT status FROM applications WHERE id = ?");
    $checkStmt->bind_param("i", $appId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $currentStatus = $checkResult->fetch_assoc();
    error_log("Current status for application $appId: " . print_r($currentStatus, true));
    $checkStmt->close();
    
    $stmt = $conn->prepare("UPDATE applications SET status = ? WHERE id = ?");
    if ($stmt === false) {
        error_log("SQL Error in prepare: " . $conn->error);
        $_SESSION['message'] = "Database error. Please check the error log.";
        header("Location: admin_review.php");
        exit();
    }
    
    $stmt->bind_param("si", $newStatus, $appId);
    error_log("Executing UPDATE with status='$newStatus' and id='$appId'");

    if ($stmt->execute()) {
        error_log("UPDATE query executed successfully. Affected rows: " . $stmt->affected_rows);
        
    // Verify the update
    $verifyStmt = $conn->prepare("SELECT status FROM applications WHERE id = ?");
    $verifyStmt->bind_param("i", $appId);
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->get_result();
    $updatedRow = $verifyResult->fetch_assoc();
    $updatedStatus = $updatedRow['status'] ?? null;
    error_log("New status after update: " . print_r($updatedRow, true));
    $verifyStmt->close();
        
        // Log the update
        $admin = $_SESSION['email'] ?? 'unknown';
        $logStmt = $conn->prepare("INSERT INTO application_logs (application_id, action, performed_by, performed_at) VALUES (?, ?, ?, NOW())");
        $action = "Status changed to {$updatedStatus}";
        $logStmt->bind_param("iss", $appId, $action, $admin);
        $logStmt->execute();
        $logStmt->close();

        // Prepare email content
        // Use the actual enum values from the DB: 'Accepted' and 'Rejected'
        if ($updatedStatus === 'Accepted') {
            $subject = "Congratulations! Your Bursary Application has been accepted";
            $message = "<p>Congratulations, {$student['fullname']}!</p>";
            $message .= "<p>Your application for the ABC Bursary program has been <strong>accepted</strong>.</p>";
            $message .= "<p>We will contact you shortly with next steps and any documentation required.</p>";
            $_SESSION['show_congrats_popup'] = true;
        } else if ($updatedStatus === 'Rejected') {
            $subject = "Update on Your Bursary Application";
            // Friendly rejection message requested by the user
            $message = "<p>We're sorry to inform you that your application has been <strong>rejected</strong>.</p>";
            $message .= "<p>Thank you for taking the time to apply for the ABC Bursary program.</p>";
            if (!empty($reviewComments)) {
                $message .= "<p><strong>Feedback:</strong> {$reviewComments}</p>";
            }
            $message .= "<p>We encourage you to apply again in future rounds or explore other opportunities we may share.</p>";
        } else {
            $subject = "Bursary Application Status Update";
            $message = "<p>Your application status has been updated to: {$updatedStatus}</p>";
        }

        // Execute Python email script
        $pythonScript = __DIR__ . "/send_bulk_notification.py";
        
        // Try to use full path to Python if available
        $pythonPath = "C:\\Python312\\python.exe";  // Adjust this path based on your Python installation
        if (!file_exists($pythonPath)) {
            $pythonPath = "python";  // Fallback to using PATH
        }
        
        $cmd = sprintf('"%s" "%s" %s %s %s',
            $pythonPath,
            $pythonScript,
            escapeshellarg($student['email']),
            escapeshellarg($subject),
            escapeshellarg($message)
        );
        
        error_log("Executing command: " . $cmd);  // Log the command for debugging
        exec($cmd, $output, $exitCode);
        error_log("Python script output: " . implode("\n", $output));  // Log the output
        
        if ($exitCode === 0) {
            $_SESSION['message'] = "✅ Application #{$appId} status updated to <b>{$updatedStatus}</b>. Email notification sent.";
            error_log("Email notification sent successfully to: " . $student['email']);
        } else {
            $_SESSION['message'] = "✅ Application #{$appId} status updated to <b>{$updatedStatus}</b>. ⚠️ Email notification failed.";
            error_log("Failed to send email. Exit code: " . $exitCode . " Error: " . implode("\n", $output));
        }
    } else {
        $_SESSION['message'] = "❌ Error updating application. Please try again.";
    }
    $stmt->close();
} else {
    $_SESSION['message'] = "❌ Invalid request. Please check your input.";
}

$conn->close();
header("Location: admin_review.php");
exit();
?>