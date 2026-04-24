<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit_helpers.php';

require_login();
$uid = current_user_id();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('workspace_members.php');
}

$wid = isset($_POST['workspace_id']) && ctype_digit((string) $_POST['workspace_id'])
    ? (int) $_POST['workspace_id']
    : 0;
$target = isset($_POST['user_id']) && ctype_digit((string) $_POST['user_id'])
    ? (int) $_POST['user_id']
    : 0;
$role = (string) ($_POST['member_role'] ?? '');
$allowed = ['admin', 'member', 'viewer'];

if ($wid <= 0 || $target <= 0 || !in_array($role, $allowed, true)) {
    set_flash('error', 'Invalid request.');
    redirect('workspace_members.php' . ($wid > 0 ? '?workspace_id=' . $wid : ''));
}

if (!user_in_workspace($pdo, $wid, $uid) || !user_is_admin_in_workspace($pdo, $wid, $uid)) {
    set_flash('error', 'You do not have permission to change roles.');
    redirect('workspace_members.php?workspace_id=' . $wid);
}

$st = $pdo->prepare('SELECT member_role FROM WorkspaceMembers WHERE workspace_id = ? AND user_id = ?');
$st->execute([$wid, $target]);
$current = $st->fetchColumn();
if ($current === false) {
    set_flash('error', 'Member not found.');
    redirect('workspace_members.php?workspace_id=' . $wid);
}
$currentRole = (string) $current;

if ($target === $uid && $currentRole === 'admin' && $role !== 'admin') {
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM WorkspaceMembers WHERE workspace_id = ? AND member_role = 'admin'"
    );
    $st->execute([$wid]);
    $adminCount = (int) $st->fetchColumn();
    if ($adminCount <= 1) {
        set_flash('error', 'Cannot demote yourself: you are the only admin in this workspace.');
        redirect('workspace_members.php?workspace_id=' . $wid);
    }
}

if ($role === $currentRole) {
    redirect('workspace_members.php?workspace_id=' . $wid);
}

try {
    $pdo->beginTransaction();
    $up = $pdo->prepare('UPDATE WorkspaceMembers SET member_role = ? WHERE workspace_id = ? AND user_id = ?');
    $up->execute([$role, $wid, $target]);
    log_audit($pdo, $wid, 'update', 'workspace_member', $target);
    $pdo->commit();
    set_flash('success', 'Role updated.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', 'Could not update role.');
}

redirect('workspace_members.php?workspace_id=' . $wid);
