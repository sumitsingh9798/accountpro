<?php
declare(strict_types=1);
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/session.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? AND is_active=1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($pass, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['company_id'] = $user['company_id'];
        $comp = $pdo->prepare("SELECT entry_mode FROM companies WHERE id=?");
        $comp->execute([$user['company_id']]);
        $_SESSION['entry_mode'] = $comp->fetchColumn() ?: 'double';
        header('Location: /index.php');
        exit;
    }
    $error = 'Invalid email or password.';
}
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Login · AccountPro</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body style="background:linear-gradient(135deg,#1e3a5f,#14283f);min-height:100vh;display:flex;align-items:center;justify-content:center;">
<div class="card-panel" style="width:380px;">
  <h4 class="mb-3" style="color:var(--brand);font-weight:700;">AccountPro Login</h4>
  <?php if ($error): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
    <button class="btn btn-brand w-100">Sign In</button>
  </form>
</div>
</body></html>
