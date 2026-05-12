<?php
require_once __DIR__ . '/../../includes/header.php';
$db = getDB();

// Handle create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $acct  = (int)$_POST['account_id'];
        $type  = sanitize($_POST['type']);
        $amt   = floatval($_POST['amount']);
        $desc  = sanitize($_POST['description']);
        $ref   = sanitize($_POST['reference_no']);
        $date  = sanitize($_POST['transaction_date']);
        $uid   = (int)$_SESSION['user_id'];

        $stmt = $db->prepare("INSERT INTO transactions (account_id,type,amount,description,reference_no,transaction_date,created_by) VALUES(?,?,?,?,?,?,?)");
        $stmt->bind_param('isdsssi', $acct, $type, $amt, $desc, $ref, $date, $uid);
        $stmt->execute();

        // Update account balance
        if ($type === 'credit') {
            $db->query("UPDATE accounts SET balance = balance + $amt WHERE id = $acct");
        } else {
            $db->query("UPDATE accounts SET balance = balance - $amt WHERE id = $acct");
        }

        header('Location: transactions.php?success=1'); exit;
    }
    if ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        // Reverse balance
        $t = $db->query("SELECT * FROM transactions WHERE id=$id")->fetch_assoc();
        if ($t) {
            $reversal = $t['type'] === 'credit' ? "-{$t['amount']}" : "+{$t['amount']}";
            $db->query("UPDATE accounts SET balance = balance $reversal WHERE id = {$t['account_id']}");
            $db->query("DELETE FROM transactions WHERE id=$id");
        }
        header('Location: transactions.php?success=1'); exit;
    }
}

