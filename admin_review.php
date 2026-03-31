<?php
session_start();
require_once 'db.php';

// --- Config ---
$ACCEPT_THRESHOLD = 75; // minimum average % to enable Accept
date_default_timezone_set('Africa/Johannesburg');

// --- Aggregate counts ---
$total = $conn->query("SELECT COUNT(*) FROM applications")->fetch_row()[0];
$accepted = $conn->query("SELECT COUNT(*) FROM applications WHERE status IN ('Accepted','Approved')")->fetch_row()[0];
$rejected = $conn->query("SELECT COUNT(*) FROM applications WHERE status='Rejected'")->fetch_row()[0];
$processing = $conn->query("SELECT COUNT(*) FROM applications WHERE status IN ('Processing','Pending')")->fetch_row()[0];

// --- Fetch all applications (latest first) ---
$apps_rs = $conn->query("SELECT * FROM applications ORDER BY date_applied DESC");

// Helper: fetch student details by user_id
function fetch_student_details($conn, $user_id) {
    $stmt = $conn->prepare("SELECT * FROM students WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $data = $res->fetch_assoc();
    $stmt->close();
    return $data ?: null;
}

// Helper: matric average by student_id
function fetch_matric_average($conn, $student_id) {
    if (!$student_id) return 0;
    $stmt = $conn->prepare("SELECT percentage FROM matric_subjects WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $arr = [];
    while ($row = $res->fetch_assoc()) $arr[] = (float)$row['percentage'];
    $stmt->close();
    return count($arr) ? round(array_sum($arr) / count($arr), 2) : 0;
}

// Build enriched arrays for each status
$rows_all = [];
$rows_processing = [];
$rows_accepted = [];
$rows_rejected = [];

while ($application = $apps_rs->fetch_assoc()) {
    $user_id = (int)$application['user_id'];
    $student = fetch_student_details($conn, $user_id);
    $student_id = $student ? (int)$student['id'] : null;
    $avg = fetch_matric_average($conn, $student_id);

    // quick "merit score" (avg + tiny bonus for motivation length)
    $motBonus = min(strlen($application['motivation']) / 500, 5.0); // cap +5
    $meritScore = round($avg + $motBonus, 2);

    $enriched = [
        'app' => $application,
        'student' => $student,
        'avg' => $avg,
        'merit' => $meritScore
    ];

    $rows_all[] = $enriched;

    $s = $application['status'];
    if (in_array($s, ['Accepted','Approved'])) $rows_accepted[] = $enriched;
    elseif ($s === 'Rejected') $rows_rejected[] = $enriched;
    else $rows_processing[] = $enriched; // Pending / Processing / other
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin — Review Applications</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Icons + Charts + Confetti -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <style>
        :root{
            --primary:#2e6da4; --bg:#f4f6fb; --card:#ffffff; --muted:#7a8aa0; --success:#27ae60; --warn:#f39c12; --error:#e74c3c; --ink:#0f172a;
        }
        *{box-sizing:border-box}
        body{margin:0;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:linear-gradient(120deg,#eef2ff,#f9fafb);}
        .container{max-width:1200px;margin:26px auto;padding:0 16px;}
        .header{
            display:flex;gap:16px;align-items:center;justify-content:space-between;margin-bottom:16px;
        }
        .title{font-size:24px;color:var(--ink);font-weight:800;letter-spacing:.2px}
        .pill{background:#e7effd;color:#1f3a8a;padding:6px 10px;border-radius:999px;font-weight:600;font-size:12px}
        .grid{display:grid;grid-template-columns:repeat(12,1fr);gap:16px}
        .card{background:var(--card);border-radius:16px;box-shadow:0 8px 28px rgba(15,23,42,.06);padding:18px}
        .kpi{display:flex;align-items:center;gap:12px}
        .kpi .icon{width:38px;height:38px;border-radius:10px;display:grid;place-items:center;color:#fff}
        .kpi .meta{display:flex;flex-direction:column}
        .kpi .meta .label{font-size:12px;color:var(--muted)}
        .kpi .meta .value{font-size:20px;font-weight:800;color:var(--ink)}
        .toolbar{display:flex;gap:10px;flex-wrap:wrap}
        .btn{border:none;border-radius:10px;padding:10px 14px;font-weight:700;cursor:pointer;transition:.2s;background:#e9eef8;color:#1f2a44}
        .btn:hover{transform:translateY(-1px)}
        .btn.primary{background:var(--primary);color:#fff}
        .btn.success{background:var(--success);color:#fff}
        .btn.warn{background:var(--warn);color:#fff}
        .btn.error{background:var(--error);color:#fff}
        .search{flex:1;min-width:220px;background:#f3f6fb;border-radius:12px;padding:10px 14px;display:flex;align-items:center;gap:10px}
        .search input{border:none;background:transparent;outline:none;width:100%}
        .tabs{display:flex;gap:8px;flex-wrap:wrap}
        .tab{padding:8px 14px;border-radius:999px;background:#eff2f9;color:#334155;font-weight:700;cursor:pointer;border:2px solid transparent}
        .tab.active{background:#1f3a8a;color:#fff;border-color:#10245b}
        table{width:100%;border-collapse:separate;border-spacing:0 10px}
        thead th{font-size:12px;text-transform:uppercase;color:#6b7280;text-align:left;padding:6px 10px}
        tbody tr{background:#ffffff}
        tbody tr td{padding:12px 10px;border-top:1px solid #edf2f7;border-bottom:1px solid #edf2f7}
        tbody tr td:first-child{border-left:1px solid #edf2f7;border-top-left-radius:12px;border-bottom-left-radius:12px}
        tbody tr td:last-child{border-right:1px solid #edf2f7;border-top-right-radius:12px;border-bottom-right-radius:12px}
        .chip{padding:4px 8px;border-radius:999px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:6px}
        .chip.processing{background:#fff5e6;color:#b45309}
        .chip.accepted{background:#e7f8ef;color:#14532d}
        .chip.rejected{background:#fdecec;color:#7f1d1d}
        .avg-badge{font-weight:800;padding:4px 8px;border-radius:8px}
        .avg-green{background:#e7f8ef;color:#14532d}
        .avg-amber{background:#fff5e6;color:#92400e}
        .avg-red{background:#fdecec;color:#7f1d1d}
        .badge{background:#eef2ff;color:#1e3a8a;font-weight:800;padding:2px 8px;border-radius:999px;font-size:11px}
        .row-actions{display:flex;gap:8px;flex-wrap:wrap}
        .details{background:#f8fafc;border:1px dashed #d9e2ec;border-radius:12px;margin-top:10px;padding:12px;display:none}
        .stickybar{position:sticky;top:0;z-index:20;background:linear-gradient(120deg,#fff,#f7f9fe);padding:12px;border-radius:12px;box-shadow:0 10px 24px rgba(0,0,0,.06)}
        .tablewrap{overflow:auto}
        .select-col{text-align:center}
        .checkbox{width:18px;height:18px}
        .footer-note{color:#6b7280;font-size:12px;margin-top:8px}
        .hidden{display:none}
        .export{display:flex;gap:8px;align-items:center}
        .modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.4);display:none;align-items:center;justify-content:center;z-index:50}
        .modal{background:#fff;border-radius:16px;max-width:520px;width:94%;padding:18px;box-shadow:0 20px 60px rgba(0,0,0,.2)}
        .modal h3{margin:0 0 8px 0}
        .modal textarea{width:100%;height:100px;border:1px solid #e5e7eb;border-radius:10px;padding:10px}
        .legend{display:flex;gap:12px;align-items:center;flex-wrap:wrap;font-size:12px;color:#64748b}
        .legend span{display:inline-flex;align-items:center;gap:6px}
        .dot{width:10px;height:10px;border-radius:999px;display:inline-block}
        @media (max-width:900px){
            .grid{grid-template-columns:repeat(6,1fr)}
        }
        @media (max-width:600px){
            .grid{grid-template-columns:repeat(2,1fr)}
            .toolbar{flex-direction:column;align-items:stretch}
        }
    </style>
</head>
<body>
<div class="container">

    <!-- Sticky top bar with search + export + bulk -->
    <div class="stickybar card">
        <div class="header">
            <div class="title">Admin • Review Applications <span class="pill">Real-time dashboard</span></div>
            <div class="legend">
                <span><i class="fa fa-gauge"></i> Threshold: <b><?php echo (int)$ACCEPT_THRESHOLD; ?>%</b></span>
                <span><span class="dot" style="background:#27ae60"></span>Accepted</span>
                <span><span class="dot" style="background:#f39c12"></span>Processing</span>
                <span><span class="dot" style="background:#e74c3c"></span>Rejected</span>
            </div>
        </div>

        <div class="toolbar">
            <div class="search">
                <i class="fa fa-search"></i>
                <input id="searchInput" type="text" placeholder="Search by name, student number, field, university, status…">
            </div>
            <div class="tabs" id="tabs">
                <button class="tab active" data-target="table_all">All (<?php echo $total; ?>)</button>
                <button class="tab" data-target="table_processing">Processing (<?php echo $processing; ?>)</button>
                <button class="tab" data-target="table_accepted">Accepted (<?php echo $accepted; ?>)</button>
                <button class="tab" data-target="table_rejected">Rejected (<?php echo $rejected; ?>)</button>
            </div>
            <div class="export" style="margin-left:auto">
                <button class="btn" onclick="exportVisibleCSV()">Export CSV</button>
                <button class="btn" onclick="window.print()">Print</button>
                <button class="btn success" onclick="bulkAction('Accepted')"><i class="fa fa-check"></i> Bulk Accept</button>
                <button class="btn error" onclick="openCommentModal('Rejected')"><i class="fa fa-times"></i> Bulk Reject</button>
            </div>
        </div>
    </div>

    <!-- KPI cards -->
    <div class="grid" style="margin-top:16px">
        <div class="card kpi" style="grid-column: span 3">
            <div class="icon" style="background:#1f3a8a"><i class="fa fa-users"></i></div>
            <div class="meta"><span class="label">Total Applications</span><span class="value"><?php echo $total; ?></span></div>
        </div>
        <div class="card kpi" style="grid-column: span 3">
            <div class="icon" style="background:#27ae60"><i class="fa fa-circle-check"></i></div>
            <div class="meta"><span class="label">Accepted</span><span class="value"><?php echo $accepted; ?></span></div>
        </div>
        <div class="card kpi" style="grid-column: span 3">
            <div class="icon" style="background:#f39c12"><i class="fa fa-hourglass-half"></i></div>
            <div class="meta"><span class="label">Processing</span><span class="value"><?php echo $processing; ?></span></div>
        </div>
        <div class="card kpi" style="grid-column: span 3">
            <div class="icon" style="background:#e74c3c"><i class="fa fa-circle-xmark"></i></div>
            <div class="meta"><span class="label">Rejected</span><span class="value"><?php echo $rejected; ?></span></div>
        </div>
    </div>

    <!-- Charts -->
    <div class="grid" style="margin-top:4px">
        <div class="card" style="grid-column:span 6">
            <h3 style="margin:0 0 8px 0">Status Distribution</h3>
            <canvas id="statusChart" height="100"></canvas>
        </div>
        <div class="card" style="grid-column:span 6">
            <h3 style="margin:0 0 8px 0">Average Threshold</h3>
            <p class="footer-note">Candidates meeting or exceeding <b><?php echo (int)$ACCEPT_THRESHOLD; ?>%</b> are immediately eligible to be accepted (button enabled).</p>
            <canvas id="thresholdChart" height="100"></canvas>
        </div>
    </div>

    <!-- Tables -->
    <div class="card tablewrap" id="wrap_all">
        <?php echo render_table("table_all", $rows_all, $ACCEPT_THRESHOLD, "All Applications"); ?>
    </div>
    <div class="card tablewrap hidden" id="wrap_processing">
        <?php echo render_table("table_processing", $rows_processing, $ACCEPT_THRESHOLD, "Processing / Pending"); ?>
    </div>
    <div class="card tablewrap hidden" id="wrap_accepted">
        <?php echo render_table("table_accepted", $rows_accepted, $ACCEPT_THRESHOLD, "Accepted / Approved"); ?>
    </div>
    <div class="card tablewrap hidden" id="wrap_rejected">
        <?php echo render_table("table_rejected", $rows_rejected, $ACCEPT_THRESHOLD, "Rejected"); ?>
    </div>

    <p class="footer-note">Tip: Click headers to sort. Use the checkboxes for bulk actions. “View details” reveals full student/application info and documents.</p>
</div>

<!-- Rejection Comment Modal -->
<div class="modal-backdrop" id="commentModal">
    <div class="modal">
        <h3><i class="fa fa-comment-dots"></i> Provide a short reason (visible to student)</h3>
        <textarea id="bulkComment" placeholder="Example: Average below threshold; incomplete payslip; apply again next cycle…"></textarea>
        <div style="margin-top:10px;display:flex;gap:8px;justify-content:flex-end">
            <button class="btn" onclick="closeCommentModal()">Cancel</button>
            <button class="btn error" onclick="bulkRejectWithComment()">Reject Selected</button>
        </div>
    </div>
</div>

<script>
/* ===== Utility: CSV Export (visible table) ===== */
function exportVisibleCSV(){
    const activeWrap = document.querySelector('.tablewrap:not(.hidden)');
    if(!activeWrap){ alert('No table visible'); return; }
    const table = activeWrap.querySelector('table');
    const rows = [...table.querySelectorAll('tr')];
    const lines = rows.map(tr => [...tr.querySelectorAll('th,td')].map(td => {
        const text = td.innerText.replace(/\s+/g,' ').trim().replace(/"/g,'""');
        return `"${text}"`;
    }).join(','));
    const blob = new Blob([lines.join('\n')], {type:'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href=url; a.download='applications_export.csv'; a.click();
    URL.revokeObjectURL(url);
}

/* ===== Tabs ===== */
const tabs = document.querySelectorAll('.tab');
tabs.forEach(t => t.addEventListener('click', () => {
    tabs.forEach(x => x.classList.remove('active'));
    t.classList.add('active');
    const tgt = t.getAttribute('data-target');
    document.querySelectorAll('.tablewrap').forEach(w => w.classList.add('hidden'));
    if(tgt.includes('all')) document.getElementById('wrap_all').classList.remove('hidden');
    if(tgt.includes('processing')) document.getElementById('wrap_processing').classList.remove('hidden');
    if(tgt.includes('accepted')) document.getElementById('wrap_accepted').classList.remove('hidden');
    if(tgt.includes('rejected')) document.getElementById('wrap_rejected').classList.remove('hidden');
    document.getElementById('searchInput').dispatchEvent(new Event('input')); // re-filter
}));

/* ===== Live Search across visible table ===== */
document.getElementById('searchInput').addEventListener('input', function(){
    const q = this.value.toLowerCase();
    const activeWrap = document.querySelector('.tablewrap:not(.hidden)');
    if(!activeWrap) return;
    activeWrap.querySelectorAll('tbody tr.data').forEach(tr => {
        const hit = tr.innerText.toLowerCase().includes(q);
        tr.style.display = hit ? '' : 'none';
        const details = tr.nextElementSibling;
        if(details && details.classList.contains('details-row')) {
            details.style.display = hit ? 'table-row' : 'none';
        }
    });
});

/* ===== Sort by clicking headers ===== */
document.querySelectorAll('table thead th.sortable').forEach(th=>{
    th.addEventListener('click', ()=>{
        const table = th.closest('table');
        const idx = [...th.parentNode.children].indexOf(th);
        const tbody = table.querySelector('tbody');
        const rows = [...tbody.querySelectorAll('tr.data')];
        const asc = th.dataset.sort !== 'asc';
        th.dataset.sort = asc ? 'asc' : 'desc';
        rows.sort((a,b)=>{
            const A = a.children[idx].innerText.trim().toLowerCase();
            const B = b.children[idx].innerText.trim().toLowerCase();
            const nA = parseFloat(A.replace(/[^\d.]/g,'')), nB = parseFloat(B.replace(/[^\d.]/g,''));
            if(!isNaN(nA) && !isNaN(nB)) return asc ? nA-nB : nB-nA;
            return asc ? A.localeCompare(B) : B.localeCompare(A);
        });
        rows.forEach(r=>{
            const details = r.nextElementSibling && r.nextElementSibling.classList.contains('details-row') ? r.nextElementSibling : null;
            tbody.appendChild(r);
            if(details) tbody.appendChild(details);
        });
    });
});

/* ===== Details toggles ===== */
function toggleDetails(id){
    const el = document.getElementById('details_' + id);
    if(!el) return;
    el.style.display = (el.style.display === 'none' || el.style.display === '') ? 'block' : 'none';
}

/* ===== Checkbox helpers ===== */
function getVisibleCheckedIds(){
    const activeWrap = document.querySelector('.tablewrap:not(.hidden)');
    return [...activeWrap.querySelectorAll('input.rowcheck:checked')].map(c=>c.value);
}
function toggleAll(source){
    const wrap = source.closest('.tablewrap');
    wrap.querySelectorAll('input.rowcheck').forEach(cb=>cb.checked = source.checked);
}

/* ===== Bulk actions ===== */
function bulkAction(status){
    const ids = getVisibleCheckedIds();
    if(ids.length===0) return alert('Select at least one application.');
    if(status==='Accepted'){
        if(!confirm('Accept '+ids.length+' application(s)?')) return;
        launchConfetti();
        postBulk(ids, 'Accepted', '');
    }else if(status==='Rejected'){
        openCommentModal('Rejected');
    }
}
function postBulk(ids, newStatus, comment){
    // sequential POSTs to update_application.php
    (async ()=>{
        for (const id of ids){
            const form = new FormData();
            form.append('application_id', id);
            form.append('new_status', newStatus);
            if(comment) form.append('review_comments', comment);
            await fetch('update_application.php', {method:'POST', body: form});
        }
        location.reload();
    })();
}

/* ===== Rejection comment modal ===== */
let pendingBulkStatus = null;
function openCommentModal(status){ pendingBulkStatus = status; document.getElementById('commentModal').style.display='flex'; }
function closeCommentModal(){ document.getElementById('commentModal').style.display='none'; }
function bulkRejectWithComment(){
    const ids = getVisibleCheckedIds();
    if(ids.length===0) return alert('Select at least one application.');
    const c = document.getElementById('bulkComment').value.trim();
    if(c.length<5) return alert('Please enter a brief reason (min 5 chars).');
    postBulk(ids, 'Rejected', c);
}

/* ===== Confetti ===== */
function launchConfetti(){
    confetti({particleCount: 160, spread: 70, origin: { y: 0.6 }});
}

/* ===== Charts ===== */
const statusCtx = document.getElementById('statusChart');
const thresholdCtx = document.getElementById('thresholdChart');
new Chart(statusCtx, {
    type:'doughnut',
    data:{
        labels:['Accepted','Processing','Rejected'],
        datasets:[{ data:[<?php echo $accepted; ?>, <?php echo $processing; ?>, <?php echo $rejected; ?>] }]
    },
    options:{ responsive:true, plugins:{ legend:{ position:'bottom' } } }
});
new Chart(thresholdCtx, {
    type:'bar',
    data:{
        labels:['Threshold'],
        datasets:[{ label:'Required %', data:[<?php echo (int)$ACCEPT_THRESHOLD; ?>] }]
    },
    options:{ responsive:true, plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true, max:100 } } }
});
</script>

<?php
// ---------- RENDER TABLE FUNCTION ----------
function render_table($tableId, $rows, $ACCEPT_THRESHOLD, $title){
    ob_start(); ?>
    <h3 style="margin:0 0 10px 0"><?php echo htmlspecialchars($title); ?></h3>
    <table id="<?php echo htmlspecialchars($tableId); ?>">
        <thead>
            <tr>
                <th class="select-col"><input type="checkbox" onclick="toggleAll(this)"></th>
                <th class="sortable">ID</th>
                <th class="sortable">Student #</th>
                <th class="sortable">Full Name</th>
                <th class="sortable">Bursary Type</th>
                <th class="sortable">Field of Study</th>
                <th class="sortable">University</th>
                <th class="sortable">Avg %</th>
                <th class="sortable">Merit</th>
                <th class="sortable">Status</th>
                <th class="sortable">Applied</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if(empty($rows)): ?>
            <tr class="data"><td colspan="12" style="text-align:center;color:#6b7280;padding:20px">No records.</td></tr>
        <?php else: foreach($rows as $row):
            $a = $row['app']; $s = $row['student']; $avg = (float)$row['avg']; $merit = $row['merit'];
            $status = $a['status'];
            $chip = 'processing'; if(in_array($status,['Accepted','Approved'])) $chip='accepted'; if($status==='Rejected') $chip='rejected';
            $avgClass = $avg >= $ACCEPT_THRESHOLD ? 'avg-green' : ($avg >= ($ACCEPT_THRESHOLD-10) ? 'avg-amber' : 'avg-red');
            $canAccept = $avg >= $ACCEPT_THRESHOLD ? '' : 'disabled';
            $safe = fn($v)=>htmlspecialchars((string)$v ?? '');
        ?>
            <tr class="data">
                <td class="select-col"><input class="checkbox rowcheck" type="checkbox" value="<?php echo $safe($a['id']); ?>"></td>
                <td><?php echo $safe($a['id']); ?></td>
                <td><?php echo $safe($a['student_number']); ?></td>
                <td><?php echo $safe($s['fullname'] ?? '—'); ?></td>
                <td><span class="badge"><?php echo $safe($a['bursary_type']); ?></span></td>
                <td><?php echo $safe($a['field_of_study']); ?></td>
                <td><?php echo $safe($a['university']); ?></td>
                <td><span class="avg-badge <?php echo $avgClass; ?>"><?php echo number_format($avg,2); ?>%</span></td>
                <td><span class="badge"><?php echo number_format($merit,2); ?></span></td>
                <td>
                    <span class="chip <?php echo $chip; ?>">
                        <?php if($chip==='processing'): ?><i class="fa fa-hourglass-half"></i><?php endif; ?>
                        <?php if($chip==='accepted'): ?><i class="fa fa-circle-check"></i><?php endif; ?>
                        <?php if($chip==='rejected'): ?><i class="fa fa-circle-xmark"></i><?php endif; ?>
                        <?php echo $safe($status); ?>
                    </span>
                </td>
                <td><?php echo $safe($a['date_applied']); ?></td>
                <td class="row-actions">
                    <form action="update_application.php" method="post" onsubmit="launchConfetti()" style="display:inline">
                        <input type="hidden" name="application_id" value="<?php echo $safe($a['id']); ?>">
                        <input type="hidden" name="new_status" value="Accepted">
                        <button type="submit" class="btn success" <?php echo $canAccept; ?>><i class="fa fa-check"></i> Accept</button>
                    </form>

                    <form action="update_application.php" method="post" style="display:inline" onsubmit="return askRejectReason(this)">
                        <input type="hidden" name="application_id" value="<?php echo $safe($a['id']); ?>">
                        <input type="hidden" name="new_status" value="Rejected">
                        <input type="hidden" name="review_comments" value="">
                        <button type="submit" class="btn error"><i class="fa fa-times"></i> Reject</button>
                    </form>

                    <button class="btn" onclick="toggleDetails('<?php echo $safe($a['id']); ?>')"><i class="fa fa-eye"></i> Details</button>
                </td>
            </tr>

            <!-- Details row -->
            <tr class="details-row">
                <td colspan="12">
                    <div class="details" id="details_<?php echo $safe($a['id']); ?>">
                        <!-- Student -->
                        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px">
                            <div>
                                <h4 style="margin:0 0 6px 0">Student</h4>
                                <p><b>Full Name:</b> <?php echo $safe($s['fullname'] ?? '—'); ?></p>
                                <p><b>ID Number:</b> <?php echo $safe($s['idnumber'] ?? '—'); ?></p>
                                <p><b>Email:</b> <?php echo $safe($s['email'] ?? '—'); ?></p>
                                <p><b>Phone:</b> <?php echo $safe($s['phone'] ?? '—'); ?></p>
                                <p><b>Address:</b> <?php echo $safe($s['address'] ?? '—'); ?></p>
                                <p><b>Province:</b> <?php echo $safe($s['province'] ?? '—'); ?></p>
                                <p><b>Registered at PSET:</b> <?php echo $safe($s['registered'] ?? '—'); ?></p>
                                <p><b>Level of Study:</b> <?php echo $safe($s['level'] ?? '—'); ?></p>
                                <p><b>Institution Name:</b> <?php echo $safe($s['institution'] ?? '—'); ?></p>
                            </div>
                            <div>
                                <h4 style="margin:0 0 6px 0">Application</h4>
                                <p><b>Qualification Type:</b> <?php echo $safe($a['qualification_type']); ?></p>
                                <p><b>Qualification:</b> <?php echo $safe($a['qualification']); ?></p>
                                <p><b>Degree Name:</b> <?php echo $safe($a['degree_name']); ?></p>
                                <p><b>Field of Study:</b> <?php echo $safe($a['field_of_study']); ?></p>
                                <p><b>Qualification Duration:</b> <?php echo $safe($a['qualification_duration']); ?></p>
                                <p><b>Motivation:</b> <?php echo $safe($a['motivation']); ?></p>
                                <p><b>Motivation Comments:</b> <?php echo nl2br($safe($a['motivation_comments'])); ?></p>
                                <p><b>Other Sponsor:</b> <?php echo $safe($a['other_sponsor']); ?></p>
                                <?php if(($a['other_sponsor'] ?? '') === 'Yes'): ?>
                                    <p><b>Other Sponsor Name:</b> <?php echo $safe($a['other_sponsor_name']); ?></p>
                                    <p><b>Other Sponsor Cellphone:</b> <?php echo $safe($a['other_sponsor_cell']); ?></p>
                                    <p><b>Other Sponsor Reason:</b> <?php echo nl2br($safe($a['other_sponsor_reason'])); ?></p>
                                <?php endif; ?>
                                <p><b>Average (Matric):</b> <?php echo number_format($avg,2); ?>%</p>
                            </div>
                        </div>

                        <!-- Documents -->
                        <div style="margin-top:8px">
                            <h4 style="margin:6px 0">Documents</h4>
                            <ul style="margin:0;padding-left:16px">
                                <li><b>Certified ID Copy:</b> <?php echo render_doc($a['id_copy']); ?></li>
                                <li><b>Certified Matric Certificate:</b> <?php echo render_doc($a['matric']); ?></li>
                                <li><b>Proof of Acceptance:</b> <?php echo render_doc($a['acceptance']); ?></li>
                                <li><b>Motivational Letter:</b> <?php echo render_doc($a['motivation_letter']); ?></li>
                                <li><b>Parent/Guardian 1 ID:</b> <?php echo render_doc($a['parent1_id']); ?></li>
                                <li><b>Parent/Guardian 2 ID:</b> <?php echo render_doc($a['parent2_id']); ?></li>
                                <li><b>Parent/Guardian 1 Payslip:</b> <?php echo render_doc($a['payslip1']); ?></li>
                                <li><b>Parent/Guardian 2 Payslip / SASSA:</b> <?php echo render_doc($a['payslip2']); ?></li>
                            </ul>
                        </div>
                    </div>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    <script>
        // Inline reject reason for single row
        function askRejectReason(form){
            const reason = prompt('Provide a short reason for rejection (visible to student):','Average below threshold.');
            if(reason===null) return false;
            form.querySelector('input[name="review_comments"]').value = reason;
            return true;
        }
    </script>
    <?php
    return ob_get_clean();
}

// Render document link helper
function render_doc($path){
    $p = htmlspecialchars((string)$path ?? '');
    if($p) return '<a href="'.$p.'" target="_blank">View</a>';
    return '<span style="color:#9ca3af">Not provided</span>';
}
?>