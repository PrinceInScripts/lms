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
$testId = (int)($_GET['test_id'] ?? 0);
if(!$id || !$testId) { setFlash('error','Invalid request.'); redirect($B.'/admin/tests/test.php'); }

$db->prepare("DELETE FROM test_questions WHERE id = ?")->execute([$id]);
logActivity('DELETE_QUESTION', 'tests', "Deleted question ID {$id}");
setFlash('success','Question deleted.');
redirect($B."/admin/tests/view_test.php?id={$testId}");