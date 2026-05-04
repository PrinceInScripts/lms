<?php
define('GYANSETU_APP', true);
require_once __DIR__ . '/../../../includes/security_headers.php';
require_once __DIR__ . '/../../../includes/db_conn.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/activity_logger.php';
require_once __DIR__ . '/../../../includes/auth.php';
requireAdmin();

$db = getDB();

// Pre-fill from query params if coming from course list
$preselectedCourse = sanitizeInt($_GET['course_id'] ?? 0);
$preselectedBatch  = sanitizeInt($_GET['batch_id']  ?? 0);

$errors = [];

// Fetch batches and courses for dropdowns
$batches = $db->query("SELECT id, batch_id, batch_name FROM batches WHERE status != 'cancelled' ORDER BY batch_name")->fetchAll();
$courses = $db->query("SELECT id, course_code, course_name FROM courses WHERE status = 'active' ORDER BY course_name")->fetchAll();

if (isPost()) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token invalid. Please refresh and try again.';
    } else {
        $batchId  = sanitizeInt($_POST['batch_id']  ?? 0);
        $courseId = sanitizeInt($_POST['course_id'] ?? 0);


        

        if (!$batchId)  $errors[] = 'Please select a batch.';
        if (!$courseId) $errors[] = 'Please select a course.';

        if (empty($errors)) {
            // Validate both exist
            $b = $db->prepare("SELECT batch_id, batch_name FROM batches WHERE id = ?");
            $b->execute([$batchId]);
            $batch = $b->fetch();

            print_r($batch); // Debug log

            $c = $db->prepare("SELECT course_code, course_name FROM courses WHERE id = ?");
            $c->execute([$courseId]);
            $course = $c->fetch();

              print_r($course); // Debug log

            if (!$batch || !$course) {
                $errors[] = 'Invalid batch or course selected.';
            } else {
                // Duplicate check
                $dup = $db->prepare("SELECT 1 FROM batch_courses WHERE batch_id = ? AND course_id = ?");
                $dup->execute([$batch['batch_id'], $courseId]);
                if ($dup->fetchColumn()) {
                    $errors[] = "Course \"{$course['course_name']}\" is already assigned to batch \"{$batch['batch_name']}\".";
                } else {
                    try {
                        $db->prepare("INSERT INTO batch_courses (batch_id, course_id) VALUES (?, ?)")
                           ->execute([$batch['batch_id'], $courseId]);

                        logActivity('ASSIGN_COURSE_TO_BATCH', 'courses',
                            "Assigned course {$course['course_name']} [{$course['course_code']}] to batch {$batch['batch_name']} [{$batch['batch_id']}]",
                            null,
                            ['batch_id' => $batchId, 'course_id' => $courseId]
                        );

                        setFlash('success', "Course \"{$course['course_name']}\" assigned to \"{$batch['batch_name']}\" successfully.");
                        redirect(BASE_URL . '/admin/batch/course/batch_courses.php?batch_id=' . $batch['batch_id']);
                    } catch (PDOException $e) {
                        error_log('[GyanSetu] assign_course error: ' . $e->getMessage());
                        $errors[] = 'Database error. Please try again. ';
                    }
                }
            }
        }
    }
}

$csrf = generateCSRFToken();
$page_title = 'Assign Course to Batch — GyanSetu';
require_once __DIR__ . '/../../../admin/sidebar.php';
?>
<div class="main-content">
  <div class="p-6 max-w-xl mx-auto">

    <!-- Header -->
    <div class="flex items-center gap-3 mb-8">
      <a href="batch_courses.php" class="w-9 h-9 rounded-xl bg-white border border-gray-200 flex items-center justify-center hover:bg-gray-50 transition shadow-sm">
        <i class="ri-arrow-left-line text-gray-600"></i>
      </a>
      <div>
        <h1 class="text-2xl font-bold text-gray-800">Assign Course to Batch</h1>
        <p class="text-sm text-gray-500 mt-0.5">Link an active course to a batch</p>
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

        <!-- Select Batch -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">
            Select Batch <span class="text-red-500">*</span>
          </label>
          <?php if (empty($batches)): ?>
            <p class="text-sm text-gray-400 italic">No active batches available.</p>
          <?php else: ?>
            <select name="batch_id" required
              class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:border-[#E87F24] focus:ring-2 focus:ring-[#E87F24]/20 transition text-sm">
              <option value="">— Choose a Batch —</option>
              <?php foreach ($batches as $b): ?>
                <option value="<?= (int)$b['id'] ?>"
                  <?= ((int)($_POST['batch_id'] ?? $preselectedBatch) === (int)$b['id']) ? 'selected' : '' ?>>
                  <?= sanitize($b['batch_name']) ?> (<?= sanitize($b['batch_id']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>

        <!-- Select Course -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">
            Select Course <span class="text-red-500">*</span>
          </label>
          <?php if (empty($courses)): ?>
            <p class="text-sm text-gray-400 italic">No active courses available.
              <a href="../../course/add_course.php" class="text-[#E87F24] underline">Add one?</a>
            </p>
          <?php else: ?>
            <select name="course_id" required
              class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:border-[#E87F24] focus:ring-2 focus:ring-[#E87F24]/20 transition text-sm">
              <option value="">— Choose a Course —</option>
              <?php foreach ($courses as $c): ?>
                <option value="<?= (int)$c['id'] ?>"
                  <?= ((int)($_POST['course_id'] ?? $preselectedCourse) === (int)$c['id']) ? 'selected' : '' ?>>
                  <?= sanitize($c['course_name']) ?> (<?= sanitize($c['course_code']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>

        <!-- Info -->
        <div class="bg-[#FEFDDF] border border-[#FFC81E]/40 rounded-xl p-4 flex items-start gap-3">
          <i class="ri-information-line text-[#E87F24] text-lg flex-shrink-0 mt-0.5"></i>
          <p class="text-sm text-gray-600">Each batch–course pair is unique. Duplicate assignments will be rejected.</p>
        </div>

        <div class="flex items-center gap-3 pt-2">
          <button type="submit"
            class="flex-1 bg-[#E87F24] hover:bg-[#d4711f] text-white font-semibold py-3 rounded-xl transition flex items-center justify-center gap-2 shadow-sm">
            <i class="ri-links-line"></i> Assign Course
          </button>
          <a href="batch_courses.php"
            class="px-6 py-3 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 font-medium transition text-sm">
            Cancel
          </a>
        </div>
      </form>
    </div>

    <!-- Quick link -->
    <p class="text-center text-sm text-gray-400 mt-4">
      Need to view batch courses?
      <a href="batch_courses.php" class="text-[#73A5CA] hover:underline">View All</a>
    </p>
  </div>
</div>
</body>
</html>
