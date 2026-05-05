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
if(!$id) { setFlash('error','Invalid request.'); redirect($B.'/admin/assignments/assignments.php'); }

$stmt = $db->prepare("SELECT file_path, title FROM assignments WHERE id = ?");
$stmt->execute([$id]);
$assign = $stmt->fetch();
if(!$assign) { setFlash('error','Assignment not found.'); redirect($B.'/admin/assignments/assignments.php'); }

$fullPath = $_SERVER['DOCUMENT_ROOT'] . '/Project/lms/' . $assign['file_path'];
if(file_exists($fullPath)) unlink($fullPath);
$db->prepare("DELETE FROM assignments WHERE id = ?")->execute([$id]);
// Optionally delete submissions too (CASCADE in DB or manually)
$db->prepare("DELETE FROM assignment_submissions WHERE assignment_id = ?")->execute([$id]);

logActivity('DELETE_ASSIGNMENT','assignments',"Deleted assignment '{$assign['title']}' (ID:{$id})");
setFlash('success','Assignment deleted.');
redirect($B.'/admin/assignments/assignments.php');