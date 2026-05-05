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
$errors = [];
$old = [];

$allowedRoles = ['admin','trainer','sales','accounts'];

if (isPost()) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token invalid.';
    } else {
        $old = $_POST;
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $designation = trim($_POST['designation'] ?? '');

        if (empty($username)) $errors[] = 'Username required.';
        if (strlen($username) < 3) $errors[] = 'Username min 3 chars.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
        if (strlen($password) < 8) $errors[] = 'Password min 8 characters.';
        if (!in_array($role, $allowedRoles)) $errors[] = 'Invalid role.';
        if (empty($fullName)) $errors[] = 'Full name required.';

        // Check duplicates
        $chk = $db->prepare("SELECT id FROM users WHERE username = ?");
        $chk->execute([$username]);
        if ($chk->fetch()) $errors[] = "Username '{$username}' already taken.";

        $chk2 = $db->prepare("SELECT id FROM users WHERE email = ?");
        $chk2->execute([$email]);
        if ($chk2->fetch()) $errors[] = "Email '{$email}' already registered.";

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                // Insert into users
                $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, role, status) VALUES (?, ?, ?, ?, 'active')");
                $stmt->execute([$username, strtolower($email), password_hash($password, PASSWORD_DEFAULT), $role]);
                $userId = $db->lastInsertId();

                // Insert into appropriate details table
                $detailStmt = $db->prepare("INSERT INTO admin_details (user_id, full_name, phone, designation) VALUES (?, ?, ?, ?)");
                $detailStmt->execute([$userId, $fullName, $phone ?: null, $designation ?: null]);

                $db->commit();

                logActivity('ADD_USER', 'users', "Added user '{$username}' (role: {$role})");
                setFlash('success', "User {$fullName} added successfully.");
                redirect($B . '/admin/users/users.php');
            } catch (Exception $e) {
                $db->rollBack();
                error_log('[GyanSetu] add_user: ' . $e->getMessage());
                $errors[] = 'Database error. Please try again.';
            }
        }
    }
}

$csrf = generateCSRFToken();
$page_title = 'Add User — GyanSetu';
include __DIR__ . '/../sidebar.php';
?>
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="<?= $B ?>/admin/users/users.php" class="w-9 h-9 rounded-xl bg-white shadow flex items-center justify-center"><i class="ri-arrow-left-line"></i></a>
        <div><h1 class="text-2xl font-bold">Add User</h1><p class="text-sm text-gray-500">Create admin, trainer, sales or accounts user</p></div>
    </div>
    <?php renderFlashMessages(); if(!empty($errors)): ?><div class="bg-red-50 p-4 rounded-xl mb-5"><?php foreach($errors as $e) echo "<p class='text-red-600'>• $e</p>"; ?></div><?php endif; ?>
    <div class="bg-white rounded-2xl border p-7">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div><label class="block font-semibold mb-1">Username *</label><input type="text" name="username" value="<?= sanitize($old['username'] ?? '') ?>" class="w-full border rounded-xl px-4 py-2.5" required></div>
                <div><label class="block font-semibold mb-1">Email *</label><input type="email" name="email" value="<?= sanitize($old['email'] ?? '') ?>" class="w-full border rounded-xl px-4 py-2.5" required></div>
                <div><label class="block font-semibold mb-1">Password *</label><input type="password" name="password" class="w-full border rounded-xl px-4 py-2.5" required></div>
                <div><label class="block font-semibold mb-1">Role *</label><select name="role" class="w-full border rounded-xl px-4 py-2.5 bg-white"><?php foreach($allowedRoles as $r): ?><option value="<?= $r ?>" <?= ($old['role']??'')===$r?'selected':'' ?>><?= ucfirst($r) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div class="md:col-span-2"><label class="block font-semibold mb-1">Full Name *</label><input type="text" name="full_name" value="<?= sanitize($old['full_name'] ?? '') ?>" class="w-full border rounded-xl px-4 py-2.5" required></div>
                <div><label class="block font-semibold mb-1">Phone</label><input type="tel" name="phone" value="<?= sanitize($old['phone'] ?? '') ?>" class="w-full border rounded-xl px-4 py-2.5"></div>
                <div><label class="block font-semibold mb-1">Designation</label><input type="text" name="designation" value="<?= sanitize($old['designation'] ?? '') ?>" class="w-full border rounded-xl px-4 py-2.5"></div>
            </div>
            <div class="flex gap-3"><button type="submit" class="flex-1 bg-gradient-to-r from-[#E87F24] to-[#FFC81E] text-white py-3 rounded-xl">Add User</button><a href="<?= $B ?>/admin/users/users.php" class="px-6 py-3 border rounded-xl text-gray-600">Cancel</a></div>
        </form>
    </div>
</div>