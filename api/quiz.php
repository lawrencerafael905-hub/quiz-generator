<?php
// api/quiz.php — Quiz CRUD API
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../config/database.php';

startSecureSession();
requireLogin();
header('Content-Type: application/json');

$user   = currentUser();
$action = get('action');

// Toggle publish — GET is acceptable here as it's teacher-only navigation
if ($action === 'toggle_publish') {
    requireRole('teacher', 'admin');
    $id = getInt('id');
    if (!$id) jsonError('Missing quiz id.');

    $quiz = db_query(
        'SELECT * FROM quizzes WHERE id=? AND created_by=?',
        [$id, $user['id']]
    )->fetch();
    if (!$quiz) jsonError('Quiz not found.', 404);

    db_query(
        'UPDATE quizzes SET is_published = ? WHERE id=?',
        [$quiz['is_published'] ? 0 : 1, $id]
    );
    header('Location: ../pages/dashboard.php');
    exit;
}

// All other operations require POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed.', 405);

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? get('action');

// Validate CSRF from JSON body or header
$csrfIn = $input['_csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!hash_equals(csrfToken(), $csrfIn)) jsonError('CSRF token mismatch.', 419);

// DELETE quiz
if ($action === 'delete') {
    requireRole('teacher', 'admin');
    $id = sanitizeInt($input['id'] ?? null);
    if (!$id) jsonError('Missing quiz id.');

    $deleted = db_query(
        'DELETE FROM quizzes WHERE id=? AND created_by=?',
        [$id, $user['id']]
    )->rowCount();

    if ($deleted) jsonSuccess();
    else jsonError('Quiz not found or permission denied.', 404);
}

jsonError('Unknown action.');
