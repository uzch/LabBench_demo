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

if ($wid <= 0 || $target <= 0) {
    set_flash('error', 'Invalid request.');
    redirect('workspace_members.php');
}

if ($target === $uid) {
    set_flash('error', 'You cannot remove yourself from the workspace.');
    redirect('workspace_members.php?workspace_id=' . $wid);
}

if (!user_in_workspace($pdo, $wid, $uid) || !user_is_admin_in_workspace($pdo, $wid, $uid)) {
    set_flash('error', 'You do not have permission to remove members.');
    redirect('workspace_members.php?workspace_id=' . $wid);
}

$st = $pdo->prepare('SELECT 1 FROM WorkspaceMembers WHERE workspace_id = ? AND user_id = ?');
$st->execute([$wid, $target]);
if (!$st->fetchColumn()) {
    set_flash('error', 'Member not found.');
    redirect('workspace_members.php?workspace_id=' . $wid);
}

try {
    $pdo->beginTransaction();
    $del = $pdo->prepare('DELETE FROM WorkspaceMembers WHERE workspace_id = ? AND user_id = ?');
    $del->execute([$wid, $target]);
    log_audit($pdo, $wid, 'delete', 'workspace_member', $target);
    $pdo->commit();
    set_flash('success', 'Member removed.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', 'Could not remove member.');
}

redirect('workspace_members.php?workspace_id=' . $wid);
