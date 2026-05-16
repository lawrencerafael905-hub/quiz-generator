<?php
// api/submit.php — Submit quiz attempt; auto-grade via stored procedure; broadcast via Pusher
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/pusher.php';

// Require Pusher SDK (install via Composer)
// composer require pusher/pusher-php-server
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

startSecureSession();
requireLogin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed.', 405);

$user  = currentUser();
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// CSRF
$csrfIn = $input['_csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!hash_equals(csrfToken(), $csrfIn)) jsonError('CSRF token mismatch.', 419);

$attemptId = sanitizeInt($input['attempt_id'] ?? null);
if (!$attemptId) jsonError('Missing attempt_id.');

// Confirm attempt belongs to this user and is not yet submitted
$attempt = db_query(
    'SELECT * FROM attempts WHERE id=? AND user_id=? AND submitted_at IS NULL',
    [$attemptId, $user['id']]
)->fetch();
if (!$attempt) jsonError('Attempt not found or already submitted.', 404);

$pdo = Database::getInstance();

// Save responses
$answers = $input['answers'] ?? [];
foreach ($answers as $questionId => $value) {
    $questionId = sanitizeInt($questionId);
    if (!$questionId) continue;

    // Verify question belongs to this quiz
    $q = db_query(
        'SELECT id, question_type FROM questions WHERE id=? AND quiz_id=?',
        [$questionId, $attempt['quiz_id']]
    )->fetch();
    if (!$q) continue;

    // Delete any prior response (retake scenario)
    db_query('DELETE FROM responses WHERE attempt_id=? AND question_id=?', [$attemptId, $questionId]);

    if ($q['question_type'] === 'short_answer') {
        $textAnswer = sanitizeString($value, 1000);
        db_query(
            'INSERT INTO responses (attempt_id, question_id, text_answer) VALUES (?,?,?)',
            [$attemptId, $questionId, $textAnswer]
        );
    } else {
        $choiceId = sanitizeInt($value);
        if ($choiceId) {
            db_query(
                'INSERT INTO responses (attempt_id, question_id, choice_id) VALUES (?,?,?)',
                [$attemptId, $questionId, $choiceId]
            );
        }
    }
}

// Call stored procedure to grade and mark submitted
$stmt = $pdo->prepare('CALL sp_submit_attempt(?, @score, @total)');
$stmt->execute([$attemptId]);
$pdo->closeCursor();

$result = $pdo->query('SELECT @score AS score, @total AS total')->fetch();
$score  = (float)$result['score'];
$total  = (int)$result['total'];

// Broadcast via Pusher for real-time update on teacher's results page
$channel = 'quiz-' . $attempt['quiz_id'];
broadcastEvent($channel, 'submission-received', [
    'username'   => $user['username'],
    'score'      => $score,
    'total'      => $total,
    'submitted_at' => date('Y-m-d H:i:s'),
]);

jsonSuccess([
    'score'        => $score,
    'total_points' => $total,
]);
