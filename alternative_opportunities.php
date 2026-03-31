<?php
// Set session start here
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- START: PHP LOGIC AND CONFIGURATION ---
require_once 'db.php';

$using_mock = false;
if (!isset($conn) || !($conn instanceof mysqli)) {
    $using_mock = true;
    error_log("DB CONNECTION FAILED. RUNNING IN MOCK MODE.");
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

// --- CORE DATA FETCHING FUNCTION ---
function fetch_application_data($conn, $user_id, $using_mock) {
    if ($using_mock) {
        // MOCK DATA: Simulates a rejected application
        return [
            'id' => 500, 'user_id' => $user_id, 'status' => 'Rejected', 
            'field_of_study' => 'Engineering', 'university' => 'WITS', 'degree_name' => 'BSc Eng',
            'year_of_study' => '2nd', 'average_percentage' => '78.5', 
            'external_acceptance_time' => null 
        ];
    }
    
    $sql = "SELECT a.id, a.user_id, a.status, a.field_of_study, a.university, a.degree_name, a.year_of_study, a.motivation, s.average_percentage, a.external_acceptance_time 
            FROM applications a 
            JOIN students s ON a.user_id = s.user_id 
            WHERE a.user_id = ? ORDER BY a.date_applied DESC LIMIT 1";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $data = $res->fetch_assoc();
        $stmt->close();
        return $data;
    }
    return null;
}

// --- USER IDENTIFICATION ---
$user_id = $_SESSION['user_id'] ?? 101; 
$email = $_SESSION['email'] ?? 'testuser@example.com';
$user = ['id' => $user_id, 'full_name' => $_SESSION['full_name'] ?? 'Demo Student', 'email' => $email, 'role' => 'student'];

// Initial application fetch
$application = fetch_application_data($conn, $user_id, $using_mock);
if (!$application) {
    $application = fetch_application_data($conn, $user_id, true); 
}

$current_time = time();
$is_rejected = (isset($application['status']) && $application['status'] === 'Rejected');
$is_accepted = (isset($application['status']) && $application['status'] === 'Accepted');

// --- STATUS AND AUTO-ACCEPTANCE LOGIC ---
$external_acceptance_time_str = $application['external_acceptance_time'] ?? null;
$external_acceptance_time = strtotime($external_acceptance_time_str ?? 0);
$acceptance_delay_seconds = 120; // **2 MINUTES**

// Check for pending external acceptance: Rejected AND time is set AND time is less than 2 minutes old
$is_pending_external = ($is_rejected && $external_acceptance_time_str !== null && $external_acceptance_time !== false && ($current_time - $external_acceptance_time) < $acceptance_delay_seconds);

