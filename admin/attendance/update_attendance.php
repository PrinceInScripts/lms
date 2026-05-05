<?php
define('GYANSETU_APP', true);
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/db_conn.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/activity_logger.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db = getDB();
$B  = rtrim(BASE_URL, '/');

// Only accept POST
if (!isPost()) {
    redirect($B . '/admin/attendance/view_attendance.php');
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Security token invalid. Please refresh and try again.');
    redirect($B . '/admin/attendance/view_attendance.php');
}

$id       = sanitizeInt($_POST['id'] ?? 0);
$status   = trim($_POST['status'] ?? '');
$redirect = trim($_POST['redirect'] ?? $B . '/admin/attendance/view_attendance.php');

// Sanitize the redirect URL — only allow internal redirects
$redirectPath = parse_url($redirect, PHP_URL_PATH);
$safeRedirect = $redirectPath ? ($B . $redirectPath) : ($B . '/admin/attendance/view_attendance.php');

if (!$id) {
    setFlash('error', 'Invalid attendance record.');
    redirect($safeRedirect);
}

if (!in_array($status, ['Present', 'Absent', 'Late'])) {
    setFlash('error', 'Invalid status value.');
    redirect($safeRedirect);
}

// Load the record for logging
$stmt = $db->prepare("
    SELECT a.id, a.student_id, a.batch_id, a.attendance_date, a.status AS old_status,
           sd.full_name
    FROM attendance a
    JOIN student_details sd ON sd.student_id = a.student_id
    WHERE a.id = ?
");
$stmt->execute([$id]);
$record = $stmt->fetch();

if (!$record) {
    setFlash('error', 'Attendance record not found.');
    redirect($safeRedirect);
}

if ($record['old_status'] === $status) {
    // No change needed
    setFlash('info', 'No change — status was already ' . $status . '.');
    redirect($safeRedirect);
}

try {
    $db->prepare("UPDATE attendance SET status = ?, marked_by = ?, marked_at = NOW() WHERE id = ?")
       ->execute([$status, currentUserId(), $id]);

    logActivity(
        'UPDATE_ATTENDANCE',
        'attendance',
        "Changed {$record['full_name']} ({$record['student_id']}) attendance on {$record['attendance_date']} " .
        "from {$record['old_status']} → {$status} (batch: {$record['batch_id']})",
        ['status' => $record['old_status']],
        ['status' => $status]
    );

    setFlash('success', "<strong>{$record['full_name']}</strong>'s attendance updated to <strong>{$status}</strong>.");

} catch (Exception $e) {
    error_log('[GyanSetu] update_attendance: ' . $e->getMessage());
    setFlash('error', 'Could not update attendance. Please try again.');
}

redirect($safeRedirect);
