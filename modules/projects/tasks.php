<?php
require_once __DIR__ . '/../../includes/header.php';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $pid   = (int)$_POST['project_id'];
        $title = sanitize($_POST['title']);
        $desc  = sanitize($_POST['description']);
        $asgn  = (int)$_POST['assigned_to'] ?: null;
        $pri   = sanitize($_POST['priority']);
        $st    = sanitize($_POST['status']);
        $due   = sanitize($_POST['due_date']);
        $stmt  = $db->prepare("INSERT INTO tasks (project_id,title,description,assigned_to,priority,status,due_date) VALUES(?,?,?,?,?,?,?)");
        $stmt->bind_param('ississs', $pid, $title, $desc, $asgn, $pri, $st, $due);
        $stmt->execute();
        header('Location: tasks.php?success=1'); exit;
    }
    if ($_POST['action'] === 'update_status') {
        $id = (int)$_POST['id'];
        $st = sanitize($_POST['status']);
        $stmt = $db->prepare("UPDATE tasks SET status=? WHERE id=?");
        $stmt->bind_param('si', $st, $id);
        $stmt->execute();
        header('Location: tasks.php?success=1'); exit;
    }
    if ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        $db->query("DELETE FROM tasks WHERE id=$id");
        header('Location: tasks.php?success=1'); exit;
    }
}

$filterProject = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
$where = $filterProject ? "WHERE t.project_id = $filterProject" : "";

