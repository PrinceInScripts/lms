<?php
define('GYANSETU_APP', true);
require_once __DIR__ . '/../../../includes/security_headers.php';
require_once __DIR__ . '/../../../includes/db_conn.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/activity_logger.php';
require_once __DIR__ . '/../../../includes/auth.php';
requireAdmin();

$db = getDB();

// Optional filter by batch
$batchFilter = sanitizeInt($_GET['batch_id'] ?? 0);

// Fetch all batches for filter dropdown
$batches = $db->query("SELECT id, batch_id, batch_name FROM batches ORDER BY batch_name")->fetchAll();

// Build query
$params = [];
$batchWhere = '';
if ($batchFilter) {
    $batchWhere = 'AND b.id = ?';
    $params[] = $batchFilter;
}

$rows = $db->prepare("
    SELECT b.id AS batch_db_id, b.batch_id, b.batch_name, b.status AS batch_status,
           c.id AS course_id, c.course_code, c.course_name, c.status AS course_status,
           COUNT(DISTINCT cm.id) AS module_count
    FROM batch_courses bc
    JOIN batches b  ON b.batch_id  = bc.batch_id
    JOIN courses c  ON c.id  = bc.course_id
    LEFT JOIN course_modules cm ON cm.course_id = c.id
    WHERE 1=1 $batchWhere
    GROUP BY bc.batch_id, bc.course_id
    ORDER BY b.batch_name, c.course_name
");
$rows->execute($params);
$assignments = $rows->fetchAll();

// Group by batch for display
$grouped = [];
foreach ($assignments as $row) {
    $grouped[$row['batch_db_id']]['batch'] = [
        'id'     => $row['batch_db_id'],
        'bid'    => $row['batch_id'],
        'name'   => $row['batch_name'],
        'status' => $row['batch_status'],
    ];
    $grouped[$row['batch_db_id']]['courses'][] = $row;
}

$page_title = 'Batch Courses — GyanSetu';
require_once __DIR__ . '/../../../admin/sidebar.php';
?>
<div class="main-content">
  <div class="p-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">Batch Courses</h1>
        <p class="text-sm text-gray-500 mt-0.5"><?= count($assignments) ?> assignment<?= count($assignments) !== 1 ? 's' : '' ?> total</p>
      </div>
      <a href="assign_course.php"
        class="inline-flex items-center gap-2 bg-[#E87F24] hover:bg-[#d4711f] text-white font-semibold px-5 py-2.5 rounded-xl transition shadow-sm text-sm">
        <i class="ri-add-circle-line"></i> Assign Course
      </a>
    </div>

    <?php renderFlashMessages(); ?>

    <!-- Filter -->
    <form method="GET" class="flex gap-3 mb-6">
      <select name="batch_id"
        class="px-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-[#E87F24]">
        <option value="">All Batches</option>
        <?php foreach ($batches as $b): ?>
          <option value="<?= (int)$b['id'] ?>" <?= $batchFilter === (int)$b['id'] ? 'selected' : '' ?>>
            <?= sanitize($b['batch_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="px-5 py-2.5 bg-[#73A5CA] text-white rounded-xl text-sm font-medium hover:bg-[#5a8fb5] transition">
        Filter
      </button>
      <?php if ($batchFilter): ?>
        <a href="batch_courses.php" class="px-5 py-2.5 border border-gray-200 text-gray-600 rounded-xl text-sm hover:bg-gray-50 transition">Clear</a>
      <?php endif; ?>
    </form>

    <!-- Content -->
    <?php if (empty($grouped)): ?>
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-16 text-center">
        <div class="w-16 h-16 bg-[#FEFDDF] rounded-2xl flex items-center justify-center mx-auto mb-4">
          <i class="ri-links-line text-3xl text-[#E87F24]"></i>
        </div>
        <h3 class="font-semibold text-gray-700 mb-1">No assignments yet</h3>
        <p class="text-sm text-gray-400 mb-4">Assign courses to batches to get started.</p>
        <a href="assign_course.php" class="inline-flex items-center gap-2 bg-[#E87F24] text-white px-5 py-2.5 rounded-xl text-sm font-medium hover:bg-[#d4711f] transition">
          <i class="ri-add-line"></i> Assign Now
        </a>
      </div>
    <?php else: ?>
      <div class="space-y-6">
        <?php foreach ($grouped as $batchId => $group): $batch = $group['batch']; ?>
          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <!-- Batch Header -->
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between"
              style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%)">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-[#E87F24] flex items-center justify-center flex-shrink-0">
                  <i class="ri-group-line text-white text-sm"></i>
                </div>
                <div>
                  <h2 class="font-bold text-white text-base"><?= sanitize($batch['name']) ?></h2>
                  <span class="text-xs font-mono text-gray-400"><?= sanitize($batch['bid']) ?></span>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <span class="text-xs text-gray-400"><?= count($group['courses']) ?> course<?= count($group['courses']) !== 1 ? 's' : '' ?></span>
                <a href="assign_course.php?batch_id=<?= (int)$batchId ?>"
                  class="px-3 py-1.5 bg-[#E87F24] hover:bg-[#d4711f] text-white text-xs font-medium rounded-lg transition">
                  + Add Course
                </a>
              </div>
            </div>

            <!-- Courses Table -->
            <div class="overflow-x-auto">
              <table class="w-full">
                <thead>
                  <tr class="bg-gray-50 text-xs text-gray-500 font-semibold uppercase tracking-wide">
                    <th class="px-6 py-3 text-left">Course</th>
                    <th class="px-6 py-3 text-left">Code</th>
                    <th class="px-6 py-3 text-center">Modules</th>
                    <th class="px-6 py-3 text-center">Status</th>
                    <th class="px-6 py-3 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                  <?php foreach ($group['courses'] as $c): ?>
                    <tr class="hover:bg-gray-50/50 transition">
                      <td class="px-6 py-4">
                        <span class="font-medium text-gray-800 text-sm"><?= sanitize($c['course_name']) ?></span>
                      </td>
                      <td class="px-6 py-4">
                        <span class="font-mono text-xs text-[#E87F24] bg-orange-50 px-2 py-0.5 rounded-md">
                          <?= sanitize($c['course_code']) ?>
                        </span>
                      </td>
                      <td class="px-6 py-4 text-center">
                        <span class="text-sm font-semibold text-[#73A5CA]"><?= (int)$c['module_count'] ?></span>
                      </td>
                      <td class="px-6 py-4 text-center">
                        <?php if ($c['course_status'] === 'active'): ?>
                          <span class="px-2.5 py-0.5 bg-green-100 text-green-700 text-xs font-semibold rounded-full">Active</span>
                        <?php else: ?>
                          <span class="px-2.5 py-0.5 bg-gray-100 text-gray-500 text-xs font-semibold rounded-full">Inactive</span>
                        <?php endif; ?>
                      </td>
                      <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                          <a href="batch_course_modules.php?batch_id=<?= (int)$batchId ?>&course_id=<?= (int)$c['course_id'] ?>"
                            class="px-3 py-1.5 text-xs font-medium text-[#73A5CA] border border-[#73A5CA] rounded-lg hover:bg-[#73A5CA] hover:text-white transition">
                            View Modules
                          </a>
                          <a href="remove_batch_course.php?batch_id=<?= (int)$batchId ?>&course_id=<?= (int)$c['course_id'] ?>"
                            onclick="return confirm('Remove this course from the batch?')"
                            class="px-3 py-1.5 text-xs font-medium text-red-400 border border-red-200 rounded-lg hover:bg-red-50 transition">
                            Remove
                          </a>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>
</div>
</body>
</html>
