<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/audit_helpers.php';

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
    $errors = validate_model($_POST);
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
        $name = trim((string) $_POST['model_name']);
        $desc = $_POST['description'] ?? null;
        $desc = is_string($desc) && $desc !== '' ? $desc : null;
        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare(
                'INSERT INTO Models (project_id, model_name, description, created_at)
                 VALUES (?, ?, ?, NOW())'
            );
            $st->execute([$pid, $name, $desc]);
            $new_id = (int) $pdo->lastInsertId();
            log_audit($pdo, $wid, 'create', 'model', $new_id);
            $pdo->commit();
            set_flash('success', 'Model created.');
            redirect('project_detail.php?id=' . $pid);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash('error', 'Could not create model. Please try again.');
            redirect('create_model.php' . ($preselect_pid > 0 ? '?project_id=' . $preselect_pid : ''));
        }
    }
}

$selected_pid = (int) ($_POST['project_id'] ?? $preselect_pid);
$cancel_href = $selected_pid > 0 ? 'project_detail.php?id=' . $selected_pid : 'projects.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LabBench - Create Model</title>
  <link rel="stylesheet" href="assets/styles.css" />
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
        <div>Create Model</div>
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

<div class="breadcrumb"><a href="projects.php">Projects</a> / Create Model</div>
<h1 class="page-title">Create Model</h1>
<p class="page-sub">Input form for the Models relation.</p>

<div class="card">
  <form action="create_model.php" method="post">
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
      <div class="form-group">
        <label for="model_name">Model Name</label>
        <input type="text" id="model_name" name="model_name" maxlength="150" required
               value="<?php echo h((string) ($_POST['model_name'] ?? '')); ?>" />
      </div>
      <div class="form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description"
                  placeholder="Describe the model family or experiment line."><?php echo h((string) ($_POST['description'] ?? '')); ?></textarea>
      </div>
    </div>
    <div class="form-actions">
      <button type="submit">Create Model</button>
      <a class="button secondary" href="<?php echo h($cancel_href); ?>">Cancel</a>
    </div>
  </form>
</div>

      </main>
    </div>
  </div>
</body>
</html>
