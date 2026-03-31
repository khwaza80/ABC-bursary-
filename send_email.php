<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

function sendEmail($to, $toName, $subject, $message) {
    // Create logs directory if it doesn't exist
    $logsDir = __DIR__ . "/logs";
    if (!file_exists($logsDir)) {
        mkdir($logsDir);
    }

    // Start logging
    $log = "[" . date('Y-m-d H:i:s') . "] Attempting to send email to: {$to}\n";
    file_put_contents($logsDir . "/email_detailed.log", $log, FILE_APPEND);

    try {
        $mail = new PHPMailer(true);
        
        // Enable debugging
        $mail->SMTPDebug = 3; // Enable verbose debug output
        $mail->Debugoutput = function($str, $level) use ($logsDir) {
            file_put_contents($logsDir . "/email_detailed.log", "[" . date('Y-m-d H:i:s') . "] {$str}\n", FILE_APPEND);
        };

        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'nkanyisokhwaza2@gmail.com';
        $mail->Password = 'ansl gkub lrdf jejc';
        
        // Enable verbose error reporting
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->Debugoutput = 'error_log';
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Recipients
        $mail->setFrom('nkanyisokhwaza2@gmail.com', 'ABC Bursary');
        $mail->addAddress($to, $toName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        // Send email
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: " . $e->getMessage());
        return false;
    }
}

// Test the email sending directly when this file is accessed
if (basename($_SERVER['SCRIPT_NAME']) === 'send_email.php') {
    error_log("Starting direct email test...");
    
    $result = sendEmail(
        'nkanyisokhwaza2@gmail.com',
        'Test User',
        'Test Email from Bursary System',
        '<h1>Test Email</h1><p>This is a test email sent at: ' . date('Y-m-d H:i:s') . '</p>'
    );
    
    if ($result) {
        echo "Email sent successfully! Check your inbox and spam folder.";
        error_log("Test email sent successfully!");
    } else {
        echo "Failed to send email. Check the error logs.";
        error_log("Test email failed!");
    }
}
?>