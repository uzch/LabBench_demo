<?php
// run_detail.php
// LabBench Phase 4 — View/update/delete one Run and manage RunParams/RunMetrics.

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit_helpers.php';

require_login();
$uid = current_user_id();

$allowedStatuses = ['queued', 'running', 'completed', 'failed'];
$errors = [];
$messages = [];

$runId = $_GET['run_id'] ?? $_POST['run_id'] ?? '';
if ($runId === '' || !ctype_digit((string) $runId)) {
    set_flash('error', 'Missing or invalid run_id.');
    redirect('runs.php');
}
$runId = (int) $runId;

function load_accessible_run(PDO $pdo, int $runId, int $uid): ?array
{
    $stmt = $pdo->prepare(
        'SELECT
            r.run_id,
            r.model_id,
            r.dataset_version_id,
            r.created_by_user_id,
            r.started_at,
            r.ended_at,
            r.status,
            r.code_version_tag,
            r.notes,
            m.model_name,
            p.project_id,
            p.project_name,
            p.workspace_id,
            w.workspace_name,
            d.dataset_name,
            dv.version_tag,
            u.full_name
         FROM Runs r
         INNER JOIN Models m ON r.model_id = m.model_id
         INNER JOIN Projects p ON m.project_id = p.project_id
         INNER JOIN Workspaces w ON w.workspace_id = p.workspace_id
         INNER JOIN WorkspaceMembers wm ON wm.workspace_id = p.workspace_id AND wm.user_id = ?
         INNER JOIN DatasetVersions dv ON r.dataset_version_id = dv.dataset_version_id
         INNER JOIN Datasets d ON dv.dataset_id = d.dataset_id
         INNER JOIN Users u ON r.created_by_user_id = u.user_id
         WHERE r.run_id = ?'
    );
    $stmt->execute([$uid, $runId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

$run = load_accessible_run($pdo, $runId, $uid);
if (!$run) {
    set_flash('error', 'Run not found or access denied.');
    redirect('runs.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'update_run') {
            $status = trim((string) ($_POST['status'] ?? ''));
            $endedAt = trim((string) ($_POST['ended_at'] ?? ''));
            $codeVersionTag = trim((string) ($_POST['code_version_tag'] ?? ''));
            $notes = trim((string) ($_POST['notes'] ?? ''));

            if (!in_array($status, $allowedStatuses, true)) {
                $errors[] = 'Invalid status.';
            }
            if ($endedAt !== '' && strtotime($endedAt) === false) {
                $errors[] = 'Ended At is invalid.';
            }
            if (
                $endedAt !== '' &&
                $run['started_at'] !== null &&
                strtotime($endedAt) < strtotime((string) $run['started_at'])
            ) {
                $errors[] = 'Ended At cannot be earlier than Started At.';
            }

            if ($errors === []) {
                $stmt = $pdo->prepare(
                    'UPDATE Runs
                     SET status = :status,
                         ended_at = :ended_at,
                         code_version_tag = :code_version_tag,
                         notes = :notes
                     WHERE run_id = :run_id'
                );
                $stmt->execute([
                    ':status' => $status,
                    ':ended_at' => $endedAt !== '' ? date('Y-m-d H:i:s', strtotime($endedAt)) : null,
                    ':code_version_tag' => $codeVersionTag !== '' ? $codeVersionTag : null,
                    ':notes' => $notes !== '' ? $notes : null,
                    ':run_id' => $runId,
                ]);
                log_audit($pdo, (int) $run['workspace_id'], 'update', 'run', $runId);
                $messages[] = 'Run updated.';
                $run = load_accessible_run($pdo, $runId, $uid);
            }
        } elseif ($action === 'add_param') {
            $paramKey = trim((string) ($_POST['param_key'] ?? ''));
            $paramValue = trim((string) ($_POST['param_value'] ?? ''));

            if ($paramKey === '' || $paramValue === '') {
                $errors[] = 'Both parameter key and parameter value are required.';
            }

            if ($errors === []) {
                $stmt = $pdo->prepare(
                    'INSERT INTO RunParams (run_id, param_key, param_value)
                     VALUES (:run_id, :param_key, :param_value)'
                );
                $stmt->execute([
                    ':run_id' => $runId,
                    ':param_key' => $paramKey,
                    ':param_value' => $paramValue,
                ]);
                log_audit($pdo, (int) $run['workspace_id'], 'update', 'run', $runId);
                $messages[] = 'Parameter added.';
            }
        } elseif ($action === 'delete_param') {
            $paramKey = trim((string) ($_POST['param_key'] ?? ''));

            $stmt = $pdo->prepare(
                'DELETE FROM RunParams
                 WHERE run_id = :run_id AND param_key = :param_key'
            );
            $stmt->execute([
                ':run_id' => $runId,
                ':param_key' => $paramKey,
            ]);
            log_audit($pdo, (int) $run['workspace_id'], 'update', 'run', $runId);
            $messages[] = 'Parameter deleted.';
        } elseif ($action === 'add_metric') {
            $metricKey = trim((string) ($_POST['metric_key'] ?? ''));
            $metricValue = trim((string) ($_POST['metric_value'] ?? ''));
            $step = trim((string) ($_POST['step'] ?? ''));
            $recordedAt = trim((string) ($_POST['recorded_at'] ?? ''));

            if ($metricKey === '' || $metricValue === '' || $step === '') {
                $errors[] = 'Metric key, metric value, and step are required.';
            }
            if ($metricValue !== '' && !is_numeric($metricValue)) {
                $errors[] = 'Metric value must be numeric.';
            }
            if ($step !== '' && (!ctype_digit($step) || (int) $step < 0)) {
                $errors[] = 'Metric step must be a nonnegative integer.';
            }
            if ($recordedAt !== '' && strtotime($recordedAt) === false) {
                $errors[] = 'Recorded At is invalid.';
            }

            if ($errors === []) {
                if ($recordedAt !== '') {
                    $stmt = $pdo->prepare(
                        'INSERT INTO RunMetrics (run_id, metric_key, metric_value, step, recorded_at)
                         VALUES (:run_id, :metric_key, :metric_value, :step, :recorded_at)'
                    );
                    $stmt->execute([
                        ':run_id' => $runId,
                        ':metric_key' => $metricKey,
                        ':metric_value' => $metricValue,
                        ':step' => (int) $step,
                        ':recorded_at' => date('Y-m-d H:i:s', strtotime($recordedAt)),
                    ]);
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO RunMetrics (run_id, metric_key, metric_value, step)
                         VALUES (:run_id, :metric_key, :metric_value, :step)'
                    );
                    $stmt->execute([
                        ':run_id' => $runId,
                        ':metric_key' => $metricKey,
                        ':metric_value' => $metricValue,
                        ':step' => (int) $step,
                    ]);
                }

                log_audit($pdo, (int) $run['workspace_id'], 'update', 'run', $runId);
                $messages[] = 'Metric added.';
            }
        } elseif ($action === 'delete_metric') {
            $metricKey = trim((string) ($_POST['metric_key'] ?? ''));
            $step = trim((string) ($_POST['step'] ?? ''));

            $stmt = $pdo->prepare(
                'DELETE FROM RunMetrics
                 WHERE run_id = :run_id AND metric_key = :metric_key AND step = :step'
            );
            $stmt->execute([
                ':run_id' => $runId,
                ':metric_key' => $metricKey,
                ':step' => (int) $step,
            ]);
            log_audit($pdo, (int) $run['workspace_id'], 'update', 'run', $runId);
            $messages[] = 'Metric deleted.';
        } elseif ($action === 'delete_run') {
            $registryStmt = $pdo->prepare(
                'SELECT registry_id
                 FROM ModelRegistry
                 WHERE source_run_id = :run_id
                 LIMIT 1'
            );
            $registryStmt->execute([':run_id' => $runId]);
            $registryRow = $registryStmt->fetch();

            if ($registryRow) {
                $errors[] = 'This run cannot be deleted because it is registered in the Model Registry. Registered runs are kept for reproducibility history.';
            } else {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare('DELETE FROM Runs WHERE run_id = :run_id');
                $stmt->execute([':run_id' => $runId]);

                log_audit($pdo, (int) $run['workspace_id'], 'delete', 'run', $runId);

                $pdo->commit();

                set_flash('success', 'Run deleted. Dependent parameters and metrics were removed by cascade.');
                redirect('runs.php');
            }
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($e instanceof PDOException && $e->getCode() === '23000') {
            $errors[] = 'This action could not be completed because it would break a database relationship.';
        } else {
            $errors[] = 'Database operation failed. Please check the input and try again.';
        }
    }
}

$paramsStmt = $pdo->prepare(
    'SELECT run_id, param_key, param_value
     FROM RunParams
     WHERE run_id = :run_id
     ORDER BY param_key'
);
$paramsStmt->execute([':run_id' => $runId]);
$params = $paramsStmt->fetchAll();

$metricsStmt = $pdo->prepare(
    'SELECT run_id, metric_key, metric_value, step, recorded_at
     FROM RunMetrics
     WHERE run_id = :run_id
     ORDER BY metric_key, step'
);
$metricsStmt->execute([':run_id' => $runId]);
$metrics = $metricsStmt->fetchAll();

$endedAtValue = '';
if (!empty($run['ended_at'])) {
    $endedAtValue = date('Y-m-d\TH:i', strtotime((string) $run['ended_at']));
}

$datasetVersionLabel = trim((string) $run['dataset_name']) . ' — ' . trim((string) $run['version_tag']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LabBench - Run Detail</title>
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
        <div>Run Detail</div>
        <div class="header-right"><?php echo h($run['workspace_name']); ?></div>
      </header>

      <main class="content">
        <div class="breadcrumb"><a href="runs.php">Runs</a> / <?php echo h((string) $run['run_id']); ?></div>
        <h1 class="page-title">Run Detail</h1>
        <p class="page-sub">View, update, and manage parameters and metrics for this run.</p>

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

        <?php if ($messages !== []): ?>
          <div class="card" style="border-color:#14532d;">
            <div class="card-title">Success</div>
            <ul>
              <?php foreach ($messages as $message): ?>
                <li><?php echo h($message); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <div class="two-col">
          <div class="card">
            <div class="card-title">Run Summary</div>
            <table>
              <tbody>
                <tr><th>Run ID</th><td class="mono"><?php echo h((string) $run['run_id']); ?></td></tr>
                <tr><th>Workspace</th><td><?php echo h($run['workspace_name']); ?></td></tr>
                <tr><th>Project</th><td><?php echo h($run['project_name']); ?></td></tr>
                <tr><th>Model</th><td><?php echo h($run['model_name']); ?></td></tr>
                <tr><th>Dataset Version</th><td><?php echo h($datasetVersionLabel); ?></td></tr>
                <tr><th>Status</th><td><span class="badge badge-green"><?php echo h($run['status']); ?></span></td></tr>
                <tr><th>Started At</th><td class="mono"><?php echo h((string) $run['started_at']); ?></td></tr>
                <tr><th>Ended At</th><td class="mono"><?php echo h((string) $run['ended_at']); ?></td></tr>
                <tr><th>Code Version</th><td class="mono"><?php echo h((string) $run['code_version_tag']); ?></td></tr>
                <tr><th>Created By</th><td><?php echo h($run['full_name']); ?></td></tr>
              </tbody>
            </table>
          </div>

          <div class="card">
            <div class="card-title">Update Run</div>
            <form action="run_detail.php" method="post">
              <input type="hidden" name="action" value="update_run" />
              <input type="hidden" name="run_id" value="<?php echo h((string) $run['run_id']); ?>" />

              <div class="form-group">
                <label for="update_status">Status</label>
                <select id="update_status" name="status" required>
                  <?php foreach ($allowedStatuses as $status): ?>
                    <option value="<?php echo h($status); ?>"<?php echo $run['status'] === $status ? ' selected' : ''; ?>>
                      <?php echo h($status); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label for="update_ended_at">Ended At</label>
                <input type="datetime-local" id="update_ended_at" name="ended_at" value="<?php echo h($endedAtValue); ?>" />
              </div>

              <div class="form-group">
                <label for="update_code_version_tag">Code Version Tag</label>
                <input type="text" id="update_code_version_tag" name="code_version_tag" maxlength="100" value="<?php echo h((string) $run['code_version_tag']); ?>" />
              </div>

              <div class="form-group">
                <label for="update_notes">Notes</label>
                <textarea id="update_notes" name="notes"><?php echo h((string) $run['notes']); ?></textarea>
              </div>

              <div class="form-actions">
                <button type="submit">Update Run</button>
              </div>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card-title">Hyperparameters</div>

          <table>
            <thead><tr><th>Parameter</th><th>Value</th><th></th></tr></thead>
            <tbody>
              <?php if ($params === []): ?>
                <tr><td colspan="3" class="placeholder">No parameters recorded.</td></tr>
              <?php else: ?>
                <?php foreach ($params as $param): ?>
                  <tr>
                    <td class="mono"><?php echo h($param['param_key']); ?></td>
                    <td class="mono"><?php echo h($param['param_value']); ?></td>
                    <td>
                      <form action="run_detail.php" method="post" style="display:inline;">
                        <input type="hidden" name="action" value="delete_param" />
                        <input type="hidden" name="run_id" value="<?php echo h((string) $run['run_id']); ?>" />
                        <input type="hidden" name="param_key" value="<?php echo h($param['param_key']); ?>" />
                        <button type="submit" class="secondary">Delete</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>

          <form action="run_detail.php" method="post" style="margin-top:18px;">
            <input type="hidden" name="action" value="add_param" />
            <input type="hidden" name="run_id" value="<?php echo h((string) $run['run_id']); ?>" />
            <div class="form-grid">
              <div class="form-group">
                <label for="add_param_key">Parameter Key</label>
                <input type="text" id="add_param_key" name="param_key" maxlength="100" placeholder="batch_size" required />
              </div>
              <div class="form-group">
                <label for="add_param_value">Parameter Value</label>
                <input type="text" id="add_param_value" name="param_value" maxlength="100" placeholder="64" required />
              </div>
            </div>
            <div class="form-actions">
              <button type="submit">Add Parameter</button>
            </div>
          </form>
        </div>

        <div class="card">
          <div class="card-title">Metrics</div>

          <table>
            <thead><tr><th>Metric</th><th>Value</th><th>Step</th><th>Recorded At</th><th></th></tr></thead>
            <tbody>
              <?php if ($metrics === []): ?>
                <tr><td colspan="5" class="placeholder">No metrics recorded.</td></tr>
              <?php else: ?>
                <?php foreach ($metrics as $metric): ?>
                  <tr>
                    <td class="mono"><?php echo h($metric['metric_key']); ?></td>
                    <td class="mono"><?php echo h((string) $metric['metric_value']); ?></td>
                    <td class="mono"><?php echo h((string) $metric['step']); ?></td>
                    <td class="mono"><?php echo h((string) $metric['recorded_at']); ?></td>
                    <td>
                      <form action="run_detail.php" method="post" style="display:inline;">
                        <input type="hidden" name="action" value="delete_metric" />
                        <input type="hidden" name="run_id" value="<?php echo h((string) $run['run_id']); ?>" />
                        <input type="hidden" name="metric_key" value="<?php echo h($metric['metric_key']); ?>" />
                        <input type="hidden" name="step" value="<?php echo h((string) $metric['step']); ?>" />
                        <button type="submit" class="secondary">Delete</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>

          <form action="run_detail.php" method="post" style="margin-top:18px;">
            <input type="hidden" name="action" value="add_metric" />
            <input type="hidden" name="run_id" value="<?php echo h((string) $run['run_id']); ?>" />
            <div class="form-grid">
              <div class="form-group">
                <label for="add_metric_key">Metric Key</label>
                <input type="text" id="add_metric_key" name="metric_key" maxlength="100" placeholder="accuracy" required />
              </div>
              <div class="form-group">
                <label for="add_metric_value">Metric Value</label>
                <input type="number" step="any" id="add_metric_value" name="metric_value" placeholder="0.95" required />
              </div>
              <div class="form-group">
                <label for="add_metric_step">Step</label>
                <input type="number" id="add_metric_step" name="step" min="0" placeholder="1" required />
              </div>
              <div class="form-group">
                <label for="add_metric_recorded_at">Recorded At</label>
                <input type="datetime-local" id="add_metric_recorded_at" name="recorded_at" />
              </div>
            </div>
            <div class="form-actions">
              <button type="submit">Add Metric</button>
            </div>
          </form>
        </div>

        <div class="card">
          <div class="card-title">Delete Run</div>
          <p class="muted">
            Deleting a run also deletes dependent RunParams and RunMetrics rows. Runs that are already registered in the Model Registry are protected and cannot be deleted.
          </p>
          <form action="run_detail.php" method="post" onsubmit="return confirm('Delete this run?');">
            <input type="hidden" name="action" value="delete_run" />
            <input type="hidden" name="run_id" value="<?php echo h((string) $run['run_id']); ?>" />
            <div class="form-actions">
              <button type="submit">Delete Run</button>
              <a class="button secondary" href="runs.php">Back to Runs</a>
            </div>
          </form>
        </div>
      </main>
    </div>
  </div>
</body>
</html>
