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
$id = (int)($_GET['id'] ?? 0);
if (!$id) { setFlash('error','Invalid request.'); redirect($B.'/admin/notes/notes.php'); }

$stmt = $db->prepare("SELECT file_path, title FROM notes WHERE id = ?");
$stmt->execute([$id]);
$note = $stmt->fetch();
if (!$note) { setFlash('error','Note not found.'); redirect($B.'/admin/notes/notes.php'); }

$fullPath = $_SERVER['DOCUMENT_ROOT'] . '/Project/lms/' . $note['file_path'];
if (file_exists($fullPath)) unlink($fullPath);
$db->prepare("DELETE FROM notes WHERE id = ?")->execute([$id]);
logActivity('DELETE_NOTE', 'notes', "Deleted note '{$note['title']}' (ID:{$id})");
setFlash('success','Note deleted.');
redirect($B.'/admin/notes/notes.php');