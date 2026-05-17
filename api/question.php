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

$csrfIn = $input['_csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!hash_equals(csrfToken(), $csrfIn)) jsonError('CSRF token mismatch.', 419);

$action = $input['action'] ?? '';

function assertQuestionOwnership(int $questionId, int $userId): array {
    $q = db_query(
        'SELECT q.* FROM questions q
         JOIN quizzes qz ON q.quiz_id = qz.id
         WHERE q.id=? AND qz.created_by=?',
        [$questionId, $userId]
    )->fetch();
    if (!$q) jsonError('Question not found.', 404);
    return $q;
}

function insertChoices(int $qId, array $choices, string $type): void {
    if ($type === 'short_answer') {
        return;
    }
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
}

// ADD question
if ($action === 'add') {
    $quizId  = sanitizeInt($input['quiz_id'] ?? null);
    $text    = sanitizeString($input['text'] ?? '', 1000);
    $type    = in_array($input['type'] ?? '', ['multiple_choice','true_false','short_answer'])
               ? $input['type'] : 'multiple_choice';
    $points  = max(1, (int)($input['points'] ?? 1));
    $choices = $input['choices'] ?? [];

    if (!$quizId || !$text) jsonError('quiz_id and text are required.');

    $quiz = db_query(
        'SELECT id FROM quizzes WHERE id=? AND created_by=?',
        [$quizId, $user['id']]
    )->fetch();
    if (!$quiz) jsonError('Quiz not found.', 404);

    $maxOrder = db_query(
        'SELECT COALESCE(MAX(sort_order),0) AS m FROM questions WHERE quiz_id=?',
        [$quizId]
    )->fetchColumn();

    db_query(
        'INSERT INTO questions (quiz_id, question_text, question_type, points, sort_order)
         VALUES (?,?,?,?,?)',
        [$quizId, $text, $type, $points, $maxOrder + 1]
    );
    $qId = (int)db_last_id();
    insertChoices($qId, $choices, $type);

    jsonSuccess(['question_id' => $qId]);
}

// UPDATE question
if ($action === 'update') {
    $id      = sanitizeInt($input['id'] ?? null);
    $text    = sanitizeString($input['text'] ?? '', 1000);
    $type    = in_array($input['type'] ?? '', ['multiple_choice','true_false','short_answer'])
               ? $input['type'] : 'multiple_choice';
    $points  = max(1, (int)($input['points'] ?? 1));
    $choices = $input['choices'] ?? [];

    if (!$id || !$text) jsonError('id and text are required.');

    $existing = assertQuestionOwnership($id, $user['id']);

    if ($existing['question_type'] !== $type) {
        $hasResponses = db_query(
            'SELECT COUNT(*) FROM responses WHERE question_id=?',
            [$id]
        )->fetchColumn();
        if ($hasResponses > 0) {
            jsonError('Cannot change question type after students have answered it.');
        }
    }

    $pdo = Database::getInstance();
    $pdo->beginTransaction();
    try {
        db_query(
            'UPDATE questions SET question_text=?, question_type=?, points=? WHERE id=?',
            [$text, $type, $points, $id]
        );
        db_query('DELETE FROM choices WHERE question_id=?', [$id]);
        insertChoices($id, $choices, $type);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        jsonError('Failed to update question.', 500);
    }

    jsonSuccess(['question_id' => $id]);
}

// DELETE question
if ($action === 'delete') {
    $id = sanitizeInt($input['id'] ?? null);
    if (!$id) jsonError('Missing question id.');

    assertQuestionOwnership($id, $user['id']);
    db_query('DELETE FROM questions WHERE id=?', [$id]);
    jsonSuccess();
}

jsonError('Unknown action.');
