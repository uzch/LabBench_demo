<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/audit_helpers.php';

require_login();
$uid = current_user_id();

$id = isset($_GET['id']) && ctype_digit((string) $_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    set_flash('error', 'Invalid dataset.');
    redirect('projects.php');
}

$st = $pdo->prepare(
    'SELECT d.*, p.project_id, p.project_name, p.workspace_id, w.workspace_name FROM Datasets d
     INNER JOIN Projects p ON p.project_id = d.project_id
     INNER JOIN Workspaces w ON w.workspace_id = p.workspace_id
     INNER JOIN WorkspaceMembers wm ON wm.workspace_id = p.workspace_id AND wm.user_id = ?
     WHERE d.dataset_id = ?'
);
$st->execute([$uid, $id]);
$dataset = $st->fetch();
if (!$dataset) {
    set_flash('error', 'Dataset not found or access denied.');
    redirect('projects.php');
}

$pid = (int) $dataset['project_id'];

$st = $pdo->prepare(
    'SELECT dataset_version_id, version_tag, row_count, schema_hash, created_at
     FROM DatasetVersions
     WHERE dataset_id = ?
     ORDER BY created_at DESC'
);
$st->execute([$id]);
$versions = $st->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LabBench - <?php echo h($dataset['dataset_name']); ?></title>
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
        <div>Dataset Detail</div>
        <div class="header-right"><?php echo h($dataset['workspace_name']); ?></div>
      </header>
      <main class="content">
        <?php show_flash(); ?>

<div class="breadcrumb">
  <a href="projects.php">Projects</a> /
  <a href="project_detail.php?id=<?php echo h((string) $pid); ?>"><?php echo h($dataset['project_name']); ?></a> /
  <?php echo h($dataset['dataset_name']); ?>
</div>
<h1 class="page-title"><?php echo h($dataset['dataset_name']); ?></h1>
<p class="page-sub">
  <span class="badge badge-blue"><?php echo h($dataset['modality']); ?></span>
  &nbsp;<?php echo h($dataset['source_type']); ?>
  &nbsp;&mdash; <?php echo h($dataset['project_name']); ?>
</p>

<div class="actions">
  <a class="button secondary" href="edit_dataset.php?id=<?php echo h((string) $id); ?>">Edit Dataset</a>
  <a class="button" href="create_dataset_version.php?dataset_id=<?php echo h((string) $id); ?>">+ Add Version</a>
</div>

<div class="card">
  <div class="card-title">Versions</div>
  <table>
    <thead>
      <tr>
        <th>Version Tag</th>
        <th>Row Count</th>
        <th>Schema Hash</th>
        <th>Created</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if ($versions === []): ?>
      <tr><td colspan="5" class="placeholder">No versions yet.</td></tr>
      <?php else: ?>
        <?php foreach ($versions as $v): ?>
      <tr>
        <td class="mono"><?php echo h($v['version_tag']); ?></td>
        <td class="mono"><?php echo h((string) $v['row_count']); ?></td>
        <td class="mono"><?php echo $v['schema_hash'] !== null && $v['schema_hash'] !== ''
            ? h($v['schema_hash'])
            : '<span class="placeholder">—</span>'; ?></td>
        <td class="mono"><?php echo h($v['created_at']); ?></td>
        <td style="white-space:nowrap;">
          <a class="button secondary" href="edit_dataset_version.php?id=<?php echo h((string) $v['dataset_version_id']); ?>">Edit</a>
          <form method="post" action="delete_dataset_version.php" style="display:inline;"
                onsubmit="return confirm('Delete this version and all its runs?');">
            <input type="hidden" name="dataset_version_id" value="<?php echo h((string) $v['dataset_version_id']); ?>" />
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
