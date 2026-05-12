<?php
require_once __DIR__ . '/../../includes/header.php';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $code = generateCode('SUP-','suppliers','supplier_code');
        $comp = sanitize($_POST['company_name']);
        $cont = sanitize($_POST['contact_name']);
        $em   = sanitize($_POST['email']);
        $ph   = sanitize($_POST['phone']);
        $addr = sanitize($_POST['address']);
        $city = sanitize($_POST['city']);
        $coun = sanitize($_POST['country']);
        $stmt = $db->prepare("INSERT INTO suppliers (supplier_code,company_name,contact_name,email,phone,address,city,country) VALUES(?,?,?,?,?,?,?,?)");
        $stmt->bind_param('ssssssss',$code,$comp,$cont,$em,$ph,$addr,$city,$coun);
        $stmt->execute();
        header('Location: suppliers.php?success=1'); exit;
    }
}
if (isset($_GET['delete'])) {
    $db->query("UPDATE suppliers SET status='inactive' WHERE id=".(int)$_GET['delete']);
    header('Location: suppliers.php?success=1'); exit;
}

$suppliers = $db->query("SELECT * FROM suppliers WHERE status='active' ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
?>

<div class="page-header">
  <div>
    <h1><i class="fa fa-truck" style="margin-right:10px;color:var(--accent)"></i>Suppliers</h1>
    <p>Manage vendors and supplier relationships</p>
  </div>
  <button class="btn btn-primary" onclick="openModal('supModal')">
    <i class="fa fa-plus"></i> Add Supplier
  </button>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success"><i class="fa fa-circle-check"></i> Action completed.</div>
<?php endif; ?>

<div class="card">
  <div class="search-bar">
    <div class="search-input-wrap">
      <i class="fa fa-search"></i>
      <input type="text" placeholder="Search suppliers..." oninput="filterTable('supSearch','supTable')" id="supSearch">
    </div>
  </div>
  <div class="table-wrap">
    <table id="supTable">
      <thead>
        <tr><th>Code</th><th>Company</th><th>Contact</th><th>Email</th><th>Phone</th><th>City</th><th>Country</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php foreach ($suppliers as $s): ?>
      <tr>
        <td class="font-mono text-accent"><?= htmlspecialchars($s['supplier_code']) ?></td>
        <td style="font-weight:600"><?= htmlspecialchars($s['company_name']) ?></td>
        <td><?= htmlspecialchars($s['contact_name'] ?? '—') ?></td>
        <td style="color:var(--text-secondary)"><?= htmlspecialchars($s['email'] ?? '—') ?></td>
        <td style="color:var(--text-secondary)"><?= htmlspecialchars($s['phone'] ?? '—') ?></td>
        <td><?= htmlspecialchars($s['city'] ?? '—') ?></td>
        <td><?= htmlspecialchars($s['country'] ?? '—') ?></td>
        <td>
          <a href="suppliers.php?delete=<?= $s['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Remove supplier?')"><i class="fa fa-trash"></i></a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($suppliers)): ?>
      <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:32px;">No suppliers found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="modal-overlay" id="supModal">
  <div class="modal">
    <div class="modal-header">
      <h2><i class="fa fa-truck" style="margin-right:8px;color:var(--accent)"></i>Add Supplier</h2>
      <button class="modal-close" onclick="closeModal('supModal')"><i class="fa fa-xmark"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="form-grid">
        <div class="form-group"><label>Company Name</label><input type="text" name="company_name" required placeholder="Supplier Co."></div>
        <div class="form-group"><label>Contact Name</label><input type="text" name="contact_name" placeholder="John Smith"></div>
        <div class="form-group"><label>Email</label><input type="email" name="email" placeholder="supplier@co.com"></div>
        <div class="form-group"><label>Phone</label><input type="text" name="phone" placeholder="+1 ..."></div>
        <div class="form-group"><label>City</label><input type="text" name="city" placeholder="Chicago"></div>
        <div class="form-group"><label>Country</label><input type="text" name="country" placeholder="USA"></div>
      </div>
      <div class="form-group mt-2"><label>Address</label><textarea name="address" placeholder="Full address..."></textarea></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('supModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Add Supplier</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
