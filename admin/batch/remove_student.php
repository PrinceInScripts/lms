<?php
define('GYANSETU_APP', true);
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/db_conn.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/activity_logger.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db        = getDB();
$studentId = trim($_GET['student_id'] ?? '');
$batchId   = trim($_GET['batch_id']   ?? '');

if (empty($studentId) || empty($batchId)) {
    setFlash('error', 'Invalid request.');
    redirect(BASE_URL . '/admin/batch/batches.php');
}

// Validate the enrollment record exists and is active
$stmt = $db->prepare("
    SELECT sb.id, sb.status,
           sd.full_name,
           b.batch_name
    FROM student_batches sb
    JOIN student_details sd ON sd.student_id = sb.student_id
    JOIN batches b ON b.batch_id = sb.batch_id
    WHERE sb.student_id = ? AND sb.batch_id = ?
");
$stmt->execute([$studentId, $batchId]);
$record = $stmt->fetch();

if (!$record) {
    setFlash('error', 'Enrollment record not found.');
    redirect(BASE_URL . '/admin/batch/view_batch.php?id=' . urlencode($batchId));
}

if ($record['status'] !== 'active') {
    setFlash('error', 'Student is not actively enrolled in this batch.');
    redirect(BASE_URL . '/admin/batch/view_batch.php?id=' . urlencode($batchId));
}

try {
    $db->beginTransaction();

    // Soft remove: mark as dropped
    $db->prepare("
        UPDATE student_batches SET status = 'dropped'
        WHERE student_id = ? AND batch_id = ?
    ")->execute([$studentId, $batchId]);

    // Recalculate current_enrollment from real active count (stays accurate)
    $db->prepare("
        UPDATE batches
        SET current_enrollment = (
            SELECT COUNT(*) FROM student_batches
            WHERE batch_id = ? AND status = 'active'
        )
        WHERE batch_id = ?
    ")->execute([$batchId, $batchId]);

    $db->commit();

    logActivity('REMOVE_STUDENT', 'batches',
        "Removed student '{$record['full_name']}' (ID: {$studentId}) from batch '{$record['batch_name']}' (ID: {$batchId})");

    setFlash('success',
        "Student {$record['full_name']} removed from {$record['batch_name']}.");

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[GyanSetu] remove_student: ' . $e->getMessage());
    setFlash('error', 'Could not remove student. Please try again.');
}

redirect(BASE_URL . '/admin/batch/view_batch.php?id=' . urlencode($batchId));
