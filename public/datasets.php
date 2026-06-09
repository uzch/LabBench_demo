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

$sql = "SELECT d.dataset_id, d.dataset_name, d.modality, d.source_type,
               p.project_id, p.project_name, w.workspace_name,
               COUNT(dv.dataset_version_id) AS version_count
        FROM Datasets d
        INNER JOIN Projects p ON p.project_id = d.project_id
        INNER JOIN Workspaces w ON w.workspace_id = p.workspace_id
        INNER JOIN WorkspaceMembers wm ON wm.workspace_id = p.workspace_id AND wm.user_id = ?
        LEFT JOIN DatasetVersions dv ON dv.dataset_id = d.dataset_id
        WHERE 1=1 {$ws_clause}
        GROUP BY d.dataset_id, d.dataset_name, d.modality, d.source_type,
                 p.project_id, p.project_name, w.workspace_name
        ORDER BY d.dataset_id DESC";

$st = $pdo->prepare($sql);
$st->execute($params);
$datasets = $st->fetchAll();

$count_datasets = count($datasets);
$count_versions = (int) array_sum(array_column($datasets, 'version_count'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LabBench - Datasets</title>
  <link rel="stylesheet" href="assets/styles.css" />
</head>
<body>
  <div class="layout">
    <aside class="sidebar">
      <div class="logo">LABBENCH</div>
      <nav class="nav">
        <?php render_sidebar('datasets'); ?>
      </nav>
    </aside>
    <div class="main">
      <header class="header">
        <div>Datasets</div>
        <div class="header-right">Signed in as <?php echo h(current_user_name()); ?></div>
      </header>
      <main class="content">
        <?php show_flash(); ?>

<div class="breadcrumb">Workspace / Datasets</div>
<h1 class="page-title">Datasets</h1>
<p class="page-sub">Datasets across workspaces you belong to.</p>

<div class="actions">
  <a class="button" href="create_dataset.php">+ New Dataset</a>
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
  <div class="stat-card"><div class="stat-label">Datasets</div><div class="stat-value"><?php echo h((string) $count_datasets); ?></div></div>
  <div class="stat-card"><div class="stat-label">Versions</div><div class="stat-value"><?php echo h((string) $count_versions); ?></div></div>
</div>

<div class="card">
  <div class="card-title">Dataset Registry</div>
  <table>
    <thead>
      <tr>
        <th>Dataset Name</th>
        <th>Project</th>
        <th>Modality</th>
        <th>Source</th>
        <th>Versions</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if ($datasets === []): ?>
        <tr><td colspan="6" class="placeholder">No datasets yet. Create one to get started.</td></tr>
      <?php else: ?>
        <?php foreach ($datasets as $d): ?>
        <tr>
          <td><a href="dataset_detail.php?id=<?php echo h((string) $d['dataset_id']); ?>"><?php echo h($d['dataset_name']); ?></a></td>
          <td><?php echo h($d['project_name']); ?> <span class="muted small">&mdash; <?php echo h($d['workspace_name']); ?></span></td>
          <td><span class="badge badge-blue"><?php echo h($d['modality']); ?></span></td>
          <td class="mono"><?php echo h($d['source_type']); ?></td>
          <td class="mono"><?php echo h((string) $d['version_count']); ?></td>
          <td style="white-space:nowrap;">
            <a class="button secondary" href="edit_dataset.php?id=<?php echo h((string) $d['dataset_id']); ?>">Edit</a>
            <a class="button secondary" href="create_dataset_version.php?dataset_id=<?php echo h((string) $d['dataset_id']); ?>">+ Version</a>
            <form method="post" action="delete_dataset.php" style="display:inline;"
                  onsubmit="return confirm('Delete this dataset and all its versions and runs?');">
              <input type="hidden" name="dataset_id" value="<?php echo h((string) $d['dataset_id']); ?>" />
              <button type="submit" class="secondary"
                      style="background:transparent;color:#f47f7f;border-color:#5c2a2a;">Delete</button>
            </form>
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
