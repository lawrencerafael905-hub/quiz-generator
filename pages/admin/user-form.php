<?php
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../config/database.php';

startSecureSession();
requireRole('admin');

$userId = getInt('id');
$edit   = null;

if ($userId) {
    $edit = db_query('SELECT id, username, email, role FROM users WHERE id=?', [$userId])->fetch();
    if (!$edit) {
        setFlash('danger', 'User not found.');
        header('Location: ' . pageUrl('admin/users.php'));
        exit;
    }
}

$isEdit = (bool)$edit;
renderHeader($isEdit ? 'Edit User' : 'New User', 'admin-users');
?>
<div class="container" style="max-width:520px">
  <div class="page-header">
    <div class="page-title">
      <h2><?= $isEdit ? 'Edit User' : 'Create User' ?></h2>
      <p><?= $isEdit ? 'Update account details' : 'Add a new account to the system' ?></p>
    </div>
  </div>

  <a href="<?= e(pageUrl('admin/users.php')) ?>" class="btn" style="margin-bottom:20px">&larr; Back to Users</a>

  <div class="card">
    <form id="user-form">
      <?php if ($isEdit): ?>
        <input type="hidden" id="user-id" value="<?= (int)$edit['id'] ?>">
      <?php endif; ?>

      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" required minlength="3" maxlength="50"
               value="<?= e($edit['username'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" required value="<?= e($edit['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="password">Password<?= $isEdit ? ' (leave blank to keep)' : '' ?></label>
        <input type="password" id="password" <?= $isEdit ? '' : 'required minlength="8"' ?>>
      </div>
      <div class="form-group">
        <label for="role">Role</label>
        <select id="role">
          <option value="student" <?= ($edit['role'] ?? '') === 'student' ? 'selected' : '' ?>>Student</option>
          <option value="teacher" <?= ($edit['role'] ?? '') === 'teacher' ? 'selected' : '' ?>>Teacher</option>
          <option value="admin" <?= ($edit['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
        </select>
      </div>
      <div id="form-error" class="alert alert-danger" style="display:none"></div>
      <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Create User' ?></button>
    </form>
  </div>
</div>

<script>
const CSRF = <?= json_encode(csrfToken()) ?>;
const API_U = <?= json_encode(assetUrl('api/user.php')) ?>;
const IS_EDIT = <?= $isEdit ? 'true' : 'false' ?>;

document.getElementById('user-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const errEl = document.getElementById('form-error');
  errEl.style.display = 'none';

  const payload = {
    action: IS_EDIT ? 'update' : 'create',
    username: document.getElementById('username').value.trim(),
    email: document.getElementById('email').value.trim(),
    password: document.getElementById('password').value,
    role: document.getElementById('role').value,
    _csrf_token: CSRF
  };
  if (IS_EDIT) payload.id = parseInt(document.getElementById('user-id').value);

  const res = await fetch(API_U, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
    body: JSON.stringify(payload)
  });
  const data = await res.json();
  if (data.success) {
    window.location.href = <?= json_encode(pageUrl('admin/users.php')) ?> + (IS_EDIT ? '?updated=1' : '?created=1');
  } else {
    errEl.textContent = data.error || 'Request failed.';
    errEl.style.display = 'block';
  }
});
</script>
<?php renderFooter(); ?>
