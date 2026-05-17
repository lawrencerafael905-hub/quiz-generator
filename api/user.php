<?php
// api/user.php — Admin user CRUD API
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../config/database.php';

startSecureSession();
requireRole('admin');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed.', 405);

$user  = currentUser();
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$csrfIn = $input['_csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!hash_equals(csrfToken(), $csrfIn)) jsonError('CSRF token mismatch.', 419);

$action = $input['action'] ?? '';

function countAdmins(): int {
    return (int)db_query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
}

// CREATE user
if ($action === 'create') {
    $username = sanitizeString($input['username'] ?? '', 50);
    $email    = sanitizeEmail($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $role     = in_array($input['role'] ?? '', ['student','teacher','admin']) ? $input['role'] : 'student';

    if (strlen($username) < 3) jsonError('Username must be at least 3 characters.');
    if (!$email) jsonError('Valid email is required.');
    if (strlen($password) < 8) jsonError('Password must be at least 8 characters.');

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    try {
        db_query(
            'INSERT INTO users (username, email, password, role) VALUES (?,?,?,?)',
            [$username, $email, $hash, $role]
        );
        jsonSuccess(['user_id' => (int)db_last_id()]);
    } catch (PDOException $e) {
        jsonError('Username or email already exists.');
    }
}

// UPDATE user
if ($action === 'update') {
    $id       = sanitizeInt($input['id'] ?? null);
    $username = sanitizeString($input['username'] ?? '', 50);
    $email    = sanitizeEmail($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $role     = in_array($input['role'] ?? '', ['student','teacher','admin']) ? $input['role'] : 'student';

    if (!$id) jsonError('User id is required.');
    if (strlen($username) < 3) jsonError('Username must be at least 3 characters.');
    if (!$email) jsonError('Valid email is required.');

    $target = db_query('SELECT id, role FROM users WHERE id=?', [$id])->fetch();
    if (!$target) jsonError('User not found.', 404);

    if ((int)$id === (int)$user['id'] && $role !== 'admin') {
        jsonError('You cannot remove your own admin role.');
    }

    if ($target['role'] === 'admin' && $role !== 'admin' && countAdmins() <= 1) {
        jsonError('Cannot demote the last admin account.');
    }

    try {
        if ($password !== '') {
            if (strlen($password) < 8) jsonError('Password must be at least 8 characters.');
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            db_query(
                'UPDATE users SET username=?, email=?, password=?, role=? WHERE id=?',
                [$username, $email, $hash, $role, $id]
            );
        } else {
            db_query(
                'UPDATE users SET username=?, email=?, role=? WHERE id=?',
                [$username, $email, $role, $id]
            );
        }
        jsonSuccess();
    } catch (PDOException $e) {
        jsonError('Username or email already exists.');
    }
}

// DELETE user
if ($action === 'delete') {
    $id = sanitizeInt($input['id'] ?? null);
    if (!$id) jsonError('User id is required.');

    if ((int)$id === (int)$user['id']) {
        jsonError('You cannot delete your own account.');
    }

    $target = db_query('SELECT id, role FROM users WHERE id=?', [$id])->fetch();
    if (!$target) jsonError('User not found.', 404);

    if ($target['role'] === 'admin' && countAdmins() <= 1) {
        jsonError('Cannot delete the last admin account.');
    }

    db_query('DELETE FROM users WHERE id=?', [$id]);
    jsonSuccess();
}

jsonError('Unknown action.');
