<?php
// includes/sanitize.php — XSS prevention & input validation

/**
 * Sanitize a string for safe HTML output (prevents XSS).
 */
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Sanitize and trim a plain text field.
 */
function sanitizeString(?string $value, int $maxLen = 255): string {
    if ($value === null) return '';
    $value = trim(strip_tags($value));
    return mb_substr($value, 0, $maxLen);
}

/**
 * Sanitize and validate an integer.
 */
function sanitizeInt(mixed $value): ?int {
    $filtered = filter_var($value, FILTER_VALIDATE_INT);
    return ($filtered === false) ? null : (int)$filtered;
}

/**
 * Sanitize an email address.
 */
function sanitizeEmail(?string $value): ?string {
    if ($value === null) return null;
    $clean = filter_var(trim($value), FILTER_SANITIZE_EMAIL);
    return filter_var($clean, FILTER_VALIDATE_EMAIL) ? strtolower($clean) : null;
}

/**
 * Get a sanitized POST value.
 */
function post(string $key, string $default = ''): string {
    return sanitizeString($_POST[$key] ?? $default);
}

/**
 * Get a sanitized GET value.
 */
function get(string $key, string $default = ''): string {
    return sanitizeString($_GET[$key] ?? $default);
}

/**
 * Get a validated integer from POST.
 */
function postInt(string $key): ?int {
    return sanitizeInt($_POST[$key] ?? null);
}

/**
 * Get a validated integer from GET.
 */
function getInt(string $key): ?int {
    return sanitizeInt($_GET[$key] ?? null);
}

/**
 * Return a JSON error response and exit.
 */
function jsonError(string $message, int $code = 400): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

/**
 * Return a JSON success response and exit.
 */
function jsonSuccess(array $data = []): never {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, ...$data]);
    exit;
}
