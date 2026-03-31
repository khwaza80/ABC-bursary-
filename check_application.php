<?php
require_once 'db.php';

// Find user id for the email
$email = 'neotelane526@gmail.com';
$user_sql = "SELECT id FROM users WHERE email = ?";
if ($stmt = $conn->prepare($user_sql)) {
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if ($user) {
        $user_id = $user['id'];
        echo "Found user_id: " . $user_id . "\n";
        
        // Get their most recent application
        $app_sql = "SELECT id, status, external_acceptance_time FROM applications WHERE user_id = ? ORDER BY date_applied DESC LIMIT 1";
        if ($stmt = $conn->prepare($app_sql)) {
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $app = $result->fetch_assoc();
            $stmt->close();
            
            if ($app) {
                echo "Application found:\n";
                echo "ID: " . $app['id'] . "\n";
                echo "Status: " . $app['status'] . "\n";
                echo "External acceptance time: " . ($app['external_acceptance_time'] ?? 'NULL') . "\n";
                
                // If rejected but no external_acceptance_time, let's set it
                if ($app['status'] === 'Rejected' && !$app['external_acceptance_time']) {
                    $update_sql = "UPDATE applications SET external_acceptance_time = NOW() WHERE id = ?";
                    if ($stmt = $conn->prepare($update_sql)) {
                        $stmt->bind_param('i', $app['id']);
                        if ($stmt->execute()) {
                            echo "\nUpdated application " . $app['id'] . " with current timestamp!\n";
                            echo "Please refresh your dashboard now - you should see the countdown!\n";
                        }
                        $stmt->close();
                    }
                }
            } else {
                echo "No application found for this user.\n";
            }
        }
    } else {
        echo "User not found with email: " . $email;
    }
}
?>