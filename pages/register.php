<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/sanitize.php';

startSecureSession();

if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username  = post('username');
    $email     = sanitizeEmail($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['password_confirm'] ?? '';
    $role      = in_array($_POST['role'] ?? '', ['student', 'teacher']) ? $_POST['role'] : 'student';

    if (!$username || !$email || !$password) {
        $error = 'All fields are required.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $ok = registerUser($username, $email, $password, $role);
        if ($ok) {
            $success = 'Account created! You can now log in.';
        } else {
            $error = 'Username or email already in use.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register — Quiz Generator</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-body">
<div class="auth-card">
    <h1>Create Account</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <?= csrfField() ?>
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required
                   maxlength="50" value="<?= e(post('username')) ?>">
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required
                   value="<?= e($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="role">Role</label>
            <select id="role" name="role">
                <option value="student">Student</option>
                <option value="teacher">Teacher</option>
            </select>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required
                   minlength="8">
        </div>
        <div class="form-group">
            <label for="password_confirm">Confirm Password</label>
            <input type="password" id="password_confirm" name="password_confirm" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Register</button>
    </form>
    <p class="auth-link">Already have an account? <a href="login.php">Log in</a></p>
</div>
</body>
</html>
