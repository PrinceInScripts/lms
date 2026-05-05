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
if (!$id) { setFlash('error','Invalid user.'); redirect($B.'/admin/users/users.php'); }

$stmt = $db->prepare("
    SELECT u.*, ad.full_name, ad.phone, ad.designation
    FROM users u
    LEFT JOIN admin_details ad ON ad.user_id = u.id
    WHERE u.id = ? AND u.role != 'student'
");
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) { setFlash('error','User not found.'); redirect($B.'/admin/users/users.php'); }

$page_title = 'View User — GyanSetu';
include __DIR__ . '/../sidebar.php';
?>
<div class="max-w-3xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="<?= $B ?>/admin/users/users.php" class="w-9 h-9 rounded-xl bg-white shadow flex items-center justify-center"><i class="ri-arrow-left-line"></i></a>
        <div><h1 class="text-2xl font-bold">User Profile</h1><p class="text-sm text-gray-500"><?= sanitize($user['full_name'] ?? $user['username']) ?></p></div>
        <div class="ml-auto flex gap-2">
            <a href="<?= $B ?>/admin/users/edit_user.php?id=<?= $id ?>" class="bg-[#E87F24] text-white px-4 py-2 rounded-xl"><i class="ri-edit-line"></i> Edit</a>
            <a href="<?= $B ?>/admin/settings/access.php?user_id=<?= $id ?>" class="bg-[#73A5CA] text-white px-4 py-2 rounded-xl"><i class="ri-settings-line"></i> Access</a>
        </div>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border p-6 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div><span class="font-semibold">Username:</span> <?= sanitize($user['username']) ?></div>
            <div><span class="font-semibold">Email:</span> <?= sanitize($user['email']) ?></div>
            <div><span class="font-semibold">Role:</span> <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full text-xs"><?= ucfirst(str_replace('_', ' ', $user['role'])) ?></span></div>
            <div><span class="font-semibold">Status:</span> <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs <?= $user['status']==='active'?'bg-green-100 text-green-700':'bg-red-100 text-red-700' ?>"><?= ucfirst($user['status']) ?></span></div>
            <div><span class="font-semibold">Full Name:</span> <?= sanitize($user['full_name'] ?? '—') ?></div>
            <div><span class="font-semibold">Phone:</span> <?= sanitize($user['phone'] ?? '—') ?></div>
            <div class="md:col-span-2"><span class="font-semibold">Designation:</span> <?= sanitize($user['designation'] ?? '—') ?></div>
            <div><span class="font-semibold">Last Login:</span> <?= $user['last_login'] ? date('d M Y, h:i A', strtotime($user['last_login'])) : 'Never' ?></div>
            <div><span class="font-semibold">Account Created:</span> <?= date('d M Y, h:i A', strtotime($user['created_at'])) ?></div>
        </div>
    </div>
</div>