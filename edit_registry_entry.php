<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit_helpers.php';

require_login();
$uid = current_user_id();

$id = isset($_GET['id']) && ctype_digit((string) $_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    set_flash('error', 'Invalid registry entry.');
    redirect('model_registry.php');
}

$st = $pdo->prepare(
    'SELECT mr.model_version_id, mr.source_run_id, mr.stage, mr.approved_at,
            m.model_name, p.project_name, p.workspace_id,
            u.full_name AS approved_by_name
     FROM ModelRegistry mr
     INNER JOIN Models m ON m.model_id = mr.model_id
     INNER JOIN Projects p ON p.project_id = m.project_id
     INNER JOIN WorkspaceMembers wm ON wm.workspace_id = p.workspace_id AND wm.user_id = ?
     INNER JOIN Users u ON u.user_id = mr.approved_by_user_id
     WHERE mr.model_version_id = ?'
);
$st->execute([$uid, $id]);
$entry = $st->fetch();
if (!$entry) {
    set_flash('error', 'Registry entry not found or access denied.');
    redirect('model_registry.php');
}

$wid = (int) $entry['workspace_id'];
$allowed_stages = ['staging', 'production', 'archived'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stage = (string) ($_POST['stage'] ?? '');
    if (!in_array($stage, $allowed_stages, true)) {
        $errors[] = 'Stage must be staging, production, or archived.';
    }
    if ($errors === []) {
        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare('UPDATE ModelRegistry SET stage = ? WHERE model_version_id = ?');
            $st->execute([$stage, $id]);
            log_audit($pdo, $wid, 'update', 'model_registry', $id);
            $pdo->commit();
            set_flash('success', 'Registry entry updated.');
            redirect('model_registry.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash('error', 'Could not update registry entry.');
            redirect('edit_registry_entry.php?id=' . $id);
        }
    }
}

$stage_val = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? (string) ($_POST['stage'] ?? '')
    : (string) $entry['stage'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LabBench - Edit Registry Entry</title>
  <link rel="stylesheet" href="styles.css" />
</head>
<body>
  <div class="layout">
    <aside class="sidebar">
      <div class="logo">LABBENCH</div>
      <nav class="nav">
        <?php render_sidebar('model_registry'); ?>
      </nav>
    </aside>
    <div class="main">
      <header class="header">
        <div>Edit Registry Entry</div>
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

<div class="breadcrumb"><a href="model_registry.php">Model Registry</a> / Edit Entry</div>
<h1 class="page-title">Edit Registry Entry</h1>
<p class="page-sub">Update the deployment stage for this approved model version.</p>

<div class="card">
  <div class="form-grid single" style="margin-bottom:18px;">
    <div class="form-group">
      <label>Model</label>
      <input type="text" value="<?php echo h($entry['model_name']); ?> &mdash; <?php echo h($entry['project_name']); ?>" disabled />
    </div>
    <div class="form-group">
      <label>Source Run</label>
      <input type="text" value="Run #<?php echo h((string) $entry['source_run_id']); ?>" disabled />
    </div>
    <div class="form-group">
      <label>Approved By</label>
      <input type="text" value="<?php echo h($entry['approved_by_name']); ?>" disabled />
    </div>
    <div class="form-group">
      <label>Approved At</label>
      <input type="text" value="<?php echo h($entry['approved_at']); ?>" disabled />
    </div>
  </div>
  <form action="edit_registry_entry.php?id=<?php echo h((string) $id); ?>" method="post">
    <div class="form-grid single">
      <div class="form-group">
        <label for="stage">Stage</label>
        <select id="stage" name="stage" required>
          <?php foreach (['staging', 'production', 'archived'] as $s): ?>
            <option value="<?php echo h($s); ?>"<?php echo $stage_val === $s ? ' selected' : ''; ?>>
              <?php echo h(ucfirst($s)); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-actions">
      <button type="submit">Save Changes</button>
      <a class="button secondary" href="model_registry.php">Cancel</a>
    </div>
  </form>
</div>

      </main>
    </div>
  </div>
</body>
</html>
