<?php
require_once __DIR__ . '/../../includes/header.php';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'adjust') {
        $pid   = (int)$_POST['product_id'];
        $type  = sanitize($_POST['type']);
        $qty   = (int)$_POST['quantity'];
        $ref   = sanitize($_POST['reference']);
        $notes = sanitize($_POST['notes']);
        $uid   = (int)$_SESSION['user_id'];

        // Record movement
        $stmt = $db->prepare("INSERT INTO stock_movements (product_id,type,quantity,reference,notes,created_by) VALUES(?,?,?,?,?,?)");
        $stmt->bind_param('isissi', $pid, $type, $qty, $ref, $notes, $uid);
        $stmt->execute();

        // Update product quantity
        if ($type === 'in') {
            $db->query("UPDATE products SET quantity = quantity + $qty WHERE id = $pid");
        } elseif ($type === 'out') {
            $db->query("UPDATE products SET quantity = GREATEST(0, quantity - $qty) WHERE id = $pid");
        } else { // adjustment
            $db->query("UPDATE products SET quantity = $qty WHERE id = $pid");
        }

        header('Location: stock.php?success=1'); exit;
    }
}

$products = $db->query("
    SELECT p.*, c.name as cat_name,
           (SELECT SUM(CASE WHEN type='in' THEN quantity ELSE 0 END) FROM stock_movements WHERE product_id=p.id) as total_in,
           (SELECT SUM(CASE WHEN type='out' THEN quantity ELSE 0 END) FROM stock_movements WHERE product_id=p.id) as total_out
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.status = 'active'
    ORDER BY p.name
")->fetch_all(MYSQLI_ASSOC);

$movements = $db->query("
    SELECT sm.*, p.name as product_name, p.sku, u.name as by_name
    FROM stock_movements sm
    JOIN products p ON sm.product_id = p.id
    LEFT JOIN users u ON sm.created_by = u.id
    ORDER BY sm.created_at DESC LIMIT 50
")->fetch_all(MYSQLI_ASSOC);

$lowStock = array_filter($products, fn($p) => $p['quantity'] <= $p['reorder_level']);
?>

<div class="page-header">
    <div>
        <h1><i class="fa fa-warehouse" style="margin-right:10px;color:var(--accent)"></i>Stock Management</h1>
        <p>Track inventory levels and stock movements</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('adjustModal')">
        <i class="fa fa-boxes-stacked"></i> Stock Adjustment
    </button>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success"><i class="fa fa-circle-check"></i> Stock updated successfully.</div>
<?php endif; ?>

<!-- Low Stock Alert -->
<?php if (count($lowStock) > 0): ?>
<div class="alert alert-error">
    <i class="fa fa-triangle-exclamation"></i>
    <strong><?= count($lowStock) ?> product(s) are below reorder level!</strong>
    &nbsp;—&nbsp;
    <?= implode(', ', array_map(fn($p) => htmlspecialchars($p['name']), array_slice($lowStock, 0, 3))) ?>
    <?= count($lowStock) > 3 ? ' and more...' : '' ?>
</div>
<?php endif; ?>

<div class="charts-row">
    <!-- Current Stock Levels -->
    <div class="card" style="flex:1.5">
        <div class="card-header">
            <h2><i class="fa fa-cubes" style="margin-right:8px"></i>Current Stock Levels</h2>
            <div class="search-input-wrap" style="width:200px">
                <i class="fa fa-search"></i>
                <input type="text" id="stockSearch" placeholder="Search..." oninput="filterTable('stockSearch','stockTable')">
            </div>
        </div>
        <div class="table-wrap">
            <table id="stockTable">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th>In Stock</th>
                        <th>Reorder Lvl</th>
                        <th>Total In</th>
                        <th>Total Out</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $p):
                    $isLow = $p['quantity'] <= $p['reorder_level'];
                ?>
                <tr>
                    <td class="font-mono text-accent"><?= htmlspecialchars($p['sku']) ?></td>
                    <td style="font-weight:600"><?= htmlspecialchars($p['name']) ?></td>
                    <td style="color:var(--text-secondary)"><?= htmlspecialchars($p['cat_name'] ?? '—') ?></td>
                    <td>
                        <span style="font-family:monospace;font-weight:700;font-size:1rem;color:<?= $isLow ? 'var(--accent-danger)' : 'var(--accent-success)' ?>">
                            <?= number_format($p['quantity']) ?>
                        </span>
                        <span style="font-size:0.75rem;color:var(--text-muted)"> <?= $p['unit'] ?></span>
                    </td>
                    <td style="color:var(--text-secondary)"><?= $p['reorder_level'] ?></td>
                    <td style="color:var(--accent-success)">+<?= number_format($p['total_in'] ?? 0) ?></td>
                    <td style="color:var(--accent-danger)">-<?= number_format($p['total_out'] ?? 0) ?></td>
                    <td>
                        <?php if ($isLow): ?>
                        <span class="badge badge-red"><i class="fa fa-triangle-exclamation"></i> Low</span>
                        <?php else: ?>
                        <span class="badge badge-green"><i class="fa fa-circle-check"></i> OK</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($products)): ?>
                <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:32px;">No products found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Recent Movements -->
