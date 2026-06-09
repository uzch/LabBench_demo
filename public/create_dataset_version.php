<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/audit_helpers.php';

require_login();
$uid = current_user_id();

$st = $pdo->prepare(
    'SELECT d.dataset_id, d.dataset_name, p.project_id, p.project_name, w.workspace_name
     FROM Datasets d
     INNER JOIN Projects p ON p.project_id = d.project_id
     INNER JOIN Workspaces w ON w.workspace_id = p.workspace_id
     INNER JOIN WorkspaceMembers wm ON wm.workspace_id = p.workspace_id AND wm.user_id = ?
     ORDER BY p.project_name, d.dataset_name'
);
$st->execute([$uid]);
$datasets = $st->fetchAll();

$preselect_did = isset($_GET['dataset_id']) && ctype_digit((string) $_GET['dataset_id'])
    ? (int) $_GET['dataset_id']
    : 0;

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = validate_dataset_version($_POST);
    $did = isset($_POST['dataset_id']) && ctype_digit((string) $_POST['dataset_id'])
        ? (int) $_POST['dataset_id']
        : 0;

    $wid = 0;
    $pid = 0;
    if ($errors === [] && $did > 0) {
        $st = $pdo->prepare(
            'SELECT p.project_id, p.workspace_id FROM Datasets d
             INNER JOIN Projects p ON p.project_id = d.project_id
             INNER JOIN WorkspaceMembers wm ON wm.workspace_id = p.workspace_id AND wm.user_id = ?
             WHERE d.dataset_id = ?'
        );
        $st->execute([$uid, $did]);
        $row = $st->fetch();
        if ($row === false) {
            $errors[] = 'You do not have access to that dataset.';
        } else {
            $pid = (int) $row['project_id'];
            $wid = (int) $row['workspace_id'];
        }
    }

    if ($errors === []) {
        $version_tag = trim((string) $_POST['version_tag']);
        $row_count   = isset($_POST['row_count']) && $_POST['row_count'] !== '' ? (int) $_POST['row_count'] : 0;
        $schema_hash = trim((string) ($_POST['schema_hash'] ?? ''));
        $schema_hash = $schema_hash !== '' ? $schema_hash : null;

        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare(
                'INSERT INTO DatasetVersions (dataset_id, version_tag, row_count, schema_hash, created_at)
                 VALUES (?, ?, ?, ?, NOW())'
            );
            $st->execute([$did, $version_tag, $row_count, $schema_hash]);
            $new_id = (int) $pdo->lastInsertId();
            log_audit($pdo, $wid, 'create', 'dataset_version', $new_id);
            $pdo->commit();
            set_flash('success', 'Dataset version added.');
            redirect('project_detail.php?id=' . $pid);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash('error', 'Could not add dataset version. Please try again.');
            redirect('create_dataset_version.php' . ($preselect_did > 0 ? '?dataset_id=' . $preselect_did : ''));
        }
    }
}

$selected_did = (int) ($_POST['dataset_id'] ?? $preselect_did);
$cancel_href  = 'projects.php';
foreach ($datasets as $d) {
    if ((int) $d['dataset_id'] === $selected_did) {
        $cancel_href = 'project_detail.php?id=' . $d['project_id'];
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LabBench - Add Dataset Version</title>
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
        <div>Add Dataset Version</div>
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

<div class="breadcrumb"><a href="datasets.php">Datasets</a> / Add Version</div>
<h1 class="page-title">Add Dataset Version</h1>
<p class="page-sub">Input form for a new DatasetVersions entry on an existing dataset.</p>

<div class="card">
  <form action="create_dataset_version.php" method="post">
    <div class="form-grid single">
      <div class="form-group">
        <label for="dataset_id">Dataset</label>
        <select id="dataset_id" name="dataset_id" required>
          <option value="">Select a dataset</option>
          <?php foreach ($datasets as $d): ?>
            <option value="<?php echo h((string) $d['dataset_id']); ?>"
              <?php echo $selected_did === (int) $d['dataset_id'] ? ' selected' : ''; ?>>
              <?php echo h($d['dataset_name']); ?> &mdash; <?php echo h($d['project_name']); ?> (<?php echo h($d['workspace_name']); ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-grid">
      <div class="form-group">
        <label for="version_tag">Version Tag</label>
        <input type="text" id="version_tag" name="version_tag" maxlength="50" placeholder="v2.0" required
               value="<?php echo h((string) ($_POST['version_tag'] ?? '')); ?>" />
      </div>
      <div class="form-group">
        <label for="row_count">Row Count</label>
        <input type="number" id="row_count" name="row_count" min="0"
               value="<?php echo h((string) ($_POST['row_count'] ?? '0')); ?>" />
      </div>
      <div class="form-group">
        <label for="schema_hash">Schema Hash <span class="muted">(optional)</span></label>
        <input type="text" id="schema_hash" name="schema_hash" maxlength="64"
               value="<?php echo h((string) ($_POST['schema_hash'] ?? '')); ?>" />
      </div>
    </div>

    <div class="form-actions">
      <button type="submit">Add Version</button>
      <a class="button secondary" href="<?php echo h($cancel_href); ?>">Cancel</a>
    </div>
  </form>
</div>

      </main>
    </div>
  </div>
</body>
</html>
