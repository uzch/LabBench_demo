<?php
/**
 * LabBench — helpers for WorkspaceMembers, Projects, AuditLog (Person 2).
 */

declare(strict_types=1);

if (!function_exists('h')) {
    function h(?string $v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): void
    {
        header('Location: ' . $path);
        exit;
    }
}

if (!function_exists('ensure_session_started')) {
    function ensure_session_started(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}

if (!function_exists('require_login')) {
    function require_login(): void
    {
        ensure_session_started();
        if (empty($_SESSION['user_id'])) {
            redirect('login.php');
        }
    }
}

if (!function_exists('current_user_id')) {
    function current_user_id(): int
    {
        ensure_session_started();
        return (int) ($_SESSION['user_id'] ?? 0);
    }
}

function log_audit(
    PDO $pdo,
    int $workspace_id,
    string $action_type,
    string $entity_type,
    int $entity_id
): void {
    $uid = current_user_id();
    $sql = 'INSERT INTO AuditLog (workspace_id, actor_user_id, action_type, entity_type, entity_id, action_timestamp)
            VALUES (?, ?, ?, ?, ?, NOW())';
    $st = $pdo->prepare($sql);
    $st->execute([$workspace_id, $uid, $action_type, $entity_type, $entity_id]);
}

/**
 * @param array<string,mixed> $input
 * @return array<int,string>
 */
function validate_project(array $input, bool $is_create): array
{
    $errors = [];
    if ($is_create) {
        $wid = $input['workspace_id'] ?? '';
        if ($wid === '' || !ctype_digit((string) $wid)) {
            $errors[] = 'Please select a workspace.';
        }
    }
    $name = trim((string) ($input['project_name'] ?? ''));
    if ($name === '') {
        $errors[] = 'Project name is required.';
    } elseif (mb_strlen($name) > 150) {
        $errors[] = 'Project name must be at most 150 characters.';
    }
    $desc = $input['description'] ?? null;
    if ($desc !== null && $desc !== '' && !is_string($desc)) {
        $errors[] = 'Invalid description.';
    }
    return $errors;
}

/**
 * @param array<string,mixed> $input
 * @return array<int,string>
 */
function validate_member(array $input): array
{
    $errors = [];
    $wid = $input['workspace_id'] ?? '';
    if ($wid === '' || !ctype_digit((string) $wid)) {
        $errors[] = 'Please select a workspace.';
    }
    $uid = $input['user_id'] ?? '';
    if ($uid === '' || !ctype_digit((string) $uid)) {
        $errors[] = 'Please select a user to add.';
    }
    $role = (string) ($input['member_role'] ?? '');
    $allowed = ['admin', 'member', 'viewer'];
    if (!in_array($role, $allowed, true)) {
        $errors[] = 'Role must be admin, member, or viewer.';
    }
    return $errors;
}

function user_in_workspace(PDO $pdo, int $wid, int $uid): bool
{
    $st = $pdo->prepare('SELECT 1 FROM WorkspaceMembers WHERE workspace_id = ? AND user_id = ? LIMIT 1');
    $st->execute([$wid, $uid]);
    return (bool) $st->fetchColumn();
}

function user_is_admin_in_workspace(PDO $pdo, int $wid, int $uid): bool
{
    $st = $pdo->prepare(
        "SELECT 1 FROM WorkspaceMembers WHERE workspace_id = ? AND user_id = ? AND member_role = 'admin' LIMIT 1"
    );
    $st->execute([$wid, $uid]);
    return (bool) $st->fetchColumn();
}

/**
 * @return array<int,array<string,mixed>>
 */
function workspaces_for_user(PDO $pdo, int $uid): array
{
    $st = $pdo->prepare(
        'SELECT w.workspace_id, w.workspace_name
         FROM Workspaces w
         INNER JOIN WorkspaceMembers wm ON wm.workspace_id = w.workspace_id
         WHERE wm.user_id = ?
         ORDER BY w.workspace_name'
    );
    $st->execute([$uid]);
    return $st->fetchAll();
}

function reseed_team_users(PDO $pdo): void
{
    $pdo->beginTransaction();
    try {
        foreach (
            [
                'DELETE FROM AuditLog',
                'DELETE FROM ModelRegistry',
                'DELETE FROM RunMetrics',
                'DELETE FROM RunParams',
                'DELETE FROM Runs',
                'DELETE FROM DatasetVersions',
                'DELETE FROM Datasets',
                'DELETE FROM Models',
                'DELETE FROM Projects',
                'DELETE FROM WorkspaceMembers',
                'DELETE FROM Users',
                'DELETE FROM Workspaces',
            ] as $sql
        ) {
            $st = $pdo->prepare($sql);
            $st->execute();
        }

        $st = $pdo->prepare(
            'INSERT INTO Users (user_id, full_name, email, password_hash, created_at, is_active)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach (
            [
                [1, 'Yasar Labib', 'yasar@labbench.com', 'hash123', '2026-03-01 9:00:00', 1],
                [2, 'Uzayr Chaudhry', 'uzayr@labbench.com', 'hash234', '2026-03-01 9:10:00', 1],
                [3, 'Ugonna Anyalemechi', 'ugonna@labbench.com', 'hash345', '2026-03-01 9:20:00', 1],
                [4, 'Zuhaib Buchh', 'zuhaib@labbench.com', 'hash456', '2026-03-01 9:30:00', 1],
            ] as $u
        ) {
            [$id, $name, $email, $password, $createdAt, $active] = $u;
            $st->execute([$id, $name, $email, password_hash($password, PASSWORD_DEFAULT), $createdAt, $active]);
        }

        $st = $pdo->prepare(
            'INSERT INTO Workspaces (workspace_id, workspace_name, created_at)
             VALUES (?, ?, ?)'
        );
        foreach (
            [
                [1, 'Team18 Main Workspace', '2026-03-02 10:00:00'],
                [2, 'Research Workspace', '2026-03-02 10:30:00'],
            ] as $w
        ) {
            $st->execute($w);
        }

        $st = $pdo->prepare(
            'INSERT INTO WorkspaceMembers (workspace_id, user_id, member_role, joined_at)
             VALUES (?, ?, ?, ?)'
        );
        foreach (
            [
                [1, 1, 'admin', '2026-03-02 11:00:00'],
                [1, 2, 'member', '2026-03-02 11:05:00'],
                [1, 3, 'member', '2026-03-02 11:10:00'],
                [1, 4, 'member', '2026-03-02 11:15:00'],
                [2, 1, 'admin', '2026-03-02 11:20:00'],
                [2, 3, 'member', '2026-03-02 11:25:00'],
            ] as $wm
        ) {
            $st->execute($wm);
        }

        $st = $pdo->prepare(
            'INSERT INTO Projects (project_id, workspace_id, project_name, description, created_by_user_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach (
            [
                [1, 1, 'Image Classification', 'CNN experiments for classifying images', 1, '2026-03-03 12:00:00'],
                [2, 1, 'Text Sentiment Analysis', 'NLP project for sentiment prediction', 2, '2026-03-03 12:30:00'],
                [3, 2, 'Fraud Detection', 'Tabular ML project for fraud detection', 1, '2026-03-03 13:00:00'],
            ] as $p
        ) {
            $st->execute($p);
        }

        $st = $pdo->prepare(
            'INSERT INTO Datasets (dataset_id, project_id, dataset_name, modality, source_type)
             VALUES (?, ?, ?, ?, ?)'
        );
        foreach (
            [
                [1, 1, 'CIFAR-10', 'image', 'public'],
                [2, 2, 'IMDB Reviews', 'text', 'public'],
                [3, 3, 'Transaction Records', 'tabular', 'internal'],
            ] as $d
        ) {
            $st->execute($d);
        }

        $st = $pdo->prepare(
            'INSERT INTO DatasetVersions (dataset_version_id, dataset_id, version_tag, row_count, schema_hash, created_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach (
            [
                [1, 1, 'v1', 50000, 'abc123def456', '2026-03-04 9:00:00'],
                [2, 1, 'v2', 52000, 'abc999def888', '2026-03-05 9:00:00'],
                [3, 2, 'v1', 25000, 'imdb111aaa222', '2026-03-04 10:00:00'],
                [4, 3, 'v1', 100000, 'fraud333bbb444', '2026-03-04 11:00:00'],
            ] as $dv
        ) {
            $st->execute($dv);
        }

        $st = $pdo->prepare(
            'INSERT INTO Models (model_id, project_id, model_name, description, created_at)
             VALUES (?, ?, ?, ?, ?)'
        );
        foreach (
            [
                [1, 1, 'ResNet Baseline', 'Baseline CNN model for image classification', '2026-03-05 14:00:00'],
                [2, 2, 'BERT Sentiment', 'Transformer model for sentiment analysis', '2026-03-05 14:30:00'],
                [3, 3, 'XGBoost Fraud', 'Boosted trees for fraud detection', '2026-03-05 15:00:00'],
            ] as $m
        ) {
            $st->execute($m);
        }

        $st = $pdo->prepare(
            'INSERT INTO Runs (run_id, model_id, dataset_version_id, created_by_user_id, started_at, ended_at, status, code_version_tag, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach (
            [
                [1, 1, 1, 1, '2026-03-06 8:00:00', '2026-03-06 10:00:00', 'completed', 'commit_a1', 'Initial baseline run'],
                [2, 1, 2, 2, '2026-03-06 11:00:00', '2026-03-06 13:00:00', 'completed', 'commit_b2', 'Improved preprocessing'],
                [3, 2, 3, 3, '2026-03-06 14:00:00', '2026-03-06 16:00:00', 'failed', 'commit_c3', 'Tokenizer issue'],
                [4, 3, 4, 1, '2026-03-07 9:00:00', '2026-03-07 11:30:00', 'completed', 'commit_d4', 'Good fraud detection results'],
            ] as $r
        ) {
            $st->execute($r);
        }

        $st = $pdo->prepare(
            'INSERT INTO RunParams (run_id, param_key, param_value)
             VALUES (?, ?, ?)'
        );
        foreach (
            [
                [1, 'learning_rate', '0.001'],
                [1, 'batch_size', '32'],
                [2, 'learning_rate', '0.0005'],
                [2, 'batch_size', '64'],
                [3, 'max_length', '256'],
                [4, 'n_estimators', '200'],
                [4, 'max_depth', '8'],
            ] as $rp
        ) {
            $st->execute($rp);
        }

        $st = $pdo->prepare(
            'INSERT INTO RunMetrics (run_id, metric_key, metric_value, step, recorded_at)
             VALUES (?, ?, ?, ?, ?)'
        );
        foreach (
            [
                [1, 'accuracy', '0.8125', 1, '2026-03-06 9:00:00'],
                [1, 'loss', '0.5421', 1, '2026-03-06 9:00:00'],
                [2, 'accuracy', '0.8542', 1, '2026-03-06 12:00:00'],
                [2, 'loss', '0.4305', 1, '2026-03-06 12:00:00'],
                [3, 'accuracy', '0.701', 1, '2026-03-06 15:00:00'],
                [4, 'f1', '0.9123', 1, '2026-03-07 10:30:00'],
                [4, 'precision', '0.905', 1, '2026-03-07 10:30:00'],
            ] as $rm
        ) {
            $st->execute($rm);
        }

        $st = $pdo->prepare(
            'INSERT INTO ModelRegistry (model_version_id, model_id, source_run_id, stage, approved_by_user_id, approved_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach (
            [
                [1, 1, 2, 'staging', 1, '2026-03-08 10:00:00'],
                [2, 3, 4, 'production', 1, '2026-03-08 11:00:00'],
            ] as $mr
        ) {
            $st->execute($mr);
        }

        $st = $pdo->prepare(
            'INSERT INTO AuditLog (log_id, workspace_id, actor_user_id, action_type, entity_type, entity_id, action_timestamp)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        foreach (
            [
                [1, 1, 1, 'create', 'project', 1, '2026-03-03 12:00:00'],
                [2, 1, 2, 'create', 'project', 2, '2026-03-03 12:30:00'],
                [3, 2, 1, 'create', 'project', 3, '2026-03-03 13:00:00'],
                [4, 1, 1, 'approve', 'model_registry', 1, '2026-03-08 10:00:00'],
                [5, 2, 1, 'approve', 'model_registry', 2, '2026-03-08 11:00:00'],
            ] as $al
        ) {
            $st->execute($al);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

if (!function_exists('set_flash')) {
    function set_flash(string $type, string $msg): void
    {
        ensure_session_started();
        $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
    }
}

if (!function_exists('show_flash')) {
    function show_flash(): void
    {
        ensure_session_started();
        if (empty($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
            return;
        }
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        $type = $f['type'] ?? 'error';
        $msg = (string) ($f['msg'] ?? '');
        if ($msg === '') {
            return;
        }
        $style = $type === 'success'
            ? 'border:1px solid #2d6a4f;background:rgba(34,197,94,0.12);'
            : 'border:1px solid #9b2c2c;background:rgba(239,68,68,0.12);';
        echo '<div class="card" style="' . h($style) . 'margin-bottom:18px;">' . h($msg) . '</div>';
    }
}

/**
 * Sidebar nav: $active is one of: projects, runs, datasets, model_registry, workspace_members, audit_log, login
 */
function render_sidebar(string $active): void
{
    $links = [
        'projects' => ['href' => 'projects.php', 'label' => 'Projects'],
        'runs' => ['href' => 'runs.html', 'label' => 'All Runs'],
        'datasets' => ['href' => 'datasets.html', 'label' => 'Datasets'],
        'model_registry' => ['href' => 'model_registry.html', 'label' => 'Model Registry'],
        'workspace_members' => ['href' => 'workspace_members.php', 'label' => 'Workspace Members'],
        'audit_log' => ['href' => 'audit_log.php', 'label' => 'Audit Log'],
        'login' => ['href' => 'logout.php', 'label' => 'Log Out'],
    ];
    foreach ($links as $key => $info) {
        $class = $key === $active ? ' class="active"' : '';
        echo '<a href="' . h($info['href']) . '"' . $class . '>' . h($info['label']) . '</a>' . "\n";
    }
}
