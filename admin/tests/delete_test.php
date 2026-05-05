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
if(!$id) { setFlash('error','Invalid request.'); redirect($B.'/admin/tests/test.php'); }

// Delete questions first (manual cascade)
$db->prepare("DELETE FROM test_questions WHERE test_id = ?")->execute([$id]);
$db->prepare("DELETE FROM test_attempts WHERE test_id = ?")->execute([$id]);
$db->prepare("DELETE FROM tests WHERE id = ?")->execute([$id]);

logActivity('DELETE_TEST', 'tests', "Deleted test ID {$id}");
setFlash('success','Test deleted.');
redirect($B.'/admin/tests/test.php');