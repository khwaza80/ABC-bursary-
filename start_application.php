<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$email = $_SESSION['email'];
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();
$stmt->close();

$edit_mode = isset($_GET['edit']) && $_GET['edit'] == 1;
$application = null;

if ($edit_mode) {
    $stmt = $conn->prepare("SELECT * FROM applications WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $application = $result->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>GCRA Bursary Application Portal</title>
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
            background-color: #002147;
            padding: 15px;
            text-align: center;
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
            max-width: 900px;
            margin: 40px auto 50px auto;
            background-color: white;
            padding: 30px 25px 25px 25px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(44,62,80,0.13);
            animation: fadeInUp 0.9s cubic-bezier(0.23, 1, 0.32, 1);
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(60px);}
            to   { opacity: 1; transform: translateY(0);}
        }
        h2 { color: #002147; }
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
        .step { display: none; }
        .step.active { display: block; }
        .form-group { margin-top: 10px; }
        label { font-weight: 500; display: block; margin-top: 12px; }
        input, select, textarea {
            padding: 10px;
            margin-top: 5px;
            width: 100%;
            border-radius: 5px;
            border: 1px solid #cfd8dc;
            box-sizing: border-box;
            font-size: 1em;
        }
        input[type="file"] { margin-top: 10px; }
        button {
            margin-top: 20px;
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
        .doc-grid {
            display: flex;
            gap: 30px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .doc-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0 8px #ddd;
            padding: 20px;
            width: 220px;
            text-align: center;
            margin-bottom: 20px;
        }
        .doc-title {
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 8px;
        }
        .doc-status {
            font-size: 18px;
            margin-left: 5px;
        }
        .doc-desc {
            color: #444;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .doc-upload-btn {
            display: block;
            background: #0a4da2;
            color: white;
            padding: 8px 0;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .doc-upload-btn input[type="file"] { display: none; }
        .doc-upload-btn:hover { background: #0056b3; }
        .instructions {
            border: 1px solid #ccc;
            padding: 15px;
            margin-bottom: 20px;
            background-color: #f9f9f9;
        }
        @media (max-width: 700px) {
            .container { padding: 15px 5px; }
            .doc-grid { flex-direction: column; align-items: center; }
            .doc-card { width: 95%; }
        }
    </style>
    <script>
        let currentStep = 1;
        function showStep(step) {
            document.querySelectorAll('.step').forEach((div) => div.classList.remove('active'));
            document.getElementById('step' + step).classList.add('active');
            // Progress bar
            let progress = document.getElementById('progress');
            let widths = ['25%', '50%', '75%', '100%'];
            progress.style.width = widths[step-1];
        }
        function nextStep() {
            if (currentStep < 4) {
                currentStep++;
                showStep(currentStep);
            }
        }
        function prevStep() {
            if (currentStep > 1) {
                currentStep--;
                showStep(currentStep);
            }
        }
        function uploadStatus(id) {
            const input = document.getElementById(id);
            const status = document.getElementById(id + '_status');
            if (input.files.length > 0) {
                const file = input.files[0];
                if (file.size > 2 * 1024 * 1024) {
                    status.textContent = '❌ - File too large!';
                    alert("Error: The file must not exceed 2 MB. Please choose a smaller file.");
                    input.value = "";
                } else {
                    status.textContent = '✅';
                }
            } else {
                status.textContent = '❌';
            }
        }
        window.onload = function() { showStep(1); };
    </script>
</head>
<body>
<div class="navbar">
    <a href="portal_bursary.php"><i class="fa fa-home"></i> Home</a>
    <a href="profile.php"><i class="fa fa-user"></i> My Profile</a>
    <a href="start_application.php"><i class="fa fa-file-alt"></i> Apply</a>
    <a href="faq.html"><i class="fa fa-question-circle"></i> FAQ</a>
    <a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>
</div>
<div class="container">
    <div class="step-labels">
        <span>Qualification</span>
        <span>Sponsors</span>
        <span>Documents</span>
        <span>Terms</span>
    </div>
    <div class="progress-bar">
        <div class="progress" id="progress" style="width:25%"></div>
    </div>
    <form method="post" action="submit_application.php" enctype="multipart/form-data">
        <!-- Step 1 -->
        <div id="step1" class="step active">
            <h2>Step 1 of 4: Qualification Details</h2>
            <label>Student Number:</label>
            <input type="text" name="student_number" required>
            <label>Bursary Type:</label>
            <select name="bursary_type">
                <option value="">Select</option>
                <option>Top Achievers</option>
                <option>MEC/HOD Discretion</option>
                <option>Veteran Discretion</option>
                <option>Financial Assistance</option>
                <option>Top 3 in LESN schools</option>
            </select>
            <label>Year of Study:</label>
            <select name="year_of_study" required>
                <option value="">Select</option>
                <option>1st</option>
                <option>2nd</option>
                <option>3rd</option>
                <option>4th+</option>
            </select>
            <label>Academic Year:</label>
            <input type="number" name="academic_year" required>
            <label>Institution Type:</label>
            <select name="institution_type">
                <option value="">Select</option>
                <option>University</option>
                <option>College</option>
                <option>TVET</option>
                <option>University of Technology</option>
            </select>
            <label>University:</label>
            <input type="text" name="university" list="universities">
            <datalist id="universities">
                <option value="">select</option>
                <option>University of Cape Town</option>
                <option>University of Johannesburg</option>
                <option>University of Pretoria</option>
                <option>Wits University</option>
                <option>University of South Africa (UNISA)</option>
            </datalist>
            <label>Qualification Type:</label>
            <select name="qualification_type">
                <option value="">Select</option>
                <option>National Diploma</option>
                <option>Undergraduate</option>
                <option>Postgraduate</option>
            </select>
            <label>Qualification:</label>
            <select name="qualification">
                <option value="">Select</option>
                <option>National Diploma</option>
                <option>Bachelor Degree</option>
                <option>Diploma</option>
                <option>Certificate</option>
                <option>Advanced Diploma</option>
                <option>Advanced Certificate</option>
            </select>
            <label>Name of Degree/Qualification:</label>
            <input type="text" name="degree_name">
            <label>Field of Study:</label>
            <input type="text" name="field_of_study">
            <label>Qualification Duration:</label>
            <select name="qualification_duration">
                <option value="">Select</option>
                <option>1</option>
                <option>2</option>
                <option>3</option>
                <option>4</option>
                <option>5</option>
                <option>6</option>
            </select>
            <label>Motivation:</label>
            <select name="motivation">
                <option value="">Select</option>
                <option>Academic Excellence</option>
                <option>Financial Need</option>
                <option>Academic Performance</option>
                <option>Community Impact</option>
            </select>
            <label>Motivation Comments:</label>
            <textarea name="motivation_comments"></textarea>
            <button type="button" onclick="nextStep()">Next</button>
        </div>
        <!-- Step 2 -->
        <div id="step2" class="step">
            <h2>Step 2 of 4: Other Sponsors Details</h2>
            <label>Are you currently funded by any other sponsor(s)?</label>
            <select name="other_sponsor" id="other_sponsor_select" onchange="document.getElementById('otherSponsorDetails').style.display = this.value==='Yes' ? 'block' : 'none';">
                <option value="">Select</option>
                <option value="No">No</option>
                <option value="Yes">Yes</option>
            </select>
            <div id="otherSponsorDetails" style="display:none; margin-top:20px;">
                <label>Other Sponsor Name:</label>
                <input type="text" name="other_sponsor_name">
                <label>Other Sponsor Cellphone Number:</label>
                <input type="text" name="other_sponsor_cell" pattern="\d{10,}" maxlength="15" placeholder="e.g. 0821234567">
                <label>Reason for applying to us:</label>
                <textarea name="other_sponsor_reason"></textarea>
            </div>
            <button type="button" onclick="prevStep()">Back</button>
            <button type="button" onclick="nextStep()">Next</button>
        </div>
        <!-- Step 3 -->
        <div id="step3" class="step">
            <h2 style="background:#0a4da2;color:white;padding:10px;border-radius:5px;">Step 3 of 4: Document Upload</h2>
            <div class="instructions">
                <strong>Note:</strong> All documents must be PDF or image (JPG/PNG), not older than 3 months, max size 2 MB.
            </div>
            <div class="doc-grid">
                <!-- Certified ID Copy -->
                <div class="doc-card">
                    <div class="doc-title">Certified ID Copy <span class="doc-status" id="id_copy_status">❌</span></div>
                    <div class="doc-desc">Not older than 3 months</div>
                    <label class="doc-upload-btn">
                        <input type="file" id="id_copy" name="id_copy" accept=".pdf,image/*" onchange="uploadStatus('id_copy')">Upload
                    </label>
                </div>
                <!-- Certified Matric Certificate -->
                <div class="doc-card">
                    <div class="doc-title">Certified Matric Certificate <span class="doc-status" id="matric_status">❌</span></div>
                    <div class="doc-desc">Not older than 3 months</div>
                    <label class="doc-upload-btn">
                        <input type="file" id="matric" name="matric" accept=".pdf,image/*" onchange="uploadStatus('matric')">Upload
                    </label>
                </div>
                <!-- Proof of Acceptance -->
                <div class="doc-card">
                    <div class="doc-title">Proof of Acceptance <span class="doc-status" id="acceptance_status">❌</span></div>
                    <div class="doc-desc">University/College acceptance letter</div>
                    <label class="doc-upload-btn">
                        <input type="file" id="acceptance" name="acceptance" accept=".pdf,image/*" onchange="uploadStatus('acceptance')">Upload
                    </label>
                </div>
                <!-- Motivational Letter -->
                <div class="doc-card">
                    <div class="doc-title">Motivational Letter <span class="doc-status" id="motivation_letter_status">❌</span></div>
                    <div class="doc-desc"></div>
                    <label class="doc-upload-btn">
                        <input type="file" id="motivation_letter" name="motivation_letter" accept=".pdf,image/*" onchange="uploadStatus('motivation_letter')">Upload
                    </label>
                </div>
                <!-- Parent/Guardian 1 ID -->
                <div class="doc-card">
                    <div class="doc-title">Parent/Guardian 1 ID <span class="doc-status" id="parent1_id_status">❌</span></div>
                    <div class="doc-desc"></div>
                    <label class="doc-upload-btn">
                        <input type="file" id="parent1_id" name="parent1_id" accept=".pdf,image/*" onchange="uploadStatus('parent1_id')">Upload
                    </label>
                </div>
                <!-- Parent/Guardian 2 ID -->
                <div class="doc-card">
                    <div class="doc-title">Parent/Guardian 2 ID <span class="doc-status" id="parent2_id_status">❌</span></div>
                    <div class="doc-desc"></div>
                    <label class="doc-upload-btn">
                        <input type="file" id="parent2_id" name="parent2_id" accept=".pdf,image/*" onchange="uploadStatus('parent2_id')">Upload
                    </label>
                </div>
                <!-- Parent/Guardian 1 Payslip -->
                <div class="doc-card">
                    <div class="doc-title">Parent/Guardian 1 Payslip <span class="doc-status" id="payslip1_status">❌</span></div>
                    <div class="doc-desc"></div>
                    <label class="doc-upload-btn">
                        <input type="file" id="payslip1" name="payslip1" accept=".pdf,image/*" onchange="uploadStatus('payslip1')">Upload
                    </label>
                </div>
                <!-- Parent/Guardian 2 Payslip or SASSA Receipt -->
                <div class="doc-card">
                    <div class="doc-title">Parent/Guardian 2 Payslip or SASSA Receipt <span class="doc-status" id="payslip2_status">❌</span></div>
                    <div class="doc-desc"></div>
                    <label class="doc-upload-btn">
                        <input type="file" id="payslip2" name="payslip2" accept=".pdf,image/*" onchange="uploadStatus('payslip2')">Upload
                    </label>
                </div>
                <!-- Proof of Residence Upload -->
                <div class="doc-card">
                    <div class="doc-title">Proof of Residence <span class="doc-status" id="proof_of_residence_status">❌</span></div>
                    <div class="doc-desc">Attach PDF, JPG, or PNG (not older than 3 months)</div>
                    <label class="doc-upload-btn">
                        <input type="file" id="proof_of_residence" name="proof_of_residence" accept=".pdf,image/*" onchange="uploadStatus('proof_of_residence')">Upload
                    </label>
                </div>
            </div>
            <div style="text-align:center;margin-top:30px;">
                <button type="button" onclick="prevStep()" style="margin-right:20px;">&lt;&lt; Back</button>
                <button type="button" onclick="nextStep()">&gt;&gt; Next</button>
            </div>
        </div>
        <!-- Step 4 -->
        <div id="step4" class="step">
            <h2>Step 4 of 4: Terms & Conditions</h2>
            <p>You must agree to our terms and conditions to apply for the bursary.</p>
            <label><input type="checkbox" name="terms" required> I agree to the Terms & Conditions.</label>
            <br>
            <button type="button" onclick="prevStep()">Back</button>
            <button type="submit">Apply</button>
        </div>
    </form>
</div>
</body>
</html>