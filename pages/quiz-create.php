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
    --danger-dim:rgba(255,94,114,0.10);
    --radius:    12px;
    --nav-h:     60px;
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
  @keyframes drift {
    from { transform: translate(0,0) scale(1); }
    to   { transform: translate(55px,40px) scale(1.1); }
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
    text-decoration: none; color: var(--text);
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
    line-height: 1;
  }
  .btn:hover { background: rgba(255,255,255,0.09); color: var(--text); border-color: var(--border-hi); }
  .btn-primary { background: var(--accent); color: #12100a; border-color: var(--accent); font-weight: 600; }
  .btn-primary:hover { filter: brightness(1.08); box-shadow: 0 4px 16px rgba(245,200,66,0.25); color: #12100a; }
  .btn-success { background: var(--green-dim); color: var(--green); border-color: rgba(62,207,142,0.25); }
  .btn-success:hover { background: rgba(62,207,142,0.2); }
  .btn-danger  { background: var(--danger-dim); color: var(--danger); border-color: rgba(255,94,114,0.25); }
  .btn-danger:hover  { background: rgba(255,94,114,0.18); }

  /* ── Container ── */
  .container {
    position: relative; z-index: 1;
    max-width: 780px;
    margin: 0 auto;
    padding: 36px 24px 80px;
  }

  /* ── Page header ── */
  .page-header { margin-bottom: 28px; }
  .page-header h2 {
    font-family: 'Syne', sans-serif;
    font-size: 26px; font-weight: 800;
    letter-spacing: -0.02em;
  }
  .page-header p { font-size: 13px; color: var(--sub); margin-top: 4px; }

  /* ── Alerts ── */
  .alert {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 12px 15px;
    border-radius: var(--radius);
    font-size: 13.5px; line-height: 1.45;
    margin-bottom: 18px;
    animation: slideIn .3s ease both;
  }
  .alert-danger  { background: rgba(255,94,114,0.09);  border: 1px solid rgba(255,94,114,0.25);  color: #ff8a96; }
  .alert-success { background: rgba(62,207,142,0.09);  border: 1px solid rgba(62,207,142,0.25);  color: var(--green); }
  @keyframes slideIn { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:translateY(0); } }

  /* ── Card ── */
  .card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 28px;
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    box-shadow: 0 8px 40px rgba(0,0,0,0.3), 0 0 0 1px rgba(255,255,255,0.04) inset;
    margin-bottom: 20px;
    animation: cardIn .5s cubic-bezier(0.16,1,0.3,1) both;
  }
  @keyframes cardIn { from { opacity:0; transform:translateY(14px); } to { opacity:1; transform:translateY(0); } }

  .card-title {
    font-family: 'Syne', sans-serif;
    font-size: 15px; font-weight: 700;
    margin-bottom: 20px;
    display: flex; align-items: center; gap: 8px;
    padding-bottom: 14px;
    border-bottom: 1px solid var(--border);
  }
  .card-title-icon {
    width: 28px; height: 28px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px;
    flex-shrink: 0;
  }
  .icon-blue   { background: var(--blue-dim); }
  .icon-green  { background: var(--green-dim); }
  .icon-accent { background: var(--accent-dim); }

  /* ── Section heading ── */
  .section-heading {
    display: flex; align-items: center; justify-content: space-between;
    margin: 32px 0 14px;
  }
  .section-heading h3 {
    font-family: 'Syne', sans-serif;
    font-size: 17px; font-weight: 700;
    display: flex; align-items: center; gap: 8px;
  }
  .q-count {
    background: var(--blue-dim);
    color: var(--blue);
    border-radius: 100px;
    padding: 2px 10px;
    font-size: 11.5px; font-weight: 600;
  }

  /* ── Form elements ── */
  .form-group { margin-bottom: 16px; }
  .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

  label {
    display: flex; align-items: center; gap: 6px;
    font-size: 12px; font-weight: 500;
    color: var(--sub);
    letter-spacing: 0.06em; text-transform: uppercase;
    margin-bottom: 7px;
  }

  input[type="text"],
  input[type="number"],
  textarea,
  select {
    width: 100%;
    background: rgba(255,255,255,0.04);
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    padding: 11px 14px;
    font-family: 'DM Sans', sans-serif;
    font-size: 14px; color: var(--text);
    outline: none;
    transition: border-color .2s, background .2s, box-shadow .2s;
    appearance: none; -webkit-appearance: none;
  }
  textarea { resize: vertical; min-height: 80px; line-height: 1.55; }
  input::placeholder, textarea::placeholder { color: var(--sub); opacity: .5; }
  input:focus, textarea:focus, select:focus {
    border-color: var(--blue);
    background: var(--blue-dim);
    box-shadow: 0 0 0 3px rgba(91,142,255,0.13);
  }

  /* Select chevron */
  .select-wrap { position: relative; }
  .select-wrap::after {
    content: '';
    position: absolute; right: 13px; top: 50%; transform: translateY(-50%);
    width: 0; height: 0;
    border-left: 4px solid transparent;
    border-right: 4px solid transparent;
    border-top: 5px solid var(--sub);
    pointer-events: none;
  }
  select { cursor: pointer; padding-right: 34px; }
  select option { background: #13172a; color: var(--text); }

  /* ── Question cards ── */
  .question-card {
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 18px 20px;
    margin-bottom: 12px;
    transition: border-color .2s, box-shadow .2s;
    animation: cardIn .4s cubic-bezier(0.16,1,0.3,1) both;
  }
  .question-card:hover { border-color: var(--border-hi); }

  .question-header {
    display: flex; align-items: flex-start; gap: 10px;
    flex-wrap: wrap;
  }
  .q-number {
    font-family: 'Syne', sans-serif;
    font-size: 12px; font-weight: 700;
    color: var(--blue);
    background: var(--blue-dim);
    border-radius: 6px;
    padding: 2px 8px;
    flex-shrink: 0;
    margin-top: 1px;
  }
  .q-text {
    flex: 1;
    font-size: 14px; line-height: 1.5;
    font-weight: 500;
    min-width: 0;
  }
  .q-actions { display: flex; align-items: center; gap: 6px; margin-left: auto; flex-shrink: 0; }

  .badge {
    display: inline-flex; align-items: center;
    padding: 2px 9px;
    border-radius: 100px;
    font-size: 11px; font-weight: 600;
    letter-spacing: 0.02em;
    white-space: nowrap;
  }
  .badge-info    { background: var(--blue-dim);   color: var(--blue);   border: 1px solid rgba(91,142,255,0.2); }
  .badge-success { background: var(--green-dim);  color: var(--green);  border: 1px solid rgba(62,207,142,0.2); }
  .badge-warning { background: var(--orange-dim); color: var(--orange); border: 1px solid rgba(255,154,60,0.2); }
  .badge-pts {
    background: var(--accent-dim); color: var(--accent);
    border: 1px solid rgba(245,200,66,0.2);
    font-size: 11px;
  }

  /* Choices list */
  .choices-list {
    list-style: none;
    margin-top: 12px;
    display: flex; flex-direction: column; gap: 6px;
    padding-left: 28px;
  }
  .choices-list li {
    font-size: 13px; color: var(--sub);
    display: flex; align-items: center; gap: 7px;
  }
  .choices-list li.correct { color: var(--green); font-weight: 500; }
  .choice-dot {
    width: 7px; height: 7px; border-radius: 50%;
    background: var(--border-hi); flex-shrink: 0;
  }
  .choices-list li.correct .choice-dot { background: var(--green); }

  /* ── Choice builder ── */
  #choices-builder { margin-top: 4px; }
  #choices-builder > label { margin-bottom: 10px; }

  .choice-row {
    display: flex; align-items: center; gap: 10px;
    margin-bottom: 8px;
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 9px 12px;
    transition: border-color .2s;
  }
  .choice-row:focus-within { border-color: var(--blue); }

  .choice-row input[type="checkbox"],
  .choice-row input[type="radio"] {
    width: 16px; height: 16px;
    accent-color: var(--green);
    flex-shrink: 0;
    cursor: pointer;
    padding: 0; border: none; background: none;
    box-shadow: none;
  }
  .choice-row input[type="checkbox"]:focus,
  .choice-row input[type="radio"]:focus { box-shadow: none; border: none; background: none; }

  .choice-row .choice-text {
    flex: 1; border: none; background: transparent;
    padding: 0; font-size: 13.5px;
    box-shadow: none !important;
  }
  .choice-row .choice-text:focus { border: none; background: transparent; box-shadow: none; }

  .choice-text-static {
    font-size: 13.5px; color: var(--text);
  }

  /* ── Feedback ── */
  #q-feedback { margin-top: 12px; }
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
    <h2><?= $quiz ? 'Edit Quiz' : 'Create New Quiz' ?></h2>
    <p><?= $quiz ? 'Update your quiz details and manage questions below.' : 'Fill in the details to create your quiz, then add questions.' ?></p>
  </div>

  <!-- Alerts -->
  <?php if ($error): ?>
    <div class="alert alert-danger">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?= e($error) ?>
    </div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><polyline points="20 6 9 17 4 12"/></svg>
      <?= e($success) ?>
    </div>
  <?php endif; ?>
  <?php if (isset($_GET['created'])): ?>
    <div class="alert alert-success">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><polyline points="20 6 9 17 4 12"/></svg>
      Quiz created! Now add questions below.
    </div>
  <?php endif; ?>

  <!-- Quiz details card -->
  <div class="card">
    <div class="card-title">
      <div class="card-title-icon icon-blue">📋</div>
      Quiz Details
    </div>
    <form method="POST" action="">
      <?= csrfField() ?>
      <div class="form-group">
        <label for="title">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="14" y2="12"/><line x1="4" y1="18" x2="18" y2="18"/></svg>
          Quiz Title <span style="color:var(--danger)">*</span>
        </label>
        <input type="text" id="title" name="title" required maxlength="200"
               placeholder="e.g. Introduction to Algebra"
               value="<?= e($quiz['title'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="description">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          Description
        </label>
        <textarea id="description" name="description"
                  placeholder="Optional — briefly describe what this quiz covers."><?= e($quiz['description'] ?? '') ?></textarea>
      </div>
      <div class="form-group" style="max-width:240px">
        <label for="time_limit">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          Time Limit (seconds, 0 = unlimited)
        </label>
        <input type="number" id="time_limit" name="time_limit" min="0"
               placeholder="0"
               value="<?= (int)($quiz['time_limit'] ?? 0) ?>">
      </div>
      <button type="submit" class="btn btn-primary">
        <?php if ($quiz): ?>
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          Save Changes
        <?php else: ?>
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Create Quiz
        <?php endif; ?>
      </button>
    </form>
  </div>

  <?php if ($quizId && $quiz): ?>

  <!-- Questions section -->
  <div class="section-heading">
    <h3>
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      Questions
      <span class="q-count"><?= count($questions) ?></span>
    </h3>
  </div>

  <div id="questions-list">
    <?php foreach ($questions as $i => $q): ?>
    <div class="question-card" data-qid="<?= (int)$q['id'] ?>">
      <div class="question-header">
        <span class="q-number">Q<?= $i + 1 ?></span>
        <span class="q-text"><?= e($q['question_text']) ?></span>
        <div class="q-actions">
          <span class="badge badge-info"><?= e($q['question_type']) ?></span>
          <span class="badge badge-pts"><?= (int)$q['points'] ?> pt<?= $q['points'] != 1 ? 's' : '' ?></span>
          <button class="btn btn-danger del-question" data-id="<?= (int)$q['id'] ?>" style="padding:5px 10px;font-size:12px">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
            Delete
          </button>
        </div>
      </div>
      <?php if ($q['choices_raw']): ?>
      <ul class="choices-list">
        <?php foreach (explode(';;;', $q['choices_raw']) as $c): ?>
          <?php [$cId, $cText, $cCorrect] = explode('|||', $c); ?>
          <li class="<?= $cCorrect ? 'correct' : '' ?>">
            <span class="choice-dot"></span>
            <?= e($cText) ?>
            <?php if ($cCorrect): ?><span class="badge badge-success" style="font-size:10px;padding:1px 7px">correct</span><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Add question card -->
  <div class="card" style="margin-top:8px">
    <div class="card-title">
      <div class="card-title-icon icon-green">➕</div>
      Add Question
    </div>

    <div class="form-group">
      <label for="q-text">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="14" y2="18"/></svg>
        Question Text <span style="color:var(--danger)">*</span>
      </label>
      <textarea id="q-text" rows="2" maxlength="1000" placeholder="Type your question here…"></textarea>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label for="q-type">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/><path d="M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
          Type
        </label>
        <div class="select-wrap">
          <select id="q-type">
            <option value="multiple_choice">Multiple Choice</option>
            <option value="true_false">True / False</option>
            <option value="short_answer">Short Answer</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label for="q-points">
          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          Points
        </label>
        <input type="number" id="q-points" value="1" min="1" max="100">
      </div>
    </div>

    <div id="choices-builder">
      <label style="margin-bottom:10px">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        Choices — check the correct answer(s)
      </label>
      <div id="choices-container">
        <?php foreach (['A','B','C','D'] as $l): ?>
        <div class="choice-row">
          <input type="checkbox" class="choice-correct">
          <input type="text" class="choice-text" placeholder="Choice <?= $l ?>">
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div style="margin-top:18px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <button id="add-question-btn" class="btn btn-success" style="padding:10px 20px;font-size:14px;font-weight:600">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Question
      </button>
      <div id="q-feedback" style="display:none"></div>
    </div>
  </div>

  <?php endif; ?>
</div>

<script>
const QUIZ_ID = <?= $quizId ?? 'null' ?>;
const CSRF    = <?= json_encode(csrfToken()) ?>;

// Type change handler
document.getElementById('q-type')?.addEventListener('change', function () {
  const cb  = document.getElementById('choices-builder');
  const cc  = document.getElementById('choices-container');
  const lbl = cb.querySelector('label');
  switch (this.value) {
    case 'true_false':
      cb.style.display = 'block';
      lbl.style.display = 'flex';
      cc.innerHTML = ['True','False'].map((v,i) => `
        <div class="choice-row">
          <input type="radio" name="tf" value="${i===0?1:0}" class="choice-correct">
          <span class="choice-text-static">${v}</span>
        </div>`).join('');
      break;
    case 'short_answer':
      cb.style.display = 'none';
      break;
    default:
      cb.style.display = 'block';
      lbl.style.display = 'flex';
      cc.innerHTML = ['A','B','C','D'].map(l => `
        <div class="choice-row">
          <input type="checkbox" class="choice-correct">
          <input type="text" class="choice-text" placeholder="Choice ${l}">
        </div>`).join('');
  }
});

// Add question
document.getElementById('add-question-btn')?.addEventListener('click', async () => {
  const text = document.getElementById('q-text').value.trim();
  const type = document.getElementById('q-type').value;
  const pts  = parseInt(document.getElementById('q-points').value) || 1;

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

  const btn = document.getElementById('add-question-btn');
  btn.disabled = true;
  btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin .7s linear infinite"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Adding…`;

  const res  = await fetch('../api/question.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
    body: JSON.stringify({ action: 'add', quiz_id: QUIZ_ID, text, type, points: pts, choices })
  });
  const data = await res.json();

  btn.disabled = false;
  btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add Question`;

  if (data.success) {
    showFeedback('Question added!', true);
    setTimeout(() => location.reload(), 700);
  } else {
    showFeedback(data.error || 'Error adding question.', false);
  }
});

// Delete question
document.querySelectorAll('.del-question').forEach(btn => {
  btn.addEventListener('click', async () => {
    if (!confirm('Delete this question?')) return;
    const id  = btn.dataset.id;
    const res = await fetch('../api/question.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify({ action: 'delete', id })
    });
    const data = await res.json();
    if (data.success) {
      const card = btn.closest('.question-card');
      card.style.transition = 'opacity .25s, transform .25s';
      card.style.opacity = '0';
      card.style.transform = 'translateX(12px)';
      setTimeout(() => card.remove(), 260);
    } else {
      alert(data.error || 'Error deleting question.');
    }
  });
});

function showFeedback(msg, ok) {
  const el = document.getElementById('q-feedback');
  el.textContent = msg;
  el.style.cssText = `display:flex;align-items:center;gap:8px;padding:9px 13px;border-radius:10px;font-size:13px;animation:slideIn .3s ease both;${
    ok
      ? 'background:rgba(62,207,142,0.1);border:1px solid rgba(62,207,142,0.25);color:#3ecf8e'
      : 'background:rgba(255,94,114,0.1);border:1px solid rgba(255,94,114,0.25);color:#ff8a96'
  }`;
}

// Spinner keyframe
const style = document.createElement('style');
style.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
document.head.appendChild(style);
</script>
</body>
</html>
