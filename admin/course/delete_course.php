<?php
define('GYANSETU_APP', true);
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/db_conn.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/activity_logger.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db = getDB();
$id = sanitizeInt($_GET['id'] ?? 0);

if (!$id) {
    setFlash('error', 'Invalid course ID.');
    redirect(BASE_URL . '/admin/course/courses.php');
}

$stmt = $db->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$id]);
$course = $stmt->fetch();

if (!$course) {
    setFlash('error', 'Course not found.');
    redirect(BASE_URL . '/admin/course/courses.php');
}

// Check if course has active batch assignments
$assigned = $db->prepare("SELECT COUNT(*) FROM batch_courses WHERE course_id = ?");
$assigned->execute([$id]);
$batchCount = (int)$assigned->fetchColumn();

try {
    if ($batchCount > 0) {
        // Soft delete — set inactive
        $db->prepare("UPDATE courses SET status = 'inactive' WHERE id = ?")->execute([$id]);
        logActivity('DEACTIVATE_COURSE', 'courses',
            "Deactivated course (has {$batchCount} batch assignments): {$course['course_name']} [{$course['course_code']}]",
            ['status' => 'active'], ['status' => 'inactive']
        );
        setFlash('warning', "Course \"{$course['course_name']}\" is assigned to {$batchCount} batch(es) and has been set to inactive instead of deleted.");
    } else {
        // Hard delete — remove modules + submodules + course
        $db->beginTransaction();

        // Delete submodules via module join
        $db->prepare("
            DELETE ms FROM module_submodules ms
            INNER JOIN course_modules cm ON cm.id = ms.module_id
            WHERE cm.course_id = ?
        ")->execute([$id]);

        $db->prepare("DELETE FROM course_modules WHERE course_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM courses WHERE id = ?")->execute([$id]);

        $db->commit();

        logActivity('DELETE_COURSE', 'courses',
            "Deleted course: {$course['course_name']} [{$course['course_code']}]",
            $course, null
        );
        setFlash('success', "Course \"{$course['course_name']}\" deleted successfully.");
    }
} catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('[GyanSetu] delete_course error: ' . $e->getMessage());
    setFlash('error', 'Failed to delete course. Please try again.');
}

redirect(BASE_URL . '/admin/course/courses.php');