$transactions = $db->query("
    SELECT t.*, a.account_name, u.name as created_by_name
    FROM transactions t
    LEFT JOIN accounts a ON t.account_id = a.id
    LEFT JOIN users u ON t.created_by = u.id
    ORDER BY t.created_at DESC LIMIT 200
")->fetch_all(MYSQLI_ASSOC);

$accounts = $db->query("SELECT * FROM accounts ORDER BY account_name")->fetch_all(MYSQLI_ASSOC);

// Totals
$totals = $db->query("SELECT type, SUM(amount) as total FROM transactions GROUP BY type")->fetch_all(MYSQLI_ASSOC);
$totMap = [];
foreach ($totals as $t) $totMap[$t['type']] = $t['total'];
?>

<div class="page-header">
    <div>
        <h1><i class="fa fa-arrows-left-right" style="margin-right:10px;color:var(--accent)"></i>Transactions</h1>
        <p>General ledger — all financial entries</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('txnModal')">
        <i class="fa fa-plus"></i> New Transaction
    </button>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success"><i class="fa fa-circle-check"></i> Transaction recorded successfully.</div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="stat-grid" style="margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-icon green"><i class="fa fa-arrow-down-to-line"></i></div>
        <div class="stat-info">
            <div class="label">Total Credits</div>
            <div class="value">$<?= number_format($totMap['credit'] ?? 0, 2) ?></div>
            <div class="change up"><i class="fa fa-circle-check"></i> Inflow</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fa fa-arrow-up-from-line"></i></div>
        <div class="stat-info">
            <div class="label">Total Debits</div>
            <div class="value">$<?= number_format($totMap['debit'] ?? 0, 2) ?></div>
            <div class="change down"><i class="fa fa-circle-xmark"></i> Outflow</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fa fa-scale-balanced"></i></div>
        <div class="stat-info">
            <div class="label">Net Balance</div>
            <?php $net = ($totMap['credit'] ?? 0) - ($totMap['debit'] ?? 0); ?>
            <div class="value" style="color:<?= $net >= 0 ? 'var(--accent-success)' : 'var(--accent-danger)' ?>">
                $<?= number_format(abs($net), 2) ?>
            </div>
            <div class="change <?= $net >= 0 ? 'up' : 'down' ?>">
                <i class="fa fa-<?= $net >= 0 ? 'arrow-trend-up' : 'arrow-trend-down' ?>"></i>
                <?= $net >= 0 ? 'Surplus' : 'Deficit' ?>
            </div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><i class="fa fa-list-ul"></i></div>
        <div class="stat-info">
            <div class="label">Total Entries</div>
            <div class="value"><?= count($transactions) ?></div>
            <div class="change up"><i class="fa fa-database"></i> Records</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="search-bar">
        <div class="search-input-wrap">
            <i class="fa fa-search"></i>
            <input type="text" id="txnSearch" placeholder="Search transactions..." oninput="filterTable('txnSearch','txnTable')">
        </div>
        <select onchange="filterByType(this.value)" class="btn btn-secondary btn-sm" style="padding:9px 14px;">
            <option value="">All Types</option>
            <option value="credit">Credit</option>
            <option value="debit">Debit</option>
        </select>
    </div>
    <div class="table-wrap">
        <table id="txnTable">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Account</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Reference</th>
                    <th>Description</th>
                    <th>By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($transactions as $t): ?>
            <tr data-type="<?= $t['type'] ?>">
                <td style="color:var(--text-secondary);white-space:nowrap"><?= $t['transaction_date'] ?></td>
                <td style="font-weight:600"><?= htmlspecialchars($t['account_name'] ?? '—') ?></td>
                <td>
                    <span class="badge <?= $t['type'] === 'credit' ? 'badge-green' : 'badge-red' ?>">
                        <i class="fa fa-<?= $t['type'] === 'credit' ? 'arrow-down' : 'arrow-up' ?>"></i>
                        <?= ucfirst($t['type']) ?>
                    </span>
                </td>
                <td style="font-family:monospace;font-weight:600;color:<?= $t['type'] === 'credit' ? 'var(--accent-success)' : 'var(--accent-danger)' ?>">
                    <?= $t['type'] === 'credit' ? '+' : '-' ?>$<?= number_format($t['amount'], 2) ?>
                </td>
                <td class="font-mono text-accent"><?= htmlspecialchars($t['reference_no'] ?? '—') ?></td>
                <td style="color:var(--text-secondary);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                    <?= htmlspecialchars($t['description'] ?? '—') ?>
                </td>
                <td style="color:var(--text-secondary)"><?= htmlspecialchars($t['created_by_name'] ?? '—') ?></td>
                <td>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm"
                            onclick="return confirm('Reverse and delete this transaction?')">
                            <i class="fa fa-rotate-left"></i>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($transactions)): ?>
            <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:40px;">
                No transactions recorded yet.
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- New Transaction Modal -->
<div class="modal-overlay" id="txnModal">
    <div class="modal">
        <div class="modal-header">
            <h2><i class="fa fa-arrows-left-right" style="margin-right:8px;color:var(--accent)"></i>New Transaction</h2>
            <button class="modal-close" onclick="closeModal('txnModal')"><i class="fa fa-xmark"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-grid">
                <div class="form-group">
                    <label>Account</label>
                    <select name="account_id" required>
                        <option value="">— Select Account —</option>
                        <?php foreach ($accounts as $a): ?>
                        <option value="<?= $a['id'] ?>">
                            <?= htmlspecialchars($a['account_name']) ?> (<?= ucfirst($a['account_type']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select name="type" required>
                        <option value="credit">Credit (Inflow)</option>
                        <option value="debit">Debit (Outflow)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Amount ($)</label>
                    <input type="number" name="amount" step="0.01" min="0.01" required placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Transaction Date</label>
                    <input type="date" name="transaction_date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label>Reference No.</label>
                    <input type="text" name="reference_no" placeholder="REF-2024-001">
                </div>
            </div>
            <div class="form-group mt-2">
                <label>Description</label>
                <textarea name="description" placeholder="Transaction description..." rows="3"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('txnModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Record Transaction</button>
            </div>
        </form>
    </div>
</div>

<script>
function filterByType(type) {
    document.querySelectorAll('#txnTable tbody tr[data-type]').forEach(row => {
        row.style.display = (!type || row.dataset.type === type) ? '' : 'none';
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
