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

$allowedRoles = ['super_admin','admin','trainer','sales','accounts'];
$errors = [];

if (isPost()) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) $errors[] = 'CSRF invalid.';
    else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? '';
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $designation = trim($_POST['designation'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        $status = $_POST['status'] ?? 'active';

        if (empty($username)) $errors[] = 'Username required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
        if (!in_array($role, $allowedRoles)) $errors[] = 'Invalid role.';
        if (empty($fullName)) $errors[] = 'Full name required.';
        if ($newPassword !== '' && strlen($newPassword) < 8) $errors[] = 'Password min 8 characters.';
        if (!in_array($status, ['active','inactive'])) $errors[] = 'Invalid status.';

        // Duplicate check (exclude self)
        $chk = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $chk->execute([$username, $id]);
        if ($chk->fetch()) $errors[] = "Username '{$username}' already taken.";

        $chk2 = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $chk2->execute([$email, $id]);
        if ($chk2->fetch()) $errors[] = "Email '{$email}' already registered.";

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                if ($newPassword !== '') {
                    $db->prepare("UPDATE users SET username=?, email=?, password_hash=?, role=?, status=? WHERE id=?")
                       ->execute([$username, strtolower($email), password_hash($newPassword, PASSWORD_DEFAULT), $role, $status, $id]);
                } else {
                    $db->prepare("UPDATE users SET username=?, email=?, role=?, status=? WHERE id=?")
                       ->execute([$username, strtolower($email), $role, $status, $id]);
                }

                // Update admin_details (exists? insert if not)
                $detailChk = $db->prepare("SELECT id FROM admin_details WHERE user_id = ?");
                $detailChk->execute([$id]);
                if ($detailChk->fetch()) {
                    $db->prepare("UPDATE admin_details SET full_name=?, phone=?, designation=? WHERE user_id=?")
                       ->execute([$fullName, $phone ?: null, $designation ?: null, $id]);
                } else {
                    $db->prepare("INSERT INTO admin_details (user_id, full_name, phone, designation) VALUES (?,?,?,?)")
                       ->execute([$id, $fullName, $phone ?: null, $designation ?: null]);
                }

                $db->commit();
                logActivity('EDIT_USER', 'users', "Updated user ID {$id}");
                setFlash('success', 'User updated.');
                redirect($B . '/admin/users/view_user.php?id=' . $id);
            } catch (Exception $e) {
                $db->rollBack();
                error_log('[GyanSetu] edit_user: ' . $e->getMessage());
                $errors[] = 'Database error.';
            }
        }
    }
}

$csrf = generateCSRFToken();
$page_title = 'Edit User — GyanSetu';
include __DIR__ . '/../sidebar.php';
?>
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-4 mb-6"><a href="<?= $B ?>/admin/users/view_user.php?id=<?= $id ?>" class="w-9 h-9 rounded-xl bg-white shadow flex items-center justify-center"><i class="ri-arrow-left-line"></i></a><div><h1 class="text-2xl font-bold">Edit User</h1><p class="text-sm text-gray-500"><?= sanitize($user['full_name'] ?? $user['username']) ?></p></div></div>
    <?php renderFlashMessages(); if(!empty($errors)): ?><div class="bg-red-50 p-4 rounded-xl mb-5"><?php foreach($errors as $e) echo "<p class='text-red-600'>• $e</p>"; ?></div><?php endif; ?>
    <div class="bg-white rounded-2xl border p-7">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div><label class="block font-semibold mb-1">Username *</label><input type="text" name="username" value="<?= sanitize($_POST['username'] ?? $user['username']) ?>" class="w-full border rounded-xl px-4 py-2.5" required></div>
                <div><label class="block font-semibold mb-1">Email *</label><input type="email" name="email" value="<?= sanitize($_POST['email'] ?? $user['email']) ?>" class="w-full border rounded-xl px-4 py-2.5" required></div>
                <div><label class="block font-semibold mb-1">New Password</label><input type="password" name="new_password" class="w-full border rounded-xl px-4 py-2.5" placeholder="Leave blank to keep current"></div>
                <div><label class="block font-semibold mb-1">Role *</label><select name="role" class="w-full border rounded-xl px-4 py-2.5 bg-white"><?php foreach($allowedRoles as $r): ?><option value="<?= $r ?>" <?= (($_POST['role']??$user['role'])===$r)?'selected':'' ?>><?= ucfirst($r) ?></option><?php endforeach; ?></select></div>
                <div><label class="block font-semibold mb-1">Status *</label><select name="status" class="w-full border rounded-xl px-4 py-2.5 bg-white"><option value="active" <?= (($_POST['status']??$user['status'])==='active'?'selected':'') ?>>Active</option><option value="inactive" <?= (($_POST['status']??$user['status'])==='inactive'?'selected':'') ?>>Inactive</option></select></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div class="md:col-span-2"><label class="block font-semibold mb-1">Full Name *</label><input type="text" name="full_name" value="<?= sanitize($_POST['full_name'] ?? ($user['full_name'] ?? '')) ?>" class="w-full border rounded-xl px-4 py-2.5" required></div>
                <div><label class="block font-semibold mb-1">Phone</label><input type="tel" name="phone" value="<?= sanitize($_POST['phone'] ?? ($user['phone'] ?? '')) ?>" class="w-full border rounded-xl px-4 py-2.5"></div>
                <div><label class="block font-semibold mb-1">Designation</label><input type="text" name="designation" value="<?= sanitize($_POST['designation'] ?? ($user['designation'] ?? '')) ?>" class="w-full border rounded-xl px-4 py-2.5"></div>
            </div>
            <div class="flex gap-3"><button type="submit" class="flex-1 bg-gradient-to-r from-[#E87F24] to-[#FFC81E] text-white py-3 rounded-xl">Save Changes</button><a href="<?= $B ?>/admin/users/view_user.php?id=<?= $id ?>" class="px-6 py-3 border rounded-xl text-gray-600">Cancel</a></div>
        </form>
    </div>
</div>