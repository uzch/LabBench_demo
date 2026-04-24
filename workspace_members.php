<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit_helpers.php';

require_login();
$uid = current_user_id();

$workspaces = workspaces_for_user($pdo, $uid);

$selected_wid = null;
if (isset($_GET['workspace_id']) && ctype_digit((string) $_GET['workspace_id'])) {
    $selected_wid = (int) $_GET['workspace_id'];
}
if ($selected_wid === null && $workspaces !== []) {
    $selected_wid = (int) $workspaces[0]['workspace_id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_member') {
    $wid = isset($_POST['workspace_id']) && ctype_digit((string) $_POST['workspace_id'])
        ? (int) $_POST['workspace_id']
        : 0;
    $errors = validate_member($_POST);
    if (!user_in_workspace($pdo, $wid, $uid)) {
        $errors[] = 'You do not have access to that workspace.';
    }
    if ($errors === [] && !user_is_admin_in_workspace($pdo, $wid, $uid)) {
        $errors[] = 'Only workspace admins can add members.';
    }
    if ($errors === []) {
        $newUid = (int) $_POST['user_id'];
        $role = (string) $_POST['member_role'];
        $check = $pdo->prepare('SELECT 1 FROM WorkspaceMembers WHERE workspace_id = ? AND user_id = ?');
        $check->execute([$wid, $newUid]);
        if ($check->fetchColumn()) {
            set_flash('error', 'That user is already a member of this workspace.');
            redirect('workspace_members.php?workspace_id=' . $wid);
        }
        try {
            $pdo->beginTransaction();
            $ins = $pdo->prepare(
                'INSERT INTO WorkspaceMembers (workspace_id, user_id, member_role, joined_at) VALUES (?, ?, ?, NOW())'
            );
            $ins->execute([$wid, $newUid, $role]);
            log_audit($pdo, $wid, 'create', 'workspace_member', $newUid);
            $pdo->commit();
            set_flash('success', 'Member added.');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $code = $e->errorInfo[1] ?? null;
            if ($code === 1062) {
                set_flash('error', 'That user is already a member of this workspace.');
            } else {
                set_flash('error', 'Could not add member.');
            }
        }
        redirect('workspace_members.php?workspace_id=' . $wid);
    }
    set_flash('error', implode(' ', $errors));
    redirect('workspace_members.php?workspace_id=' . ($wid > 0 ? $wid : ($selected_wid ?? 0)));
}

$members = [];
$candidates = [];
$is_admin = false;

if ($selected_wid !== null && $selected_wid > 0 && user_in_workspace($pdo, $selected_wid, $uid)) {
    $is_admin = user_is_admin_in_workspace($pdo, $selected_wid, $uid);
    $st = $pdo->prepare(
        'SELECT wm.user_id, wm.member_role, wm.joined_at, u.full_name, u.email
         FROM WorkspaceMembers wm
         INNER JOIN Users u ON u.user_id = wm.user_id
         WHERE wm.workspace_id = ?
         ORDER BY u.full_name'
    );
    $st->execute([$selected_wid]);
    $members = $st->fetchAll();

    $st = $pdo->prepare(
        'SELECT u.user_id, u.full_name, u.email FROM Users u
         WHERE u.user_id NOT IN (
           SELECT wm2.user_id FROM WorkspaceMembers wm2 WHERE wm2.workspace_id = ?
         )
         ORDER BY u.full_name'
    );
    $st->execute([$selected_wid]);
    $candidates = $st->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LabBench - Workspace Members</title>
  <link rel="stylesheet" href="styles.css" />
</head>
<body>
  <div class="layout">
    <aside class="sidebar">
      <div class="logo">LABBENCH</div>
      <nav class="nav">
        <?php render_sidebar('workspace_members'); ?>
      </nav>
    </aside>
    <div class="main">
      <header class="header">
        <div>Workspace Members</div>
        <div class="header-right">Signed in as user #<?php echo h((string) $uid); ?></div>
      </header>
      <main class="content">
        <?php show_flash(); ?>

<h1 class="page-title">Workspace Members</h1>
<p class="page-sub">View and manage membership for workspaces you belong to.</p>

<?php if ($workspaces === []): ?>
  <div class="card"><p class="muted">You are not a member of any workspace.</p></div>
<?php else: ?>

<div class="card" style="margin-bottom:18px;">
  <div class="card-title">Workspace</div>
  <form method="get" style="max-width:420px;">
    <div class="form-group">
      <label for="workspace_id">Select workspace</label>
      <select id="workspace_id" name="workspace_id" onchange="this.form.submit()">
        <?php foreach ($workspaces as $w): ?>
          <option value="<?php echo h((string) $w['workspace_id']); ?>"<?php echo $selected_wid === (int) $w['workspace_id'] ? ' selected' : ''; ?>>
            <?php echo h($w['workspace_name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>
</div>

<?php if ($selected_wid !== null && user_in_workspace($pdo, $selected_wid, $uid)): ?>

<?php if ($is_admin && $candidates !== []): ?>
<div class="card" style="margin-bottom:18px;">
  <div class="card-title">Add Member</div>
  <form method="post" action="workspace_members.php?workspace_id=<?php echo h((string) $selected_wid); ?>">
    <input type="hidden" name="action" value="add_member" />
    <input type="hidden" name="workspace_id" value="<?php echo h((string) $selected_wid); ?>" />
    <div class="form-grid">
      <div class="form-group">
        <label for="user_id">User</label>
        <select id="user_id" name="user_id" required>
          <?php foreach ($candidates as $c): ?>
            <option value="<?php echo h((string) $c['user_id']); ?>"><?php echo h($c['full_name'] . ' — ' . $c['email']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="member_role">Role</label>
        <select id="member_role" name="member_role" required>
          <option value="admin">admin</option>
          <option value="member" selected>member</option>
          <option value="viewer">viewer</option>
        </select>
      </div>
    </div>
    <div class="form-actions">
      <button type="submit">Add Member</button>
    </div>
  </form>
</div>
<?php elseif ($is_admin && $candidates === []): ?>
<div class="card" style="margin-bottom:18px;">
  <div class="card-title">Add Member</div>
  <p class="muted">All users are already members of this workspace.</p>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-title">Members</div>
  <table>
    <thead>
      <tr><th>Name</th><th>Email</th><th>Role</th><th>Joined</th><?php echo $is_admin ? '<th></th>' : ''; ?></tr>
    </thead>
    <tbody>
      <?php foreach ($members as $m): ?>
      <tr>
        <td><?php echo h($m['full_name']); ?></td>
        <td><?php echo h($m['email']); ?></td>
        <td>
          <?php if ($is_admin): ?>
          <form method="post" action="update_member.php" style="margin:0;">
            <input type="hidden" name="workspace_id" value="<?php echo h((string) $selected_wid); ?>" />
            <input type="hidden" name="user_id" value="<?php echo h((string) $m['user_id']); ?>" />
            <select name="member_role" onchange="this.form.submit()" aria-label="Role for <?php echo h($m['full_name']); ?>">
              <?php foreach (['admin', 'member', 'viewer'] as $r): ?>
                <option value="<?php echo h($r); ?>"<?php echo $m['member_role'] === $r ? ' selected' : ''; ?>><?php echo h($r); ?></option>
              <?php endforeach; ?>
            </select>
          </form>
          <?php else: ?>
            <?php echo h($m['member_role']); ?>
          <?php endif; ?>
        </td>
        <td class="mono"><?php echo h($m['joined_at']); ?></td>
        <?php if ($is_admin): ?>
        <td>
          <?php if ((int) $m['user_id'] !== $uid): ?>
          <form method="post" action="remove_member.php" style="margin:0;display:inline;" onsubmit="return confirm('Remove this member?');">
            <input type="hidden" name="workspace_id" value="<?php echo h((string) $selected_wid); ?>" />
            <input type="hidden" name="user_id" value="<?php echo h((string) $m['user_id']); ?>" />
            <button type="submit" class="secondary" style="background:transparent;color:#f47f7f;border-color:#5c2a2a;">Remove</button>
          </form>
          <?php else: ?>
            <span class="small muted">—</span>
          <?php endif; ?>
        </td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php else: ?>
  <div class="card"><p class="muted">Select a valid workspace.</p></div>
<?php endif; ?>

<?php endif; ?>

      </main>
    </div>
  </div>
</body>
</html>
