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
$old    = [];  // repopulate form on error

// ── Generate unique student_id ─────────────────────────────────────────────
// function generateStudentId(PDO $db): string {
//     $year = date('Y');
//     do {
//         $rand      = strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
//         $candidate = "STU-{$year}-{$rand}";
//         $stmt      = $db->prepare("SELECT 1 FROM student_details WHERE student_id = ?");
//         $stmt->execute([$candidate]);
//     } while ($stmt->fetchColumn());
//     return $candidate;
// }

// ── POST handler ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token invalid. Please refresh and try again.';
    } else {
        // Collect & validate
        $old = $_POST;

        $username    = trim($_POST['username']   ?? '');
        $email       = trim($_POST['email']      ?? '');
        $password    = $_POST['password']        ?? '';
        $fullName    = trim($_POST['full_name']  ?? '');
        $phone       = trim($_POST['phone']      ?? '');
        $altPhone    = trim($_POST['alt_phone']  ?? '');
        $dob         = trim($_POST['dob']        ?? '');
        $gender      = $_POST['gender']          ?? '';
        $stream      = $_POST['stream']          ?? '';
        $customStream = trim($_POST['custom_stream'] ?? '');
        $fatherName  = trim($_POST['father_name'] ?? '');
        $fatherPhone = trim($_POST['father_phone'] ?? '');
        $address     = trim($_POST['address']    ?? '');
        $city        = trim($_POST['city']       ?? '');
        $state       = trim($_POST['state']      ?? '');
        $pincode     = trim($_POST['pincode']    ?? '');
        $enrollDate  = $_POST['enrollment_date'] ?? date('Y-m-d');
        $status      = $_POST['current_status']  ?? 'active';

        // Validation
        if (empty($username))                   $errors[] = 'Username is required.';
        if (strlen($username) < 3)              $errors[] = 'Username must be at least 3 characters.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
        if (strlen($password) < 8)              $errors[] = 'Password must be at least 8 characters.';
        if (empty($fullName))                   $errors[] = 'Full name is required.';
        if (!preg_match('/^[6-9]\d{9}$/', $phone)) $errors[] = 'Valid 10-digit Indian mobile number required.';
        if (!in_array($gender, ['Male','Female','Other'])) $errors[] = 'Please select a gender.';
        if (!in_array($stream, ["Let's Win","Super 30","Other"])) $errors[] = 'Please select a stream.';
        if (!in_array($status, ['active','dropped','on_hold','transferred','completed'])) $errors[] = 'Invalid status.';

        // Duplicate checks
        if (empty($errors)) {
            $chk = $db->prepare("SELECT id FROM users WHERE username = ?");
            $chk->execute([$username]);
            if ($chk->fetch()) $errors[] = "Username '{$username}' is already taken.";

            $chk2 = $db->prepare("SELECT id FROM users WHERE email = ?");
            $chk2->execute([$email]);
            if ($chk2->fetch()) $errors[] = "Email '{$email}' is already registered.";
        }

        // ── Insert (transaction) ───────────────────────────────────────────
        if (empty($errors)) {
            try {
                $db->beginTransaction();

                // 1. users row
                $stmtUser = $db->prepare("
                    INSERT INTO users (username, email, password_hash, role, status)
                    VALUES (?, ?, ?, 'student', 'active')
                ");
                $stmtUser->execute([
                    $username,
                    strtolower($email),
                    password_hash($password, PASSWORD_DEFAULT),
                ]);
                $userId = (int) $db->lastInsertId();

                // 2. student_details row
                $studentId = generateStudentId($db);
                $stmtDetail = $db->prepare("
                    INSERT INTO student_details
                        (user_id, student_id, full_name, phone, alternate_phone, email,
                         date_of_birth, gender, stream, custom_stream,
                         father_name, father_phone, address, city, state, pincode,
                         enrollment_date, current_status)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ");
                $stmtDetail->execute([
                    $userId, $studentId, $fullName, $phone,
                    $altPhone ?: null, strtolower($email),
                    $dob ?: null, $gender,
                    $stream, $stream === 'Other' ? ($customStream ?: null) : null,
                    $fatherName ?: null, $fatherPhone ?: null,
                    $address ?: null, $city ?: null, $state ?: null, $pincode ?: null,
                    $enrollDate, $status,
                ]);

                $db->commit();

                logActivity('ADD_STUDENT', 'students',
                    "Added student '{$fullName}' (ID: {$studentId}, email: {$email})");

                setFlash('success', "Student <strong>{$fullName}</strong> added successfully with ID <strong>{$studentId}</strong>.");
                redirect('/admin/students/students.php');

            } catch (Exception $e) {
                $db->rollBack();
                error_log('[GyanSetu] add_student error: ' . $e->getMessage());
                $errors[] = 'Database error. Please try again.';
            }
        }
    }
}

$csrfToken   = generateCSRFToken();
$todayDate   = date('Y-m-d');
$streams     = ["Let's Win", "Super 30", "Other"];
$statuses    = ['active' => 'Active', 'on_hold' => 'On Hold', 'dropped' => 'Dropped'];
$genders     = ['Male', 'Female', 'Other'];

$page_title = 'Add Student — GyanSetu';
include __DIR__ . '/../sidebar.php';
?>
<style>
.form-label { display:block; font-size:.875rem; font-weight:600; color:#374151; margin-bottom:.375rem; }
input, select, textarea { transition: border-color .2s, box-shadow .2s; }
input:focus, select:focus, textarea:focus { outline:none; border-color:#E87F24!important; box-shadow:0 0 0 3px rgba(232,127,36,.12); }
.section-card { background:#fff; border-radius:20px; box-shadow:0 2px 16px rgba(0,0,0,.06); }
.section-title { font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:#E87F24; border-bottom:2px solid #FFF3E0; padding-bottom:.75rem; margin-bottom:1.25rem; }
.btn-primary { background:linear-gradient(135deg,#E87F24,#FFC81E); }
.btn-primary:hover { opacity:.9; transform:translateY(-1px); }
</style>
    <div>

        <!-- Header -->
        <div class="flex items-center gap-4 mb-8">
            <a href="../students/students.php"
               class="w-9 h-9 flex items-center justify-center rounded-xl bg-white shadow text-gray-500 hover:text-[#E87F24] transition">
                <i class="ri-arrow-left-line"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Add New Student</h1>
                <p class="text-sm text-gray-500">Fill in the details below to enroll a new student</p>
            </div>
        </div>

        <!-- Errors -->
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

                <!-- LEFT: Main details -->
                <div class="lg:col-span-2 space-y-6">

                    <!-- Account -->
                    <div class="section-card p-6">
                        <p class="section-title"><i class="ri-shield-user-line mr-1"></i> Login Account</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="form-label">Username <span class="text-red-500">*</span></label>
                                <input type="text" name="username"
                                       value="<?= sanitize($old['username'] ?? '') ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm"
                                       placeholder="e.g. rahul_sharma">
                            </div>
                            <div>
                                <label class="form-label">Email <span class="text-red-500">*</span></label>
                                <input type="email" name="email"
                                       value="<?= sanitize($old['email'] ?? '') ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm"
                                       placeholder="student@example.com">
                            </div>
                            <div class="md:col-span-2">
                                <label class="form-label">Password <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <input type="password" name="password" id="password"
                                           class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm pr-10"
                                           placeholder="Min 8 characters">
                                    <button type="button" onclick="togglePw()"
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-[#E87F24]">
                                        <i class="ri-eye-off-line" id="eyeIcon"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Personal -->
                    <div class="section-card p-6">
                        <p class="section-title"><i class="ri-user-line mr-1"></i> Personal Information</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="md:col-span-2">
                                <label class="form-label">Full Name <span class="text-red-500">*</span></label>
                                <input type="text" name="full_name"
                                       value="<?= sanitize($old['full_name'] ?? '') ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm"
                                       placeholder="As per records">
                            </div>
                            <div>
                                <label class="form-label">Phone <span class="text-red-500">*</span></label>
                                <input type="tel" name="phone" maxlength="10"
                                       value="<?= sanitize($old['phone'] ?? '') ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm"
                                       placeholder="10-digit mobile number">
                            </div>
                            <div>
                                <label class="form-label">Alternate Phone</label>
                                <input type="tel" name="alt_phone" maxlength="10"
                                       value="<?= sanitize($old['alt_phone'] ?? '') ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm"
                                       placeholder="Optional">
                            </div>
                            <div>
                                <label class="form-label">Date of Birth</label>
                                <input type="date" name="dob"
                                       value="<?= sanitize($old['dob'] ?? '') ?>"
                                       max="<?= date('Y-m-d', strtotime('-5 years')) ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm">
                            </div>
                            <div>
                                <label class="form-label">Gender <span class="text-red-500">*</span></label>
                                <select name="gender" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-white">
                                    <option value="">Select gender</option>
                                    <?php foreach ($genders as $g): ?>
                                        <option value="<?= $g ?>" <?= ($old['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Family -->
                    <div class="section-card p-6">
                        <p class="section-title"><i class="ri-home-line mr-1"></i> Family & Address</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="form-label">Father's Name</label>
                                <input type="text" name="father_name"
                                       value="<?= sanitize($old['father_name'] ?? '') ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm">
                            </div>
                            <div>
                                <label class="form-label">Father's Phone</label>
                                <input type="tel" name="father_phone" maxlength="10"
                                       value="<?= sanitize($old['father_phone'] ?? '') ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm">
                            </div>
                            <div class="md:col-span-2">
                                <label class="form-label">Address</label>
                                <textarea name="address" rows="2"
                                          class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm resize-none"
                                          placeholder="Street / Colony / Locality"><?= sanitize($old['address'] ?? '') ?></textarea>
                            </div>
                            <div>
                                <label class="form-label">City</label>
                                <input type="text" name="city"
                                       value="<?= sanitize($old['city'] ?? '') ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm">
                            </div>
                            <div>
                                <label class="form-label">State</label>
                                <input type="text" name="state"
                                       value="<?= sanitize($old['state'] ?? '') ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm">
                            </div>
                            <div>
                                <label class="form-label">Pincode</label>
                                <input type="text" name="pincode" maxlength="6"
                                       value="<?= sanitize($old['pincode'] ?? '') ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm">
                            </div>
                        </div>
                    </div>

                </div>

                <!-- RIGHT: Enrollment -->
                <div class="space-y-6">
                    <div class="section-card p-6">
                        <p class="section-title"><i class="ri-book-open-line mr-1"></i> Enrollment</p>
                        <div class="space-y-4">
                            <div>
                                <label class="form-label">Stream <span class="text-red-500">*</span></label>
                                <select name="stream" id="streamSelect"
                                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-white"
                                        onchange="toggleCustomStream(this.value)">
                                    <option value="">Select stream</option>
                                    <?php foreach ($streams as $st): ?>
                                        <option value="<?= $st ?>" <?= ($old['stream'] ?? '') === $st ? 'selected' : '' ?>><?= $st ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div id="customStreamDiv" class="<?= ($old['stream'] ?? '') === 'Other' ? '' : 'hidden' ?>">
                                <label class="form-label">Custom Stream Name</label>
                                <input type="text" name="custom_stream"
                                       value="<?= sanitize($old['custom_stream'] ?? '') ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm"
                                       placeholder="Specify stream">
                            </div>
                            <div>
                                <label class="form-label">Enrollment Date <span class="text-red-500">*</span></label>
                                <input type="date" name="enrollment_date"
                                       value="<?= sanitize($old['enrollment_date'] ?? $todayDate) ?>"
                                       max="<?= $todayDate ?>"
                                       class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm">
                            </div>
                            <div>
                                <label class="form-label">Status</label>
                                <select name="current_status" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm bg-white">
                                    <?php foreach ($statuses as $val => $label): ?>
                                        <option value="<?= $val ?>" <?= ($old['current_status'] ?? 'active') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Info box -->
                    <div class="bg-blue-50 border border-blue-100 rounded-2xl p-4 text-sm text-blue-700">
                        <p class="font-semibold mb-1 flex items-center gap-1.5">
                            <i class="ri-information-line"></i> Auto-generated
                        </p>
                        <p class="text-xs text-blue-500">
                            A unique Student ID (e.g. STU-2025-A3F9C) will be automatically generated after saving.
                        </p>
                    </div>

                    <!-- Submit -->
                    <div class="flex flex-col gap-3">
                        <button type="submit"
                                class="btn-primary w-full py-3 rounded-xl text-white font-semibold shadow transition flex items-center justify-center gap-2">
                            <i class="ri-user-add-line"></i> Add Student
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
            const pw  = document.getElementById('password');
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
