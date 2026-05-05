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
$id = sanitizeInt($_GET['id'] ?? 0);

if (!$id) { setFlash('error','Invalid request.'); redirect($B.'/admin/schedule/schedule.php'); }

$stmt = $db->prepare("SELECT id, topic, schedule_date, batch_id FROM schedule WHERE id = ? AND is_cancelled = 0");
$stmt->execute([$id]);
$sched = $stmt->fetch();

if (!$sched) { setFlash('error','Schedule not found or already cancelled.'); redirect($B.'/admin/schedule/schedule.php'); }

try {
    $db->prepare("UPDATE schedule SET is_cancelled = 1 WHERE id = ?")->execute([$id]);
    logActivity('CANCEL_SCHEDULE','schedule',"Cancelled schedule '{$sched['topic']}' (ID:{$id}) for batch {$sched['batch_id']} on {$sched['schedule_date']}");
    setFlash('success',"Session <strong>{$sched['topic']}</strong> has been cancelled.");
} catch (Exception $e) {
    error_log('[GyanSetu] delete_schedule: '.$e->getMessage());
    setFlash('error','Could not cancel schedule. Please try again.');
}

redirect($B.'/admin/schedule/schedule.php');
