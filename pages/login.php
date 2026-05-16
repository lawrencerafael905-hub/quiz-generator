<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/sanitize.php';

startSecureSession();

if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = post('username');
    $password = $_POST['password'] ?? '';

    $user = loginUser($username, $password);
    if ($user) {
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — Quiz Generator</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-body">
<div class="auth-card">
    <h1>Quiz Generator</h1>
    <p class="subtitle">NEUST — ITWS Case Study</p>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <?= csrfField() ?>
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required
                   autocomplete="username" maxlength="50"
                   value="<?= e(post('username')) ?>">
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required
                   autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Log In</button>
    </form>
    <p class="auth-link">No account? <a href="register.php">Register</a></p>
</div>
</body>
</html>
