<?php
// log_run.php
// LabBench Phase 4 — Create Runs plus optional initial RunParams/RunMetrics.

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit_helpers.php';

require_login();
$uid = current_user_id();

$allowedStatuses = ['queued', 'running', 'completed', 'failed'];
$errors = [];

$old = [
    'project_id' => trim((string) ($_GET['project_id'] ?? '')),
    'model_id' => '',
    'dataset_version_id' => '',
    'status' => 'queued',
    'started_at' => '',
    'ended_at' => '',
    'code_version_tag' => '',
    'notes' => '',
    'param_key' => '',
    'param_value' => '',
    'metric_key' => '',
    'metric_value' => '',
    'step' => '',
    'recorded_at' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($old as $key => $unused) {
        $old[$key] = trim((string) ($_POST[$key] ?? ''));
    }
}

$projectsStmt = $pdo->prepare(
    'SELECT p.project_id, p.project_name, w.workspace_name
     FROM Projects p
     INNER JOIN Workspaces w ON w.workspace_id = p.workspace_id
     INNER JOIN WorkspaceMembers wm ON wm.workspace_id = p.workspace_id AND wm.user_id = ?
     ORDER BY w.workspace_name, p.project_name'
);
$projectsStmt->execute([$uid]);
$projects = $projectsStmt->fetchAll();
$projectIds = array_map(static fn ($p) => (int) $p['project_id'], $projects);

if ($old['project_id'] !== '' && ctype_digit($old['project_id'])) {
    if (!in_array((int) $old['project_id'], $projectIds, true)) {
        $old['project_id'] = '';
    }
} else {
    $old['project_id'] = '';
}

$modelSql =
    'SELECT m.model_id, m.model_name, p.project_id, p.project_name, p.workspace_id, w.workspace_name
     FROM Models m
     INNER JOIN Projects p ON m.project_id = p.project_id
     INNER JOIN Workspaces w ON w.workspace_id = p.workspace_id
     INNER JOIN WorkspaceMembers wm ON wm.workspace_id = p.workspace_id AND wm.user_id = ?';
$modelParams = [$uid];
if ($old['project_id'] !== '') {
    $modelSql .= ' WHERE p.project_id = ?';
    $modelParams[] = (int) $old['project_id'];
}
$modelSql .= ' ORDER BY w.workspace_name, p.project_name, m.model_name';
$modelStmt = $pdo->prepare($modelSql);
$modelStmt->execute($modelParams);
$models = $modelStmt->fetchAll();

$datasetSql =
    'SELECT dv.dataset_version_id, dv.version_tag, d.dataset_name, d.project_id, p.project_name, p.workspace_id, w.workspace_name
     FROM DatasetVersions dv
     INNER JOIN Datasets d ON dv.dataset_id = d.dataset_id
     INNER JOIN Projects p ON d.project_id = p.project_id
     INNER JOIN Workspaces w ON w.workspace_id = p.workspace_id
     INNER JOIN WorkspaceMembers wm ON wm.workspace_id = p.workspace_id AND wm.user_id = ?';
