<?php
define('GYANSETU_APP', true);
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/db_conn.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/activity_logger.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db      = getDB();
$batchId = trim($_GET['id'] ?? '');

if (empty($batchId)) {
    setFlash('error','Invalid request.');
    redirect(BASE_URL.'/admin/batch/batches.php');
}

// Load batch for logging
$stmt = $db->prepare("SELECT batch_id, batch_name, current_enrollment FROM batches WHERE batch_id = ?");
$stmt->execute([$batchId]);
$batch = $stmt->fetch();

if (!$batch) {
    setFlash('error','Batch not found.');
    redirect(BASE_URL.'/admin/batch/batches.php');
}

try {
    $db->beginTransaction();

    // Soft delete: mark as cancelled + drop all active enrollments
    $db->prepare("UPDATE batches SET status = 'cancelled' WHERE batch_id = ?")->execute([$batchId]);
    $db->prepare("UPDATE student_batches SET status = 'dropped' WHERE batch_id = ? AND status = 'active'")->execute([$batchId]);

    // Reset enrollment count
    $db->prepare("UPDATE batches SET current_enrollment = 0 WHERE batch_id = ?")->execute([$batchId]);

    $db->commit();

    // ── HARD DELETE (uncomment if you want full removal) ───────────────────
    // $db->prepare("DELETE FROM batches WHERE batch_id = ?")->execute([$batchId]);
    // Note: student_batches has FK on batch_id — add ON DELETE CASCADE or delete it first.

    logActivity('DELETE_BATCH','batches',
        "Soft-deleted batch '{$batch['batch_name']}' (ID: {$batchId}), had {$batch['current_enrollment']} enrollments");

    setFlash('success',"Batch {$batch['batch_name']} has been cancelled and removed from active batches.");

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[GyanSetu] delete_batch: ' . $e->getMessage());
    setFlash('error','Could not delete batch. Please try again.');
}

redirect(BASE_URL.'/admin/batch/batches.php');
