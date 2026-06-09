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
    'SELECT d.*, p.project_id, p.workspace_id, p.project_name FROM Datasets d
     INNER JOIN Projects p ON p.project_id = d.project_id
     INNER JOIN WorkspaceMembers wm ON wm.workspace_id = p.workspace_id AND wm.user_id = ?
     WHERE d.dataset_id = ?'
);
$st->execute([$uid, $id]);
$dataset = $st->fetch();
if (!$dataset) {
    set_flash('error', 'Dataset not found or access denied.');
    redirect('projects.php');
}

$wid = (int) $dataset['workspace_id'];
$pid = (int) $dataset['project_id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = validate_dataset($_POST, false);
    if ($errors === []) {
        $name        = trim((string) $_POST['dataset_name']);
        $modality    = (string) $_POST['modality'];
        $source_type = (string) $_POST['source_type'];
        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare(
                'UPDATE Datasets SET dataset_name = ?, modality = ?, source_type = ? WHERE dataset_id = ?'
            );
            $st->execute([$name, $modality, $source_type, $id]);
            log_audit($pdo, $wid, 'update', 'dataset', $id);
            $pdo->commit();
            set_flash('success', 'Dataset updated.');
            redirect('project_detail.php?id=' . $pid);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash('error', 'Could not update dataset.');
            redirect('edit_dataset.php?id=' . $id);
        }
    }
}

$name_val        = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? trim((string) ($_POST['dataset_name'] ?? ''))
    : (string) $dataset['dataset_name'];
$modality_val    = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (string) ($_POST['modality'] ?? '')
    : (string) $dataset['modality'];
$source_type_val = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (string) ($_POST['source_type'] ?? '')
    : (string) $dataset['source_type'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LabBench - Edit Dataset</title>
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
        <div>Edit Dataset</div>
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
  <a href="project_detail.php?id=<?php echo h((string) $pid); ?>"><?php echo h($dataset['project_name']); ?></a> /
  Edit Dataset
</div>
<h1 class="page-title">Edit Dataset</h1>
<p class="page-sub">Update dataset name, modality, and source type.</p>

<div class="card">
  <form action="edit_dataset.php?id=<?php echo h((string) $id); ?>" method="post">
    <div class="form-grid single">
      <div class="form-group">
        <label for="dataset_name">Dataset Name</label>
        <input type="text" id="dataset_name" name="dataset_name" maxlength="150" required
               value="<?php echo h($name_val); ?>" />
      </div>
      <div class="form-group">
        <label for="modality">Modality</label>
        <select id="modality" name="modality" required>
          <option value="">Select modality</option>
          <?php foreach (['image', 'text', 'tabular', 'audio', 'video'] as $opt): ?>
            <option value="<?php echo h($opt); ?>"
              <?php echo $modality_val === $opt ? ' selected' : ''; ?>>
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
              <?php echo $source_type_val === $opt ? ' selected' : ''; ?>>
              <?php echo h($opt); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-actions">
      <button type="submit">Save Changes</button>
      <a class="button secondary" href="project_detail.php?id=<?php echo h((string) $pid); ?>">Cancel</a>
    </div>
  </form>
</div>

      </main>
    </div>
  </div>
</body>
</html>
