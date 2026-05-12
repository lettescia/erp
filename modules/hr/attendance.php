<?php
require_once __DIR__ . '/../../includes/header.php';
$db = getDB();

$allowedAttendanceStatus = ['present', 'absent', 'half-day', 'leave'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'mark') {
        $empId  = (int)$_POST['employee_id'];
        $date   = sanitize($_POST['date']);
        $cin    = sanitize($_POST['check_in']);
        $cout   = sanitize($_POST['check_out']);
        $status = sanitize($_POST['status']);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }
        if (!in_array($status, $allowedAttendanceStatus, true)) {
            $status = 'present';
        }

        // Upsert
        $chkStmt = $db->prepare("SELECT id FROM attendance WHERE employee_id=? AND date=?");
        $chkStmt->bind_param('is', $empId, $date);
        $chkStmt->execute();
        $check = $chkStmt->get_result()->fetch_assoc();
        if ($check) {
            $stmt = $db->prepare("UPDATE attendance SET check_in=?,check_out=?,status=? WHERE employee_id=? AND date=?");
            $stmt->bind_param('sssis', $cin, $cout, $status, $empId, $date);
            $stmt->execute();
        } else {
            $stmt = $db->prepare("INSERT INTO attendance (employee_id,date,check_in,check_out,status) VALUES(?,?,?,?,?)");
            $stmt->bind_param('issss', $empId, $date, $cin, $cout, $status);
            $stmt->execute();
        }
        header('Location: attendance.php?success=1'); exit;
    }
    if ($_POST['action'] === 'bulk_mark') {
        $date   = sanitize($_POST['date']);
        $status = sanitize($_POST['bulk_status']);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }
        if (!in_array($status, $allowedAttendanceStatus, true)) {
            $status = 'present';
        }
        $empIds = $_POST['emp_ids'] ?? [];
        $stmtUp = $db->prepare("UPDATE attendance SET status=? WHERE employee_id=? AND date=?");
        $stmtIn = $db->prepare("INSERT INTO attendance (employee_id,date,status) VALUES (?,?,?)");
        foreach ($empIds as $eid) {
            $eid = (int)$eid;
            if ($eid < 1) {
                continue;
            }
            $chkStmt = $db->prepare("SELECT id FROM attendance WHERE employee_id=? AND date=?");
            $chkStmt->bind_param('is', $eid, $date);
            $chkStmt->execute();
            $check = $chkStmt->get_result()->fetch_assoc();
            if ($check) {
                $stmtUp->bind_param('sis', $status, $eid, $date);
                $stmtUp->execute();
            } else {
                $stmtIn->bind_param('iss', $eid, $date, $status);
                $stmtIn->execute();
            }
        }
        header('Location: attendance.php?date='.urlencode($date).'&success=1'); exit;
    }
}

$filterDate = isset($_GET['date']) ? sanitize($_GET['date']) : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) {
    $filterDate = date('Y-m-d');
}

