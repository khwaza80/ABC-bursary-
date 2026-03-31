<?php
session_start();
require_once 'db.php';

$token = $_GET['token'] ?? '';
$error = '';

if (empty($token)) {
    die("Invalid or missing token.");
}

// Find user by token
$sql = "SELECT id, token_expiry FROM users WHERE reset_token = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die("Invalid or expired token.");
}

$user = $result->fetch_assoc();

// Check expiry
if (strtotime($user['token_expiry']) < time()) {
    die("This reset link has expired.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['confirm'] ?? '');

    if (empty($new_password) || strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($new_password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        // Save new password and clear token
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, token_expiry = NULL WHERE id = ?");
        $update->bind_param("si", $hashed, $user['id']);
        $update->execute();
        $update->close();

        $_SESSION['message'] = "Password reset successfully. Please log in.";
        $_SESSION['message_type'] = "success";
        header("Location: login.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password - ABC Bursary</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; }
        .container { max-width: 400px; margin: 60px auto; background: #fff; padding: 24px; border-radius: 10px; box-shadow: 0 2px 8px #eee; }
        label { display: block; margin-top: 12px; }
        input { width: 100%; padding: 8px; margin-top: 4px; border-radius: 5px; border: 1px solid #ccc; }
        button { margin-top: 16px; background: #002147; color: #fff; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; }
        .error { color: red; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Reset Password</h2>
        <?php if (!empty($error)) echo "<div class='error'>$error</div>"; ?>
        <form method="post">
            <label for="password">New Password:</label>
            <input type="password" name="password" id="password" required minlength="6">
            <label for="confirm">Confirm Password:</label>
            <input type="password" name="confirm" id="confirm" required minlength="6">
            <button type="submit">Reset Password</button>
        </form>
    </div>
</body>
</html>