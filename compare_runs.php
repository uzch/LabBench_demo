<?php
// compare_runs.php
// LabBench Phase 4 — Compare two accessible Runs side by side.

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit_helpers.php';

require_login();
$uid = current_user_id();

$runOptionsStmt = $pdo->prepare(
    'SELECT r.run_id, p.project_name, m.model_name, r.status
     FROM Runs r
     INNER JOIN Models m ON r.model_id = m.model_id
     INNER JOIN Projects p ON m.project_id = p.project_id
     INNER JOIN WorkspaceMembers wm ON wm.workspace_id = p.workspace_id AND wm.user_id = ?
     ORDER BY r.run_id DESC'
);
$runOptionsStmt->execute([$uid]);
$runOptions = $runOptionsStmt->fetchAll();

$runAId = trim((string) ($_GET['run_a'] ?? ''));
$runBId = trim((string) ($_GET['run_b'] ?? ''));
$errors = [];
$runA = null;
$runB = null;
$metricComparison = [];

if ($runAId !== '' || $runBId !== '') {
    if (!ctype_digit($runAId) || !ctype_digit($runBId)) {
        $errors[] = 'Both selected runs must be valid integers.';
    } elseif ((int) $runAId === (int) $runBId) {
        $errors[] = 'Run A and Run B must be different.';
    } else {
        $summaryStmt = $pdo->prepare(
            'SELECT
                r.run_id,
                r.status,
                r.code_version_tag,
                m.model_name,
                p.project_name,
                w.workspace_name,
                dv.version_tag
             FROM Runs r
             INNER JOIN Models m ON r.model_id = m.model_id
             INNER JOIN Projects p ON m.project_id = p.project_id
             INNER JOIN Workspaces w ON w.workspace_id = p.workspace_id
             INNER JOIN WorkspaceMembers wm ON wm.workspace_id = p.workspace_id AND wm.user_id = :uid
             INNER JOIN DatasetVersions dv ON r.dataset_version_id = dv.dataset_version_id
             WHERE r.run_id = :run_id'
        );

        $summaryStmt->execute([':uid' => $uid, ':run_id' => (int) $runAId]);
        $runA = $summaryStmt->fetch();

        $summaryStmt->execute([':uid' => $uid, ':run_id' => (int) $runBId]);
        $runB = $summaryStmt->fetch();

        if (!$runA || !$runB) {
            $errors[] = 'One or both selected runs do not exist or are not available to the logged-in user.';
        } else {
            $metricStmt = $pdo->prepare(
                'SELECT metric_key, step, metric_value
                 FROM RunMetrics
                 WHERE run_id = :run_id
                 ORDER BY metric_key, step'
            );

            $metricStmt->execute([':run_id' => (int) $runAId]);
            $runAMetrics = $metricStmt->fetchAll();

            $metricStmt->execute([':run_id' => (int) $runBId]);
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
            usort($metricComparison, static function ($a, $b) {
                return [$a['metric_key'], (int) $a['step']] <=> [$b['metric_key'], (int) $b['step']];
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
        <?php render_sidebar('runs'); ?>
      </nav>
    </aside>

    <div class="main">
      <header class="header">
        <div>Compare Runs</div>
        <div class="header-right">Signed in as user #<?php echo h((string) $uid); ?></div>
      </header>

      <main class="content">
        <div class="breadcrumb"><a href="runs.php">Runs</a> / Compare Runs</div>
        <h1 class="page-title">Compare Runs</h1>
        <p class="page-sub">Compare two selected runs side by side.</p>

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
          <form action="compare_runs.php" method="get">
            <div class="form-grid">
              <div class="form-group">
                <label for="compare_run_a">Run A</label>
                <select id="compare_run_a" name="run_a" required>
                  <option value="">Select first run</option>
                  <?php foreach ($runOptions as $option): ?>
                    <option value="<?php echo h((string) $option['run_id']); ?>"<?php echo $runAId === (string) $option['run_id'] ? ' selected' : ''; ?>>
                      <?php echo h('Run ' . $option['run_id'] . ' — ' . $option['project_name'] . ' — ' . $option['model_name'] . ' — ' . $option['status']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label for="compare_run_b">Run B</label>
                <select id="compare_run_b" name="run_b" required>
                  <option value="">Select second run</option>
                  <?php foreach ($runOptions as $option): ?>
                    <option value="<?php echo h((string) $option['run_id']); ?>"<?php echo $runBId === (string) $option['run_id'] ? ' selected' : ''; ?>>
                      <?php echo h('Run ' . $option['run_id'] . ' — ' . $option['project_name'] . ' — ' . $option['model_name'] . ' — ' . $option['status']); ?>
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

        <?php if ($runA && $runB && $errors === []): ?>
          <div class="two-col">
            <div class="card">
              <div class="card-title">Run A</div>
              <table>
                <tbody>
                  <tr><th>Run ID</th><td class="mono"><?php echo h((string) $runA['run_id']); ?></td></tr>
                  <tr><th>Workspace</th><td><?php echo h($runA['workspace_name']); ?></td></tr>
                  <tr><th>Project</th><td><?php echo h($runA['project_name']); ?></td></tr>
                  <tr><th>Model</th><td><?php echo h($runA['model_name']); ?></td></tr>
                  <tr><th>Dataset Version</th><td class="mono"><?php echo h($runA['version_tag']); ?></td></tr>
                  <tr><th>Status</th><td><span class="badge badge-green"><?php echo h($runA['status']); ?></span></td></tr>
                  <tr><th>Code Version</th><td class="mono"><?php echo h((string) $runA['code_version_tag']); ?></td></tr>
                </tbody>
              </table>
            </div>

            <div class="card">
              <div class="card-title">Run B</div>
              <table>
                <tbody>
                  <tr><th>Run ID</th><td class="mono"><?php echo h((string) $runB['run_id']); ?></td></tr>
                  <tr><th>Workspace</th><td><?php echo h($runB['workspace_name']); ?></td></tr>
                  <tr><th>Project</th><td><?php echo h($runB['project_name']); ?></td></tr>
                  <tr><th>Model</th><td><?php echo h($runB['model_name']); ?></td></tr>
                  <tr><th>Dataset Version</th><td class="mono"><?php echo h($runB['version_tag']); ?></td></tr>
                  <tr><th>Status</th><td><span class="badge badge-green"><?php echo h($runB['status']); ?></span></td></tr>
                  <tr><th>Code Version</th><td class="mono"><?php echo h((string) $runB['code_version_tag']); ?></td></tr>
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
                <?php if ($metricComparison === []): ?>
                  <tr>
                    <td colspan="4" class="placeholder">No comparable metrics found.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($metricComparison as $row): ?>
                    <tr>
                      <td class="mono"><?php echo h($row['metric_key']); ?></td>
                      <td class="mono"><?php echo h((string) $row['step']); ?></td>
                      <td class="mono"><?php echo h((string) $row['run_a_value']); ?></td>
                      <td class="mono"><?php echo h((string) $row['run_b_value']); ?></td>
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
