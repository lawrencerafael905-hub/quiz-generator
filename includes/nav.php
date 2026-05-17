<?php
// includes/nav.php — Role-aware navigation

function renderNav(string $activeNav = ''): void {
    $user = currentUser();
    if (!$user) {
        return;
    }

    $isTeacher = in_array($user['role'], ['teacher', 'admin'], true);
    $isAdmin   = $user['role'] === 'admin';

    $links = [
        'dashboard'   => ['label' => 'Dashboard',   'url' => pageUrl('dashboard.php'),           'show' => true],
        'my-attempts' => ['label' => 'My Attempts', 'url' => pageUrl('my-attempts.php'),         'show' => true],
        'quiz-create' => ['label' => 'New Quiz',    'url' => pageUrl('quiz-create.php'),         'show' => $isTeacher, 'primary' => true],
        'admin-users' => ['label' => 'Users',       'url' => pageUrl('admin/users.php'),       'show' => $isAdmin],
        'audit-log'   => ['label' => 'Audit Log',   'url' => pageUrl('admin/audit-log.php'),   'show' => $isAdmin],
    ];
    ?>
<nav class="navbar">
  <a href="<?= e(pageUrl('dashboard.php')) ?>" class="brand">
    <div class="brand-icon">🧠</div>
    Quiz<em>Generator</em>
  </a>

  <div class="nav-links">
    <?php foreach ($links as $key => $link): ?>
      <?php if (!$link['show'] || !empty($link['primary'])) continue; ?>
      <a href="<?= e($link['url']) ?>" class="nav-link<?= $activeNav === $key ? ' active' : '' ?>">
        <?= e($link['label']) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="nav-right">
    <div class="nav-user">
      <div class="nav-avatar"><?= strtoupper(substr(e($user['username']), 0, 1)) ?></div>
      <?= e($user['username']) ?>
      <span class="nav-role"><?= e($user['role']) ?></span>
    </div>
    <?php if ($isTeacher): ?>
      <a href="<?= e(pageUrl('quiz-create.php')) ?>" class="btn btn-primary">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        New Quiz
      </a>
    <?php endif; ?>
    <a href="<?= e(assetUrl('api/logout.php')) ?>" class="btn">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Log Out
    </a>
  </div>
</nav>
    <?php
}
