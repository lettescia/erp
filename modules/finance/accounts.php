<?php
require_once __DIR__ . '/../../includes/header.php';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $name = sanitize($_POST['account_name']);
        $type = sanitize($_POST['account_type']);
        $bal  = floatval($_POST['balance']);
        $cur  = sanitize($_POST['currency']);
        $stmt = $db->prepare("INSERT INTO accounts (account_name,account_type,balance,currency) VALUES(?,?,?,?)");
        $stmt->bind_param('ssds',$name,$type,$bal,$cur);
        $stmt->execute();
        header('Location: accounts.php?success=1'); exit;
    }
}
if (isset($_GET['delete'])) {
    $db->query("DELETE FROM accounts WHERE id=".(int)$_GET['delete']);
    header('Location: accounts.php?success=1'); exit;
}

$accounts = $db->query("SELECT * FROM accounts ORDER BY account_type, account_name")->fetch_all(MYSQLI_ASSOC);
$typeColors = ['asset'=>'badge-green','liability'=>'badge-red','equity'=>'badge-blue','income'=>'badge-teal','expense'=>'badge-amber'];
?>

<div class="page-header">
  <div><h1><i class="fa fa-landmark" style="margin-right:10px;color:var(--accent)"></i>Chart of Accounts</h1><p>Manage general ledger accounts</p></div>
  <button class="btn btn-primary" onclick="openModal('acctModal')"><i class="fa fa-plus"></i> New Account</button>
</div>

<?php if (isset($_GET['success'])): ?><div class="alert alert-success"><i class="fa fa-circle-check"></i> Done.</div><?php endif; ?>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Account Name</th><th>Type</th><th>Balance</th><th>Currency</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($accounts as $a): ?>
      <tr>
        <td style="font-weight:600"><?= htmlspecialchars($a['account_name']) ?></td>
        <td><span class="badge <?= $typeColors[$a['account_type']] ?? 'badge-gray' ?>"><?= ucfirst($a['account_type']) ?></span></td>
        <td style="font-family:monospace">$<?= number_format($a['balance'],2) ?></td>
        <td><?= htmlspecialchars($a['currency']) ?></td>
        <td><a href="accounts.php?delete=<?= $a['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete account?')"><i class="fa fa-trash"></i></a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($accounts)): ?>
      <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:32px;">No accounts yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="acctModal">
  <div class="modal">
    <div class="modal-header"><h2><i class="fa fa-landmark" style="margin-right:8px;color:var(--accent)"></i>New Account</h2><button class="modal-close" onclick="closeModal('acctModal')"><i class="fa fa-xmark"></i></button></div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="form-grid">
        <div class="form-group"><label>Account Name</label><input type="text" name="account_name" required placeholder="Cash & Equivalents"></div>
        <div class="form-group"><label>Type</label>
          <select name="account_type" required>
            <option value="asset">Asset</option><option value="liability">Liability</option>
            <option value="equity">Equity</option><option value="income">Income</option><option value="expense">Expense</option>
          </select>
        </div>
        <div class="form-group"><label>Opening Balance ($)</label><input type="number" name="balance" step="0.01" value="0"></div>
        <div class="form-group"><label>Currency</label>
          <select name="currency"><option>USD</option><option>EUR</option><option>GBP</option><option>INR</option></select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('acctModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Create Account</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