$tasks = $db->query("
    SELECT t.*, p.name as project_name, p.project_code,
           u.name as assigned_name
    FROM tasks t
    LEFT JOIN projects p ON t.project_id = p.id
    LEFT JOIN users u ON t.assigned_to = u.id
    $where
    ORDER BY
        FIELD(t.priority,'urgent','high','medium','low'),
        FIELD(t.status,'todo','in-progress','review','done'),
        t.due_date ASC
")->fetch_all(MYSQLI_ASSOC);

$projects = $db->query("SELECT * FROM projects WHERE status IN ('active','planning') ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$users    = $db->query("SELECT * FROM users WHERE status='active'")->fetch_all(MYSQLI_ASSOC);

$statusColors   = ['todo'=>'badge-gray','in-progress'=>'badge-blue','review'=>'badge-amber','done'=>'badge-green'];
$priorityColors = ['low'=>'badge-gray','medium'=>'badge-teal','high'=>'badge-amber','urgent'=>'badge-red'];

// Kanban counts
$kanban = ['todo'=>[],'in-progress'=>[],'review'=>[],'done'=>[]];
foreach ($tasks as $t) $kanban[$t['status']][] = $t;
?>

<div class="page-header">
    <div>
        <h1><i class="fa fa-list-check" style="margin-right:10px;color:var(--accent)"></i>Tasks</h1>
        <p>Project tasks and assignments</p>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
        <form method="GET" style="display:flex;gap:8px;align-items:center;">
            <select name="project_id" onchange="this.form.submit()"
                style="background:var(--bg-hover);border:1px solid var(--border-light);border-radius:8px;padding:8px 12px;color:var(--text-primary);font-family:'DM Sans',sans-serif;font-size:0.85rem;outline:none;">
                <option value="0">All Projects</option>
                <?php foreach ($projects as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $filterProject===$p['id']?'selected':'' ?>>
                    <?= htmlspecialchars($p['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
        <button class="btn btn-primary" onclick="openModal('taskModal')">
            <i class="fa fa-plus"></i> New Task
        </button>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success"><i class="fa fa-circle-check"></i> Task updated.</div>
<?php endif; ?>

<!-- Kanban Board -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px;">
<?php
$columns = [
    'todo'       => ['To Do',        'fa-circle',       'var(--text-secondary)'],
    'in-progress'=> ['In Progress',  'fa-circle-half-stroke', 'var(--accent)'],
    'review'     => ['In Review',    'fa-magnifying-glass', 'var(--accent-3)'],
    'done'       => ['Done',         'fa-circle-check', 'var(--accent-success)'],
];
foreach ($columns as $col => [$label, $icon, $color]): ?>
<div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius);padding:16px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
        <div style="display:flex;align-items:center;gap:8px;">
            <i class="fa <?= $icon ?>" style="color:<?= $color ?>"></i>
            <span style="font-family:'Syne',sans-serif;font-weight:700;font-size:0.875rem;"><?= $label ?></span>
        </div>
        <span style="background:var(--bg-hover);border-radius:20px;padding:2px 10px;font-size:0.75rem;font-weight:700;color:var(--text-secondary)">
            <?= count($kanban[$col]) ?>
        </span>
    </div>
    <div style="display:flex;flex-direction:column;gap:10px;">
    <?php foreach ($kanban[$col] as $t): ?>
    <div style="background:var(--bg-card);border:1px solid var(--border-light);border-radius:10px;padding:14px;cursor:default;transition:all .2s;"
         onmouseenter="this.style.borderColor='var(--accent)'" onmouseleave="this.style.borderColor='var(--border-light)'">
        <div style="font-weight:600;font-size:0.85rem;margin-bottom:6px;line-height:1.3">
            <?= htmlspecialchars($t['title']) ?>
        </div>
        <div style="font-size:0.72rem;color:var(--text-secondary);margin-bottom:8px">
            <i class="fa fa-diagram-project"></i> <?= htmlspecialchars($t['project_name'] ?? '—') ?>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:4px;">
            <span class="badge <?= $priorityColors[$t['priority']] ?? 'badge-gray' ?>" style="font-size:0.68rem">
                <?= ucfirst($t['priority']) ?>
            </span>
            <?php if ($t['due_date']): ?>
            <span style="font-size:0.7rem;color:<?= strtotime($t['due_date'])<time()&&$t['status']!=='done'?'var(--accent-danger)':'var(--text-muted)' ?>">
                <i class="fa fa-calendar"></i> <?= date('M d', strtotime($t['due_date'])) ?>
            </span>
            <?php endif; ?>
        </div>
        <?php if ($t['assigned_name']): ?>
        <div style="margin-top:8px;padding-top:8px;border-top:1px solid var(--border);font-size:0.72rem;color:var(--text-secondary)">
            <i class="fa fa-user"></i> <?= htmlspecialchars($t['assigned_name']) ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (empty($kanban[$col])): ?>
    <div style="text-align:center;color:var(--text-muted);font-size:0.8rem;padding:20px 0">
        <i class="fa fa-inbox" style="display:block;font-size:1.5rem;margin-bottom:6px"></i>No tasks
    </div>
    <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- Tasks List Table -->
<div class="card">
    <div class="card-header">
        <h2><i class="fa fa-table-list" style="margin-right:8px"></i>All Tasks</h2>
        <div class="search-input-wrap" style="width:220px">
            <i class="fa fa-search"></i>
            <input type="text" id="taskSearch" placeholder="Search..." oninput="filterTable('taskSearch','taskTable')">
        </div>
    </div>
    <div class="table-wrap">
        <table id="taskTable">
            <thead>
                <tr><th>Task</th><th>Project</th><th>Assigned To</th><th>Priority</th><th>Due Date</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($tasks as $t): ?>
            <tr>
                <td style="font-weight:600;max-width:220px"><?= htmlspecialchars($t['title']) ?></td>
                <td style="color:var(--text-secondary)"><?= htmlspecialchars($t['project_name'] ?? '—') ?></td>
                <td><?= htmlspecialchars($t['assigned_name'] ?? 'Unassigned') ?></td>
                <td><span class="badge <?= $priorityColors[$t['priority']] ?? 'badge-gray' ?>"><?= ucfirst($t['priority']) ?></span></td>
                <td style="color:<?= $t['due_date']&&strtotime($t['due_date'])<time()&&$t['status']!=='done'?'var(--accent-danger)':'var(--text-secondary)' ?>">
                    <?= $t['due_date'] ?? '—' ?>
                </td>
                <td>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                        <select name="status" onchange="this.form.submit()"
                            style="background:var(--bg-hover);border:1px solid var(--border-light);border-radius:6px;padding:4px 8px;color:var(--text-primary);font-size:0.78rem;outline:none;">
                            <option value="todo" <?= $t['status']==='todo'?'selected':'' ?>>To Do</option>
                            <option value="in-progress" <?= $t['status']==='in-progress'?'selected':'' ?>>In Progress</option>
                            <option value="review" <?= $t['status']==='review'?'selected':'' ?>>Review</option>
                            <option value="done" <?= $t['status']==='done'?'selected':'' ?>>Done</option>
                        </select>
                    </form>
                </td>
                <td>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this task?')">
                            <i class="fa fa-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($tasks)): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:32px;">No tasks found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- New Task Modal -->
<div class="modal-overlay" id="taskModal">
    <div class="modal">
        <div class="modal-header">
            <h2><i class="fa fa-plus-circle" style="margin-right:8px;color:var(--accent)"></i>New Task</h2>
            <button class="modal-close" onclick="closeModal('taskModal')"><i class="fa fa-xmark"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-grid">
                <div class="form-group" style="grid-column:1/-1">
                    <label>Task Title</label>
                    <input type="text" name="title" required placeholder="Task description...">
                </div>
                <div class="form-group">
                    <label>Project</label>
                    <select name="project_id" required>
                        <option value="">— Select Project —</option>
                        <?php foreach ($projects as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $filterProject===$p['id']?'selected':'' ?>>
                            <?= htmlspecialchars($p['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Assigned To</label>
                    <select name="assigned_to">
                        <option value="">— Unassigned —</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
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
                        <option value="todo">To Do</option>
                        <option value="in-progress">In Progress</option>
                        <option value="review">Review</option>
                        <option value="done">Done</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Due Date</label>
                    <input type="date" name="due_date">
                </div>
            </div>
            <div class="form-group mt-2">
                <label>Description</label>
                <textarea name="description" placeholder="Task details..." rows="3"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('taskModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Create Task</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
