<?php
// includes/auth.php — Authentication using Bcrypt

require_once __DIR__ . '/../config/database.php';

const SESSION_USER_KEY = '_qg_user';

function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => (int)(getenv('SESSION_LIFETIME') ?: 7200),
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

/**
 * Register a new user.
 * Password is hashed with PASSWORD_BCRYPT (cost 12).
 */
function registerUser(string $username, string $email, string $password, string $role = 'student'): bool {
    $username = trim($username);
    $email    = strtolower(trim($email));

    // Validate
    if (strlen($username) < 3 || strlen($username) > 50)   return false;
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))         return false;
    if (strlen($password) < 8)                              return false;

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    try {
        db_query(
            'INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)',
            [$username, $email, $hash, $role]
        );
        return true;
    } catch (PDOException $e) {
        // Duplicate username/email
        return false;
    }
}

/**
 * Attempt login; returns user row on success or false on failure.
 * Uses constant-time comparison via password_verify().
 */
function loginUser(string $username, string $password): array|false {
    $stmt = db_query(
        'SELECT id, username, email, password, role FROM users WHERE username = ? LIMIT 1',
        [trim($username)]
    );
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }

    // Rehash if algorithm/cost changed
    if (password_needs_rehash($user['password'], PASSWORD_BCRYPT, ['cost' => 12])) {
        $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        db_query('UPDATE users SET password = ? WHERE id = ?', [$newHash, $user['id']]);
    }

    // Regenerate session ID to prevent fixation
    session_regenerate_id(true);

    unset($user['password']);
    $_SESSION[SESSION_USER_KEY] = $user;
    return $user;
}

function logoutUser(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function currentUser(): ?array {
    return $_SESSION[SESSION_USER_KEY] ?? null;
}

function isLoggedIn(): bool {
    return isset($_SESSION[SESSION_USER_KEY]);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /pages/login.php');
        exit;
    }
}

function requireRole(string ...$roles): void {
    requireLogin();
    $user = currentUser();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        die('Access denied.');
    }
}

function isAdmin(): bool {
    $user = currentUser();
    return $user && $user['role'] === 'admin';
}

function isTeacher(): bool {
    $user = currentUser();
    return $user && in_array($user['role'], ['teacher', 'admin'], true);
}
