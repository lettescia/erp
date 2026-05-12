<?php
require_once __DIR__ . '/../../includes/header.php';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $code  = generateCode('PRJ-','projects','project_code');
        $name  = sanitize($_POST['name']);
        $desc  = sanitize($_POST['description']);
        $client= sanitize($_POST['client_name']);
        $mgr   = (int)$_POST['manager_id'];
        $sd    = sanitize($_POST['start_date']);
        $ed    = sanitize($_POST['end_date']);
        $bud   = floatval($_POST['budget']);
        $st    = sanitize($_POST['status']);
        $stmt = $db->prepare("INSERT INTO projects (project_code,name,description,client_name,manager_id,start_date,end_date,budget,status) VALUES(?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('ssssissds',$code,$name,$desc,$client,$mgr,$sd,$ed,$bud,$st);
        $stmt->execute();
        header('Location: projects.php?success=1'); exit;
    }
    if ($_POST['action'] === 'update_progress') {
        $id = (int)$_POST['id'];
        $pg = (int)$_POST['progress'];
        $st = sanitize($_POST['status']);
        $stmt = $db->prepare("UPDATE projects SET progress=?,status=? WHERE id=?");
        $stmt->bind_param('isi',$pg,$st,$id);
        $stmt->execute();
        header('Location: projects.php?success=1'); exit;
    }
}

$projects = $db->query("SELECT p.*, u.name as manager_name FROM projects p LEFT JOIN users u ON p.manager_id=u.id ORDER BY p.created_at DESC")->fetch_all(MYSQLI_ASSOC);
$managers = $db->query("SELECT * FROM users WHERE status='active'")->fetch_all(MYSQLI_ASSOC);
$statusColors = ['planning'=>'badge-amber','active'=>'badge-teal','on-hold'=>'badge-red','completed'=>'badge-green','cancelled'=>'badge-gray'];
?>

<div class="page-header">
  <div>
    <h1><i class="fa fa-diagram-project" style="margin-right:10px;color:var(--accent)"></i>Projects</h1>
    <p>Manage projects, timelines and budgets</p>
  </div>
  <button class="btn btn-primary" onclick="openModal('projModal')">
    <i class="fa fa-plus"></i> New Project
  </button>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success"><i class="fa fa-circle-check"></i> Action completed.</div>
<?php endif; ?>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Code</th><th>Project</th><th>Client</th><th>Manager</th><th>Budget</th><th>Progress</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
      <?php foreach ($projects as $p): ?>
      <tr>
        <td class="font-mono text-accent"><?= htmlspecialchars($p['project_code']) ?></td>
        <td>
          <div style="font-weight:600"><?= htmlspecialchars($p['name']) ?></div>
          <div style="font-size:0.75rem;color:var(--text-secondary)"><?= $p['start_date'] ?> → <?= $p['end_date'] ?? '—' ?></div>
        </td>
        <td><?= htmlspecialchars($p['client_name'] ?? '—') ?></td>
        <td><?= htmlspecialchars($p['manager_name'] ?? '—') ?></td>
        <td>$<?= number_format($p['budget'],0) ?></td>
        <td style="min-width:100px">
          <div style="margin-bottom:4px;font-size:0.78rem;color:var(--text-secondary)"><?= $p['progress'] ?>%</div>
          <div class="progress-bar"><div class="progress-fill" style="width:<?= $p['progress'] ?>%"></div></div>
        </td>
        <td><span class="badge <?= $statusColors[$p['status']] ?? 'badge-gray' ?>"><?= ucfirst($p['status']) ?></span></td>
        <td>
          <button class="btn btn-secondary btn-sm" onclick="openEditModal(<?= htmlspecialchars(json_encode($p)) ?>)">
            <i class="fa fa-pencil"></i>
          </button>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($projects)): ?>
      <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:32px;">No projects found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- New Project Modal -->
<div class="modal-overlay" id="projModal">
  <div class="modal">
    <div class="modal-header">
      <h2><i class="fa fa-diagram-project" style="margin-right:8px;color:var(--accent)"></i>New Project</h2>
      <button class="modal-close" onclick="closeModal('projModal')"><i class="fa fa-xmark"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="create">
      <div class="form-grid">
        <div class="form-group">
          <label>Project Name</label>
          <input type="text" name="name" required placeholder="Project name">
        </div>
        <div class="form-group">
          <label>Client</label>
          <input type="text" name="client_name" placeholder="Client name">
        </div>
        <div class="form-group">
          <label>Manager</label>
          <select name="manager_id">
            <option value="">— Select —</option>
            <?php foreach ($managers as $m): ?>
            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="status">
            <option value="planning">Planning</option>
            <option value="active">Active</option>
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
        <div class="form-group">
          <label>Budget ($)</label>
          <input type="number" name="budget" step="0.01" placeholder="0.00">
        </div>
      </div>
      <div class="form-group mt-2">
        <label>Description</label>
        <textarea name="description" placeholder="Project description..."></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('projModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Create Project</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Progress Modal -->
<div class="modal-overlay" id="editProjModal">
  <div class="modal" style="max-width:400px">
    <div class="modal-header">
      <h2>Update Progress</h2>
      <button class="modal-close" onclick="closeModal('editProjModal')"><i class="fa fa-xmark"></i></button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="update_progress">
      <input type="hidden" name="id" id="edit_proj_id">
      <div class="form-group">
        <label>Progress (%)</label>
        <input type="number" name="progress" id="edit_progress" min="0" max="100">
      </div>
      <div class="form-group mt-2">
        <label>Status</label>
        <select name="status" id="edit_status">
          <option value="planning">Planning</option>
          <option value="active">Active</option>
          <option value="on-hold">On Hold</option>
          <option value="completed">Completed</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('editProjModal')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Update</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(p) {
  document.getElementById('edit_proj_id').value = p.id;
  document.getElementById('edit_progress').value = p.progress;
  document.getElementById('edit_status').value = p.status;
  openModal('editProjModal');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
