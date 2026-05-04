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
    setFlash('error', 'Invalid course.');
    redirect(BASE_URL . '/admin/course/courses.php');
}

$course = $db->prepare("SELECT * FROM courses WHERE id = ?");
$course->execute([$id]);
$course = $course->fetch();

if (!$course) {
    setFlash('error', 'Course not found.');
    redirect(BASE_URL . '/admin/course/courses.php');
}

$errors = [];

if (isPost()) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token invalid. Please refresh and try again.';
    } else {
        $courseName  = trim($_POST['course_name']  ?? '');
        $description = trim($_POST['description']  ?? '');
        $status      = $_POST['status'] ?? 'active';

        if (empty($courseName))        $errors[] = 'Course name is required.';
        if (strlen($courseName) > 150) $errors[] = 'Course name too long (max 150 chars).';
        if (!in_array($status, ['active', 'inactive'])) $errors[] = 'Invalid status.';

        if (empty($errors)) {
            try {
                $old = $course;
                $stmt = $db->prepare("
                    UPDATE courses SET course_name = ?, description = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([$courseName, $description, $status, $id]);

                logActivity('UPDATE_COURSE', 'courses',
                    "Updated course: {$courseName} [{$course['course_code']}]",
                    ['course_name' => $old['course_name'], 'status' => $old['status']],
                    ['course_name' => $courseName, 'status' => $status]
                );

                setFlash('success', "Course updated successfully.");
                redirect(BASE_URL . '/admin/course/courses.php');
            } catch (PDOException $e) {
                error_log('[GyanSetu] edit_course error: ' . $e->getMessage());
                $errors[] = 'Database error. Please try again.';
            }
        }
    }
}

$csrf = generateCSRFToken();
$page_title = 'Edit Course — GyanSetu';
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
        <h1 class="text-2xl font-bold text-gray-800">Edit Course</h1>
        <p class="text-sm text-gray-500 mt-0.5 font-mono text-[#E87F24]"><?= sanitize($course['course_code']) ?></p>
      </div>
    </div>

    <?php renderFlashMessages(); ?>

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

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
      <form method="POST" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

        <!-- Course Code (readonly) -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Course Code</label>
          <div class="px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-sm font-mono text-gray-500">
            <?= sanitize($course['course_code']) ?>
          </div>
          <p class="text-xs text-gray-400 mt-1">Course code cannot be changed.</p>
        </div>

        <!-- Course Name -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">
            Course Name <span class="text-red-500">*</span>
          </label>
          <input type="text" name="course_name" maxlength="150"
            value="<?= sanitize($_POST['course_name'] ?? $course['course_name']) ?>"
            class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:border-[#E87F24] focus:ring-2 focus:ring-[#E87F24]/20 transition text-sm">
        </div>

        <!-- Description -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
          <textarea name="description" rows="4" maxlength="1000"
            class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:border-[#E87F24] focus:ring-2 focus:ring-[#E87F24]/20 transition text-sm resize-none"><?= sanitize($_POST['description'] ?? $course['description']) ?></textarea>
        </div>

        <!-- Status -->
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
          <div class="flex gap-4">
            <?php foreach (['active' => 'Active', 'inactive' => 'Inactive'] as $val => $label): ?>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" name="status" value="<?= $val ?>"
                  <?= (($_POST['status'] ?? $course['status']) === $val) ? 'checked' : '' ?>
                  class="accent-[#E87F24]">
                <span class="text-sm font-medium text-gray-700"><?= $label ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="flex items-center gap-3 pt-2">
          <button type="submit"
            class="flex-1 bg-[#E87F24] hover:bg-[#d4711f] text-white font-semibold py-3 rounded-xl transition flex items-center justify-center gap-2 shadow-sm">
            <i class="ri-save-line"></i> Save Changes
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
