<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit_helpers.php';

require_login();
$uid = current_user_id();

// All models accessible to the user
$st = $pdo->prepare(
    'SELECT m.model_id, m.model_name, p.project_name, p.workspace_id
     FROM Models m
     INNER JOIN Projects p ON p.project_id = m.project_id
     INNER JOIN WorkspaceMembers wm ON wm.workspace_id = p.workspace_id AND wm.user_id = ?
     ORDER BY m.model_name'
);
$st->execute([$uid]);
$models = $st->fetchAll();

// All runs for those models (used by JS to filter run dropdown)
$st = $pdo->prepare(
    'SELECT r.run_id, r.model_id, r.status, r.started_at
     FROM Runs r
     INNER JOIN Models m ON m.model_id = r.model_id
     INNER JOIN Projects p ON p.project_id = m.project_id
     INNER JOIN WorkspaceMembers wm ON wm.workspace_id = p.workspace_id AND wm.user_id = ?
     ORDER BY r.started_at DESC'
);
$st->execute([$uid]);
$all_runs = $st->fetchAll();

$runs_for_js = array_map(fn($r) => [
    'run_id'     => (int) $r['run_id'],
    'model_id'   => (int) $r['model_id'],
    'status'     => $r['status'],
    'started_at' => $r['started_at'],
], $all_runs);

$allowed_stages = ['staging', 'production', 'archived'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $model_id = isset($_POST['model_id']) && ctype_digit((string) $_POST['model_id'])
        ? (int) $_POST['model_id'] : 0;
    $run_id = isset($_POST['source_run_id']) && ctype_digit((string) $_POST['source_run_id'])
        ? (int) $_POST['source_run_id'] : 0;
    $stage = (string) ($_POST['stage'] ?? '');

    if ($model_id === 0) {
        $errors[] = 'Please select a model.';
    }
    if ($run_id === 0) {
        $errors[] = 'Please select a source run.';
    }
    if (!in_array($stage, $allowed_stages, true)) {
        $errors[] = 'Stage must be staging, production, or archived.';
    }

    // Verify run belongs to the selected model and user has access
    $wid = 0;
    if ($errors === [] && $model_id > 0 && $run_id > 0) {
        $st = $pdo->prepare(
            'SELECT p.workspace_id FROM Runs r
             INNER JOIN Models m ON m.model_id = r.model_id
             INNER JOIN Projects p ON p.project_id = m.project_id
             INNER JOIN WorkspaceMembers wm ON wm.workspace_id = p.workspace_id AND wm.user_id = ?
             WHERE r.run_id = ? AND r.model_id = ?'
        );
        $st->execute([$uid, $run_id, $model_id]);
        $row = $st->fetch();
        if (!$row) {
            $errors[] = 'That run does not belong to the selected model, or you do not have access.';
        } else {
            $wid = (int) $row['workspace_id'];
        }
    }

    if ($errors === []) {
        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare(
                'INSERT INTO ModelRegistry (model_id, source_run_id, stage, approved_by_user_id, approved_at)
                 VALUES (?, ?, ?, ?, NOW())'
            );
            $st->execute([$model_id, $run_id, $stage, $uid]);
            $new_id = (int) $pdo->lastInsertId();
            log_audit($pdo, $wid, 'create', 'model_registry', $new_id);
            $pdo->commit();
            set_flash('success', 'Registry entry created.');
            redirect('model_registry.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            set_flash('error', 'Could not create registry entry. Please try again.');
            redirect('create_registry_entry.php');
        }
    }
}

$sel_model = (int) ($_POST['model_id'] ?? 0);
$sel_run   = (int) ($_POST['source_run_id'] ?? 0);
$sel_stage = (string) ($_POST['stage'] ?? 'staging');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LabBench - Add Registry Entry</title>
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
        <div>Add Registry Entry</div>
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

<div class="breadcrumb"><a href="model_registry.php">Model Registry</a> / Add Entry</div>
<h1 class="page-title">Add Registry Entry</h1>
<p class="page-sub">Approve a model version derived from a training run.</p>

<div class="card">
  <form action="create_registry_entry.php" method="post">
    <div class="form-grid single">
      <div class="form-group">
        <label for="model_id">Model</label>
        <select id="model_id" name="model_id" required onchange="filterRuns()">
          <option value="">Select a model</option>
          <?php foreach ($models as $m): ?>
            <option value="<?php echo h((string) $m['model_id']); ?>"
              <?php echo $sel_model === (int) $m['model_id'] ? ' selected' : ''; ?>>
              <?php echo h($m['model_name']); ?> &mdash; <?php echo h($m['project_name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="source_run_id">Source Run</label>
        <select id="source_run_id" name="source_run_id" required>
          <option value="">Select a model first</option>
        </select>
      </div>
      <div class="form-group">
        <label for="stage">Stage</label>
        <select id="stage" name="stage" required>
          <?php foreach (['staging', 'production', 'archived'] as $s): ?>
            <option value="<?php echo h($s); ?>"<?php echo $sel_stage === $s ? ' selected' : ''; ?>>
              <?php echo h(ucfirst($s)); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-actions">
      <button type="submit">Add Entry</button>
      <a class="button secondary" href="model_registry.php">Cancel</a>
    </div>
  </form>
</div>

<script>
const RUNS = <?php echo json_encode($runs_for_js, JSON_HEX_TAG); ?>;
const PRE_RUN = <?php echo $sel_run; ?>;

function filterRuns() {
    const modelId = parseInt(document.getElementById('model_id').value, 10);
    const runSelect = document.getElementById('source_run_id');
    runSelect.innerHTML = '<option value="">Select a run</option>';
    if (!modelId) return;
    RUNS.filter(r => r.model_id === modelId).forEach(r => {
        const opt = document.createElement('option');
        opt.value = r.run_id;
        opt.textContent = 'Run #' + r.run_id + ' — ' + r.status + ' (' + r.started_at + ')';
        if (r.run_id === PRE_RUN) opt.selected = true;
        runSelect.appendChild(opt);
    });
}

document.addEventListener('DOMContentLoaded', filterRuns);
</script>

      </main>
    </div>
  </div>
</body>
</html>
