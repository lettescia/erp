<?php
require_once __DIR__ . '/../../includes/header.php';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'generate') {
        $month = (int)$_POST['month'];
        $year  = (int)$_POST['year'];

        // Get all active employees
        $emps = $db->query("SELECT * FROM employees WHERE status='active'")->fetch_all(MYSQLI_ASSOC);
        $generated = 0;
        foreach ($emps as $e) {
            // Check if already generated
            $exists = $db->query("SELECT id FROM payroll WHERE employee_id={$e['id']} AND month=$month AND year=$year")->fetch_assoc();
            if (!$exists) {
                $basic  = floatval($e['salary']);
                $allow  = $basic * 0.20; // 20% allowances
                $deduc  = $basic * 0.10; // 10% deductions (tax/pf)
                $net    = $basic + $allow - $deduc;
                $stmt = $db->prepare("INSERT INTO payroll (employee_id,month,year,basic_salary,allowances,deductions,net_salary,status) VALUES(?,?,?,?,?,?,?,'pending')");
                $stmt->bind_param('iiidddd', $e['id'], $month, $year, $basic, $allow, $deduc, $net);
                $stmt->execute();
                $generated++;
            }
        }
        header("Location: payroll.php?success=1&gen=$generated"); exit;
    }
    if ($_POST['action'] === 'pay') {
        $id = (int)$_POST['id'];
        $db->query("UPDATE payroll SET status='paid', paid_date=CURDATE() WHERE id=$id");
        header('Location: payroll.php?success=1'); exit;
    }
    if ($_POST['action'] === 'pay_all') {
        $month = (int)$_POST['month'];
        $year  = (int)$_POST['year'];
        $db->query("UPDATE payroll SET status='paid', paid_date=CURDATE() WHERE month=$month AND year=$year AND status='pending'");
        header('Location: payroll.php?success=1'); exit;
    }
    if ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        $db->query("DELETE FROM payroll WHERE id=$id");
        header('Location: payroll.php?success=1'); exit;
    }
}

$filterMonth = (int)($_GET['month'] ?? date('n'));
$filterYear  = (int)($_GET['year']  ?? date('Y'));

