<?php
require_once __DIR__ . '/../config.php';
startDbSession();

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: ' . BASE_URL . '/admin/');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = getDB()->prepare('SELECT * FROM admin_users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user']      = $user['username'];
        header('Location: ' . BASE_URL . '/admin/');
        exit;
    }
    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — E-Invitation</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;1,400&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
</head>
<body class="login-body">
<canvas id="particleCanvas"></canvas>
<div class="login-container">
    <div class="login-card">
        <div class="login-logo">
            <i class="fas fa-heart"></i>
            <h1>E-Invitation</h1>
            <p>Admin Portal</p>
        </div>
        <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-triangle-exclamation"></i> <?= e($error) ?></div>
        <?php endif; ?>
        <form method="POST" class="login-form">
            <div class="form-group">
                <label><i class="fas fa-user"></i> Username</label>
                <input type="text" name="username" required placeholder="admin" value="<?= e($_POST['username'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Password</label>
                <div class="password-wrap">
                    <input type="password" name="password" required placeholder="••••••••" id="pwdInput">
                    <button type="button" class="toggle-pwd" onclick="togglePwd()"><i class="fas fa-eye" id="eyeIcon"></i></button>
                </div>
            </div>
            <button type="submit" class="btn-login">
                <i class="fas fa-right-to-bracket"></i> Sign In
            </button>
        </form>
        <p class="login-hint">Default: admin / admin123</p>
    </div>
</div>
<script>
function togglePwd() {
    const inp = document.getElementById('pwdInput');
    const ico = document.getElementById('eyeIcon');
    if (inp.type === 'password') { inp.type = 'text'; ico.className = 'fas fa-eye-slash'; }
    else { inp.type = 'password'; ico.className = 'fas fa-eye'; }
}
</script>
<script src="<?= BASE_URL ?>/assets/js/particles-mini.js"></script>
</body>
</html>
