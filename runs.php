<?php
// runs.php
// LabBench Phase 4 — Runs list, filtering, and compare selection.

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit_helpers.php';

require_login();
$uid = current_user_id();

$allowedStatuses = ['queued', 'running', 'completed', 'failed'];

$statusFilter = trim((string) ($_GET['status'] ?? ''));
$projectFilter = trim((string) ($_GET['project_id'] ?? ''));
$search = trim((string) ($_GET['search'] ?? ''));

if ($statusFilter !== '' && !in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
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
if ($projectFilter !== '' && ctype_digit($projectFilter)) {
    if (!in_array((int) $projectFilter, $projectIds, true)) {
        $projectFilter = '';
    }
} else {
    $projectFilter = '';
}

$sql = "
    SELECT
        r.run_id,
        p.project_id,
        p.project_name,
        w.workspace_name,
        m.model_name,
        dv.version_tag,
        r.status,
        r.code_version_tag,
        r.started_at
    FROM Runs r
    INNER JOIN Models m ON r.model_id = m.model_id
    INNER JOIN Projects p ON m.project_id = p.project_id
    INNER JOIN Workspaces w ON w.workspace_id = p.workspace_id
    INNER JOIN WorkspaceMembers wm ON wm.workspace_id = p.workspace_id AND wm.user_id = :uid
    INNER JOIN DatasetVersions dv ON r.dataset_version_id = dv.dataset_version_id
    WHERE 1 = 1
";

$params = [':uid' => $uid];

if ($statusFilter !== '') {
    $sql .= ' AND r.status = :status';
    $params[':status'] = $statusFilter;
}

if ($projectFilter !== '') {
    $sql .= ' AND p.project_id = :project_id';
    $params[':project_id'] = (int) $projectFilter;
}

if ($search !== '') {
    $sql .= ' AND (r.code_version_tag LIKE :search OR r.notes LIKE :search OR m.model_name LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}

$sql .= ' ORDER BY r.run_id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$runs = $stmt->fetchAll();

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LabBench - All Runs</title>
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
        <div>All Runs</div>
        <div class="header-right">Signed in as user #<?php echo h((string) $uid); ?></div>
      </header>

      <main class="content">
        <?php show_flash(); ?>
        <div class="breadcrumb">Workspace / Runs</div>
        <h1 class="page-title">All Runs</h1>
        <p class="page-sub">Browse, filter, and compare runs in workspaces you belong to.</p>

        <div class="card">
          <div class="card-title">Run Filters</div>
          <form action="runs.php" method="get">
            <div class="form-grid">
              <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                  <option value="">All Statuses</option>
                  <?php foreach ($allowedStatuses as $status): ?>
                    <option value="<?php echo h($status); ?>"<?php echo $statusFilter === $status ? ' selected' : ''; ?>>
                      <?php echo h($status); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label for="project_id">Project</label>
                <select id="project_id" name="project_id">
                  <option value="">All Projects</option>
                  <?php foreach ($projects as $project): ?>
                    <option value="<?php echo h((string) $project['project_id']); ?>"<?php echo $projectFilter === (string) $project['project_id'] ? ' selected' : ''; ?>>
                      <?php echo h($project['workspace_name'] . ' — ' . $project['project_name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label for="search">Search</label>
                <input
                  type="text"
                  id="search"
                  name="search"
                  value="<?php echo h($search); ?>"
                  placeholder="Search code tag, notes, or model"
                />
              </div>

              <div class="form-group">
                <label>&nbsp;</label>
                <button type="submit">Apply Filters</button>
              </div>
            </div>
          </form>
        </div>

        <div class="actions">
          <a class="button" href="log_run.php<?php echo $projectFilter !== '' ? '?project_id=' . h($projectFilter) : ''; ?>">+ Log Run</a>
        </div>

        <div class="card">
          <div class="card-title">Run Results</div>
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
                <th>Started</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php if ($runs === []): ?>
                <tr>
                  <td colspan="9" class="placeholder">No runs found.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($runs as $run): ?>
                  <tr>
                    <td class="mono"><?php echo h((string) $run['run_id']); ?></td>
                    <td><?php echo h($run['workspace_name']); ?></td>
                    <td><?php echo h($run['project_name']); ?></td>
                    <td><?php echo h($run['model_name']); ?></td>
                    <td class="mono"><?php echo h($run['version_tag']); ?></td>
                    <td><span class="badge badge-green"><?php echo h($run['status']); ?></span></td>
                    <td class="mono"><?php echo h((string) $run['code_version_tag']); ?></td>
                    <td class="mono"><?php echo h((string) $run['started_at']); ?></td>
                    <td>
                      <a class="button secondary" href="run_detail.php?run_id=<?php echo h((string) $run['run_id']); ?>">View</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="card">
          <div class="card-title">Compare Two Runs</div>
          <form action="compare_runs.php" method="get">
            <div class="form-grid">
              <div class="form-group">
                <label for="run_a">Run A</label>
                <select id="run_a" name="run_a" required>
                  <option value="">Select first run</option>
                  <?php foreach ($runOptions as $option): ?>
                    <option value="<?php echo h((string) $option['run_id']); ?>">
                      <?php echo h('Run ' . $option['run_id'] . ' — ' . $option['project_name'] . ' — ' . $option['model_name'] . ' — ' . $option['status']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label for="run_b">Run B</label>
                <select id="run_b" name="run_b" required>
                  <option value="">Select second run</option>
                  <?php foreach ($runOptions as $option): ?>
                    <option value="<?php echo h((string) $option['run_id']); ?>">
                      <?php echo h('Run ' . $option['run_id'] . ' — ' . $option['project_name'] . ' — ' . $option['model_name'] . ' — ' . $option['status']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="form-actions">
              <button type="submit">Compare Runs</button>
            </div>
          </form>
        </div>
      </main>
    </div>
  </div>
</body>
</html>
