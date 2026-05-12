<?php
require_once __DIR__ . '/../../includes/header.php';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $no   = generateCode('PO-', 'purchase_orders', 'po_no');
        $sid  = (int)$_POST['supplier_id'];
        $od   = sanitize($_POST['order_date']);
        $ed   = sanitize($_POST['expected_date']);
        $sub  = floatval($_POST['subtotal']);
        $tax  = floatval($_POST['tax']);
        $tot  = $sub + $tax;
        $st   = sanitize($_POST['status']);
        $notes= sanitize($_POST['notes']);
        $uid  = (int)$_SESSION['user_id'];

        $stmt = $db->prepare("INSERT INTO purchase_orders (po_no,supplier_id,order_date,expected_date,subtotal,tax,total,status,notes,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('sissdddssi', $no, $sid, $od, $ed, $sub, $tax, $tot, $st, $notes, $uid);
        $stmt->execute();
        header('Location: purchase_orders.php?success=1'); exit;
    }
    if ($_POST['action'] === 'update_status') {
        $id = (int)$_POST['id'];
        $st = sanitize($_POST['status']);
        $stmt = $db->prepare("UPDATE purchase_orders SET status=? WHERE id=?");
        $stmt->bind_param('si', $st, $id);
        $stmt->execute();
        header('Location: purchase_orders.php?success=1'); exit;
    }
    if ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        $db->query("DELETE FROM purchase_orders WHERE id=$id AND status='draft'");
        header('Location: purchase_orders.php?success=1'); exit;
    }
}

$orders = $db->query("
    SELECT po.*, s.company_name as supplier_name, u.name as created_by_name
    FROM purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.id
    LEFT JOIN users u ON po.created_by = u.id
    ORDER BY po.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$suppliers = $db->query("SELECT * FROM suppliers WHERE status='active' ORDER BY company_name")->fetch_all(MYSQLI_ASSOC);

$statusColors = ['draft'=>'badge-gray','sent'=>'badge-blue','received'=>'badge-green','cancelled'=>'badge-red'];

// Totals
$totalPOs = count($orders);
$totalValue = array_sum(array_column($orders,'total'));
$pending = count(array_filter($orders, fn($o) => in_array($o['status'],['draft','sent'])));
$received = count(array_filter($orders, fn($o) => $o['status']==='received'));
?>

<div class="page-header">
    <div>
        <h1><i class="fa fa-clipboard-list" style="margin-right:10px;color:var(--accent)"></i>Purchase Orders</h1>
        <p>Manage procurement and supplier orders</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('poModal')">
        <i class="fa fa-plus"></i> New Purchase Order
    </button>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success"><i class="fa fa-circle-check"></i> Action completed.</div>
<?php endif; ?>

<!-- Stats -->
<div class="stat-grid" style="margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fa fa-clipboard-list"></i></div>
        <div class="stat-info"><div class="label">Total POs</div><div class="value"><?= $totalPOs ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><i class="fa fa-dollar-sign"></i></div>
        <div class="stat-info"><div class="label">Total Value</div><div class="value">$<?= number_format($totalValue, 0) ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fa fa-clock"></i></div>
        <div class="stat-info"><div class="label">Pending</div><div class="value"><?= $pending ?></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fa fa-circle-check"></i></div>
        <div class="stat-info"><div class="label">Received</div><div class="value"><?= $received ?></div></div>
    </div>
</div>

<div class="card">
    <div class="search-bar">
        <div class="search-input-wrap">
            <i class="fa fa-search"></i>
            <input type="text" id="poSearch" placeholder="Search purchase orders..." oninput="filterTable('poSearch','poTable')">
        </div>
    </div>
    <div class="table-wrap">
        <table id="poTable">
            <thead>
                <tr>
                    <th>PO Number</th>
                    <th>Supplier</th>
                    <th>Order Date</th>
                    <th>Expected</th>
                    <th>Subtotal</th>
                    <th>Tax</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
            <tr>
                <td class="font-mono text-accent"><?= htmlspecialchars($o['po_no']) ?></td>
                <td style="font-weight:600"><?= htmlspecialchars($o['supplier_name'] ?? '—') ?></td>
                <td style="color:var(--text-secondary)"><?= $o['order_date'] ?></td>
                <td style="color:var(--text-secondary)"><?= $o['expected_date'] ?? '—' ?></td>
                <td>$<?= number_format($o['subtotal'], 2) ?></td>
                <td>$<?= number_format($o['tax'], 2) ?></td>
                <td style="font-weight:700">$<?= number_format($o['total'], 2) ?></td>
                <td><span class="badge <?= $statusColors[$o['status']] ?? 'badge-gray' ?>"><?= ucfirst($o['status']) ?></span></td>
                <td>
                    <div class="flex gap-2">
                        <form method="POST" style="display:flex;align-items:center;gap:6px;">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="id" value="<?= $o['id'] ?>">
                            <select name="status" onchange="this.form.submit()"
                                style="background:var(--bg-hover);border:1px solid var(--border-light);border-radius:6px;padding:4px 8px;color:var(--text-primary);font-size:0.78rem;outline:none;">
                                <?php foreach (['draft','sent','received','cancelled'] as $s): ?>
                                <option value="<?= $s ?>" <?= $o['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <?php if ($o['status'] === 'draft'): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $o['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this draft PO?')">
                                <i class="fa fa-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($orders)): ?>
            <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:40px;">No purchase orders found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- New PO Modal -->
<div class="modal-overlay" id="poModal">
    <div class="modal">
        <div class="modal-header">
            <h2><i class="fa fa-clipboard-list" style="margin-right:8px;color:var(--accent)"></i>New Purchase Order</h2>
            <button class="modal-close" onclick="closeModal('poModal')"><i class="fa fa-xmark"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-grid">
                <div class="form-group">
                    <label>Supplier</label>
                    <select name="supplier_id" required>
                        <option value="">— Select Supplier —</option>
                        <?php foreach ($suppliers as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="draft">Draft</option>
                        <option value="sent">Sent to Supplier</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Order Date</label>
                    <input type="date" name="order_date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="form-group">
                    <label>Expected Delivery</label>
                    <input type="date" name="expected_date">
                </div>
                <div class="form-group">
                    <label>Subtotal ($)</label>
                    <input type="number" name="subtotal" step="0.01" required placeholder="0.00" id="po_sub"
                        oninput="calcPOTotal()">
                </div>
                <div class="form-group">
                    <label>Tax ($)</label>
                    <input type="number" name="tax" step="0.01" value="0" id="po_tax" oninput="calcPOTotal()">
                </div>
            </div>
            <div style="background:var(--bg-hover);border-radius:8px;padding:14px;margin-top:14px;text-align:right;">
                <span style="font-size:0.85rem;color:var(--text-secondary)">Total: </span>
                <span id="po_total_display" style="font-family:'Syne',sans-serif;font-weight:700;font-size:1.1rem;color:var(--accent)">$0.00</span>
            </div>
            <div class="form-group mt-2">
                <label>Notes</label>
                <textarea name="notes" placeholder="Order notes or special instructions..." rows="2"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('poModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Create PO</button>
            </div>
        </form>
    </div>
</div>

<script>
function calcPOTotal() {
    const sub = parseFloat(document.getElementById('po_sub').value) || 0;
    const tax = parseFloat(document.getElementById('po_tax').value) || 0;
    document.getElementById('po_total_display').textContent = '$' + (sub + tax).toLocaleString('en-US', {minimumFractionDigits:2});
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
