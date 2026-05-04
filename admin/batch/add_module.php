<?php
define('GYANSETU_APP', true);
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/db_conn.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/activity_logger.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db     = getDB();
$errors = [];

// Pre-fill course from query
$preselectedCourse = sanitizeInt($_GET['course_id'] ?? 0);

// Fetch active courses
$courses = $db->query("SELECT id, course_code, course_name FROM courses WHERE status = 'active' ORDER BY course_name")->fetchAll();

if (isPost()) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token invalid. Please refresh and try again.';
    } else {
        $courseId    = sanitizeInt($_POST['course_id']    ?? 0);
        $moduleName  = trim($_POST['module_name']  ?? '');
        $moduleOrder = sanitizeInt($_POST['module_order'] ?? 0) ?? 0;

        // Submodules
        $submoduleNames  = array_map('trim', $_POST['submodule_names']  ?? []);
        $submoduleOrders = array_map('intval', $_POST['submodule_orders'] ?? []);

        if (!$courseId)         $errors[] = 'Please select a course.';
        if (empty($moduleName)) $errors[] = 'Module name is required.';
        if (strlen($moduleName) > 200) $errors[] = 'Module name too long (max 200 chars).';

        // Validate submodules
        $submodules = [];
        foreach ($submoduleNames as $i => $name) {
            if ($name === '') continue;
            $submodules[] = [
                'name'  => $name,
                'order' => $submoduleOrders[$i] ?? ($i + 1),
            ];
        }

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                // Get next order if not set
                if ($moduleOrder <= 0) {
                    $maxOrder = $db->prepare("SELECT COALESCE(MAX(module_order),0)+1 FROM course_modules WHERE course_id = ?");
                    $maxOrder->execute([$courseId]);
                    $moduleOrder = (int)$maxOrder->fetchColumn();
                }

                $stmt = $db->prepare("
                    INSERT INTO course_modules (course_id, module_name, module_order)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$courseId, $moduleName, $moduleOrder]);
                $moduleId = (int)$db->lastInsertId();

                // Insert submodules
                foreach ($submodules as $i => $sub) {
                    $db->prepare("
                        INSERT INTO module_submodules (module_id, submodule_name, submodule_order)
                        VALUES (?, ?, ?)
                    ")->execute([$moduleId, $sub['name'], $sub['order']]);
                }

                $db->commit();

                // Fetch course name for log
                $cn = $db->prepare("SELECT course_name FROM courses WHERE id = ?");
                $cn->execute([$courseId]);
                $courseName = $cn->fetchColumn();

                logActivity('CREATE_MODULE', 'modules',
                    "Added module \"{$moduleName}\" to course \"{$courseName}\" with " . count($submodules) . " submodule(s)",
                    null,
                    ['module_id' => $moduleId, 'course_id' => $courseId, 'module_name' => $moduleName]
                );

                setFlash('success', "Module \"{$moduleName}\" added successfully" . (count($submodules) ? " with " . count($submodules) . " submodule(s)." : "."));
                redirect(BASE_URL . '/admin/batch/course/batch_course_modules.php?course_id=' . $courseId);
            } catch (PDOException $e) {
                $db->rollBack();
                error_log('[GyanSetu] add_module error: ' . $e->getMessage());
                $errors[] = 'Database error. Please try again.';
            }
        }
    }
}

