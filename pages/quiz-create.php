<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../config/database.php';

startSecureSession();
requireRole('teacher', 'admin');

$user   = currentUser();
$quizId = getInt('id');
$quiz   = null;
$questions = [];

// Load existing quiz for editing
if ($quizId) {
    $quiz = db_query(
        'SELECT * FROM quizzes WHERE id = ? AND created_by = ?',
        [$quizId, $user['id']]
    )->fetch();
    if (!$quiz) { header('Location: dashboard.php'); exit; }

    $questions = db_query(
        'SELECT q.*, GROUP_CONCAT(c.id,"|||",c.choice_text,"|||",c.is_correct ORDER BY c.id SEPARATOR ";;;") AS choices_raw
         FROM questions q
         LEFT JOIN choices c ON c.question_id = q.id
         WHERE q.quiz_id = ?
         GROUP BY q.id
         ORDER BY q.sort_order ASC',
        [$quizId]
    )->fetchAll();
}

$success = $error = '';

// Handle quiz save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $title      = post('title', '');
    $desc       = post('description', '');
    $timeLimit  = postInt('time_limit') ?? 0;

    if (!$title) {
        $error = 'Quiz title is required.';
    } else {
        if ($quizId && $quiz) {
            // UPDATE
            db_query(
                'UPDATE quizzes SET title=?, description=?, time_limit=? WHERE id=? AND created_by=?',
                [$title, $desc, $timeLimit, $quizId, $user['id']]
            );
            $success = 'Quiz updated.';
        } else {
            // INSERT
            db_query(
                'INSERT INTO quizzes (title, description, time_limit, created_by) VALUES (?,?,?,?)',
                [$title, $desc, $timeLimit, $user['id']]
            );
            $quizId = (int)db_last_id();
            header("Location: quiz-create.php?id=$quizId&created=1");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $quiz ? 'Edit Quiz' : 'New Quiz' ?> — Quiz Generator</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav class="navbar">
    <a class="brand" href="dashboard.php">📝 Quiz Generator</a>
    <div class="nav-links">
        <a href="dashboard.php" class="btn btn-sm">← Dashboard</a>
    </div>
</nav>

<div class="container">
    <h2><?= $quiz ? 'Edit Quiz' : 'Create New Quiz' ?></h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['created'])): ?>
        <div class="alert alert-success">Quiz created! Now add questions below.</div>
    <?php endif; ?>

    <!-- Quiz details form -->
    <div class="card">
        <form method="POST" action="">
            <?= csrfField() ?>
            <div class="form-group">
                <label for="title">Quiz Title *</label>
                <input type="text" id="title" name="title" required maxlength="200"
                       value="<?= e($quiz['title'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3"><?= e($quiz['description'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label for="time_limit">Time Limit (seconds, 0 = unlimited)</label>
                <input type="number" id="time_limit" name="time_limit" min="0"
                       value="<?= (int)($quiz['time_limit'] ?? 0) ?>">
            </div>
            <button type="submit" class="btn btn-primary">
                <?= $quiz ? 'Save Changes' : 'Create Quiz' ?>
            </button>
        </form>
    </div>

    <?php if ($quizId && $quiz): ?>
    <!-- Questions section -->
    <h3 style="margin-top:2rem">Questions</h3>

    <div id="questions-list">
        <?php foreach ($questions as $i => $q): ?>
        <div class="question-card" data-qid="<?= (int)$q['id'] ?>">
            <div class="question-header">
                <strong>Q<?= $i+1 ?>.</strong> <?= e($q['question_text']) ?>
                <span class="badge badge-info"><?= e($q['question_type']) ?></span>
                <span class="badge"><?= (int)$q['points'] ?> pt(s)</span>
                <button class="btn btn-sm btn-danger del-question"
                        data-id="<?= (int)$q['id'] ?>">Delete</button>
            </div>
            <?php if ($q['choices_raw']): ?>
            <ul class="choices-list">
                <?php foreach (explode(';;;', $q['choices_raw']) as $c): ?>
                    <?php [$cId, $cText, $cCorrect] = explode('|||', $c); ?>
                    <li class="<?= $cCorrect ? 'correct' : '' ?>">
                        <?= $cCorrect ? '✅ ' : '○ ' ?><?= e($cText) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Add question form -->
    <div class="card" style="margin-top:1.5rem">
        <h4>Add Question</h4>
        <div class="form-group">
            <label>Question Text *</label>
            <textarea id="q-text" rows="2" maxlength="1000"></textarea>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Type</label>
                <select id="q-type">
                    <option value="multiple_choice">Multiple Choice</option>
                    <option value="true_false">True / False</option>
                    <option value="short_answer">Short Answer</option>
                </select>
            </div>
            <div class="form-group">
                <label>Points</label>
                <input type="number" id="q-points" value="1" min="1" max="100">
            </div>
        </div>

        <div id="choices-builder">
            <label>Choices (check the correct one)</label>
            <div id="choices-container">
                <div class="choice-row">
                    <input type="checkbox" class="choice-correct">
                    <input type="text" class="choice-text" placeholder="Choice A">
                </div>
                <div class="choice-row">
                    <input type="checkbox" class="choice-correct">
                    <input type="text" class="choice-text" placeholder="Choice B">
                </div>
                <div class="choice-row">
                    <input type="checkbox" class="choice-correct">
                    <input type="text" class="choice-text" placeholder="Choice C">
                </div>
                <div class="choice-row">
                    <input type="checkbox" class="choice-correct">
                    <input type="text" class="choice-text" placeholder="Choice D">
                </div>
            </div>
        </div>

        <button id="add-question-btn" class="btn btn-primary" style="margin-top:1rem">
            Add Question
        </button>
        <div id="q-feedback" class="alert" style="display:none;margin-top:.5rem"></div>
    </div>
    <?php endif; ?>
</div>

<script>
const QUIZ_ID = <?= $quizId ?? 'null' ?>;
const CSRF    = <?= json_encode(csrfToken()) ?>;

// Type changes
document.getElementById('q-type')?.addEventListener('change', function() {
    const cb = document.getElementById('choices-builder');
    const type = this.value;
    if (type === 'true_false') {
        document.getElementById('choices-container').innerHTML = `
            <div class="choice-row"><input type="radio" name="tf" value="1" class="choice-correct"> <span class="choice-text-static">True</span></div>
            <div class="choice-row"><input type="radio" name="tf" value="0" class="choice-correct"> <span class="choice-text-static">False</span></div>`;
        cb.style.display = 'block';
    } else if (type === 'short_answer') {
        cb.style.display = 'none';
    } else {
        cb.style.display = 'block';
        document.getElementById('choices-container').innerHTML = ['A','B','C','D'].map(l => `
            <div class="choice-row">
                <input type="checkbox" class="choice-correct">
                <input type="text" class="choice-text" placeholder="Choice ${l}">
            </div>`).join('');
    }
});

// Add question
document.getElementById('add-question-btn')?.addEventListener('click', async () => {
    const text  = document.getElementById('q-text').value.trim();
    const type  = document.getElementById('q-type').value;
    const pts   = parseInt(document.getElementById('q-points').value) || 1;

    if (!text) { showFeedback('Question text is required.', false); return; }

    let choices = [];
    if (type !== 'short_answer') {
        if (type === 'true_false') {
            const sel = document.querySelector('input[name="tf"]:checked');
            choices = [
                { text: 'True',  correct: sel?.value === '1' ? 1 : 0 },
                { text: 'False', correct: sel?.value === '0' ? 1 : 0 },
            ];
        } else {
            document.querySelectorAll('#choices-container .choice-row').forEach(row => {
                const t = row.querySelector('.choice-text')?.value.trim();
                const c = row.querySelector('.choice-correct')?.checked ? 1 : 0;
                if (t) choices.push({ text: t, correct: c });
            });
            if (!choices.some(c => c.correct)) {
                showFeedback('Please mark at least one correct choice.', false); return;
            }
        }
    }

    const res = await fetch('../api/question.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify({ action:'add', quiz_id: QUIZ_ID, text, type, points: pts, choices })
    });
    const data = await res.json();
    if (data.success) {
        showFeedback('Question added!', true);
        location.reload();
    } else {
        showFeedback(data.error || 'Error', false);
    }
});

// Delete question
document.querySelectorAll('.del-question').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm('Delete this question?')) return;
        const id = btn.dataset.id;
        const res = await fetch('../api/question.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({ action: 'delete', id })
        });
        const data = await res.json();
        if (data.success) btn.closest('.question-card').remove();
        else alert(data.error || 'Error deleting question.');
    });
});

function showFeedback(msg, ok) {
    const el = document.getElementById('q-feedback');
    el.textContent = msg;
    el.className = `alert ${ok ? 'alert-success' : 'alert-danger'}`;
    el.style.display = 'block';
}
</script>
<link rel="stylesheet" href="../assets/css/style.css">
</body>
</html>
