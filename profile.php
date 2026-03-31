<?php
// filepath: c:\Users\mypc\OneDrive\Desktop\aura\htdocs\abc_bursary\profile.php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$email = $_SESSION['email'];
$message = '';
$message_type = '';

// Fetch user info
$stmt = $conn->prepare("SELECT id, full_name, email FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: logout.php");
    exit();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');

    // Basic validation
    if (empty($full_name)) {
        $message = "Full name cannot be empty.";
        $message_type = "error";
    } elseif (!preg_match('/^[a-zA-Z\s\.\'-]+$/', $full_name)) {
        $message = "Full name contains invalid characters.";
        $message_type = "error";
    } else {
        // Update user info
        $stmt = $conn->prepare("UPDATE users SET full_name = ? WHERE id = ?");
        $stmt->bind_param("si", $full_name, $user['id']);
        if ($stmt->execute()) {
            $message = "Profile updated successfully.";
            $message_type = "success";
            $user['full_name'] = $full_name;
        } else {
            $message = "Failed to update profile. Please try again.";
            $message_type = "error";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>My Profile - ABC Bursary</title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f6fb; font-family: 'Segoe UI', Arial, sans-serif; margin: 0; }
        header { background: #002147; color: #fff; padding: 18px 0; text-align: center; }
        nav { background: #1a2a4f; padding: 10px 0; text-align: center; }
        nav a { color: #fff; margin: 0 18px; text-decoration: none; font-weight: 500; }
        nav a:hover { text-decoration: underline; }
        .container { max-width: 500px; margin: 40px auto; background: #fff; border-radius: 10px; box-shadow: 0 2px 12px #e0e7ff; padding: 32px; }
        h2 { color: #002147; }
        label { display: block; margin-top: 18px; font-weight: bold; }
        input[type="text"], input[type="email"] {
            width: 100%; padding: 10px; margin-top: 6px; border-radius: 5px; border: 1px solid #ccc;
        }
        input[readonly] { background: #f7f9fc; }
        button { background: #002147; color: #fff; border: none; padding: 10px 22px; border-radius: 5px; cursor: pointer; font-size: 1em; margin-top: 18px; }
        button:hover { background: #0056b3; }
        .message { margin: 15px 0; padding: 10px; border-radius: 5px; }
        .error { background-color: #f8d7da; color: #842029; }
        .success { background-color: #d1e7dd; color: #0f5132; }
        .logout { margin-top: 30px; text-align: right; }
        .logout a { color: #e74c3c; text-decoration: none; font-weight: bold; }
        .logout a:hover { text-decoration: underline; }
        .back-link { margin-top: 18px; display: block; }
        @media (max-width: 600px) {
            .container { padding: 12px; }
        }
    </style>
</head>
<body>
<header>
    <h1>ABC Bursary Portal</h1>
</header>
<nav>
    <a href="portal_bursary.php"><i class="fa fa-home"></i> Home</a>
    <a href="profile.php"><i class="fa fa-user"></i> My Profile</a>
    <a href="start_application.php"><i class="fa fa-file-alt"></i> Apply</a>
    <a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>
</nav>
<div class="container">
    <h2><i class="fa fa-user-circle"></i> My Profile</h2>
    <?php if ($message): ?>
        <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <form method="post" autocomplete="off">
        <label for="full_name">Full Name:</label>
        <input type="text" name="full_name" id="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required maxlength="80" pattern="[a-zA-Z\s\.\'-]+">

        <label for="email">Email Address:</label>
        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>

        <button type="submit"><i class="fa fa-save"></i> Update Profile</button>
    </form>
    <a href="portal_bursary.php" class="back-link"><i class="fa fa-arrow-left"></i> Back to Portal</a>
</div>
<footer class="footer" style="background:#002147;color:#fff;text-align:center;padding:16px 0;margin-top:40px;border-radius:0 0 10px 10px;">
    &copy; <?php echo date('Y'); ?> ABC Bursary. All rights reserved.
</footer>
</body>
</html> 