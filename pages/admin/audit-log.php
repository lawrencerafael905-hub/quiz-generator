<?php
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/sanitize.php';
require_once __DIR__ . '/../../includes/layout.php';
require_once __DIR__ . '/../../config/database.php';

startSecureSession();
requireRole('admin');

$table  = get('table');
$action = get('action_filter');
$from   = get('from');
$to     = get('to');
$page   = max(1, getInt('page') ?? 1);
$limit  = 50;
$offset = ($page - 1) * $limit;

$where  = ['1=1'];
$params = [];

if ($table && in_array($table, ['quizzes', 'attempts'], true)) {
    $where[] = 'table_name = ?';
    $params[] = $table;
}
if ($action && in_array($action, ['INSERT', 'UPDATE', 'DELETE'], true)) {
    $where[] = 'action = ?';
    $params[] = $action;
}
if ($from) {
    $where[] = 'DATE(changed_at) >= ?';
    $params[] = $from;
}
if ($to) {
    $where[] = 'DATE(changed_at) <= ?';
    $params[] = $to;
}

$whereSql = implode(' AND ', $where);
$total = (int)db_query("SELECT COUNT(*) FROM audit_log WHERE $whereSql", $params)->fetchColumn();
$logs  = db_query(
    "SELECT * FROM audit_log WHERE $whereSql ORDER BY changed_at DESC LIMIT $limit OFFSET $offset",
    $params
)->fetchAll();

$totalPages = max(1, (int)ceil($total / $limit));

renderHeader('Audit Log', 'audit-log');
?>
<div class="container container-wide">
  <div class="page-header">
    <div class="page-title">
      <h2>Audit Log</h2>
      <p>System activity recorded by database triggers</p>
    </div>
  </div>

  <form method="GET" class="filters-bar">
    <div class="form-group">
      <label for="table">Table</label>
      <select id="table" name="table">
        <option value="">All</option>
        <option value="quizzes" <?= $table === 'quizzes' ? 'selected' : '' ?>>Quizzes</option>
        <option value="attempts" <?= $table === 'attempts' ? 'selected' : '' ?>>Attempts</option>
      </select>
    </div>
    <div class="form-group">
      <label for="action_filter">Action</label>
      <select id="action_filter" name="action_filter">
        <option value="">All</option>
        <option value="INSERT" <?= $action === 'INSERT' ? 'selected' : '' ?>>Insert</option>
        <option value="UPDATE" <?= $action === 'UPDATE' ? 'selected' : '' ?>>Update</option>
        <option value="DELETE" <?= $action === 'DELETE' ? 'selected' : '' ?>>Delete</option>
      </select>
    </div>
    <div class="form-group">
      <label for="from">From</label>
      <input type="date" id="from" name="from" value="<?= e($from) ?>">
    </div>
    <div class="form-group">
      <label for="to">To</label>
      <input type="date" id="to" name="to" value="<?= e($to) ?>">
    </div>
    <button type="submit" class="btn">Filter</button>
    <a href="<?= e(pageUrl('admin/audit-log.php')) ?>" class="btn">Reset</a>
  </form>

  <div class="data-table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>When</th>
          <th>Table</th>
          <th>Action</th>
          <th>Record</th>
          <th>Description</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($logs)): ?>
          <tr><td colspan="5" style="text-align:center;color:var(--sub);padding:32px">No log entries match your filters.</td></tr>
        <?php else: ?>
          <?php foreach ($logs as $log): ?>
          <tr>
            <td style="white-space:nowrap"><?= e(date('M j, Y g:i A', strtotime($log['changed_at']))) ?></td>
            <td><span class="badge badge-info"><?= e($log['table_name']) ?></span></td>
            <td><span class="badge badge-<?= strtolower($log['action']) === 'delete' ? 'danger' : (strtolower($log['action']) === 'insert' ? 'success' : 'warning') ?>"><?= e($log['action']) ?></span></td>
            <td>#<?= (int)$log['record_id'] ?></td>
            <td><?= e($log['description']) ?></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <?php
    $qs = http_build_query(array_filter([
        'table' => $table, 'action_filter' => $action, 'from' => $from, 'to' => $to,
    ]));
    for ($p = 1; $p <= $totalPages; $p++):
    ?>
      <?php if ($p === $page): ?>
        <span class="current"><?= $p ?></span>
      <?php else: ?>
        <a href="?page=<?= $p ?>&<?= e($qs) ?>"><?= $p ?></a>
      <?php endif; ?>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
<?php renderFooter(); ?>
