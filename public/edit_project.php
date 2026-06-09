<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/audit_helpers.php';

require_login();
$uid = current_user_id();

$id = isset($_GET['id']) && ctype_digit((string) $_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    set_flash('error', 'Invalid project.');
    redirect('projects.php');
}

$st = $pdo->prepare(
    'SELECT p.* FROM Projects p
     INNER JOIN WorkspaceMembers wm ON wm.workspace_id = p.workspace_id AND wm.user_id = ?
     WHERE p.project_id = ?'
);
$st->execute([$uid, $id]);
$project = $st->fetch();
if (!$project) {
    set_flash('error', 'Project not found or access denied.');
    redirect('projects.php');
}

$wid = (int) $project['workspace_id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = validate_project($_POST, false);
    if ($errors === []) {
        $name = trim((string) $_POST['project_name']);
        $desc = $_POST['description'] ?? null;
        $desc = is_string($desc) && $desc !== '' ? $desc : null;
        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare(
                'UPDATE Projects SET project_name = ?, description = ? WHERE project_id = ? AND workspace_id = ?'
            );
            $st->execute([$name, $desc, $id, $wid]);
            log_audit($pdo, $wid, 'update', 'project', $id);
            $pdo->commit();
            set_flash('success', 'Project updated.');
            redirect('project_detail.php?id=' . $id);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash('error', 'Could not update project.');
            redirect('edit_project.php?id=' . $id);
        }
    }
}

$name_val = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? trim((string) ($_POST['project_name'] ?? ''))
    : (string) $project['project_name'];
$desc_val = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (string) ($_POST['description'] ?? '')
    : (string) ($project['description'] ?? '');

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LabBench - Edit Project</title>
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
        <div>Edit Project</div>
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

<div class="breadcrumb"><a href="projects.php">Projects</a> / <a href="project_detail.php?id=<?php echo h((string) $id); ?>"><?php echo h($project['project_name']); ?></a> / Edit</div>
<h1 class="page-title">Edit Project</h1>
<p class="page-sub">Update project name and description.</p>

<div class="card">
  <form action="edit_project.php?id=<?php echo h((string) $id); ?>" method="post">
    <div class="form-grid single">
      <div class="form-group">
        <label for="project_name">Project Name</label>
        <input type="text" id="project_name" name="project_name" maxlength="150" required value="<?php echo h($name_val); ?>" />
      </div>
      <div class="form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description" placeholder="Describe the project goals and scope."><?php echo h($desc_val); ?></textarea>
      </div>
    </div>
    <div class="form-actions">
      <button type="submit">Save Changes</button>
      <a class="button secondary" href="project_detail.php?id=<?php echo h((string) $id); ?>">Cancel</a>
    </div>
  </form>
</div>

      </main>
    </div>
  </div>
</body>
</html>
