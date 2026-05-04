<?php
/**
 * delete_student.php — GyanSetu LMS
 *
 * Performs a soft delete by setting current_status = 'dropped' AND
 * users.status = 'inactive'. This preserves all historical records
 * (attendance, payments, exam results) while preventing login.
 *
 * If you need hard delete uncomment the hard-delete block below.
 * The DB has ON DELETE CASCADE on student_details FK so a hard delete
 * on users will cascade cleanly.
 */

define('GYANSETU_APP', true);
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/db_conn.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/activity_logger.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db     = getDB();
$userId = sanitizeInt($_GET['id'] ?? 0);

if (!$userId) {
    setFlash('error', 'Invalid request.');
    redirect('/admin/students/students.php');
}

// Load student for logging — also validates the user is actually a student
$stmt = $db->prepare("
    SELECT u.id, u.username, sd.full_name, sd.student_id, sd.current_status
    FROM users u
    JOIN student_details sd ON sd.user_id = u.id
    WHERE u.id = ? AND u.role = 'student'
");
$stmt->execute([$userId]);
$student = $stmt->fetch();

if (!$student) {
    setFlash('error', 'Student not found.');
    redirect('/admin/students/students.php');
}

// Prevent deleting a student who is already the logged-in admin (safety)
if ($userId === currentUserId()) {
    setFlash('error', 'You cannot delete your own account.');
    redirect('/admin/students/students.php');
}

try {
    // ── SOFT DELETE (recommended) ──────────────────────────────────────────
    // Marks as inactive — preserves all relational data
    $db->beginTransaction();

    $db->prepare("UPDATE users SET status = 'inactive' WHERE id = ?")
       ->execute([$userId]);

    $db->prepare("UPDATE student_details SET current_status = 'dropped' WHERE user_id = ?")
       ->execute([$userId]);

    $db->commit();

    // ── HARD DELETE (uncomment only if you want full erasure) ──────────────
    // WARNING: This cascades to student_details and any FK-linked tables.
    // Make sure your DB schema has ON DELETE CASCADE where needed.
    //
    // $db->beginTransaction();
    // $db->prepare("DELETE FROM users WHERE id = ? AND role = 'student'")->execute([$userId]);
    // $db->commit();

    logActivity(
        'DELETE_STUDENT',
        'students',
        "Soft-deleted student '{$student['full_name']}' (ID: {$student['student_id']}, user_id: {$userId})"
    );

    setFlash('success', "Student <strong>{$student['full_name']}</strong> has been removed.");

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('[GyanSetu] delete_student error: ' . $e->getMessage());
    setFlash('error', 'Could not delete student. Please try again.');
}

redirect('/admin/students/students.php');
