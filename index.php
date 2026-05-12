<?php
require_once __DIR__ . '/includes/header.php';
$db = getDB();

// Fetch KPIs
$stats = [];
$stats['employees'] = $db->query("SELECT COUNT(*) as c FROM employees WHERE status='active'")->fetch_assoc()['c'];
$stats['customers']  = $db->query("SELECT COUNT(*) as c FROM customers WHERE status='active'")->fetch_assoc()['c'];
$stats['products']   = $db->query("SELECT COUNT(*) as c FROM products WHERE status='active'")->fetch_assoc()['c'];
$stats['revenue']    = $db->query("SELECT COALESCE(SUM(total),0) as c FROM invoices WHERE status='paid'")->fetch_assoc()['c'];
$stats['orders']     = $db->query("SELECT COUNT(*) as c FROM sales_orders WHERE status NOT IN ('cancelled')")->fetch_assoc()['c'];
$stats['pending_inv']= $db->query("SELECT COUNT(*) as c FROM invoices WHERE status='sent'")->fetch_assoc()['c'];
$stats['projects']   = $db->query("SELECT COUNT(*) as c FROM projects WHERE status='active'")->fetch_assoc()['c'];
$stats['low_stock']  = $db->query("SELECT COUNT(*) as c FROM products WHERE quantity <= reorder_level")->fetch_assoc()['c'];

// Recent invoices
$recentInvoices = $db->query("SELECT * FROM invoices ORDER BY created_at DESC LIMIT 6")->fetch_all(MYSQLI_ASSOC);

// Recent orders
$recentOrders = $db->query("SELECT so.*, c.company_name FROM sales_orders so LEFT JOIN customers c ON so.customer_id=c.id ORDER BY so.created_at DESC LIMIT 6")->fetch_all(MYSQLI_ASSOC);

