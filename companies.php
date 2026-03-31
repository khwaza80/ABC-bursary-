<?php
// Set session start here if not already done by required files
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- START: PHP LOGIC ---
require_once 'db.php';

$using_mock = false;
if (!isset($conn) || !($conn instanceof mysqli)) {
    $using_mock = true;
    class SimpleMock {
        public function prepare($sql) { return null; }
        public function query($sql) { return false; }
        public function real_escape_string($s){ return $s; }
        public function insert_id(){ return 999; }
    }
    $conn = new SimpleMock();
    error_log("companies.php: running with mock DB.");
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(20));
}
$csrf_token = $_SESSION['csrf_token'];

function ajax_json($arr) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr);
    exit;
}

$email = $_SESSION['email'] ?? null;
// Prefer the logged-in user id from the session; fall back to a safe default for local testing
$user = null;
$user_id = $_SESSION['user_id'] ?? 101; // Use session user id when available

// Mock/Fetch User Data
if ($email && !$using_mock) {
    $user = [
        'id' => $user_id,
        'full_name' => 'Demo Student',
        'email' => $email,
        'role' => 'student'
    ];
} else {
    $user = [
        'id' => $user_id,
        'full_name' => 'Demo Student',
        'email' => $email ?? 'testuser@example.com',
        'role' => 'student'
    ];
}

// Security Check: Only students can use this portal
if ($user['role'] !== 'student') {
    header("Location: alternative_opportunities.php"); 
    exit;
}

// --- COMPANIES OPPORTUNITY DATA (sample) ---
$company_opportunities = [
    [
        'id' => 301,
        'name' => 'Acme Corp Student Sponsorship',
        'agency' => 'Acme Corporation',
        'fields' => 'Engineering, Computer Science',
        'deadline' => '2026-03-31',
        'description' => 'Corporate sponsorship with internship placement.',
        'status' => 'Open'
    ],
    [
        'id' => 302,
        'name' => 'TechWorks Graduate Bursary',
        'agency' => 'TechWorks Ltd',
        'fields' => 'IT, Software Engineering',
        'deadline' => '2026-02-28',
        'description' => 'Support for students pursuing tech degrees with mentorship.',
        'status' => 'Open'
    ],
];

