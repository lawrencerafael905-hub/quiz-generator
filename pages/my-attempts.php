<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/database.php';

startSecureSession();
requireLogin();

$user = currentUser();

$attempts = db_query(
    'SELECT a.id, a.score, a.submitted_at, q.id AS quiz_id, q.title, q.is_published
     FROM attempts a
     JOIN quizzes q ON a.quiz_id = q.id
     WHERE a.user_id = ? AND a.submitted_at IS NOT NULL
     ORDER BY a.submitted_at DESC',
    [$user['id']]
)->fetchAll();

renderHeader('My Attempts', 'my-attempts');
?>
<div class="container">
  <div class="page-header">
    <div class="page-title">
      <h2>My Attempts</h2>
      <p>Your quiz submission history and scores</p>
    </div>
  </div>

  <?php if (empty($attempts)): ?>
    <div class="empty-state">
      <div class="empty-icon">📝</div>
      <h3>No attempts yet</h3>
      <p>Take a quiz from the dashboard to see your results here.</p>
      <a href="<?= e(pageUrl('dashboard.php')) ?>" class="btn btn-primary">Browse Quizzes</a>
    </div>
  <?php else: ?>
    <div class="data-table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Quiz</th>
            <th>Score</th>
            <th>Submitted</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($attempts as $a): ?>
          <tr>
            <td><?= e($a['title']) ?></td>
            <td><strong style="color:var(--accent)"><?= number_format((float)$a['score'], 1) ?>%</strong></td>
            <td><?= e(date('M j, Y g:i A', strtotime($a['submitted_at']))) ?></td>
            <td>
              <?php if ($a['is_published']): ?>
                <a href="<?= e(pageUrl('quiz-take.php')) ?>?id=<?= (int)$a['quiz_id'] ?>" class="btn btn-sm">Retake</a>
              <?php else: ?>
                <span style="color:var(--sub);font-size:12px">Unavailable</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php renderFooter(); ?>
