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
    --nav-h:     60px;
  }

  html, body { height: 100%; }

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
  .orb-2 { width: 500px; height: 500px; background: #f5c842; opacity: .08; bottom: -200px; right: -150px; animation-delay: -7s; }
  .orb-3 { width: 360px; height: 360px; background: #8b2fff; opacity: .09; top: 40%; left: 60%; animation-delay: -13s; }
  @keyframes drift {
    from { transform: translate(0,0) scale(1); }
    to   { transform: translate(55px, 40px) scale(1.1); }
  }
  .grid {
    position: fixed; inset: 0; z-index: 0; pointer-events: none;
    background-image:
      linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
    background-size: 40px 40px;
    mask-image: radial-gradient(ellipse 80% 80% at 50% 30%, black 30%, transparent 100%);
  }

  /* ── Navbar ── */
  .navbar {
    position: sticky; top: 0; z-index: 100;
    height: var(--nav-h);
    background: rgba(8,11,20,0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 28px;
  }

  .brand {
    display: flex; align-items: center; gap: 10px;
    font-family: 'Syne', sans-serif;
    font-size: 17px; font-weight: 800;
    letter-spacing: -0.02em;
  }
  .brand-icon {
    width: 32px; height: 32px;
    background: linear-gradient(135deg, #1c2ff0, #5b8eff);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px;
    box-shadow: 0 4px 12px rgba(91,142,255,0.3);
  }
  .brand em { font-style: normal; color: var(--accent); }

  .nav-right { display: flex; align-items: center; gap: 10px; }

  .nav-user {
    display: flex; align-items: center; gap: 8px;
    font-size: 13px; color: var(--sub);
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: 100px;
    padding: 5px 13px 5px 8px;
  }
  .nav-avatar {
    width: 24px; height: 24px;
    background: linear-gradient(135deg, var(--blue), #a78bff);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 700; color: #fff;
    flex-shrink: 0;
  }
  .nav-role {
    background: var(--accent-dim);
    color: var(--accent);
    border-radius: 100px;
    padding: 1px 8px;
    font-size: 10.5px; font-weight: 600;
    text-transform: capitalize;
    letter-spacing: 0.04em;
  }

  .btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 14px;
    border-radius: 8px;
    font-family: 'DM Sans', sans-serif;
    font-size: 13px; font-weight: 500;
    text-decoration: none;
    border: 1px solid var(--border);
    background: rgba(255,255,255,0.05);
    color: var(--sub);
    cursor: pointer;
    transition: all .18s;
    white-space: nowrap;
  }
  .btn:hover { background: rgba(255,255,255,0.09); color: var(--text); border-color: var(--border-hi); }

  .btn-primary {
    background: var(--accent); color: #12100a;
    border-color: var(--accent);
    font-weight: 600;
  }
  .btn-primary:hover { filter: brightness(1.08); box-shadow: 0 4px 16px rgba(245,200,66,0.25); color: #12100a; }

  .btn-success { background: var(--green-dim); color: var(--green); border-color: rgba(62,207,142,0.25); }
  .btn-success:hover { background: rgba(62,207,142,0.2); }

  .btn-warning { background: var(--orange-dim); color: var(--orange); border-color: rgba(255,154,60,0.25); }
  .btn-warning:hover { background: rgba(255,154,60,0.2); }

  .btn-danger { background: rgba(255,94,114,0.1); color: var(--danger); border-color: rgba(255,94,114,0.25); }
  .btn-danger:hover { background: rgba(255,94,114,0.18); }

  /* ── Page container ── */
  .container {
    position: relative; z-index: 1;
    max-width: 1100px;
    margin: 0 auto;
    padding: 36px 24px 60px;
  }

  /* ── Page header ── */
  .page-header {
    display: flex; align-items: flex-end; justify-content: space-between;
    margin-bottom: 28px;
    flex-wrap: wrap; gap: 16px;
  }
  .page-title h2 {
    font-family: 'Syne', sans-serif;
    font-size: 26px; font-weight: 800;
    letter-spacing: -0.02em; line-height: 1.1;
  }
  .page-title p { font-size: 13px; color: var(--sub); margin-top: 4px; }

  /* ── Notification banner ── */
  .rt-banner {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 16px;
    border-radius: var(--radius);
    background: rgba(91,142,255,0.1);
    border: 1px solid rgba(91,142,255,0.25);
    color: var(--blue);
    font-size: 13.5px;
    margin-bottom: 24px;
    animation: slideIn .35s ease both;
  }
  .rt-banner::before { content: '🔔'; font-size: 15px; }
  @keyframes slideIn {
    from { opacity:0; transform:translateY(-8px); }
    to   { opacity:1; transform:translateY(0); }
  }

  /* ── Empty state ── */
  .empty-state {
    text-align: center;
    padding: 64px 24px;
    background: var(--surface);
    border: 1px dashed var(--border-hi);
    border-radius: 18px;
    backdrop-filter: blur(12px);
  }
  .empty-icon { font-size: 48px; margin-bottom: 16px; }
  .empty-state h3 {
    font-family: 'Syne', sans-serif;
    font-size: 18px; font-weight: 700;
    margin-bottom: 8px;
  }
  .empty-state p { font-size: 14px; color: var(--sub); margin-bottom: 20px; }

  /* ── Quiz grid ── */
  .quiz-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 18px;
  }

  /* ── Quiz card ── */
  .quiz-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 22px;
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    display: flex; flex-direction: column; gap: 14px;
    transition: border-color .2s, box-shadow .2s, transform .2s;
    animation: cardIn .5s cubic-bezier(0.16,1,0.3,1) both;
  }
  .quiz-card:hover {
    border-color: var(--border-hi);
    box-shadow: 0 12px 40px rgba(0,0,0,0.35);
    transform: translateY(-2px);
  }
  @keyframes cardIn {
    from { opacity:0; transform:translateY(16px); }
    to   { opacity:1; transform:translateY(0); }
  }
  /* Stagger cards */
  .quiz-card:nth-child(1) { animation-delay: .05s; }
  .quiz-card:nth-child(2) { animation-delay: .10s; }
  .quiz-card:nth-child(3) { animation-delay: .15s; }
  .quiz-card:nth-child(4) { animation-delay: .20s; }
  .quiz-card:nth-child(5) { animation-delay: .25s; }
  .quiz-card:nth-child(6) { animation-delay: .30s; }

  /* Card top accent line */
  .quiz-card::before {
    content: '';
    position: absolute;
    top: 0; left: 20px; right: 20px; height: 2px;
    background: linear-gradient(90deg, transparent, var(--blue), transparent);
    border-radius: 0 0 4px 4px;
    opacity: 0;
    transition: opacity .2s;
  }
  .quiz-card { position: relative; }
  .quiz-card:hover::before { opacity: .6; }

  .quiz-card-header {
    display: flex; align-items: flex-start; justify-content: space-between; gap: 10px;
  }
  .quiz-card-header h3 {
    font-family: 'Syne', sans-serif;
    font-size: 15.5px; font-weight: 700;
    line-height: 1.3;
    letter-spacing: -0.01em;
    flex: 1;
  }

  .badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 9px;
    border-radius: 100px;
    font-size: 11px; font-weight: 600;
    letter-spacing: 0.03em;
    white-space: nowrap;
    flex-shrink: 0;
  }
  .badge::before { content: ''; width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
  .badge-success { background: var(--green-dim);  color: var(--green);  border: 1px solid rgba(62,207,142,0.2); }
  .badge-warning { background: var(--orange-dim); color: var(--orange); border: 1px solid rgba(255,154,60,0.2); }
  .badge-info    { background: var(--blue-dim);   color: var(--blue);   border: 1px solid rgba(91,142,255,0.2); }

  .quiz-desc {
    font-size: 13px; color: var(--sub);
    line-height: 1.55;
    flex: 1;
  }

  .quiz-meta {
    display: flex; align-items: center; gap: 8px;
    flex-wrap: wrap;
    padding-top: 12px;
    border-top: 1px solid var(--border);
  }
  .meta-item {
    display: flex; align-items: center; gap: 5px;
    font-size: 12px; color: var(--sub);
  }
  .meta-item svg { opacity: .6; }

  .quiz-actions {
    display: flex; align-items: center; gap: 8px;
    flex-wrap: wrap;
  }
  .quiz-actions .btn { font-size: 12.5px; padding: 6px 12px; }
  .quiz-actions .btn-primary { flex: 1; justify-content: center; }
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
  <div class="brand">
    <div class="brand-icon">🧠</div>
    Quiz<em>Generator</em>
  </div>

  <div class="nav-right">
    <div class="nav-user">
      <div class="nav-avatar"><?= strtoupper(substr(e($user['username']), 0, 1)) ?></div>
      <?= e($user['username']) ?>
      <span class="nav-role"><?= e($user['role']) ?></span>
    </div>
    <?php if (in_array($user['role'], ['teacher', 'admin'])): ?>
      <a href="quiz-create.php" class="btn btn-primary">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Quiz
      </a>
    <?php endif; ?>
    <a href="../api/logout.php" class="btn">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Log Out
    </a>
  </div>
</nav>

<!-- Main content -->
<div class="container">

  <div class="page-header">
    <div class="page-title">
      <h2><?= $user['role'] === 'student' ? 'Available Quizzes' : 'My Quizzes' ?></h2>
      <p><?= $user['role'] === 'student' ? 'Browse and take quizzes assigned to you' : 'Manage, publish, and review your quiz results' ?></p>
    </div>
  </div>

  <!-- Real-time notification -->
  <div id="rt-notification" class="rt-banner" style="display:none"></div>

  <?php if (empty($quizzes)): ?>
    <div class="empty-state">
      <div class="empty-icon">📋</div>
      <h3><?= $user['role'] === 'student' ? 'No quizzes available yet' : 'No quizzes yet' ?></h3>
      <p><?= $user['role'] === 'student' ? 'Check back later — your teacher will publish quizzes here.' : 'Get started by creating your first quiz.' ?></p>
      <?php if ($user['role'] !== 'student'): ?>
        <a href="quiz-create.php" class="btn btn-primary">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Create your first quiz
        </a>
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
            <span class="meta-item">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              <?= e($q['author']) ?>
            </span>
            <?php if ($q['taken'] > 0): ?>
              <span class="badge badge-info">✓ Completed</span>
            <?php endif; ?>
          <?php else: ?>
            <span class="meta-item">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
              <?= (int)$q['submissions'] ?> submission<?= $q['submissions'] != 1 ? 's' : '' ?>
            </span>
          <?php endif; ?>
        </div>

        <div class="quiz-actions">
          <?php if ($user['role'] === 'student'): ?>
            <a href="quiz-take.php?id=<?= (int)$q['id'] ?>" class="btn btn-primary">
              <?php if ($q['taken'] > 0): ?>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.9"/></svg>
                Retake
              <?php else: ?>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                Start Quiz
              <?php endif; ?>
            </a>
          <?php else: ?>
            <a href="quiz-create.php?id=<?= (int)$q['id'] ?>" class="btn">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              Edit
            </a>
            <a href="results.php?quiz_id=<?= (int)$q['id'] ?>" class="btn">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
              Results
            </a>
            <a href="../api/quiz.php?action=toggle_publish&id=<?= (int)$q['id'] ?>"
               class="btn <?= $q['is_published'] ? 'btn-warning' : 'btn-success' ?>">
              <?php if ($q['is_published']): ?>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                Unpublish
              <?php else: ?>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                Publish
              <?php endif; ?>
            </a>
          <?php endif; ?>
        </div>

      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<script src="https://js.pusher.com/8.0/pusher.min.js"></script>
<script src="../assets/js/realtime.js"></script>
<script>
  <?php if ($user['role'] !== 'student' && !empty($quizzes)): ?>
  const quizIds = <?= json_encode(array_column($quizzes, 'id')) ?>;
  initDashboardRealtime(quizIds, '<?= getenv("PUSHER_APP_KEY") ?>', '<?= getenv("PUSHER_APP_CLUSTER") ?>');
  <?php endif; ?>
</script>
</body>
</html>
