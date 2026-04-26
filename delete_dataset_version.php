<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit_helpers.php';

require_login();
$uid = current_user_id();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['dataset_version_id'])) {
    redirect('projects.php');
}

$vid = ctype_digit((string) $_POST['dataset_version_id']) ? (int) $_POST['dataset_version_id'] : 0;
if ($vid <= 0) {
    set_flash('error', 'Invalid dataset version.');
    redirect('projects.php');
}

$st = $pdo->prepare(
    'SELECT dv.dataset_version_id, d.dataset_id, p.project_id, p.workspace_id FROM DatasetVersions dv
     INNER JOIN Datasets d ON d.dataset_id = dv.dataset_id
     INNER JOIN Projects p ON p.project_id = d.project_id
     INNER JOIN WorkspaceMembers wm ON wm.workspace_id = p.workspace_id AND wm.user_id = ?
     WHERE dv.dataset_version_id = ?'
);
$st->execute([$uid, $vid]);
$row = $st->fetch();
if (!$row) {
    set_flash('error', 'Dataset version not found or access denied.');
    redirect('projects.php');
}

$did = (int) $row['dataset_id'];
$wid = (int) $row['workspace_id'];

try {
    $pdo->beginTransaction();

    // 1. RunMetrics for runs that reference this version
    $st = $pdo->prepare(
        'DELETE FROM RunMetrics WHERE run_id IN (
            SELECT run_id FROM Runs WHERE dataset_version_id = ?
        )'
    );
    $st->execute([$vid]);

    // 2. RunParams for those runs
    $st = $pdo->prepare(
        'DELETE FROM RunParams WHERE run_id IN (
            SELECT run_id FROM Runs WHERE dataset_version_id = ?
        )'
    );
    $st->execute([$vid]);

    // 3. ModelRegistry rows tied to those runs
    $st = $pdo->prepare(
        'DELETE FROM ModelRegistry WHERE source_run_id IN (
            SELECT run_id FROM Runs WHERE dataset_version_id = ?
        )'
    );
    $st->execute([$vid]);

    // 4. Runs referencing this version
    $st = $pdo->prepare('DELETE FROM Runs WHERE dataset_version_id = ?');
    $st->execute([$vid]);

    // 5. The version itself
    $st = $pdo->prepare('DELETE FROM DatasetVersions WHERE dataset_version_id = ?');
    $st->execute([$vid]);

    log_audit($pdo, $wid, 'delete', 'dataset_version', $vid);

    $pdo->commit();
    set_flash('success', 'Dataset version deleted.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', 'Could not delete dataset version.');
}

redirect('dataset_detail.php?id=' . $did);