$datasetParams = [$uid];
if ($old['project_id'] !== '') {
    $datasetSql .= ' WHERE p.project_id = ?';
    $datasetParams[] = (int) $old['project_id'];
}
$datasetSql .= ' ORDER BY w.workspace_name, p.project_name, d.dataset_name, dv.dataset_version_id DESC';
$datasetStmt = $pdo->prepare($datasetSql);
$datasetStmt->execute($datasetParams);
$datasetVersions = $datasetStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($old['model_id'] === '' || !ctype_digit($old['model_id'])) {
        $errors[] = 'Model is required.';
    }
    if ($old['dataset_version_id'] === '' || !ctype_digit($old['dataset_version_id'])) {
        $errors[] = 'Dataset version is required.';
    }
    if (!in_array($old['status'], $allowedStatuses, true)) {
        $errors[] = 'Status is invalid.';
    }

    if ($old['started_at'] !== '' && strtotime($old['started_at']) === false) {
        $errors[] = 'Started At is invalid.';
    }
    if ($old['ended_at'] !== '' && strtotime($old['ended_at']) === false) {
        $errors[] = 'Ended At is invalid.';
    }
    if (
        $old['started_at'] !== '' &&
        $old['ended_at'] !== '' &&
        strtotime($old['ended_at']) < strtotime($old['started_at'])
    ) {
        $errors[] = 'Ended At cannot be earlier than Started At.';
    }

    $paramStarted = $old['param_key'] !== '' || $old['param_value'] !== '';
    if ($paramStarted && ($old['param_key'] === '' || $old['param_value'] === '')) {
        $errors[] = 'If you enter an initial hyperparameter, both parameter key and parameter value are required.';
    }

    $metricStarted = $old['metric_key'] !== '' || $old['metric_value'] !== '' || $old['step'] !== '' || $old['recorded_at'] !== '';
    if ($metricStarted) {
        if ($old['metric_key'] === '' || $old['metric_value'] === '' || $old['step'] === '') {
            $errors[] = 'If you enter an initial metric, metric key, metric value, and step are required.';
        }
        if ($old['metric_value'] !== '' && !is_numeric($old['metric_value'])) {
            $errors[] = 'Metric value must be numeric.';
        }
        if ($old['step'] !== '' && (!ctype_digit($old['step']) || (int) $old['step'] < 0)) {
            $errors[] = 'Metric step must be a nonnegative integer.';
        }
        if ($old['recorded_at'] !== '' && strtotime($old['recorded_at']) === false) {
            $errors[] = 'Recorded At is invalid.';
        }
    }

    $modelContext = null;
    $datasetContext = null;
    if ($errors === []) {
        $st = $pdo->prepare(
            'SELECT m.model_id, m.project_id, p.workspace_id
             FROM Models m
             INNER JOIN Projects p ON p.project_id = m.project_id
             INNER JOIN WorkspaceMembers wm ON wm.workspace_id = p.workspace_id AND wm.user_id = ?
             WHERE m.model_id = ?'
        );
        $st->execute([$uid, (int) $old['model_id']]);
        $modelContext = $st->fetch();
        if (!$modelContext) {
            $errors[] = 'Selected model is not available to the logged-in user.';
        }

        $st = $pdo->prepare(
            'SELECT dv.dataset_version_id, d.project_id, p.workspace_id
             FROM DatasetVersions dv
             INNER JOIN Datasets d ON d.dataset_id = dv.dataset_id
             INNER JOIN Projects p ON p.project_id = d.project_id
             INNER JOIN WorkspaceMembers wm ON wm.workspace_id = p.workspace_id AND wm.user_id = ?
             WHERE dv.dataset_version_id = ?'
        );
        $st->execute([$uid, (int) $old['dataset_version_id']]);
        $datasetContext = $st->fetch();
        if (!$datasetContext) {
            $errors[] = 'Selected dataset version is not available to the logged-in user.';
        }

        if ($modelContext && $datasetContext && (int) $modelContext['project_id'] !== (int) $datasetContext['project_id']) {
            $errors[] = 'The selected model and dataset version must belong to the same project.';
        }
    }

    if ($errors === []) {
        try {
            $pdo->beginTransaction();

            if ($old['started_at'] !== '') {
                $runStmt = $pdo->prepare(
                    'INSERT INTO Runs
                        (model_id, dataset_version_id, created_by_user_id, started_at, ended_at, status, code_version_tag, notes)
                     VALUES
                        (:model_id, :dataset_version_id, :created_by_user_id, :started_at, :ended_at, :status, :code_version_tag, :notes)'
                );
                $runStmt->execute([
                    ':model_id' => (int) $old['model_id'],
                    ':dataset_version_id' => (int) $old['dataset_version_id'],
                    ':created_by_user_id' => $uid,
                    ':started_at' => date('Y-m-d H:i:s', strtotime($old['started_at'])),
                    ':ended_at' => $old['ended_at'] !== '' ? date('Y-m-d H:i:s', strtotime($old['ended_at'])) : null,
                    ':status' => $old['status'],
                    ':code_version_tag' => $old['code_version_tag'] !== '' ? $old['code_version_tag'] : null,
                    ':notes' => $old['notes'] !== '' ? $old['notes'] : null,
                ]);
            } else {
                $runStmt = $pdo->prepare(
                    'INSERT INTO Runs
                        (model_id, dataset_version_id, created_by_user_id, ended_at, status, code_version_tag, notes)
                     VALUES
                        (:model_id, :dataset_version_id, :created_by_user_id, :ended_at, :status, :code_version_tag, :notes)'
                );
                $runStmt->execute([
                    ':model_id' => (int) $old['model_id'],
                    ':dataset_version_id' => (int) $old['dataset_version_id'],
                    ':created_by_user_id' => $uid,
                    ':ended_at' => $old['ended_at'] !== '' ? date('Y-m-d H:i:s', strtotime($old['ended_at'])) : null,
                    ':status' => $old['status'],
                    ':code_version_tag' => $old['code_version_tag'] !== '' ? $old['code_version_tag'] : null,
                    ':notes' => $old['notes'] !== '' ? $old['notes'] : null,
                ]);
            }

            $runId = (int) $pdo->lastInsertId();

            if ($old['param_key'] !== '' && $old['param_value'] !== '') {
                $paramStmt = $pdo->prepare(
                    'INSERT INTO RunParams (run_id, param_key, param_value)
                     VALUES (:run_id, :param_key, :param_value)'
                );
                $paramStmt->execute([
                    ':run_id' => $runId,
                    ':param_key' => $old['param_key'],
                    ':param_value' => $old['param_value'],
                ]);
            }

            if ($old['metric_key'] !== '' && $old['metric_value'] !== '' && $old['step'] !== '') {
                if ($old['recorded_at'] !== '') {
                    $metricStmt = $pdo->prepare(
                        'INSERT INTO RunMetrics (run_id, metric_key, metric_value, step, recorded_at)
                         VALUES (:run_id, :metric_key, :metric_value, :step, :recorded_at)'
                    );
                    $metricStmt->execute([
                        ':run_id' => $runId,
                        ':metric_key' => $old['metric_key'],
                        ':metric_value' => $old['metric_value'],
                        ':step' => (int) $old['step'],
                        ':recorded_at' => date('Y-m-d H:i:s', strtotime($old['recorded_at'])),
                    ]);
                } else {
                    $metricStmt = $pdo->prepare(
                        'INSERT INTO RunMetrics (run_id, metric_key, metric_value, step)
                         VALUES (:run_id, :metric_key, :metric_value, :step)'
                    );
                    $metricStmt->execute([
                        ':run_id' => $runId,
                        ':metric_key' => $old['metric_key'],
                        ':metric_value' => $old['metric_value'],
                        ':step' => (int) $old['step'],
                    ]);
                }
            }

            log_audit($pdo, (int) $modelContext['workspace_id'], 'create', 'run', $runId);

            $pdo->commit();
            redirect('run_detail.php?run_id=' . $runId);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Unable to save run: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LabBench - Log Run</title>
  <link rel="stylesheet" href="styles.css" />
</head>
<body>
  <div class="layout">
    <aside class="sidebar">
      <div class="logo">LABBENCH</div>
      <nav class="nav">
        <?php render_sidebar('runs'); ?>
      </nav>
    </aside>

    <div class="main">
      <header class="header">
        <div>Log Run</div>
        <div class="header-right">Created by <?php echo h(current_user_name()); ?></div>
      </header>

      <main class="content">
        <div class="breadcrumb"><a href="runs.php">Runs</a> / Log Run</div>
        <h1 class="page-title">Log Run</h1>
        <p class="page-sub">Create a run with an optional initial parameter and optional initial metric.</p>

        <?php if ($errors !== []): ?>
          <div class="card" style="border-color:#7f1d1d;">
            <div class="card-title">Please fix the following</div>
            <ul>
              <?php foreach ($errors as $error): ?>
                <li><?php echo h($error); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <div class="card">
          <form action="log_run.php" method="post">
            <div class="form-grid">
              <div class="form-group">
                <label for="project_id">Project Filter</label>
                <select id="project_id" name="project_id" onchange="window.location='log_run.php?project_id=' + encodeURIComponent(this.value)">
                  <option value="">All Projects</option>
                  <?php foreach ($projects as $project): ?>
                    <option value="<?php echo h((string) $project['project_id']); ?>"<?php echo $old['project_id'] === (string) $project['project_id'] ? ' selected' : ''; ?>>
                      <?php echo h($project['workspace_name'] . ' — ' . $project['project_name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label for="model_id">Model</label>
                <select id="model_id" name="model_id" required>
                  <option value="">Select a model</option>
                  <?php foreach ($models as $model): ?>
                    <option value="<?php echo h((string) $model['model_id']); ?>"<?php echo $old['model_id'] === (string) $model['model_id'] ? ' selected' : ''; ?>>
                      <?php echo h($model['project_name'] . ' — ' . $model['model_name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label for="dataset_version_id">Dataset Version</label>
                <select id="dataset_version_id" name="dataset_version_id" required>
                  <option value="">Select a dataset version</option>
                  <?php foreach ($datasetVersions as $version): ?>
                    <option value="<?php echo h((string) $version['dataset_version_id']); ?>"<?php echo $old['dataset_version_id'] === (string) $version['dataset_version_id'] ? ' selected' : ''; ?>>
                      <?php echo h($version['project_name'] . ' — ' . $version['dataset_name'] . ' — ' . $version['version_tag']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status" required>
                  <?php foreach ($allowedStatuses as $status): ?>
                    <option value="<?php echo h($status); ?>"<?php echo $old['status'] === $status ? ' selected' : ''; ?>>
                      <?php echo h($status); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label for="started_at">Started At</label>
                <input type="datetime-local" id="started_at" name="started_at" value="<?php echo h($old['started_at']); ?>" />
              </div>

              <div class="form-group">
                <label for="ended_at">Ended At</label>
                <input type="datetime-local" id="ended_at" name="ended_at" value="<?php echo h($old['ended_at']); ?>" />
              </div>

              <div class="form-group">
                <label for="code_version_tag">Code Version Tag</label>
                <input type="text" id="code_version_tag" name="code_version_tag" maxlength="100" value="<?php echo h($old['code_version_tag']); ?>" />
              </div>

              <div class="form-group">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes"><?php echo h($old['notes']); ?></textarea>
              </div>
            </div>

            <div class="card" style="margin-top:18px;">
              <div class="card-title">Initial Hyperparameter (Optional)</div>
              <div class="form-grid">
                <div class="form-group">
                  <label for="param_key">Parameter Key</label>
                  <input type="text" id="param_key" name="param_key" maxlength="100" value="<?php echo h($old['param_key']); ?>" placeholder="learning_rate" />
                </div>

                <div class="form-group">
                  <label for="param_value">Parameter Value</label>
                  <input type="text" id="param_value" name="param_value" maxlength="100" value="<?php echo h($old['param_value']); ?>" placeholder="0.001" />
                </div>
              </div>
            </div>

            <div class="card" style="margin-top:18px;">
              <div class="card-title">Initial Metric (Optional)</div>
              <div class="form-grid">
                <div class="form-group">
                  <label for="metric_key">Metric Key</label>
                  <input type="text" id="metric_key" name="metric_key" maxlength="100" value="<?php echo h($old['metric_key']); ?>" placeholder="accuracy" />
                </div>

                <div class="form-group">
                  <label for="metric_value">Metric Value</label>
                  <input type="number" step="any" id="metric_value" name="metric_value" value="<?php echo h($old['metric_value']); ?>" placeholder="0.91" />
                </div>

                <div class="form-group">
                  <label for="step">Step</label>
                  <input type="number" id="step" name="step" min="0" value="<?php echo h($old['step']); ?>" placeholder="1" />
                </div>

                <div class="form-group">
                  <label for="recorded_at">Recorded At</label>
                  <input type="datetime-local" id="recorded_at" name="recorded_at" value="<?php echo h($old['recorded_at']); ?>" />
                </div>
              </div>
            </div>

            <div class="form-actions">
              <button type="submit">Save Run</button>
              <a class="button secondary" href="runs.php">Cancel</a>
            </div>
          </form>
        </div>
      </main>
    </div>
  </div>
</body>
</html>
