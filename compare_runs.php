<?php
// compare_runs.php
require 'db.php';

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$runOptions = $pdo->query("
    SELECT run_id
    FROM Runs
    ORDER BY run_id DESC
")->fetchAll();

$runAId = trim($_GET['run_a'] ?? '');
$runBId = trim($_GET['run_b'] ?? '');
$errors = [];
$runA = null;
$runB = null;
$metricComparison = [];

if ($runAId !== '' || $runBId !== '') {
    if (!ctype_digit($runAId) || !ctype_digit($runBId)) {
        $errors[] = 'Both selected runs must be valid integers.';
    } elseif ((int)$runAId === (int)$runBId) {
        $errors[] = 'Run A and Run B must be different.';
    } else {
        $summaryStmt = $pdo->prepare("
            SELECT
                r.run_id,
                r.status,
                r.code_version_tag,
                m.model_name,
                dv.version_tag
            FROM Runs r
            INNER JOIN Models m ON r.model_id = m.model_id
            INNER JOIN DatasetVersions dv ON r.dataset_version_id = dv.dataset_version_id
            WHERE r.run_id = :run_id
        ");

        $summaryStmt->execute([':run_id' => (int)$runAId]);
        $runA = $summaryStmt->fetch();

        $summaryStmt->execute([':run_id' => (int)$runBId]);
        $runB = $summaryStmt->fetch();

        if (!$runA || !$runB) {
            $errors[] = 'One or both selected runs do not exist.';
        } else {
            $metricStmt = $pdo->prepare("
                SELECT metric_key, step, metric_value
                FROM RunMetrics
                WHERE run_id = :run_id
                ORDER BY metric_key, step
            ");

            $metricStmt->execute([':run_id' => (int)$runAId]);
            $runAMetrics = $metricStmt->fetchAll();

            $metricStmt->execute([':run_id' => (int)$runBId]);
            $runBMetrics = $metricStmt->fetchAll();

            $indexed = [];

            foreach ($runAMetrics as $metric) {
                $key = $metric['metric_key'] . '|' . $metric['step'];
                $indexed[$key] = [
                    'metric_key' => $metric['metric_key'],
                    'step' => $metric['step'],
                    'run_a_value' => $metric['metric_value'],
                    'run_b_value' => null,
                ];
            }

            foreach ($runBMetrics as $metric) {
                $key = $metric['metric_key'] . '|' . $metric['step'];
                if (!isset($indexed[$key])) {
                    $indexed[$key] = [
                        'metric_key' => $metric['metric_key'],
                        'step' => $metric['step'],
                        'run_a_value' => null,
                        'run_b_value' => $metric['metric_value'],
                    ];
                } else {
                    $indexed[$key]['run_b_value'] = $metric['metric_value'];
                }
            }

            $metricComparison = array_values($indexed);
            usort($metricComparison, function ($a, $b) {
                return [$a['metric_key'], (int)$a['step']] <=> [$b['metric_key'], (int)$b['step']];
            });
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LabBench - Compare Runs</title>
  <link rel="stylesheet" href="styles.css" />
</head>
<body>
  <div class="layout">
    <aside class="sidebar">
      <div class="logo">LABBENCH</div>
      <nav class="nav">
        <a href="projects.php">Projects</a>
        <a href="runs.php" class="active">All Runs</a>
        <a href="datasets.php">Datasets</a>
        <a href="model_registry.php">Model Registry</a>
        <a href="login.php">Log Out</a>
      </nav>
    </aside>

    <div class="main">
      <header class="header">
        <div>Compare Runs</div>
        <div class="header-right">PHP + MySQL</div>
      </header>

      <main class="content">
        <div class="breadcrumb"><a href="runs.php">Runs</a> / Compare Runs</div>
        <h1 class="page-title">Compare Runs</h1>
        <p class="page-sub">Compare two selected runs side by side.</p>

        <?php if ($errors): ?>
          <div class="card" style="border-color:#7f1d1d;">
            <div class="card-title">Please fix the following</div>
            <ul>
              <?php foreach ($errors as $error): ?>
                <li><?php echo e($error); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <div class="card">
          <form action="compare_runs.php" method="get">
            <div class="form-grid">
              <div class="form-group">
                <label for="compare_run_a">Run A</label>
                <select id="compare_run_a" name="run_a" required>
                  <option value="">Select first run</option>
                  <?php foreach ($runOptions as $option): ?>
                    <option value="<?php echo e($option['run_id']); ?>" <?php echo $runAId === (string)$option['run_id'] ? 'selected' : ''; ?>>
                      <?php echo e($option['run_id']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label for="compare_run_b">Run B</label>
                <select id="compare_run_b" name="run_b" required>
                  <option value="">Select second run</option>
                  <?php foreach ($runOptions as $option): ?>
                    <option value="<?php echo e($option['run_id']); ?>" <?php echo $runBId === (string)$option['run_id'] ? 'selected' : ''; ?>>
                      <?php echo e($option['run_id']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="form-actions">
              <button type="submit">Refresh Comparison</button>
            </div>
          </form>
        </div>

        <?php if ($runA && $runB && !$errors): ?>
          <div class="two-col">
            <div class="card">
              <div class="card-title">Run A</div>
              <table>
                <tbody>
                  <tr><th>Run ID</th><td class="mono"><?php echo e($runA['run_id']); ?></td></tr>
                  <tr><th>Model</th><td><?php echo e($runA['model_name']); ?></td></tr>
                  <tr><th>Dataset Version</th><td class="mono"><?php echo e($runA['version_tag']); ?></td></tr>
                  <tr><th>Status</th><td><span class="badge badge-green"><?php echo e($runA['status']); ?></span></td></tr>
                  <tr><th>Code Version</th><td class="mono"><?php echo e($runA['code_version_tag']); ?></td></tr>
                </tbody>
              </table>
            </div>

            <div class="card">
              <div class="card-title">Run B</div>
              <table>
                <tbody>
                  <tr><th>Run ID</th><td class="mono"><?php echo e($runB['run_id']); ?></td></tr>
                  <tr><th>Model</th><td><?php echo e($runB['model_name']); ?></td></tr>
                  <tr><th>Dataset Version</th><td class="mono"><?php echo e($runB['version_tag']); ?></td></tr>
                  <tr><th>Status</th><td><span class="badge badge-green"><?php echo e($runB['status']); ?></span></td></tr>
                  <tr><th>Code Version</th><td class="mono"><?php echo e($runB['code_version_tag']); ?></td></tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="card">
            <div class="card-title">Metric Comparison</div>
            <table>
              <thead>
                <tr>
                  <th>Metric</th>
                  <th>Step</th>
                  <th>Run A</th>
                  <th>Run B</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$metricComparison): ?>
                  <tr>
                    <td colspan="4" class="placeholder">No comparable metrics found.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($metricComparison as $row): ?>
                    <tr>
                      <td class="mono"><?php echo e($row['metric_key']); ?></td>
                      <td class="mono"><?php echo e($row['step']); ?></td>
                      <td class="mono"><?php echo e($row['run_a_value']); ?></td>
                      <td class="mono"><?php echo e($row['run_b_value']); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </main>
    </div>
  </div>
</body>
</html>
