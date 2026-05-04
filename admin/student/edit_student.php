<?php
define('GYANSETU_APP', true);
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/db_conn.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/activity_logger.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db     = getDB();
$userId = sanitizeInt($_GET['id'] ?? 0);
$errors = [];

if (!$userId) {
    setFlash('error', 'Invalid student ID.');
    redirect('/admin/students/students.php');
}

// ── Load existing data ─────────────────────────────────────────────────────
$stmt = $db->prepare("
    SELECT u.id AS user_id, u.username, u.email, u.status AS user_status,
           sd.*
    FROM users u
    JOIN student_details sd ON sd.user_id = u.id
    WHERE u.id = ? AND u.role = 'student'
");
$stmt->execute([$userId]);
$student = $stmt->fetch();

if (!$student) {
    setFlash('error', 'Student not found.');
    redirect('/admin/students/students.php');
}

// ── POST: handle update ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token invalid. Please refresh and try again.';
    } else {
        $username    = trim($_POST['username']    ?? '');
        $email       = trim($_POST['email']       ?? '');
        $fullName    = trim($_POST['full_name']   ?? '');
        $phone       = trim($_POST['phone']       ?? '');
        $altPhone    = trim($_POST['alt_phone']   ?? '');
        $dob         = trim($_POST['dob']         ?? '');
        $gender      = $_POST['gender']           ?? '';
        $stream      = $_POST['stream']           ?? '';
        $customStream= trim($_POST['custom_stream'] ?? '');
        $fatherName  = trim($_POST['father_name'] ?? '');
        $fatherPhone = trim($_POST['father_phone'] ?? '');
        $address     = trim($_POST['address']     ?? '');
        $city        = trim($_POST['city']        ?? '');
        $state       = trim($_POST['state']       ?? '');
        $pincode     = trim($_POST['pincode']     ?? '');
        $enrollDate  = $_POST['enrollment_date']  ?? '';
        $status      = $_POST['current_status']   ?? 'active';
        $newPassword = $_POST['new_password']     ?? '';

        // Validation
        if (empty($username))                    $errors[] = 'Username is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
        if (empty($fullName))                    $errors[] = 'Full name is required.';
        if (!preg_match('/^[6-9]\d{9}$/', $phone)) $errors[] = 'Valid 10-digit mobile number required.';
        if (!in_array($gender, ['Male','Female','Other'])) $errors[] = 'Please select a gender.';
        if (!in_array($stream, ["Let's Win","Super 30","Other"])) $errors[] = 'Please select a stream.';
        if ($newPassword !== '' && strlen($newPassword) < 8) $errors[] = 'New password must be at least 8 characters.';

        // Duplicate username/email (exclude current user)
        if (empty($errors)) {
            $chk = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $chk->execute([$username, $userId]);
            if ($chk->fetch()) $errors[] = "Username '{$username}' is already taken.";

            $chk2 = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $chk2->execute([$email, $userId]);
            if ($chk2->fetch()) $errors[] = "Email '{$email}' is already registered.";
        }

        if (empty($errors)) {
            // Snapshot old data for the activity log
            $oldSnapshot = [
                'username' => $student['username'],
                'email'    => $student['email'],
                'full_name'=> $student['full_name'],
                'phone'    => $student['phone'],
                'stream'   => $student['stream'],
                'status'   => $student['current_status'],
            ];

            try {
                $db->beginTransaction();

                // 1. Update users
                if ($newPassword !== '') {
                    $stmtU = $db->prepare("UPDATE users SET username=?, email=?, password_hash=? WHERE id=?");
                    $stmtU->execute([$username, strtolower($email), password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
                } else {
                    $stmtU = $db->prepare("UPDATE users SET username=?, email=? WHERE id=?");
                    $stmtU->execute([$username, strtolower($email), $userId]);
                }

                // 2. Update student_details
                $stmtD = $db->prepare("
                    UPDATE student_details SET
                        full_name=?, phone=?, alternate_phone=?, email=?,
                        date_of_birth=?, gender=?, stream=?, custom_stream=?,
                        father_name=?, father_phone=?,
                        address=?, city=?, state=?, pincode=?,
                        enrollment_date=?, current_status=?
                    WHERE user_id=?
                ");
                $stmtD->execute([
                    $fullName, $phone,
                    $altPhone  ?: null, strtolower($email),
                    $dob       ?: null, $gender,
                    $stream, $stream === 'Other' ? ($customStream ?: null) : null,
                    $fatherName ?: null, $fatherPhone ?: null,
                    $address ?: null, $city ?: null, $state ?: null, $pincode ?: null,
                    $enrollDate, $status, $userId,
                ]);

                $db->commit();

                $newSnapshot = [
                    'username' => $username,
                    'email'    => strtolower($email),
                    'full_name'=> $fullName,
                    'phone'    => $phone,
                    'stream'   => $stream,
                    'status'   => $status,
                ];

                logActivity('UPDATE_STUDENT', 'students',
                    "Updated student '{$fullName}' (user_id: {$userId})",
                    $oldSnapshot, $newSnapshot);

                // Re-fetch fresh data for the form
                $stmt->execute([$userId]);
                $student = $stmt->fetch();

                setFlash('success', "Student {$fullName} updated successfully.");
                redirect('/admin/student/edit_student.php?id=' . $userId);

            } catch (Exception $e) {
                $db->rollBack();
                error_log('[GyanSetu] edit_student error: ' . $e->getMessage());
                $errors[] = 'Database error. Please try again.';
            }
        }
    }
}

$csrfToken = generateCSRFToken();
$streams   = ["Let's Win", "Super 30", "Other"];
$statuses  = ['active' => 'Active', 'on_hold' => 'On Hold', 'dropped' => 'Dropped', 'transferred' => 'Transferred', 'completed' => 'Completed'];
$genders   = ['Male', 'Female', 'Other'];
?>
<?php
$page_title = 'Edit Student — GyanSetu';
include __DIR__ . '/../sidebar.php';
?>
<style>
* { font-family: 'Inter', sans-serif; }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #E87F24 !important;
            box-shadow: 0 0 0 3px rgba(232,127,36,.12);
        }
        .section-card { background: white; border-radius: 20px; box-shadow: 0 2px 16px rgba(0,0,0,.06); }
        .section-title { font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #E87F24; border-bottom: 2px solid #FFF3E0; padding-bottom: .75rem; margin-bottom: 1.25rem; }
        .btn-primary { background: linear-gradient(135deg,#E87F24,#FFC81E); }
        .btn-primary:hover { opacity:.9; transform:translateY(-1px); }
        .form-input { @apply w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition; }
</style>
    <div>

        <!-- Header -->
        <div class="flex items-center gap-4 mb-8">
            <a href="../students/students.php"
               class="w-9 h-9 flex items-center justify-center rounded-xl bg-white shadow text-gray-500 hover:text-[#E87F24] transition">
                <i class="ri-arrow-left-line"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Edit Student</h1>
                <p class="text-sm text-gray-500">
                    <span class="font-mono text-[#E87F24] font-semibold"><?= sanitize($student['student_id']) ?></span>
                    · <?= sanitize($student['full_name']) ?>
                </p>
            </div>
            <a href="view_student.php?id=<?= $userId ?>"
               class="ml-auto inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border border-[#73A5CA] text-[#73A5CA] text-sm font-medium hover:bg-blue-50 transition">
                <i class="ri-eye-line"></i> View
            </a>
        </div>

        <?php renderFlashMessages(); ?>

        <?php if (!empty($errors)): ?>
        <div class="bg-red-50 border-l-4 border-red-400 rounded-xl p-4 mb-6 space-y-1">
            <p class="text-red-700 font-semibold text-sm flex items-center gap-2">
                <i class="ri-error-warning-line"></i> Please fix the following:
            </p>
            <?php foreach ($errors as $e): ?>
                <p class="text-red-600 text-sm pl-5">• <?= sanitize($e) ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-6">

                    <!-- Account -->
                    <div class="section-card p-6">
                        <p class="section-title"><i class="ri-shield-user-line mr-1"></i> Login Account</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Username <span class="text-red-500">*</span></label>
                                <input type="text" name="username"
                                       value="<?= sanitize($_POST['username'] ?? $student['username']) ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Email <span class="text-red-500">*</span></label>
                                <input type="email" name="email"
                                       value="<?= sanitize($_POST['email'] ?? $student['email']) ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">New Password</label>
                                <div class="relative">
                                    <input type="password" name="new_password" id="pwField"
                                           class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm pr-10 transition"
                                           placeholder="Leave blank to keep current password">
                                    <button type="button" onclick="togglePw()"
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-[#E87F24]">
                                        <i class="ri-eye-off-line" id="eyeIcon"></i>
                                    </button>
                                </div>
                                <p class="text-xs text-gray-400 mt-1">Leave blank to keep the existing password.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Personal -->
                    <div class="section-card p-6">
                        <p class="section-title"><i class="ri-user-line mr-1"></i> Personal Information</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Full Name <span class="text-red-500">*</span></label>
                                <input type="text" name="full_name"
                                       value="<?= sanitize($_POST['full_name'] ?? $student['full_name']) ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Phone <span class="text-red-500">*</span></label>
                                <input type="tel" name="phone" maxlength="10"
                                       value="<?= sanitize($_POST['phone'] ?? $student['phone']) ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Alternate Phone</label>
                                <input type="tel" name="alt_phone" maxlength="10"
                                       value="<?= sanitize($_POST['alt_phone'] ?? $student['alternate_phone']) ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Date of Birth</label>
                                <input type="date" name="dob"
                                       value="<?= sanitize($_POST['dob'] ?? $student['date_of_birth']) ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Gender <span class="text-red-500">*</span></label>
                                <select name="gender" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-white transition">
                                    <?php foreach ($genders as $g): ?>
                                        <option value="<?= $g ?>"
                                            <?= ($g === ($_POST['gender'] ?? $student['gender'])) ? 'selected' : '' ?>>
                                            <?= $g ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Family & Address -->
                    <div class="section-card p-6">
                        <p class="section-title"><i class="ri-home-line mr-1"></i> Family & Address</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Father's Name</label>
                                <input type="text" name="father_name"
                                       value="<?= sanitize($_POST['father_name'] ?? $student['father_name']) ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Father's Phone</label>
                                <input type="tel" name="father_phone" maxlength="10"
                                       value="<?= sanitize($_POST['father_phone'] ?? $student['father_phone']) ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Address</label>
                                <textarea name="address" rows="2"
                                          class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm resize-none transition"><?= sanitize($_POST['address'] ?? $student['address']) ?></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">City</label>
                                <input type="text" name="city"
                                       value="<?= sanitize($_POST['city'] ?? $student['city']) ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">State</label>
                                <input type="text" name="state"
                                       value="<?= sanitize($_POST['state'] ?? $student['state']) ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Pincode</label>
                                <input type="text" name="pincode" maxlength="6"
                                       value="<?= sanitize($_POST['pincode'] ?? $student['pincode']) ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition">
                            </div>
                        </div>
                    </div>

                </div>

                <!-- RIGHT -->
                <div class="space-y-6">
                    <div class="section-card p-6">
                        <p class="section-title"><i class="ri-book-open-line mr-1"></i> Enrollment</p>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Student ID</label>
                                <input type="text" value="<?= sanitize($student['student_id']) ?>" disabled
                                       class="w-full border border-gray-100 rounded-xl px-4 py-2.5 text-sm bg-gray-50 text-gray-500 font-mono">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Stream <span class="text-red-500">*</span></label>
                                <select name="stream" id="streamSelect"
                                        onchange="toggleCustomStream(this.value)"
                                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-white transition">
                                    <?php
                                    $currentStream = $_POST['stream'] ?? $student['stream'];
                                    foreach ($streams as $st): ?>
                                        <option value="<?= $st ?>" <?= $st === $currentStream ? 'selected' : '' ?>><?= $st ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div id="customStreamDiv" class="<?= $currentStream === 'Other' ? '' : 'hidden' ?>">
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Custom Stream</label>
                                <input type="text" name="custom_stream"
                                       value="<?= sanitize($_POST['custom_stream'] ?? $student['custom_stream']) ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Enrollment Date</label>
                                <input type="date" name="enrollment_date"
                                       value="<?= sanitize($_POST['enrollment_date'] ?? $student['enrollment_date']) ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm transition">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Status</label>
                                <select name="current_status" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-white transition">
                                    <?php
                                    $currentStatus = $_POST['current_status'] ?? $student['current_status'];
                                    foreach ($statuses as $val => $label): ?>
                                        <option value="<?= $val ?>" <?= $val === $currentStatus ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3">
                        <button type="submit"
                                class="btn-primary w-full py-3 rounded-xl text-white font-semibold shadow transition flex items-center justify-center gap-2">
                            <i class="ri-save-line"></i> Save Changes
                        </button>
                        <a href="../students/students.php"
                           class="w-full py-3 rounded-xl border border-gray-200 text-gray-600 text-sm font-medium text-center hover:bg-gray-50 transition">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>
        </form>
        </div><!-- /.page content -->
    </div><!-- /.content-area (sidebar) -->
</div><!-- /.page-wrapper (sidebar) -->

    <script>
        function togglePw() {
            const pw  = document.getElementById('pwField');
            const eye = document.getElementById('eyeIcon');
            if (pw.type === 'password') { pw.type = 'text';     eye.className = 'ri-eye-line'; }
            else                        { pw.type = 'password'; eye.className = 'ri-eye-off-line'; }
        }
        function toggleCustomStream(val) {
            document.getElementById('customStreamDiv').classList.toggle('hidden', val !== 'Other');
        }
    </script>
</body>
</html>
