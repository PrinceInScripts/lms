<?php
define('GYANSETU_APP', true);
require_once __DIR__ . '/../../../includes/security_headers.php';
require_once __DIR__ . '/../../../includes/db_conn.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/activity_logger.php';
require_once __DIR__ . '/../../../includes/auth.php';
requireAdmin();

$db       = getDB();
$batchId  = sanitizeInt($_GET['batch_id']  ?? 0);
$courseId = sanitizeInt($_GET['course_id'] ?? 0);

if (!$batchId || !$courseId) {
    setFlash('error', 'Invalid parameters.');
    redirect(BASE_URL . '/admin/batch/course/batch_courses.php');
}

try {
    $stmt = $db->prepare("DELETE FROM batch_courses WHERE batch_id = ? AND course_id = ?");
    $stmt->execute([$batchId, $courseId]);

    logActivity('REMOVE_BATCH_COURSE', 'courses',
        "Removed course (id:{$courseId}) from batch (id:{$batchId})",
        ['batch_id' => $batchId, 'course_id' => $courseId], null
    );

    setFlash('success', 'Course removed from batch successfully.');
} catch (PDOException $e) {
    error_log('[GyanSetu] remove_batch_course error: ' . $e->getMessage());
    setFlash('error', 'Failed to remove course. Please try again.');
}

redirect(BASE_URL . '/admin/batch/course/batch_courses.php?batch_id=' . $batchId);
