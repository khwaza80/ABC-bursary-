<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - ABC Bursary</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            font-family: Arial, sans-serif; 
            background: #f4f4f4; 
            margin: 0; 
            padding: 0; 
        }
        .navbar { 
            background-color: #002147; 
            overflow: hidden; 
            padding: 15px; 
        }
        .navbar a { 
            float: left; 
            color: white; 
            padding: 14px 16px; 
            text-decoration: none; 
            font-weight: bold; 
        }
        .navbar a:hover { 
            background-color: #0056b3; 
        }
        .container {
            max-width: 400px;
            margin: 50px auto;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h2 {
            color: #002147;
            text-align: center;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #333;
        }
        input[type="email"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .submit-btn {
            width: 100%;
            padding: 12px;
            background-color: #002147;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .submit-btn:hover {
            background-color: #0056b3;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .back-to-login {
            text-align: center;
            margin-top: 20px;
        }
        .back-to-login a {
            color: #002147;
            text-decoration: none;
        }
        .back-to-login a:hover {
            text-decoration: underline;
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
        <h2><i class="fa fa-lock"></i> Forgot Password</h2>
        
        <?php if (isset($_SESSION['reset_success'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['reset_success'];
                unset($_SESSION['reset_success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['reset_error'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['reset_error'];
                unset($_SESSION['reset_error']);
                ?>
            </div>
        <?php endif; ?>

        <form action="send_reset_link.php" method="POST">
            <div class="form-group">
                <label for="email">Email Address:</label>
                <input type="email" id="email" name="email" required placeholder="Enter your registered email">
            </div>
            <button type="submit" class="submit-btn">Send Reset Link</button>
        </form>
        <div class="back-to-login">
            <a href="login.php">Back to Login</a>
        </div>
    </div>
</body>
</html>
