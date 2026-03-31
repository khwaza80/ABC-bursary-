<?php
session_start();
require_once 'db.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $_SESSION['reset_error'] = "Please enter your email address.";
        header("Location: forgot_password.php");
        exit();
    }

    // Check if email exists in database
    $stmt = $conn->prepare("SELECT id, full_name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user) {
        // Generate unique reset token
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', time() + 3600); // Token expires in 1 hour

        // Store reset token in database
        $stmt = $conn->prepare("UPDATE users SET reset_token = ?, token_expiry = ? WHERE email = ?");
        $stmt->bind_param("sss", $token, $expiry, $email);
        $stmt->execute();
        $stmt->close();

        // Send reset email
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'nkanyisokhwaza2@gmail.com'; // your email
            $mail->Password = 'ludi fdhk zimz swmc'; // your app password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('nkanyisokhwaza2@gmail.com', 'ABC Portal');
            $mail->addAddress($email, $user['full_name']);
            $mail->isHTML(true);

            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/abc_bursary/reset_password.php?token=" . $token;
            
            $mail->Subject = 'Password Reset Request - ABC Portal';
            $mail->Body = "
                <p>Hello {$user['full_name']},</p>
                <p>We received a request to reset your password for your ABC Portal account.</p>
                <p>To reset your password, click on the link below:</p>
                <p><a href='{$reset_link}'>{$reset_link}</a></p>
                <p>This link will expire in 1 hour for security reasons.</p>
                <p>If you didn't request this password reset, please ignore this email.</p>
                <p>Best regards,<br>ABC Portal Team</p>";

            $mail->send();
            $_SESSION['reset_success'] = "Password reset instructions have been sent to your email.";
        } catch (Exception $e) {
            $_SESSION['reset_error'] = "Failed to send reset email. Please try again later.";
        }
    } else {
        // Don't reveal if email exists or not for security
        $_SESSION['reset_success'] = "If your email exists in our system, you will receive password reset instructions.";
    }

    header("Location: forgot_password.php");
    exit();
} else {
    // If accessed directly without POST request
    header("Location: forgot_password.php");
    exit();
}
?>