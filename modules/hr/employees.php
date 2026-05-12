<?php
require_once __DIR__ . '/../../includes/header.php';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $empId = generateCode('EMP-','employees','employee_id');
        $fn = sanitize($_POST['first_name']);
        $ln = sanitize($_POST['last_name']);
        $em = sanitize($_POST['email']);
        $ph = sanitize($_POST['phone']);
        $did= (int)$_POST['department_id'];
        $des= sanitize($_POST['designation']);
        $sal= floatval($_POST['salary']);
        $hd = sanitize($_POST['hire_date']);
        $stmt = $db->prepare("INSERT INTO employees (employee_id,first_name,last_name,email,phone,department_id,designation,salary,hire_date) VALUES(?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('sssssisds',$empId,$fn,$ln,$em,$ph,$did,$des,$sal,$hd);
        $stmt->execute();
        header('Location: employees.php?success=1'); exit;
    }
    if ($_POST['action'] === 'delete') {
        $db->query("UPDATE employees SET status='terminated' WHERE id=".(int)$_POST['id']);
        header('Location: employees.php?success=1'); exit;
    }
}

$employees = $db->query("SELECT e.*, d.name as dept_name FROM employees e LEFT JOIN departments d ON e.department_id=d.id WHERE e.status='active' ORDER BY e.created_at DESC")->fetch_all(MYSQLI_ASSOC);
$departments = $db->query("SELECT * FROM departments ORDER BY name")->fetch_all(MYSQLI_ASSOC);
?>

<div class="page-header">
  <div>
    <h1><i class="fa fa-users" style="margin-right:10px;color:var(--accent)"></i>Employees</h1>
    <p>Manage your workforce</p>
  </div>
  <button class="btn btn-primary" onclick="openModal('empModal')">
    <i class="fa fa-user-plus"></i> Add Employee
  </button>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success"><i class="fa fa-circle-check"></i> Action completed.</div>
<?php endif; ?>

<div class="card">
  <div class="search-bar">
    <div class="search-input-wrap">
      <i class="fa fa-search"></i>
      <input type="text" placeholder="Search employees..." oninput="filterTable('empSearch','empTable')" id="empSearch">
    </div>
  </div>
  <div class="table-wrap">
    <table id="empTable">
      <thead>
        <tr><th>ID</th><th>Name</th><th>Email</th><th>Department</th><th>Designation</th><th>Salary</th><th>Hire Date</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php foreach ($employees as $e): ?>
      <tr>
        <td class="font-mono text-accent"><?= htmlspecialchars($e['employee_id']) ?></td>
        <td style="font-weight:600"><?= htmlspecialchars($e['first_name'].' '.$e['last_name']) ?></td>
        <td style="color:var(--text-secondary)"><?= htmlspecialchars($e['email']) ?></td>
        <td><?= htmlspecialchars($e['dept_name'] ?? '—') ?></td>
        <td><?= htmlspecialchars($e['designation'] ?? '—') ?></td>
        <td>$<?= number_format($e['salary'],2) ?></td>
        <td style="color:var(--text-secondary)"><?= $e['hire_date'] ?></td>
        <td>
          <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $e['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Terminate this employee?')"><i class="fa fa-user-xmark"></i></button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($employees)): ?>
      <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:32px;">No employees found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Employee Modal -->
<div class="modal-overlay" id="empModal">
  <div class="modal">
    <div class="modal-header">
      <h2><i class="fa fa-user-plus" style="margin-right:8px;color:var(--accent)"></i>Add Employee</h2>
      <button class="modal-close" onclick="closeModal('empModal')"><i class="fa fa-xmark"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="form-grid">
        <div class="form-group">
          <label>First Name</label>
          <input type="text" name="first_name" required placeholder="John">
        </div>
        <div class="form-group">
          <label>Last Name</label>
          <input type="text" name="last_name" required placeholder="Doe">
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" required placeholder="john@company.com">
        </div>
        <div class="form-group">
          <label>Phone</label>
          <input type="text" name="phone" placeholder="+1 234 567 8900">
        </div>
        <div class="form-group">
          <label>Department</label>
          <select name="department_id">
            <option value="">— Select —</option>
            <?php foreach ($departments as $d): ?>
            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Designation</label>
          <input type="text" name="designation" placeholder="Software Engineer">
        </div>
        <div class="form-group">
          <label>Salary</label>
          <input type="number" name="salary" step="0.01" placeholder="50000">
        </div>
        <div class="form-group">
          <label>Hire Date</label>
          <input type="date" name="hire_date" value="<?= date('Y-m-d') ?>">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('empModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Add Employee</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
