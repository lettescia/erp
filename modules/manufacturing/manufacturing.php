<?php
require_once __DIR__ . '/../../includes/header.php';
$db = getDB();

// Ensure manufacturing tables exist
$db->query("CREATE TABLE IF NOT EXISTS work_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    wo_no VARCHAR(50) UNIQUE NOT NULL,
    product_name VARCHAR(150) NOT NULL,
    quantity INT NOT NULL,
    unit VARCHAR(30) DEFAULT 'pcs',
    start_date DATE,
    end_date DATE,
    status ENUM('planned','in-progress','quality-check','completed','cancelled') DEFAULT 'planned',
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
)");

$db->query("CREATE TABLE IF NOT EXISTS bom_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    work_order_id INT,
    material_name VARCHAR(150) NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit VARCHAR(30) DEFAULT 'pcs',
    available DECIMAL(10,2) DEFAULT 0,
    status ENUM('available','partial','shortage') DEFAULT 'available',
    FOREIGN KEY (work_order_id) REFERENCES work_orders(id) ON DELETE CASCADE
)");

$db->query("CREATE TABLE IF NOT EXISTS production_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    work_order_id INT,
    stage VARCHAR(100) NOT NULL,
    quantity_done INT DEFAULT 0,
    notes TEXT,
    logged_by INT,
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (work_order_id) REFERENCES work_orders(id),
    FOREIGN KEY (logged_by) REFERENCES users(id)
)");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_wo') {
        $no   = generateCode('WO-','work_orders','wo_no');
        $prod = sanitize($_POST['product_name']);
        $qty  = (int)$_POST['quantity'];
        $unit = sanitize($_POST['unit']);
        $sd   = sanitize($_POST['start_date']);
        $ed   = sanitize($_POST['end_date']);
        $st   = sanitize($_POST['status']);
        $pri  = sanitize($_POST['priority']);
        $notes= sanitize($_POST['notes']);
        $uid  = (int)$_SESSION['user_id'];

        $stmt = $db->prepare("INSERT INTO work_orders (wo_no,product_name,quantity,unit,start_date,end_date,status,priority,notes,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('sisssssssi', $no,$prod,$qty,$unit,$sd,$ed,$st,$pri,$notes,$uid);
        $stmt->execute();
        header('Location: manufacturing.php?success=1'); exit;
    }
    if ($_POST['action'] === 'update_status') {
        $id = (int)$_POST['id'];
        $st = sanitize($_POST['status']);
        $stmt = $db->prepare("UPDATE work_orders SET status=? WHERE id=?");
        $stmt->bind_param('si',$st,$id);
        $stmt->execute();
        header('Location: manufacturing.php?success=1'); exit;
    }
    if ($_POST['action'] === 'log_production') {
        $wid   = (int)$_POST['work_order_id'];
        $stage = sanitize($_POST['stage']);
        $done  = (int)$_POST['quantity_done'];
        $notes = sanitize($_POST['log_notes']);
        $uid   = (int)$_SESSION['user_id'];
        $stmt  = $db->prepare("INSERT INTO production_log (work_order_id,stage,quantity_done,notes,logged_by) VALUES(?,?,?,?,?)");
        $stmt->bind_param('isisi',$wid,$stage,$done,$notes,$uid);
        $stmt->execute();
        header('Location: manufacturing.php?success=1'); exit;
    }
    if ($_POST['action'] === 'delete_wo') {
        $id = (int)$_POST['id'];
        $db->query("DELETE FROM bom_items WHERE work_order_id=$id");
        $db->query("DELETE FROM production_log WHERE work_order_id=$id");
        $db->query("DELETE FROM work_orders WHERE id=$id");
        header('Location: manufacturing.php?success=1'); exit;
    }
}

