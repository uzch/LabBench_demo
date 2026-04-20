<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit_helpers.php';

require_login();
$uid = current_user_id();

$workspaces = workspaces_for_user($pdo, $uid);
$ws_ids = array_map(static fn ($w) => (int) $w['workspace_id'], $workspaces);

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
    $ws_clause = ' AND p.workspace_id = ? ';
    $params[] = $filter_ws;
} else {
    $ws_clause = '';
}

$sql = "SELECT p.project_id, p.workspace_id, p.project_name, p.description, p.created_at,
               w.workspace_name, u.full_name AS creator_name
        FROM Projects p
        INNER JOIN Workspaces w ON w.workspace_id = p.workspace_id
        INNER JOIN WorkspaceMembers wm ON wm.workspace_id = p.workspace_id AND wm.user_id = ?
        INNER JOIN Users u ON u.user_id = p.created_by_user_id
        WHERE 1=1 {$ws_clause}
        ORDER BY p.created_at DESC";

$st = $pdo->prepare($sql);
$st->execute($params);
$projects = $st->fetchAll();

$count_projects = count($projects);
$count_models = 0;
$count_runs = 0;

if ($ws_ids !== []) {
    $in = implode(',', array_fill(0, count($ws_ids), '?'));
    $params_m = $ws_ids;
    if ($filter_ws !== null && $filter_ws > 0) {
        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM Models m
             INNER JOIN Projects p ON p.project_id = m.project_id
             WHERE p.workspace_id = ?"
        );
        $st->execute([$filter_ws]);
        $count_models = (int) $st->fetchColumn();

        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM Runs r
             INNER JOIN Models m ON m.model_id = r.model_id
             INNER JOIN Projects p ON p.project_id = m.project_id
             WHERE p.workspace_id = ?"
        );
        $st->execute([$filter_ws]);
        $count_runs = (int) $st->fetchColumn();
    } else {
        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM Models m
             INNER JOIN Projects p ON p.project_id = m.project_id
             WHERE p.workspace_id IN ($in)"
        );
        $st->execute($params_m);
        $count_models = (int) $st->fetchColumn();

        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM Runs r
             INNER JOIN Models m ON m.model_id = r.model_id
             INNER JOIN Projects p ON p.project_id = m.project_id
             WHERE p.workspace_id IN ($in)"
        );
        $st->execute($params_m);
        $count_runs = (int) $st->fetchColumn();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LabBench - Projects</title>
  <link rel="stylesheet" href="styles.css" />
</head>
<body>
  <div class="layout">
    <aside class="sidebar">
      <div class="logo">LABBENCH</div>
      <nav class="nav">
        <?php render_sidebar('projects'); ?>
      </nav>
    </aside>
    <div class="main">
      <header class="header">
        <div>Projects</div>
        <div class="header-right">Signed in as user #<?php echo h((string) $uid); ?></div>
      </header>
      <main class="content">
        <?php show_flash(); ?>

<div class="breadcrumb">Workspace / Projects</div>
<h1 class="page-title">Projects</h1>
<p class="page-sub">Projects in workspaces you belong to.</p>

<div class="actions">
  <a class="button" href="create_project.php">+ New Project</a>
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
  <div class="stat-card"><div class="stat-label">Projects</div><div class="stat-value"><?php echo h((string) $count_projects); ?></div></div>
  <div class="stat-card"><div class="stat-label">Models</div><div class="stat-value"><?php echo h((string) $count_models); ?></div></div>
  <div class="stat-card"><div class="stat-label">Runs</div><div class="stat-value"><?php echo h((string) $count_runs); ?></div></div>
</div>

<div class="card">
  <div class="card-title">Project Cards</div>
  <?php if ($projects === []): ?>
    <p class="muted">No projects yet. Create one to get started.</p>
  <?php else: ?>
  <div class="two-col">
    <?php foreach ($projects as $p): ?>
    <div class="card">
      <div class="card-title"><?php echo h($p['project_name']); ?></div>
      <div class="small">In <?php echo h($p['workspace_name']); ?> · Created by <?php echo h($p['creator_name']); ?> on <?php echo h($p['created_at']); ?></div>
      <p class="muted"><?php echo $p['description'] !== null && $p['description'] !== '' ? h($p['description']) : '<span class="placeholder">No description</span>'; ?></p>
      <div class="form-actions">
        <a class="button secondary" href="project_detail.php?id=<?php echo h((string) $p['project_id']); ?>">Open Project</a>
        <a class="button secondary" href="edit_project.php?id=<?php echo h((string) $p['project_id']); ?>">Edit</a>
        <form method="post" action="delete_project.php" style="display:inline;" onsubmit="return confirm('Delete this project?');">
          <input type="hidden" name="project_id" value="<?php echo h((string) $p['project_id']); ?>" />
          <button type="submit" class="secondary" style="background:transparent;color:#f47f7f;border-color:#5c2a2a;">Delete</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

      </main>
    </div>
  </div>
</body>
</html>