$payrolls = $db->query("
    SELECT p.*, CONCAT(e.first_name,' ',e.last_name) as emp_name,
           e.employee_id as emp_code, d.name as dept
    FROM payroll p
    JOIN employees e ON p.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    WHERE p.month = $filterMonth AND p.year = $filterYear
    ORDER BY e.first_name
")->fetch_all(MYSQLI_ASSOC);

$summary = $db->query("
    SELECT SUM(basic_salary) as basic, SUM(allowances) as allow,
           SUM(deductions) as deduc, SUM(net_salary) as net,
           COUNT(*) as cnt,
           SUM(CASE WHEN status='paid' THEN 1 ELSE 0 END) as paid_cnt,
           SUM(CASE WHEN status='paid' THEN net_salary ELSE 0 END) as paid_amt
    FROM payroll WHERE month=$filterMonth AND year=$filterYear
")->fetch_assoc();

$months = ['January','February','March','April','May','June',
           'July','August','September','October','November','December'];
?>

<div class="page-header">
    <div>
        <h1><i class="fa fa-money-bill-wave" style="margin-right:10px;color:var(--accent)"></i>Payroll</h1>
        <p>Process and manage employee salary payments</p>
    </div>
    <div style="display:flex;gap:10px;">
        <button class="btn btn-secondary" onclick="openModal('generateModal')">
            <i class="fa fa-gears"></i> Generate Payroll
        </button>
        <?php if (!empty($payrolls)): ?>
        <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="pay_all">
            <input type="hidden" name="month" value="<?= $filterMonth ?>">
            <input type="hidden" name="year" value="<?= $filterYear ?>">
            <button type="submit" class="btn btn-primary" onclick="return confirm('Mark all pending as paid?')">
                <i class="fa fa-check-double"></i> Pay All Pending
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success">
    <i class="fa fa-circle-check"></i>
    <?= isset($_GET['gen']) ? 'Generated payroll for ' . htmlspecialchars((string)$_GET['gen'], ENT_QUOTES, 'UTF-8') . ' employees.' : 'Action completed.' ?>
</div>
<?php endif; ?>

<!-- Month Filter -->
<div class="card" style="margin-bottom:20px;">
    <form method="GET" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <label style="font-size:0.8rem;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.5px">Period:</label>
        <select name="month" style="background:var(--bg-hover);border:1px solid var(--border-light);border-radius:8px;padding:8px 12px;color:var(--text-primary);font-family:'DM Sans',sans-serif;outline:none;">
            <?php foreach ($months as $i => $m): ?>
            <option value="<?= $i+1 ?>" <?= ($i+1)===$filterMonth?'selected':'' ?>><?= $m ?></option>
            <?php endforeach; ?>
        </select>
        <select name="year" style="background:var(--bg-hover);border:1px solid var(--border-light);border-radius:8px;padding:8px 12px;color:var(--text-primary);font-family:'DM Sans',sans-serif;outline:none;">
            <?php for ($y = date('Y'); $y >= date('Y')-3; $y--): ?>
            <option value="<?= $y ?>" <?= $y===$filterYear?'selected':'' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
        <button type="submit" class="btn btn-secondary btn-sm"><i class="fa fa-filter"></i> View</button>
    </form>
</div>

<!-- Summary Cards -->
<?php if (!empty($payrolls)): ?>
<div class="stat-grid" style="margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fa fa-users"></i></div>
        <div class="stat-info">
            <div class="label">Total Employees</div>
            <div class="value"><?= $summary['cnt'] ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><i class="fa fa-sack-dollar"></i></div>
        <div class="stat-info">
            <div class="label">Gross Payroll</div>
            <div class="value">$<?= number_format($summary['basic'] + $summary['allow'], 0) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fa fa-minus-circle"></i></div>
        <div class="stat-info">
            <div class="label">Total Deductions</div>
            <div class="value">$<?= number_format($summary['deduc'], 0) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fa fa-circle-check"></i></div>
        <div class="stat-info">
            <div class="label">Net Payroll</div>
            <div class="value">$<?= number_format($summary['net'], 0) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon teal"><i class="fa fa-money-bill-transfer"></i></div>
        <div class="stat-info">
            <div class="label">Paid / Pending</div>
            <div class="value"><?= $summary['paid_cnt'] ?>/<?= $summary['cnt'] - $summary['paid_cnt'] ?></div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Payroll Table -->
<div class="card">
    <div class="card-header">
        <h2><i class="fa fa-table" style="margin-right:8px"></i>
            <?= $months[$filterMonth-1] ?> <?= $filterYear ?> Payroll
        </h2>
        <span style="font-size:0.8rem;color:var(--text-secondary)"><?= count($payrolls) ?> records</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Emp ID</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Basic</th>
                    <th>Allowances</th>
                    <th>Deductions</th>
                    <th>Net Salary</th>
                    <th>Status</th>
                    <th>Paid Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($payrolls as $p): ?>
            <tr>
                <td class="font-mono text-accent"><?= htmlspecialchars($p['emp_code']) ?></td>
                <td style="font-weight:600"><?= htmlspecialchars($p['emp_name']) ?></td>
                <td style="color:var(--text-secondary)"><?= htmlspecialchars($p['dept'] ?? '—') ?></td>
                <td>$<?= number_format($p['basic_salary'], 2) ?></td>
                <td style="color:var(--accent-success)">+$<?= number_format($p['allowances'], 2) ?></td>
                <td style="color:var(--accent-danger)">-$<?= number_format($p['deductions'], 2) ?></td>
                <td style="font-weight:700;font-family:monospace">$<?= number_format($p['net_salary'], 2) ?></td>
                <td>
                    <span class="badge <?= $p['status'] === 'paid' ? 'badge-green' : 'badge-amber' ?>">
                        <i class="fa fa-<?= $p['status'] === 'paid' ? 'circle-check' : 'clock' ?>"></i>
                        <?= ucfirst($p['status']) ?>
                    </span>
                </td>
                <td style="color:var(--text-secondary)"><?= $p['paid_date'] ?? '—' ?></td>
                <td>
                    <div class="flex gap-2">
                        <?php if ($p['status'] === 'pending'): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="pay">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button type="submit" class="btn btn-primary btn-sm" title="Mark as Paid">
                                <i class="fa fa-money-bill"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete payroll record?')">
                                <i class="fa fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($payrolls)): ?>
            <tr>
                <td colspan="10" style="text-align:center;color:var(--text-muted);padding:40px;">
                    <i class="fa fa-inbox" style="font-size:2rem;margin-bottom:12px;display:block"></i>
                    No payroll for <?= $months[$filterMonth-1] ?> <?= $filterYear ?>.
                    <br><br>
                    <button class="btn btn-primary" onclick="openModal('generateModal')">
                        <i class="fa fa-gears"></i> Generate Now
                    </button>
                </td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Generate Payroll Modal -->
<div class="modal-overlay" id="generateModal">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <h2><i class="fa fa-gears" style="margin-right:8px;color:var(--accent)"></i>Generate Payroll</h2>
            <button class="modal-close" onclick="closeModal('generateModal')"><i class="fa fa-xmark"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="generate">
            <p style="color:var(--text-secondary);font-size:0.875rem;margin-bottom:20px;">
                Automatically generate payroll for all active employees based on their salary records.
                Allowances are set at 20% and deductions at 10% of basic salary.
            </p>
            <div class="form-grid">
                <div class="form-group">
                    <label>Month</label>
                    <select name="month" required>
                        <?php foreach ($months as $i => $m): ?>
                        <option value="<?= $i+1 ?>" <?= ($i+1)===date('n')?'selected':'' ?>><?= $m ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Year</label>
                    <select name="year" required>
                        <?php for ($y = date('Y'); $y >= date('Y')-2; $y--): ?>
                        <option value="<?= $y ?>" <?= $y===date('Y')?'selected':'' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <div class="alert alert-info mt-2" style="margin-top:16px">
                <i class="fa fa-circle-info"></i>
                Duplicate entries for the same period will be skipped automatically.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('generateModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa fa-gears"></i> Generate</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
