<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit_helpers.php';

require_login();
$uid = current_user_id();

$id = isset($_GET['id']) && ctype_digit((string) $_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    set_flash('error', 'Invalid dataset version.');
    redirect('projects.php');
}

$st = $pdo->prepare(
    'SELECT dv.*, d.dataset_id, d.dataset_name, p.project_id, p.project_name, p.workspace_id FROM DatasetVersions dv
     INNER JOIN Datasets d ON d.dataset_id = dv.dataset_id
     INNER JOIN Projects p ON p.project_id = d.project_id
     INNER JOIN WorkspaceMembers wm ON wm.workspace_id = p.workspace_id AND wm.user_id = ?
     WHERE dv.dataset_version_id = ?'
);
$st->execute([$uid, $id]);
$version = $st->fetch();
if (!$version) {
    set_flash('error', 'Dataset version not found or access denied.');
    redirect('projects.php');
}

$did = (int) $version['dataset_id'];
$wid = (int) $version['workspace_id'];
$pid = (int) $version['project_id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = validate_dataset_version($_POST, false);
    if ($errors === []) {
        $version_tag = trim((string) $_POST['version_tag']);
        $row_count   = isset($_POST['row_count']) && $_POST['row_count'] !== '' ? (int) $_POST['row_count'] : 0;
        $schema_hash = trim((string) ($_POST['schema_hash'] ?? ''));
        $schema_hash = $schema_hash !== '' ? $schema_hash : null;
        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare(
                'UPDATE DatasetVersions SET version_tag = ?, row_count = ?, schema_hash = ? WHERE dataset_version_id = ?'
            );
            $st->execute([$version_tag, $row_count, $schema_hash, $id]);
            log_audit($pdo, $wid, 'update', 'dataset_version', $id);
            $pdo->commit();
            set_flash('success', 'Dataset version updated.');
            redirect('dataset_detail.php?id=' . $did);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash('error', 'Could not update dataset version.');
            redirect('edit_dataset_version.php?id=' . $id);
        }
    }
}

$version_tag_val  = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? trim((string) ($_POST['version_tag'] ?? ''))
    : (string) $version['version_tag'];
$row_count_val    = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (string) ($_POST['row_count'] ?? '0')
    : (string) $version['row_count'];
$schema_hash_val  = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (string) ($_POST['schema_hash'] ?? '')
    : (string) ($version['schema_hash'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LabBench - Edit Dataset Version</title>
  <link rel="stylesheet" href="styles.css" />
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
        <div>Edit Dataset Version</div>
        <div class="header-right">Signed in as <?php echo h(current_user_name()); ?></div>
      </header>
      <main class="content">

<?php if ($errors !== []): ?>
<div class="card" style="border:1px solid #9b2c2c;background:rgba(239,68,68,0.12);margin-bottom:18px;">
  <div class="card-title">Please fix the following</div>
  <ul style="margin:0;padding-left:18px;">
    <?php foreach ($errors as $e): ?>
      <li><?php echo h($e); ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="breadcrumb">
  <a href="projects.php">Projects</a> /
  <a href="project_detail.php?id=<?php echo h((string) $pid); ?>"><?php echo h($version['project_name']); ?></a> /
  <a href="dataset_detail.php?id=<?php echo h((string) $did); ?>"><?php echo h($version['dataset_name']); ?></a> /
  Edit Version
</div>
<h1 class="page-title">Edit Dataset Version</h1>
<p class="page-sub">Update version tag, row count, and schema hash.</p>

<div class="card">
  <form action="edit_dataset_version.php?id=<?php echo h((string) $id); ?>" method="post">
    <div class="form-grid single">
      <div class="form-group">
        <label for="version_tag">Version Tag</label>
        <input type="text" id="version_tag" name="version_tag" maxlength="50" required
               value="<?php echo h($version_tag_val); ?>" />
      </div>
      <div class="form-group">
        <label for="row_count">Row Count</label>
        <input type="number" id="row_count" name="row_count" min="0"
               value="<?php echo h($row_count_val); ?>" />
      </div>
      <div class="form-group">
        <label for="schema_hash">Schema Hash <span class="muted">(optional)</span></label>
        <input type="text" id="schema_hash" name="schema_hash" maxlength="64"
               value="<?php echo h($schema_hash_val); ?>" />
      </div>
    </div>
    <div class="form-actions">
      <button type="submit">Save Changes</button>
      <a class="button secondary" href="dataset_detail.php?id=<?php echo h((string) $did); ?>">Cancel</a>
    </div>
  </form>
</div>

      </main>
    </div>
  </div>
</body>
</html>
