<?php
session_start();
require_once __DIR__ . '/config/database.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /erp/index.php'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = trim($_POST['password'] ?? '');
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if ($user && password_verify($pass, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role']    = $user['role'];
        header('Location: /erp/index.php'); exit;
    } else {
        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NexusERP — Login</title>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0a0c10;--surface:#10131a;--card:#141820;
  --border:#1f2535;--accent:#4f8ef7;--accent2:#22d4b8;
  --text:#e8ecf4;--muted:#7b8aab;--radius:14px;
}
body{font-family:'DM Sans',sans-serif;background:var(--bg);min-height:100vh;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;color:var(--text);}
.bg-pattern{position:fixed;inset:0;pointer-events:none;
  background: radial-gradient(ellipse 80% 60% at 20% 10%, rgba(79,142,247,0.08) 0%, transparent 60%),
              radial-gradient(ellipse 60% 50% at 80% 80%, rgba(34,212,184,0.07) 0%, transparent 60%);
}
.grid-overlay{position:fixed;inset:0;pointer-events:none;
  background-image:linear-gradient(rgba(79,142,247,0.04) 1px,transparent 1px),
                   linear-gradient(90deg,rgba(79,142,247,0.04) 1px,transparent 1px);
  background-size:40px 40px;
}
.login-wrap{position:relative;z-index:1;width:100%;max-width:420px;padding:20px;}
.login-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:40px;box-shadow:0 24px 64px rgba(0,0,0,0.5);}
.login-brand{text-align:center;margin-bottom:36px;}
.brand-icon{width:52px;height:52px;background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:22px;color:#fff;margin:0 auto 14px;}
.brand-name{font-family:'Syne',sans-serif;font-weight:800;font-size:1.5rem;letter-spacing:0.5px;}
.brand-sub{font-size:0.82rem;color:var(--muted);margin-top:4px;}
.form-group{display:flex;flex-direction:column;gap:8px;margin-bottom:18px;}
.form-group label{font-size:0.75rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px;}
.input-wrap{position:relative;}
.input-wrap i{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:0.85rem;}
.input-wrap input{width:100%;background:#10131a;border:1px solid var(--border);border-radius:10px;padding:12px 14px 12px 40px;color:var(--text);font-family:'DM Sans',sans-serif;font-size:0.9rem;outline:none;transition:all .2s;}
.input-wrap input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(79,142,247,0.12);}
.btn-login{width:100%;background:var(--accent);color:#fff;border:none;border-radius:10px;padding:13px;font-family:'Syne',sans-serif;font-weight:700;font-size:0.95rem;cursor:pointer;transition:all .2s;letter-spacing:0.5px;margin-top:8px;}
.btn-login:hover{background:#6aa0f9;box-shadow:0 0 24px rgba(79,142,247,0.35);}
.error-msg{background:rgba(247,79,79,0.12);border:1px solid rgba(247,79,79,0.25);color:#f74f4f;padding:11px 14px;border-radius:9px;font-size:0.85rem;margin-bottom:18px;display:flex;align-items:center;gap:8px;}
.demo-hint{margin-top:20px;text-align:center;font-size:0.8rem;color:var(--muted);background:rgba(79,142,247,0.07);padding:10px;border-radius:9px;border:1px solid rgba(79,142,247,0.15);}
.demo-hint strong{color:var(--accent);}
</style>
</head>
<body>
<div class="bg-pattern"></div>
<div class="grid-overlay"></div>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-brand">
      <div class="brand-icon"><i class="fa fa-hexagon-nodes"></i></div>
      <div class="brand-name">NexusERP</div>
      <div class="brand-sub">Enterprise Resource Planning System</div>
    </div>

    <?php if ($error): ?>
    <div class="error-msg"><i class="fa fa-circle-xmark"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label>Email Address</label>
        <div class="input-wrap">
          <i class="fa fa-envelope"></i>
          <input type="email" name="email" placeholder="admin@erp.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label>Password</label>
        <div class="input-wrap">
          <i class="fa fa-lock"></i>
          <input type="password" name="password" placeholder="••••••••" required>
        </div>
      </div>
      <button type="submit" class="btn-login"><i class="fa fa-right-to-bracket"></i> Sign In</button>
    </form>

    <div class="demo-hint">
      Demo: <strong>admin@erp.com</strong> / <strong>password</strong>
    </div>
  </div>
</div>
</body>
</html>
