<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../config/database.php';

startSecureSession();
requireLogin();

$user = currentUser();

// Fetch quizzes
if ($user['role'] === 'student') {
    // Students see published quizzes and their attempts
    $quizzes = db_query(
        'SELECT q.*, u.username AS author,
                (SELECT COUNT(*) FROM attempts a WHERE a.quiz_id = q.id AND a.user_id = ? AND a.submitted_at IS NOT NULL) AS taken
         FROM quizzes q
         JOIN users u ON q.created_by = u.id
         WHERE q.is_published = 1
         ORDER BY q.created_at DESC',
        [$user['id']]
    )->fetchAll();
} else {
    // Teachers / admins see their own quizzes
    $quizzes = db_query(
        'SELECT q.*,
                (SELECT COUNT(*) FROM attempts a WHERE a.quiz_id = q.id AND a.submitted_at IS NOT NULL) AS submissions
         FROM quizzes q
         WHERE q.created_by = ?
         ORDER BY q.created_at DESC',
        [$user['id']]
    )->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard — Quiz Generator</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav class="navbar">
    <span class="brand">📝 Quiz Generator</span>
    <div class="nav-links">
        <span class="nav-user">👤 <?= e($user['username']) ?> (<?= e($user['role']) ?>)</span>
        <?php if (in_array($user['role'], ['teacher', 'admin'])): ?>
            <a href="quiz-create.php" class="btn btn-sm btn-primary">+ New Quiz</a>
        <?php endif; ?>
        <a href="../api/logout.php" class="btn btn-sm">Log Out</a>
    </div>
</nav>

<div class="container">
    <h2>
        <?= $user['role'] === 'student' ? 'Available Quizzes' : 'My Quizzes' ?>
    </h2>

    <!-- Real-time notification area -->
    <div id="rt-notification" class="rt-banner" style="display:none"></div>

    <?php if (empty($quizzes)): ?>
        <p class="empty-state">No quizzes found.
            <?php if ($user['role'] !== 'student'): ?>
                <a href="quiz-create.php">Create your first quiz →</a>
            <?php endif; ?>
        </p>
    <?php else: ?>
    <div class="quiz-grid">
        <?php foreach ($quizzes as $q): ?>
        <div class="quiz-card">
            <div class="quiz-card-header">
                <h3><?= e($q['title']) ?></h3>
                <?php if ($user['role'] !== 'student'): ?>
                    <span class="badge <?= $q['is_published'] ? 'badge-success' : 'badge-warning' ?>">
                        <?= $q['is_published'] ? 'Published' : 'Draft' ?>
                    </span>
                <?php endif; ?>
            </div>
            <p class="quiz-desc"><?= e(mb_substr($q['description'] ?? '', 0, 100)) ?></p>
            <div class="quiz-meta">
                <?php if ($user['role'] === 'student'): ?>
                    <span>Author: <?= e($q['author']) ?></span>
                    <?php if ($q['taken'] > 0): ?>
                        <span class="badge badge-info">Completed</span>
                    <?php endif; ?>
                <?php else: ?>
                    <span><?= (int)$q['submissions'] ?> submission(s)</span>
                <?php endif; ?>
            </div>
            <div class="quiz-actions">
                <?php if ($user['role'] === 'student'): ?>
                    <a href="quiz-take.php?id=<?= (int)$q['id'] ?>" class="btn btn-primary btn-sm">
                        <?= $q['taken'] > 0 ? 'Retake' : 'Start Quiz' ?>
                    </a>
                <?php else: ?>
                    <a href="quiz-create.php?id=<?= (int)$q['id'] ?>" class="btn btn-sm">Edit</a>
                    <a href="results.php?quiz_id=<?= (int)$q['id'] ?>" class="btn btn-sm">Results</a>
                    <a href="../api/quiz.php?action=toggle_publish&id=<?= (int)$q['id'] ?>"
                       class="btn btn-sm <?= $q['is_published'] ? 'btn-warning' : 'btn-success' ?>">
                        <?= $q['is_published'] ? 'Unpublish' : 'Publish' ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Pusher real-time: show live notification when a new submission arrives -->
<script src="https://js.pusher.com/8.0/pusher.min.js"></script>
<script src="../assets/js/realtime.js"></script>
<script>
    // Teacher dashboard: listen for submissions on their quizzes
    <?php if ($user['role'] !== 'student' && !empty($quizzes)): ?>
    const quizIds = <?= json_encode(array_column($quizzes, 'id')) ?>;
    initDashboardRealtime(quizIds, '<?= getenv("PUSHER_APP_KEY") ?>', '<?= getenv("PUSHER_APP_CLUSTER") ?>');
    <?php endif; ?>
</script>
</body>
</html>
