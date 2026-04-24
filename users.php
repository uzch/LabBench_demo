<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit_helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'reset_users') {
        try {
            reseed_team_users($pdo);
            set_flash(
                'success',
                'Reset complete. Team credentials: yasar@labbench.com/hash123, uzayr@labbench.com/hash234, ugonna@labbench.com/hash345, zuhaib@labbench.com/hash456'
            );
        } catch (Throwable $e) {
            set_flash('error', 'Could not reset team users. Please try again.');
        }
        redirect('users.php');
    }

    if ($action === 'create_user') {
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $isActive = (string) ($_POST['is_active'] ?? '1');
        $activeValue = $isActive === '0' ? 0 : 1;

        $errors = [];
        if ($fullName === '') {
            $errors[] = 'Full Name is required.';
        }
        if ($email === '') {
            $errors[] = 'Email is required.';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Email format is invalid.';
        }
        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }

        if ($errors !== []) {
            set_flash('error', implode(' ', $errors));
            redirect('users.php');
        }

        try {
            $st = $pdo->prepare(
                'INSERT INTO Users (full_name, email, password_hash, is_active)
                 VALUES (?, ?, ?, ?)'
            );
            $st->execute([$fullName, $email, password_hash($password, PASSWORD_DEFAULT), $activeValue]);
            set_flash('success', 'User created: ' . $email);
        } catch (PDOException $e) {
            $code = $e->errorInfo[1] ?? null;
            if ($code === 1062) {
                set_flash('error', 'Email already exists: ' . $email);
            } else {
                set_flash('error', 'Could not create user.');
            }
        }
        redirect('users.php');
    }
}

$st = $pdo->prepare(
    'SELECT u.user_id, u.full_name, u.email, u.is_active, wm.member_role AS workspace1_role
     FROM Users u
     LEFT JOIN WorkspaceMembers wm ON wm.user_id = u.user_id AND wm.workspace_id = 1
     ORDER BY u.user_id ASC'
);
$st->execute();
$users = $st->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LabBench - Users</title>
  <link rel="stylesheet" href="styles.css" />
</head>
<body>
  <div class="layout">
    <aside class="sidebar">
      <div class="logo">LABBENCH</div>
      <nav class="nav">
        <a href="projects.php">Projects</a>
        <a href="runs.html">All Runs</a>
        <a href="datasets.html">Datasets</a>
        <a href="model_registry.html">Model Registry</a>
        <a href="workspace_members.php">Workspace Members</a>
        <a href="audit_log.php">Audit Log</a>
        <a href="logout.php">Log Out</a>
      </nav>
    </aside>
    <div class="main">
      <header class="header">
        <div>Users</div>
        <div class="header-right">Team account management</div>
      </header>
      <main class="content">
        <?php show_flash(); ?>

        <h1 class="page-title">Team Users</h1>
        <p class="page-sub">Seed team users and manage additional accounts.</p>

        <div class="card" style="margin-bottom:18px;">
          <div class="card-title">Team Users</div>
          <form method="post" style="margin-bottom:12px;">
            <input type="hidden" name="action" value="reset_users" />
            <button type="submit">Reset All Team Users</button>
          </form>
          <table>
            <thead>
              <tr>
                <th>User ID</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Status</th>
                <th>Role (Workspace 1)</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($users === []): ?>
              <tr>
                <td colspan="5" class="placeholder">No users found.</td>
              </tr>
              <?php else: ?>
                <?php foreach ($users as $u): ?>
              <tr>
                <td class="mono"><?php echo h((string) $u['user_id']); ?></td>
                <td><?php echo h((string) $u['full_name']); ?></td>
                <td><?php echo h((string) $u['email']); ?></td>
                <td>
                  <?php if ((int) $u['is_active'] === 1): ?>
                    <span class="badge" style="background:rgba(34,197,94,0.15);border:1px solid #2d6a4f;color:#9be7b5;">Active</span>
                  <?php else: ?>
                    <span class="badge" style="background:rgba(148,163,184,0.18);border:1px solid #64748b;color:#cbd5e1;">Inactive</span>
                  <?php endif; ?>
                </td>
                <td><?php echo h((string) ($u['workspace1_role'] ?? '—')); ?></td>
              </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="card">
          <div class="card-title">Create New User</div>
          <form method="post" action="users.php">
            <input type="hidden" name="action" value="create_user" />
            <div class="form-grid single">
              <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" required />
              </div>
              <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required />
              </div>
              <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" minlength="6" required />
              </div>
              <div class="form-group">
                <label for="is_active">Account Status</label>
                <select id="is_active" name="is_active">
                  <option value="1">Active</option>
                  <option value="0">Inactive</option>
                </select>
              </div>
            </div>
            <div class="form-actions">
              <button type="submit">Create User</button>
            </div>
          </form>
        </div>
      </main>
    </div>
  </div>
</body>
</html>
