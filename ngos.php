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
    error_log("ngos.php: running with mock DB.");
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
$user = null;
$user_id = 101; // Default User ID for student

// Mock/Fetch User Data 
if ($email && !$using_mock) {
    // In a real system, you would fetch user data here
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

// --- NGO OPPORTUNITY DATA ---
$ngo_opportunities = [
    [
        'id' => 401,
        'name' => 'Green Earth Environmental Fund',
        'agency' => 'Green Earth NGO',
        'fields' => 'Environmental Science, Hydrology, Conservation',
        'deadline' => '2025-11-30',
        'description' => 'Funding for projects and studies focused on sustainable development and climate change mitigation.',
        'status' => 'Open'
    ],
    [
        'id' => 402,
        'name' => 'Community Upliftment Scholarship',
        'agency' => 'Uplift Foundation',
        'fields' => 'Social Work, Education, Community Health',
        'deadline' => '2026-02-15',
        'description' => 'A grant specifically targeting students committed to working in underserved communities post-graduation.',
        'status' => 'Open'
    ],
    // ... (rest of the opportunities)
];

// --- POST HANDLER: SUBMIT APPLICATION (Updated for File Upload & Timer) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_external_application') {
    
    // Check opportunity validity
    $opportunity_id = intval($_POST['opportunity_id'] ?? 0);
    $opportunity_name = 'Unknown NGO Scheme';
    $opportunity_exists = false;
    foreach ($ngo_opportunities as $opp) {
        if ($opp['id'] === $opportunity_id) {
            $opportunity_name = $opp['name'];
            $opportunity_exists = true;
            break;
        }
    }
    if (!$opportunity_exists) {
        ajax_json(['status' => 'error', 'message' => 'Invalid opportunity selected.']);
    }

    // --- File Handling Simulation ---
    $file_upload_success = false;
    $ref_id = "NGO-{$user_id}-{$opportunity_id}-" . date('YmdHis');
    $filename = $ref_id . ".zip"; // Assuming files are zipped for submission

    if (isset($_FILES['cover_letter']) && $_FILES['cover_letter']['error'] === UPLOAD_ERR_OK &&
        isset($_FILES['matric_results']) && $_FILES['matric_results']['error'] === UPLOAD_ERR_OK) {
        
        // In a live system, files would be moved here
        $file_upload_success = true;
    } else {
        // Mock success if file validation fails in a local environment
        $file_upload_success = true;
        $mock_message = "NOTE: File uploads mocked successfully. In a live system, files would have been saved.";
    }

    if ($file_upload_success) {
        // 1. Record Submission in external_applications table
        if (!$using_mock) {
            $portal_name = 'ngos'; 
            $sql_app = "INSERT INTO external_applications (user_id, portal, opportunity_id, ref_id, status, filename) VALUES (?, ?, ?, ?, 'New', ?)";
            if ($stmt_app = $conn->prepare($sql_app)) {
                $stmt_app->bind_param('isiss', $user_id, $portal_name, $opportunity_id, $ref_id, $filename);
                $stmt_app->execute();
                $stmt_app->close();
            } else {
                 // Non-fatal error for submission history
            }

            // 2. Trigger the 3-minute Acceptance Timer in the main application
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
                // Ensure external_acceptance_time column exists in your 'applications' table
                $sql_timer = "UPDATE applications SET external_acceptance_time = ? WHERE id = ?";
                if ($stmt_timer = $conn->prepare($sql_timer)) {
                    $stmt_timer->bind_param('si', $now, $app_id);
                    $stmt_timer->execute();
                    $stmt_timer->close();
                }
            }
        }

        // Send Success Response
        $msg = "Your profile and documents have been submitted to **{$opportunity_name}** (Ref: {$ref_id}).";
        $msg .= "<br>You will receive a confirmation email shortly.";
        $msg .= "<br><br>**IMPORTANT:** Your main bursary application status on the dashboard will switch to 'Accepted' in the next 3-5 minutes, simulating the success of this external submission.";
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
// --- END: PHP LOGIC ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>NGO & Foundation Support | ABC Bursary</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* --- Portal Styles (NGO theme) --- */
:root{
    --bg:#f8fafc; --card:#ffffff; --ink:#0f172a; --muted:#64748b;
    --accent:#16a34a; /* Green/Success theme color for NGOs */
    --accent-light:#dcfce7; 
    --success:#16a34a; --warn:#f59e0b; --danger:#e11d48;
    --primary-shadow:0 10px 30px rgba(2,6,23,0.08);
}
*{box-sizing:border-box}
body{margin:0;font-family:'Inter', Poppins, system-ui, Arial;background:var(--bg);color:var(--ink);padding:26px}
.container{max-width:1000px;margin:0 auto}
.header{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:20px}
.brand h1{margin:0;font-size:1.5rem}
.small-muted{font-size:0.92rem;color:var(--muted)}
.card{background:var(--card);border-radius:16px;padding:24px;box-shadow:var(--primary-shadow);margin-bottom:18px}
.btn{display:inline-block;padding:10px 18px;border-radius:10px;background:var(--accent);color:#fff;text-decoration:none;font-weight:700;border:none;cursor:pointer;transition:background .2s}
.btn-ghost{background:transparent;color:var(--accent);border:1px solid var(--accent-light);transition:background .2s;}
.btn-ghost:hover{background:var(--accent-light);}
.opportunity-list{display:grid;gap:16px}
.opp-card{border:1px solid #e6eefc;border-radius:12px;padding:20px;display:flex;justify-content:space-between;align-items:center;transition:box-shadow .2s;background:var(--card);}
.opp-card:hover{box-shadow:0 4px 15px rgba(2,6,23,0.05);}
.opp-card h3{margin-top:0;font-size:1.1rem;color:var(--accent)}
.opp-card .meta{font-size:0.9rem;color:var(--muted);margin-bottom:10px;}
.opp-card .fields{display:inline-block;padding:4px 8px;border-radius:6px;background:var(--accent-light);color:var(--accent);font-weight:600;}
.opp-card .deadline{font-weight:700;color:var(--danger);}
.modal{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);display:flex;justify-content:center;align-items:center;z-index:1000}
.modal-content{background:var(--card);padding:30px;border-radius:18px;box-shadow:var(--primary-shadow);max-width:550px;width:90%}
textarea,input[type="text"],input[type="file"]{width:100%;padding:10px;border-radius:8px;border:1px solid #e6eefc; margin-top:5px; margin-bottom:15px;}
.file-upload-label { display: block; font-weight: 600; margin-top: 15px; }
@media (max-width: 600px) {
    .opp-card { flex-direction: column; align-items: flex-start; }
    .opp-card .actions { margin-top: 15px; width: 100%; }
    .opp-card .actions .btn { width: 100%; }
}
</style>
</head>
<body>

<div class="container">
    <div class="header">
        <div class="brand">
            <h1><i class="fa-solid fa-hands-holding-heart" style="color:var(--accent)"></i> NGO & Foundation Support</h1>
            <div class="small-muted">Direct submission portal for non-profit and charitable grants.</div>
        </div>
        <a href="alternative_opportunities.php" class="btn-ghost"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <div class="card">
        <h2 style="margin-top:0"><i class="fa-solid fa-file-invoice"></i> Submission Instructions</h2>
        <p class="small-muted">You MUST use the **Official Endorsement Letter** generated in your main dashboard and upload your **Matric Results** below.</p>
        <p class="small-muted" style="font-weight:600; color:var(--danger)">**NOTE:** Successful submission here will trigger an automatic 'Accepted' status on your main bursary application within 3-5 minutes.</p>
    </div>

    <h2 style="margin-top:30px; margin-bottom:16px;"><i class="fa-solid fa-list"></i> Available NGO Opportunities</h2>

    <div class="opportunity-list">
        <?php foreach ($ngo_opportunities as $opp): ?>
        <div class="opp-card">
            <div class="info">
                <h3><?php echo htmlspecialchars($opp['name']); ?></h3>
                <div class="meta">
                    **Foundation:** <?php echo htmlspecialchars($opp['agency']); ?> | 
                    **Deadline:** <span class="deadline"><?php echo htmlspecialchars($opp['deadline']); ?></span>
                </div>
                <div class="small-muted"><?php echo htmlspecialchars($opp['description']); ?></div>
                <div class="fields" style="margin-top:8px;"><i class="fa-solid fa-tag"></i> <?php echo htmlspecialchars($opp['fields']); ?></div>
            </div>
            <div class="actions">
                <button class="btn" onclick="showSubmissionModal(<?php echo $opp['id']; ?>, '<?php echo htmlspecialchars($opp['name'], ENT_QUOTES); ?>')"><i class="fa-solid fa-upload"></i> Submit Documents</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

</div>

<div id="submissionModal" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="header">
            <h2 style="margin-top:0"><i class="fa-solid fa-check-to-slot"></i> Final Submission: <span id="modalOppName" style="color:var(--accent)"></span></h2>
            <button class="btn-ghost" onclick="closeSubmissionModal()"><i class="fa-solid fa-xmark"></i> Close</button>
        </div>
        
        <form id="submissionForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="submit_external_application">
            <input type="hidden" name="csrf" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="opportunity_id" id="modalOppId">
            
            <label for="cover_letter" class="file-upload-label"><i class="fa-solid fa-file-pdf"></i> Upload Official Endorsement Letter (PDF):</label>
            <input type="file" name="cover_letter" id="cover_letter" accept=".pdf" required>

            <label for="matric_results" class="file-upload-label"><i class="fa-solid fa-file-pdf"></i> Upload Matric Results (PDF):</label>
            <input type="file" name="matric_results" id="matric_results" accept=".pdf" required>

            <label for="notes" class="file-upload-label">Short Motivation Note (Optional):</label>
            <textarea name="notes" id="notes" rows="3" placeholder="e.g., I have volunteered for a conservation project and this grant aligns with my passion."></textarea>

            <button type="submit" class="btn" style="width:100%;margin-top:20px"><i class="fa-solid fa-check"></i> Final Submit & Trigger Acceptance</button>
        </form>
    </div>
</div>

<div id="statusModal" class="modal" style="display:none;">
    <div class="modal-content">
        <h3 id="statusModalTitle"></h3>
        <p id="statusModalMessage"></p>
        <button class="btn" onclick="document.getElementById('statusModal').style.display='none'">OK</button>
    </div>
</div>

<script>
function showStatusModal(title, message, isSuccess) {
    document.getElementById('statusModalTitle').textContent = title;
    document.getElementById('statusModalMessage').innerHTML = message;
    const btn = document.querySelector('#statusModal .btn');
    btn.style.backgroundColor = isSuccess ? 'var(--success)' : 'var(--danger)';
    document.getElementById('statusModal').style.display = 'flex';
}

function showSubmissionModal(id, name) {
    document.getElementById('modalOppId').value = id;
    document.getElementById('modalOppName').textContent = name;
    document.getElementById('notes').value = '';
    
    // Reset file inputs 
    document.getElementById('cover_letter').value = '';
    document.getElementById('matric_results').value = '';
    
    document.getElementById('submissionModal').style.display = 'flex';
}

function closeSubmissionModal() {
    document.getElementById('submissionModal').style.display = 'none';
}

document.getElementById('submissionForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const button = document.querySelector('#submissionForm button[type="submit"]');
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';

    // Use FormData for file uploads
    const formData = new FormData(this);

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        const json = await response.json();
        const isSuccess = json.status === 'ok';

        if (isSuccess) {
            showStatusModal('Submission Complete!', json.message, true);
            closeSubmissionModal();
            // Redirect back to dashboard to start the external funnel and show countdown
            setTimeout(function(){
                window.location.href = '/abc_bursary/alternative_opportunities.php?start_external_funnel=1';
            }, 1200);
        } else {
            showStatusModal('Submission Failed', json.message || 'Unknown error. Check file selection.', false);
        }
    } catch (error) {
        showStatusModal('Error', 'Network or server error: ' + error.message, false);
    } finally {
        button.disabled = false;
        button.innerHTML = originalText;
    }
});
</script>
</body>
</html>