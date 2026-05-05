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
$examId = trim($_GET['exam_id'] ?? '');
if(!$examId) { setFlash('error','Invalid request.'); redirect($B.'/admin/exams/exams.php'); }

$db->prepare("DELETE FROM exam_results WHERE exam_id = ?")->execute([$examId]);
$db->prepare("DELETE FROM exams WHERE exam_id = ?")->execute([$examId]);
logActivity('DELETE_EXAM', 'exams', "Deleted exam {$examId}");
setFlash('success','Exam deleted.');
redirect($B.'/admin/exams/exams.php');