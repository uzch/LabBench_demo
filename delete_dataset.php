<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit_helpers.php';

require_login();
$uid = current_user_id();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['dataset_id'])) {
    redirect('projects.php');
}

$did = ctype_digit((string) $_POST['dataset_id']) ? (int) $_POST['dataset_id'] : 0;
if ($did <= 0) {
    set_flash('error', 'Invalid dataset.');
    redirect('projects.php');
}

$st = $pdo->prepare(
    'SELECT d.dataset_id, p.project_id, p.workspace_id FROM Datasets d
     INNER JOIN Projects p ON p.project_id = d.project_id
     INNER JOIN WorkspaceMembers wm ON wm.workspace_id = p.workspace_id AND wm.user_id = ?
     WHERE d.dataset_id = ?'
);
$st->execute([$uid, $did]);
$row = $st->fetch();
if (!$row) {
    set_flash('error', 'Dataset not found or access denied.');
    redirect('projects.php');
}

$pid = (int) $row['project_id'];
$wid = (int) $row['workspace_id'];

try {
    $pdo->beginTransaction();

    // 1. RunMetrics for runs that reference versions of this dataset
    $st = $pdo->prepare(
        'DELETE FROM RunMetrics WHERE run_id IN (
            SELECT run_id FROM Runs WHERE dataset_version_id IN (
                SELECT dataset_version_id FROM DatasetVersions WHERE dataset_id = ?
            )
        )'
    );
    $st->execute([$did]);

    // 2. RunParams for those runs
    $st = $pdo->prepare(
        'DELETE FROM RunParams WHERE run_id IN (
            SELECT run_id FROM Runs WHERE dataset_version_id IN (
                SELECT dataset_version_id FROM DatasetVersions WHERE dataset_id = ?
            )
        )'
    );
    $st->execute([$did]);

    // 3. ModelRegistry rows tied to those runs
    $st = $pdo->prepare(
        'DELETE FROM ModelRegistry WHERE source_run_id IN (
            SELECT run_id FROM Runs WHERE dataset_version_id IN (
                SELECT dataset_version_id FROM DatasetVersions WHERE dataset_id = ?
            )
        )'
    );
    $st->execute([$did]);

    // 4. Runs referencing versions of this dataset
    $st = $pdo->prepare(
        'DELETE FROM Runs WHERE dataset_version_id IN (
            SELECT dataset_version_id FROM DatasetVersions WHERE dataset_id = ?
        )'
    );
    $st->execute([$did]);

    // 5. DatasetVersions
    $st = $pdo->prepare('DELETE FROM DatasetVersions WHERE dataset_id = ?');
    $st->execute([$did]);

    // 6. Dataset itself
    $st = $pdo->prepare('DELETE FROM Datasets WHERE dataset_id = ?');
    $st->execute([$did]);

    log_audit($pdo, $wid, 'delete', 'dataset', $did);

    $pdo->commit();
    set_flash('success', 'Dataset deleted.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', 'Could not delete dataset.');
}

redirect('project_detail.php?id=' . $pid);
