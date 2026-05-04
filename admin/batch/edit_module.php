<?php
define('GYANSETU_APP', true);
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/db_conn.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/activity_logger.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db = getDB();
$id = sanitizeInt($_GET['id'] ?? 0);

if (!$id) {
    setFlash('error', 'Invalid module ID.');
    redirect(BASE_URL . '/admin/batch/course/batch_course_modules.php');
}

// Fetch module
$modStmt = $db->prepare("SELECT cm.*, c.course_name, c.course_code FROM course_modules cm JOIN courses c ON c.id = cm.course_id WHERE cm.id = ?");
$modStmt->execute([$id]);
$module = $modStmt->fetch();

if (!$module) {
    setFlash('error', 'Module not found.');
    redirect(BASE_URL . '/admin/batch/course/batch_course_modules.php');
}

// Fetch existing submodules
$subStmt = $db->prepare("SELECT * FROM module_submodules WHERE module_id = ? ORDER BY submodule_order ASC, id ASC");
$subStmt->execute([$id]);
$existingSubmodules = $subStmt->fetchAll();

$errors = [];

if (isPost()) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token invalid. Please refresh and try again.';
    } else {
        $moduleName  = trim($_POST['module_name']  ?? '');
        $moduleOrder = sanitizeInt($_POST['module_order'] ?? 0) ?? 1;

        // Submodules from form
        $subIds     = $_POST['sub_ids']    ?? [];  // existing IDs (0 = new)
        $subNames   = array_map('trim', $_POST['submodule_names']  ?? []);
        $subOrders  = array_map('intval', $_POST['submodule_orders'] ?? []);

        if (empty($moduleName)) $errors[] = 'Module name is required.';
        if (strlen($moduleName) > 200) $errors[] = 'Module name too long (max 200 chars).';

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                $old = ['module_name' => $module['module_name'], 'module_order' => $module['module_order']];

                // Update module
                $db->prepare("UPDATE course_modules SET module_name = ?, module_order = ? WHERE id = ?")
                   ->execute([$moduleName, $moduleOrder, $id]);

                // Process submodules: delete removed ones, upsert rest
                $keptIds = [];
                foreach ($subNames as $i => $name) {
                    if ($name === '') continue;
                    $subId    = (int)($subIds[$i] ?? 0);
                    $subOrder = $subOrders[$i] ?? ($i + 1);

                    if ($subId > 0) {
                        // Update existing
                        $db->prepare("UPDATE module_submodules SET submodule_name = ?, submodule_order = ? WHERE id = ? AND module_id = ?")
                           ->execute([$name, $subOrder, $subId, $id]);
                        $keptIds[] = $subId;
                    } else {
                        // Insert new
                        $db->prepare("INSERT INTO module_submodules (module_id, submodule_name, submodule_order) VALUES (?, ?, ?)")
                           ->execute([$id, $name, $subOrder]);
                        $keptIds[] = (int)$db->lastInsertId();
                    }
                }

                // Delete removed submodules
                $existingIds = array_column($existingSubmodules, 'id');
                $toDelete = array_diff($existingIds, $keptIds);
                if (!empty($toDelete)) {
                    $placeholders = implode(',', array_fill(0, count($toDelete), '?'));
                    $db->prepare("DELETE FROM module_submodules WHERE id IN ($placeholders) AND module_id = ?")
                       ->execute([...$toDelete, $id]);
                }

                $db->commit();

                logActivity('UPDATE_MODULE', 'modules',
                    "Updated module \"{$moduleName}\" in course \"{$module['course_name']}\"",
                    $old,
                    ['module_name' => $moduleName, 'module_order' => $moduleOrder]
                );

                setFlash('success', "Module \"{$moduleName}\" updated successfully.");
                redirect(BASE_URL . '/admin/batch/course/batch_course_modules.php?course_id=' . (int)$module['course_id']);
            } catch (PDOException $e) {
                $db->rollBack();
                error_log('[GyanSetu] edit_module error: ' . $e->getMessage());
                $errors[] = 'Database error. Please try again.';
            }
        }
    }

    // Re-fetch submodules on error for display
    $subStmt->execute([$id]);
    $existingSubmodules = $subStmt->fetchAll();
}

