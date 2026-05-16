<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../config/database.php';

startSecureSession();
requireLogin();

$user   = currentUser();
$quizId = getInt('id');

if (!$quizId) { header('Location: dashboard.php'); exit; }

$quiz = db_query(
    'SELECT * FROM quizzes WHERE id = ? AND is_published = 1',
    [$quizId]
)->fetch();

if (!$quiz) { header('Location: dashboard.php'); exit; }

// Load questions and choices
$questions = db_query(
    'SELECT * FROM questions WHERE quiz_id = ? ORDER BY sort_order ASC',
    [$quizId]
)->fetchAll();

foreach ($questions as &$q) {
    if ($q['question_type'] !== 'short_answer') {
        $q['choices'] = db_query(
            'SELECT id, choice_text FROM choices WHERE question_id = ?',
            [$q['id']]
        )->fetchAll();
    }
}
unset($q);

// Create (or retrieve in-progress) attempt
$attempt = db_query(
    'SELECT * FROM attempts WHERE quiz_id=? AND user_id=? AND submitted_at IS NULL LIMIT 1',
    [$quizId, $user['id']]
)->fetch();

if (!$attempt) {
    db_query(
        'INSERT INTO attempts (quiz_id, user_id) VALUES (?,?)',
        [$quizId, $user['id']]
    );
    $attemptId = (int)db_last_id();
} else {
    $attemptId = (int)$attempt['id'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($quiz['title']) ?> — Quiz Generator</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav class="navbar">
    <span class="brand">📝 <?= e($quiz['title']) ?></span>
    <?php if ($quiz['time_limit'] > 0): ?>
    <div id="timer" class="timer">⏱ <span id="timer-display">--:--</span></div>
    <?php endif; ?>
</nav>

<div class="container">
    <form id="quiz-form">
        <input type="hidden" name="_csrf_token" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="attempt_id" value="<?= $attemptId ?>">

        <?php foreach ($questions as $i => $q): ?>
        <div class="question-card take-mode">
            <p class="question-num">Question <?= $i+1 ?> of <?= count($questions) ?>
                <span class="badge badge-info"><?= (int)$q['points'] ?> pt(s)</span>
            </p>
            <p class="question-text"><?= e($q['question_text']) ?></p>

            <?php if ($q['question_type'] === 'short_answer'): ?>
                <textarea name="answer[<?= (int)$q['id'] ?>]" rows="3"
                          placeholder="Type your answer here…" class="short-answer"></textarea>

            <?php elseif ($q['question_type'] === 'true_false'): ?>
                <?php foreach ($q['choices'] as $c): ?>
                <label class="choice-label">
                    <input type="radio" name="answer[<?= (int)$q['id'] ?>]"
                           value="<?= (int)$c['id'] ?>">
                    <?= e($c['choice_text']) ?>
                </label>
                <?php endforeach; ?>

            <?php else: ?>
                <?php foreach ($q['choices'] as $c): ?>
                <label class="choice-label">
                    <input type="radio" name="answer[<?= (int)$q['id'] ?>]"
                           value="<?= (int)$c['id'] ?>">
                    <?= e($c['choice_text']) ?>
                </label>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <div style="margin:2rem 0;text-align:center">
            <button type="button" id="submit-btn" class="btn btn-primary btn-lg">
                Submit Quiz
            </button>
        </div>
    </form>

    <div id="result-section" style="display:none" class="card result-card">
        <h2>🎉 Quiz Submitted!</h2>
        <p id="score-display" class="score-big"></p>
        <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
        <button onclick="window.location.reload()" class="btn">Retake</button>
    </div>
</div>

<script src="https://js.pusher.com/8.0/pusher.min.js"></script>
<script src="../assets/js/realtime.js"></script>
<script>
const ATTEMPT_ID  = <?= $attemptId ?>;
const QUIZ_ID     = <?= (int)$quiz['id'] ?>;
const TIME_LIMIT  = <?= (int)$quiz['time_limit'] ?>;
const CSRF        = <?= json_encode(csrfToken()) ?>;
const PUSHER_KEY  = '<?= getenv("PUSHER_APP_KEY") ?>';
const PUSHER_CLUS = '<?= getenv("PUSHER_APP_CLUSTER") ?>';

// Timer
if (TIME_LIMIT > 0) {
    let remaining = TIME_LIMIT;
    const display = document.getElementById('timer-display');
    const tick = setInterval(() => {
        remaining--;
        const m = String(Math.floor(remaining / 60)).padStart(2,'0');
        const s = String(remaining % 60).padStart(2,'0');
        display.textContent = `${m}:${s}`;
        if (remaining <= 0) { clearInterval(tick); submitQuiz(); }
    }, 1000);
}

document.getElementById('submit-btn').addEventListener('click', () => {
    if (!confirm('Submit quiz? You cannot change answers after submission.')) return;
    submitQuiz();
});

async function submitQuiz() {
    document.getElementById('submit-btn').disabled = true;

    const form    = document.getElementById('quiz-form');
    const data    = new FormData(form);
    const payload = { attempt_id: ATTEMPT_ID, answers: {}, _csrf_token: CSRF };

    for (const [k, v] of data.entries()) {
        const m = k.match(/^answer\[(\d+)\]$/);
        if (m) payload.answers[m[1]] = v;
    }

    const res  = await fetch('../api/submit.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify(payload)
    });
    const json = await res.json();

    if (json.success) {
        document.getElementById('quiz-form').style.display = 'none';
        document.getElementById('result-section').style.display = 'block';
        document.getElementById('score-display').textContent =
            `Your score: ${json.score}% (${json.total_points} points total)`;

        // Real-time: listen for leaderboard update
        initStudentRealtime(QUIZ_ID, PUSHER_KEY, PUSHER_CLUS);
    } else {
        alert(json.error || 'Submission failed.');
        document.getElementById('submit-btn').disabled = false;
    }
}
</script>
</body>
</html>
