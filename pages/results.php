<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layout.php';

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
<?php renderHeader('Results', 'dashboard'); ?>
<div class="container">

  <!-- Page header -->
  <div class="page-header">
    <h2>Results: <?= e($quiz['title']) ?></h2>
    <p>Submission breakdown and leaderboard for this quiz</p>
  </div>

  <!-- RT notification -->
  <div id="rt-notification" class="rt-banner" style="display:none">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
    <span id="rt-notification-text"></span>
  </div>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-icon" style="background:var(--blue-dim)">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </div>
      <div class="stat-label">Submissions</div>
      <div class="stat-value"><?= count($results) ?></div>
    </div>
    <?php if (!empty($results)):
      $avg = round(array_sum(array_column($results,'score')) / count($results), 1);
      $hi  = max(array_column($results,'score'));
    ?>
    <div class="stat-card">
      <div class="stat-icon" style="background:var(--accent-dim)">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
      </div>
      <div class="stat-label">Average Score</div>
      <div class="stat-value"><?= $avg ?>%</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:var(--green-dim)">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
      </div>
      <div class="stat-label">Highest Score</div>
      <div class="stat-value"><?= $hi ?>%</div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Leaderboard -->
  <div class="section-heading" style="margin-top:0">
    <h3>Leaderboard</h3>
    <span class="live-dot"></span>
    <span class="live-label">Live</span>
  </div>

  <?php if (empty($leaderboard)): ?>
    <div class="empty-state">No submissions yet â€” check back soon.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Rank</th>
          <th>Student</th>
          <th>Score</th>
          <th>Submitted</th>
        </tr>
      </thead>
      <tbody id="leaderboard-body">
        <?php foreach ($leaderboard as $r):
          $rank = (int)$r['rank_pos'];
          $rankClass = $rank === 1 ? 'rank-1' : ($rank === 2 ? 'rank-2' : ($rank === 3 ? 'rank-3' : 'rank-n'));
          $score = (float)$r['score'];
          $scoreClass = $score >= 75 ? 'score-high' : ($score >= 50 ? 'score-mid' : 'score-low');
          $initial = strtoupper(substr($r['username'], 0, 1));
        ?>
        <tr>
          <td><span class="rank-cell <?= $rankClass ?>"><?= $rank <= 3 ? ['ðŸ¥‡','ðŸ¥ˆ','ðŸ¥‰'][$rank-1] : $rank ?></span></td>
          <td>
            <div class="user-cell">
              <div class="user-avatar"><?= $initial ?></div>
              <?= e($r['username']) ?>
            </div>
          </td>
          <td><span class="score-chip <?= $scoreClass ?>"><?= number_format($score, 1) ?>%</span></td>
          <td><span class="date-text"><?= e($r['submitted_at']) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- All submissions -->
  <div class="section-heading">
    <h3>All Submissions</h3>
  </div>

  <?php if (empty($results)): ?>
    <div class="empty-state">No submissions yet.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Student</th>
          <th>Score</th>
          <th>Time Taken</th>
          <th>Submitted</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($results as $r):
          $score = (float)$r['score'];
          $scoreClass = $score >= 75 ? 'score-high' : ($score >= 50 ? 'score-mid' : 'score-low');
          $initial = strtoupper(substr($r['username'], 0, 1));
        ?>
        <tr>
          <td>
            <div class="user-cell">
              <div class="user-avatar"><?= $initial ?></div>
              <?= e($r['username']) ?>
            </div>
          </td>
          <td><span class="score-chip <?= $scoreClass ?>"><?= number_format($score, 1) ?>%</span></td>
          <td>
            <span class="time-chip">
              <?php if ($r['time_taken_seconds']): ?>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <?= gmdate('i:s', $r['time_taken_seconds']) ?>
              <?php else: ?>â€”<?php endif; ?>
            </span>
          </td>
          <td><span class="date-text"><?= e($r['submitted_at']) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</div>

<?php
$scripts = '<script src="https://js.pusher.com/8.0/pusher.min.js"></script>'
    . '<script src="' . e(assetUrl('assets/js/realtime.js')) . '"></script>'
    . '<script>initResultsRealtime(' . $quizId . ', "'
    . e(getenv('PUSHER_APP_KEY') ?: '') . '", "'
    . e(getenv('PUSHER_APP_CLUSTER') ?: '') . '");</script>';
renderFooter($scripts);
