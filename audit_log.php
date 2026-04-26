<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit_helpers.php';

require_login();
$uid = current_user_id();

$workspaces = workspaces_for_user($pdo, $uid);
$ws_ids = array_map(static fn ($w) => (int) $w['workspace_id'], $workspaces);

$f_ws = $_GET['filter_workspace'] ?? '';
$f_action = $_GET['filter_action'] ?? '';
$f_entity = $_GET['filter_entity'] ?? '';
$f_from = trim((string) ($_GET['from'] ?? ''));
$f_to = trim((string) ($_GET['to'] ?? ''));

$filter_workspace = null;
if ($f_ws !== '' && $f_ws !== 'all' && ctype_digit((string) $f_ws)) {
    $fw = (int) $f_ws;
    if (in_array($fw, $ws_ids, true)) {
        $filter_workspace = $fw;
    }
}

$action_types = ['create', 'update', 'delete', 'approve'];
$filter_action = in_array($f_action, $action_types, true) ? $f_action : null;

$entity_types = ['workspace', 'project', 'workspace_member', 'run', 'model', 'dataset', 'model_registry'];
$filter_entity = in_array($f_entity, $entity_types, true) ? $f_entity : null;

$params = [$uid];
$sql = 'SELECT al.log_id, al.workspace_id, al.actor_user_id, al.action_type, al.entity_type, al.entity_id,
               al.action_timestamp, w.workspace_name, u.full_name AS actor_name
        FROM AuditLog al
        INNER JOIN Workspaces w ON w.workspace_id = al.workspace_id
        INNER JOIN WorkspaceMembers wm ON wm.workspace_id = al.workspace_id AND wm.user_id = ?
        INNER JOIN Users u ON u.user_id = al.actor_user_id
        WHERE 1=1';

if ($filter_workspace !== null) {
    $sql .= ' AND al.workspace_id = ?';
    $params[] = $filter_workspace;
}
if ($filter_action !== null) {
    $sql .= ' AND al.action_type = ?';
    $params[] = $filter_action;
}
if ($filter_entity !== null) {
    $sql .= ' AND al.entity_type = ?';
    $params[] = $filter_entity;
}
if ($f_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_from)) {
    $sql .= ' AND DATE(al.action_timestamp) >= ?';
    $params[] = $f_from;
}
if ($f_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_to)) {
    $sql .= ' AND DATE(al.action_timestamp) <= ?';
    $params[] = $f_to;
}

$sql .= ' ORDER BY al.action_timestamp DESC, al.log_id DESC LIMIT 500';

$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LabBench - Audit Log</title>
  <link rel="stylesheet" href="styles.css" />
</head>
<body>
  <div class="layout">
    <aside class="sidebar">
      <div class="logo">LABBENCH</div>
      <nav class="nav">
        <?php render_sidebar('audit_log'); ?>
      </nav>
    </aside>
    <div class="main">
      <header class="header">
        <div>Audit Log</div>
        <div class="header-right">Showing up to 500 rows · newest first</div>
      </header>
      <main class="content">

<h1 class="page-title">Audit Log</h1>
<p class="page-sub">Activity in workspaces you belong to.</p>

<div class="card" style="margin-bottom:18px;">
  <div class="card-title">Filters</div>
  <form method="get" class="form-grid" style="grid-template-columns: repeat(2, minmax(160px, 1fr)); align-items:end;">
    <div class="form-group">
      <label for="filter_workspace">Workspace</label>
      <select id="filter_workspace" name="filter_workspace">
        <option value="all">All</option>
        <?php foreach ($workspaces as $w): ?>
          <option value="<?php echo h((string) $w['workspace_id']); ?>"<?php echo $filter_workspace === (int) $w['workspace_id'] ? ' selected' : ''; ?>>
            <?php echo h($w['workspace_name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label for="filter_action">Action</label>
      <select id="filter_action" name="filter_action">
        <option value="">All</option>
        <?php foreach ($action_types as $a): ?>
          <option value="<?php echo h($a); ?>"<?php echo $filter_action === $a ? ' selected' : ''; ?>><?php echo h($a); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label for="filter_entity">Entity type</label>
      <select id="filter_entity" name="filter_entity">
        <option value="">All</option>
        <?php foreach ($entity_types as $e): ?>
          <option value="<?php echo h($e); ?>"<?php echo $filter_entity === $e ? ' selected' : ''; ?>><?php echo h($e); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label for="from">From date</label>
      <input type="date" id="from" name="from" value="<?php echo h($f_from); ?>" />
    </div>
    <div class="form-group">
      <label for="to">To date</label>
      <input type="date" id="to" name="to" value="<?php echo h($f_to); ?>" />
    </div>
    <div class="form-actions" style="grid-column:1/-1;">
      <button type="submit">Apply filters</button>
      <a class="button secondary" href="audit_log.php">Reset</a>
    </div>
  </form>
</div>

<div class="card">
  <div class="card-title">Entries (<?php echo h((string) count($rows)); ?>)</div>
  <table>
    <thead>
      <tr>
        <th>Time</th>
        <th>Workspace</th>
        <th>Actor</th>
        <th>Action</th>
        <th>Entity</th>
        <th>ID</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($rows === []): ?>
      <tr><td colspan="6" class="placeholder">No log entries match these filters.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
      <tr>
        <td class="mono"><?php echo h($r['action_timestamp']); ?></td>
        <td><?php echo h($r['workspace_name']); ?></td>
        <td><?php echo h($r['actor_name']); ?> <span class="small muted">(#<?php echo h((string) $r['actor_user_id']); ?>)</span></td>
        <td><span class="badge badge-blue"><?php echo h($r['action_type']); ?></span></td>
        <td><?php echo h($r['entity_type']); ?></td>
        <td class="mono"><?php echo h((string) $r['entity_id']); ?></td>
      </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

      </main>
    </div>
  </div>
</body>
</html>