$workOrders = $db->query("
    SELECT wo.*, u.name as creator,
        (SELECT SUM(quantity_done) FROM production_log WHERE work_order_id=wo.id) as produced
    FROM work_orders wo
    LEFT JOIN users u ON wo.created_by = u.id
    ORDER BY wo.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$recentLog = $db->query("
    SELECT pl.*, wo.wo_no, wo.product_name, u.name as logged_by_name
    FROM production_log pl
    JOIN work_orders wo ON pl.work_order_id = wo.id
    LEFT JOIN users u ON pl.logged_by = u.id
    ORDER BY pl.logged_at DESC LIMIT 20
")->fetch_all(MYSQLI_ASSOC);

$statusColors   = ['planned'=>'badge-amber','in-progress'=>'badge-blue','quality-check'=>'badge-teal','completed'=>'badge-green','cancelled'=>'badge-gray'];
$priorityColors = ['low'=>'badge-gray','medium'=>'badge-teal','high'=>'badge-amber','urgent'=>'badge-red'];

// KPIs
$total     = count($workOrders);
$active    = count(array_filter($workOrders, fn($w) => $w['status']==='in-progress'));
$completed = count(array_filter($workOrders, fn($w) => $w['status']==='completed'));
$planned   = count(array_filter($workOrders, fn($w) => $w['status']==='planned'));
?>

<div class="page-header">
    <div>
        <h1><i class="fa fa-industry" style="margin-right:10px;color:var(--accent)"></i>Manufacturing</h1>
        <p>Work orders, production planning, and BOM management</p>
    </div>
    <div style="display:flex;gap:10px;">
        <button class="btn btn-secondary" onclick="openModal('logModal')">
            <i class="fa fa-clipboard-check"></i> Log Production
        </button>
        <button class="btn btn-primary" onclick="openModal('woModal')">
            <i class="fa fa-plus"></i> New Work Order
        </button>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success"><i class="fa fa-circle-check"></i> Action completed.</div>
<?php endif; ?>

<!-- KPI Cards -->
<div class="stat-grid" style="margin-bottom:24px;">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fa fa-file-lines"></i></div>
        <div class="stat-info">
            <div class="label">Total Work Orders</div>
            <div class="value"><?= $total ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><i class="fa fa-clock"></i></div>
        <div class="stat-info">
            <div class="label">Planned</div>
            <div class="value"><?= $planned ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon teal"><i class="fa fa-gears"></i></div>
        <div class="stat-info">
            <div class="label">In Production</div>
            <div class="value"><?= $active ?></div>
            <div class="change up"><i class="fa fa-circle-half-stroke"></i> Active</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fa fa-circle-check"></i></div>
        <div class="stat-info">
            <div class="label">Completed</div>
            <div class="value"><?= $completed ?></div>
        </div>
    </div>
</div>

<!-- Work Orders Table -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <h2><i class="fa fa-list-check" style="margin-right:8px"></i>Work Orders</h2>
        <div class="search-input-wrap" style="width:220px">
            <i class="fa fa-search"></i>
            <input type="text" id="woSearch" placeholder="Search..." oninput="filterTable('woSearch','woTable')">
        </div>
    </div>
    <div class="table-wrap">
        <table id="woTable">
            <thead>
                <tr>
                    <th>WO #</th><th>Product</th><th>Qty</th><th>Produced</th>
                    <th>Progress</th><th>Priority</th><th>Start</th><th>End</th>
                    <th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($workOrders as $wo):
                $produced = (int)($wo['produced'] ?? 0);
                $pct = $wo['quantity'] > 0 ? min(100, round($produced / $wo['quantity'] * 100)) : 0;
            ?>
            <tr>
                <td class="font-mono text-accent"><?= htmlspecialchars($wo['wo_no']) ?></td>
                <td style="font-weight:600"><?= htmlspecialchars($wo['product_name']) ?></td>
                <td><?= number_format($wo['quantity']) ?> <?= htmlspecialchars($wo['unit']) ?></td>
                <td style="color:var(--accent-success)"><?= number_format($produced) ?></td>
                <td style="min-width:100px">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div class="progress-bar" style="flex:1">
                            <div class="progress-fill" style="width:<?= $pct ?>%"></div>
                        </div>
                        <span style="font-size:0.72rem;color:var(--text-secondary);white-space:nowrap"><?= $pct ?>%</span>
                    </div>
                </td>
                <td><span class="badge <?= $priorityColors[$wo['priority']] ?>"><?= ucfirst($wo['priority']) ?></span></td>
                <td style="color:var(--text-secondary)"><?= $wo['start_date'] ?? '—' ?></td>
                <td style="color:<?= $wo['end_date']&&strtotime($wo['end_date'])<time()&&!in_array($wo['status'],['completed','cancelled'])?'var(--accent-danger)':'var(--text-secondary)' ?>">
                    <?= $wo['end_date'] ?? '—' ?>
                </td>
                <td>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="id" value="<?= $wo['id'] ?>">
                        <select name="status" onchange="this.form.submit()"
                            style="background:var(--bg-hover);border:1px solid var(--border-light);border-radius:6px;padding:4px 8px;color:var(--text-primary);font-size:0.78rem;outline:none;">
                            <?php foreach (['planned','in-progress','quality-check','completed','cancelled'] as $s): ?>
                            <option value="<?= $s ?>" <?= $wo['status']===$s?'selected':'' ?>><?= ucwords(str_replace('-',' ',$s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </td>
                <td>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="delete_wo">
                        <input type="hidden" name="id" value="<?= $wo['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this work order?')">
                            <i class="fa fa-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($workOrders)): ?>
            <tr><td colspan="10" style="text-align:center;color:var(--text-muted);padding:40px;">
                <i class="fa fa-industry" style="font-size:2rem;display:block;margin-bottom:10px"></i>
                No work orders yet.
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Production Log -->
<div class="card">
    <div class="card-header">
        <h2><i class="fa fa-clipboard-check" style="margin-right:8px"></i>Production Log</h2>
        <span style="font-size:0.8rem;color:var(--text-secondary)">Last 20 entries</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Date/Time</th><th>Work Order</th><th>Product</th><th>Stage</th><th>Qty Done</th><th>Notes</th><th>Logged By</th></tr>
            </thead>
            <tbody>
            <?php foreach ($recentLog as $log): ?>
            <tr>
                <td style="color:var(--text-secondary);white-space:nowrap"><?= date('d M Y H:i', strtotime($log['logged_at'])) ?></td>
                <td class="font-mono text-accent"><?= htmlspecialchars($log['wo_no']) ?></td>
                <td style="font-weight:600"><?= htmlspecialchars($log['product_name']) ?></td>
                <td><?= htmlspecialchars($log['stage']) ?></td>
                <td style="color:var(--accent-success);font-weight:700"><?= number_format($log['quantity_done']) ?></td>
                <td style="color:var(--text-secondary);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                    <?= htmlspecialchars($log['notes'] ?? '—') ?>
                </td>
                <td style="color:var(--text-secondary)"><?= htmlspecialchars($log['logged_by_name'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recentLog)): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:32px;">No production logs yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- New Work Order Modal -->
<div class="modal-overlay" id="woModal">
    <div class="modal">
        <div class="modal-header">
            <h2><i class="fa fa-industry" style="margin-right:8px;color:var(--accent)"></i>New Work Order</h2>
            <button class="modal-close" onclick="closeModal('woModal')"><i class="fa fa-xmark"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create_wo">
            <div class="form-grid">
                <div class="form-group" style="grid-column:1/-1">
                    <label>Product / Item to Manufacture</label>
                    <input type="text" name="product_name" required placeholder="e.g. Steel Frame Type A">
                </div>
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" name="quantity" min="1" required placeholder="100">
                </div>
                <div class="form-group">
                    <label>Unit</label>
                    <input type="text" name="unit" value="pcs" placeholder="pcs, kg, units...">
                </div>
                <div class="form-group">
                    <label>Priority</label>
                    <select name="priority">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="planned">Planned</option>
                        <option value="in-progress">In Progress</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label>End Date</label>
                    <input type="date" name="end_date">
                </div>
            </div>
            <div class="form-group mt-2">
                <label>Notes / Instructions</label>
                <textarea name="notes" placeholder="Production notes, material requirements..." rows="3"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('woModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Create Work Order</button>
            </div>
        </form>
    </div>
</div>

<!-- Log Production Modal -->
<div class="modal-overlay" id="logModal">
    <div class="modal" style="max-width:500px">
        <div class="modal-header">
            <h2><i class="fa fa-clipboard-check" style="margin-right:8px;color:var(--accent)"></i>Log Production</h2>
            <button class="modal-close" onclick="closeModal('logModal')"><i class="fa fa-xmark"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="log_production">
            <div class="form-grid">
                <div class="form-group">
                    <label>Work Order</label>
                    <select name="work_order_id" required>
                        <option value="">— Select Work Order —</option>
                        <?php foreach ($workOrders as $wo): if ($wo['status'] === 'cancelled') continue; ?>
                        <option value="<?= $wo['id'] ?>">
                            <?= htmlspecialchars($wo['wo_no']) ?> — <?= htmlspecialchars($wo['product_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Production Stage</label>
                    <select name="stage">
                        <option>Raw Material Prep</option>
                        <option>Machining</option>
                        <option>Assembly</option>
                        <option>Welding</option>
                        <option>Painting</option>
                        <option>Quality Inspection</option>
                        <option>Packaging</option>
                        <option>Finished Goods</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantity Completed</label>
                    <input type="number" name="quantity_done" min="1" required placeholder="0">
                </div>
            </div>
            <div class="form-group mt-2">
                <label>Notes</label>
                <textarea name="log_notes" placeholder="Production notes, quality issues..." rows="3"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('logModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa fa-clipboard-check"></i> Log Entry</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
