<?php
define('GYANSETU_APP', true);
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/db_conn.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/activity_logger.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db = getDB();
$B = rtrim(BASE_URL, '/');
$id = (int)($_GET['id'] ?? 0);
if (!$id) { setFlash('error','Invalid request.'); redirect($B.'/admin/users/users.php'); }

// Prevent self-delete
if ($id === currentUserId()) {
    setFlash('error', 'You cannot delete your own account.');
    redirect($B.'/admin/users/users.php');
}

$db->prepare("UPDATE users SET status = 'inactive' WHERE id = ?")->execute([$id]);
logActivity('DELETE_USER', 'users', "Soft-deleted user ID {$id}");
setFlash('success', 'User deactivated (soft delete).');
redirect($B.'/admin/users/users.php');