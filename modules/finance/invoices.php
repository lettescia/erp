<?php
require_once __DIR__ . '/../../includes/header.php';
$db = getDB();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $a = $_POST['action'];
    if ($a === 'create') {
        $no     = generateCode('INV-','invoices','invoice_no');
        $name   = sanitize($_POST['customer_name']);
        $email  = sanitize($_POST['customer_email']);
        $amount = floatval($_POST['amount']);
        $tax    = floatval($_POST['tax']);
        $total  = $amount + $tax;
        $status = sanitize($_POST['status']);
        $due    = sanitize($_POST['due_date']);
        $notes  = sanitize($_POST['notes']);
        $uid    = $_SESSION['user_id'];
        $stmt = $db->prepare("INSERT INTO invoices (invoice_no,customer_name,customer_email,amount,tax,total,status,due_date,notes,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('sssdddsssi',$no,$name,$email,$amount,$tax,$total,$status,$due,$notes,$uid);
        $stmt->execute();
        header('Location: invoices.php?success=1'); exit;
    }
    if ($a === 'mark_paid') {
        $id = (int)$_POST['id'];
        $db->query("UPDATE invoices SET status='paid', paid_date=CURDATE() WHERE id=$id");
        header('Location: invoices.php?success=1'); exit;
    }
}
if (isset($_GET['delete'])) {
    $db->query("DELETE FROM invoices WHERE id=".(int)$_GET['delete']);
    header('Location: invoices.php?success=1'); exit;
}

$invoices = $db->query("SELECT * FROM invoices ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
$statusColors = ['paid'=>'badge-green','sent'=>'badge-blue','draft'=>'badge-gray','overdue'=>'badge-red','cancelled'=>'badge-gray'];
?>

<div class="page-header">
  <div>
    <h1><i class="fa fa-file-invoice-dollar" style="margin-right:10px;color:var(--accent)"></i>Invoices</h1>
    <p>Manage customer invoices and billing</p>
  </div>
  <button class="btn btn-primary" onclick="openModal('invoiceModal')">
    <i class="fa fa-plus"></i> New Invoice
  </button>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success"><i class="fa fa-circle-check"></i> Action completed successfully.</div>
<?php endif; ?>

<div class="card">
  <div class="search-bar">
    <div class="search-input-wrap">
      <i class="fa fa-search"></i>
      <input type="text" placeholder="Search invoices..." oninput="filterTable('invSearch','invTable')" id="invSearch">
    </div>
  </div>
  <div class="table-wrap">
    <table id="invTable">
      <thead>
        <tr><th>Invoice #</th><th>Customer</th><th>Email</th><th>Amount</th><th>Tax</th><th>Total</th><th>Status</th><th>Due Date</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php foreach ($invoices as $inv): ?>
      <tr>
        <td class="font-mono text-accent"><?= htmlspecialchars($inv['invoice_no']) ?></td>
        <td><?= htmlspecialchars($inv['customer_name']) ?></td>
        <td style="color:var(--text-secondary)"><?= htmlspecialchars($inv['customer_email']) ?></td>
        <td>$<?= number_format($inv['amount'],2) ?></td>
        <td>$<?= number_format($inv['tax'],2) ?></td>
        <td style="font-weight:600">$<?= number_format($inv['total'],2) ?></td>
        <td><span class="badge <?= $statusColors[$inv['status']] ?? 'badge-gray' ?>"><?= ucfirst($inv['status']) ?></span></td>
        <td style="color:var(--text-secondary)"><?= $inv['due_date'] ?? '—' ?></td>
        <td>
          <div class="flex gap-2">
            <?php if ($inv['status'] !== 'paid'): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="mark_paid">
              <input type="hidden" name="id" value="<?= $inv['id'] ?>">
              <button type="submit" class="btn btn-secondary btn-sm" title="Mark Paid"><i class="fa fa-check"></i></button>
            </form>
            <?php endif; ?>
            <button class="btn btn-danger btn-sm" onclick="confirmDelete('/erp/modules/finance/invoices.php?delete=<?= $inv['id'] ?>')"><i class="fa fa-trash"></i></button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($invoices)): ?>
      <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:32px;">No invoices found. Create your first invoice!</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Create Invoice Modal -->
<div class="modal-overlay" id="invoiceModal">
  <div class="modal">
    <div class="modal-header">
      <h2><i class="fa fa-file-plus" style="margin-right:8px;color:var(--accent)"></i>New Invoice</h2>
      <button class="modal-close" onclick="closeModal('invoiceModal')"><i class="fa fa-xmark"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="form-grid">
        <div class="form-group">
          <label>Customer Name</label>
          <input type="text" name="customer_name" required placeholder="John Doe">
        </div>
        <div class="form-group">
          <label>Customer Email</label>
          <input type="email" name="customer_email" placeholder="john@example.com">
        </div>
        <div class="form-group">
          <label>Amount</label>
          <input type="number" name="amount" step="0.01" required placeholder="0.00">
        </div>
        <div class="form-group">
          <label>Tax</label>
          <input type="number" name="tax" step="0.01" value="0" placeholder="0.00">
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="status">
            <option value="draft">Draft</option>
            <option value="sent">Sent</option>
            <option value="paid">Paid</option>
          </select>
        </div>
        <div class="form-group">
          <label>Due Date</label>
          <input type="date" name="due_date">
        </div>
      </div>
      <div class="form-group mt-2">
        <label>Notes</label>
        <textarea name="notes" placeholder="Additional notes..."></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('invoiceModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Create Invoice</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
