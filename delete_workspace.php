<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit_helpers.php';

require_login();
$uid = current_user_id();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['workspace_id'])) {
    redirect('workspace.php');
}

$wid = ctype_digit((string) $_POST['workspace_id']) ? (int) $_POST['workspace_id'] : 0;
if ($wid <= 0) {
    set_flash('error', 'Invalid workspace.');
    redirect('workspace.php');
}

if (!user_in_workspace($pdo, $wid, $uid) || !user_is_admin_in_workspace($pdo, $wid, $uid)) {
    set_flash('error', 'You do not have permission to delete that workspace.');
    redirect('workspace.php');
}

$st = $pdo->prepare('SELECT workspace_name FROM Workspaces WHERE workspace_id = ?');
$st->execute([$wid]);
$workspace = $st->fetch();
if (!$workspace) {
    set_flash('error', 'Workspace not found.');
    redirect('workspace.php');
}

try {
    $pdo->beginTransaction();

    $st = $pdo->prepare(
        'DELETE FROM RunMetrics WHERE run_id IN (
            SELECT r.run_id
            FROM Runs r
            INNER JOIN Models m ON m.model_id = r.model_id
            INNER JOIN Projects p ON p.project_id = m.project_id
            WHERE p.workspace_id = ?
        )'
    );
    $st->execute([$wid]);

    $st = $pdo->prepare(
        'DELETE FROM RunParams WHERE run_id IN (
            SELECT r.run_id
            FROM Runs r
            INNER JOIN Models m ON m.model_id = r.model_id
            INNER JOIN Projects p ON p.project_id = m.project_id
            WHERE p.workspace_id = ?
        )'
    );
    $st->execute([$wid]);

    $st = $pdo->prepare(
        'DELETE FROM ModelRegistry WHERE source_run_id IN (
            SELECT r.run_id
            FROM Runs r
            INNER JOIN Models m ON m.model_id = r.model_id
            INNER JOIN Projects p ON p.project_id = m.project_id
            WHERE p.workspace_id = ?
        )'
    );
    $st->execute([$wid]);

    $st = $pdo->prepare(
        'DELETE mr FROM ModelRegistry mr
         INNER JOIN Models m ON m.model_id = mr.model_id
         INNER JOIN Projects p ON p.project_id = m.project_id
         WHERE p.workspace_id = ?'
    );
    $st->execute([$wid]);

    $st = $pdo->prepare(
        'DELETE FROM Runs WHERE model_id IN (
            SELECT m.model_id
            FROM Models m
            INNER JOIN Projects p ON p.project_id = m.project_id
            WHERE p.workspace_id = ?
        )'
    );
    $st->execute([$wid]);

    $st = $pdo->prepare(
        'DELETE FROM DatasetVersions WHERE dataset_id IN (
            SELECT d.dataset_id
            FROM Datasets d
            INNER JOIN Projects p ON p.project_id = d.project_id
            WHERE p.workspace_id = ?
        )'
    );
    $st->execute([$wid]);

    $st = $pdo->prepare(
        'DELETE d FROM Datasets d
         INNER JOIN Projects p ON p.project_id = d.project_id
         WHERE p.workspace_id = ?'
    );
    $st->execute([$wid]);

    $st = $pdo->prepare(
        'DELETE m FROM Models m
         INNER JOIN Projects p ON p.project_id = m.project_id
         WHERE p.workspace_id = ?'
    );
    $st->execute([$wid]);

    $st = $pdo->prepare('DELETE FROM Projects WHERE workspace_id = ?');
    $st->execute([$wid]);

    $st = $pdo->prepare('DELETE FROM AuditLog WHERE workspace_id = ?');
    $st->execute([$wid]);

    $st = $pdo->prepare('DELETE FROM WorkspaceMembers WHERE workspace_id = ?');
    $st->execute([$wid]);

    $st = $pdo->prepare('DELETE FROM Workspaces WHERE workspace_id = ?');
    $st->execute([$wid]);

    $pdo->commit();
    set_flash('success', 'Workspace deleted: ' . (string) $workspace['workspace_name']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', 'Could not delete workspace.');
}

redirect('workspace.php');
