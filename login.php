<?php
require_once 'db.php';
require_once 'audit_helpers.php';

$st = $pdo->prepare("SELECT COUNT(*) FROM Users WHERE password_hash LIKE '$2y$%'");
$st->execute();
if ((int) $st->fetchColumn() === 0) {
    reseed_team_users($pdo);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? 'login';

    if ($mode === 'signup') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $isActive = ($_POST['is_active'] ?? 'true') === 'true' ? 1 : 0;

        if ($fullName !== '' && $email !== '' && $password !== '') {
            try {
                $pdo->prepare(
                    'INSERT INTO Users (full_name, email, password_hash, is_active) VALUES (?, ?, ?, ?)'
                )->execute([$fullName, $email, password_hash($password, PASSWORD_DEFAULT), $isActive]);
                $message = 'Account created. You can log in with that email.';
            } catch (PDOException $e) {
                $message = 'That email may already exist.';
            }
        } else {
            $message = 'Please complete every account field.';
        }
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare(
            'SELECT user_id, full_name, password_hash FROM Users WHERE email = ? AND is_active = TRUE'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $email;
            $_SESSION['is_logged_in'] = true;

            header('Location: projects.php');
            exit;
        }

        $message = 'Login was not accepted. Check the email and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LabBench - Login</title>
  <link rel="stylesheet" href="styles.css" />
</head>
<body>
  <main class="auth-shell">
    <section class="auth-card">
      <div class="logo">LABBENCH</div>
      <h1 class="page-title">Login</h1>
      <p class="page-sub">Access the LabBench project workspace.</p>

      <?php if ($message): ?>
        <p class="note"><?= h($message) ?></p>
      <?php endif; ?>

      <form action="login.php" method="post">
        <input type="hidden" name="mode" value="login" />
        <div class="form-group">
          <label for="login_email">Email</label>
          <input type="email" id="login_email" name="email" placeholder="user@example.com" required />
        </div>
        <div class="form-group">
          <label for="login_password">Password</label>
          <input type="password" id="login_password" name="password" required />
        </div>
        <div class="form-actions">
          <button type="submit">Log In</button>
          <a class="button secondary" href="projects.php">Continue to Projects</a>
        </div>
      </form>

      <hr />

      <h2 class="card-title">Create Account</h2>
      <form action="login.php" method="post">
        <input type="hidden" name="mode" value="signup" />
        <div class="form-grid single">
          <div class="form-group">
            <label for="full_name">Full Name</label>
            <input type="text" id="full_name" name="full_name" placeholder="Jane Smith" required />
          </div>
          <div class="form-group">
            <label for="signup_email">Email</label>
            <input type="email" id="signup_email" name="email" placeholder="jane@example.com" required />
          </div>
          <div class="form-group">
            <label for="signup_password">Password</label>
            <input type="password" id="signup_password" name="password" required />
          </div>
          <div class="form-group">
            <label for="is_active">Account Status</label>
            <select id="is_active" name="is_active">
              <option value="true">Active</option>
              <option value="false">Inactive</option>
            </select>
          </div>
        </div>
        <div class="form-actions">
          <button type="submit">Create Account</button>
        </div>
      </form>
    </section>
  </main>
</body>
</html>