// Monthly revenue - last 7 months
$monthlyRevenue = $db->query("
  SELECT DATE_FORMAT(paid_date,'%b') as month, SUM(total) as total
  FROM invoices WHERE status='paid' AND paid_date >= DATE_SUB(NOW(), INTERVAL 7 MONTH)
  GROUP BY DATE_FORMAT(paid_date,'%Y%m') ORDER BY paid_date ASC
")->fetch_all(MYSQLI_ASSOC);

$statusColors = [
  'paid'=>'badge-green','sent'=>'badge-blue','draft'=>'badge-gray',
  'overdue'=>'badge-red','cancelled'=>'badge-gray',
  'pending'=>'badge-amber','confirmed'=>'badge-teal','processing'=>'badge-blue',
  'shipped'=>'badge-blue','delivered'=>'badge-green','active'=>'badge-green',
  'planning'=>'badge-amber','completed'=>'badge-green','on-hold'=>'badge-red'
];
?>

<!-- Stats -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fa fa-dollar-sign"></i></div>
    <div class="stat-info">
      <div class="label">Total Revenue</div>
      <div class="value">$<?= number_format($stats['revenue'],0) ?></div>
      <div class="change up"><i class="fa fa-arrow-trend-up"></i> Paid invoices</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon teal"><i class="fa fa-users"></i></div>
    <div class="stat-info">
      <div class="label">Active Employees</div>
      <div class="value"><?= $stats['employees'] ?></div>
      <div class="change up"><i class="fa fa-user-check"></i> Active staff</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon amber"><i class="fa fa-cart-shopping"></i></div>
    <div class="stat-info">
      <div class="label">Sales Orders</div>
      <div class="value"><?= $stats['orders'] ?></div>
      <div class="change up"><i class="fa fa-arrow-trend-up"></i> Total orders</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red"><i class="fa fa-file-invoice"></i></div>
    <div class="stat-info">
      <div class="label">Pending Invoices</div>
      <div class="value"><?= $stats['pending_inv'] ?></div>
      <div class="change down"><i class="fa fa-clock"></i> Awaiting payment</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fa fa-handshake"></i></div>
    <div class="stat-info">
      <div class="label">Customers</div>
      <div class="value"><?= $stats['customers'] ?></div>
      <div class="change up"><i class="fa fa-circle-check"></i> Active accounts</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fa fa-boxes-stacked"></i></div>
    <div class="stat-info">
      <div class="label">Products</div>
      <div class="value"><?= $stats['products'] ?></div>
      <div class="change <?= $stats['low_stock']>0?'down':'up' ?>">
        <i class="fa fa-triangle-exclamation"></i> <?= $stats['low_stock'] ?> low stock
      </div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon teal"><i class="fa fa-diagram-project"></i></div>
    <div class="stat-info">
      <div class="label">Active Projects</div>
      <div class="value"><?= $stats['projects'] ?></div>
      <div class="change up"><i class="fa fa-rocket"></i> In progress</div>
    </div>
  </div>
</div>

<!-- Charts Row -->
<div class="charts-row">
  <div class="card">
    <div class="card-header">
      <h2><i class="fa fa-chart-bar text-accent" style="margin-right:8px"></i>Monthly Revenue</h2>
    </div>
    <?php if (!empty($monthlyRevenue)): 
      $maxRev = max(array_column($monthlyRevenue,'total')) ?: 1;
    ?>
    <div class="mini-bars" style="height:160px;align-items:flex-end;gap:10px;padding:0 8px;">
      <?php foreach ($monthlyRevenue as $r): 
        $h = max(8, round(($r['total']/$maxRev)*140));
      ?>
      <div style="display:flex;flex-direction:column;align-items:center;gap:6px;flex:1">
        <span style="font-size:0.7rem;color:var(--text-secondary);">$<?= number_format($r['total']/1000,1) ?>k</span>
        <div class="mini-bar" style="height:<?= $h ?>px;width:100%;"></div>
        <span style="font-size:0.7rem;color:var(--text-muted);"><?= $r['month'] ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="chart-placeholder"><i class="fa fa-chart-bar" style="margin-right:8px"></i>No revenue data yet</div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-header">
      <h2><i class="fa fa-circle-nodes text-accent" style="margin-right:8px"></i>Module Overview</h2>
    </div>
    <div style="display:flex;flex-direction:column;gap:14px;margin-top:4px;">
      <?php
      $mods = [
        ['Finance','Invoices & Accounts',$stats['revenue']>0?75:10,$stats['pending_inv'].' pending','blue'],
        ['HR','Employees & Payroll',$stats['employees']>0?80:5,$stats['employees'].' active','teal'],
        ['Inventory','Products & Stock',$stats['products']>0?65:5,$stats['products'].' items','amber'],
        ['Sales','Orders & CRM',$stats['orders']>0?70:5,$stats['orders'].' orders','green'],
      ];
      foreach ($mods as [$name,$sub,$pct,$note,$color]):
      ?>
      <div>
        <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
          <span style="font-size:0.85rem;font-weight:600;"><?= $name ?></span>
          <span style="font-size:0.75rem;color:var(--text-secondary);"><?= $note ?></span>
        </div>
        <div class="progress-bar">
          <div class="progress-fill" style="width:<?= $pct ?>%"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Recent Tables -->
<div class="charts-row">
  <!-- Recent Invoices -->
  <div class="card">
    <div class="card-header">
      <h2>Recent Invoices</h2>
      <a href="/erp/modules/finance/invoices.php" class="btn btn-secondary btn-sm">View All</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Invoice #</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($recentInvoices as $inv): ?>
        <tr>
          <td class="font-mono text-accent"><?= htmlspecialchars($inv['invoice_no']) ?></td>
          <td><?= htmlspecialchars($inv['customer_name']) ?></td>
          <td>$<?= number_format($inv['total'],2) ?></td>
          <td><span class="badge <?= $statusColors[$inv['status']] ?? 'badge-gray' ?>"><?= ucfirst($inv['status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($recentInvoices)): ?>
        <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:24px;">No invoices yet</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Recent Orders -->
  <div class="card">
    <div class="card-header">
      <h2>Recent Orders</h2>
      <a href="/erp/modules/sales/orders.php" class="btn btn-secondary btn-sm">View All</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Order #</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($recentOrders as $ord): ?>
        <tr>
          <td class="font-mono text-accent"><?= htmlspecialchars($ord['order_no']) ?></td>
          <td><?= htmlspecialchars($ord['company_name'] ?? 'N/A') ?></td>
          <td>$<?= number_format($ord['total'],2) ?></td>
          <td><span class="badge <?= $statusColors[$ord['status']] ?? 'badge-gray' ?>"><?= ucfirst($ord['status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($recentOrders)): ?>
        <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:24px;">No orders yet</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