<div class="card" style="margin-top:20px">
    <div class="card-header">
        <h2><i class="fa fa-arrow-right-arrow-left" style="margin-right:8px"></i>Recent Stock Movements</h2>
        <span style="font-size:0.8rem;color:var(--text-secondary)">Last 50 records</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Date</th><th>SKU</th><th>Product</th><th>Type</th><th>Qty</th><th>Reference</th><th>Notes</th><th>By</th></tr>
            </thead>
            <tbody>
            <?php foreach ($movements as $m): ?>
            <tr>
                <td style="color:var(--text-secondary);white-space:nowrap"><?= date('d M Y H:i', strtotime($m['created_at'])) ?></td>
                <td class="font-mono text-accent"><?= htmlspecialchars($m['sku']) ?></td>
                <td style="font-weight:600"><?= htmlspecialchars($m['product_name']) ?></td>
                <td>
                    <?php
                    $typeLabel = ['in'=>['badge-green','fa-arrow-down','Stock In'],
                                  'out'=>['badge-red','fa-arrow-up','Stock Out'],
                                  'adjustment'=>['badge-blue','fa-sliders','Adjustment']];
                    [$bc,$ic,$lbl] = $typeLabel[$m['type']] ?? ['badge-gray','fa-circle','Unknown'];
                    ?>
                    <span class="badge <?= $bc ?>"><i class="fa <?= $ic ?>"></i> <?= $lbl ?></span>
                </td>
                <td style="font-weight:700;font-family:monospace;color:<?= $m['type']==='in'?'var(--accent-success)':'var(--accent-danger)' ?>">
                    <?= $m['type']==='in' ? '+' : ($m['type']==='out' ? '-' : '') ?><?= number_format($m['quantity']) ?>
                </td>
                <td class="font-mono" style="font-size:0.8rem;color:var(--text-secondary)"><?= htmlspecialchars($m['reference'] ?? '—') ?></td>
                <td style="color:var(--text-secondary);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                    <?= htmlspecialchars($m['notes'] ?? '—') ?>
                </td>
                <td style="color:var(--text-secondary)"><?= htmlspecialchars($m['by_name'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($movements)): ?>
            <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:32px;">No stock movements recorded.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Stock Adjustment Modal -->
<div class="modal-overlay" id="adjustModal">
    <div class="modal" style="max-width:500px">
        <div class="modal-header">
            <h2><i class="fa fa-sliders" style="margin-right:8px;color:var(--accent)"></i>Stock Adjustment</h2>
            <button class="modal-close" onclick="closeModal('adjustModal')"><i class="fa fa-xmark"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="adjust">
            <div class="form-grid">
                <div class="form-group">
                    <label>Product</label>
                    <select name="product_id" required>
                        <option value="">— Select Product —</option>
                        <?php foreach ($products as $p): ?>
                        <option value="<?= $p['id'] ?>">
                            [<?= htmlspecialchars($p['sku']) ?>] <?= htmlspecialchars($p['name']) ?> (<?= $p['quantity'] ?> <?= $p['unit'] ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Movement Type</label>
                    <select name="type" required>
                        <option value="in">Stock In (Add)</option>
                        <option value="out">Stock Out (Remove)</option>
                        <option value="adjustment">Adjustment (Set to)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" name="quantity" min="1" required placeholder="0">
                </div>
                <div class="form-group">
                    <label>Reference</label>
                    <input type="text" name="reference" placeholder="PO-001, SO-123...">
                </div>
            </div>
            <div class="form-group mt-2">
                <label>Notes</label>
                <textarea name="notes" placeholder="Reason for adjustment..." rows="2"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('adjustModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Apply Adjustment</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
