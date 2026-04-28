<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit_helpers.php';

require_login();
$uid = current_user_id();

$st = $pdo->prepare(
    'SELECT p.project_id, p.project_name, w.workspace_name
     FROM Projects p
     INNER JOIN Workspaces w ON w.workspace_id = p.workspace_id
     INNER JOIN WorkspaceMembers wm ON wm.workspace_id = p.workspace_id AND wm.user_id = ?
     ORDER BY p.project_name'
);
$st->execute([$uid]);
$projects = $st->fetchAll();

$preselect_pid = isset($_GET['project_id']) && ctype_digit((string) $_GET['project_id'])
    ? (int) $_GET['project_id']
    : 0;

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = validate_dataset($_POST);
    $pid = isset($_POST['project_id']) && ctype_digit((string) $_POST['project_id'])
        ? (int) $_POST['project_id']
        : 0;

    $wid = 0;
    if ($errors === [] && $pid > 0) {
        $st = $pdo->prepare(
            'SELECT p.workspace_id FROM Projects p
             INNER JOIN WorkspaceMembers wm ON wm.workspace_id = p.workspace_id AND wm.user_id = ?
             WHERE p.project_id = ?'
        );
        $st->execute([$uid, $pid]);
        $row = $st->fetch();
        if ($row === false) {
            $errors[] = 'You do not have access to that project.';
        } else {
            $wid = (int) $row['workspace_id'];
        }
    }

    if ($errors === []) {
        $dataset_name = trim((string) $_POST['dataset_name']);
        $modality     = (string) $_POST['modality'];
        $source_type  = (string) $_POST['source_type'];
        $version_tag  = trim((string) $_POST['version_tag']);
        $row_count    = isset($_POST['row_count']) && $_POST['row_count'] !== '' ? (int) $_POST['row_count'] : 0;
        $schema_hash  = trim((string) ($_POST['schema_hash'] ?? ''));
        $schema_hash  = $schema_hash !== '' ? $schema_hash : null;

        try {
            $pdo->beginTransaction();

            $st = $pdo->prepare(
                'INSERT INTO Datasets (project_id, dataset_name, modality, source_type)
                 VALUES (?, ?, ?, ?)'
            );
            $st->execute([$pid, $dataset_name, $modality, $source_type]);
            $dataset_id = (int) $pdo->lastInsertId();

            $st = $pdo->prepare(
                'INSERT INTO DatasetVersions (dataset_id, version_tag, row_count, schema_hash, created_at)
                 VALUES (?, ?, ?, ?, NOW())'
            );
            $st->execute([$dataset_id, $version_tag, $row_count, $schema_hash]);

            log_audit($pdo, $wid, 'create', 'dataset', $dataset_id);
            $pdo->commit();
            set_flash('success', 'Dataset created.');
            redirect('project_detail.php?id=' . $pid);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash('error', 'Could not create dataset. Please try again.');
            redirect('create_dataset.php' . ($preselect_pid > 0 ? '?project_id=' . $preselect_pid : ''));
        }
    }
}

$selected_pid = (int) ($_POST['project_id'] ?? $preselect_pid);
$cancel_href  = $selected_pid > 0 ? 'project_detail.php?id=' . $selected_pid : 'projects.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LabBench - Create Dataset</title>
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
        <div>Create Dataset</div>
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

<div class="breadcrumb"><a href="datasets.php">Datasets</a> / Create Dataset</div>
<h1 class="page-title">Create Dataset</h1>
<p class="page-sub">Input form for Datasets and the initial DatasetVersions entry.</p>

<div class="card">
  <form action="create_dataset.php" method="post">
    <div class="form-grid single">
      <div class="form-group">
        <label for="project_id">Project</label>
        <select id="project_id" name="project_id" required>
          <option value="">Select a project</option>
          <?php foreach ($projects as $p): ?>
            <option value="<?php echo h((string) $p['project_id']); ?>"
              <?php echo $selected_pid === (int) $p['project_id'] ? ' selected' : ''; ?>>
              <?php echo h($p['project_name']); ?> &mdash; <?php echo h($p['workspace_name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="card-title" style="margin-top:18px;">Dataset</div>
    <div class="form-grid">
      <div class="form-group">
        <label for="dataset_name">Dataset Name</label>
        <input type="text" id="dataset_name" name="dataset_name" maxlength="150" required
               value="<?php echo h((string) ($_POST['dataset_name'] ?? '')); ?>" />
      </div>
      <div class="form-group">
        <label for="modality">Modality</label>
        <select id="modality" name="modality" required>
          <option value="">Select modality</option>
          <?php foreach (['image', 'text', 'tabular', 'audio', 'video'] as $opt): ?>
            <option value="<?php echo h($opt); ?>"
              <?php echo (($_POST['modality'] ?? '') === $opt) ? ' selected' : ''; ?>>
              <?php echo h($opt); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="source_type">Source Type</label>
        <select id="source_type" name="source_type" required>
          <option value="">Select source type</option>
          <?php foreach (['public', 'internal', 'synthetic'] as $opt): ?>
            <option value="<?php echo h($opt); ?>"
              <?php echo (($_POST['source_type'] ?? '') === $opt) ? ' selected' : ''; ?>>
              <?php echo h($opt); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="card-title" style="margin-top:18px;">Initial Version</div>
    <div class="form-grid">
      <div class="form-group">
        <label for="version_tag">Version Tag</label>
        <input type="text" id="version_tag" name="version_tag" maxlength="50" placeholder="v1.0" required
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
      <button type="submit">Create Dataset</button>
      <a class="button secondary" href="<?php echo h($cancel_href); ?>">Cancel</a>
    </div>
  </form>
</div>

      </main>
    </div>
  </div>
</body>
</html>
