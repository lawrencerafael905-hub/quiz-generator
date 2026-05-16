<?php
// api/question.php — Question CRUD API
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../config/database.php';

startSecureSession();
requireRole('teacher', 'admin');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed.', 405);

$user  = currentUser();
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// CSRF validation
$csrfIn = $input['_csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!hash_equals(csrfToken(), $csrfIn)) jsonError('CSRF token mismatch.', 419);

$action = $input['action'] ?? '';

// ADD question
if ($action === 'add') {
    $quizId = sanitizeInt($input['quiz_id'] ?? null);
    $text   = sanitizeString($input['text'] ?? '', 1000);
    $type   = in_array($input['type'] ?? '', ['multiple_choice','true_false','short_answer'])
              ? $input['type'] : 'multiple_choice';
    $points = max(1, (int)($input['points'] ?? 1));
    $choices = $input['choices'] ?? [];

    if (!$quizId || !$text) jsonError('quiz_id and text are required.');

    // Ensure quiz belongs to this teacher
    $quiz = db_query(
        'SELECT id FROM quizzes WHERE id=? AND created_by=?',
        [$quizId, $user['id']]
    )->fetch();
    if (!$quiz) jsonError('Quiz not found.', 404);

    // Get next sort order
    $maxOrder = db_query(
        'SELECT COALESCE(MAX(sort_order),0) AS m FROM questions WHERE quiz_id=?',
        [$quizId]
    )->fetchColumn();

    // Insert question (prepared statement — SQL injection safe)
    db_query(
        'INSERT INTO questions (quiz_id, question_text, question_type, points, sort_order)
         VALUES (?,?,?,?,?)',
        [$quizId, $text, $type, $points, $maxOrder + 1]
    );
    $qId = (int)db_last_id();

    // Insert choices
    foreach ($choices as $c) {
        $cText    = sanitizeString($c['text'] ?? '', 500);
        $cCorrect = ($c['correct'] ?? 0) ? 1 : 0;
        if ($cText) {
            db_query(
                'INSERT INTO choices (question_id, choice_text, is_correct) VALUES (?,?,?)',
                [$qId, $cText, $cCorrect]
            );
        }
    }

    jsonSuccess(['question_id' => $qId]);
}

// DELETE question
if ($action === 'delete') {
    $id = sanitizeInt($input['id'] ?? null);
    if (!$id) jsonError('Missing question id.');

    // Confirm ownership via quiz
    $q = db_query(
        'SELECT q.id FROM questions q
         JOIN quizzes qz ON q.quiz_id = qz.id
         WHERE q.id=? AND qz.created_by=?',
        [$id, $user['id']]
    )->fetch();
    if (!$q) jsonError('Question not found.', 404);

    db_query('DELETE FROM questions WHERE id=?', [$id]);
    jsonSuccess();
}

jsonError('Unknown action.');
