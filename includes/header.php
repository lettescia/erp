<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$user = currentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NexusERP — <?= ucfirst($currentPage) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/erp/assets/css/main.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="erp-shell">

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="brand-icon"><i class="fa fa-hexagon-nodes"></i></div>
      <span class="brand-name">NexusERP</span>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-label">Main</div>
      <a href="/erp/index.php" class="nav-item <?= $currentPage==='index'?'active':'' ?>">
        <i class="fa fa-grid-2"></i><span>Dashboard</span>
      </a>

      <div class="nav-section-label">Finance</div>
      <a href="/erp/modules/finance/accounts.php" class="nav-item <?= $currentPage==='accounts'?'active':'' ?>">
        <i class="fa fa-landmark"></i><span>Accounts</span>
      </a>
      <a href="/erp/modules/finance/invoices.php" class="nav-item <?= $currentPage==='invoices'?'active':'' ?>">
        <i class="fa fa-file-invoice-dollar"></i><span>Invoices</span>
      </a>
      <a href="/erp/modules/finance/transactions.php" class="nav-item <?= $currentPage==='transactions'?'active':'' ?>">
        <i class="fa fa-arrows-left-right"></i><span>Transactions</span>
      </a>

      <div class="nav-section-label">HR & Payroll</div>
      <a href="/erp/modules/hr/employees.php" class="nav-item <?= $currentPage==='employees'?'active':'' ?>">
        <i class="fa fa-users"></i><span>Employees</span>
      </a>
      <a href="/erp/modules/hr/departments.php" class="nav-item <?= $currentPage==='departments'?'active':'' ?>">
        <i class="fa fa-sitemap"></i><span>Departments</span>
      </a>
      <a href="/erp/modules/hr/attendance.php" class="nav-item <?= $currentPage==='attendance'?'active':'' ?>">
        <i class="fa fa-clock"></i><span>Attendance</span>
      </a>
      <a href="/erp/modules/hr/payroll.php" class="nav-item <?= $currentPage==='payroll'?'active':'' ?>">
        <i class="fa fa-money-bill-wave"></i><span>Payroll</span>
      </a>

      <div class="nav-section-label">Inventory</div>
      <a href="/erp/modules/inventory/products.php" class="nav-item <?= $currentPage==='products'?'active':'' ?>">
        <i class="fa fa-boxes-stacked"></i><span>Products</span>
      </a>
      <a href="/erp/modules/inventory/stock.php" class="nav-item <?= $currentPage==='stock'?'active':'' ?>">
        <i class="fa fa-warehouse"></i><span>Stock</span>
      </a>

      <div class="nav-section-label">Sales & CRM</div>
      <a href="/erp/modules/sales/customers.php" class="nav-item <?= $currentPage==='customers'?'active':'' ?>">
        <i class="fa fa-handshake"></i><span>Customers</span>
      </a>
      <a href="/erp/modules/sales/orders.php" class="nav-item <?= $currentPage==='orders'?'active':'' ?>">
        <i class="fa fa-cart-shopping"></i><span>Sales Orders</span>
      </a>

      <div class="nav-section-label">Procurement</div>
      <a href="/erp/modules/purchasing/suppliers.php" class="nav-item <?= $currentPage==='suppliers'?'active':'' ?>">
        <i class="fa fa-truck"></i><span>Suppliers</span>
      </a>
      <a href="/erp/modules/purchasing/purchase_orders.php" class="nav-item <?= $currentPage==='purchase_orders'?'active':'' ?>">
        <i class="fa fa-clipboard-list"></i><span>Purchase Orders</span>
      </a>

      <div class="nav-section-label">Manufacturing</div>
      <a href="/erp/modules/manufacturing/manufacturing.php" class="nav-item <?= $currentPage==='manufacturing'?'active':'' ?>">
        <i class="fa fa-industry"></i><span>Work Orders</span>
      </a>

      <div class="nav-section-label">Projects</div>
      <a href="/erp/modules/projects/projects.php" class="nav-item <?= $currentPage==='projects'?'active':'' ?>">
        <i class="fa fa-diagram-project"></i><span>Projects</span>
      </a>
      <a href="/erp/modules/projects/tasks.php" class="nav-item <?= $currentPage==='tasks'?'active':'' ?>">
        <i class="fa fa-list-check"></i><span>Tasks</span>
      </a>

      <div class="nav-section-label">Assets</div>
      <a href="/erp/modules/assets/assets.php" class="nav-item <?= $currentPage==='assets'?'active':'' ?>">
        <i class="fa fa-server"></i><span>Assets</span>
      </a>

      <div class="nav-section-label">System</div>
      <a href="/erp/modules/reports/reports.php" class="nav-item <?= $currentPage==='reports'?'active':'' ?>">
        <i class="fa fa-chart-mixed"></i><span>Reports</span>
      </a>
      <a href="/erp/logout.php" class="nav-item nav-logout">
        <i class="fa fa-right-from-bracket"></i><span>Logout</span>
      </a>
    </nav>
  </aside>

  <!-- Main Content Area -->
  <div class="main-area">
    <!-- Topbar -->
    <header class="topbar">
      <div class="topbar-left">
        <button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('collapsed')">
          <i class="fa fa-bars"></i>
        </button>
        <div class="page-title"><?= ucwords(str_replace('_',' ',$currentPage)) ?></div>
      </div>
      <div class="topbar-right">
        <div class="topbar-date"><i class="fa fa-calendar-days"></i><?= date('M d, Y') ?></div>
        <div class="notif-btn"><i class="fa fa-bell"></i><span class="notif-dot"></span></div>
        <div class="user-badge">
          <div class="user-avatar"><?= strtoupper(substr($user['name'],0,1)) ?></div>
          <div class="user-info">
            <span class="user-name"><?= sanitize($user['name']) ?></span>
            <span class="user-role"><?= ucfirst($user['role']) ?></span>
          </div>
        </div>
      </div>
    </header>

    <!-- Page Content Starts -->
    <main class="page-content">
