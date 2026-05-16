<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/sanitize.php';

startSecureSession();

if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username  = post('username');
    $email     = sanitizeEmail($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['password_confirm'] ?? '';
    $role      = in_array($_POST['role'] ?? '', ['student', 'teacher']) ? $_POST['role'] : 'student';

    if (!$username || !$email || !$password) {
        $error = 'All fields are required.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $ok = registerUser($username, $email, $password, $role);
        if ($ok) {
            $success = 'Account created! You can now log in.';
        } else {
            $error = 'Username or email already in use.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register — Quiz Generator</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,400&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:        #080b14;
    --surface:   rgba(18, 22, 42, 0.92);
    --border:    rgba(255,255,255,0.08);
    --border-hi: rgba(255,255,255,0.14);
    --accent:    #f5c842;
    --blue:      #5b8eff;
    --blue-dim:  rgba(91,142,255,0.12);
    --green:     #3ecf8e;
    --green-dim: rgba(62,207,142,0.10);
    --text:      #eceef8;
    --sub:       #8890b8;
    --danger:    #ff5e72;
    --success:   #3ecf8e;
    --radius:    13px;
  }

  html, body { height: 100%; }

  body {
    font-family: 'DM Sans', sans-serif;
    background: var(--bg);
    color: var(--text);
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    overflow-x: hidden;
    overflow-y: auto;
    position: relative;
    padding: 24px 16px;
  }

  /* ── Background ── */
  .bg-layer { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
  .orb {
    position: absolute; border-radius: 50%; filter: blur(100px);
    animation: drift 18s ease-in-out infinite alternate;
  }
  .orb-1 { width: 600px; height: 600px; background: #1a3aff; opacity: .13; top: -200px; left: -200px; }
  .orb-2 { width: 480px; height: 480px; background: #f5c842; opacity: .10; bottom: -180px; right: -180px; animation-delay: -6s; }
  .orb-3 { width: 320px; height: 320px; background: #2fff8b; opacity: .09; top: 35%; left: 55%; animation-delay: -11s; }
  @keyframes drift {
    from { transform: translate(0,0) scale(1); }
    to   { transform: translate(50px, 35px) scale(1.1); }
  }

  .grid {
    position: fixed; inset: 0; z-index: 0; pointer-events: none;
    background-image:
      linear-gradient(rgba(255,255,255,0.025) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255,255,255,0.025) 1px, transparent 1px);
    background-size: 40px 40px;
    mask-image: radial-gradient(ellipse 70% 70% at 50% 50%, black 40%, transparent 100%);
  }

  .floaters { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
  .floater {
    position: absolute;
    font-family: 'Syne', sans-serif; font-weight: 800;
    color: rgba(255,255,255,0.035);
    animation: floatUp linear infinite;
    user-select: none;
  }
  @keyframes floatUp {
    from { transform: translateY(105vh) rotate(-12deg); opacity: 0; }
    8%   { opacity: 1; }
    92%  { opacity: 1; }
    to   { transform: translateY(-8vh) rotate(12deg); opacity: 0; }
  }

  /* ── Card ── */
  .auth-card {
    position: relative; z-index: 1;
    background: var(--surface);
    border: 1px solid var(--border-hi);
    border-radius: 22px;
    padding: 36px 40px 32px;
    width: 100%;
    max-width: 460px;
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    box-shadow:
      0 0 0 1px rgba(255,255,255,0.05) inset,
      0 40px 80px rgba(0,0,0,0.6),
      0 0 60px rgba(62,207,142,0.04);
    animation: cardIn 0.65s cubic-bezier(0.16,1,0.3,1) both;
  }
  @keyframes cardIn {
    from { opacity: 0; transform: translateY(32px) scale(0.96); }
    to   { opacity: 1; transform: translateY(0)    scale(1);    }
  }

  /* Top accent line — green tint for register */
  .card-topline {
    position: absolute; top: 0; left: 32px; right: 32px; height: 2px;
    background: linear-gradient(90deg, transparent, var(--green), var(--blue), transparent);
    border-radius: 0 0 4px 4px;
    opacity: .7;
  }

  /* Logo */
  .logo-area {
    display: flex; align-items: center; gap: 14px;
    margin-bottom: 24px;
  }
  .logo-icon {
    width: 44px; height: 44px;
    background: linear-gradient(135deg, #0e9e60 0%, #3ecf8e 100%);
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; flex-shrink: 0;
    box-shadow: 0 6px 20px rgba(62,207,142,0.3);
  }
  .logo-text h1 {
    font-family: 'Syne', sans-serif;
    font-size: 21px; font-weight: 800;
    letter-spacing: -0.02em; line-height: 1;
    white-space: nowrap;
  }
  .logo-text h1 em { font-style: normal; color: var(--accent); }
  .logo-text .tagline { font-size: 11.5px; color: var(--sub); margin-top: 3px; }

  /* Section header */
  .section-header { margin-bottom: 20px; }
  .section-header h2 {
    font-family: 'Syne', sans-serif;
    font-size: 19px; font-weight: 700; letter-spacing: -0.01em;
  }
  .section-header p { font-size: 13px; color: var(--sub); margin-top: 3px; }

  /* Divider */
  .divider {
    display: flex; align-items: center; gap: 10px;
    margin-bottom: 18px;
  }
  .divider::before, .divider::after {
    content: ''; flex: 1; height: 1px; background: var(--border);
  }
  .divider span { font-size: 11px; color: var(--sub); letter-spacing: 0.08em; text-transform: uppercase; }

  /* Alerts */
  .alert {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 11px 14px;
    border-radius: var(--radius);
    font-size: 13px; line-height: 1.4;
    margin-bottom: 16px;
    animation: slideIn .3s ease both;
  }
  .alert-danger  { background: rgba(255,94,114,0.09);  border: 1px solid rgba(255,94,114,0.25);  color: #ff8a96; }
  .alert-success { background: rgba(62,207,142,0.09); border: 1px solid rgba(62,207,142,0.25); color: var(--green); }
  .alert-icon { flex-shrink: 0; font-size: 14px; margin-top: 1px; }
  @keyframes slideIn {
    from { opacity:0; transform:translateY(-6px); }
    to   { opacity:1; transform:translateY(0); }
  }

  /* Two-col row */
  .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

  /* Form group */
  .form-group { margin-bottom: 14px; }

  label {
    display: flex; align-items: center; gap: 6px;
    font-size: 11.5px; font-weight: 500;
    color: var(--sub);
    letter-spacing: 0.06em; text-transform: uppercase;
    margin-bottom: 6px;
  }

  /* Input with icon */
  .input-wrap { position: relative; }
  .input-icon {
    position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
    color: var(--sub); pointer-events: none; display: flex;
  }
  .input-wrap input,
  .input-wrap select {
    width: 100%;
    background: rgba(255,255,255,0.04);
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    padding: 11px 13px 11px 40px;
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    color: var(--text);
    outline: none;
    transition: border-color .2s, background .2s, box-shadow .2s;
    appearance: none;
    -webkit-appearance: none;
  }
  .input-wrap input::placeholder { color: var(--sub); opacity: .55; }
  .input-wrap input:focus,
  .input-wrap select:focus {
    border-color: var(--blue);
    background: var(--blue-dim);
    box-shadow: 0 0 0 3px rgba(91,142,255,0.14);
  }

  /* Select chevron */
  .select-wrap::after {
    content: '';
    position: absolute; right: 13px; top: 50%; transform: translateY(-50%);
    width: 0; height: 0;
    border-left: 4px solid transparent;
    border-right: 4px solid transparent;
    border-top: 5px solid var(--sub);
    pointer-events: none;
  }
  .select-wrap select { cursor: pointer; }
  select option { background: #13172a; color: var(--text); }

  /* Role cards */
  .role-group { margin-bottom: 14px; }
  .role-label {
    display: flex; align-items: center; gap: 6px;
    font-size: 11.5px; font-weight: 500;
    color: var(--sub);
    letter-spacing: 0.06em; text-transform: uppercase;
    margin-bottom: 8px;
  }
  .role-cards {
    display: grid; grid-template-columns: 1fr 1fr; gap: 10px;
    align-items: stretch;
  }
  .role-card {
    position: relative;
    display: flex;
  }
  .role-card input[type="radio"] {
    position: absolute; opacity: 0; width: 0; height: 0;
  }
  .role-card label {
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 7px;
    padding: 14px 10px;
    width: 100%;
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    background: rgba(255,255,255,0.03);
    cursor: pointer;
    transition: border-color .2s, background .2s, box-shadow .2s;
    text-transform: none; letter-spacing: 0; font-size: 13px;
    color: var(--sub); font-weight: 400;
    min-height: 110px;
  }
  .role-card label .role-icon { font-size: 22px; }
  .role-card label .role-name { font-weight: 500; color: var(--text); font-size: 13.5px; }
  .role-card label .role-desc { font-size: 11px; color: var(--sub); text-align: center; line-height: 1.4; }
  .role-card input:checked + label {
    border-color: var(--blue);
    background: var(--blue-dim);
    box-shadow: 0 0 0 3px rgba(91,142,255,0.12);
    color: var(--text);
  }
  .role-card label:hover { border-color: var(--border-hi); }

  /* Password toggle */
  .pw-toggle {
    position: absolute; right: 11px; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer;
    color: var(--sub); padding: 4px; line-height: 1;
    transition: color .2s; display: flex;
    border-radius: 6px;
  }
  .pw-toggle:hover { color: var(--text); background: rgba(255,255,255,0.07); }

  /* Password strength bar */
  .pw-strength { margin-top: 7px; }
  .pw-strength-bar {
    height: 3px; border-radius: 100px;
    background: var(--border);
    overflow: hidden;
    margin-bottom: 4px;
  }
  .pw-strength-fill {
    height: 100%; border-radius: 100px; width: 0%;
    transition: width .3s, background .3s;
  }
  .pw-strength-text { font-size: 11px; color: var(--sub); }

  /* Submit */
  .btn-primary {
    width: 100%;
    background: var(--green);
    color: #04180e;
    border: none; border-radius: var(--radius);
    padding: 13px;
    font-family: 'Syne', sans-serif;
    font-size: 15px; font-weight: 700;
    letter-spacing: 0.02em;
    cursor: pointer; margin-top: 4px;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    transition: transform .15s, box-shadow .2s, filter .15s;
  }
  .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 32px rgba(62,207,142,0.28);
    filter: brightness(1.06);
  }
  .btn-primary:active { transform: translateY(0); box-shadow: none; }
  .btn-arrow { transition: transform .2s; }
  .btn-primary:hover .btn-arrow { transform: translateX(3px); }

  /* Footer */
  .auth-footer {
    display: flex; align-items: center; justify-content: space-between;
    margin-top: 20px;
  }
  .auth-link { font-size: 13px; color: var(--sub); }
  .auth-link a {
    color: var(--blue); text-decoration: none; font-weight: 500;
    transition: opacity .2s;
  }
  .auth-link a:hover { opacity: .8; text-decoration: underline; }

  .neust-badge {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 10.5px; color: var(--sub);
    background: rgba(255,255,255,0.04);
    border: 1px solid var(--border);
    border-radius: 100px; padding: 3px 10px;
  }
  .neust-badge::before {
    content: ''; width: 5px; height: 5px; border-radius: 50%;
    background: var(--green);
    animation: pulse 2.4s ease-in-out infinite;
  }
  @keyframes pulse { 0%,100% { opacity:1; } 50% { opacity:.35; } }
</style>
</head>
<body>

<div class="bg-layer">
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>
  <div class="orb orb-3"></div>
</div>
<div class="grid"></div>
<div class="floaters" id="floaters"></div>

<div class="auth-card">
  <div class="card-topline"></div>

  <div class="logo-area">
    <div class="logo-icon">🧠</div>
    <div class="logo-text">
      <h1>Quiz<em>Generator</em></h1>
      <div class="tagline">NEUST · ITWS Case Study</div>
    </div>
  </div>

  <div class="section-header">
    <h2>Create your account</h2>
    <p>Join and start creating or taking quizzes</p>
  </div>

  <div class="divider"><span>account details</span></div>

  <?php if ($error): ?>
    <div class="alert alert-danger">
      <span class="alert-icon">⚠️</span>
      <span><?= e($error) ?></span>
    </div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success">
      <span class="alert-icon">✅</span>
      <span><?= e($success) ?></span>
    </div>
  <?php endif; ?>

  <form method="POST" action="">
    <?= csrfField() ?>

    <!-- Username + Email side by side -->
    <div class="form-row">
      <div class="form-group">
        <label for="username">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          Username
        </label>
        <div class="input-wrap">
          <span class="input-icon">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </span>
          <input type="text" id="username" name="username" required
                 maxlength="50" placeholder="your_name"
                 value="<?= e(post('username')) ?>">
        </div>
      </div>

      <div class="form-group">
        <label for="email">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="2" y="4" width="20" height="16" rx="2"/><polyline points="2,4 12,13 22,4"/></svg>
          Email
        </label>
        <div class="input-wrap">
          <span class="input-icon">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><polyline points="2,4 12,13 22,4"/></svg>
          </span>
          <input type="email" id="email" name="email" required
                 placeholder="you@email.com"
                 value="<?= e($_POST['email'] ?? '') ?>">
        </div>
      </div>
    </div>

    <!-- Role picker -->
    <div class="role-group">
      <div class="role-label">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Role
      </div>
      <div class="role-cards">
        <div class="role-card">
          <input type="radio" id="role_student" name="role" value="student" checked>
          <label for="role_student">
            <span class="role-icon">🎓</span>
            <span class="role-name">Student</span>
            <span class="role-desc">Take quizzes &amp; track your scores</span>
          </label>
        </div>
        <div class="role-card">
          <input type="radio" id="role_teacher" name="role" value="teacher">
          <label for="role_teacher">
            <span class="role-icon">🧑‍🏫</span>
            <span class="role-name">Teacher</span>
            <span class="role-desc">Create &amp; manage quiz content</span>
          </label>
        </div>
      </div>
    </div>

    <!-- Passwords side by side -->
    <div class="form-row">
      <div class="form-group">
        <label for="password">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          Password
        </label>
        <div class="input-wrap">
          <span class="input-icon">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          </span>
          <input type="password" id="password" name="password" required
                 minlength="8" placeholder="Min. 8 chars">
          <button type="button" class="pw-toggle" id="pwToggle1" aria-label="Toggle password">
            <svg id="eye1" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        <div class="pw-strength" id="pwStrength" style="display:none">
          <div class="pw-strength-bar"><div class="pw-strength-fill" id="pwFill"></div></div>
          <span class="pw-strength-text" id="pwText"></span>
        </div>
      </div>

      <div class="form-group">
        <label for="password_confirm">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="20 6 9 17 4 12"/></svg>
          Confirm
        </label>
        <div class="input-wrap">
          <span class="input-icon">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
          </span>
          <input type="password" id="password_confirm" name="password_confirm" required
                 placeholder="Repeat password">
          <button type="button" class="pw-toggle" id="pwToggle2" aria-label="Toggle confirm password">
            <svg id="eye2" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
      </div>
    </div>

    <button type="submit" class="btn-primary">
      Create Account
      <svg class="btn-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
    </button>
  </form>

  <div class="auth-footer">
    <p class="auth-link">Have an account? <a href="login.php">Sign in</a></p>
    <span class="neust-badge">NEUST</span>
  </div>
</div>

<script>
  // Eye icon SVGs
  const eyeOpen   = `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`;
  const eyeClosed = `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`;

  function makeToggle(btnId, inputId) {
    document.getElementById(btnId).addEventListener('click', function() {
      const inp = document.getElementById(inputId);
      const hide = inp.type === 'password';
      inp.type = hide ? 'text' : 'password';
      this.innerHTML = hide ? eyeClosed : eyeOpen;
    });
  }
  makeToggle('pwToggle1', 'password');
  makeToggle('pwToggle2', 'password_confirm');

  // Password strength
  const pwInput    = document.getElementById('password');
  const pwStrength = document.getElementById('pwStrength');
  const pwFill     = document.getElementById('pwFill');
  const pwText     = document.getElementById('pwText');

  pwInput.addEventListener('input', function() {
    const v = this.value;
    if (!v) { pwStrength.style.display = 'none'; return; }
    pwStrength.style.display = 'block';

    let score = 0;
    if (v.length >= 8)  score++;
    if (v.length >= 12) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^a-zA-Z0-9]/.test(v)) score++;

    const levels = [
      { pct: '20%', color: '#ff5e72', label: 'Too weak' },
      { pct: '40%', color: '#ff9a3c', label: 'Weak' },
      { pct: '60%', color: '#f5c842', label: 'Fair' },
      { pct: '80%', color: '#7bdb8e', label: 'Good' },
      { pct: '100%', color: '#3ecf8e', label: 'Strong' },
    ];
    const lvl = levels[Math.min(score, 4)];
    pwFill.style.width = lvl.pct;
    pwFill.style.background = lvl.color;
    pwText.style.color = lvl.color;
    pwText.textContent = lvl.label;
  });

  // Floating chars
  const chars = ['?','?','A','B','C','D','✓','✗','?'];
  const fc = document.getElementById('floaters');
  function spawn() {
    const el = document.createElement('div');
    el.className = 'floater';
    el.textContent = chars[Math.floor(Math.random() * chars.length)];
    el.style.cssText = `left:${Math.random()*100}vw;font-size:${Math.random()*90+36}px`;
    const d = Math.random() * 16 + 10;
    el.style.animationDuration = d + 's';
    el.style.animationDelay = (Math.random() * -d) + 's';
    fc.appendChild(el);
    setTimeout(() => el.remove(), (d + 2) * 1000);
  }
  for (let i = 0; i < 16; i++) spawn();
  setInterval(spawn, 2200);
</script>
</body>
</html>
