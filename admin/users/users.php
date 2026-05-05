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

$roleFilter = $_GET['role'] ?? '';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

$where = "u.role != 'student'";
$params = [];
if ($roleFilter && in_array($roleFilter, ['super_admin','admin','trainer','sales','accounts'])) {
    $where .= " AND u.role = ?";
    $params[] = $roleFilter;
}
if ($search) {
    $where .= " AND (u.username LIKE ? OR u.email LIKE ? OR ad.full_name LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

// Count total
$countStmt = $db->prepare("SELECT COUNT(*) FROM users u LEFT JOIN admin_details ad ON ad.user_id = u.id WHERE $where");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $limit));

// Fetch users
$stmt = $db->prepare("
    SELECT u.id, u.username, u.email, u.role, u.status,
           ad.full_name, ad.phone, ad.designation
    FROM users u
    LEFT JOIN admin_details ad ON ad.user_id = u.id
    WHERE $where
    ORDER BY u.id DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([...$params, $limit, $offset]);
$users = $stmt->fetchAll();

$roles = ['super_admin','admin','trainer','sales','accounts'];

$page_title = 'User Management — GyanSetu';
include __DIR__ . '/../sidebar.php';
?>
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">User Management</h1>
            <p class="text-sm text-gray-500"><?= number_format($total) ?> user(s) (excluding students)</p>
        </div>
        <a href="<?= $B ?>/admin/users/add_user.php" class="bg-gradient-to-r from-[#E87F24] to-[#FFC81E] text-white px-5 py-2.5 rounded-xl shadow flex items-center gap-2">
            <i class="ri-add-line"></i> Add User
        </a>
    </div>

    <?php renderFlashMessages(); ?>

    <!-- Filters -->
    <div class="bg-white rounded-2xl shadow-sm border p-4 mb-5">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div class="relative flex-1 min-w-[180px]">
                <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="search" value="<?= sanitize($search) ?>" placeholder="Search by name, username or email…" class="w-full pl-9 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm">
            </div>
            <select name="role" class="border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-white">
                <option value="">All Roles</option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= $r ?>" <?= $roleFilter === $r ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $r)) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="bg-[#73A5CA] text-white px-5 py-2.5 rounded-xl">Filter</button>
            <?php if ($search || $roleFilter): ?>
                <a href="<?= $B ?>/admin/users/users.php" class="border px-4 py-2.5 rounded-xl text-gray-600">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Users Table -->
    <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
        <?php if (empty($users)): ?>
            <div class="p-16 text-center text-gray-400"><i class="ri-user-settings-line text-5xl mb-3"></i><p>No users found.</p></div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gradient-to-r from-[#E87F24] to-[#FFC81E] text-white">
                        <th class="px-5 py-3 text-left">User</th>
                        <th class="px-5 py-3 text-left">Role</th>
                        <th class="px-5 py-3 text-left">Status</th>
                        <th class="px-5 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($users as $u): ?>
                        <?php
                        $statusClass = $u['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
                        $statusDot = $u['status'] === 'active' ? 'bg-green-500' : 'bg-red-500';
                        ?>
                        <tr class="hover:bg-orange-50/50">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-[#E87F24] to-[#FFC81E] flex items-center justify-center text-white font-bold text-sm flex-shrink-0">
                                        <?= strtoupper(substr($u['full_name'] ?? $u['username'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-800"><?= sanitize($u['full_name'] ?? $u['username']) ?></p>
                                        <p class="text-xs text-gray-400"><?= sanitize($u['email']) ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="px-2 py-0.5 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full"><?= ucfirst(str_replace('_', ' ', $u['role'])) ?></span>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold <?= $statusClass ?>">
                                    <span class="w-1.5 h-1.5 rounded-full <?= $statusDot ?>"></span>
                                    <?= ucfirst($u['status']) ?>
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-center gap-3">
                                    <a href="<?= $B ?>/admin/users/view_user.php?id=<?= $u['id'] ?>" title="View" class="text-[#73A5CA] hover:text-blue-700"><i class="ri-eye-line text-lg"></i></a>
                                    <a href="<?= $B ?>/admin/users/edit_user.php?id=<?= $u['id'] ?>" title="Edit" class="text-[#E87F24] hover:text-orange-700"><i class="ri-edit-line text-lg"></i></a>
                                    <button onclick="confirmDelete(<?= $u['id'] ?>, '<?= sanitize($u['full_name'] ?? $u['username']) ?>')" title="Delete" class="text-red-400 hover:text-red-600"><i class="ri-delete-bin-line text-lg"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="flex justify-between px-5 py-4 border-t">
            <p class="text-sm text-gray-500">Showing <?= $offset+1 ?>–<?= min($offset+$limit, $total) ?> of <?= $total ?></p>
            <div class="flex gap-1">
                <?php for ($p=1; $p<=$totalPages; $p++): ?>
                <a href="?page=<?= $p ?>&role=<?= urlencode($roleFilter) ?>&search=<?= urlencode($search) ?>" class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-medium <?= $p===$page ? 'bg-[#E87F24] text-white' : 'text-gray-500 hover:bg-orange-50' ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function confirmDelete(id, name) {
    if (confirm(`Delete user "${name}"? This will set status to inactive.`)) {
        window.location.href = '<?= $B ?>/admin/users/delete_user.php?id=' + id;
    }
}
</script>