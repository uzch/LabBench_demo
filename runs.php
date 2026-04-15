<?php
// runs.php
require 'db.php';

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$statusFilter = trim($_GET['status'] ?? '');
$projectFilter = trim($_GET['project_id'] ?? '');
$search = trim($_GET['search'] ?? '');

$allowedStatuses = ['queued', 'running', 'completed', 'failed'];
if ($statusFilter !== '' && !in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

$projectsStmt = $pdo->query("
    SELECT p.project_id, p.project_name
    FROM Projects p
    ORDER BY p.project_name
");
$projects = $projectsStmt->fetchAll();

$runOptionsStmt = $pdo->query("
    SELECT r.run_id
    FROM Runs r
    ORDER BY r.run_id DESC
");
$runOptions = $runOptionsStmt->fetchAll();

$sql = "
    SELECT
        r.run_id,
        p.project_id,
        p.project_name,
        m.model_name,
        dv.version_tag,
        r.status,
        r.code_version_tag,
        r.started_at
    FROM Runs r
    INNER JOIN Models m ON r.model_id = m.model_id
    INNER JOIN Projects p ON m.project_id = p.project_id
    INNER JOIN DatasetVersions dv ON r.dataset_version_id = dv.dataset_version_id
    WHERE 1 = 1
";

$params = [];

if ($statusFilter !== '') {
    $sql .= " AND r.status = :status";
    $params[':status'] = $statusFilter;
}

if ($projectFilter !== '' && ctype_digit($projectFilter)) {
    $sql .= " AND p.project_id = :project_id";
    $params[':project_id'] = (int)$projectFilter;
}

if ($search !== '') {
    $sql .= " AND (r.code_version_tag LIKE :search OR r.notes LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

$sql .= " ORDER BY r.run_id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$runs = $stmt->fetchAll();
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
        <a href="projects.php">Projects</a>
        <a href="runs.php" class="active">All Runs</a>
        <a href="datasets.php">Datasets</a>
        <a href="model_registry.php">Model Registry</a>
        <a href="login.php">Log Out</a>
      </nav>
    </aside>

    <div class="main">
      <header class="header">
        <div>All Runs</div>
        <div class="header-right">PHP + MySQL</div>
      </header>

      <main class="content">
        <div class="breadcrumb">Workspace / Runs</div>
        <h1 class="page-title">All Runs</h1>
        <p class="page-sub">Browse, filter, and compare runs.</p>

        <div class="card">
          <div class="card-title">Run Filters</div>
          <form action="runs.php" method="get">
            <div class="form-grid">
              <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                  <option value="">All Statuses</option>
                  <?php foreach ($allowedStatuses as $status): ?>
                    <option value="<?php echo e($status); ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>>
                      <?php echo e($status); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label for="project_id">Project</label>
                <select id="project_id" name="project_id">
                  <option value="">All Projects</option>
                  <?php foreach ($projects as $project): ?>
                    <option value="<?php echo e($project['project_id']); ?>" <?php echo $projectFilter === (string)$project['project_id'] ? 'selected' : ''; ?>>
                      <?php echo e($project['project_name']); ?>
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
                  value="<?php echo e($search); ?>"
                  placeholder="Search code tag or notes"
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
          <a class="button" href="log_run.php">+ Log Run</a>
        </div>

        <div class="card">
          <div class="card-title">Run Results</div>
          <table>
            <thead>
              <tr>
                <th>Run ID</th>
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
              <?php if (!$runs): ?>
                <tr>
                  <td colspan="8" class="placeholder">No runs found.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($runs as $run): ?>
                  <tr>
                    <td class="mono"><?php echo e($run['run_id']); ?></td>
                    <td><?php echo e($run['project_name']); ?></td>
                    <td><?php echo e($run['model_name']); ?></td>
                    <td class="mono"><?php echo e($run['version_tag']); ?></td>
                    <td><span class="badge badge-green"><?php echo e($run['status']); ?></span></td>
                    <td class="mono"><?php echo e($run['code_version_tag']); ?></td>
                    <td class="mono"><?php echo e($run['started_at']); ?></td>
                    <td>
                      <a class="button secondary" href="run_detail.php?run_id=<?php echo e($run['run_id']); ?>">View</a>
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
                    <option value="<?php echo e($option['run_id']); ?>">
                      <?php echo e($option['run_id']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label for="run_b">Run B</label>
                <select id="run_b" name="run_b" required>
                  <option value="">Select second run</option>
                  <?php foreach ($runOptions as $option): ?>
                    <option value="<?php echo e($option['run_id']); ?>">
                      <?php echo e($option['run_id']); ?>
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
