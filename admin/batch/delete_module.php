<?php
define('GYANSETU_APP', true);
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/db_conn.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/activity_logger.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db       = getDB();
$id       = sanitizeInt($_GET['id']        ?? 0);
$courseId = sanitizeInt($_GET['course_id'] ?? 0);

if (!$id) {
    setFlash('error', 'Invalid module ID.');
    redirect(BASE_URL . '/admin/batch/course/batch_course_modules.php' . ($courseId ? "?course_id={$courseId}" : ''));
}

$stmt = $db->prepare("SELECT cm.*, c.course_name FROM course_modules cm JOIN courses c ON c.id = cm.course_id WHERE cm.id = ?");
$stmt->execute([$id]);
$module = $stmt->fetch();

if (!$module) {
    setFlash('error', 'Module not found.');
    redirect(BASE_URL . '/admin/batch/course/batch_course_modules.php' . ($courseId ? "?course_id={$courseId}" : ''));
}

$backCourseId = $courseId ?: (int)$module['course_id'];

try {
    $db->beginTransaction();

    // Delete submodules first
    $db->prepare("DELETE FROM module_submodules WHERE module_id = ?")->execute([$id]);

    // Delete module
    $db->prepare("DELETE FROM course_modules WHERE id = ?")->execute([$id]);

    $db->commit();

    logActivity('DELETE_MODULE', 'modules',
        "Deleted module \"{$module['module_name']}\" from course \"{$module['course_name']}\"",
        $module, null
    );

    setFlash('success', "Module \"{$module['module_name']}\" and its submodules deleted successfully.");
} catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[GyanSetu] delete_module error: ' . $e->getMessage());
    setFlash('error', 'Failed to delete module. Please try again.');
}

redirect(BASE_URL . '/admin/batch/course/batch_course_modules.php?course_id=' . $backCourseId);
