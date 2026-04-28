<?php
// sql_injection_demo.php
// LabBench Phase 4 — SQL injection demonstration and prepared-statement defense.

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit_helpers.php';

require_login();
$uid = current_user_id();

$fields = [
    'project_name' => 'Project Name',
    'model_name' => 'Model Name',
    'status' => 'Run Status',
    'code_version_tag' => 'Code Version Tag',
];

$inputs = [];
foreach ($fields as $name => $label) {
    $inputs[$name] = trim((string) ($_GET[$name] ?? ''));
}

$mode = (string) ($_GET['mode'] ?? '');
if (!in_array($mode, ['vulnerable', 'prepared'], true)) {
    $mode = '';
}

$baseSelect = "
    SELECT
        r.run_id,
        w.workspace_name,
        p.project_name,
        m.model_name,
        dv.version_tag,
        r.status,
        r.code_version_tag,
        r.started_at,
        r.ended_at,
        u.full_name AS created_by
    FROM Runs r
    INNER JOIN Models m ON m.model_id = r.model_id
    INNER JOIN Projects p ON p.project_id = m.project_id
    INNER JOIN Workspaces w ON w.workspace_id = p.workspace_id
    INNER JOIN WorkspaceMembers wm ON wm.workspace_id = p.workspace_id
    INNER JOIN DatasetVersions dv ON dv.dataset_version_id = r.dataset_version_id
    INNER JOIN Users u ON u.user_id = r.created_by_user_id
";

$results = [];
$error = '';
$queryShown = '';
$parameterShown = [];

