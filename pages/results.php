<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../config/database.php';

startSecureSession();
requireRole('teacher', 'admin');

$user   = currentUser();
$quizId = getInt('quiz_id');

if (!$quizId) { header('Location: dashboard.php'); exit; }

$quiz = db_query(
    'SELECT * FROM quizzes WHERE id = ? AND created_by = ?',
    [$quizId, $user['id']]
)->fetch();
if (!$quiz) { header('Location: dashboard.php'); exit; }

// Use stored procedure for results
$pdo  = Database::getInstance();
$stmt = $pdo->prepare('CALL sp_get_quiz_results(?)');
$stmt->execute([$quizId]);
$results = $stmt->fetchAll();

// Leaderboard via stored procedure
$stmt->closeCursor(); // required between stored proc calls
$stmt2 = $pdo->prepare('CALL sp_get_leaderboard(?)');
$stmt2->execute([$quizId]);
$leaderboard = $stmt2->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Results — <?= e($quiz['title']) ?></title>
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
    <h2>Results: <?= e($quiz['title']) ?></h2>

    <!-- Live notification area -->
    <div id="rt-notification" class="rt-banner" style="display:none"></div>

    <!-- Summary cards -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-label">Submissions</div>
            <div class="stat-value"><?= count($results) ?></div>
        </div>
        <?php if (!empty($results)): ?>
        <div class="stat-card">
            <div class="stat-label">Average Score</div>
            <div class="stat-value"><?= round(array_sum(array_column($results,'score')) / count($results), 1) ?>%</div>
        </div>
        <div class="stat-card">
            <div class="stat-label">Highest Score</div>
            <div class="stat-value"><?= max(array_column($results,'score')) ?>%</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Leaderboard -->
    <h3>Leaderboard</h3>
    <?php if (empty($leaderboard)): ?>
        <p class="empty-state">No submissions yet.</p>
    <?php else: ?>
    <table class="results-table">
        <thead>
            <tr>
                <th>Rank</th>
                <th>Username</th>
                <th>Score</th>
                <th>Submitted</th>
            </tr>
        </thead>
        <tbody id="leaderboard-body">
            <?php foreach ($leaderboard as $r): ?>
            <tr>
                <td><?= (int)$r['rank_pos'] ?></td>
                <td><?= e($r['username']) ?></td>
                <td><?= number_format((float)$r['score'], 2) ?>%</td>
                <td><?= e($r['submitted_at']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <!-- Full results -->
    <h3 style="margin-top:2rem">All Submissions</h3>
    <table class="results-table">
        <thead>
            <tr><th>Username</th><th>Score</th><th>Time Taken</th><th>Submitted</th></tr>
        </thead>
        <tbody>
            <?php foreach ($results as $r): ?>
            <tr>
                <td><?= e($r['username']) ?></td>
                <td><?= number_format((float)$r['score'], 2) ?>%</td>
                <td><?= $r['time_taken_seconds'] ? gmdate('i:s', $r['time_taken_seconds']) : '—' ?></td>
                <td><?= e($r['submitted_at']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Real-time: refresh leaderboard when new submission arrives -->
<script src="https://js.pusher.com/8.0/pusher.min.js"></script>
<script src="../assets/js/realtime.js"></script>
<script>
initResultsRealtime(
    <?= $quizId ?>,
    '<?= getenv("PUSHER_APP_KEY") ?>',
    '<?= getenv("PUSHER_APP_CLUSTER") ?>'
);
</script>
</body>
</html>
