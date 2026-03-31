<?php
// filepath: c:\Users\mypc\OneDrive\Desktop\aura\htdocs\abc_bursary\portal_bursary.php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$email = $_SESSION['email'];

// Fetch user info
$stmt = $conn->prepare("SELECT id, full_name FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

if (!$user) {
    header("Location: logout.php");
    exit();
}

// Fetch latest application for this user
$stmt = $conn->prepare("SELECT * FROM applications WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$result = $stmt->get_result();
$application = $result->fetch_assoc();

$stmt->close();

// Helper for status color
function statusColor($status) {
    if ($status == 'Accepted' || $status == 'Approved') return '#27ae60';
    if ($status == 'Rejected') return '#e74c3c';
    if ($status == 'Processing' || $status == 'Pending') return '#f39c12';
    return '#888';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>ABC Bursary Portal</title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f4f6fb; font-family: 'Segoe UI', Arial, sans-serif; margin: 0; }
        header { background: #002147; color: #fff; padding: 18px 0; text-align: center; }
        nav { background: #1a2a4f; padding: 10px 0; text-align: center; }
        nav a { color: #fff; margin: 0 18px; text-decoration: none; font-weight: 500; }
        nav a:hover { text-decoration: underline; }
        .container { max-width: 900px; margin: 30px auto; background: #fff; border-radius: 10px; box-shadow: 0 2px 12px #e0e7ff; padding: 32px; }
        .welcome { font-size: 1.3em; margin-bottom: 18px; }
        .profile-link { float: right; margin-top: -38px; }
        .profile-link a { color: #002147; text-decoration: none; font-weight: bold; }
        .profile-link a:hover { text-decoration: underline; }
        .dashboard-widget { background: #e0e7ff; padding: 18px; border-radius: 10px; margin-bottom: 18px; text-align: center; }
        .dashboard-widget h3 { color: #002147; }
        .application-summary { display: flex; gap: 18px; margin-bottom: 18px; flex-wrap: wrap; }
        .summary-card { flex: 1 1 180px; background: #f7f9fc; border-radius: 8px; padding: 18px; text-align: center; box-shadow: 0 2px 8px #e0e7ef; }
        .summary-card h4 { margin: 0 0 8px 0; color: #002147; }
        .summary-card .fa { font-size: 1.7em; margin-bottom: 8px; }
        .notifications { background: #f7f9fc; border-left: 5px solid #002147; padding: 12px; border-radius: 8px; margin-bottom: 18px; }
        .application-data { margin-top: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px; border-bottom: 1px solid #eaeaea; text-align: left; }
        th { background: #f7f9fc; }
        .status-icon { font-size: 1.2em; margin-right: 6px; }
        .status-processing { color: #f39c12; font-weight: bold; }
        .status-accepted { color: #27ae60; font-weight: bold; }
        .status-rejected { color: #e74c3c; font-weight: bold; }
        .cta-btn, button { background: #002147; color: #fff; border: none; padding: 10px 22px; border-radius: 5px; cursor: pointer; font-size: 1em; margin-top: 10px; }
        .cta-btn:hover, button:hover { background: #0056b3; }
        .logout { margin-top: 30px; text-align: right; }
        .logout a { color: #e74c3c; text-decoration: none; font-weight: bold; }
        .logout a:hover { text-decoration: underline; }
        .footer { background: #002147; color: #fff; text-align: center; padding: 16px 0; margin-top: 40px; border-radius: 0 0 10px 10px; }
        @media (max-width: 700px) {
            .container { padding: 12px; }
            .application-summary { flex-direction: column; gap: 10px; }
            table, th, td { font-size: 0.95em; }
        }
        /* Popup styles */
        #congrats-popup {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.4);
            z-index: 999;
        }
        .popup-content {
            background: #fff;
            padding: 30px 40px;
            border-radius: 16px;
            max-width: 400px;
            margin: 80px auto;
            text-align: center;
            position: relative;
        }
        .close-btn {
            position: absolute;
            top: 10px;
            right: 14px;
            font-size: 1.5em;
            background: none;
            border: none;
            cursor: pointer;
        }
        .confetti {
            font-size: 2.5em;
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
    <div class="profile-link">
        <a href="profile.php"><i class="fa fa-user-circle"></i> Profile</a>
    </div>
    <div class="welcome">
        👋 Hello, <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>!
    </div>
    <div class="application-summary">
        <div class="summary-card">
            <div><i class="fa fa-file-alt" style="color:#002147;"></i></div>
            <h4>Application Status</h4>
            <div style="color:<?php echo $application ? statusColor($application['status']) : '#888'; ?>;font-weight:bold;">
                <?php echo $application ? htmlspecialchars($application['status']) : "No Application"; ?>
            </div>
        </div>
        <div class="summary-card">
            <div><i class="fa fa-calendar" style="color:#27ae60;"></i></div>
            <h4>Date Applied</h4>
            <div>
                <?php echo $application ? htmlspecialchars($application['date_applied']) : "-"; ?>
            </div>
        </div>
        <div class="summary-card">
            <div><i class="fa fa-trophy" style="color:#f39c12;"></i></div>
            <h4>Progress</h4>
            <div>
                <?php
                if ($application) {
                    $status = $application['status'];
                    echo ($status == 'Accepted') ? "100%" : (($status == 'Processing') ? "60%" : "30%");
                } else {
                    echo "0%";
                }
                ?>
            </div>
        </div>
    </div>
    <div class="notifications">
        <?php
        // Example notification logic
        if ($application && $application['status'] == 'Accepted') {
            echo '<i class="fa fa-trophy" style="color:#27ae60;"></i> <b>Congratulations!</b> Your application has been accepted!';
        } elseif ($application && $application['status'] == 'Rejected') {
            echo '<i class="fa fa-exclamation-circle" style="color:#e74c3c;"></i> <b>Update:</b> Your application was not successful. <a href="alternative_opportunities.php">See other bursaries</a>.';
        } elseif ($application) {
            echo '<i class="fa fa-hourglass-half" style="color:#f39c12;"></i> <b>Status:</b> Your application is being processed.';
        } else {
            echo '<i class="fa fa-info-circle" style="color:#002147;"></i> <b>Tip:</b> Start your bursary application now!';
        }
        ?>
    </div>
    <div class="dashboard-widget">
        <h3>Application Progress</h3>
        <div style="background:#f3f6fa;border-radius:8px;overflow:hidden;margin:10px 0;">
            <div style="width:
              <?php
                if ($application) {
                    $status = $application['status'];
                    echo ($status == 'Accepted') ? '100%' : (($status == 'Processing') ? '60%' : '30%');
                } else {
                    echo '0%';
                }
              ?>;
              background:<?php echo $application ? statusColor($application['status']) : '#888'; ?>;height:14px;"></div>
        </div>
        <small>
            <?php
            if ($application) {
                if ($status == 'Accepted') echo "Completed";
                elseif ($status == 'Processing') echo "In Progress";
                else echo "Started";
            } else {
                echo "No application yet";
            }
            ?>
        </small>
    </div>
    <?php if ($application): ?>
        <div class="application-data">
            <h3>Your Bursary Application Details</h3>
            <table>
                <tr><th>Student Number</th><td><?php echo htmlspecialchars($application['student_number']); ?></td></tr>
                <tr><th>Bursary Type</th><td><?php echo htmlspecialchars($application['bursary_type']); ?></td></tr>
                <tr><th>Year of Study</th><td><?php echo htmlspecialchars($application['year_of_study']); ?></td></tr>
                <tr><th>Academic Year</th><td><?php echo htmlspecialchars($application['academic_year']); ?></td></tr>
                <tr><th>Institution Type</th><td><?php echo htmlspecialchars($application['institution_type']); ?></td></tr>
                <tr><th>University</th><td><?php echo htmlspecialchars($application['university']); ?></td></tr>
                <tr><th>Qualification Type</th><td><?php echo htmlspecialchars($application['qualification_type']); ?></td></tr>
                <tr><th>Qualification</th><td><?php echo htmlspecialchars($application['qualification']); ?></td></tr>
                <tr><th>Degree Name</th><td><?php echo htmlspecialchars($application['degree_name']); ?></td></tr>
                <tr><th>Field of Study</th><td><?php echo htmlspecialchars($application['field_of_study']); ?></td></tr>
                <tr><th>Qualification Duration</th><td><?php echo htmlspecialchars($application['qualification_duration']); ?></td></tr>
                <tr><th>Motivation</th><td><?php echo htmlspecialchars($application['motivation']); ?></td></tr>
                <tr><th>Motivation Comments</th><td><?php echo nl2br(htmlspecialchars($application['motivation_comments'])); ?></td></tr>
                <tr><th>Other Sponsor</th><td><?php echo htmlspecialchars($application['other_sponsor']); ?></td></tr>
                <?php if ($application['other_sponsor'] === 'Yes'): ?>
                <tr><th>Other Sponsor Name</th><td><?php echo htmlspecialchars($application['other_sponsor_name']); ?></td></tr>
                <tr><th>Other Sponsor Cellphone</th><td><?php echo htmlspecialchars($application['other_sponsor_cell']); ?></td></tr>
                <tr><th>Other Sponsor Reason</th><td><?php echo nl2br(htmlspecialchars($application['other_sponsor_reason'])); ?></td></tr>
                <?php endif; ?>
                <tr>
                    <th>Status</th>
                    <td>
                        <?php
                        $status = $application['status'];
                        if ($status == 'Pending' || $status == 'Processing') {
                            echo '<span class="status-icon"><i class="fa-solid fa-hourglass-half"></i></span>';
                            echo '<span class="status-processing">Processing</span>';
                            echo '<br><a href="start_application.php?edit=1"><button>Edit Application</button></a>';
                        } elseif ($status == 'Approved' || $status == 'Accepted') {
                            echo '<span class="status-icon"><i class="fa-solid fa-circle-check"></i></span>';
                            echo '<span class="status-accepted">Accepted</span>';
                        } elseif ($status == 'Rejected') {
                            echo '<span class="status-icon"><i class="fa-solid fa-circle-xmark"></i></span>';
                            echo '<span class="status-rejected">Rejected</span><br>';
                            echo 'Sorry, we could not accept your application, but after reviewing your marks, we see you have potential. ';
                            echo '<a class="alt-link" href="alternative_opportunities.php">Click here</a> to see other bursaries you can apply to.';
                        } else {
                            echo htmlspecialchars($status);
                        }
                        ?>
                    </td>
                </tr>
                <tr><th>Date Applied</th><td><?php echo htmlspecialchars($application['date_applied']); ?></td></tr>
            </table>
            <?php if ($status == 'Accepted' || $status == 'Approved'): ?>
                <div style="text-align:center;margin-top:20px;">
                    <a href="award_letter.php" class="cta-btn"><i class="fa fa-file-pdf"></i> View/Download Award Letter</a>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="application-data" style="text-align:center;">
            <p style="color: #e74c3c; font-weight: bold; font-size:1.1em;">You haven't registered an application yet.</p>
            <a href="start_application.php" class="cta-btn"><i class="fa fa-plus"></i> Start Application</a>
        </div>
    <?php endif; ?>
    <div class="logout">
        <a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </div>
</div>
<footer class="footer">
    &copy; <?php echo date('Y'); ?> ABC Bursary. All rights reserved.
</footer>
<?php if (isset($_SESSION['show_congrats_popup']) && $_SESSION['show_congrats_popup']): ?>
    <div id="congrats-popup">
        <h2>Congratulations!</h2>
        <p>Your bursary application has been ACCEPTED.<br>Welcome to the ABC Bursary family!</p>
        <a href="download_letter.php" class="download-btn">Download Acceptance Letter</a>
        <button onclick="document.getElementById('congrats-popup').style.display='none';">Close</button>
    </div>
    <?php unset($_SESSION['show_congrats_popup']); ?>
<?php endif; ?>
</body>
</html>