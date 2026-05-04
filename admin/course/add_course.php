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
$old    = [];

// ── Generate unique course_code ────────────────────────────────────────────
function generateCourseCode(PDO $db): string {
    do {
        $num       = str_pad(random_int(1, 999), 3, '0', STR_PAD_LEFT);
        $candidate = 'CRS' . $num;
        $chk       = $db->prepare("SELECT 1 FROM courses WHERE course_code = ?");
        $chk->execute([$candidate]);
    } while ($chk->fetchColumn());
    return $candidate;
}

// ── POST handler ───────────────────────────────────────────────────────────
if (isPost()) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token invalid. Please refresh and try again.';
    } else {
        $old         = $_POST;
        $courseName  = trim($_POST['course_name']  ?? '');
        $description = trim($_POST['description']  ?? '');
        $status      = $_POST['status'] ?? 'active';

        if (empty($courseName))            $errors[] = 'Course name is required.';
        if (strlen($courseName) > 150)     $errors[] = 'Course name too long (max 150 chars).';
        if (!in_array($status, ['active', 'inactive'])) $errors[] = 'Invalid status.';

        if (empty($errors)) {
            try {
                $courseCode = generateCourseCode($db);
                $stmt = $db->prepare("
                    INSERT INTO courses (course_code, course_name, description, status)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$courseCode, $courseName, $description, $status]);
                $newId = $db->lastInsertId();

                logActivity('CREATE_COURSE', 'courses',
                    "Created course: {$courseName} [{$courseCode}]",
                    null,
                    ['id' => $newId, 'course_code' => $courseCode, 'course_name' => $courseName]
                );

                setFlash('success', "Course \"{$courseName}\" created successfully with code {$courseCode}.");
                redirect(BASE_URL . '/admin/course/courses.php');
            } catch (PDOException $e) {
                error_log('[GyanSetu] add_course error: ' . $e->getMessage());
                $errors[] = 'Database error. Please try again.';
            }
        }
    }
}

$csrf = generateCSRFToken();
$page_title = 'Add Course — GyanSetu';
require_once __DIR__ . '/../../admin/sidebar.php';
?>
<div class="main-content">
  <div class="p-6 max-w-2xl mx-auto">

    <!-- Header -->
    <div class="flex items-center gap-3 mb-8">
      <a href="courses.php" class="w-9 h-9 rounded-xl bg-white border border-gray-200 flex items-center justify-center hover:bg-gray-50 transition shadow-sm">
        <i class="ri-arrow-left-line text-gray-600"></i>
      </a>
      <div>
        <h1 class="text-2xl font-bold text-gray-800">Add New Course</h1>
        <p class="text-sm text-gray-500 mt-0.5">Course code will be auto-generated</p>
      </div>
    </div>

    <!-- Flash messages -->
    <?php renderFlashMessages(); ?>

    <!-- Errors -->
    <?php if (!empty($errors)): ?>
      <div class="bg-red-50 border-l-4 border-red-400 rounded-xl p-4 mb-6">
        <div class="flex items-start gap-3">
          <i class="ri-error-warning-line text-red-500 text-lg mt-0.5 flex-shrink-0"></i>
          <ul class="space-y-1">
            <?php foreach ($errors as $e): ?>
              <li class="text-sm text-red-700"><?= sanitize($e) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    <?php endif; ?>

    <!-- Form Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
      <form method="POST" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

        <!-- Course Name -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">
            Course Name <span class="text-red-500">*</span>
          </label>
          <input type="text" name="course_name" maxlength="150"
            value="<?= sanitize($old['course_name'] ?? '') ?>"
            placeholder="e.g. Advanced Python Programming"
            class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:border-[#E87F24] focus:ring-2 focus:ring-[#E87F24]/20 transition text-sm">
        </div>

        <!-- Description -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
          <textarea name="description" rows="4" maxlength="1000"
            placeholder="Brief overview of what this course covers..."
            class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:border-[#E87F24] focus:ring-2 focus:ring-[#E87F24]/20 transition text-sm resize-none"><?= sanitize($old['description'] ?? '') ?></textarea>
          <p class="text-xs text-gray-400 mt-1">Max 1000 characters</p>
        </div>

        <!-- Status -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
          <div class="flex gap-4">
            <?php foreach (['active' => ['Active', 'green'], 'inactive' => ['Inactive', 'gray']] as $val => [$label, $color]): ?>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" name="status" value="<?= $val ?>"
                  <?= (($old['status'] ?? 'active') === $val) ? 'checked' : '' ?>
                  class="accent-[#E87F24]">
                <span class="text-sm font-medium text-gray-700"><?= $label ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Info box -->
        <div class="bg-[#FEFDDF] border border-[#FFC81E]/40 rounded-xl p-4 flex items-start gap-3">
          <i class="ri-information-line text-[#E87F24] text-lg flex-shrink-0 mt-0.5"></i>
          <p class="text-sm text-gray-600">Course code (e.g. <strong>CRS001</strong>) will be auto-generated and cannot be changed later.</p>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-3 pt-2">
          <button type="submit"
            class="flex-1 bg-[#E87F24] hover:bg-[#d4711f] text-white font-semibold py-3 rounded-xl transition flex items-center justify-center gap-2 shadow-sm">
            <i class="ri-add-circle-line"></i> Create Course
          </button>
          <a href="courses.php"
            class="px-6 py-3 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 font-medium transition text-sm">
            Cancel
          </a>
        </div>
      </form>
    </div>

  </div>
</div>
</body>
</html>
