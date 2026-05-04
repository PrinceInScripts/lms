<?php
define('GYANSETU_APP', true);
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/db_conn.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/activity_logger.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db = getDB();

// Search & filter
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$params = [];
$where  = "WHERE 1=1";
if ($search !== '') {
    $where .= " AND (c.course_name LIKE ? OR c.course_code LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if (in_array($statusFilter, ['active', 'inactive'])) {
    $where .= " AND c.status = ?";
    $params[] = $statusFilter;
}

$stmt = $db->prepare("
    SELECT c.*,
           COUNT(DISTINCT bc.batch_id)        AS batch_count,
           COUNT(DISTINCT cm.id)              AS module_count
    FROM   courses c
    LEFT JOIN batch_courses bc ON bc.course_id = c.id
    LEFT JOIN course_modules cm ON cm.course_id = c.id
    $where
    GROUP BY c.id
    ORDER BY c.id DESC
");
$stmt->execute($params);
$courses = $stmt->fetchAll();

$page_title = 'Courses — GyanSetu';
require_once __DIR__ . '/../../admin/sidebar.php';
?>
<div class="main-content">
  <div class="p-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">Courses</h1>
        <p class="text-sm text-gray-500 mt-0.5"><?= count($courses) ?> course<?= count($courses) !== 1 ? 's' : '' ?> found</p>
      </div>
      <a href="add_course.php"
        class="inline-flex items-center gap-2 bg-[#E87F24] hover:bg-[#d4711f] text-white font-semibold px-5 py-2.5 rounded-xl transition shadow-sm text-sm">
        <i class="ri-add-circle-line text-base"></i> Add Course
      </a>
    </div>

    <?php renderFlashMessages(); ?>

    <!-- Filters -->
    <form method="GET" class="flex flex-col sm:flex-row gap-3 mb-6">
      <div class="relative flex-1">
        <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
        <input type="text" name="search" value="<?= sanitize($search) ?>"
          placeholder="Search by course name or code..."
          class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#E87F24] text-sm">
      </div>
      <select name="status"
        class="px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[#E87F24]">
        <option value="">All Status</option>
        <option value="active"   <?= $statusFilter === 'active'   ? 'selected' : '' ?>>Active</option>
        <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
      </select>
      <button type="submit"
        class="px-5 py-2.5 bg-[#73A5CA] text-white rounded-xl text-sm font-medium hover:bg-[#5a8fb5] transition">
        Filter
      </button>
      <?php if ($search || $statusFilter): ?>
        <a href="courses.php" class="px-5 py-2.5 border border-gray-200 text-gray-600 rounded-xl text-sm hover:bg-gray-50 transition">
          Clear
        </a>
      <?php endif; ?>
    </form>

    <!-- Course Cards Grid -->
    <?php if (empty($courses)): ?>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-16 text-center">
        <div class="w-16 h-16 bg-[#FEFDDF] rounded-2xl flex items-center justify-center mx-auto mb-4">
          <i class="ri-book-open-line text-3xl text-[#E87F24]"></i>
        </div>
        <h3 class="font-semibold text-gray-700 mb-1">No courses found</h3>
        <p class="text-sm text-gray-400 mb-4">Start by adding your first course.</p>
        <a href="add_course.php" class="inline-flex items-center gap-2 bg-[#E87F24] text-white px-5 py-2.5 rounded-xl text-sm font-medium hover:bg-[#d4711f] transition">
          <i class="ri-add-line"></i> Add Course
        </a>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        <?php foreach ($courses as $c): ?>
          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all duration-200 flex flex-col">
            <!-- Card Header -->
            <div class="p-5 border-b border-gray-50">
              <div class="flex items-start justify-between gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                  style="background: linear-gradient(135deg,#E87F24,#FFC81E)">
                  <i class="ri-book-2-line text-white text-lg"></i>
                </div>
                <?php if ($c['status'] === 'active'): ?>
                  <span class="px-2.5 py-0.5 bg-green-100 text-green-700 text-xs font-semibold rounded-full">Active</span>
                <?php else: ?>
                  <span class="px-2.5 py-0.5 bg-gray-100 text-gray-500 text-xs font-semibold rounded-full">Inactive</span>
                <?php endif; ?>
              </div>
              <h3 class="font-bold text-gray-800 text-base leading-snug mb-1">
                <?= sanitize($c['course_name']) ?>
              </h3>
              <span class="inline-block text-xs font-mono text-[#E87F24] bg-orange-50 px-2 py-0.5 rounded-md">
                <?= sanitize($c['course_code']) ?>
              </span>
              <?php if ($c['description']): ?>
                <p class="text-sm text-gray-500 mt-2 line-clamp-2">
                  <?= sanitize(truncate($c['description'], 100)) ?>
                </p>
              <?php endif; ?>
            </div>

            <!-- Stats -->
            <div class="px-5 py-3 flex gap-4 bg-gray-50/50">
              <div class="text-center">
                <p class="text-lg font-bold text-[#73A5CA]"><?= (int)$c['batch_count'] ?></p>
                <p class="text-xs text-gray-400">Batches</p>
              </div>
              <div class="w-px bg-gray-200"></div>
              <div class="text-center">
                <p class="text-lg font-bold text-[#E87F24]"><?= (int)$c['module_count'] ?></p>
                <p class="text-xs text-gray-400">Modules</p>
              </div>
            </div>

            <!-- Actions -->
            <div class="px-5 py-4 flex gap-2 mt-auto">
              <a href="edit_course.php?id=<?= (int)$c['id'] ?>"
                class="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 text-xs font-medium transition">
                <i class="ri-edit-line"></i> Edit
              </a>
              <a href="../batch/course/assign_course.php?course_id=<?= (int)$c['id'] ?>"
                class="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-xl border border-[#73A5CA] text-[#73A5CA] hover:bg-[#73A5CA] hover:text-white text-xs font-medium transition">
                <i class="ri-links-line"></i> Assign
              </a>
              <a href="delete_course.php?id=<?= (int)$c['id'] ?>"
                onclick="return confirm('Delete this course? This cannot be undone.')"
                class="w-9 h-9 flex items-center justify-center rounded-xl border border-red-100 text-red-400 hover:bg-red-50 transition">
                <i class="ri-delete-bin-line text-sm"></i>
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>
</div>

<style>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
</body>
</html>