// AUTO-ACCEPTANCE: If the timer has expired, change status
if ($is_rejected && $external_acceptance_time !== false && ($current_time - $external_acceptance_time) >= $acceptance_delay_seconds) {
    
    // 1. Change status internally for this page render
    $application['status'] = 'Accepted';
    $is_rejected = false;
    $is_accepted = true;

    // 2. Update status in the database (Only if not mocking)
    if (!$using_mock) {
        $update_sql = "UPDATE applications SET status = 'Accepted', admin_note = 'Auto-Accepted via External Rescue Funnel' WHERE id = ?";
        if ($stmt = $conn->prepare($update_sql)) {
            $stmt->bind_param('i', $application['id']);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// --- POST HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'generate_cover_letter') {
        // (PDF content generation logic here - kept for PDF function)
        $full_name = $user['full_name'] ?? 'Applicant';
        $inst = $application['university'] ?? 'N/A';
        $field = $application['field_of_study'] ?? 'N/A';
        $degree = $application['degree_name'] ?? 'BSc Degree';
        $year = $application['year_of_study'] ?? '1st';
        $percentage = $application['average_percentage'] ?? 'N/A';

        $pdf_content = [];
        $pdf_content[] = "OFFICIAL LETTER OF ENDORSEMENT AND SUPPORT";
        $pdf_content[] = "---";
        $pdf_content[] = "Date: " . date("F d, Y");
        $pdf_content[] = "To Whom It May Concern (External Funding Partner)";
        $pdf_content[] = "Subject: Urgent Endorsement for Bursary Applicant, " . $full_name;
        $pdf_content[] = "---";
        $pdf_content[] = "This letter confirms that " . $full_name . " is a highly capable student registered for the " . $year . " Year " . $degree . " in " . $field . " at " . $inst . ".";
        $pdf_content[] = "While their application to the ABC Bursary was unfortunately categorized as 'Rejected' due to strict funding limitations, our internal review confirms this student is of exceptional quality.";
        $pdf_content[] = "Their latest academic records show a strong average percentage of " . $percentage . "%.";
        $pdf_content[] = "We strongly recommend " . $full_name . " for consideration.";
        $pdf_content[] = "---";
        $pdf_content[] = "Sincerely,";
        $pdf_content[] = "The ABC Bursary Administration Team";
        
        ajax_json(['status'=>'ok','letter'=>implode("\n",$pdf_content)]);
    }

    // *** RE-INTRODUCED: Action to set timestamp and start the 2-minute auto-accept timer ***
    if ($action === 'start_external_funnel') {
        if ($is_rejected && !$using_mock) {
            $update_sql = "UPDATE applications SET external_acceptance_time = NOW() WHERE id = ?";
            if ($stmt = $conn->prepare($update_sql)) {
                $stmt->bind_param('i', $application['id']);
                $stmt->execute();
                $stmt->close();
                
                // Re-fetch data to reflect the new timestamp
                $application = fetch_application_data($conn, $user_id, false); 
                
                ajax_json(['status'=>'ok', 'message'=>'External funnel started. Timer set.']);
            } else {
                 ajax_json(['status'=>'error', 'message'=>'Database error starting funnel.']);
            }
        }
        ajax_json(['status'=>'ok', 'message'=>'External funnel started. Timer set.']);
    }
}
// --- GET handler: allow portals to redirect back to start the external funnel ---
// Example: /alternative_opportunities.php?start_external_funnel=1
if (isset($_GET['start_external_funnel']) && $_GET['start_external_funnel'] == '1') {
    // Only start if application is rejected
    if ($is_rejected) {
        if (!$using_mock) {
            $update_sql = "UPDATE applications SET external_acceptance_time = NOW() WHERE id = ?";
            if ($stmt = $conn->prepare($update_sql)) {
                $stmt->bind_param('i', $application['id']);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            // In mock mode, set the value so the UI shows pending
            $application['external_acceptance_time'] = date('Y-m-d H:i:s');
            $external_acceptance_time = time();
        }
    }
    // Redirect to remove the query param and show the pending UI
    header('Location: alternative_opportunities.php');
    exit();
}
// --- END: PHP LOGIC ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Alternative Opportunities Dashboard | ABC Bursary</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<style>
/* ... (CSS Styles are omitted for brevity, ensure they are in your file) ... */
:root{
    --bg:#f8fafc; --card:#ffffff; --ink:#0f172a; --muted:#64748b;
    --accent:#2563eb; --accent-light:#eef2ff;
    --success:#16a34a; --success-light:#dcfce7;
    --warn:#f59e0b; --warn-light:#fef9c3;
    --danger:#e11d48; --danger-light:#fee2e2;
    --primary-shadow:0 10px 30px rgba(2,6,23,0.08);
    --secondary-shadow:0 4px 12px rgba(2,6,23,0.04);
}
*{box-sizing:border-box}
body{margin:0;font-family:'Inter', Poppins, system-ui, Arial;background:var(--bg);color:var(--ink);padding:26px}
.container{max-width:1200px;margin:0 auto}
.card{background:var(--card);border-radius:16px;padding:24px;box-shadow:var(--primary-shadow);margin-bottom:18px}
.header{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:10px}
.brand h1{margin:0;font-size:1.5rem}
.status-badge{font-weight:700;padding:8px 14px;border-radius:10px;display:inline-flex;align-items:center;gap:8px;}
.status-accepted{background:var(--success-light);color:var(--success);} 
.status-processing,.status-pending{background:var(--warn-light);color:var(--warn);} 
.status-rejected{background:var(--danger-light);color:var(--danger);}
.small-muted{font-size:0.92rem;color:var(--muted)}
.hero{display:flex;align-items:start;gap:20px;flex-wrap:wrap}
.hero .left{flex:2;min-width:300px}
.hero .right{flex:1;min-width:260px;text-align:right}
.progress{height:12px;background:#eef5ff;border-radius:999px;overflow:hidden;margin-top:8px}
.progress > i{display:block;height:100%;background:linear-gradient(90deg,#60a5fa,#2563eb);width:15%}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px}
.action{background:var(--card);padding:20px;border-radius:12px;min-height:160px;display:flex;flex-direction:column;justify-content:space-between;border:1px solid #e6eefc;transition:all .2s;cursor:pointer;box-shadow:var(--secondary-shadow);}
.action:hover{transform:translateY(-4px);box-shadow:var(--primary-shadow);}
.icon{font-size:26px;color:var(--accent)}
.action h3{margin:8px 0 6px;font-size:1.1rem;color:var(--ink)}
.small{font-size:0.95rem;color:var(--muted)}
.btn{display:inline-block;padding:10px 18px;border-radius:10px;background:var(--accent);color:#fff;text-decoration:none;font-weight:700;border:none;cursor:pointer;transition:background .2s}
.btn:hover{background:#1d4ed8}
.badge{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;font-weight:600;font-size:0.85rem;}
.action.rescue-cta{background:var(--danger-light);border-color:var(--danger);box-shadow:0 0 0 3px var(--danger-light);min-height:auto}
.action.rescue-cta .icon{color:var(--danger)}
.action.rescue-cta .badge{background:var(--danger);color:#fff}
.status-external-pending { background: #fef3c7; color: #d97706; }
.modal{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);display:flex;justify-content:center;align-items:center;z-index:1000}
.modal-content{background:var(--card);padding:30px;border-radius:18px;box-shadow:var(--primary-shadow);max-width:550px;width:90%;}
.btn-ghost { background: transparent; color: var(--ink); border: 1px solid var(--muted); padding: 8px 12px; border-radius: 8px; cursor: pointer;}
#coverLetterText { 
    white-space: pre-wrap; 
    font-family: monospace; 
    background: #f1f5f9; 
    padding: 15px; 
    border-radius: 8px; 
    border: 1px solid #e2e8f0;
    overflow-x: auto;
}
</style>
</head>
<body>

<div class="container">
    <div class="header">
        <div class="brand">
            <h1><i class="fa-solid fa-layer-group" style="color:var(--accent)"></i> Alternative Opportunities</h1>
            <div class="small-muted">Navigate support pathways outside the main bursary cycle.</div>
        </div>
        <div class="user-info">
            <span class="small-muted">Welcome, **<?php echo htmlspecialchars($user['full_name'] ?? 'Guest'); ?>**</span>
        </div>
    </div>

    <?php if ($application): ?>
    
    <div class="hero card">
        <div class="left">
            <h2 style="margin-top:0;margin-bottom:10px"><i class="fa-solid fa-graduation-cap"></i> Your Main Application</h2>

            <?php 
            $status_class = strtolower($application['status'] ?? 'pending');
            $status_text = htmlspecialchars($application['status'] ?? 'Pending');
            $progress_width = $is_accepted ? '100%' : ($is_rejected ? '100%' : '50%');
            $progress_color = $is_accepted ? 'var(--success)' : ($is_rejected ? 'var(--danger)' : 'var(--accent)');
            
            if ($is_pending_external) {
                $status_class = 'external-pending';
                $status_text = 'Processing Rescue Acceptance...';
                $progress_width = '85%';
                $progress_color = 'var(--warn)';
            } 
            ?>
            <div class="status-badge status-<?php echo $status_class; ?>">
                Application Status: **<?php echo $status_text; ?>**
            </div>
            
            <?php if ($is_pending_external): ?>
                <?php 
                    $time_left = max(0, ($external_acceptance_time + $acceptance_delay_seconds) - $current_time);
                    $init_minutes = floor($time_left / 60);
                    $init_seconds = $time_left % 60;
                    $init_mmss = sprintf('%02d:%02d', $init_minutes, $init_seconds);
                ?>
                <p style="font-size:0.9rem; color:var(--warn); font-weight:600; margin-top:10px;">
                    <i class="fa-solid fa-hourglass-half"></i>
                    Status update pending: Your rescue attempt is being reviewed. Status will update to <strong>'Accepted'</strong> in approximately <strong id="countdownPretty"><?php echo $init_mmss; ?></strong>.
                </p>
            <?php elseif ($is_rejected): ?>
                <p style="font-size:0.9rem; color:var(--danger); font-weight:600; margin-top:10px;">
                    <i class="fa-solid fa-triangle-exclamation"></i> Your main application was rejected due to high demand. Please use the portals below to secure external funding.
                </p>
            <?php endif; ?>
            
            <div class="small-muted" style="margin-top:10px">
                **Field:** <?php echo htmlspecialchars($application['field_of_study'] ?? 'N/A'); ?> &bull; **University:** <?php echo htmlspecialchars($application['university'] ?? 'N/A'); ?>.
                <br>**Average Academic Score:** **<?php echo htmlspecialchars($application['average_percentage'] ?? 'N/A'); ?>%**
            </div>
            <div class="progress"><i style="width:<?php echo $progress_width; ?>; background:<?php echo $progress_color; ?>"></i></div>
        </div>
        <div class="right">
            </div>
    </div>
    
    <h2 style="margin-top:30px; margin-bottom:16px;"><i class="fa-solid fa-table-cells"></i> External Portals & Tools</h2>
    <div class="grid" id="portalGrid">
        
        <?php if (!$is_accepted): ?>
        <div class="action rescue-cta" onclick="showCoverLetterModal()">
            <div>
                <i class="icon fa-solid fa-file-signature" style="color:var(--danger)"></i>
                <h3>Generate Endorsement Letter (Mandatory)</h3>
                <p class="small" style="color:var(--danger); font-weight:600;">**FIRST STEP!** Generate the official PDF endorsement needed for all external applications.</p>
            </div>
            <div class="badge">Generate Letter <i class="fa-solid fa-arrow-right"></i></div>
        </div>
        <?php endif; ?>
        
        <div class="action" id="ngoPortal" onclick="window.location.href='ngos.php'">
            <div>
                <i class="icon fa-solid fa-hands-holding-heart" style="color:var(--success)"></i>
                <h3>NGO & Foundation Support</h3>
                <p class="small">Submit your profile and endorsement letter to non-profit partners.</p>
            </div>
            <div class="badge" style="background:var(--success-light); color:var(--success);">Go to Portal <i class="fa-solid fa-arrow-right"></i></div>
        </div>

        <div class="action" id="corpPortal" onclick="window.location.href='companies.php'">
            <div>
                <i class="icon fa-solid fa-building" style="color:var(--accent)"></i>
                <h3>Corporate Bursary Schemes</h3>
                <p class="small">Access private sector internships, jobs, and funding opportunities.</p>
            </div>
            <div class="badge" style="background:var(--accent-light); color:var(--accent);">Go to Portal <i class="fa-solid fa-arrow-right"></i></div>
        </div>
        
        <div class="action" id="govPortal" onclick="window.location.href='government.php'">
            <div>
                <i class="icon fa-solid fa-scale-balanced" style="color:#64748b"></i>
                <h3>Government Funding Schemes</h3>
                <p class="small">Access public sector bursary and funding opportunities.</p>
            </div>
            <div class="badge" style="background:#eef2ff; color:#64748b;">Go to Portal <i class="fa-solid fa-arrow-right"></i></div>
        </div>
        
    </div>
    
    <div class="card" style="margin-top:30px;">
        <h2 style="margin-top:0"><i class="fa-solid fa-clock-rotate-left"></i> External Application History</h2>
        <div class="small-muted">No external application history found.</div>
    </div>

    <?php endif; ?>

</div>

<div id="coverLetterModal" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="header">
            <h2 style="margin-top:0"><i class="fa-solid fa-file-signature"></i> Official Endorsement Letter</h2>
            <button class="btn-ghost" onclick="closeCoverLetterModal()"><i class="fa-solid fa-xmark"></i> Close</button>
        </div>
        <p class="small">This letter acts as an **official endorsement** from the ABC Bursary, explaining your excellent performance and our funding constraints. **You must save this as a PDF.**</p>
        <div id="coverLetterText" style="min-height:200px">
            <i class="fa-solid fa-spinner fa-spin"></i> Generating...
        </div>
        <div style="margin-top:15px; display:flex; gap:10px;">
            <button id="downloadPdfBtn" class="btn" onclick="downloadCoverLetterPdf()"><i class="fa-solid fa-file-pdf"></i> Download Official PDF</button>
            <button id="copyBtn" class="btn btn-ghost" onclick="copyCoverLetter()"><i class="fa-solid fa-copy"></i> Copy Text</button>
        </div>
        <p class="small-muted" style="margin-top:10px;">
            **Next Step:** Once downloaded, proceed to the portals (NGOs, Companies, Gov't) to submit this letter along with your Matric results.
        </p>
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
// Standard Functions (Unchanged)
function showStatusModal(title, message, isSuccess = true) {
    document.getElementById('statusModalTitle').textContent = title;
    document.getElementById('statusModalMessage').innerHTML = message;
    const btn = document.querySelector('#statusModal .btn');
    btn.style.backgroundColor = isSuccess ? 'var(--success)' : 'var(--danger)';
    document.getElementById('statusModal').style.display = 'flex';
}
function showCoverLetterModal() {
    document.getElementById('coverLetterModal').style.display = 'flex';
    generateCoverLetter(); 
}
function closeCoverLetterModal() {
    document.getElementById('coverLetterModal').style.display = 'none';
}
async function generateCoverLetter() {
    const letterBox = document.getElementById('coverLetterText');
    letterBox.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Generating official endorsement...';
    const csrf = '<?php echo $csrf_token; ?>';

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({ action: 'generate_cover_letter', csrf: csrf })
        });
        const json = await response.json();
        if (json.status === 'ok') {
            letterBox.textContent = json.letter;
        } else {
            letterBox.textContent = 'Error generating letter: ' + (json.message || 'Unknown error.');
        }
    } catch (error) {
        letterBox.textContent = 'Network Error: Could not reach server.';
    }
}

// *** MODIFIED downloadCoverLetterPdf: Downloads PDF, sets timestamp on server, and reloads to start timer. ***
async function downloadCoverLetterPdf() {
    const text = document.getElementById('coverLetterText').textContent;
    const csrf = '<?php echo $csrf_token; ?>';
    
    // 1. GENERATE VALID PDF USING jsPDF
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    const lines = text.split('\n');
    let y = 15; 

    doc.setFontSize(10);
    lines.forEach(line => {
        const splitText = doc.splitTextToSize(line, 180);
        doc.text(splitText, 15, y);
        y += (splitText.length * 5); 
    });

    // Save the PDF file
    doc.save('ABC_Bursary_Endorsement_<?php echo $user['id']; ?>.pdf');
    
    // 2. Start the timer on the server
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({ action: 'start_external_funnel', csrf: csrf }) 
        });
        const json = await response.json();
        
        if (json.status === 'ok') {
            closeCoverLetterModal();
            showStatusModal('Success! Timer Started.', 'Your Endorsement Letter is downloaded. The system has started the 2-minute review timer. **The dashboard will show "Processing Rescue Acceptance..."** and then change to "Accepted".', true);
            
            // Force reload to show the new status and start the timer countdown
            setTimeout(() => {
                window.location.reload(true); 
            }, 1500); 
            
        } else {
            showStatusModal('Warning', 'Letter downloaded, but the timer failed to start. Error: ' + json.message, false);
        }
    } catch (error) {
        showStatusModal('Error', 'Letter downloaded, but the timer failed due to network error.', false);
    }
}

function copyCoverLetter() {
    const text = document.getElementById('coverLetterText').textContent;
    navigator.clipboard.writeText(text).then(() => {
        showStatusModal('Success!', 'Cover letter text copied to clipboard.');
    }).catch(err => {
        showStatusModal('Copy Failed', 'Could not copy text automatically. Please select and copy manually.', false);
    });
}

function startStatusWatcher() {
    // This is optional, but allows the status badge to update automatically without a manual reload after 2 minutes
    <?php if ($is_pending_external): ?>
    const acceptanceTime = <?php echo $external_acceptance_time ?? 0; ?>;
    const delaySeconds = <?php echo $acceptance_delay_seconds; ?>;
    const completionTime = acceptanceTime + delaySeconds;

    function pad2(n){ return String(n).padStart(2,'0'); }

    function tickExternalCountdown(){
        const currentTime = Math.floor(Date.now() / 1000);
        const timeLeft = completionTime - currentTime;
        const countdownEl = document.getElementById('countdownPretty');
        if (!countdownEl) return;
        if (timeLeft <= 0) {
            countdownEl.textContent = '00:00';
            // brief delay so user sees 00:00, then reload to pick up server-side Accepted status
            setTimeout(function(){ window.location.reload(true); }, 400);
            return;
        }
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        countdownEl.textContent = pad2(minutes) + ':' + pad2(seconds);
    }

    // Kick off immediately and then every second
    tickExternalCountdown();
    setInterval(tickExternalCountdown, 1000);
    <?php endif; ?>
}

window.onload = startStatusWatcher;
</script>
</body>
</html>