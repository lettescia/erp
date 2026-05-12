<?php
require_once __DIR__ . '/../../includes/header.php';
$db = getDB();

// KPIs
$totalRevenue  = $db->query("SELECT COALESCE(SUM(total),0) as v FROM invoices WHERE status='paid'")->fetch_assoc()['v'];
$totalExpenses = $db->query("SELECT COALESCE(SUM(amount),0) as v FROM transactions WHERE type='debit'")->fetch_assoc()['v'];
$totalOrders   = $db->query("SELECT COUNT(*) as v FROM sales_orders")->fetch_assoc()['v'];
$totalEmps     = $db->query("SELECT COUNT(*) as v FROM employees WHERE status='active'")->fetch_assoc()['v'];
$payrollCost   = $db->query("SELECT COALESCE(SUM(salary),0) as v FROM employees WHERE status='active'")->fetch_assoc()['v'];
$assetValue    = $db->query("SELECT COALESCE(SUM(current_value),0) as v FROM assets WHERE status='active'")->fetch_assoc()['v'];
$lowStock      = $db->query("SELECT COUNT(*) as v FROM products WHERE quantity <= reorder_level AND status='active'")->fetch_assoc()['v'];
$activeProjects= $db->query("SELECT COUNT(*) as v FROM projects WHERE status='active'")->fetch_assoc()['v'];

// Monthly revenue trend
$monthlyRev = $db->query("SELECT DATE_FORMAT(paid_date,'%b %Y') as m, SUM(total) as total FROM invoices WHERE status='paid' AND paid_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH) GROUP BY DATE_FORMAT(paid_date,'%Y%m') ORDER BY paid_date ASC")->fetch_all(MYSQLI_ASSOC);

// Order by status
$ordersByStatus = $db->query("SELECT status, COUNT(*) as cnt FROM sales_orders GROUP BY status")->fetch_all(MYSQLI_ASSOC);

// Top products
$topProducts = $db->query("SELECT p.name, SUM(oi.quantity) as sold FROM order_items oi JOIN products p ON oi.product_id=p.id GROUP BY p.id ORDER BY sold DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
?>

<div class="page-header">
  <div>
    <h1><i class="fa fa-chart-mixed" style="margin-right:10px;color:var(--accent)"></i>Reports</h1>
    <p>Business intelligence and analytics overview</p>
  </div>
</div>

<!-- Summary KPIs -->
<div class="stat-grid" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr));">
  <div class="stat-card">
    <div class="stat-icon green"><i class="fa fa-dollar-sign"></i></div>
    <div class="stat-info">
      <div class="label">Total Revenue</div>
      <div class="value">$<?= number_format($totalRevenue,0) ?></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red"><i class="fa fa-arrow-trend-down"></i></div>
    <div class="stat-info">
      <div class="label">Total Expenses</div>
      <div class="value">$<?= number_format($totalExpenses,0) ?></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fa fa-scale-balanced"></i></div>
    <div class="stat-info">
      <div class="label">Net Profit</div>
      <div class="value" style="color:<?= $totalRevenue-$totalExpenses>=0?'var(--accent-success)':'var(--accent-danger)' ?>">
        $<?= number_format($totalRevenue-$totalExpenses,0) ?>
      </div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon amber"><i class="fa fa-users"></i></div>
    <div class="stat-info">
      <div class="label">Monthly Payroll</div>
      <div class="value">$<?= number_format($payrollCost,0) ?></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon teal"><i class="fa fa-server"></i></div>
    <div class="stat-info">
      <div class="label">Asset Value</div>
      <div class="value">$<?= number_format($assetValue,0) ?></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon <?= $lowStock>0?'red':'green' ?>"><i class="fa fa-warehouse"></i></div>
    <div class="stat-info">
      <div class="label">Low Stock Items</div>
      <div class="value"><?= $lowStock ?></div>
    </div>
  </div>
</div>

