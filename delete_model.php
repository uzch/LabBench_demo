<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit_helpers.php';

require_login();
$uid = current_user_id();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['model_id'])) {
    redirect('projects.php');
}

$mid = ctype_digit((string) $_POST['model_id']) ? (int) $_POST['model_id'] : 0;
if ($mid <= 0) {
    set_flash('error', 'Invalid model.');
    redirect('projects.php');
}

$st = $pdo->prepare(
    'SELECT m.model_id, p.project_id, p.workspace_id FROM Models m
     INNER JOIN Projects p ON p.project_id = m.project_id
     INNER JOIN WorkspaceMembers wm ON wm.workspace_id = p.workspace_id AND wm.user_id = ?
     WHERE m.model_id = ?'
);
$st->execute([$uid, $mid]);
$row = $st->fetch();
if (!$row) {
    set_flash('error', 'Model not found or access denied.');
    redirect('projects.php');
}

$pid = (int) $row['project_id'];
$wid = (int) $row['workspace_id'];

try {
    $pdo->beginTransaction();

    // 1. RunMetrics for runs belonging to this model
    $st = $pdo->prepare(
        'DELETE FROM RunMetrics WHERE run_id IN (
            SELECT run_id FROM Runs WHERE model_id = ?
        )'
    );
    $st->execute([$mid]);

    // 2. RunParams for those runs
    $st = $pdo->prepare(
        'DELETE FROM RunParams WHERE run_id IN (
            SELECT run_id FROM Runs WHERE model_id = ?
        )'
    );
    $st->execute([$mid]);

    // 3. ModelRegistry rows referencing this model
    $st = $pdo->prepare('DELETE FROM ModelRegistry WHERE model_id = ?');
    $st->execute([$mid]);

    // 4. Runs for this model
    $st = $pdo->prepare('DELETE FROM Runs WHERE model_id = ?');
    $st->execute([$mid]);

    // 5. The model itself
    $st = $pdo->prepare('DELETE FROM Models WHERE model_id = ?');
    $st->execute([$mid]);

    log_audit($pdo, $wid, 'delete', 'model', $mid);

    $pdo->commit();
    set_flash('success', 'Model deleted.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', 'Could not delete model.');
}

redirect('project_detail.php?id=' . $pid);
