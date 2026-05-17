<?php
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../config/database.php';

startSecureSession();
requireRole('admin');

if (isset($_GET['created'])) {
    setFlash('success', 'User created successfully.');
    header('Location: ' . pageUrl('admin/users.php'));
    exit;
}
if (isset($_GET['updated'])) {
    setFlash('success', 'User updated successfully.');
    header('Location: ' . pageUrl('admin/users.php'));
    exit;
}

$search = get('q');
$page   = max(1, getInt('page') ?? 1);
$limit  = 20;
$offset = ($page - 1) * $limit;

$where  = '1=1';
$params = [];
if ($search) {
    $where .= ' AND (username LIKE ? OR email LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$total = (int)db_query("SELECT COUNT(*) FROM users WHERE $where", $params)->fetchColumn();
$users = db_query(
    "SELECT id, username, email, role, created_at FROM users WHERE $where ORDER BY created_at DESC LIMIT $limit OFFSET $offset",
    $params
)->fetchAll();

$totalPages = max(1, (int)ceil($total / $limit));

renderHeader('Manage Users', 'admin-users');
?>
<div class="container container-wide">
  <div class="page-header">
    <div class="page-title">
      <h2>Users</h2>
      <p>Create, edit, and remove system accounts</p>
    </div>
    <a href="<?= e(pageUrl('admin/user-form.php')) ?>" class="btn btn-primary">+ New User</a>
  </div>

  <?php renderFlash(); ?>

  <form method="GET" class="filters-bar">
    <div class="form-group">
      <label for="q">Search</label>
      <input type="text" id="q" name="q" placeholder="Username or email…" value="<?= e($search) ?>">
    </div>
    <button type="submit" class="btn">Search</button>
    <?php if ($search): ?>
      <a href="<?= e(pageUrl('admin/users.php')) ?>" class="btn">Clear</a>
    <?php endif; ?>
  </form>

  <div class="data-table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Username</th>
          <th>Email</th>
          <th>Role</th>
          <th>Joined</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($users)): ?>
          <tr><td colspan="6" style="text-align:center;color:var(--sub);padding:32px">No users found.</td></tr>
        <?php else: ?>
          <?php foreach ($users as $u): ?>
          <tr>
            <td><?= (int)$u['id'] ?></td>
            <td><?= e($u['username']) ?></td>
            <td><?= e($u['email']) ?></td>
            <td><span class="badge badge-<?= e($u['role']) ?>"><?= e($u['role']) ?></span></td>
            <td><?= e(date('M j, Y', strtotime($u['created_at']))) ?></td>
            <td>
              <div class="table-actions">
                <a href="<?= e(pageUrl('admin/user-form.php')) ?>?id=<?= (int)$u['id'] ?>" class="btn btn-sm">Edit</a>
                <?php if ((int)$u['id'] !== (int)currentUser()['id']): ?>
                  <button type="button" class="btn btn-danger btn-sm del-user" data-id="<?= (int)$u['id'] ?>" data-name="<?= e($u['username']) ?>">Delete</button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
      <?php if ($p === $page): ?>
        <span class="current"><?= $p ?></span>
      <?php else: ?>
        <a href="?page=<?= $p ?><?= $search ? '&q=' . urlencode($search) : '' ?>"><?= $p ?></a>
      <?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<script>
const CSRF = <?= json_encode(csrfToken()) ?>;
const API_U = <?= json_encode(assetUrl('api/user.php')) ?>;
document.querySelectorAll('.del-user').forEach(btn => {
  btn.addEventListener('click', async () => {
    if (!confirm('Delete user "' + btn.dataset.name + '"? This cannot be undone.')) return;
    btn.disabled = true;
    const res = await fetch(API_U, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify({ action: 'delete', id: parseInt(btn.dataset.id) })
    });
    const data = await res.json();
    if (data.success) location.reload();
    else { alert(data.error || 'Delete failed.'); btn.disabled = false; }
  });
});
</script>
<?php renderFooter(); ?>
