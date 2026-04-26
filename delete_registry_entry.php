<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit_helpers.php';

require_login();
$uid = current_user_id();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['model_version_id'])) {
    redirect('model_registry.php');
}

$id = ctype_digit((string) $_POST['model_version_id']) ? (int) $_POST['model_version_id'] : 0;
if ($id <= 0) {
    set_flash('error', 'Invalid registry entry.');
    redirect('model_registry.php');
}

$st = $pdo->prepare(
    'SELECT mr.model_version_id, p.workspace_id FROM ModelRegistry mr
     INNER JOIN Models m ON m.model_id = mr.model_id
     INNER JOIN Projects p ON p.project_id = m.project_id
     INNER JOIN WorkspaceMembers wm ON wm.workspace_id = p.workspace_id AND wm.user_id = ?
     WHERE mr.model_version_id = ?'
);
$st->execute([$uid, $id]);
$row = $st->fetch();
if (!$row) {
    set_flash('error', 'Registry entry not found or access denied.');
    redirect('model_registry.php');
}

$wid = (int) $row['workspace_id'];

if (!user_is_admin_in_workspace($pdo, $wid, $uid)) {
    set_flash('error', 'Only workspace admins can delete registry entries.');
    redirect('model_registry.php');
}

try {
    $pdo->beginTransaction();
    $st = $pdo->prepare('DELETE FROM ModelRegistry WHERE model_version_id = ?');
    $st->execute([$id]);
    log_audit($pdo, $wid, 'delete', 'model_registry', $id);
    $pdo->commit();
    set_flash('success', 'Registry entry deleted.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', 'Could not delete registry entry.');
}

redirect('model_registry.php');