if ($mode === 'vulnerable') {
    // Intentionally vulnerable for the course assignment: raw user input is concatenated into a SELECT query.
    $sql = $baseSelect . "\n    WHERE wm.user_id = " . (int) $uid;

    if ($inputs['project_name'] !== '') {
        $sql .= "\n      AND (p.project_name = '" . $inputs['project_name'] . "')";
    }
    if ($inputs['model_name'] !== '') {
        $sql .= "\n      AND (m.model_name = '" . $inputs['model_name'] . "')";
    }
    if ($inputs['status'] !== '') {
        $sql .= "\n      AND (r.status = '" . $inputs['status'] . "')";
    }
    if ($inputs['code_version_tag'] !== '') {
        $sql .= "\n      AND (r.code_version_tag = '" . $inputs['code_version_tag'] . "')";
    }

    $sql .= "\n    ORDER BY r.run_id DESC\n    LIMIT 100";
    $queryShown = $sql;

    try {
        $results = $pdo->query($sql)->fetchAll();
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
} elseif ($mode === 'prepared') {
    // Safe rewrite: the same SELECT routine uses placeholders and bound parameters.
    $sql = $baseSelect . "\n    WHERE wm.user_id = :uid";
    $params = [':uid' => $uid];

    if ($inputs['project_name'] !== '') {
        $sql .= "\n      AND (p.project_name = :project_name)";
        $params[':project_name'] = $inputs['project_name'];
    }
    if ($inputs['model_name'] !== '') {
        $sql .= "\n      AND (m.model_name = :model_name)";
        $params[':model_name'] = $inputs['model_name'];
    }
    if ($inputs['status'] !== '') {
        $sql .= "\n      AND (r.status = :status)";
        $params[':status'] = $inputs['status'];
    }
    if ($inputs['code_version_tag'] !== '') {
        $sql .= "\n      AND (r.code_version_tag = :code_version_tag)";
        $params[':code_version_tag'] = $inputs['code_version_tag'];
    }

    $sql .= "\n    ORDER BY r.run_id DESC\n    LIMIT 100";
    $queryShown = $sql;
    $parameterShown = $params;

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

function selected_mode(string $actual, string $expected): string
{
    return $actual === $expected ? ' selected' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LabBench - SQL Injection Demo</title>
  <link rel="stylesheet" href="styles.css" />
</head>
<body>
  <div class="layout">
    <aside class="sidebar">
      <div class="logo">LABBENCH</div>
      <nav class="nav">
        <?php render_sidebar('sql_injection'); ?>
      </nav>
    </aside>

    <div class="main">
      <header class="header">
        <div>SQL Injection Demo</div>
        <div class="header-right">Signed in as <?php echo h(current_user_name()); ?></div>
      </header>

      <main class="content">
        <?php show_flash(); ?>
        <div class="breadcrumb">Workspace / SQL Injection Demo</div>
        <h1 class="page-title">SQL Injection Assignment</h1>
        <p class="page-sub">Demonstrates the unsafe SELECT routine and the prepared-statement rewrite using the same LabBench run-search form.</p>

        <div class="card" style="margin-bottom:18px;">
          <div class="card-title">Demo script for the TA</div>
          <p class="note">
            Use the same input twice. First choose <strong>Vulnerable SELECT</strong>, then choose <strong>Prepared Statement SELECT</strong>.
            For the injection test, put this exact value in <strong>Code Version Tag</strong> and leave the other fields blank:
          </p>
          <pre class="mono" style="white-space:pre-wrap;background:#1a1e28;border:1px solid #333849;border-radius:8px;padding:12px;">not-real' OR '1'='1</pre>
          <p class="note">
            The vulnerable version returns rows even though the code tag is not accurate. The prepared-statement version treats the payload as text and should return zero rows.
          </p>
        </div>

        <div class="card">
          <div class="card-title">Run Search Form</div>
          <form action="sql_injection_demo.php" method="get">
            <div class="form-grid">
              <div class="form-group">
                <label for="mode">Routine</label>
                <select id="mode" name="mode" required>
                  <option value="">Select routine</option>
                  <option value="vulnerable"<?php echo selected_mode($mode, 'vulnerable'); ?>>Part A: Vulnerable SELECT</option>
                  <option value="prepared"<?php echo selected_mode($mode, 'prepared'); ?>>Part B: Prepared Statement SELECT</option>
                </select>
              </div>
              <div class="form-group">
                <label for="project_name">Project Name</label>
                <input type="text" id="project_name" name="project_name" value="<?php echo h($inputs['project_name']); ?>" placeholder="Image Classification" />
              </div>
              <div class="form-group">
                <label for="model_name">Model Name</label>
                <input type="text" id="model_name" name="model_name" value="<?php echo h($inputs['model_name']); ?>" placeholder="ResNet Baseline" />
              </div>
              <div class="form-group">
                <label for="status">Run Status</label>
                <input type="text" id="status" name="status" value="<?php echo h($inputs['status']); ?>" placeholder="completed" />
              </div>
              <div class="form-group">
                <label for="code_version_tag">Code Version Tag</label>
                <input type="text" id="code_version_tag" name="code_version_tag" value="<?php echo h($inputs['code_version_tag']); ?>" placeholder="commit_a1" />
              </div>
            </div>
            <div class="form-actions">
              <button type="submit">Run SELECT</button>
              <a class="button secondary" href="sql_injection_demo.php">Reset</a>
            </div>
          </form>
        </div>

        <?php if ($mode !== ''): ?>
          <div class="card">
            <div class="card-title"><?php echo $mode === 'vulnerable' ? 'Part A: Generated Vulnerable SQL' : 'Part B: Prepared SQL'; ?></div>
            <pre class="mono" style="white-space:pre-wrap;background:#1a1e28;border:1px solid #333849;border-radius:8px;padding:12px;"><?php echo h($queryShown); ?></pre>
            <?php if ($parameterShown !== []): ?>
              <div class="card-title" style="margin-top:14px;">Bound Parameters</div>
              <pre class="mono" style="white-space:pre-wrap;background:#1a1e28;border:1px solid #333849;border-radius:8px;padding:12px;"><?php echo h(json_encode($parameterShown, JSON_PRETTY_PRINT)); ?></pre>
            <?php endif; ?>
          </div>

          <?php if ($error !== ''): ?>
            <div class="card" style="border-color:#9b2c2c;background:rgba(239,68,68,0.12);">
              <div class="card-title">SQL Error</div>
              <p class="note"><?php echo h($error); ?></p>
            </div>
          <?php endif; ?>

          <div class="card">
            <div class="card-title">Query Results (<?php echo h((string) count($results)); ?>)</div>
            <table>
              <thead>
                <tr>
                  <th>Run ID</th>
                  <th>Workspace</th>
                  <th>Project</th>
                  <th>Model</th>
                  <th>Dataset Version</th>
                  <th>Status</th>
                  <th>Code Tag</th>
                  <th>Created By</th>
                  <th>Started</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($results === []): ?>
                  <tr><td colspan="9" class="placeholder">No rows returned.</td></tr>
                <?php else: ?>
                  <?php foreach ($results as $row): ?>
                    <tr>
                      <td class="mono"><?php echo h((string) $row['run_id']); ?></td>
                      <td><?php echo h($row['workspace_name']); ?></td>
                      <td><?php echo h($row['project_name']); ?></td>
                      <td><?php echo h($row['model_name']); ?></td>
                      <td class="mono"><?php echo h($row['version_tag']); ?></td>
                      <td><span class="badge badge-blue"><?php echo h($row['status']); ?></span></td>
                      <td class="mono"><?php echo h((string) $row['code_version_tag']); ?></td>
                      <td><?php echo h($row['created_by']); ?></td>
                      <td class="mono"><?php echo h($row['started_at']); ?></td>
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
