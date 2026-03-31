<?php
session_start();

// If user is already logged in, redirect to portal
if (isset($_SESSION['email'])) {
    header("Location: portal_bursary.php");
    exit();
}

// Handle agreement and redirect to login_step2.php
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['agree'])) {
    $_SESSION['popia_agreed'] = true;
    header("Location: login_step2.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>ABC Bursary Login - Step 1</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', Arial, sans-serif;
            margin: 0; padding: 0;
            background: linear-gradient(120deg, #e0e7ff 0%, #f4f6fb 100%);
            min-height: 100vh;
        }
        .navbar {
            background-color: #002147;
            overflow: hidden;
            padding: 15px;
            text-align: center;
        }
        .navbar a {
            color: white;
            padding: 14px 16px;
            text-decoration: none;
            font-weight: bold;
            display: inline-block;
        }
        .navbar a:hover { background-color: #0056b3; }
        .logo {
            display: block;
            margin: 30px auto 10px auto;
            width: 80px;
        }
        .step-indicator {
            text-align: center;
            margin-bottom: 10px;
            color: #002147;
            font-weight: bold;
        }
        .container {
            background: #fff;
            max-width: 420px;
            margin: 30px auto;
            border-radius: 12px;
            box-shadow: 0 4px 24px #cfd8dc;
            padding: 32px 28px 24px 28px;
        }
        h2 { color: #002147; text-align: center; margin-bottom: 18px; }
        .disclaimer, .criteria {
            font-size: 14px;
            margin-bottom: 15px;
            max-height: 120px;
            overflow-y: auto;
            border: 1px solid #ccc;
            padding: 10px;
            background-color: #f7f9fc;
            border-radius: 6px;
        }
        .collapsible {
            background: #e0e7ff;
            color: #002147;
            cursor: pointer;
            padding: 10px;
            width: 100%;
            border: none;
            text-align: left;
            outline: none;
            font-size: 15px;
            border-radius: 6px;
            margin-bottom: 6px;
        }
        .active, .collapsible:hover { background-color: #d1d9f0; }
        .content { display: none; }
        label { display: block; margin-bottom: 10px; }
        input[type="submit"] {
            width: 100%;
            padding: 12px;
            background-color: #002147;
            color: white;
            border: none;
            cursor: pointer;
            font-weight: bold;
            border-radius: 6px;
            font-size: 1.1em;
            transition: background 0.2s, transform 0.2s;
        }
        input[type="submit"]:hover:enabled {
            background-color: #0056b3;
            transform: translateY(-2px) scale(1.03);
        }
        input[type="submit"]:disabled {
            background: #b0b8c9;
            cursor: not-allowed;
        }
        .checkbox-section { margin-bottom: 15px; }
        .help-link { text-align: right; margin-top: 10px; }
        .help-link a { color: #0056b3; font-size: 0.95em; text-decoration: none; }
        .help-link a:hover { text-decoration: underline; }
        @media (max-width: 600px) {
            .container { padding: 12px; }
        }
    </style>
    <script>
        function toggleNext() {
            const checkbox = document.getElementById('agree');
            const nextBtn = document.getElementById('nextBtn');
            nextBtn.disabled = !checkbox.checked;
        }
        document.addEventListener("DOMContentLoaded", function() {
            var coll = document.getElementsByClassName("collapsible");
            for (var i = 0; i < coll.length; i++) {
                coll[i].addEventListener("click", function() {
                    this.classList.toggle("active");
                    var content = this.nextElementSibling;
                    if (content.style.display === "block") {
                        content.style.display = "none";
                    } else {
                        content.style.display = "block";
                    }
                });
            }
        });
    </script>
</head>
<body>
<div class="navbar">
    <a href="index.php">Home</a>
    <a href="about.html">About</a>
    <a href="contact.html">Contact</a>
    <a href="login.php">Login</a>
</div>
<img src="logo.png" alt="ABC Logo" class="logo" />
<div class="container">
    <div class="step-indicator">Step 1 of 2</div>
    <h2>POPIA Disclaimer & Criteria</h2>
    <button type="button" class="collapsible">Read POPIA Disclaimer</button>
    <div class="content disclaimer">
        <p>
            ABC bursary is a responsible entity for processing of personal information 
            (such as name, surname, ID number, educational qualifications, study area, institution of studies, 
            personal address, family information, and location data amongst other things) requested from the 
            data subjects (parents and students and institutions of higher learning) in discharging its mandate 
            which is to provide and build a skilled and capable workforce in the Gauteng City Region.
        </p>
        <p>
            The personal information will be collected and used for the purpose for which it was collected. 
            The Department reaffirms its commitment that the information will not be shared with any third party 
            without the consent of the data subject.
        </p>
        <p>
            The ABC will use personal information for workforce development, student support, and financial support 
            related to educational programmes. The department will take reasonable steps to ensure the information is 
            complete, accurate, and updated where necessary.
        </p>
        <p>
            Your personal information may be shared between your chosen academic institution and the ABC. You are 
            expected to give your academic institution permission to share this information only for the purposes 
            outlined above.
        </p>
        <p><strong>For more details regarding the POPIA</strong> – <a href="#">Read More...</a></p>
    </div>
    <button type="button" class="collapsible">See Bursary Criteria</button>
    <div class="content criteria">
        <ul>
            <li>Be a South African citizen</li>
            <li>Reside in Gauteng</li>
            <li>Meet the academic requirements</li>
            <li>Be between the age of 18 and 35</li>
            <li>Not currently funded by another bursary</li>
        </ul>
    </div>
    <form method="post" action="login.php">
        <div class="checkbox-section">
            <label>
                <input type="checkbox" id="agree" name="agree" onchange="toggleNext()">
                I have read and agree to the POPIA and bursary criteria
            </label>
        </div>
        <input type="submit" id="nextBtn" value="Next" disabled>
    </form>
    <div class="help-link">
        <a href="faq.html"><i class="fa fa-question-circle"></i> Need Help?</a>
    </div>
</div>
</body>
</html>