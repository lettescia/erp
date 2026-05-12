<?php
require_once __DIR__ . '/../../includes/header.php';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $sku  = generateCode('SKU-','products','sku');
        $name = sanitize($_POST['name']);
        $cat  = (int)$_POST['category_id'];
        $desc = sanitize($_POST['description']);
        $up   = floatval($_POST['unit_price']);
        $cp   = floatval($_POST['cost_price']);
        $qty  = (int)$_POST['quantity'];
        $rl   = (int)$_POST['reorder_level'];
        $unit = sanitize($_POST['unit']);
        $stmt = $db->prepare("INSERT INTO products (sku,name,category_id,description,unit_price,cost_price,quantity,reorder_level,unit) VALUES(?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('ssissddis',$sku,$name,$cat,$desc,$up,$cp,$qty,$rl,$unit);
        $stmt->execute();
        header('Location: products.php?success=1'); exit;
    }
}
if (isset($_GET['delete'])) {
    $db->query("UPDATE products SET status='inactive' WHERE id=".(int)$_GET['delete']);
    header('Location: products.php?success=1'); exit;
}

$products = $db->query("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.status='active' ORDER BY p.created_at DESC")->fetch_all(MYSQLI_ASSOC);
$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);
?>

<div class="page-header">
  <div>
    <h1><i class="fa fa-boxes-stacked" style="margin-right:10px;color:var(--accent)"></i>Products</h1>
    <p>Manage inventory products and stock levels</p>
  </div>
  <button class="btn btn-primary" onclick="openModal('productModal')">
    <i class="fa fa-plus"></i> Add Product
  </button>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success"><i class="fa fa-circle-check"></i> Action completed.</div>
<?php endif; ?>

<!-- Low stock warning -->
<?php $lowCount = array_filter($products, fn($p) => $p['quantity'] <= $p['reorder_level']);
if (count($lowCount) > 0): ?>
<div class="alert alert-error"><i class="fa fa-triangle-exclamation"></i> <?= count($lowCount) ?> product(s) are at or below reorder level!</div>
<?php endif; ?>

<div class="card">
  <div class="search-bar">
    <div class="search-input-wrap">
      <i class="fa fa-search"></i>
      <input type="text" placeholder="Search products..." oninput="filterTable('prodSearch','prodTable')" id="prodSearch">
    </div>
  </div>
  <div class="table-wrap">
    <table id="prodTable">
      <thead>
        <tr><th>SKU</th><th>Name</th><th>Category</th><th>Unit Price</th><th>Cost Price</th><th>Qty</th><th>Reorder Lvl</th><th>Unit</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php foreach ($products as $p): ?>
      <?php $lowStock = $p['quantity'] <= $p['reorder_level']; ?>
      <tr>
        <td class="font-mono text-accent"><?= htmlspecialchars($p['sku']) ?></td>
        <td style="font-weight:600"><?= htmlspecialchars($p['name']) ?></td>
        <td><?= htmlspecialchars($p['cat_name'] ?? '—') ?></td>
        <td>$<?= number_format($p['unit_price'],2) ?></td>
        <td>$<?= number_format($p['cost_price'],2) ?></td>
        <td>
          <span class="badge <?= $lowStock ? 'badge-red' : 'badge-green' ?>"><?= $p['quantity'] ?></span>
        </td>
        <td style="color:var(--text-secondary)"><?= $p['reorder_level'] ?></td>
        <td style="color:var(--text-secondary)"><?= htmlspecialchars($p['unit']) ?></td>
        <td>
          <a href="products.php?delete=<?= $p['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Deactivate this product?')"><i class="fa fa-trash"></i></a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($products)): ?>
      <tr><td colspan="9" style="text-align:center;color:var(--text-muted);padding:32px;">No products found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Product Modal -->
<div class="modal-overlay" id="productModal">
  <div class="modal">
    <div class="modal-header">
      <h2><i class="fa fa-box" style="margin-right:8px;color:var(--accent)"></i>Add Product</h2>
      <button class="modal-close" onclick="closeModal('productModal')"><i class="fa fa-xmark"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="form-grid">
        <div class="form-group">
          <label>Product Name</label>
          <input type="text" name="name" required placeholder="Product name">
        </div>
        <div class="form-group">
          <label>Category</label>
          <select name="category_id">
            <option value="">— Select —</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Unit Price ($)</label>
          <input type="number" name="unit_price" step="0.01" required placeholder="0.00">
        </div>
        <div class="form-group">
          <label>Cost Price ($)</label>
          <input type="number" name="cost_price" step="0.01" placeholder="0.00" value="0">
        </div>
        <div class="form-group">
          <label>Initial Quantity</label>
          <input type="number" name="quantity" value="0" min="0">
        </div>
        <div class="form-group">
          <label>Reorder Level</label>
          <input type="number" name="reorder_level" value="10" min="0">
        </div>
        <div class="form-group">
          <label>Unit</label>
          <input type="text" name="unit" value="pcs" placeholder="pcs, kg, litre...">
        </div>
      </div>
      <div class="form-group mt-2">
        <label>Description</label>
        <textarea name="description" placeholder="Product description..."></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('productModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Add Product</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
