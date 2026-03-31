<?php
session_start();
require_once 'db.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Only allow access if POPIA was agreed
if (!isset($_SESSION['popia_agreed'])) {
    header("Location: login.php");
    exit();
}

$login_error = '';
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (empty($email) || empty($password)) {
        $login_error = "Please enter both email and password.";
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, password FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $login_error = "No account found with that email.";
        } elseif (!password_verify($password, $user['password'])) {
            $login_error = "Incorrect password.";
        } else {
            // Generate OTP
            $otp = random_int(100000, 999999);
            // Log OTP for debugging (local dev only)
            error_log("DEBUG: Generated OTP for {$email}: {$otp}");
            $expires = date('Y-m-d H:i:s', time() + 300); // 5 min

            $_SESSION['otp_user_id'] = $user['id'];
            $_SESSION['otp_email'] = $email;
            $_SESSION['otp_code'] = $otp;
            $_SESSION['otp_expires'] = $expires;
            $_SESSION['otp_full_name'] = $user['full_name'];

            // Send OTP via email
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com'; // Use your SMTP server
                $mail->SMTPAuth = true;
                $mail->Username = 'nkanyisokhwaza2@gmail.com'; // your email
                $mail->Password = 'ludi fdhk zimz swmc'; // your app password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom('nkanyisokhwaza2@gmail.com', 'ABC Portal');
                $mail->addAddress($email, $user['full_name']);
                $mail->isHTML(true);
                $mail->Subject = 'Your ABC Portal OTP';
                $mail->Body = "<p>Hi {$user['full_name']},</p>
                               <p>Your OTP for login is: <strong>{$otp}</strong></p>
                               <p>This code will expire in 5 minutes.</p>";

                $mail->send();
                header("Location: verify_otp.php");
                exit();
            } catch (Exception $e) {
                $login_error = "Failed to send OTP email: {$mail->ErrorInfo}";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Login - Step 2</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .navbar { background-color: #002147; overflow: hidden; padding: 15px; }
        .navbar a { float: left; color: white; padding: 14px 16px; text-decoration: none; font-weight: bold; }
        .navbar a:hover { background-color: #0056b3; }
        .container {
            padding: 32px 24px 24px 24px;
            max-width: 400px;
            margin: 50px auto 0 auto;
            border-radius: 14px;
            background: white;
            box-shadow: 0 8px 32px rgba(44,62,80,0.13);
            animation: fadeInUp 0.9s cubic-bezier(0.23, 1, 0.32, 1);
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(60px);}
            to   { opacity: 1; transform: translateY(0);}
        }
        h2 {
            text-align: center;
            color: #002147;
            margin-bottom: 20px;
        }
        label {
            font-weight: 500;
            margin-bottom: 6px;
            display: block;
        }
        input[type="text"], input[type="password"], input[type="email"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 16px;
            border-radius: 7px;
            border: 1px solid #cfd8dc;
            font-size: 1em;
            box-sizing: border-box;
        }
        .login-btn {
            width: 100%;
            padding: 12px;
            background-color: #002147;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.08em;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 8px;
        }
        .login-btn:hover {
            background-color: #0056b3;
        }
        .error {
            background: #e74c3c;
            color: white;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 18px;
            text-align: center;
            font-weight: 500;
        }
        .forgot-link {
            text-align: right;
            margin-top: 10px;
        }
        .forgot-link a {
            color: #e74c3c;
            text-decoration: none;
            font-size: 0.98em;
        }
        .forgot-link a:hover {
            text-decoration: underline;
        }
        .register-link {
            text-align: center;
            margin-top: 18px;
        }
        .register-link a {
            color: #2e6da4;
            text-decoration: underline;
            font-weight: 500;
        }
        .register-link a:hover {
            color: #ffd700;
        }
        @media (max-width: 500px) {
            .container { padding: 12px 2px; }
        }
    </style>
</head>
<body>
<div class="navbar">
    <a href="index.php"><i class="fa fa-home"></i> Home</a>
    <a href="about.html"><i class="fa fa-info-circle"></i> About</a>
    <a href="contact.html"><i class="fa fa-envelope"></i> Contact</a>
    <a href="login.php"><i class="fa fa-sign-in-alt"></i> Login</a>
</div>
<div class="container">
    <h2><i class="fa fa-lock"></i> Login to ABC Portal</h2>
    <?php if ($login_error): ?>
        <div class="error"><?php echo htmlspecialchars($login_error); ?></div>
    <?php endif; ?>
    <form method="post" action="login_step2.php" autocomplete="on">
        <label for="username">Email Address:</label>
        <input type="email" name="username" id="username" required autofocus autocomplete="username" aria-label="Email Address">
        <label for="password">Password:</label>
        <input type="password" name="password" id="password" required autocomplete="current-password" aria-label="Password">
        <button type="submit" class="login-btn">Login</button>
    </form>
    <div class="forgot-link">
        <a href="forgot_password.php">Forgot Password?</a>
    </div>
    <div class="register-link">
        New to ABC? <a href="register.php">Create an account</a>
    </div>
</div>


</body>
</html>