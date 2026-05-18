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
    --danger:    #ff5e72;
    --text:      #eceef8;
    --sub:       #8890b8;
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
    background: rgba(8,11,20,0.85);
    backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 28px; height: 60px; gap: 16px;
  }
  .brand {
    display: flex; align-items: center; gap: 10px;
    font-family: 'Syne', sans-serif;
    font-size: 15px; font-weight: 800;
    letter-spacing: -0.02em;
    min-width: 0;
  }
  .brand-icon {
    width: 30px; height: 30px; flex-shrink: 0;
    background: linear-gradient(135deg, #1c2ff0, #5b8eff);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px;
    box-shadow: 0 4px 12px rgba(91,142,255,0.3);
  }
  .brand-title {
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    color: var(--text);
  }

  /* Timer */
  .timer {
    display: flex; align-items: center; gap: 8px;
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: 100px;
    padding: 6px 16px;
    font-family: 'Syne', sans-serif;
    font-size: 15px; font-weight: 700;
    letter-spacing: 0.04em;
    flex-shrink: 0;
    transition: border-color .3s, color .3s, background .3s;
  }
  .timer svg { flex-shrink: 0; }
  .timer.urgent {
    border-color: rgba(255,94,114,0.5);
    color: var(--danger);
    background: rgba(255,94,114,0.08);
    animation: timerPulse 1s ease-in-out infinite;
  }
  @keyframes timerPulse { 0%,100% { opacity:1; } 50% { opacity:.65; } }

  /* Progress bar */
  .progress-bar-wrap {
    position: sticky; top: 60px; z-index: 99;
    height: 3px; background: var(--border);
  }
  .progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--blue), var(--green));
    border-radius: 0 2px 2px 0;
    transition: width .4s ease;
    width: 0%;
  }

  /* ── Container ── */
  .container {
    position: relative; z-index: 1;
    max-width: 700px;
    margin: 0 auto;
    padding: 36px 24px 80px;
  }

  /* Question counter strip */
  .q-strip {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 28px;
    flex-wrap: wrap; gap: 10px;
  }
  .q-strip-label {
    font-size: 13px; color: var(--sub);
  }
  .q-strip-dots {
    display: flex; gap: 6px; flex-wrap: wrap;
  }
  .q-dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: var(--border-hi);
    transition: background .3s;
  }
  .q-dot.answered { background: var(--green); }

  /* ── Question card ── */
  .question-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 28px;
    backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
    box-shadow: 0 8px 40px rgba(0,0,0,0.3), 0 0 0 1px rgba(255,255,255,0.04) inset;
    margin-bottom: 16px;
    animation: cardIn .45s cubic-bezier(0.16,1,0.3,1) both;
    position: relative; overflow: hidden;
  }
  .question-card::before {
    content: '';
    position: absolute; top: 0; left: 24px; right: 24px; height: 2px;
    background: linear-gradient(90deg, transparent, var(--blue), transparent);
    opacity: .5;
  }
  @keyframes cardIn { from { opacity:0; transform:translateY(14px); } to { opacity:1; transform:translateY(0); } }

  .question-meta {
    display: flex; align-items: center; gap: 8px;
    margin-bottom: 14px;
  }
  .q-number {
    font-family: 'Syne', sans-serif;
    font-size: 11.5px; font-weight: 700;
    color: var(--blue); background: var(--blue-dim);
    border-radius: 6px; padding: 3px 9px;
  }
  .q-of { font-size: 12px; color: var(--sub); }
  .badge-pts {
    margin-left: auto;
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 9px;
    border-radius: 100px;
    font-size: 11px; font-weight: 600;
    background: var(--accent-dim); color: var(--accent);
    border: 1px solid rgba(245,200,66,0.2);
  }

  .question-text {
    font-size: 16px; font-weight: 500;
    line-height: 1.6;
    margin-bottom: 20px;
    letter-spacing: -0.01em;
  }

  /* Short answer */
  textarea.short-answer {
    width: 100%;
    background: rgba(255,255,255,0.04);
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    padding: 12px 14px;
    font-family: 'DM Sans', sans-serif;
    font-size: 14px; color: var(--text);
    outline: none; resize: vertical; min-height: 90px;
    line-height: 1.55;
    transition: border-color .2s, background .2s, box-shadow .2s;
  }
  textarea.short-answer::placeholder { color: var(--sub); opacity: .5; }
  textarea.short-answer:focus {
    border-color: var(--blue);
    background: var(--blue-dim);
    box-shadow: 0 0 0 3px rgba(91,142,255,0.13);
  }

  /* Choice labels */
  .choices-list { display: flex; flex-direction: column; gap: 9px; }

  .choice-label {
    display: flex; align-items: center; gap: 12px;
    padding: 13px 16px;
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    background: rgba(255,255,255,0.03);
    cursor: pointer;
    font-size: 14px;
    transition: border-color .18s, background .18s, box-shadow .18s;
    user-select: none;
    position: relative;
  }
  .choice-label:hover {
    border-color: var(--border-hi);
    background: rgba(255,255,255,0.06);
  }
  .choice-label input[type="radio"] {
    appearance: none; -webkit-appearance: none;
    width: 18px; height: 18px; flex-shrink: 0;
    border: 2px solid var(--border-hi);
    border-radius: 50%;
    background: transparent;
    cursor: pointer;
    transition: border-color .18s, background .18s;
    position: relative;
  }
  .choice-label input[type="radio"]::after {
    content: '';
    position: absolute; inset: 3px;
    border-radius: 50%;
    background: var(--blue);
    opacity: 0; transform: scale(0);
    transition: opacity .18s, transform .18s;
  }
  .choice-label input[type="radio"]:checked { border-color: var(--blue); }
  .choice-label input[type="radio"]:checked::after { opacity: 1; transform: scale(1); }
  .choice-label:has(input:checked) {
    border-color: var(--blue);
    background: var(--blue-dim);
    box-shadow: 0 0 0 3px rgba(91,142,255,0.1);
    color: var(--text);
  }
  .choice-letter {
    width: 24px; height: 24px; flex-shrink: 0;
    border-radius: 6px;
    background: rgba(255,255,255,0.06);
    display: flex; align-items: center; justify-content: center;
    font-family: 'Syne', sans-serif;
    font-size: 11px; font-weight: 700;
    color: var(--sub);
    transition: background .18s, color .18s;
  }
  .choice-label:has(input:checked) .choice-letter {
    background: var(--blue);
    color: #fff;
  }

  /* Submit button */
  .submit-wrap {
    text-align: center;
    margin: 32px 0 0;
  }
  .btn-submit {
    display: inline-flex; align-items: center; gap: 9px;
    background: var(--accent); color: #12100a;
    border: none; border-radius: 13px;
    padding: 15px 40px;
    font-family: 'Syne', sans-serif;
    font-size: 16px; font-weight: 700;
    letter-spacing: 0.02em;
    cursor: pointer;
    transition: transform .15s, box-shadow .2s, filter .15s;
    box-shadow: 0 6px 28px rgba(245,200,66,0.22);
  }
  .btn-submit:hover { transform: translateY(-2px); filter: brightness(1.07); box-shadow: 0 12px 36px rgba(245,200,66,0.3); }
  .btn-submit:active { transform: translateY(0); }
  .btn-submit:disabled { opacity: .6; cursor: not-allowed; transform: none; }

  /* ── Result card ── */
  .result-card {
    background: var(--surface);
    border: 1px solid var(--border-hi);
    border-radius: 22px;
    padding: 52px 40px;
    text-align: center;
    backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
    box-shadow: 0 32px 80px rgba(0,0,0,0.45);
    animation: cardIn .6s cubic-bezier(0.16,1,0.3,1) both;
    position: relative; overflow: hidden;
  }
  .result-card::before {
    content: '';
    position: absolute; top: 0; left: 40px; right: 40px; height: 2px;
    background: linear-gradient(90deg, transparent, var(--green), var(--accent), transparent);
    opacity: .8;
  }
  .result-emoji { font-size: 56px; margin-bottom: 16px; display: block; }
  .result-card h2 {
    font-family: 'Syne', sans-serif;
    font-size: 26px; font-weight: 800;
    letter-spacing: -0.02em;
    margin-bottom: 8px;
  }
  .result-sub { font-size: 14px; color: var(--sub); margin-bottom: 28px; }
  .score-ring {
    display: inline-flex; align-items: center; justify-content: center;
    width: 130px; height: 130px;
    border-radius: 50%;
    background: conic-gradient(var(--green) 0%, transparent 0%);
    box-shadow: 0 0 0 3px var(--border), inset 0 0 0 16px var(--bg);
    margin: 0 auto 28px;
    position: relative;
    font-family: 'Syne', sans-serif;
    font-size: 26px; font-weight: 800;
    transition: background 1s ease;
  }
  .score-pts { font-size: 12px; color: var(--sub); margin-bottom: 28px; }
  .result-actions { display: flex; align-items: center; justify-content: center; gap: 12px; flex-wrap: wrap; }
  .btn-result {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 12px 24px;
    border-radius: 10px;
    font-family: 'DM Sans', sans-serif;
    font-size: 14px; font-weight: 600;
    text-decoration: none;
    border: 1px solid var(--border);
    background: rgba(255,255,255,0.05);
    color: var(--sub);
    cursor: pointer;
    transition: all .18s;
  }
  .btn-result:hover { background: rgba(255,255,255,0.09); color: var(--text); }
  .btn-result-primary {
    background: var(--accent); color: #12100a;
    border-color: var(--accent); font-weight: 700;
  }
  .btn-result-primary:hover { filter: brightness(1.08); color: #12100a; }
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
    <span class="brand-title"><?= e($quiz['title']) ?></span>
  </div>
  <?php if ($quiz['time_limit'] > 0): ?>
  <div class="timer" id="timer">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    <span id="timer-display">--:--</span>
  </div>
  <?php endif; ?>
</nav>

<!-- Progress bar -->
<div class="progress-bar-wrap">
  <div class="progress-bar-fill" id="progress-fill"></div>
</div>

<div class="container">

  <!-- Answer dots strip -->
  <div class="q-strip">
    <span class="q-strip-label"><?= count($questions) ?> question<?= count($questions) != 1 ? 's' : '' ?></span>
    <div class="q-strip-dots" id="q-dots">
      <?php foreach ($questions as $i => $q): ?>
        <div class="q-dot" data-qi="<?= $i ?>"></div>
      <?php endforeach; ?>
    </div>
  </div>

  <form id="quiz-form">
    <input type="hidden" name="_csrf_token" value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="attempt_id" value="<?= $attemptId ?>">

    <?php foreach ($questions as $i => $q): ?>
    <div class="question-card" data-qi="<?= $i ?>">

      <div class="question-meta">
        <span class="q-number">Q<?= $i + 1 ?></span>
        <span class="q-of">of <?= count($questions) ?></span>
        <span class="badge-pts">
          <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          <?= (int)$q['points'] ?> pt<?= $q['points'] != 1 ? 's' : '' ?>
        </span>
      </div>

      <p class="question-text"><?= e($q['question_text']) ?></p>

      <?php if ($q['question_type'] === 'short_answer'): ?>
        <textarea name="answer[<?= (int)$q['id'] ?>]" rows="3"
                  placeholder="Type your answer here…"
                  class="short-answer"
                  data-qi="<?= $i ?>"></textarea>

      <?php else: ?>
        <div class="choices-list">
          <?php foreach ($q['choices'] as $ci => $c): ?>
          <label class="choice-label">
            <input type="radio" name="answer[<?= (int)$q['id'] ?>]"
                   value="<?= (int)$c['id'] ?>"
                   data-qi="<?= $i ?>">
            <span class="choice-letter"><?= chr(65 + $ci) ?></span>
            <?= e($c['choice_text']) ?>
          </label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>
    <?php endforeach; ?>

    <div class="submit-wrap">
      <button type="button" id="submit-btn" class="btn-submit">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        Submit Quiz
      </button>
    </div>
  </form>

  <!-- Result section -->
  <div id="result-section" style="display:none">
    <div class="result-card">
      <span class="result-emoji" id="result-emoji">🎉</span>
      <h2 id="result-title">Quiz Submitted!</h2>
      <p class="result-sub" id="result-sub">Here's how you did</p>
      <div class="score-ring" id="score-ring">
        <span id="score-pct">–</span>
      </div>
      <p class="score-pts" id="score-pts"></p>
      <div class="result-actions">
        <a href="dashboard.php" class="btn-result btn-result-primary">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
          Back to Dashboard
        </a>
        <button onclick="window.location.reload()" class="btn-result">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.9"/></svg>
          Retake
        </button>
      </div>
    </div>
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
const TOTAL_Q     = <?= count($questions) ?>;

// ── Progress tracking ──
const dots     = document.querySelectorAll('.q-dot');
const progFill = document.getElementById('progress-fill');
let answered   = new Set();

function markAnswered(qi) {
  answered.add(qi);
  dots[qi]?.classList.add('answered');
  progFill.style.width = (answered.size / TOTAL_Q * 100) + '%';
}

document.querySelectorAll('input[type="radio"]').forEach(r => {
  r.addEventListener('change', () => markAnswered(parseInt(r.dataset.qi)));
});
document.querySelectorAll('textarea.short-answer').forEach(t => {
  t.addEventListener('input', () => {
    if (t.value.trim()) markAnswered(parseInt(t.dataset.qi));
    else { answered.delete(parseInt(t.dataset.qi)); dots[parseInt(t.dataset.qi)]?.classList.remove('answered'); progFill.style.width = (answered.size / TOTAL_Q * 100) + '%'; }
  });
});

// ── Submit guard (prevents double-submission from timer + button) ──
let isSubmitting = false;

// ── Timer ──
if (TIME_LIMIT > 0) {
  let remaining = TIME_LIMIT;
  const display = document.getElementById('timer-display');
  const timerEl = document.getElementById('timer');
  const tick = setInterval(() => {
    remaining--;
    const m = String(Math.floor(remaining / 60)).padStart(2,'0');
    const s = String(remaining % 60).padStart(2,'0');
    display.textContent = `${m}:${s}`;
    if (remaining <= 30) timerEl.classList.add('urgent');
    if (remaining <= 0) {
      clearInterval(tick); // stop timer BEFORE submit to prevent re-entry
      const btn = document.getElementById('submit-btn');
      if (btn) btn.disabled = true;
      submitQuiz();
    }
  }, 1000);
  // Init display
  const m0 = String(Math.floor(TIME_LIMIT / 60)).padStart(2,'0');
  const s0 = String(TIME_LIMIT % 60).padStart(2,'0');
  display.textContent = `${m0}:${s0}`;
}

// ── Submit ──
document.getElementById('submit-btn').addEventListener('click', () => {
  if (isSubmitting) return; // guard: already in flight
  const unanswered = TOTAL_Q - answered.size;
  const msg = unanswered > 0
    ? `You have ${unanswered} unanswered question${unanswered > 1 ? 's' : ''}. Submit anyway?`
    : 'Submit quiz? You cannot change answers after submission.';
  if (!confirm(msg)) return;
  submitQuiz();
});

async function submitQuiz() {
  if (isSubmitting) return; // guard: prevent concurrent calls
  isSubmitting = true;

  const btn = document.getElementById('submit-btn');
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin .7s linear infinite"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Submitting…`;
  }

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
    document.getElementById('quiz-form').style.opacity = '0';
    document.getElementById('quiz-form').style.transition = 'opacity .3s';
    setTimeout(() => {
      document.getElementById('quiz-form').style.display = 'none';
      const rs = document.getElementById('result-section');
      rs.style.display = 'block';

      // Populate result card
      const pct = json.score;
      document.getElementById('score-pct').textContent = pct + '%';
      document.getElementById('score-pts').textContent = `${json.total_points} total point${json.total_points != 1 ? 's' : ''}`;

      // Score ring fill
      const ring = document.getElementById('score-ring');
      const color = pct >= 75 ? '#3ecf8e' : pct >= 50 ? '#f5c842' : '#ff5e72';
      ring.style.background = `conic-gradient(${color} ${pct * 3.6}deg, rgba(255,255,255,0.05) 0deg)`;
      ring.style.boxShadow  = `0 0 0 3px rgba(255,255,255,0.06), inset 0 0 0 16px #080b14, 0 0 40px ${color}33`;

      // Emoji & title by score
      const emoji = pct >= 90 ? '🏆' : pct >= 75 ? '🎉' : pct >= 50 ? '👍' : '📚';
      const title = pct >= 90 ? 'Excellent work!' : pct >= 75 ? 'Great job!' : pct >= 50 ? 'Not bad!' : 'Keep practicing!';
      document.getElementById('result-emoji').textContent = emoji;
      document.getElementById('result-title').textContent = title;
      document.getElementById('result-sub').textContent   = `You scored ${pct}% on this quiz`;

      initStudentRealtime(QUIZ_ID, PUSHER_KEY, PUSHER_CLUS);
    }, 320);
  } else {
    alert(json.error || 'Submission failed.');
    isSubmitting = false; // allow retry on genuine errors
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Submit Quiz`;
    }
  }
}

// Spinner keyframe
const s = document.createElement('style');
s.textContent = '@keyframes spin { to { transform: rotate(360deg); } }';
document.head.appendChild(s);
</script>
</body>
</html>
