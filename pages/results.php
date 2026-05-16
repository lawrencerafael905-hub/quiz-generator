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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,400&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:        #080b14;
    --surface:   rgba(18, 22, 42, 0.88);
    --surface-2: rgba(22, 27, 50, 0.7);
    --border:    rgba(255,255,255,0.08);
    --border-hi: rgba(255,255,255,0.13);
    --accent:    #f5c842;
    --accent-dim:rgba(245,200,66,0.13);
    --blue:      #5b8eff;
    --blue-dim:  rgba(91,142,255,0.12);
    --green:     #3ecf8e;
    --green-dim: rgba(62,207,142,0.12);
    --orange:    #ff9a3c;
    --orange-dim:rgba(255,154,60,0.12);
    --text:      #eceef8;
    --sub:       #8890b8;
    --danger:    #ff5e72;
    --radius:    12px;
  }

  html, body { min-height: 100%; }
  body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
  }

  /* ── Background ── */
  .bg-layer { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
  .orb { position: absolute; border-radius: 50%; filter: blur(110px); animation: drift 20s ease-in-out infinite alternate; }
  .orb-1 { width: 700px; height: 700px; background: #1a3aff; opacity: .10; top: -250px; left: -200px; }
  .orb-2 { width: 500px; height: 500px; background: #f5c842; opacity: .07; bottom: -200px; right: -150px; animation-delay: -7s; }
  .orb-3 { width: 360px; height: 360px; background: #8b2fff; opacity: .08; top: 40%; left: 60%; animation-delay: -13s; }
  @keyframes drift { from { transform: translate(0,0) scale(1); } to { transform: translate(55px,40px) scale(1.1); } }
  .grid {
    position: fixed; inset: 0; z-index: 0; pointer-events: none;
    background-image: linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
    background-size: 40px 40px;
    mask-image: radial-gradient(ellipse 80% 80% at 50% 30%, black 30%, transparent 100%);
  }

  /* ── Navbar ── */
  .navbar {
    position: sticky; top: 0; z-index: 100;
    height: 60px;
    background: rgba(8,11,20,0.85);
    backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 28px; gap: 16px;
  }
  .brand {
    display: flex; align-items: center; gap: 10px;
    font-family: 'Syne', sans-serif;
    font-size: 17px; font-weight: 800;
    letter-spacing: -0.02em;
    text-decoration: none; color: var(--text);
  }
  .brand-icon {
    width: 32px; height: 32px;
    background: linear-gradient(135deg, #1c2ff0, #5b8eff);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px;
    box-shadow: 0 4px 12px rgba(91,142,255,0.3);
    flex-shrink: 0;
  }
  .brand em { font-style: normal; color: var(--accent); }
  .btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 14px; border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 13px; font-weight: 500;
    text-decoration: none;
    border: 1px solid var(--border);
    background: rgba(255,255,255,0.05);
    color: var(--sub); cursor: pointer;
    transition: all .18s; white-space: nowrap;
  }
  .btn:hover { background: rgba(255,255,255,0.09); color: var(--text); border-color: var(--border-hi); }

  /* ── Container ── */
  .container {
    position: relative; z-index: 1;
    max-width: 1000px;
    margin: 0 auto;
    padding: 36px 24px 80px;
  }

  /* ── Page header ── */
  .page-header { margin-bottom: 28px; }
  .page-header h2 {
    font-family: 'Syne', sans-serif;
    font-size: 26px; font-weight: 800;
    letter-spacing: -0.02em; line-height: 1.2;
  }
  .page-header p { font-size: 13px; color: var(--sub); margin-top: 5px; }

  /* ── RT notification ── */
  .rt-banner {
    display: flex; align-items: center; gap: 10px;
    padding: 11px 16px; border-radius: var(--radius);
    background: rgba(91,142,255,0.1);
    border: 1px solid rgba(91,142,255,0.25);
    color: var(--blue); font-size: 13.5px;
    margin-bottom: 24px;
    animation: slideIn .35s ease both;
  }
  @keyframes slideIn { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:translateY(0); } }

  /* ── Stats row ── */
  .stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 14px;
    margin-bottom: 32px;
  }
  .stat-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 22px 20px;
    backdrop-filter: blur(16px);
    display: flex; flex-direction: column; gap: 6px;
    position: relative; overflow: hidden;
    animation: cardIn .5s cubic-bezier(0.16,1,0.3,1) both;
    transition: border-color .2s, box-shadow .2s;
  }
  .stat-card:hover { border-color: var(--border-hi); box-shadow: 0 8px 30px rgba(0,0,0,0.3); }
  .stat-card::before {
    content: ''; position: absolute; top: 0; left: 16px; right: 16px; height: 2px;
    border-radius: 0 0 4px 4px; opacity: .6;
  }
  .stat-card:nth-child(1)::before { background: linear-gradient(90deg, transparent, var(--blue), transparent); }
  .stat-card:nth-child(2)::before { background: linear-gradient(90deg, transparent, var(--accent), transparent); }
  .stat-card:nth-child(3)::before { background: linear-gradient(90deg, transparent, var(--green), transparent); }
  @keyframes cardIn { from { opacity:0; transform:translateY(14px); } to { opacity:1; transform:translateY(0); } }
  .stat-card:nth-child(1) { animation-delay: .05s; }
  .stat-card:nth-child(2) { animation-delay: .10s; }
  .stat-card:nth-child(3) { animation-delay: .15s; }

  .stat-icon {
    width: 34px; height: 34px; border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; margin-bottom: 4px;
  }
  .stat-label { font-size: 11.5px; color: var(--sub); font-weight: 500; letter-spacing: 0.04em; text-transform: uppercase; }
  .stat-value {
    font-family: 'Syne', sans-serif;
    font-size: 30px; font-weight: 800;
    letter-spacing: -0.02em; line-height: 1;
  }
  .stat-card:nth-child(1) .stat-value { color: var(--blue); }
  .stat-card:nth-child(2) .stat-value { color: var(--accent); }
  .stat-card:nth-child(3) .stat-value { color: var(--green); }

  /* ── Section heading ── */
  .section-heading {
    display: flex; align-items: center; gap: 10px;
    margin-bottom: 14px; margin-top: 36px;
  }
  .section-heading h3 {
    font-family: 'Syne', sans-serif;
    font-size: 17px; font-weight: 700;
  }
  .section-heading .live-dot {
    width: 7px; height: 7px; border-radius: 50%;
    background: var(--green);
    animation: pulse 2s ease-in-out infinite;
  }
  .section-heading .live-label {
    font-size: 11px; color: var(--green); font-weight: 600;
    letter-spacing: 0.05em; text-transform: uppercase;
  }
  @keyframes pulse { 0%,100% { opacity:1; } 50% { opacity:.3; } }

  /* ── Empty state ── */
  .empty-state {
    text-align: center; padding: 40px 24px;
    background: var(--surface);
    border: 1px dashed var(--border-hi);
    border-radius: 16px;
    color: var(--sub); font-size: 14px;
  }

  /* ── Table ── */
  .table-wrap {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    overflow: hidden;
    backdrop-filter: blur(16px);
    animation: cardIn .5s cubic-bezier(0.16,1,0.3,1) both;
    animation-delay: .2s;
  }
  table {
    width: 100%; border-collapse: collapse;
    font-size: 13.5px;
  }
  thead tr {
    background: rgba(255,255,255,0.03);
    border-bottom: 1px solid var(--border);
  }
  th {
    padding: 12px 18px;
    font-size: 11px; font-weight: 600;
    color: var(--sub);
    text-transform: uppercase; letter-spacing: 0.07em;
    text-align: left; white-space: nowrap;
  }
  tbody tr {
    border-bottom: 1px solid var(--border);
    transition: background .15s;
  }
  tbody tr:last-child { border-bottom: none; }
  tbody tr:hover { background: rgba(255,255,255,0.03); }
  td {
    padding: 13px 18px;
    color: var(--text);
    vertical-align: middle;
  }

  /* Rank cell */
  .rank-cell {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px;
    border-radius: 8px;
    font-family: 'Syne', sans-serif;
    font-size: 13px; font-weight: 700;
  }
  .rank-1 { background: rgba(245,200,66,0.15); color: var(--accent); }
  .rank-2 { background: rgba(255,255,255,0.08); color: #c0c8e0; }
  .rank-3 { background: rgba(255,154,60,0.13); color: var(--orange); }
  .rank-n { background: rgba(255,255,255,0.04); color: var(--sub); }

  /* Score chip */
  .score-chip {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 10px; border-radius: 100px;
    font-size: 12.5px; font-weight: 600;
  }
  .score-high   { background: var(--green-dim);  color: var(--green);  border: 1px solid rgba(62,207,142,0.2); }
  .score-mid    { background: var(--accent-dim);  color: var(--accent); border: 1px solid rgba(245,200,66,0.2); }
  .score-low    { background: rgba(255,94,114,0.1); color: var(--danger); border: 1px solid rgba(255,94,114,0.2); }

  /* User cell */
  .user-cell { display: flex; align-items: center; gap: 10px; }
  .user-avatar {
    width: 28px; height: 28px; border-radius: 50%;
    background: linear-gradient(135deg, var(--blue), #a78bff);
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 700; color: #fff;
    flex-shrink: 0;
  }

  /* Time cell */
  .time-chip {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 12.5px; color: var(--sub);
  }

  /* Date cell */
  .date-text { font-size: 12.5px; color: var(--sub); }
</style>
</head>
<body>

<div class="bg-layer">
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>
  <div class="orb orb-3"></div>
</div>
<div class="grid"></div>

<!-- Navbar -->
<nav class="navbar">
  <a class="brand" href="dashboard.php">
    <div class="brand-icon">🧠</div>
    Quiz<em>Generator</em>
  </a>
  <a href="dashboard.php" class="btn">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
    Dashboard
  </a>
</nav>

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
    <div class="empty-state">No submissions yet — check back soon.</div>
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
          <td><span class="rank-cell <?= $rankClass ?>"><?= $rank <= 3 ? ['🥇','🥈','🥉'][$rank-1] : $rank ?></span></td>
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
              <?php else: ?>—<?php endif; ?>
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
