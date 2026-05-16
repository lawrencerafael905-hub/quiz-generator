<?php
// includes/csrf.php — CSRF protection for all POST / state-changing requests

/**
 * Generate (or retrieve cached) CSRF token for this session.
 */
function csrfToken(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Return an HTML hidden input containing the CSRF token.
 */
function csrfField(): string {
    return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validate CSRF token from POST data.
 * Call at the top of every POST handler.
 */
function verifyCsrf(): void {
    $token = $_POST['_csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!hash_equals(csrfToken(), $token)) {
        http_response_code(419);
        die(json_encode(['error' => 'CSRF token mismatch. Please refresh and try again.']));
    }
}
