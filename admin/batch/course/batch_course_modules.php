<?php
define('GYANSETU_APP', true);
require_once __DIR__ . '/../../../includes/security_headers.php';
require_once __DIR__ . '/../../../includes/db_conn.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/activity_logger.php';
require_once __DIR__ . '/../../../includes/auth.php';
requireAdmin();

$db = getDB();

$courseId = sanitizeInt($_GET['course_id'] ?? 0);
$batchId  = sanitizeInt($_GET['batch_id']  ?? 0);

// Fetch course
$course = null;
if ($courseId) {
    $stmt = $db->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$courseId]);
    $course = $stmt->fetch();
}

// Fetch all active courses (for switcher)
$allCourses = $db->query("SELECT id, course_code, course_name FROM courses WHERE status='active' ORDER BY course_name")->fetchAll();

// Fetch modules + submodules if course selected
$modules = [];
if ($course) {
    $mStmt = $db->prepare("
        SELECT cm.*, COUNT(ms.id) AS sub_count
        FROM course_modules cm
        LEFT JOIN module_submodules ms ON ms.module_id = cm.id
        WHERE cm.course_id = ?
        GROUP BY cm.id
        ORDER BY cm.module_order ASC, cm.id ASC
    ");
    $mStmt->execute([$courseId]);
    $moduleRows = $mStmt->fetchAll();

    foreach ($moduleRows as $mod) {
        $sStmt = $db->prepare("
            SELECT * FROM module_submodules
            WHERE module_id = ?
            ORDER BY submodule_order ASC, id ASC
        ");
        $sStmt->execute([$mod['id']]);
        $mod['submodules'] = $sStmt->fetchAll();
        $modules[] = $mod;
    }
}

$page_title = 'Course Modules — GyanSetu';
require_once __DIR__ . '/../../../admin/sidebar.php';
?>
<div class="main-content">
  <div class="p-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">Course Modules</h1>
        <p class="text-sm text-gray-500 mt-0.5">Browse modules and submodules by course</p>
      </div>
      <?php if ($course): ?>
        <a href="../../batch/add_module.php?course_id=<?= $courseId ?>"
          class="inline-flex items-center gap-2 bg-[#E87F24] hover:bg-[#d4711f] text-white font-semibold px-5 py-2.5 rounded-xl transition shadow-sm text-sm">
          <i class="ri-add-circle-line"></i> Add Module
        </a>
      <?php endif; ?>
    </div>

    <?php renderFlashMessages(); ?>

    <!-- Course Selector -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-6">
      <form method="GET" class="flex flex-col sm:flex-row gap-3 items-end">
        <?php if ($batchId): ?>
          <input type="hidden" name="batch_id" value="<?= $batchId ?>">
        <?php endif; ?>
        <div class="flex-1">
          <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Select Course</label>
          <select name="course_id"
            class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#E87F24] text-sm">
            <option value="">— Choose a Course —</option>
            <?php foreach ($allCourses as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $courseId === (int)$c['id'] ? 'selected' : '' ?>>
                <?= sanitize($c['course_name']) ?> (<?= sanitize($c['course_code']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit"
          class="px-6 py-2.5 bg-[#73A5CA] text-white rounded-xl text-sm font-medium hover:bg-[#5a8fb5] transition">
          View Modules
        </button>
      </form>
    </div>

    <!-- Module Hierarchy -->
    <?php if (!$course): ?>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-16 text-center">
        <div class="w-16 h-16 bg-[#FEFDDF] rounded-2xl flex items-center justify-center mx-auto mb-4">
          <i class="ri-layout-line text-3xl text-[#E87F24]"></i>
        </div>
        <h3 class="font-semibold text-gray-700 mb-1">Select a course above</h3>
        <p class="text-sm text-gray-400">Choose a course to view its module structure.</p>
      </div>
    <?php elseif (empty($modules)): ?>
      <!-- Course header + empty state -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100 flex items-center gap-4" style="background:linear-gradient(135deg,#FEFDDF,#fff)">
          <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0"
            style="background:linear-gradient(135deg,#E87F24,#FFC81E)">
            <i class="ri-book-2-line text-white text-xl"></i>
          </div>
          <div>
            <h2 class="font-bold text-gray-800 text-lg"><?= sanitize($course['course_name']) ?></h2>
            <span class="text-xs font-mono text-[#E87F24]"><?= sanitize($course['course_code']) ?></span>
          </div>
        </div>
        <div class="p-12 text-center">
          <i class="ri-folder-open-line text-4xl text-gray-200 mb-3 block"></i>
          <p class="text-gray-500 text-sm mb-4">No modules added to this course yet.</p>
          <a href="../../batch/add_module.php?course_id=<?= $courseId ?>"
            class="inline-flex items-center gap-2 bg-[#E87F24] text-white px-5 py-2.5 rounded-xl text-sm font-medium hover:bg-[#d4711f] transition">
            <i class="ri-add-line"></i> Add First Module
          </a>
        </div>
      </div>
    <?php else: ?>
      <!-- Course Card -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <!-- Course Header -->
        <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between"
          style="background:linear-gradient(135deg,#FEFDDF 0%,#fff 100%)">
          <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0"
              style="background:linear-gradient(135deg,#E87F24,#FFC81E)">
              <i class="ri-book-2-line text-white text-xl"></i>
            </div>
            <div>
              <h2 class="font-bold text-gray-800 text-lg"><?= sanitize($course['course_name']) ?></h2>
              <div class="flex items-center gap-3 mt-0.5">
                <span class="text-xs font-mono text-[#E87F24]"><?= sanitize($course['course_code']) ?></span>
                <span class="text-xs text-gray-400">•</span>
                <span class="text-xs text-gray-500"><?= count($modules) ?> module<?= count($modules) !== 1 ? 's' : '' ?></span>
                <?php if ($course['description']): ?>
                  <span class="text-xs text-gray-400">•</span>
                  <span class="text-xs text-gray-500"><?= sanitize(truncate($course['description'], 60)) ?></span>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <a href="../../batch/add_module.php?course_id=<?= $courseId ?>"
            class="px-4 py-2 bg-[#E87F24] hover:bg-[#d4711f] text-white text-xs font-semibold rounded-xl transition">
            + Module
          </a>
        </div>

        <!-- Modules List -->
        <div class="divide-y divide-gray-50">
          <?php foreach ($modules as $modIdx => $mod): ?>
            <div class="module-item" id="module-<?= (int)$mod['id'] ?>">
              <!-- Module Row -->
              <div class="flex items-start gap-4 px-6 py-4 hover:bg-gray-50/50 transition group cursor-pointer"
                onclick="toggleModule(<?= (int)$mod['id'] ?>)">
                <!-- Order Badge -->
                <div class="w-7 h-7 rounded-lg bg-[#E87F24] flex items-center justify-center flex-shrink-0 mt-0.5 text-white text-xs font-bold">
                  <?= (int)$mod['module_order'] ?>
                </div>
                <!-- Info -->
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2">
                    <h3 class="font-semibold text-gray-800 text-sm"><?= sanitize($mod['module_name']) ?></h3>
                    <?php if ($mod['sub_count'] > 0): ?>
                      <span class="px-2 py-0.5 bg-[#73A5CA]/10 text-[#73A5CA] text-xs font-semibold rounded-full">
                        <?= (int)$mod['sub_count'] ?> sub
                      </span>
                    <?php endif; ?>
                  </div>
                </div>
                <!-- Actions -->
                <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition">
                  <a href="../../batch/edit_module.php?id=<?= (int)$mod['id'] ?>"
                    onclick="event.stopPropagation()"
                    class="w-7 h-7 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-white hover:border-gray-300 transition text-xs">
                    <i class="ri-edit-line"></i>
                  </a>
                  <a href="../../batch/delete_module.php?id=<?= (int)$mod['id'] ?>&course_id=<?= $courseId ?>"
                    onclick="event.stopPropagation(); return confirm('Delete this module and all its submodules?')"
                    class="w-7 h-7 flex items-center justify-center rounded-lg border border-red-100 text-red-400 hover:bg-red-50 transition text-xs">
                    <i class="ri-delete-bin-line"></i>
                  </a>
                </div>
                <!-- Toggle Icon -->
                <i class="ri-arrow-down-s-line text-gray-400 transition-transform duration-200 flex-shrink-0 mt-0.5"
                  id="toggle-icon-<?= (int)$mod['id'] ?>"></i>
              </div>

              <!-- Submodules (collapsible) -->
              <div class="submodule-panel hidden" id="subpanel-<?= (int)$mod['id'] ?>">
                <?php if (empty($mod['submodules'])): ?>
                  <div class="px-16 py-3 text-xs text-gray-400 italic flex items-center gap-2">
                    <i class="ri-folder-open-line"></i>
                    No submodules yet.
                    <a href="../../batch/edit_module.php?id=<?= (int)$mod['id'] ?>" class="text-[#E87F24] hover:underline">Add submodules</a>
                  </div>
                <?php else: ?>
                  <div class="pl-16 pr-6 pb-3 space-y-1.5">
                    <?php foreach ($mod['submodules'] as $si => $sub): ?>
                      <div class="flex items-center gap-3 py-2 px-4 rounded-xl hover:bg-gray-50 group/sub transition">
                        <div class="w-5 h-5 rounded-md bg-[#73A5CA]/15 flex items-center justify-center flex-shrink-0">
                          <span class="text-[10px] font-bold text-[#73A5CA]"><?= (int)$sub['submodule_order'] ?></span>
                        </div>
                        <span class="text-sm text-gray-600 flex-1"><?= sanitize($sub['submodule_name']) ?></span>
                        <a href="../../batch/edit_module.php?id=<?= (int)$mod['id'] ?>"
                          class="opacity-0 group-hover/sub:opacity-100 text-xs text-gray-400 hover:text-[#E87F24] transition">
                          <i class="ri-edit-line"></i>
                        </a>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
          <span class="text-xs text-gray-400">
            <?= count($modules) ?> module<?= count($modules) !== 1 ? 's' : '' ?> •
            <?= array_sum(array_column($modules, 'sub_count')) ?> submodule<?= array_sum(array_column($modules, 'sub_count')) !== 1 ? 's' : '' ?>
          </span>
          <a href="../../batch/add_module.php?course_id=<?= $courseId ?>"
            class="text-xs text-[#E87F24] hover:underline font-medium">+ Add Module</a>
        </div>
      </div>
    <?php endif; ?>

  </div>
</div>

<script>
function toggleModule(id) {
    const panel = document.getElementById('subpanel-' + id);
    const icon  = document.getElementById('toggle-icon-' + id);
    const isHidden = panel.classList.contains('hidden');
    panel.classList.toggle('hidden', !isHidden);
    icon.style.transform = isHidden ? 'rotate(180deg)' : '';
}
</script>
</body>
</html>
