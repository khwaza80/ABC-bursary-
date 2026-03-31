<?php
session_start();

// Ensure the application_status session variable is set
if (!isset($_SESSION['application_status'])) {
    header("Location: portal_bursary.php");
    exit();
}

$status = $_SESSION['application_status'];
unset($_SESSION['application_status']);

if ($status == 'Accepted') {
    $statusColor = '#2ecc71';
    $icon = '🎉';
    $headline = 'Application Accepted!';
    $message = "Congratulations! Your ABC bursary application was received and <strong>accepted</strong>.<br>We will contact you with further details via email.";
} elseif ($status == 'Rejected') {
    $statusColor = '#e74c3c';
    $icon = '❌';
    $headline = 'Application Not Accepted';
    $message = "Unfortunately, due to the limited number of bursaries available, your application was not accepted.<br>
    <div style='margin-top:15px;'><strong>But don't be discouraged!</strong><br>
    Your academic performance suggests you are a strong candidate.<br>
    We recommend exploring alternative bursary opportunities in Gauteng, such as NSFAS or other private initiatives.<br>
    <a href='alternative_opportunities.php'>Click here for more details</a></div>";
} else { // Processing
    $statusColor = '#f1c40f';
    $icon = '⏳';
    $headline = 'Application is Being Processed';
    $message = "Your application is under review.<br>Please check back later for updates.<br>We will contact you with the outcome via email.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Application Submitted - ABC Bursary</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <style>
        body {
            font-family: 'Poppins', Arial, sans-serif;
            background: linear-gradient(120deg, #e0e7ff 0%, #f8fafc 100%);
            margin: 0;
            min-height: 100vh;
        }
        .box {
            background: white;
            padding: 40px 30px;
            border-radius: 14px;
            text-align: center;
            max-width: 500px;
            margin: 60px auto 0 auto;
            box-shadow: 0 8px 32px rgba(44,62,80,0.13);
            animation: fadeInUp 0.9s cubic-bezier(0.23, 1, 0.32, 1);
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(60px);}
            to   { opacity: 1; transform: translateY(0);}
        }
        .status-icon {
            font-size: 48px;
            margin-bottom: 10px;
            display: block;
        }
        .headline {
            color: <?php echo $statusColor; ?>;
            font-size: 1.6em;
            margin-bottom: 10px;
        }
        .message {
            font-size: 1.1em;
            color: #333;
            margin-bottom: 25px;
        }
        .status-label {
            font-weight: bold;
            color: <?php echo $statusColor; ?>;
            font-size: 1.1em;
        }
        .cta-btn {
            display: inline-block;
            margin-top: 25px;
            padding: 12px 28px;
            background: #2e6da4;
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            font-size: 1em;
            transition: background 0.2s;
        }
        .cta-btn:hover {
            background: #1a406b;
        }
        @media (max-width: 600px) {
            .box { padding: 18px 5px; }
        }
    </style>
</head>
<body>
    <div class="box">
        <span class="status-icon"><?php echo $icon; ?></span>
        <div class="headline"><?php echo $headline; ?></div>
        <div class="message"><?php echo $message; ?></div>
        <div>
            Current Application Status:
            <span class="status-label"><?php echo htmlspecialchars($status); ?></span>
        </div>
        <a class="cta-btn" href="portal_bursary.php">Return to Bursary Portal</a>
    </div>
</body>
</html>