$empStmt = $db->prepare("
    SELECT e.id, e.employee_id, e.first_name, e.last_name, d.name as dept,
           a.check_in, a.check_out, a.status as att_status
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN attendance a ON a.employee_id = e.id AND a.date = ?
    WHERE e.status = 'active'
    ORDER BY e.first_name
");
$empStmt->bind_param('s', $filterDate);
$empStmt->execute();
$employees = $empStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Stats for selected date
$present  = array_filter($employees, fn($e) => $e['att_status'] === 'present');
$absent   = array_filter($employees, fn($e) => $e['att_status'] === 'absent');
$halfday  = array_filter($employees, fn($e) => $e['att_status'] === 'half-day');
$leave    = array_filter($employees, fn($e) => $e['att_status'] === 'leave');
$unmarked = array_filter($employees, fn($e) => $e['att_status'] === null);

$statusColors = ['present'=>'badge-green','absent'=>'badge-red','half-day'=>'badge-amber','leave'=>'badge-blue'];
?>

<div class="page-header">
    <div>
        <h1><i class="fa fa-clock" style="margin-right:10px;color:var(--accent)"></i>Attendance</h1>
        <p>Track daily employee attendance and check-in/out times</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('singleModal')">
        <i class="fa fa-user-clock"></i> Mark Attendance
    </button>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success"><i class="fa fa-circle-check"></i> Attendance updated.</div>
<?php endif; ?>

<!-- Date Filter & Stats -->
<div class="card" style="margin-bottom:20px;">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;">
        <form method="GET" style="display:flex;align-items:center;gap:10px;">
            <label style="font-size:0.8rem;color:var(--text-secondary);font-weight:600;text-transform:uppercase;letter-spacing:.5px;">
                View Date:
            </label>
            <input type="date" name="date" value="<?= $filterDate ?>"
                style="background:var(--bg-hover);border:1px solid var(--border-light);border-radius:8px;padding:8px 12px;color:var(--text-primary);font-family:'DM Sans',sans-serif;outline:none;">
            <button type="submit" class="btn btn-secondary btn-sm"><i class="fa fa-filter"></i> Filter</button>
        </form>
        <div style="display:flex;gap:16px;flex-wrap:wrap;">
            <div style="text-align:center">
                <div style="font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:700;color:var(--accent-success)"><?= count($present) ?></div>
                <div style="font-size:0.72rem;color:var(--text-secondary)">Present</div>
            </div>
            <div style="text-align:center">
                <div style="font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:700;color:var(--accent-danger)"><?= count($absent) ?></div>
                <div style="font-size:0.72rem;color:var(--text-secondary)">Absent</div>
            </div>
            <div style="text-align:center">
                <div style="font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:700;color:var(--accent-3)"><?= count($halfday) ?></div>
                <div style="font-size:0.72rem;color:var(--text-secondary)">Half-Day</div>
            </div>
            <div style="text-align:center">
                <div style="font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:700;color:var(--accent)"><?= count($leave) ?></div>
                <div style="font-size:0.72rem;color:var(--text-secondary)">On Leave</div>
            </div>
            <div style="text-align:center">
                <div style="font-family:'Syne',sans-serif;font-size:1.3rem;font-weight:700;color:var(--text-muted)"><?= count($unmarked) ?></div>
                <div style="font-size:0.72rem;color:var(--text-secondary)">Unmarked</div>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Mark Form -->
<div class="card">
    <form method="POST" id="bulkForm">
        <input type="hidden" name="action" value="bulk_mark">
        <input type="hidden" name="date" value="<?= $filterDate ?>">
        <div class="card-header">
            <h2><i class="fa fa-users" style="margin-right:8px"></i>Employee Attendance — <?= date('D, d M Y', strtotime($filterDate)) ?></h2>
            <div style="display:flex;gap:10px;align-items:center;">
                <select name="bulk_status" class="btn btn-secondary btn-sm" style="padding:7px 12px;">
                    <option value="present">Mark All Present</option>
                    <option value="absent">Mark All Absent</option>
                    <option value="half-day">Mark All Half-Day</option>
                    <option value="leave">Mark All On Leave</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm" onclick="selectAll()">
                    <i class="fa fa-check-double"></i> Apply to Selected
                </button>
            </div>
        </div>

        <div class="search-bar">
            <div class="search-input-wrap">
                <i class="fa fa-search"></i>
                <input type="text" id="attSearch" placeholder="Search employees..." oninput="filterTable('attSearch','attTable')">
            </div>
            <label style="display:flex;align-items:center;gap:8px;font-size:0.85rem;color:var(--text-secondary);cursor:pointer;">
                <input type="checkbox" id="selectAllChk" onchange="toggleAll(this)"> Select All
            </label>
        </div>

        <div class="table-wrap">
            <table id="attTable">
                <thead>
                    <tr>
                        <th style="width:40px"><i class="fa fa-check"></i></th>
                        <th>Emp ID</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($employees as $e): ?>
                <tr>
                    <td><input type="checkbox" name="emp_ids[]" value="<?= $e['id'] ?>" class="emp-check"></td>
                    <td class="font-mono text-accent"><?= htmlspecialchars($e['employee_id']) ?></td>
                    <td style="font-weight:600"><?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']) ?></td>
                    <td style="color:var(--text-secondary)"><?= htmlspecialchars($e['dept'] ?? '—') ?></td>
                    <td style="color:var(--text-secondary)"><?= $e['check_in'] ? substr($e['check_in'],0,5) : '—' ?></td>
                    <td style="color:var(--text-secondary)"><?= $e['check_out'] ? substr($e['check_out'],0,5) : '—' ?></td>
                    <td>
                        <?php if ($e['att_status']): ?>
                        <span class="badge <?= $statusColors[$e['att_status']] ?? 'badge-gray' ?>">
                            <?= ucfirst($e['att_status']) ?>
                        </span>
                        <?php else: ?>
                        <span class="badge badge-gray">Unmarked</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($employees)): ?>
                <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:32px;">No active employees found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>
</div>

<!-- Single Employee Attendance Modal -->
<div class="modal-overlay" id="singleModal">
    <div class="modal" style="max-width:480px">
        <div class="modal-header">
            <h2><i class="fa fa-user-clock" style="margin-right:8px;color:var(--accent)"></i>Mark Attendance</h2>
            <button class="modal-close" onclick="closeModal('singleModal')"><i class="fa fa-xmark"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="mark">
            <div class="form-grid">
                <div class="form-group">
                    <label>Employee</label>
                    <select name="employee_id" required>
                        <option value="">— Select Employee —</option>
                        <?php foreach ($employees as $e): ?>
                        <option value="<?= $e['id'] ?>">
                            <?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="date" value="<?= $filterDate ?>" required>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" required>
                        <option value="present">Present</option>
                        <option value="absent">Absent</option>
                        <option value="half-day">Half-Day</option>
                        <option value="leave">On Leave</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Check In Time</label>
                    <input type="time" name="check_in" value="09:00">
                </div>
                <div class="form-group">
                    <label>Check Out Time</label>
                    <input type="time" name="check_out" value="17:00">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('singleModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleAll(chk) {
    document.querySelectorAll('.emp-check').forEach(c => c.checked = chk.checked);
}
function selectAll() {
    document.querySelectorAll('.emp-check').forEach(c => c.checked = true);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
