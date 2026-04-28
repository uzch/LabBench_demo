<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit_helpers.php';

require_login();
$uid = current_user_id();

$id = isset($_GET['id']) && ctype_digit((string) $_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    set_flash('error', 'Invalid model.');
    redirect('projects.php');
}

$st = $pdo->prepare(
    'SELECT m.*, p.project_id, p.workspace_id, p.project_name FROM Models m
     INNER JOIN Projects p ON p.project_id = m.project_id
     INNER JOIN WorkspaceMembers wm ON wm.workspace_id = p.workspace_id AND wm.user_id = ?
     WHERE m.model_id = ?'
);
$st->execute([$uid, $id]);
$model = $st->fetch();
if (!$model) {
    set_flash('error', 'Model not found or access denied.');
    redirect('projects.php');
}

$wid = (int) $model['workspace_id'];
$pid = (int) $model['project_id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['model_name'] ?? ''));
    if ($name === '') {
        $errors[] = 'Model name is required.';
    } elseif (mb_strlen($name) > 150) {
        $errors[] = 'Model name must be at most 150 characters.';
    }
    if ($errors === []) {
        $desc = $_POST['description'] ?? null;
        $desc = is_string($desc) && $desc !== '' ? $desc : null;
        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare(
                'UPDATE Models SET model_name = ?, description = ? WHERE model_id = ?'
            );
            $st->execute([$name, $desc, $id]);
            log_audit($pdo, $wid, 'update', 'model', $id);
            $pdo->commit();
            set_flash('success', 'Model updated.');
            redirect('project_detail.php?id=' . $pid);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash('error', 'Could not update model.');
            redirect('edit_model.php?id=' . $id);
        }
    }
}

$name_val = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? trim((string) ($_POST['model_name'] ?? ''))
    : (string) $model['model_name'];
$desc_val = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (string) ($_POST['description'] ?? '')
    : (string) ($model['description'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LabBench - Edit Model</title>
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
        <div>Edit Model</div>
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
  <a href="project_detail.php?id=<?php echo h((string) $pid); ?>"><?php echo h($model['project_name']); ?></a> /
  Edit Model
</div>
<h1 class="page-title">Edit Model</h1>
<p class="page-sub">Update model name and description.</p>

<div class="card">
  <form action="edit_model.php?id=<?php echo h((string) $id); ?>" method="post">
    <div class="form-grid single">
      <div class="form-group">
        <label for="model_name">Model Name</label>
        <input type="text" id="model_name" name="model_name" maxlength="150" required
               value="<?php echo h($name_val); ?>" />
      </div>
      <div class="form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description"
                  placeholder="Describe the model family or experiment line."><?php echo h($desc_val); ?></textarea>
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
