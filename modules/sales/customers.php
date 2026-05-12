<?php
require_once __DIR__ . '/../../includes/header.php';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $code = generateCode('CUS-','customers','customer_code');
        $comp = sanitize($_POST['company_name']);
        $cont = sanitize($_POST['contact_name']);
        $em   = sanitize($_POST['email']);
        $ph   = sanitize($_POST['phone']);
        $addr = sanitize($_POST['address']);
        $city = sanitize($_POST['city']);
        $coun = sanitize($_POST['country']);
        $stmt = $db->prepare("INSERT INTO customers (customer_code,company_name,contact_name,email,phone,address,city,country) VALUES(?,?,?,?,?,?,?,?)");
        $stmt->bind_param('ssssssss',$code,$comp,$cont,$em,$ph,$addr,$city,$coun);
        $stmt->execute();
        header('Location: customers.php?success=1'); exit;
    }
}
if (isset($_GET['delete'])) {
    $db->query("UPDATE customers SET status='inactive' WHERE id=".(int)$_GET['delete']);
    header('Location: customers.php?success=1'); exit;
}

$customers = $db->query("SELECT * FROM customers WHERE status='active' ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
?>

<div class="page-header">
  <div>
    <h1><i class="fa fa-handshake" style="margin-right:10px;color:var(--accent)"></i>Customers</h1>
    <p>Manage customer relationships and contacts</p>
  </div>
  <button class="btn btn-primary" onclick="openModal('custModal')">
    <i class="fa fa-user-plus"></i> Add Customer
  </button>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success"><i class="fa fa-circle-check"></i> Action completed.</div>
<?php endif; ?>

<div class="card">
  <div class="search-bar">
    <div class="search-input-wrap">
      <i class="fa fa-search"></i>
      <input type="text" placeholder="Search customers..." oninput="filterTable('custSearch','custTable')" id="custSearch">
    </div>
  </div>
  <div class="table-wrap">
    <table id="custTable">
      <thead>
        <tr><th>Code</th><th>Company</th><th>Contact</th><th>Email</th><th>Phone</th><th>City</th><th>Country</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php foreach ($customers as $c): ?>
      <tr>
        <td class="font-mono text-accent"><?= htmlspecialchars($c['customer_code']) ?></td>
        <td style="font-weight:600"><?= htmlspecialchars($c['company_name'] ?? '—') ?></td>
        <td><?= htmlspecialchars($c['contact_name'] ?? '—') ?></td>
        <td style="color:var(--text-secondary)"><?= htmlspecialchars($c['email'] ?? '—') ?></td>
        <td style="color:var(--text-secondary)"><?= htmlspecialchars($c['phone'] ?? '—') ?></td>
        <td><?= htmlspecialchars($c['city'] ?? '—') ?></td>
        <td><?= htmlspecialchars($c['country'] ?? '—') ?></td>
        <td>
          <a href="customers.php?delete=<?= $c['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Remove this customer?')"><i class="fa fa-trash"></i></a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($customers)): ?>
      <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:32px;">No customers found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Customer Modal -->
<div class="modal-overlay" id="custModal">
  <div class="modal">
    <div class="modal-header">
      <h2><i class="fa fa-user-plus" style="margin-right:8px;color:var(--accent)"></i>Add Customer</h2>
      <button class="modal-close" onclick="closeModal('custModal')"><i class="fa fa-xmark"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="form-grid">
        <div class="form-group">
          <label>Company Name</label>
          <input type="text" name="company_name" placeholder="Acme Corp">
        </div>
        <div class="form-group">
          <label>Contact Name</label>
          <input type="text" name="contact_name" placeholder="Jane Smith" required>
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" placeholder="contact@company.com">
        </div>
        <div class="form-group">
          <label>Phone</label>
          <input type="text" name="phone" placeholder="+1 234 567 8900">
        </div>
        <div class="form-group">
          <label>City</label>
          <input type="text" name="city" placeholder="New York">
        </div>
        <div class="form-group">
          <label>Country</label>
          <input type="text" name="country" placeholder="USA">
        </div>
      </div>
      <div class="form-group mt-2">
        <label>Address</label>
        <textarea name="address" placeholder="Full address..."></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('custModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Add Customer</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
