<?php
// filepath: c:\Users\mypc\OneDrive\Desktop\aura\htdocs\abc_bursary\submit_application.php
session_start();
require_once 'db.php';

// Security: Only allow logged-in users and POST requests
if (!isset($_SESSION['email']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit();
}

// Get user_id from session email
$email = $_SESSION['email'];
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_id = $user['id'] ?? null;
$stmt->close();

if (!$user_id) {
    $_SESSION['application_status'] = "error";
    // Redirect to the actual success page filename present in the project if it exists,
    // otherwise show a small fallback message so the user doesn't hit a 404.
    $successPage = 'submit_success_application.php';
    if (file_exists(__DIR__ . DIRECTORY_SEPARATOR . $successPage)) {
        header("Location: $successPage");
        exit();
    }
    echo "<h2>Submission received (error state)</h2><p>We couldn't find the success page. Please contact the administrator.</p>";
    exit();
}

// Get application data from POST
$student_number = $_POST['student_number'] ?? '';
$bursary_type = $_POST['bursary_type'] ?? '';
$year_of_study = $_POST['year_of_study'] ?? '';
$academic_year = $_POST['academic_year'] ?? '';
$institution_type = $_POST['institution_type'] ?? '';
$university = $_POST['university'] ?? '';
$qualification_type = $_POST['qualification_type'] ?? '';
$qualification = $_POST['qualification'] ?? '';
$degree_name = $_POST['degree_name'] ?? '';
$field_of_study = $_POST['field_of_study'] ?? '';
$qualification_duration = $_POST['qualification_duration'] ?? '';
$motivation = $_POST['motivation'] ?? '';
$motivation_comments = $_POST['motivation_comments'] ?? '';
$other_sponsor = $_POST['other_sponsor'] ?? '';

// File upload handling (add your own logic as needed)
// Example: $proof_of_residence = $_FILES['proof_of_residence'];

// Always set status to 'Processing' for new applications
$status = 'Processing';

// Insert into applications table
$stmt = $conn->prepare("INSERT INTO applications (user_id, student_number, bursary_type, year_of_study, academic_year, institution_type, university, qualification_type, qualification, degree_name, field_of_study, qualification_duration, motivation, motivation_comments, other_sponsor, status, date_applied) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
$stmt->bind_param(
    "isssssssssssssss",
    $user_id,
    $student_number,
    $bursary_type,
    $year_of_study,
    $academic_year,
    $institution_type,
    $university,
    $qualification_type,
    $qualification,
    $degree_name,
    $field_of_study,
    $qualification_duration,
    $motivation,
    $motivation_comments,
    $other_sponsor,
    $status
);
$stmt->execute();
$stmt->close();
$conn->close();

// Set a session message and redirect to the correct success page (with fallback)
$_SESSION['application_status'] = $status;
$successPage = 'submit_success_application.php';
if (file_exists(__DIR__ . DIRECTORY_SEPARATOR . $successPage)) {
    header("Location: $successPage");
    exit();
}
// Fallback: output a minimal success message so the user isn't left with a 404
echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Application Submitted</title></head><body><h2>Application Submitted</h2><p>Your application was received but the success page could not be loaded. Please <a href='portal_bursary.php'>return to the portal</a>.</p></body></html>";
exit();