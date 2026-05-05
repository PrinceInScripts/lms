<?php
define('GYANSETU_APP', true);
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/db_conn.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/activity_logger.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin(); // only super_admin

$db = getDB();
$B = rtrim(BASE_URL, '/');

$userId = (int)($_GET['user_id'] ?? 0);
if (!$userId) {
    setFlash('error', 'User ID required.');
    redirect($B . '/admin/users/users.php');
}

// Fetch user info (exclude super_admin from editing permissions - they have full anyway)
$userStmt = $db->prepare("SELECT id, username, role FROM users WHERE id = ? AND role != 'super_admin' AND role != 'student'");
$userStmt->execute([$userId]);
$user = $userStmt->fetch();
if (!$user) {
    setFlash('error', 'User not found or cannot set permissions (super_admin or student).');
    redirect($B . '/admin/users/users.php');
}

// List of features
$features = [
    'dashboard'   => 'Dashboard',
    'batches'     => 'Batches',
    'students'    => 'Students',
    'courses'     => 'Courses',
    'schedule'    => 'Schedule',
    'attendance'  => 'Attendance',
    'notes'       => 'Notes',
    'assignments' => 'Assignments',
    'tests'       => 'Tests',
    'exams'       => 'Exams',
    'payments'    => 'Payments',
    'users'       => 'Users',
    'settings'    => 'Settings',
];

$permissions = [];
if (isPost()) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'CSRF token invalid.');
    } else {
        try {
            $db->beginTransaction();
            foreach ($features as $feature => $label) {
                $can_view   = isset($_POST['perm'][$feature]['view']) ? 1 : 0;
                $can_create = isset($_POST['perm'][$feature]['create']) ? 1 : 0;
                $can_edit   = isset($_POST['perm'][$feature]['edit']) ? 1 : 0;
                $can_delete = isset($_POST['perm'][$feature]['delete']) ? 1 : 0;

                // Upsert
                $chk = $db->prepare("SELECT id FROM user_access_settings WHERE user_id = ? AND feature = ?");
                $chk->execute([$userId, $feature]);
                if ($chk->fetch()) {
                    $upd = $db->prepare("UPDATE user_access_settings SET can_view=?, can_create=?, can_edit=?, can_delete=?, set_by=?, set_at=NOW() WHERE user_id=? AND feature=?");
                    $upd->execute([$can_view, $can_create, $can_edit, $can_delete, currentUserId(), $userId, $feature]);
                } else {
                    $ins = $db->prepare("INSERT INTO user_access_settings (user_id, feature, can_view, can_create, can_edit, can_delete, set_by) VALUES (?,?,?,?,?,?,?)");
                    $ins->execute([$userId, $feature, $can_view, $can_create, $can_edit, $can_delete, currentUserId()]);
                }
            }
            $db->commit();
            logActivity('UPDATE_ACCESS', 'settings', "Updated access rights for user {$user['username']} (ID:{$userId})");
            setFlash('success', 'Access permissions saved.');
            redirect($B . "/admin/settings/access.php?user_id={$userId}");
        } catch (Exception $e) {
            $db->rollBack();
            error_log('[GyanSetu] access settings: ' . $e->getMessage());
            setFlash('error', 'Failed to save permissions.');
        }
    }
}

// Load existing permissions
$permStmt = $db->prepare("SELECT feature, can_view, can_create, can_edit, can_delete FROM user_access_settings WHERE user_id = ?");
$permStmt->execute([$userId]);
while ($row = $permStmt->fetch()) {
    $permissions[$row['feature']] = [
        'view'   => $row['can_view'],
        'create' => $row['can_create'],
        'edit'   => $row['can_edit'],
        'delete' => $row['can_delete'],
    ];
}

$csrf = generateCSRFToken();
$page_title = 'Access Settings — GyanSetu';
include __DIR__ . '/../sidebar.php';
?>
<div class="max-w-6xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="<?= $B ?>/admin/users/view_user.php?id=<?= $userId ?>" class="w-9 h-9 rounded-xl bg-white shadow flex items-center justify-center"><i class="ri-arrow-left-line"></i></a>
        <div><h1 class="text-2xl font-bold">Access Settings</h1><p class="text-sm text-gray-500">User: <?= sanitize($user['username']) ?> (<?= ucfirst($user['role']) ?>)</p></div>
    </div>
    <?php renderFlashMessages(); ?>
    <div class="bg-white rounded-2xl shadow-sm border p-6">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b">
                            <th class="px-4 py-3 text-left">Feature</th>
                            <th class="px-4 py-3 text-center w-20">View</th>
                            <th class="px-4 py-3 text-center w-20">Create</th>
                            <th class="px-4 py-3 text-center w-20">Edit</th>
                            <th class="px-4 py-3 text-center w-20">Delete</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php foreach ($features as $feature => $label): ?>
                            <?php $p = $permissions[$feature] ?? ['view'=>0,'create'=>0,'edit'=>0,'delete'=>0]; ?>
                            <tr>
                                <td class="px-4 py-3 font-medium"><?= $label ?></td>
                                <td class="px-4 py-3 text-center"><input type="checkbox" name="perm[<?= $feature ?>][view]" value="1" <?= $p['view'] ? 'checked' : '' ?> class="w-4 h-4 accent-[#E87F24]"></td>
                                <td class="px-4 py-3 text-center"><input type="checkbox" name="perm[<?= $feature ?>][create]" value="1" <?= $p['create'] ? 'checked' : '' ?> class="w-4 h-4 accent-[#E87F24]"></td>
                                <td class="px-4 py-3 text-center"><input type="checkbox" name="perm[<?= $feature ?>][edit]" value="1" <?= $p['edit'] ? 'checked' : '' ?> class="w-4 h-4 accent-[#E87F24]"></td>
                                <td class="px-4 py-3 text-center"><input type="checkbox" name="perm[<?= $feature ?>][delete]" value="1" <?= $p['delete'] ? 'checked' : '' ?> class="w-4 h-4 accent-[#E87F24]"></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-6 flex gap-3">
                <button type="submit" class="bg-gradient-to-r from-[#E87F24] to-[#FFC81E] text-white px-6 py-2.5 rounded-xl">Save Permissions</button>
                <a href="<?= $B ?>/admin/users/view_user.php?id=<?= $userId ?>" class="border px-6 py-2.5 rounded-xl text-gray-600">Cancel</a>
            </div>
        </form>
    </div>
</div>