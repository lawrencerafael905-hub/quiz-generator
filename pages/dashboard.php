<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/database.php';

startSecureSession();
requireLogin();

if (isset($_GET['deleted'])) {
    setFlash('success', 'Quiz deleted successfully.');
    header('Location: ' . pageUrl('dashboard.php'));
    exit;
}

$user = currentUser();

if ($user['role'] === 'student') {
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
    $quizzes = db_query(
        'SELECT q.*,
                (SELECT COUNT(*) FROM attempts a WHERE a.quiz_id = q.id AND a.submitted_at IS NOT NULL) AS submissions,
                (SELECT COUNT(*) FROM questions qu WHERE qu.quiz_id = q.id) AS question_count,
                (SELECT COALESCE(SUM(qu.points), 0) FROM questions qu WHERE qu.quiz_id = q.id) AS total_points
         FROM quizzes q
         WHERE q.created_by = ?
         ORDER BY q.created_at DESC',
        [$user['id']]
    )->fetchAll();
}

renderHeader('Dashboard', 'dashboard');
?>
<div class="container">
  <div class="page-header">
    <div class="page-title">
      <h2><?= $user['role'] === 'student' ? 'Available Quizzes' : 'My Quizzes' ?></h2>
      <p><?= $user['role'] === 'student' ? 'Browse and take published quizzes' : 'Manage, publish, and review your quizzes' ?></p>
    </div>
  </div>

  <?php renderFlash(); ?>

  <div id="rt-notification" class="rt-banner" style="display:none"></div>

  <?php if (empty($quizzes)): ?>
    <div class="empty-state">
      <div class="empty-icon">📋</div>
      <h3><?= $user['role'] === 'student' ? 'No quizzes available yet' : 'No quizzes yet' ?></h3>
      <p><?= $user['role'] === 'student' ? 'Check back later — your teacher will publish quizzes here.' : 'Get started by creating your first quiz.' ?></p>
      <?php if ($user['role'] !== 'student'): ?>
        <a href="<?= e(pageUrl('quiz-create.php')) ?>" class="btn btn-primary">Create your first quiz</a>
      <?php endif; ?>
    </div>
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

        <?php if (!empty($q['description'])): ?>
          <p class="quiz-desc"><?= e(mb_substr($q['description'], 0, 100)) ?><?= mb_strlen($q['description']) > 100 ? '…' : '' ?></p>
        <?php endif; ?>

        <div class="quiz-meta">
          <?php if ($user['role'] === 'student'): ?>
            <span class="meta-item">By <?= e($q['author']) ?></span>
            <?php if ($q['taken'] > 0): ?>
              <span class="badge badge-info">Completed</span>
            <?php endif; ?>
          <?php else: ?>
            <span class="meta-item"><?= (int)$q['question_count'] ?> question<?= $q['question_count'] != 1 ? 's' : '' ?></span>
            <span class="meta-item"><?= (int)$q['total_points'] ?> pts</span>
            <span class="meta-item"><?= (int)$q['submissions'] ?> submission<?= $q['submissions'] != 1 ? 's' : '' ?></span>
          <?php endif; ?>
        </div>

        <div class="quiz-actions">
          <?php if ($user['role'] === 'student'): ?>
            <a href="<?= e(pageUrl('quiz-take.php')) ?>?id=<?= (int)$q['id'] ?>" class="btn btn-primary">
              <?= $q['taken'] > 0 ? 'Retake' : 'Start Quiz' ?>
            </a>
          <?php else: ?>
            <a href="<?= e(pageUrl('quiz-create.php')) ?>?id=<?= (int)$q['id'] ?>" class="btn">Edit</a>
            <a href="<?= e(pageUrl('results.php')) ?>?quiz_id=<?= (int)$q['id'] ?>" class="btn">Results</a>
            <a href="<?= e(assetUrl('api/quiz.php')) ?>?action=toggle_publish&id=<?= (int)$q['id'] ?>"
               class="btn <?= $q['is_published'] ? 'btn-warning' : 'btn-success' ?>">
              <?= $q['is_published'] ? 'Unpublish' : 'Publish' ?>
            </a>
            <button type="button" class="btn btn-danger del-quiz" data-id="<?= (int)$q['id'] ?>" data-title="<?= e($q['title']) ?>">Delete</button>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
const CSRF = <?= json_encode(csrfToken()) ?>;
document.querySelectorAll('.del-quiz').forEach(btn => {
  btn.addEventListener('click', async () => {
    const id = btn.dataset.id;
    const title = btn.dataset.title;
    if (!confirm('Delete "' + title + '"?\n\nAll questions, attempts, and responses will be permanently removed.')) return;
    btn.disabled = true;
    const res = await fetch('<?= e(assetUrl('api/quiz.php')) ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify({ action: 'delete', id: parseInt(id) })
    });
    const data = await res.json();
    if (data.success) {
      window.location.href = '<?= e(pageUrl('dashboard.php')) ?>?deleted=1';
    } else {
      alert(data.error || 'Failed to delete quiz.');
      btn.disabled = false;
    }
  });
});
</script>
<?php
$scripts = '';
if ($user['role'] !== 'student' && !empty($quizzes)) {
    $scripts = '<script src="https://js.pusher.com/8.0/pusher.min.js"></script>'
        . '<script src="' . e(assetUrl('assets/js/realtime.js')) . '"></script>'
        . '<script>const quizIds = ' . json_encode(array_column($quizzes, 'id'))
        . '; initDashboardRealtime(quizIds, "' . e(getenv('PUSHER_APP_KEY') ?: '') . '", "'
        . e(getenv('PUSHER_APP_CLUSTER') ?: '') . '");</script>';
}
renderFooter($scripts);
