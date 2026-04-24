<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit_helpers.php';

require_login();
$uid = current_user_id();

$id = isset($_GET['id']) && ctype_digit((string) $_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    set_flash('error', 'Invalid project.');
    redirect('projects.php');
}

$st = $pdo->prepare(
    'SELECT p.*, w.workspace_name, u.full_name AS creator_name
     FROM Projects p
     INNER JOIN Workspaces w ON w.workspace_id = p.workspace_id
     INNER JOIN WorkspaceMembers wm ON wm.workspace_id = p.workspace_id AND wm.user_id = ?
     INNER JOIN Users u ON u.user_id = p.created_by_user_id
     WHERE p.project_id = ?'
);
$st->execute([$uid, $id]);
$project = $st->fetch();
if (!$project) {
    set_flash('error', 'Project not found or access denied.');
    redirect('projects.php');
}

$st = $pdo->prepare('SELECT model_id, model_name, description, created_at FROM Models WHERE project_id = ? ORDER BY created_at DESC');
$st->execute([$id]);
$models = $st->fetchAll();

$st = $pdo->prepare('SELECT dataset_id, dataset_name, modality, source_type FROM Datasets WHERE project_id = ? ORDER BY dataset_id');
$st->execute([$id]);
$datasets = $st->fetchAll();

$st = $pdo->prepare(
    'SELECT r.run_id, r.status, r.started_at, m.model_name, dv.version_tag
     FROM Runs r
     INNER JOIN Models m ON m.model_id = r.model_id
     INNER JOIN DatasetVersions dv ON dv.dataset_version_id = r.dataset_version_id
     WHERE m.project_id = ?
     ORDER BY r.started_at DESC'
);
$st->execute([$id]);
$runs = $st->fetchAll();

$st = $pdo->prepare(
    'SELECT COUNT(*) FROM Runs r INNER JOIN Models m ON m.model_id = r.model_id WHERE m.project_id = ?'
);
$st->execute([$id]);
$n_runs = (int) $st->fetchColumn();

$st = $pdo->prepare('SELECT COUNT(*) FROM Models WHERE project_id = ?');
$st->execute([$id]);
$n_models = (int) $st->fetchColumn();

$st = $pdo->prepare('SELECT COUNT(*) FROM Datasets WHERE project_id = ?');
$st->execute([$id]);
$n_datasets = (int) $st->fetchColumn();

$desc = $project['description'] ?? '';
$pname = (string) $project['project_name'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LabBench - Project Detail</title>
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
        <div>Project Detail</div>
        <div class="header-right"><?php echo h($project['workspace_name']); ?></div>
      </header>
      <main class="content">
        <?php show_flash(); ?>

<div class="breadcrumb"><a href="projects.php">Projects</a> / <?php echo h($pname); ?></div>
<h1 class="page-title"><?php echo h($pname); ?></h1>
<p class="page-sub"><?php echo $desc !== '' ? h($desc) : '<span class="placeholder">No description</span>'; ?></p>

<div class="actions">
  <a class="button secondary" href="edit_project.php?id=<?php echo h((string) $id); ?>">Edit Project</a>
  <a class="button" href="log_run.php?project_id=<?php echo h((string) $id); ?>">+ Log Run</a>
  <a class="button secondary" href="create_model.html">+ Add Model</a>
  <a class="button secondary" href="create_dataset.html">+ Add Dataset</a>
</div>

<div class="stats">
  <div class="stat-card"><div class="stat-label">Runs</div><div class="stat-value"><?php echo h((string) $n_runs); ?></div></div>
  <div class="stat-card"><div class="stat-label">Models</div><div class="stat-value"><?php echo h((string) $n_models); ?></div></div>
  <div class="stat-card"><div class="stat-label">Datasets</div><div class="stat-value"><?php echo h((string) $n_datasets); ?></div></div>
</div>

<div class="card">
  <div class="card-title">Models</div>
  <table>
    <thead><tr><th>Model Name</th><th>Description</th><th>Created</th></tr></thead>
    <tbody>
      <?php if ($models === []): ?>
      <tr><td colspan="3" class="placeholder">No models yet.</td></tr>
      <?php else: ?>
        <?php foreach ($models as $m): ?>
      <tr>
        <td><?php echo h($m['model_name']); ?></td>
        <td><?php echo $m['description'] !== null && $m['description'] !== '' ? h($m['description']) : '<span class="placeholder">—</span>'; ?></td>
        <td class="mono"><?php echo h($m['created_at']); ?></td>
      </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="card">
  <div class="card-title">Datasets</div>
  <table>
    <thead><tr><th>Dataset Name</th><th>Modality</th><th>Source</th></tr></thead>
    <tbody>
      <?php if ($datasets === []): ?>
      <tr><td colspan="3" class="placeholder">No datasets yet.</td></tr>
      <?php else: ?>
        <?php foreach ($datasets as $d): ?>
      <tr>
        <td><?php echo h($d['dataset_name']); ?></td>
        <td><span class="badge badge-blue"><?php echo h($d['modality']); ?></span></td>
        <td class="mono"><?php echo h($d['source_type']); ?></td>
      </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="card">
  <div class="card-title">Runs</div>
  <table>
    <thead><tr><th>Run ID</th><th>Model</th><th>Dataset Version</th><th>Status</th><th>Started</th><th></th></tr></thead>
    <tbody>
      <?php if ($runs === []): ?>
      <tr><td colspan="6" class="placeholder">No runs yet.</td></tr>
      <?php else: ?>
        <?php foreach ($runs as $r): ?>
      <tr>
        <td class="mono"><?php echo h((string) $r['run_id']); ?></td>
        <td><?php echo h($r['model_name']); ?></td>
        <td class="mono"><?php echo h($r['version_tag']); ?></td>
        <td>
          <?php
            $statusLower = strtolower((string) $r['status']);
            $badgeClass = 'badge-yellow';
            if (in_array($statusLower, ['succeeded', 'success', 'completed'], true)) {
                $badgeClass = 'badge-green';
            } elseif (in_array($statusLower, ['failed', 'error'], true)) {
                $badgeClass = 'badge-red';
            }
            ?>
          <span class="badge <?php echo h($badgeClass); ?>"><?php echo h($r['status']); ?></span>
        </td>
        <td class="mono"><?php echo h($r['started_at']); ?></td>
        <td><a class="button secondary" href="run_detail.php?run_id=<?php echo h((string) $r['run_id']); ?>">View</a></td>
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
