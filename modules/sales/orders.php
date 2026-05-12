<?php
require_once __DIR__ . '/../../includes/header.php';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $no   = generateCode('SO-','sales_orders','order_no');
        $cid  = (int)$_POST['customer_id'];
        $od   = sanitize($_POST['order_date']);
        $dd   = sanitize($_POST['delivery_date']);
        $sub  = floatval($_POST['subtotal']);
        $tax  = floatval($_POST['tax']);
        $disc = floatval($_POST['discount']);
        $tot  = $sub + $tax - $disc;
        $st   = sanitize($_POST['status']);
        $notes= sanitize($_POST['notes']);
        $uid  = $_SESSION['user_id'];
        $stmt = $db->prepare("INSERT INTO sales_orders (order_no,customer_id,order_date,delivery_date,subtotal,tax,discount,total,status,notes,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('sissddddssi',$no,$cid,$od,$dd,$sub,$tax,$disc,$tot,$st,$notes,$uid);
        $stmt->execute();
        header('Location: orders.php?success=1'); exit;
    }
    if ($_POST['action'] === 'update_status') {
        $id = (int)$_POST['id'];
        $st = sanitize($_POST['status']);
        $stmt = $db->prepare("UPDATE sales_orders SET status=? WHERE id=?");
        $stmt->bind_param('si',$st,$id);
        $stmt->execute();
        header('Location: orders.php?success=1'); exit;
    }
}
if (isset($_GET['delete'])) {
    $db->query("UPDATE sales_orders SET status='cancelled' WHERE id=".(int)$_GET['delete']);
    header('Location: orders.php?success=1'); exit;
}

$orders = $db->query("SELECT so.*, c.company_name, c.contact_name FROM sales_orders so LEFT JOIN customers c ON so.customer_id=c.id ORDER BY so.created_at DESC")->fetch_all(MYSQLI_ASSOC);
$customers = $db->query("SELECT * FROM customers WHERE status='active' ORDER BY company_name")->fetch_all(MYSQLI_ASSOC);
$statusColors = ['pending'=>'badge-amber','confirmed'=>'badge-teal','processing'=>'badge-blue','shipped'=>'badge-blue','delivered'=>'badge-green','cancelled'=>'badge-gray'];
?>

<div class="page-header">
  <div>
    <h1><i class="fa fa-cart-shopping" style="margin-right:10px;color:var(--accent)"></i>Sales Orders</h1>
    <p>Track and manage customer orders</p>
  </div>
  <button class="btn btn-primary" onclick="openModal('orderModal')">
    <i class="fa fa-plus"></i> New Order
  </button>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success"><i class="fa fa-circle-check"></i> Action completed.</div>
<?php endif; ?>

<div class="card">
  <div class="search-bar">
    <div class="search-input-wrap">
      <i class="fa fa-search"></i>
      <input type="text" placeholder="Search orders..." oninput="filterTable('ordSearch','ordTable')" id="ordSearch">
    </div>
  </div>
  <div class="table-wrap">
    <table id="ordTable">
      <thead>
        <tr><th>Order #</th><th>Customer</th><th>Order Date</th><th>Delivery</th><th>Subtotal</th><th>Total</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php foreach ($orders as $o): ?>
      <tr>
        <td class="font-mono text-accent"><?= htmlspecialchars($o['order_no']) ?></td>
        <td style="font-weight:600"><?= htmlspecialchars($o['company_name'] ?? $o['contact_name'] ?? 'N/A') ?></td>
        <td style="color:var(--text-secondary)"><?= $o['order_date'] ?></td>
        <td style="color:var(--text-secondary)"><?= $o['delivery_date'] ?? '—' ?></td>
        <td>$<?= number_format($o['subtotal'],2) ?></td>
        <td style="font-weight:600">$<?= number_format($o['total'],2) ?></td>
        <td><span class="badge <?= $statusColors[$o['status']] ?? 'badge-gray' ?>"><?= ucfirst($o['status']) ?></span></td>
        <td>
          <div class="flex gap-2">
            <form method="POST" style="display:inline;display:flex;align-items:center;gap:6px;">
              <input type="hidden" name="action" value="update_status">
              <input type="hidden" name="id" value="<?= $o['id'] ?>">
              <select name="status" class="btn btn-secondary btn-sm" style="padding:5px 8px;font-size:0.78rem;" onchange="this.form.submit()">
                <?php foreach (['pending','confirmed','processing','shipped','delivered','cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $o['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($orders)): ?>
      <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:32px;">No orders found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- New Order Modal -->
<div class="modal-overlay" id="orderModal">
  <div class="modal">
    <div class="modal-header">
      <h2><i class="fa fa-cart-plus" style="margin-right:8px;color:var(--accent)"></i>New Sales Order</h2>
      <button class="modal-close" onclick="closeModal('orderModal')"><i class="fa fa-xmark"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="form-grid">
        <div class="form-group">
          <label>Customer</label>
          <select name="customer_id" required>
            <option value="">— Select Customer —</option>
            <?php foreach ($customers as $c): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name'] ?: $c['contact_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="status">
            <option value="pending">Pending</option>
            <option value="confirmed">Confirmed</option>
          </select>
        </div>
        <div class="form-group">
          <label>Order Date</label>
          <input type="date" name="order_date" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="form-group">
          <label>Delivery Date</label>
          <input type="date" name="delivery_date">
        </div>
        <div class="form-group">
          <label>Subtotal ($)</label>
          <input type="number" name="subtotal" step="0.01" required placeholder="0.00">
        </div>
        <div class="form-group">
          <label>Tax ($)</label>
          <input type="number" name="tax" step="0.01" value="0" placeholder="0.00">
        </div>
        <div class="form-group">
          <label>Discount ($)</label>
          <input type="number" name="discount" step="0.01" value="0" placeholder="0.00">
        </div>
      </div>
      <div class="form-group mt-2">
        <label>Notes</label>
        <textarea name="notes" placeholder="Order notes..."></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('orderModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Create Order</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
