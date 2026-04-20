<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit_helpers.php';

require_login();
$uid = current_user_id();

$workspaces = workspaces_for_user($pdo, $uid);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = validate_project($_POST, true);
    $wid = isset($_POST['workspace_id']) && ctype_digit((string) $_POST['workspace_id'])
        ? (int) $_POST['workspace_id']
        : 0;
    if ($errors === [] && !user_in_workspace($pdo, $wid, $uid)) {
        $errors[] = 'You do not have access to that workspace.';
    }
    if ($errors === []) {
        $name = trim((string) $_POST['project_name']);
        $desc = $_POST['description'] ?? null;
        $desc = is_string($desc) && $desc !== '' ? $desc : null;
        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare(
                'INSERT INTO Projects (workspace_id, project_name, description, created_by_user_id, created_at)
                 VALUES (?, ?, ?, ?, NOW())'
            );
            $st->execute([$wid, $name, $desc, $uid]);
            $newId = (int) $pdo->lastInsertId();
            log_audit($pdo, $wid, 'create', 'project', $newId);
            $pdo->commit();
            set_flash('success', 'Project created.');
            redirect('projects.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash('error', 'Could not create project. Please try again.');
            redirect('create_project.php');
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LabBench - Create Project</title>
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
        <div>Create Project</div>
        <div class="header-right">Signed in as user #<?php echo h((string) $uid); ?></div>
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

<div class="breadcrumb"><a href="projects.php">Projects</a> / Create Project</div>
<h1 class="page-title">Create Project</h1>
<p class="page-sub">Input form for the Projects relation.</p>

<div class="card">
  <form action="create_project.php" method="post">
    <div class="form-grid single">
      <div class="form-group">
        <label for="workspace_id">Workspace</label>
        <select id="workspace_id" name="workspace_id" required>
          <option value="">Select a workspace</option>
          <?php foreach ($workspaces as $w): ?>
            <option value="<?php echo h((string) $w['workspace_id']); ?>"><?php echo h($w['workspace_name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="project_name">Project Name</label>
        <input type="text" id="project_name" name="project_name" maxlength="150" required value="<?php echo h((string) ($_POST['project_name'] ?? '')); ?>" />
      </div>
      <div class="form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description" placeholder="Describe the project goals and scope."><?php echo h((string) ($_POST['description'] ?? '')); ?></textarea>
      </div>
    </div>
    <div class="form-actions">
      <button type="submit">Create Project</button>
      <a class="button secondary" href="projects.php">Cancel</a>
    </div>
  </form>
</div>

      </main>
    </div>
  </div>
</body>
</html>