// --- POST HANDLER: SUBMIT APPLICATION (Updated for File Upload & Timer) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_external_application') {
    $opportunity_id = intval($_POST['opportunity_id'] ?? 0);
    $opportunity_name = 'Unknown Company Scheme';
    $opportunity_exists = false;
    foreach ($company_opportunities as $opp) {
        if ($opp['id'] === $opportunity_id) {
            $opportunity_name = $opp['name'];
            $opportunity_exists = true;
            break;
        }
    }
    if (!$opportunity_exists) {
        ajax_json(['status' => 'error', 'message' => 'Invalid opportunity selected.']);
    }

    // File handling simulation
    $file_upload_success = false;
    $ref_id = "COMP-{$user_id}-{$opportunity_id}-" . date('YmdHis');
    $filename = $ref_id . ".zip";

    if (isset($_FILES['cover_letter']) && $_FILES['cover_letter']['error'] === UPLOAD_ERR_OK &&
        isset($_FILES['matric_results']) && $_FILES['matric_results']['error'] === UPLOAD_ERR_OK) {
        $file_upload_success = true;
    } else {
        $file_upload_success = true;
        $mock_message = "NOTE: File uploads mocked successfully. In a live system, files would have been saved.";
    }

    if ($file_upload_success) {
        if (!$using_mock) {
            $portal_name = 'companies'; 
            $sql_app = "INSERT INTO external_applications (user_id, portal, opportunity_id, ref_id, status, filename) VALUES (?, ?, ?, ?, 'New', ?)";
            if ($stmt_app = $conn->prepare($sql_app)) {
                $stmt_app->bind_param('isiss', $user_id, $portal_name, $opportunity_id, $ref_id, $filename);
                $stmt_app->execute();
                $stmt_app->close();
            }

            // Trigger external_acceptance_time on main application
            $app_id = null;
            $sql_find_app = "SELECT id FROM applications WHERE user_id = ? ORDER BY date_applied DESC LIMIT 1";
            if ($stmt_find = $conn->prepare($sql_find_app)) {
                $stmt_find->bind_param('i', $user_id);
                $stmt_find->execute();
                $res = $stmt_find->get_result();
                if ($row = $res->fetch_assoc()) {
                    $app_id = $row['id'];
                }
                $stmt_find->close();
            }

            if ($app_id) {
                $now = date('Y-m-d H:i:s');
                $sql_timer = "UPDATE applications SET external_acceptance_time = ? WHERE id = ?";
                if ($stmt_timer = $conn->prepare($sql_timer)) {
                    $stmt_timer->bind_param('si', $now, $app_id);
                    $stmt_timer->execute();
                    $stmt_timer->close();
                }
            }
        }

        $msg = "Your profile and documents have been submitted to **{$opportunity_name}** (Ref: {$ref_id}).";
        $msg .= "<br>You will receive a confirmation email shortly.";
        $msg .= "<br><br>**IMPORTANT:** Your main bursary application status on the dashboard will switch to 'Accepted' in the next few minutes.";
        if (isset($mock_message)) $msg .= "<br><br><span style='color:red;'>({$mock_message})</span>";
        ajax_json([
            'status' => 'ok',
            'message' => $msg,
            'ref_id' => $ref_id
        ]);
    } else {
        ajax_json(['status' => 'error', 'message' => 'File upload failed. Please ensure both PDF files were selected.']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Company Sponsorships | ABC Bursary</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{ --bg:#f8fafc; --card:#ffffff; --ink:#0f172a; --muted:#64748b; --accent:#2563eb; }
*{box-sizing:border-box}
body{margin:0;font-family:'Inter', Poppins, system-ui, Arial;background:var(--bg);color:var(--ink);padding:26px}
.container{max-width:1000px;margin:0 auto}
.card{background:var(--card);border-radius:16px;padding:24px;box-shadow:0 10px 30px rgba(2,6,23,0.08);margin-bottom:18px}
.btn{display:inline-block;padding:10px 18px;border-radius:10px;background:var(--accent);color:#fff;text-decoration:none;font-weight:700;border:none;cursor:pointer}
</style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1>Company Sponsorships</h1>
        <p class="small-muted">Submit your endorsement and results to private sponsors.</p>
    </div>

    <div class="card">
        <?php foreach ($company_opportunities as $opp): ?>
            <div style="margin-bottom:16px;">
                <h3><?php echo htmlspecialchars($opp['name']); ?></h3>
                <div class="small-muted"><?php echo htmlspecialchars($opp['description']); ?></div>
                <button class="btn" onclick="openCompanyModal(<?php echo $opp['id']; ?>, '<?php echo htmlspecialchars($opp['name'], ENT_QUOTES); ?>')">Submit Documents</button>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="companyModal" style="display:none;position:fixed;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.6);justify-content:center;align-items:center;">
    <div style="background:#fff;padding:20px;border-radius:10px;max-width:600px;margin:40px auto;">
        <form id="companyForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="submit_external_application">
            <input type="hidden" name="csrf" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="opportunity_id" id="companyOppId">
            <label>Upload Official Endorsement Letter (PDF)</label>
            <input type="file" name="cover_letter" accept=".pdf" required>
            <label>Upload Matric Results (PDF)</label>
            <input type="file" name="matric_results" accept=".pdf" required>
            <button type="submit" class="btn" style="margin-top:12px;">Submit & Trigger Acceptance</button>
            <button type="button" onclick="closeCompanyModal()" style="margin-left:8px;" class="btn">Cancel</button>
        </form>
    </div>
</div>

<script>
function openCompanyModal(id, name) {
    document.getElementById('companyOppId').value = id;
    document.getElementById('companyModal').style.display = 'flex';
}
function closeCompanyModal() {
    document.getElementById('companyModal').style.display = 'none';
}

document.getElementById('companyForm')?.addEventListener('submit', async function(e){
    e.preventDefault();
    const formData = new FormData(this);
    const btn = this.querySelector('button[type="submit"]');
    const orig = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
    try {
        const res = await fetch(window.location.href, { method: 'POST', body: formData });
        const json = await res.json();
        if (json.status === 'ok') {
            alert('Submission complete. Redirecting to dashboard...');
            // Redirect to start funnel on dashboard
            setTimeout(function(){ window.location.href = '/abc_bursary/alternative_opportunities.php?start_external_funnel=1'; }, 800);
        } else {
            alert('Submission failed: ' + (json.message||'Unknown'));
        }
    } catch(err) {
        alert('Error: '+err.message);
    } finally { btn.disabled = false; btn.innerHTML = orig; closeCompanyModal(); }
});
</script>
</body>
</html>