<div class="charts-row">
  <!-- Revenue Trend -->
  <div class="card">
    <div class="card-header">
      <h2><i class="fa fa-chart-line text-accent" style="margin-right:8px"></i>Revenue Trend (12 months)</h2>
    </div>
    <?php if (!empty($monthlyRev)):
      $maxV = max(array_column($monthlyRev,'total')) ?: 1;
    ?>
    <div style="display:flex;align-items:flex-end;gap:8px;height:180px;padding:0 4px;">
      <?php foreach ($monthlyRev as $r):
        $h = max(8, round(($r['total']/$maxV)*160));
      ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:5px;">
        <span style="font-size:0.68rem;color:var(--text-secondary);">$<?= number_format($r['total']/1000,0) ?>k</span>
        <div class="mini-bar" style="height:<?= $h ?>px;width:100%;"></div>
        <span style="font-size:0.65rem;color:var(--text-muted);writing-mode:vertical-rl;transform:rotate(180deg);height:40px;"><?= $r['m'] ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="chart-placeholder">No revenue data available</div>
    <?php endif; ?>
  </div>

  <!-- Orders by Status -->
  <div class="card">
    <div class="card-header">
      <h2><i class="fa fa-chart-pie text-accent" style="margin-right:8px"></i>Orders by Status</h2>
    </div>
    <?php
    $statusCols = ['pending'=>'#f7a44f','confirmed'=>'#22d4b8','processing'=>'#4f8ef7','shipped'=>'#a44ff7','delivered'=>'#4fd47a','cancelled'=>'#f74f4f'];
    $totalOrdCount = array_sum(array_column($ordersByStatus,'cnt')) ?: 1;
    ?>
    <div style="display:flex;flex-direction:column;gap:12px;margin-top:8px;">
      <?php foreach ($ordersByStatus as $row):
        $pct = round($row['cnt']/$totalOrdCount*100);
        $col = $statusCols[$row['status']] ?? '#7b8aab';
      ?>
      <div>
        <div style="display:flex;justify-content:space-between;margin-bottom:5px;">
          <span style="font-size:0.82rem;font-weight:600;"><?= ucfirst($row['status']) ?></span>
          <span style="font-size:0.78rem;color:var(--text-secondary);"><?= $row['cnt'] ?> (<?= $pct ?>%)</span>
        </div>
        <div class="progress-bar">
          <div style="height:100%;width:<?= $pct ?>%;background:<?= $col ?>;border-radius:3px;transition:width .5s ease;"></div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($ordersByStatus)): ?>
      <p style="color:var(--text-muted);text-align:center;padding:20px;">No orders data</p>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Top Products & Quick Links -->
<div class="charts-row">
  <div class="card">
    <div class="card-header">
      <h2><i class="fa fa-star text-accent" style="margin-right:8px"></i>Top Selling Products</h2>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>Product</th><th>Units Sold</th></tr></thead>
        <tbody>
        <?php if (empty($topProducts)): ?>
        <tr><td colspan="3" style="text-align:center;color:var(--text-muted);padding:24px;">No sales data yet</td></tr>
        <?php endif; ?>
        <?php foreach ($topProducts as $i => $p): ?>
        <tr>
          <td><span class="badge badge-blue"><?= $i+1 ?></span></td>
          <td style="font-weight:600"><?= htmlspecialchars($p['name']) ?></td>
          <td><?= number_format($p['sold']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h2>Quick Summary</h2></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
      <?php
      $summary = [
        ['Total Orders', $totalOrders, 'fa-cart-shopping', 'blue'],
        ['Active Employees', $totalEmps, 'fa-users', 'teal'],
        ['Active Projects', $activeProjects, 'fa-diagram-project', 'amber'],
        ['Low Stock Alerts', $lowStock, 'fa-triangle-exclamation', 'red'],
      ];
      foreach ($summary as [$label,$val,$icon,$color]):
      ?>
      <div style="background:var(--bg-hover);border-radius:var(--radius-sm);padding:16px;text-align:center;">
        <div class="stat-icon <?= $color ?>" style="margin:0 auto 10px;width:40px;height:40px;font-size:1rem;">
          <i class="fa <?= $icon ?>"></i>
        </div>
        <div style="font-family:'Syne',sans-serif;font-size:1.4rem;font-weight:700;"><?= number_format($val) ?></div>
        <div style="font-size:0.75rem;color:var(--text-secondary);margin-top:3px;"><?= $label ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
