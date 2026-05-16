<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/sanitize.php';

startSecureSession();

if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = post('username');
    $password = $_POST['password'] ?? '';

    $user = loginUser($username, $password);
    if ($user) {
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — Quiz Generator</title>
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
    --accent-dim:rgba(245,200,66,0.15);
    --blue:      #5b8eff;
    --blue-dim:  rgba(91,142,255,0.12);
    --text:      #eceef8;
    --sub:       #8890b8;
    --danger:    #ff5e72;
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
    overflow: hidden;
    position: relative;
  }

  /* ── Background ── */
  .bg-layer {
    position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden;
  }
  .orb {
    position: absolute; border-radius: 50%; filter: blur(100px);
    animation: drift 18s ease-in-out infinite alternate;
  }
  .orb-1 { width: 600px; height: 600px; background: #1a3aff; opacity: .13; top: -200px; left: -200px; }
  .orb-2 { width: 480px; height: 480px; background: #f5c842; opacity: .10; bottom: -180px; right: -180px; animation-delay: -6s; }
  .orb-3 { width: 320px; height: 320px; background: #8b2fff; opacity: .12; top: 35%; left: 55%; animation-delay: -11s; }
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

  /* Floating quiz chars */
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
    padding: 40px 40px 36px;
    width: calc(100% - 32px);
    max-width: 420px;
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    box-shadow:
      0 0 0 1px rgba(255,255,255,0.05) inset,
      0 40px 80px rgba(0,0,0,0.6),
      0 0 60px rgba(91,142,255,0.06);
    animation: cardIn 0.65s cubic-bezier(0.16,1,0.3,1) both;
  }
  @keyframes cardIn {
    from { opacity: 0; transform: translateY(32px) scale(0.96); }
    to   { opacity: 1; transform: translateY(0)    scale(1);    }
  }

  /* Top accent line */
  .card-topline {
    position: absolute; top: 0; left: 32px; right: 32px; height: 2px;
    background: linear-gradient(90deg, transparent, var(--blue), var(--accent), transparent);
    border-radius: 0 0 4px 4px;
    opacity: .7;
  }

  /* Logo area */
  .logo-area {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 28px;
  }

  .logo-icon {
    width: 46px; height: 46px;
    background: linear-gradient(135deg, #1c2ff0 0%, #5b8eff 100%);
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
    box-shadow: 0 6px 20px rgba(91,142,255,0.35);
  }

  .logo-text { overflow: hidden; }
  .logo-text h1 {
    font-family: 'Syne', sans-serif;
    font-size: 22px;
    font-weight: 800;
    letter-spacing: -0.02em;
    line-height: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .logo-text h1 em {
    font-style: normal;
    color: var(--accent);
  }
  .logo-text .tagline {
    font-size: 11.5px;
    color: var(--sub);
    margin-top: 3px;
    letter-spacing: 0.02em;
  }

  /* Section header */
  .section-header {
    margin-bottom: 24px;
  }
  .section-header h2 {
    font-family: 'Syne', sans-serif;
    font-size: 20px;
    font-weight: 700;
    letter-spacing: -0.01em;
    line-height: 1.2;
  }
  .section-header p {
    font-size: 13px;
    color: var(--sub);
    margin-top: 4px;
  }

  /* divider */
  .divider {
    display: flex; align-items: center; gap: 10px;
    margin-bottom: 20px;
  }
  .divider::before, .divider::after {
    content: ''; flex: 1; height: 1px; background: var(--border);
  }
  .divider span {
    font-size: 11px; color: var(--sub); letter-spacing: 0.08em; text-transform: uppercase;
  }

  /* alert */
  .alert {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 12px 14px;
    border-radius: var(--radius);
    font-size: 13px;
    margin-bottom: 20px;
    animation: slideIn .3s ease both;
    line-height: 1.4;
  }
  .alert-danger {
    background: rgba(255,94,114,0.09);
    border: 1px solid rgba(255,94,114,0.25);
    color: #ff8a96;
  }
  .alert-icon { flex-shrink: 0; font-size: 14px; margin-top: 1px; }
  @keyframes slideIn {
    from { opacity:0; transform:translateY(-6px); }
    to   { opacity:1; transform:translateY(0); }
  }

  /* form */
  .form-group { margin-bottom: 16px; }

  label {
    display: flex; align-items: center; gap: 6px;
    font-size: 12px;
    font-weight: 500;
    color: var(--sub);
    letter-spacing: 0.06em;
    text-transform: uppercase;
    margin-bottom: 7px;
  }
  label svg { opacity: .7; }

  /* input with icon */
  .input-wrap {
    position: relative;
  }
  .input-icon {
    position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
    color: var(--sub); pointer-events: none; display: flex;
  }
  .input-wrap input {
    width: 100%;
    background: rgba(255,255,255,0.04);
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    padding: 12px 14px 12px 42px;
    font-family: 'DM Sans', sans-serif;
    font-size: 14.5px;
    color: var(--text);
    outline: none;
    transition: border-color .2s, background .2s, box-shadow .2s;
  }
  .input-wrap input::placeholder { color: var(--sub); opacity: .55; }
  .input-wrap input:focus {
    border-color: var(--blue);
    background: var(--blue-dim);
    box-shadow: 0 0 0 3px rgba(91,142,255,0.14);
  }

  /* pw toggle */
  .pw-toggle {
    position: absolute; right: 13px; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer;
    color: var(--sub); padding: 4px; line-height: 1;
    transition: color .2s; display: flex;
    border-radius: 6px;
  }
  .pw-toggle:hover { color: var(--text); background: rgba(255,255,255,0.07); }

  /* submit */
  .btn-primary {
    width: 100%;
    background: var(--accent);
    color: #12100a;
    border: none;
    border-radius: var(--radius);
    padding: 13.5px;
    font-family: 'Syne', sans-serif;
    font-size: 15px;
    font-weight: 700;
    letter-spacing: 0.02em;
    cursor: pointer;
    margin-top: 6px;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    position: relative; overflow: hidden;
    transition: transform .15s, box-shadow .2s, filter .15s;
  }
  .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 32px rgba(245,200,66,0.3);
    filter: brightness(1.07);
  }
  .btn-primary:active { transform: translateY(0); box-shadow: none; }
  .btn-arrow {
    transition: transform .2s;
  }
  .btn-primary:hover .btn-arrow { transform: translateX(3px); }

  /* footer */
  .auth-footer {
    display: flex; align-items: center; justify-content: space-between;
    margin-top: 22px;
  }
  .auth-link {
    font-size: 13px;
    color: var(--sub);
  }
  .auth-link a {
    color: var(--blue);
    text-decoration: none;
    font-weight: 500;
    transition: opacity .2s;
  }
  .auth-link a:hover { opacity: .8; text-decoration: underline; }

  .neust-badge {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 10.5px; color: var(--sub);
    letter-spacing: 0.04em;
    background: rgba(255,255,255,0.04);
    border: 1px solid var(--border);
    border-radius: 100px;
    padding: 3px 10px;
  }
  .neust-badge::before {
    content: '';
    width: 5px; height: 5px; border-radius: 50%;
    background: #4caf50;
    animation: pulse 2.4s ease-in-out infinite;
  }
  @keyframes pulse {
    0%,100% { opacity:1; }
    50%      { opacity:.35; }
  }
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

  <!-- Logo -->
  <div class="logo-area">
    <div class="logo-icon">🧠</div>
    <div class="logo-text">
      <h1>Quiz<em>Generator</em></h1>
      <div class="tagline">NEUST · ITWS Case Study</div>
    </div>
  </div>

  <!-- Section header -->
  <div class="section-header">
    <h2>Welcome back</h2>
    <p>Sign in to continue to your dashboard</p>
  </div>

  <div class="divider"><span>credentials</span></div>

  <?php if ($error): ?>
    <div class="alert alert-danger">
      <span class="alert-icon">⚠️</span>
      <span><?= e($error) ?></span>
    </div>
  <?php endif; ?>

  <form method="POST" action="">
    <?= csrfField() ?>

    <div class="form-group">
      <label for="username">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Username
      </label>
      <div class="input-wrap">
        <span class="input-icon">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </span>
        <input type="text" id="username" name="username" required
               autocomplete="username" maxlength="50"
               placeholder="Enter your username"
               value="<?= e(post('username')) ?>">
      </div>
    </div>

    <div class="form-group">
      <label for="password">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        Password
      </label>
      <div class="input-wrap">
        <span class="input-icon">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        </span>
        <input type="password" id="password" name="password" required
               autocomplete="current-password" placeholder="Enter your password">
        <button type="button" class="pw-toggle" id="pwToggle" aria-label="Toggle password visibility">
          <svg id="eyeIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>
    </div>

    <button type="submit" class="btn-primary">
      Sign In
      <svg class="btn-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
    </button>
  </form>

  <div class="auth-footer">
    <p class="auth-link">No account? <a href="register.php">Register here</a></p>
    <span class="neust-badge">NEUST</span>
  </div>
</div>

<script>
  // Password toggle with SVG swap
  const pwToggle = document.getElementById('pwToggle');
  const pwInput  = document.getElementById('password');
  const eyeOpen  = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`;
  const eyeClosed = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`;
  pwToggle.addEventListener('click', () => {
    const hidden = pwInput.type === 'password';
    pwInput.type = hidden ? 'text' : 'password';
    pwToggle.innerHTML = hidden ? eyeClosed : eyeOpen;
  });

  // Floating chars
  const chars = ['?','?','?','A','B','C','D','✓','✗','?'];
  const fc = document.getElementById('floaters');
  function spawn() {
    const el = document.createElement('div');
    el.className = 'floater';
    el.textContent = chars[Math.floor(Math.random() * chars.length)];
    el.style.cssText = `left:${Math.random()*100}vw;font-size:${Math.random()*90+36}px`;
    const d = Math.random() * 16 + 10;
    el.style.animationDuration = d + 's';
    el.style.animationDelay    = (Math.random() * -d) + 's';
    fc.appendChild(el);
    setTimeout(() => el.remove(), (d + 2) * 1000);
  }
  for (let i = 0; i < 16; i++) spawn();
  setInterval(spawn, 2200);
</script>
</body>
</html>