$csrf = generateCSRFToken();
$page_title = 'Edit Module — GyanSetu';
require_once __DIR__ . '/../../admin/sidebar.php';
?>
<div class="main-content">
  <div class="p-6 max-w-2xl mx-auto">

    <!-- Header -->
    <div class="flex items-center gap-3 mb-8">
      <a href="course/batch_course_modules.php?course_id=<?= (int)$module['course_id'] ?>"
        class="w-9 h-9 rounded-xl bg-white border border-gray-200 flex items-center justify-center hover:bg-gray-50 transition shadow-sm">
        <i class="ri-arrow-left-line text-gray-600"></i>
      </a>
      <div>
        <h1 class="text-2xl font-bold text-gray-800">Edit Module</h1>
        <p class="text-sm text-[#E87F24] font-medium mt-0.5"><?= sanitize($module['course_name']) ?></p>
      </div>
    </div>

    <?php renderFlashMessages(); ?>

    <?php if (!empty($errors)): ?>
      <div class="bg-red-50 border-l-4 border-red-400 rounded-xl p-4 mb-6">
        <div class="flex items-start gap-3">
          <i class="ri-error-warning-line text-red-500 text-lg flex-shrink-0 mt-0.5"></i>
          <ul class="space-y-1">
            <?php foreach ($errors as $e): ?>
              <li class="text-sm text-red-700"><?= sanitize($e) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
      <form method="POST" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

        <!-- Course (read-only) -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Course</label>
          <div class="px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-sm text-gray-500 flex items-center gap-2">
            <span class="font-mono text-[#E87F24]"><?= sanitize($module['course_code']) ?></span>
            <span>—</span>
            <span><?= sanitize($module['course_name']) ?></span>
          </div>
        </div>

        <!-- Module Name -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">
            Module Name <span class="text-red-500">*</span>
          </label>
          <input type="text" name="module_name" maxlength="200" required
            value="<?= sanitize($_POST['module_name'] ?? $module['module_name']) ?>"
            class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:border-[#E87F24] focus:ring-2 focus:ring-[#E87F24]/20 transition text-sm">
        </div>

        <!-- Module Order -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Module Order</label>
          <input type="number" name="module_order" min="1" max="999"
            value="<?= (int)($_POST['module_order'] ?? $module['module_order']) ?>"
            class="w-40 px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:border-[#E87F24] focus:ring-2 focus:ring-[#E87F24]/20 transition text-sm">
        </div>

        <!-- Submodules Section -->
        <div>
          <div class="flex items-center justify-between mb-3">
            <label class="text-sm font-semibold text-gray-700">Submodules</label>
            <button type="button" id="addSubBtn"
              class="inline-flex items-center gap-1.5 text-xs font-medium text-[#E87F24] hover:text-[#d4711f] transition">
              <i class="ri-add-circle-line text-base"></i> Add Submodule
            </button>
          </div>

          <div id="subList" class="space-y-3">
            <?php
            $displaySubs = !empty($_POST['submodule_names']) ? [] : $existingSubmodules;
            if (!empty($_POST['submodule_names'])) {
                foreach ($_POST['submodule_names'] as $i => $n) {
                    $displaySubs[] = [
                        'id'               => $_POST['sub_ids'][$i] ?? 0,
                        'submodule_name'   => $n,
                        'submodule_order'  => $_POST['submodule_orders'][$i] ?? ($i + 1),
                    ];
                }
            }
            if (empty($displaySubs)): ?>
              <p class="text-sm text-gray-400 italic" id="noSubMsg">No submodules yet.</p>
            <?php else:
              foreach ($displaySubs as $i => $sub): ?>
                <div class="submodule-row flex items-center gap-3">
                  <input type="hidden" name="sub_ids[]" value="<?= (int)$sub['id'] ?>">
                  <span class="text-xs text-gray-400 w-5 text-right flex-shrink-0"><?= $i + 1 ?></span>
                  <input type="text" name="submodule_names[]"
                    value="<?= sanitize($sub['submodule_name']) ?>"
                    placeholder="Submodule name"
                    class="flex-1 px-3 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#E87F24] text-sm">
                  <input type="number" name="submodule_orders[]"
                    value="<?= (int)$sub['submodule_order'] ?>"
                    placeholder="Order" min="1"
                    class="w-20 px-3 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#E87F24] text-sm text-center">
                  <button type="button" onclick="removeSub(this)"
                    class="w-8 h-8 rounded-lg text-red-400 hover:bg-red-50 flex items-center justify-center transition flex-shrink-0">
                    <i class="ri-delete-bin-line text-sm"></i>
                  </button>
                </div>
              <?php endforeach;
            endif; ?>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-3 pt-2">
          <button type="submit"
            class="flex-1 bg-[#E87F24] hover:bg-[#d4711f] text-white font-semibold py-3 rounded-xl transition flex items-center justify-center gap-2 shadow-sm">
            <i class="ri-save-line"></i> Save Changes
          </button>
          <a href="course/batch_course_modules.php?course_id=<?= (int)$module['course_id'] ?>"
            class="px-6 py-3 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 font-medium transition text-sm">
            Cancel
          </a>
        </div>
      </form>
    </div>

  </div>
</div>

<script>
let subCount = document.querySelectorAll('.submodule-row').length;

document.getElementById('addSubBtn').addEventListener('click', () => {
    const msg = document.getElementById('noSubMsg');
    if (msg) msg.remove();

    subCount++;
    const list = document.getElementById('subList');
    const row = document.createElement('div');
    row.className = 'submodule-row flex items-center gap-3';
    row.innerHTML = `
        <input type="hidden" name="sub_ids[]" value="0">
        <span class="text-xs text-gray-400 w-5 text-right flex-shrink-0">${subCount}</span>
        <input type="text" name="submodule_names[]"
            placeholder="Submodule name"
            class="flex-1 px-3 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#E87F24] text-sm">
        <input type="number" name="submodule_orders[]"
            value="${subCount}" placeholder="Order" min="1"
            class="w-20 px-3 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#E87F24] text-sm text-center">
        <button type="button" onclick="removeSub(this)"
            class="w-8 h-8 rounded-lg text-red-400 hover:bg-red-50 flex items-center justify-center transition flex-shrink-0">
            <i class="ri-delete-bin-line text-sm"></i>
        </button>
    `;
    list.appendChild(row);
    row.querySelector('input[name="submodule_names[]"]').focus();
    reNumber();
});

function removeSub(btn) {
    btn.closest('.submodule-row').remove();
    subCount = Math.max(0, document.querySelectorAll('.submodule-row').length);
    reNumber();
    if (!document.querySelector('.submodule-row')) {
        document.getElementById('subList').innerHTML =
            '<p class="text-sm text-gray-400 italic" id="noSubMsg">No submodules yet.</p>';
    }
}

function reNumber() {
    document.querySelectorAll('.submodule-row').forEach((row, i) => {
        row.querySelector('span').textContent = i + 1;
    });
}
</script>
</body>
</html>
