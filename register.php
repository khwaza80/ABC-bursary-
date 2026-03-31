<?php
session_start();
require_once 'db.php';

// Initialize all display flags
$show_criteria = true;
$show_second_criteria = false;
$show_popia = false;
$show_form = false;
$show_success = false;
$errors = [];
$success_msg = "";

// Helper function to sanitize output
function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['step'])) {
        $step = $_POST['step'];

        if ($step === 'criteria_next') {
            $show_criteria = false;
            $show_second_criteria = true;

        } elseif ($step === 'popia_agree') {
            $show_criteria = false;
            $show_second_criteria = false;

            if (isset($_POST['agree']) && $_POST['agree'] === 'yes') {
                $show_form = true;
            } else {
                $show_popia = true;
                $errors[] = "You must agree to the POPIA disclaimer to continue.";
            }

        } elseif ($step === 'submit_registration') {
            $show_criteria = false;
            $show_second_criteria = false;
            $show_popia = false;

            // Collect and sanitize input
            $fullname = trim($_POST['fullname']);
            $idnumber = trim($_POST['idnumber']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            $address = trim($_POST['address']);
            $province = trim($_POST['province']);
            $registered = trim($_POST['registered']);
            $level = trim($_POST['level']);
            $institution = trim($_POST['institution']);
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];

            // Validation
            if (!$fullname) $errors[] = "Full Name is required.";
            if (!$idnumber || !preg_match('/^\d{13}$/', $idnumber))
                $errors[] = "ID Number must be exactly 13 digits.";
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL))
                $errors[] = "Valid email is required.";
            if (!$phone) $errors[] = "Phone number is required.";
            if (!$address) $errors[] = "Address is required.";
            if (!$province) $errors[] = "Province is required.";
            if (!$registered) $errors[] = "Please select if you are registered at a PSET institution.";
            if (!$level) $errors[] = "Level of study is required.";
            if (!$institution) $errors[] = "Institution name is required.";
            if (!$password) $errors[] = "Password is required.";
            if ($password !== $confirm_password)
                $errors[] = "Password and Confirm Password do not match.";

            // Check if email or ID already exists
            if (empty($errors)) {
                // Email uniqueness check
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $errors[] = "A user with this email already exists.";
                }
                $stmt->close();

                // ID number uniqueness check
                $stmt = $conn->prepare("SELECT id FROM students WHERE idnumber = ?");
                $stmt->bind_param("s", $idnumber);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $errors[] = "A student with this ID number already exists.";
                }
                $stmt->close();
            }

            // If no errors, insert into database
            if (empty($errors)) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                // Insert into users table
                $stmt = $conn->prepare("INSERT INTO users (full_name, email, password) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $fullname, $email, $password_hash);

                if ($stmt->execute()) {
                    $user_id = $stmt->insert_id;
                    $stmt->close();

                    // Insert into students table (no proof_of_residence here)
                    $stmt = $conn->prepare("INSERT INTO students (fullname, idnumber, email, user_id, phone, address, province, registered, level, institution, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
                    $stmt->bind_param("ssssssssss", $fullname, $idnumber, $email, $user_id, $phone, $address, $province, $registered, $level, $institution);

                    if ($stmt->execute()) {
                        $student_id = $stmt->insert_id;
                        $stmt->close();

                        // Insert matric subjects & percentages
                        if (isset($_POST['subjects']) && isset($_POST['percentages'])) {
                            $subjects = $_POST['subjects'];
                            $percentages = $_POST['percentages'];
                            for ($i = 0; $i < count($subjects); $i++) {
                                $subject = trim($subjects[$i]);
                                $percentage = floatval($percentages[$i]);
                                if ($subject !== '' && $percentage >= 0 && $percentage <= 100) {
                                    $stmt_subject = $conn->prepare("INSERT INTO matric_subjects (student_id, subject_name, percentage) VALUES (?, ?, ?)");
                                    $stmt_subject->bind_param("isd", $student_id, $subject, $percentage);
                                    $stmt_subject->execute();
                                    $stmt_subject->close();
                                }
                            }
                        }

                        $show_success = true;
                        $success_msg = "Registration successful! You can now log in using your email and password.";
                    } else {
                        $errors[] = "Failed to register student profile. Please try again later.";
                        $show_form = true;
                        $stmt->close();
                    }
                } else {
                    $errors[] = "Failed to create user account. Please try again later.";
                    $show_form = true;
                }
            } else {
                $show_form = true;
            }
        }
    }
} elseif ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET['exit'])) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>ABC Bursary Registration</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', Arial, sans-serif;
            background: linear-gradient(120deg, #e0e7ff 0%, #f8fafc 100%);
            margin: 0;
            min-height: 100vh;
        }
        .navbar {
            background-color: #2e6da4;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(44,62,80,0.07);
        }
        .navbar a {
            color: white;
            text-decoration: none;
            margin: 0 20px;
            font-weight: bold;
            font-size: 16px;
            letter-spacing: 1px;
            transition: color 0.2s;
        }
        .navbar a:hover { color: #ffd700; }
        .container {
            width: 95%;
            max-width: 600px;
            margin: 40px auto 50px auto;
            padding: 30px 25px 25px 25px;
            background-color: white;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(44,62,80,0.13);
            animation: fadeInUp 0.9s cubic-bezier(0.23, 1, 0.32, 1);
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(60px);}
            to   { opacity: 1; transform: translateY(0);}
        }
        h2 { color: #2e6da4; text-align: center; margin-bottom: 18px; }
        .progress-bar {
            width: 100%;
            background: #e5e7eb;
            border-radius: 8px;
            margin-bottom: 25px;
            height: 10px;
            overflow: hidden;
        }
        .progress {
            height: 100%;
            background: linear-gradient(90deg, #2e6da4 60%, #ffd700 100%);
            transition: width 0.5s;
        }
        .step-labels {
            display: flex;
            justify-content: space-between;
            font-size: 0.95em;
            margin-bottom: 10px;
            color: #888;
        }
        .button-group {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        button {
            padding: 10px 22px;
            border: none;
            background-color: #2e6da4;
            color: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 500;
            transition: background 0.2s;
        }
        button:hover { background-color: #1a406b; }
        label { font-weight: 500; display: block; margin-top: 12px; }
        input, select {
            padding: 10px;
            margin-top: 5px;
            width: 100%;
            border-radius: 5px;
            border: 1px solid #cfd8dc;
            box-sizing: border-box;
            font-size: 1em;
        }
        .error {
            color: #d32f2f;
            background: #fff0f0;
            border: 1px solid #ffcdd2;
            border-radius: 6px;
            padding: 10px 15px;
            margin-bottom: 15px;
        }
        .error p { margin: 0 0 5px 0; }
        .success {
            color: #388e3c;
            background: #e8f5e9;
            border: 1px solid #a5d6a7;
            border-radius: 6px;
            padding: 12px 15px;
            margin-bottom: 18px;
            text-align: center;
        }
        .disclaimer-box {
            height: 180px;
            overflow-y: auto;
            background-color: #f9f9f9;
            border: 1px solid #ccc;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 6px;
        }
        .checkbox-row {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            margin-top: 20px;
            gap: 4px;
        }
        .checkbox-label {
            font-size: 16px;
            font-weight: normal;
        }
        .show-password {
            position: relative;
            display: flex;
            align-items: center;
        }
        .show-password input[type="checkbox"] {
            margin-left: 8px;
        }
        .tooltip {
            display: inline-block;
            position: relative;
            cursor: pointer;
            color: #2e6da4;
        }
        .tooltip .tooltiptext {
            visibility: hidden;
            width: 220px;
            background-color: #2e6da4;
            color: #fff;
            text-align: left;
            border-radius: 6px;
            padding: 8px;
            position: absolute;
            z-index: 1;
            bottom: 125%; left: 50%; transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 0.95em;
        }
        .tooltip:hover .tooltiptext { visibility: visible; opacity: 1; }
        #pwdStrengthBar { width:100%; height:7px; background:#eee; border-radius:4px; margin-top:4px; }
        #pwdStrength { height:100%; width:0; background:#e74c3c; border-radius:4px; transition:width 0.3s; }
        @media (max-width: 700px) {
            .container { padding: 15px 5px; }
        }
        @media (max-width: 500px) {
            .container { padding: 6px 2px; }
            .navbar { font-size: 14px; }
        }
    </style>
    <script>
        function submitStep(step) {
            document.getElementById('stepInput').value = step;
            document.getElementById('mainForm').submit();
        }
        function togglePopiaButton() {
            var checkbox = document.getElementById('agree');
            var btn = document.getElementById('popiaContinue');
            btn.disabled = !checkbox.checked;
            btn.style.opacity = checkbox.checked ? "1" : "0.6";
            btn.style.cursor = checkbox.checked ? "pointer" : "not-allowed";
        }
        function addRow() {
            var table = document.getElementById("subjectsTable").getElementsByTagName('tbody')[0];
            var newRow = table.insertRow();
            newRow.insertCell(0).innerHTML = '<input type="text" name="subjects[]" required>';
            newRow.insertCell(1).innerHTML = '<input type="number" name="percentages[]" min="0" max="100" required>';
            newRow.insertCell(2).innerHTML = '<button type="button" onclick="removeRow(this)">Remove</button>';
        }
        function removeRow(button) {
            var row = button.parentNode.parentNode;
            row.parentNode.removeChild(row);
        }
        function togglePassword(id) {
            var input = document.getElementById(id);
            input.type = input.type === "password" ? "text" : "password";
        }
        function updateProgress(step) {
            var progress = document.getElementById('progress');
            var widths = [0, 33, 66, 100];
            progress.style.width = widths[step] + "%";
        }
        // Example: Call this function on input change or step change
        function autoSaveForm() {
            const formData = new FormData(document.getElementById('mainForm'));
            fetch('auto_save.php', {
                method: 'POST',
                body: formData
            }).then(res => res.json())
              .then(data => {
                  // Optionally show a "saved" indicator
                  document.getElementById('autosaveMsg').innerText = 'Autosaved at ' + new Date().toLocaleTimeString();
                  setTimeout(() => {
                      document.getElementById('autosaveMsg').innerText = '';
                  }, 3000);
              });
        }
        function checkPasswordStrength() {
            var pwd = document.getElementById('password').value;
            var strengthBar = document.getElementById('pwdStrength');
            var strength = 0;
            if (pwd.length >= 8) strength++;
            if (/[A-Z]/.test(pwd)) strength++;
            if (/[0-9]/.test(pwd)) strength++;
            if (/[\W]/.test(pwd)) strength++;
            var colors = ['#e74c3c', '#f39c12', '#f1c40f', '#27ae60'];
            strengthBar.style.width = (strength * 25) + '%';
            strengthBar.style.background = colors[strength-1] || '#e74c3c';
        }
    </script>
</head>
<body>
<div class="navbar">
   <a href="index.php"><i class="fa fa-home"></i> Home</a>
    <a href="about.html"><i class="fa fa-info-circle"></i> About</a>
    <a href="contact.html"><i class="fa fa-envelope"></i> Contact</a>
    <a href="login.php"><i class="fa fa-sign-in-alt"></i> Login</a>
</div>
<div class="container">

<!-- Progress Bar and Step Labels -->
<div class="step-labels">
    <span>Criteria</span>
    <span>POPIA</span>
    <span>Registration</span>
</div>
<div class="progress-bar">
    <div class="progress" id="progress" style="width:
        <?php
        if ($show_criteria) echo '33%';
        elseif ($show_second_criteria || $show_popia) echo '66%';
        elseif ($show_form || $show_success) echo '100%';
        else echo '0%';
        ?>
    "></div>
</div>

<?php if ($show_criteria): ?>
    <h2>Application Criteria</h2>
    <ul>
        <li>A South African Citizen</li>
        <li>Youth residing in Gauteng between ages 18 and 35</li>
        <li>Matric completed and reside in Gauteng (proof of residence required at application)</li>
        <li>Top achievers from Gauteng schools also qualify</li>
        <li>Certified copy of South African ID required</li>
    </ul>
    <form id="mainForm" method="post" action="register.php">
        <input type="hidden" name="step" id="stepInput" value="criteria_next">
        <div class="button-group">
            <button type="button" onclick="submitStep('criteria_next')">Next</button>
            <button type="button" onclick="window.location.href='index.php'">Exit</button>
        </div>
    </form>

<?php elseif ($show_second_criteria): ?>
    <h2>Application Criteria - Step 2</h2>
    <ul>
        <li>Step 1: Profile (Personal, Address, Matric, Guardian Info)</li>
        <li>Step 2: Application (Tertiary Info, Sponsors, Upload Docs, Terms)</li>
            <p><strong>Step 1: Profile</strong></p>
    <ul>
        <li>Personal Details</li>
        <li>Address Details</li>
        <li>Matric Result Details</li>
        <li>Guardian Info</li>
    </ul>
    <p><strong>Step 2: Application</strong></p>
    <ul>
        <li>Tertiary Institution Info</li>
        <li>Other Sponsors</li>
        <li>Upload Required Docs</li>
        <li>Terms and Conditions</li>
    </ul>
    </ul>
    <form method="post" action="register.php">
        <input type="hidden" name="step" value="popia_agree">
        <div class="button-group">
            <button type="submit">Continue</button>
            <button type="button" onclick="window.location.href='index.php'">Exit</button>
        </div>
    </form>

<?php elseif ($show_popia): ?>
    <h2>POPIA Disclaimer</h2>
    <div class="disclaimer-box">
        <p>
            The ABC is a responsible entity for processing of personal information in compliance with POPIA. Your data will be protected and used only for bursary purposes.
        </p>
    </div>
    <?php if (!empty($errors)): ?>
        <div class="error">
            <?php foreach($errors as $error): ?>
                <p><?php echo e($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <form id="mainForm" method="post" action="register.php">
        <input type="hidden" name="step" value="popia_agree">
        <div style="display: flex; align-items: flex-start; gap: 10px; margin: 25px 0;">
            <input type="checkbox" id="agree" name="agree" value="yes" style="width:22px;height:22px;accent-color:#2e6da4;cursor:pointer;" onclick="togglePopiaButton()">
            <label for="agree" style="font-size: 1.08em; cursor:pointer; line-height:1.4; user-select: none;">
                I have read and agree to the <strong>POPIA disclaimer</strong> and bursary criteria.
            </label>
        </div>
        <div class="button-group" style="margin-top:15px;">
            <button type="submit" id="popiaContinue" disabled style="opacity:0.6;cursor:not-allowed;min-width:180px;">
                Continue to Registration
            </button>
            <button type="button" onclick="window.location.href='index.php'">Exit</button>
        </div>
    </form>
    <script>
        function togglePopiaButton() {
            var checkbox = document.getElementById('agree');
            var btn = document.getElementById('popiaContinue');
            btn.disabled = !checkbox.checked;
            btn.style.opacity = checkbox.checked ? "1" : "0.6";
            btn.style.cursor = checkbox.checked ? "pointer" : "not-allowed";
        }
    </script>
<?php elseif ($show_form): ?>
    <h2>Bursary Registration Form</h2>
    <?php if (!empty($errors)): ?>
        <div class="error">
            <?php foreach ($errors as $error): ?>
                <p><?php echo e($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <form id="mainForm" method="post" action="register.php" autocomplete="off">
        <input type="hidden" name="step" value="submit_registration">

        <label for="fullname">Full Name</label>
        <input type="text" id="fullname" name="fullname" required value="<?php echo e($fullname ?? ''); ?>" maxlength="100" placeholder="e.g. Thabo Nkosi">

        <label for="idnumber">
            ID Number (13 digits)
            <span class="tooltip">?
                <span class="tooltiptext">Enter your South African ID number (13 digits, numbers only).</span>
            </span>
        </label>
        <input type="text" id="idnumber" name="idnumber" required pattern="\d{13}" maxlength="13" title="ID Number must be exactly 13 digits" value="<?php echo e($idnumber ?? ''); ?>">

        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" required value="<?php echo e($email ?? ''); ?>" maxlength="100" placeholder="e.g. you@email.com">

        <label for="phone">Phone Number</label>
        <input type="text" id="phone" name="phone" required value="<?php echo e($phone ?? ''); ?>" maxlength="15" placeholder="e.g. 0821234567">

        <label for="address">Home Address</label>
        <input type="text" id="address" name="address" required value="<?php echo e($address ?? ''); ?>" maxlength="200" placeholder="e.g. 123 Main St, Soweto">

        <label for="province">Province</label>
        <select id="province" name="province" required>
            <option value="">Select Province</option>
            <option value="Gauteng" <?php if (($province ?? '') === "Gauteng") echo "selected"; ?>>Gauteng</option>
            <option value="Other" <?php if (($province ?? '') === "Other") echo "selected"; ?>>Other</option>
        </select>

        <label for="registered">Are you currently registered at a PSET institution?</label>
        <select id="registered" name="registered" required>
            <option value="">Select</option>
            <option value="yes" <?php if (($registered ?? '') === "yes") echo "selected"; ?>>Yes</option>
            <option value="no" <?php if (($registered ?? '') === "no") echo "selected"; ?>>No</option>
        </select>

        <label for="level">Level of Study</label>
        <select id="level" name="level" required>
            <option value="">Select</option>
            <option value="Undergraduate" <?php if (($level ?? '') === "Undergraduate") echo "selected"; ?>>Undergraduate</option>
            <option value="Postgraduate" <?php if (($level ?? '') === "Postgraduate") echo "selected"; ?>>Postgraduate</option>
        </select>

        <label for="institution">Institution Name</label>
        <input type="text" id="institution" name="institution" required value="<?php echo e($institution ?? ''); ?>" maxlength="100" placeholder="e.g. Wits University">

        <label for="password">Password</label>
        <div class="show-password">
            <input type="password" id="password" name="password" required minlength="8" maxlength="50" oninput="checkPasswordStrength()">
            <input type="checkbox" onclick="togglePassword('password')" tabindex="-1"> Show
        </div>
        <div id="pwdStrengthBar"><div id="pwdStrength"></div></div>

        <label for="confirm_password">Confirm Password</label>
        <div class="show-password">
            <input type="password" id="confirm_password" name="confirm_password" required minlength="6" maxlength="50">
            <input type="checkbox" onclick="togglePassword('confirm_password')" tabindex="-1"> Show
        </div>

        <label>
            Matric Results:
            <span class="tooltip">?
                <span class="tooltiptext">Add all your matric subjects and percentages. Click "Add Subject" for more.</span>
            </span>
        </label>
        <table id="subjectsTable" border="1" cellspacing="0" cellpadding="5" style="border-collapse:collapse;">
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Percentage (%)</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (!empty($_POST['subjects']) && !empty($_POST['percentages'])) {
                    foreach ($_POST['subjects'] as $i => $subj) {
                        $perc = $_POST['percentages'][$i] ?? '';
                        echo '<tr>';
                        echo '<td><input type="text" name="subjects[]" required value="' . e($subj) . '"></td>';
                        echo '<td><input type="number" name="percentages[]" min="0" max="100" required value="' . e($perc) . '"></td>';
                        echo '<td><button type="button" onclick="removeRow(this)">Remove</button></td>';
                        echo '</tr>';
                    }
                } else {
                    echo '<tr>
                            <td><input type="text" name="subjects[]" required></td>
                            <td><input type="number" name="percentages[]" min="0" max="100" required></td>
                            <td><button type="button" onclick="removeRow(this)">Remove</button></td>
                          </tr>';
                }
                ?>
            </tbody>
        </table>
        <br>
        <button type="button" onclick="addRow()">Add Subject</button>
        <br><br>
        <button type="submit">Create Account</button>
        <button type="button" onclick="window.location.href='index.php'">Exit</button>
    </form>

<?php elseif ($show_success): ?>
    <h2>Registration Successful!</h2>
    <div class="success"><?php echo e($success_msg); ?></div>
    <p style="text-align:center;">Please proceed to <a href="login.php">Login</a> to check your application status.</p>
<?php endif; ?>
</div>
</body>
</html>
<?php $conn->close(); ?>