<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit_helpers.php';

require_login();
$uid = current_user_id();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['project_id'])) {
    redirect('projects.php');
}

$pid = ctype_digit((string) $_POST['project_id']) ? (int) $_POST['project_id'] : 0;
if ($pid <= 0) {
    set_flash('error', 'Invalid project.');
    redirect('projects.php');
}

$st = $pdo->prepare(
    'SELECT p.project_id, p.workspace_id FROM Projects p
     INNER JOIN WorkspaceMembers wm ON wm.workspace_id = p.workspace_id AND wm.user_id = ?
     WHERE p.project_id = ?'
);
$st->execute([$uid, $pid]);
$row = $st->fetch();
if (!$row) {
    set_flash('error', 'Project not found or access denied.');
    redirect('projects.php');
}

$wid = (int) $row['workspace_id'];

try {
    $pdo->beginTransaction();

    // 1. RunMetrics for runs of models in this project
    $st = $pdo->prepare(
        'DELETE FROM RunMetrics WHERE run_id IN (
            SELECT run_id FROM Runs WHERE model_id IN (
                SELECT model_id FROM Models WHERE project_id = ?
            )
        )'
    );
    $st->execute([$pid]);

    // 2. RunParams for those runs
    $st = $pdo->prepare(
        'DELETE FROM RunParams WHERE run_id IN (
            SELECT run_id FROM Runs WHERE model_id IN (
                SELECT model_id FROM Models WHERE project_id = ?
            )
        )'
    );
    $st->execute([$pid]);

    // 3. ModelRegistry rows tied to those runs
    $st = $pdo->prepare(
        'DELETE FROM ModelRegistry WHERE source_run_id IN (
            SELECT run_id FROM Runs WHERE model_id IN (
                SELECT model_id FROM Models WHERE project_id = ?
            )
        )'
    );
    $st->execute([$pid]);

    // 4. Runs for models in this project
    $st = $pdo->prepare(
        'DELETE FROM Runs WHERE model_id IN (
            SELECT model_id FROM Models WHERE project_id = ?
        )'
    );
    $st->execute([$pid]);

    // 5. DatasetVersions for datasets in this project
    $st = $pdo->prepare(
        'DELETE FROM DatasetVersions WHERE dataset_id IN (
            SELECT dataset_id FROM Datasets WHERE project_id = ?
        )'
    );
    $st->execute([$pid]);

    // 6. Datasets
    $st = $pdo->prepare('DELETE FROM Datasets WHERE project_id = ?');
    $st->execute([$pid]);

    // 7. Models
    $st = $pdo->prepare('DELETE FROM Models WHERE project_id = ?');
    $st->execute([$pid]);

    // 8. Project row
    $st = $pdo->prepare('DELETE FROM Projects WHERE project_id = ?');
    $st->execute([$pid]);

    // 9. Audit (same transaction)
    log_audit($pdo, $wid, 'delete', 'project', $pid);

    $pdo->commit();
    set_flash('success', 'Project deleted.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', 'Could not delete project.');
}

redirect('projects.php');
