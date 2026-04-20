<?php
/**
 * LabBench — helpers for WorkspaceMembers, Projects, AuditLog (Person 2).
 */

declare(strict_types=1);

function h(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        redirect('login.php');
    }
}

function current_user_id(): int
{
    return (int) $_SESSION['user_id'];
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

function set_flash(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function show_flash(): void
{
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
        'login' => ['href' => 'login.html', 'label' => 'Log Out'],
    ];
    foreach ($links as $key => $info) {
        $class = $key === $active ? ' class="active"' : '';
        echo '<a href="' . h($info['href']) . '"' . $class . '>' . h($info['label']) . '</a>' . "\n";
    }
}
