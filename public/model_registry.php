<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/audit_helpers.php';

require_login();
$uid = current_user_id();

$workspaces = workspaces_for_user($pdo, $uid);
$ws_ids = array_map(static fn($w) => (int) $w['workspace_id'], $workspaces);

$filter_ws = $_GET['workspace_id'] ?? '';
if ($filter_ws !== '' && $filter_ws !== 'all' && ctype_digit((string) $filter_ws)) {
    $filter_ws = (int) $filter_ws;
    if (!in_array($filter_ws, $ws_ids, true)) {
        $filter_ws = 0;
    }
} else {
    $filter_ws = null;
}

$params = [$uid];
if ($filter_ws !== null && $filter_ws > 0) {
    $ws_clause = ' AND w.workspace_id = ? ';
    $params[] = $filter_ws;
} else {
    $ws_clause = '';
}

$sql = "SELECT mr.model_version_id, mr.source_run_id, mr.stage, mr.approved_at,
               m.model_name, p.project_name, w.workspace_name,
               u.full_name AS approved_by_name, wm.member_role
        FROM ModelRegistry mr
        INNER JOIN Models m ON m.model_id = mr.model_id
        INNER JOIN Projects p ON p.project_id = m.project_id
        INNER JOIN Workspaces w ON w.workspace_id = p.workspace_id
        INNER JOIN WorkspaceMembers wm ON wm.workspace_id = p.workspace_id AND wm.user_id = ?
        INNER JOIN Users u ON u.user_id = mr.approved_by_user_id
        WHERE 1=1 {$ws_clause}
        ORDER BY mr.approved_at DESC";

$st = $pdo->prepare($sql);
$st->execute($params);
$entries = $st->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LabBench - Model Registry</title>
  <link rel="stylesheet" href="assets/styles.css" />
</head>
<body>
  <div class="layout">
    <aside class="sidebar">
      <div class="logo">LABBENCH</div>
      <nav class="nav">
        <?php render_sidebar('model_registry'); ?>
      </nav>
    </aside>
    <div class="main">
      <header class="header">
        <div>Model Registry</div>
        <div class="header-right">Signed in as <?php echo h(current_user_name()); ?></div>
      </header>
      <main class="content">
        <?php show_flash(); ?>

<div class="breadcrumb">Workspace / Model Registry</div>
<h1 class="page-title">Model Registry</h1>
<p class="page-sub">Approved model versions across your workspaces.</p>

<div class="actions">
  <a class="button" href="create_registry_entry.php">+ Add Entry</a>
</div>

<div class="card" style="margin-bottom:18px;">
  <div class="card-title">Filter by workspace</div>
  <form method="get" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
    <div class="form-group" style="margin-bottom:0;min-width:220px;">
      <label for="workspace_id">Workspace</label>
      <select id="workspace_id" name="workspace_id" onchange="this.form.submit()">
        <option value="all"<?php echo $filter_ws === null ? ' selected' : ''; ?>>All my workspaces</option>
        <?php foreach ($workspaces as $w): ?>
          <option value="<?php echo h((string) $w['workspace_id']); ?>"<?php echo $filter_ws === (int) $w['workspace_id'] ? ' selected' : ''; ?>>
            <?php echo h($w['workspace_name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>
</div>

<div class="stats">
  <div class="stat-card"><div class="stat-label">Registry Entries</div><div class="stat-value"><?php echo h((string) count($entries)); ?></div></div>
</div>

<div class="card">
  <div class="card-title">Registry Entries</div>
  <table>
    <thead>
      <tr>
        <th>Model Version ID</th>
        <th>Model</th>
        <th>Project</th>
        <th>Source Run</th>
        <th>Stage</th>
        <th>Approved By</th>
        <th>Approved At</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if ($entries === []): ?>
        <tr><td colspan="8" class="placeholder">No registry entries yet.</td></tr>
      <?php else: ?>
        <?php foreach ($entries as $e): ?>
          <?php
            $stage = strtolower((string) $e['stage']);
            $badge = 'badge-blue';
            if ($stage === 'production') {
                $badge = 'badge-green';
            } elseif ($stage === 'staging') {
                $badge = 'badge-yellow';
            }
            $is_admin = ($e['member_role'] === 'admin');
          ?>
          <tr>
            <td class="mono"><?php echo h((string) $e['model_version_id']); ?></td>
            <td><?php echo h($e['model_name']); ?></td>
            <td><?php echo h($e['project_name']); ?> <span class="muted small">&mdash; <?php echo h($e['workspace_name']); ?></span></td>
            <td class="mono"><?php echo h((string) $e['source_run_id']); ?></td>
            <td><span class="badge <?php echo h($badge); ?>"><?php echo h($e['stage']); ?></span></td>
            <td><?php echo h($e['approved_by_name']); ?></td>
            <td class="mono"><?php echo h($e['approved_at']); ?></td>
            <td style="white-space:nowrap;">
              <a class="button secondary" href="edit_registry_entry.php?id=<?php echo h((string) $e['model_version_id']); ?>">Edit</a>
              <?php if ($is_admin): ?>
              <form method="post" action="delete_registry_entry.php" style="display:inline;"
                    onsubmit="return confirm('Delete this registry entry?');">
                <input type="hidden" name="model_version_id" value="<?php echo h((string) $e['model_version_id']); ?>" />
                <button type="submit" class="secondary"
                        style="background:transparent;color:#f47f7f;border-color:#5c2a2a;">Delete</button>
              </form>
              <?php endif; ?>
            </td>
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
