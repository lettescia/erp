<?php
// modules/hr/departments.php
require_once __DIR__ . '/../../includes/header.php';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $bud  = floatval($_POST['budget']);
    $stmt = $db->prepare("INSERT INTO departments (name,budget) VALUES(?,?)");
    $stmt->bind_param('sd',$name,$bud);
    $stmt->execute();
    header('Location: departments.php?success=1'); exit;
}
if (isset($_GET['delete'])) {
    $db->query("DELETE FROM departments WHERE id=".(int)$_GET['delete']);
    header('Location: departments.php?success=1'); exit;
}

$depts = $db->query("SELECT d.*, COUNT(e.id) as emp_count FROM departments d LEFT JOIN employees e ON d.id=e.department_id AND e.status='active' GROUP BY d.id ORDER BY d.name")->fetch_all(MYSQLI_ASSOC);
?>
<div class="page-header">
  <div><h1><i class="fa fa-sitemap" style="margin-right:10px;color:var(--accent)"></i>Departments</h1><p>Manage company departments</p></div>
  <button class="btn btn-primary" onclick="openModal('deptModal')"><i class="fa fa-plus"></i> Add Department</button>
</div>
<?php if (isset($_GET['success'])): ?><div class="alert alert-success"><i class="fa fa-circle-check"></i> Done.</div><?php endif; ?>
<div class="card">
  <div class="table-wrap">
    <table>
      <thead><tr><th>Department</th><th>Budget</th><th>Employees</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($depts as $d): ?>
      <tr>
        <td style="font-weight:600"><?= htmlspecialchars($d['name']) ?></td>
        <td>$<?= number_format($d['budget'],2) ?></td>
        <td><span class="badge badge-teal"><?= $d['emp_count'] ?></span></td>
        <td><a href="departments.php?delete=<?= $d['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete?')"><i class="fa fa-trash"></i></a></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<div class="modal-overlay" id="deptModal">
  <div class="modal">
    <div class="modal-header"><h2>Add Department</h2><button class="modal-close" onclick="closeModal('deptModal')"><i class="fa fa-xmark"></i></button></div>
    <form method="POST">
      <div class="form-grid">
        <div class="form-group"><label>Name</label><input type="text" name="name" required placeholder="Department name"></div>
        <div class="form-group"><label>Budget ($)</label><input type="number" name="budget" step="0.01" value="0"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('deptModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Add</button>
      </div>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
