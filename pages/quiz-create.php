<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/layout.php';

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
    $timeLimitMin = postInt('time_limit') ?? 0;
    $timeLimit    = $timeLimitMin * 60;  // store as seconds

    if (!$title) {
        $error = 'Quiz title is required.';
    } else {
        if ($quizId && $quiz) {
            // UPDATE
            db_query(
                'UPDATE quizzes SET title=?, description=?, time_limit=? WHERE id=? AND created_by=?',
                [$title, $desc, $timeLimit, $quizId, $user['id']]
            );
            setFlash('success', 'Quiz updated.');
            header('Location: quiz-create.php?id=' . $quizId);
            exit;
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
<?php
$extraCss = '.card-title-icon{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px}.icon-blue{background:var(--blue-dim)}.icon-green{background:var(--green-dim)}.choice-row input[type=checkbox],.choice-row input[type=radio]{width:16px;height:16px;accent-color:var(--blue)}.choice-row .choice-text{flex:1;border:none;background:transparent;padding:0;box-shadow:none!important}';
renderHeader($quiz ? 'Edit Quiz' : 'New Quiz', 'quiz-create', true, $extraCss);
?>
<div class="container">

  <!-- Page header -->
  <div class="page-header">
    <h2><?= $quiz ? 'Edit Quiz' : 'Create New Quiz' ?></h2>
    <p><?= $quiz ? 'Update your quiz details and manage questions below.' : 'Fill in the details to create your quiz, then add questions.' ?></p>
  </div>

  <?php renderFlash(); ?>
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
          Time Limit (minutes, 0 = unlimited)
        </label>
        <input type="number" id="time_limit" name="time_limit" min="0"
               placeholder="0"
               value="<?= (int)round(($quiz['time_limit'] ?? 0) / 60) ?>">
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
          <?php
            $choiceData = [];
            if ($q['choices_raw']) {
                foreach (explode(';;;', $q['choices_raw']) as $c) {
                    [$cId, $cText, $cCorrect] = explode('|||', $c);
                    $choiceData[] = ['text' => $cText, 'correct' => (int)$cCorrect];
                }
            }
          ?>
          <button type="button" class="btn btn-sm edit-question"
                  data-id="<?= (int)$q['id'] ?>"
                  data-text="<?= e($q['question_text']) ?>"
                  data-type="<?= e($q['question_type']) ?>"
                  data-points="<?= (int)$q['points'] ?>"
                  data-choices-b64="<?= base64_encode(json_encode($choiceData)) ?>">Edit</button>
          <button type="button" class="btn btn-danger btn-sm del-question" data-id="<?= (int)$q['id'] ?>">Delete</button>
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

    <div id="short-answer-builder" style="display:none; margin-top:18px;">
      <label style="margin-bottom:10px; display:flex; gap:8px; align-items:center; font-size:13px; color:var(--sub);">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 12h18"/><path d="M3 6h18"/><path d="M3 18h18"/></svg>
        Correct Answer for Short Answer
      </label>
      <input type="text" id="q-short-answer" placeholder="Type the expected answer" style="width:100%; padding:11px 14px; border-radius:var(--radius); border:1.5px solid var(--border); background:rgba(255,255,255,0.04); color:var(--text); outline:none;" />
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

<!-- Edit question modal -->
<div id="edit-modal" class="modal-overlay" aria-hidden="true">
  <div class="modal" role="dialog">
    <div class="modal-header">
      <h3>Edit Question</h3>
      <button type="button" class="modal-close" id="edit-modal-close">&times;</button>
    </div>
    <div class="form-group">
      <label for="edit-q-text">Question Text</label>
      <textarea id="edit-q-text" rows="2" maxlength="1000"></textarea>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label for="edit-q-type">Type</label>
        <select id="edit-q-type">
          <option value="multiple_choice">Multiple Choice</option>
          <option value="true_false">True / False</option>
          <option value="short_answer">Short Answer</option>
        </select>
      </div>
      <div class="form-group">
        <label for="edit-q-points">Points</label>
        <input type="number" id="edit-q-points" min="1" max="100" value="1">
      </div>
    </div>
    <div id="edit-choices-builder">
      <label>Choices</label>
      <div id="edit-choices-container"></div>
    </div>
    <div id="edit-short-answer-builder" style="display:none; margin-top:16px;">
      <label style="display:block; margin-bottom:8px; color:var(--sub); font-size:13px;">Correct Answer</label>
      <input type="text" id="edit-q-short-answer" placeholder="Type the expected answer" style="width:100%; padding:11px 14px; border-radius:var(--radius); border:1.5px solid var(--border); background:rgba(255,255,255,0.04); color:var(--text); outline:none;" />
    </div>
    <div id="edit-feedback" style="display:none;margin-top:10px;font-size:13px"></div>
    <div class="modal-footer">
      <button type="button" class="btn" id="edit-cancel">Cancel</button>
      <button type="button" class="btn btn-primary" id="edit-save">Save Changes</button>
    </div>
  </div>
</div>

<script>
const QUIZ_ID = <?= $quizId ?? 'null' ?>;
const CSRF    = <?= json_encode(csrfToken()) ?>;
const API_Q   = <?= json_encode(assetUrl('api/question.php')) ?>;
let editQuestionId = null;

// Type change handler
document.getElementById('q-type')?.addEventListener('change', function () {
  const cb  = document.getElementById('choices-builder');
  const cc  = document.getElementById('choices-container');
  const lbl = cb.querySelector('label');
  const sa  = document.getElementById('short-answer-builder');
  switch (this.value) {
    case 'true_false':
      cb.style.display = 'block';
      sa.style.display = 'none';
      lbl.style.display = 'flex';
      cc.innerHTML = ['True','False'].map((v,i) => `
        <div class="choice-row">
          <input type="radio" name="tf" value="${i===0?1:0}" class="choice-correct">
          <span class="choice-text-static">${v}</span>
        </div>`).join('');
      break;
    case 'short_answer':
      cb.style.display = 'none';
      sa.style.display = 'block';
      break;
    default:
      cb.style.display = 'block';
      sa.style.display = 'none';
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
  if (type === 'short_answer') {
    const answer = document.getElementById('q-short-answer').value.trim();
    if (!answer) { showFeedback('Please enter the correct answer for short answer questions.', false); return; }
    choices = [{ text: answer, correct: 1 }];
  } else if (type === 'true_false') {
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

  const btn = document.getElementById('add-question-btn');
  btn.disabled = true;
  btn.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin .7s linear infinite"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Adding…`;

  const res  = await fetch(API_Q, {
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
    const res = await fetch(API_Q, {
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

function buildEditChoices(type, choices) {
  const cb = document.getElementById('edit-choices-builder');
  const cc = document.getElementById('edit-choices-container');
  const sa = document.getElementById('edit-short-answer-builder');
  const saInput = document.getElementById('edit-q-short-answer');
  if (type === 'short_answer') {
    cb.style.display = 'none';
    sa.style.display = 'block';
    saInput.value = choices[0]?.text || '';
    return;
  }
  sa.style.display = 'none';
  cb.style.display = 'block';
  if (type === 'true_false') {
    const trueCorrect = choices.find(c => c.text === 'True')?.correct;
    cc.innerHTML = ['True','False'].map((v,i) => `
      <div class="choice-row">
        <input type="radio" name="edit_tf" value="${i===0?1:0}" class="choice-correct" ${(i===0 && trueCorrect) || (i===1 && !trueCorrect && choices.length) ? 'checked' : ''}>
        <span class="choice-text-static">${v}</span>
      </div>`).join('');
  } else {
    const labels = ['A','B','C','D'];
    const items = choices.length ? choices : labels.map(l => ({ text: '', correct: 0 }));
    cc.innerHTML = (items.length >= 4 ? items : [...items, ...Array(4-items.length).fill({text:'',correct:0})]).slice(0,4).map((c,i) => `
      <div class="choice-row">
        <input type="checkbox" class="choice-correct" ${c.correct ? 'checked' : ''}>
        <input type="text" class="choice-text" placeholder="Choice ${labels[i]}" value="${(c.text||'').replace(/"/g,'&quot;')}">
      </div>`).join('');
  }
}

document.getElementById('edit-q-type')?.addEventListener('change', function() {
  buildEditChoices(this.value, []);
});

document.querySelectorAll('.edit-question').forEach(btn => {
  btn.addEventListener('click', () => {
    editQuestionId = parseInt(btn.dataset.id);
    document.getElementById('edit-q-text').value = btn.dataset.text;
    document.getElementById('edit-q-type').value = btn.dataset.type;
    document.getElementById('edit-q-points').value = btn.dataset.points;
    let choices = [];
    try { choices = JSON.parse(atob(btn.dataset.choicesB64 || 'W10=')); } catch(e) {}
    buildEditChoices(btn.dataset.type, choices);
    document.getElementById('edit-modal').classList.add('open');
  });
});

function closeEditModal() {
  document.getElementById('edit-modal').classList.remove('open');
  editQuestionId = null;
}
document.getElementById('edit-modal-close')?.addEventListener('click', closeEditModal);
document.getElementById('edit-cancel')?.addEventListener('click', closeEditModal);
document.getElementById('edit-modal')?.addEventListener('click', e => {
  if (e.target.id === 'edit-modal') closeEditModal();
});

document.getElementById('edit-save')?.addEventListener('click', async () => {
  const text = document.getElementById('edit-q-text').value.trim();
  const type = document.getElementById('edit-q-type').value;
  const pts  = parseInt(document.getElementById('edit-q-points').value) || 1;
  const fb   = document.getElementById('edit-feedback');
  if (!text) { fb.textContent = 'Question text is required.'; fb.style.display = 'block'; fb.style.color = '#ff8a96'; return; }
  let choices = [];
  if (type === 'short_answer') {
    const answer = document.getElementById('edit-q-short-answer').value.trim();
    if (!answer) { fb.textContent = 'Please enter the correct answer for short answer questions.'; fb.style.display = 'block'; fb.style.color = '#ff8a96'; return; }
    choices = [{ text: answer, correct: 1 }];
  } else if (type === 'true_false') {
    const sel = document.querySelector('input[name="edit_tf"]:checked');
    choices = [
      { text: 'True', correct: sel?.value === '1' ? 1 : 0 },
      { text: 'False', correct: sel?.value === '0' ? 1 : 0 },
    ];
  } else {
    document.querySelectorAll('#edit-choices-container .choice-row').forEach(row => {
      const t = row.querySelector('.choice-text')?.value.trim();
      const c = row.querySelector('.choice-correct')?.checked ? 1 : 0;
      if (t) choices.push({ text: t, correct: c });
    });
    if (!choices.some(c => c.correct)) {
      fb.textContent = 'Mark at least one correct choice.'; fb.style.display = 'block'; fb.style.color = '#ff8a96'; return;
      }
  }
  const btn = document.getElementById('edit-save');
  btn.disabled = true;
  const res = await fetch(API_Q, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
    body: JSON.stringify({ action: 'update', id: editQuestionId, text, type, points: pts, choices })
  });
  const data = await res.json();
  btn.disabled = false;
  if (data.success) location.reload();
  else { fb.textContent = data.error || 'Update failed.'; fb.style.display = 'block'; fb.style.color = '#ff8a96'; }
});
</script>
<?php renderFooter(); ?>