$csrf = generateCSRFToken();
$page_title = 'Add Module — GyanSetu';
require_once __DIR__ . '/../../admin/sidebar.php';
?>
<div class="main-content">
  <div class="p-6 max-w-2xl mx-auto">

    <!-- Header -->
    <div class="flex items-center gap-3 mb-8">
      <a href="course/batch_course_modules.php" class="w-9 h-9 rounded-xl bg-white border border-gray-200 flex items-center justify-center hover:bg-gray-50 transition shadow-sm">
        <i class="ri-arrow-left-line text-gray-600"></i>
      </a>
      <div>
        <h1 class="text-2xl font-bold text-gray-800">Add Module</h1>
        <p class="text-sm text-gray-500 mt-0.5">Add a module and optional submodules to a course</p>
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

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 space-y-6">
      <form method="POST" id="moduleForm" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

        <!-- Select Course -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">
            Course <span class="text-red-500">*</span>
          </label>
          <?php if (empty($courses)): ?>
            <p class="text-sm text-gray-400 italic">No active courses. <a href="../course/add_course.php" class="text-[#E87F24] underline">Add one first.</a></p>
          <?php else: ?>
            <select name="course_id" required
              class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:border-[#E87F24] focus:ring-2 focus:ring-[#E87F24]/20 transition text-sm">
              <option value="">— Select a Course —</option>
              <?php foreach ($courses as $c): ?>
                <option value="<?= (int)$c['id'] ?>"
                  <?= ((int)($_POST['course_id'] ?? $preselectedCourse) === (int)$c['id']) ? 'selected' : '' ?>>
                  <?= sanitize($c['course_name']) ?> (<?= sanitize($c['course_code']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>

        <!-- Module Name -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">
            Module Name <span class="text-red-500">*</span>
          </label>
          <input type="text" name="module_name" maxlength="200"
            value="<?= sanitize($_POST['module_name'] ?? '') ?>"
            placeholder="e.g. Introduction to Python"
            class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:border-[#E87F24] focus:ring-2 focus:ring-[#E87F24]/20 transition text-sm">
        </div>

        <!-- Module Order -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Module Order</label>
          <input type="number" name="module_order" min="1" max="999"
            value="<?= sanitize($_POST['module_order'] ?? '') ?>"
            placeholder="Leave blank for auto"
            class="w-40 px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:border-[#E87F24] focus:ring-2 focus:ring-[#E87F24]/20 transition text-sm">
          <p class="text-xs text-gray-400 mt-1">Leave blank to append at end</p>
        </div>

        <!-- Submodules Section -->
        <div>
          <div class="flex items-center justify-between mb-3">
            <label class="text-sm font-semibold text-gray-700">Submodules</label>
            <button type="button" id="addSubmoduleBtn"
              class="inline-flex items-center gap-1.5 text-xs font-medium text-[#E87F24] hover:text-[#d4711f] transition">
              <i class="ri-add-circle-line text-base"></i> Add Submodule
            </button>
          </div>

          <div id="submoduleList" class="space-y-3">
            <?php
            $existingSubs = $_POST['submodule_names'] ?? [];
            $existingOrders = $_POST['submodule_orders'] ?? [];
            if (empty($existingSubs)): ?>
              <p class="text-sm text-gray-400 italic" id="noSubmodulesMsg">No submodules added yet.</p>
            <?php else:
              foreach ($existingSubs as $i => $sName): ?>
                <div class="submodule-row flex items-center gap-3">
                  <span class="text-xs text-gray-400 w-5 text-right"><?= $i + 1 ?></span>
                  <input type="text" name="submodule_names[]"
                    value="<?= sanitize($sName) ?>"
                    placeholder="Submodule name"
                    class="flex-1 px-3 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#E87F24] text-sm">
                  <input type="number" name="submodule_orders[]"
                    value="<?= (int)($existingOrders[$i] ?? ($i + 1)) ?>"
                    placeholder="Order" min="1"
                    class="w-20 px-3 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#E87F24] text-sm text-center">
                  <button type="button" onclick="removeSubmodule(this)"
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
            <i class="ri-save-line"></i> Save Module
          </button>
          <a href="course/batch_course_modules.php"
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

document.getElementById('addSubmoduleBtn').addEventListener('click', () => {
    const msg = document.getElementById('noSubmodulesMsg');
    if (msg) msg.remove();

    subCount++;
    const list = document.getElementById('submoduleList');
    const row = document.createElement('div');
    row.className = 'submodule-row flex items-center gap-3';
    row.innerHTML = `
        <span class="text-xs text-gray-400 w-5 text-right">${subCount}</span>
        <input type="text" name="submodule_names[]"
            placeholder="Submodule name"
            class="flex-1 px-3 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#E87F24] text-sm">
        <input type="number" name="submodule_orders[]"
            value="${subCount}" placeholder="Order" min="1"
            class="w-20 px-3 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#E87F24] text-sm text-center">
        <button type="button" onclick="removeSubmodule(this)"
            class="w-8 h-8 rounded-lg text-red-400 hover:bg-red-50 flex items-center justify-center transition flex-shrink-0">
            <i class="ri-delete-bin-line text-sm"></i>
        </button>
    `;
    list.appendChild(row);
    row.querySelector('input[name="submodule_names[]"]').focus();
    updateNumbers();
});

function removeSubmodule(btn) {
    btn.closest('.submodule-row').remove();
    subCount = Math.max(0, subCount - 1);
    updateNumbers();
    if (!document.querySelector('.submodule-row')) {
        const list = document.getElementById('submoduleList');
        list.innerHTML = '<p class="text-sm text-gray-400 italic" id="noSubmodulesMsg">No submodules added yet.</p>';
    }
}

function updateNumbers() {
    document.querySelectorAll('.submodule-row').forEach((row, i) => {
        row.querySelector('span').textContent = i + 1;
    });
}
</script>
</body>
</html>
