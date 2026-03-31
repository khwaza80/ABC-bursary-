<?php
session_start();
require_once 'db.php';

// Optional composer autoload for PHPMailer; only include if present to avoid fatal errors
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

if (!isset($_SESSION['otp_code']) || !isset($_SESSION['otp_email']) || !isset($_SESSION['otp_expires'])) {
    header("Location: login.php");
    exit();
}

$error = '';
$info = '';
$otp_email = $_SESSION['otp_email'];

// Resend handler
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['resend'])) {
    // Generate new OTP
    $otp = random_int(100000, 999999);
    $expires = date('Y-m-d H:i:s', time() + 300); // 5 minutes

    $_SESSION['otp_code'] = $otp;
    $_SESSION['otp_expires'] = $expires;

    // Try sending email if PHPMailer is available
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'nkanyisokhwaza2@gmail.com';
            $mail->Password = 'ludi fdhk zimz swmc';
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('nkanyisokhwaza2@gmail.com', 'ABC Portal');
            $mail->addAddress($otp_email);
            $mail->isHTML(true);
            $mail->Subject = 'Your ABC Portal OTP (resend)';
            $mail->Body = "<p>Your new OTP is: <strong>{$otp}</strong></p><p>It expires in 5 minutes.</p>";
            $mail->send();
            $info = 'A new OTP has been sent to your email.';
        } catch (\Exception $e) {
            $error = 'Failed to send OTP email: ' . $mail->ErrorInfo;
        }
    } else {
        $info = 'A new OTP has been generated (email not sent because PHPMailer is not available on the server).';
    }
}

// Verify handler
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['verify_otp'])) {
    $entered_otp = trim($_POST['otp'] ?? '');
    $stored_otp = (string)$_SESSION['otp_code'];
    $expires = strtotime($_SESSION['otp_expires']);

    if (empty($entered_otp)) {
        $error = 'Please enter the OTP.';
    } elseif (time() > $expires) {
        $error = 'OTP has expired. Please request a new one.';
    } elseif ((string)$entered_otp === $stored_otp) {
        // Success
        $_SESSION['user_id'] = $_SESSION['otp_user_id'];
        $_SESSION['email'] = $_SESSION['otp_email'];
        $_SESSION['full_name'] = $_SESSION['otp_full_name'];
        $_SESSION['logged_in'] = true;

    unset($_SESSION['otp_code'], $_SESSION['otp_email'], $_SESSION['otp_expires'], $_SESSION['otp_user_id'], $_SESSION['otp_full_name']);
    header('Location: portal_bursary.php');
        exit();
    } else {
        $error = 'Invalid OTP. Please try again.';
    }
}

// Provide expiry timestamp to JS
$expiry_ts = isset($_SESSION['otp_expires']) ? strtotime($_SESSION['otp_expires']) : time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify OTP - ABC Bursary</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin:0; padding:0 }
        .container { max-width:420px; margin:60px auto; background:#fff; padding:24px; border-radius:8px; box-shadow:0 6px 20px rgba(0,0,0,.08); text-align:center }
        .error{background:#f8d7da;color:#721c24;padding:10px;border-radius:6px;margin-bottom:12px}
        .info{background:#d1e7dd;color:#0f5132;padding:10px;border-radius:6px;margin-bottom:12px}
        .otp-input{width:220px;padding:12px;font-size:22px;letter-spacing:8px;text-align:center;border:2px solid #002147;border-radius:6px;margin:12px 0}
        .btn{background:#002147;color:#fff;padding:10px 18px;border:none;border-radius:6px;cursor:pointer;margin:6px}
        .btn-secondary{background:#6c757d}
        #timer{margin-top:8px;font-weight:600}
    </style>
    <script>
        // Countdown based on server expiry timestamp
        document.addEventListener('DOMContentLoaded', function(){
            var expiry = parseInt(<?php echo json_encode($expiry_ts); ?>, 10) * 1000;
            var display = document.getElementById('timer');
            var verifyBtn = document.getElementById('verifyBtn');
            function update(){
                var now = Date.now();
                var diff = Math.floor((expiry - now)/1000);
                if (diff <= 0) {
                    display.textContent = 'Code expired. Click Resend to get a new code.';
                    if (verifyBtn) verifyBtn.disabled = true;
                    clearInterval(interval);
                    return;
                }
                var m = Math.floor(diff/60), s = diff%60;
                display.textContent = 'Code expires in: ' + (m<10? '0'+m : m) + ':' + (s<10? '0'+s : s);
            }
            update();
            var interval = setInterval(update, 1000);
        });
    </script>
</head>
<body>
    <div class="container">
        <h2>Verify Your OTP</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($info): ?>
            <div class="info"><?php echo htmlspecialchars($info); ?></div>
        <?php endif; ?>

        <p>Enter the 6-digit code sent to your email:</p>
        <p><strong><?php echo htmlspecialchars($otp_email); ?></strong></p>

        <form method="POST" style="margin-bottom:6px">
            <input type="text" name="otp" class="otp-input" maxlength="6" required autofocus pattern="\d{6}" title="6-digit code">
            <div id="timer"></div>
            <div style="margin-top:12px">
                <button type="submit" name="verify_otp" id="verifyBtn" class="btn">Verify Code</button>
            </div>
        </form>

        <form method="POST">
            <button type="submit" name="resend" class="btn btn-secondary">Resend Code</button>
        </form>
    </div>
</body>
</html>