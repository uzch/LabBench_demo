<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/audit_helpers.php';

require_login();
$uid = current_user_id();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_workspace') {
    $errors = validate_workspace($_POST);

    if ($errors === []) {
        $name = trim((string) $_POST['workspace_name']);

        try {
            $pdo->beginTransaction();

            $st = $pdo->prepare('INSERT INTO Workspaces (workspace_name, created_at) VALUES (?, NOW())');
            $st->execute([$name]);
            $wid = (int) $pdo->lastInsertId();

            $st = $pdo->prepare(
                'INSERT INTO WorkspaceMembers (workspace_id, user_id, member_role, joined_at) VALUES (?, ?, ?, NOW())'
            );
            $st->execute([$wid, $uid, 'admin']);

            log_audit($pdo, $wid, 'create', 'workspace', $wid);

            $pdo->commit();
            set_flash('success', 'Workspace created.');
            redirect('workspace.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Could not create workspace. Please try again.';
        }
    }
}

$st = $pdo->prepare(
    'SELECT w.workspace_id,
            w.workspace_name,
            w.created_at,
            wm.member_role,
            (
                SELECT COUNT(*)
                FROM WorkspaceMembers wm2
                WHERE wm2.workspace_id = w.workspace_id
            ) AS member_count,
            (
                SELECT COUNT(*)
                FROM Projects p
                WHERE p.workspace_id = w.workspace_id
            ) AS project_count
     FROM Workspaces w
     INNER JOIN WorkspaceMembers wm ON wm.workspace_id = w.workspace_id
     WHERE wm.user_id = ?
     ORDER BY w.workspace_name'
);
$st->execute([$uid]);
$workspaces = $st->fetchAll();

$workspace_count = count($workspaces);
$admin_count = 0;
$member_total = 0;
foreach ($workspaces as $workspace) {
    if (($workspace['member_role'] ?? '') === 'admin') {
        $admin_count++;
    }
    $member_total += (int) $workspace['member_count'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LabBench - Workspaces</title>
  <link rel="stylesheet" href="assets/styles.css" />
</head>
<body>
  <div class="layout">
    <aside class="sidebar">
      <div class="logo">LABBENCH</div>
      <nav class="nav">
        <?php render_sidebar('workspaces'); ?>
      </nav>
    </aside>
    <div class="main">
      <header class="header">
        <div>Workspaces</div>
        <div class="header-right">Signed in as <?php echo h(current_user_name()); ?></div>
      </header>
      <main class="content">
        <?php show_flash(); ?>

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

<div class="breadcrumb">Workspace / Management</div>
<h1 class="page-title">Workspaces</h1>
<p class="page-sub">Create a workspace, view the ones you belong to, and delete the ones you administer.</p>

<div class="stats">
  <div class="stat-card"><div class="stat-label">My Workspaces</div><div class="stat-value"><?php echo h((string) $workspace_count); ?></div></div>
  <div class="stat-card"><div class="stat-label">Admin Of</div><div class="stat-value"><?php echo h((string) $admin_count); ?></div></div>
  <div class="stat-card"><div class="stat-label">Members Across Them</div><div class="stat-value"><?php echo h((string) $member_total); ?></div></div>
</div>

<div class="card" style="margin-bottom:18px;">
  <div class="card-title">Create Workspace</div>
  <form method="post" action="workspace.php">
    <input type="hidden" name="action" value="create_workspace" />
    <div class="form-grid single">
      <div class="form-group">
        <label for="workspace_name">Workspace Name</label>
        <input
          type="text"
          id="workspace_name"
          name="workspace_name"
          maxlength="100"
          required
          value="<?php echo h((string) ($_POST['workspace_name'] ?? '')); ?>"
        />
      </div>
    </div>
    <div class="form-actions">
      <button type="submit">Create Workspace</button>
    </div>
  </form>
</div>

<div class="card">
  <div class="card-title">Your Workspaces</div>
  <table>
    <thead>
      <tr>
        <th>Name</th>
        <th>Your Role</th>
        <th>Members</th>
        <th>Projects</th>
        <th>Created</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if ($workspaces === []): ?>
      <tr>
        <td colspan="6" class="placeholder">You are not a member of any workspaces yet.</td>
      </tr>
      <?php else: ?>
        <?php foreach ($workspaces as $workspace): ?>
      <tr>
        <td><?php echo h((string) $workspace['workspace_name']); ?></td>
        <td><?php echo h((string) $workspace['member_role']); ?></td>
        <td class="mono"><?php echo h((string) $workspace['member_count']); ?></td>
        <td class="mono"><?php echo h((string) $workspace['project_count']); ?></td>
        <td class="mono"><?php echo h((string) $workspace['created_at']); ?></td>
        <td>
          <?php if (($workspace['member_role'] ?? '') === 'admin'): ?>
          <form method="post" action="delete_workspace.php" style="margin:0;display:inline;" onsubmit="return confirm('Delete this workspace and all related data?');">
            <input type="hidden" name="workspace_id" value="<?php echo h((string) $workspace['workspace_id']); ?>" />
            <button type="submit" class="secondary" style="background:transparent;color:#f47f7f;border-color:#5c2a2a;">Delete</button>
          </form>
          <?php else: ?>
            <span class="small muted">Admin only</span>
          <?php endif; ?>
        </td>
      </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

      </main>
    </div>
  </div>
</body>
</html>
