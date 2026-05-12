<?php
require_once __DIR__ . '/../../includes/header.php';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $code = generateCode('AST-','assets','asset_code');
        $name = sanitize($_POST['name']);
        $cat  = sanitize($_POST['category']);
        $loc  = sanitize($_POST['location']);
        $asgn = (int)($_POST['assigned_to'] ?: 0) ?: null;
        $pd   = sanitize($_POST['purchase_date']);
        $pc   = floatval($_POST['purchase_cost']);
        $cv   = floatval($_POST['current_value']);
        $dr   = floatval($_POST['depreciation_rate']);
        $st   = sanitize($_POST['status']);
        $stmt = $db->prepare("INSERT INTO assets (asset_code,name,category,location,assigned_to,purchase_date,purchase_cost,current_value,depreciation_rate,status) VALUES(?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('ssssissdds',$code,$name,$cat,$loc,$asgn,$pd,$pc,$cv,$dr,$st);
        $stmt->execute();
        header('Location: assets.php?success=1'); exit;
    }
}
if (isset($_GET['delete'])) {
    $db->query("UPDATE assets SET status='retired' WHERE id=".(int)$_GET['delete']);
    header('Location: assets.php?success=1'); exit;
}

$assets = $db->query("SELECT a.*, CONCAT(e.first_name,' ',e.last_name) as emp_name FROM assets a LEFT JOIN employees e ON a.assigned_to=e.id WHERE a.status != 'retired' ORDER BY a.created_at DESC")->fetch_all(MYSQLI_ASSOC);
$employees = $db->query("SELECT id, CONCAT(first_name,' ',last_name) as name FROM employees WHERE status='active'")->fetch_all(MYSQLI_ASSOC);
$statusColors = ['active'=>'badge-green','maintenance'=>'badge-amber','retired'=>'badge-gray'];
?>

<div class="page-header">
  <div>
    <h1><i class="fa fa-server" style="margin-right:10px;color:var(--accent)"></i>Assets</h1>
    <p>Track company assets and depreciation</p>
  </div>
  <button class="btn btn-primary" onclick="openModal('assetModal')">
    <i class="fa fa-plus"></i> Add Asset
  </button>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success"><i class="fa fa-circle-check"></i> Action completed.</div>
<?php endif; ?>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Code</th><th>Name</th><th>Category</th><th>Location</th><th>Assigned To</th><th>Purchase Cost</th><th>Current Value</th><th>Depreciation</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php foreach ($assets as $a): ?>
      <tr>
        <td class="font-mono text-accent"><?= htmlspecialchars($a['asset_code']) ?></td>
        <td style="font-weight:600"><?= htmlspecialchars($a['name']) ?></td>
        <td><?= htmlspecialchars($a['category'] ?? '—') ?></td>
        <td><?= htmlspecialchars($a['location'] ?? '—') ?></td>
        <td><?= htmlspecialchars($a['emp_name'] ?? 'Unassigned') ?></td>
        <td>$<?= number_format($a['purchase_cost'],2) ?></td>
        <td>$<?= number_format($a['current_value'],2) ?></td>
        <td><?= $a['depreciation_rate'] ?>% / yr</td>
        <td><span class="badge <?= $statusColors[$a['status']] ?? 'badge-gray' ?>"><?= ucfirst($a['status']) ?></span></td>
        <td>
          <a href="assets.php?delete=<?= $a['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Retire this asset?')"><i class="fa fa-trash"></i></a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($assets)): ?>
      <tr><td colspan="10" style="text-align:center;color:var(--text-muted);padding:32px;">No assets found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="assetModal">
  <div class="modal">
    <div class="modal-header">
      <h2><i class="fa fa-server" style="margin-right:8px;color:var(--accent)"></i>Add Asset</h2>
      <button class="modal-close" onclick="closeModal('assetModal')"><i class="fa fa-xmark"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="form-grid">
        <div class="form-group"><label>Asset Name</label><input type="text" name="name" required placeholder="Dell Laptop 15"></div>
        <div class="form-group"><label>Category</label>
          <select name="category">
            <option>IT Equipment</option><option>Furniture</option><option>Vehicle</option>
            <option>Machinery</option><option>Office Equipment</option>
          </select>
        </div>
        <div class="form-group"><label>Location</label><input type="text" name="location" placeholder="Office Floor 2"></div>
        <div class="form-group">
          <label>Assigned To</label>
          <select name="assigned_to">
            <option value="">— Unassigned —</option>
            <?php foreach ($employees as $e): ?>
            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Purchase Date</label><input type="date" name="purchase_date" value="<?= date('Y-m-d') ?>"></div>
        <div class="form-group"><label>Purchase Cost ($)</label><input type="number" name="purchase_cost" step="0.01" placeholder="0.00"></div>
        <div class="form-group"><label>Current Value ($)</label><input type="number" name="current_value" step="0.01" placeholder="0.00"></div>
        <div class="form-group"><label>Depreciation Rate (%/yr)</label><input type="number" name="depreciation_rate" value="10" step="0.01"></div>
        <div class="form-group"><label>Status</label>
          <select name="status"><option value="active">Active</option><option value="maintenance">Maintenance</option></select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('assetModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Add Asset</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
