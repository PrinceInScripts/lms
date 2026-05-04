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

if (!$userId) {
    setFlash('error', 'Invalid student ID.');
    redirect('/admin/students/students.php');
}

$stmt = $db->prepare("
    SELECT u.id AS user_id, u.username, u.email, u.status AS account_status,
           u.created_at AS account_created, u.last_login,
           sd.*
    FROM users u
    JOIN student_details sd ON sd.user_id = u.id
    WHERE u.id = ? AND u.role = 'student'
");
$stmt->execute([$userId]);
$s = $stmt->fetch();

if (!$s) {
    setFlash('error', 'Student not found.');
    redirect('/admin/students/students.php');
}

// Status config
$statusMap = [
    'active'      => ['class' => 'bg-green-100 text-green-700',  'dot' => 'bg-green-500',  'label' => 'Active'],
    'dropped'     => ['class' => 'bg-red-100 text-red-700',      'dot' => 'bg-red-500',    'label' => 'Dropped'],
    'on_hold'     => ['class' => 'bg-yellow-100 text-yellow-700','dot' => 'bg-yellow-500', 'label' => 'On Hold'],
    'transferred' => ['class' => 'bg-blue-100 text-blue-700',    'dot' => 'bg-blue-500',   'label' => 'Transferred'],
    'completed'   => ['class' => 'bg-purple-100 text-purple-700','dot' => 'bg-purple-500', 'label' => 'Completed'],
];
$st = $statusMap[$s['current_status']] ?? ['class' => 'bg-gray-100 text-gray-600', 'dot' => 'bg-gray-400', 'label' => ucfirst($s['current_status'])];

function infoRow(string $label, ?string $value, string $icon = 'ri-information-line'): string {
    $display = $value ? sanitize($value) : '<span class="text-gray-300 italic text-xs">Not provided</span>';
    return <<<HTML
    <div class="flex items-start gap-3 py-3 border-b border-gray-50 last:border-0">
        <i class="{$icon} text-[#73A5CA] mt-0.5 flex-shrink-0"></i>
        <div>
            <p class="text-xs text-gray-400 font-medium">{$label}</p>
            <p class="text-sm text-gray-700 font-semibold mt-0.5">{$display}</p>
        </div>
    </div>
    HTML;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($s['full_name']) ?> — GyanSetu</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: #FEFDDF; }
        .card { background: white; border-radius: 20px; box-shadow: 0 2px 16px rgba(0,0,0,.06); }
        .section-title { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: #E87F24; margin-bottom: 1rem; }
    </style>
</head>
<body class="flex">
    <?php include __DIR__ . '/../sidebar.php'; ?>

    <main class="flex-1 p-6 md:p-8 ml-0 md:ml-64 min-h-screen">

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div class="flex items-center gap-4">
                <a href="../students/students.php"
                   class="w-9 h-9 flex items-center justify-center rounded-xl bg-white shadow text-gray-500 hover:text-[#E87F24] transition">
                    <i class="ri-arrow-left-line"></i>
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Student Profile</h1>
                    <p class="text-sm text-gray-400">Detailed view of enrolled student</p>
                </div>
            </div>
            <div class="flex gap-3">
                <a href="edit_student.php?id=<?= $userId ?>"
                   class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#E87F24] text-white text-sm font-semibold shadow hover:opacity-90 transition">
                    <i class="ri-edit-line"></i> Edit
                </a>
                <button onclick="confirmDelete()"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-red-200 text-red-500 text-sm font-semibold hover:bg-red-50 transition">
                    <i class="ri-delete-bin-line"></i> Delete
                </button>
            </div>
        </div>

        <?php renderFlashMessages(); ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Profile hero card -->
            <div class="lg:col-span-1">
                <div class="card p-6 text-center">
                    <!-- Avatar -->
                    <div class="w-20 h-20 rounded-full bg-gradient-to-br from-[#E87F24] to-[#FFC81E] flex items-center justify-center mx-auto mb-4 shadow-lg">
                        <span class="text-white text-3xl font-bold">
                            <?= strtoupper(mb_substr($s['full_name'], 0, 1)) ?>
                        </span>
                    </div>

                    <h2 class="text-xl font-bold text-gray-800"><?= sanitize($s['full_name']) ?></h2>
                    <p class="text-sm text-gray-400 mt-1"><?= sanitize($s['email']) ?></p>

                    <!-- Student ID badge -->
                    <div class="inline-flex items-center gap-2 bg-orange-50 px-3 py-1.5 rounded-lg mt-3">
                        <i class="ri-id-card-line text-[#E87F24] text-sm"></i>
                        <span class="font-mono text-sm font-bold text-[#E87F24]"><?= sanitize($s['student_id']) ?></span>
                    </div>

                    <!-- Status -->
                    <div class="mt-4">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold <?= $st['class'] ?>">
                            <span class="w-1.5 h-1.5 rounded-full <?= $st['dot'] ?>"></span>
                            <?= $st['label'] ?>
                        </span>
                    </div>

                    <!-- Quick stats -->
                    <div class="grid grid-cols-2 gap-3 mt-6">
                        <div class="bg-orange-50 rounded-xl p-3">
                            <p class="text-xs text-gray-400">Stream</p>
                            <p class="text-sm font-bold text-gray-700 mt-0.5">
                                <?= $s['stream'] === 'Other' && $s['custom_stream']
                                    ? sanitize($s['custom_stream'])
                                    : sanitize($s['stream']) ?>
                            </p>
                        </div>
                        <div class="bg-blue-50 rounded-xl p-3">
                            <p class="text-xs text-gray-400">Enrolled</p>
                            <p class="text-sm font-bold text-gray-700 mt-0.5">
                                <?= $s['enrollment_date'] ? date('d M Y', strtotime($s['enrollment_date'])) : '—' ?>
                            </p>
                        </div>
                        <div class="bg-yellow-50 rounded-xl p-3">
                            <p class="text-xs text-gray-400">Gender</p>
                            <p class="text-sm font-bold text-gray-700 mt-0.5"><?= sanitize($s['gender'] ?? '—') ?></p>
                        </div>
                        <div class="bg-green-50 rounded-xl p-3">
                            <p class="text-xs text-gray-400">Account</p>
                            <p class="text-sm font-bold text-gray-700 mt-0.5"><?= sanitize($s['account_status']) ?></p>
                        </div>
                    </div>

                    <!-- Last login -->
                    <p class="text-xs text-gray-400 mt-4">
                        <i class="ri-time-line mr-1"></i>
                        Last login:
                        <?= $s['last_login'] ? date('d M Y, h:i A', strtotime($s['last_login'])) : 'Never' ?>
                    </p>
                </div>
            </div>

            <!-- Detail panels -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Contact -->
                <div class="card p-6">
                    <p class="section-title"><i class="ri-phone-line mr-1"></i> Contact Information</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6">
                        <?= infoRow('Phone', $s['phone'], 'ri-phone-line') ?>
                        <?= infoRow('Alternate Phone', $s['alternate_phone'], 'ri-phone-line') ?>
                        <?= infoRow('Email', $s['email'], 'ri-mail-line') ?>
                        <?= infoRow('Date of Birth',
                            $s['date_of_birth'] ? date('d M Y', strtotime($s['date_of_birth'])) : null,
                            'ri-cake-line') ?>
                    </div>
                </div>

                <!-- Family -->
                <div class="card p-6">
                    <p class="section-title"><i class="ri-parent-line mr-1"></i> Family Details</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6">
                        <?= infoRow("Father's Name", $s['father_name'], 'ri-user-line') ?>
                        <?= infoRow("Father's Phone", $s['father_phone'], 'ri-phone-line') ?>
                        <?= infoRow("Mother's Name", $s['mother_name'], 'ri-user-line') ?>
                    </div>
                </div>

                <!-- Address -->
                <div class="card p-6">
                    <p class="section-title"><i class="ri-map-pin-line mr-1"></i> Address</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6">
                        <?= infoRow('Street / Locality', $s['address'], 'ri-road-map-line') ?>
                        <?= infoRow('City', $s['city'], 'ri-building-line') ?>
                        <?= infoRow('State', $s['state'], 'ri-map-2-line') ?>
                        <?= infoRow('Pincode', $s['pincode'], 'ri-map-pin-line') ?>
                    </div>
                </div>

                <!-- Account metadata -->
                <div class="card p-6">
                    <p class="section-title"><i class="ri-settings-line mr-1"></i> Account Metadata</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6">
                        <?= infoRow('Username', $s['username'], 'ri-user-3-line') ?>
                        <?= infoRow('Account Created',
                            $s['account_created'] ? date('d M Y, h:i A', strtotime($s['account_created'])) : null,
                            'ri-calendar-check-line') ?>
                        <?= infoRow('Account Status', ucfirst($s['account_status']), 'ri-shield-check-line') ?>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- Delete Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6">
            <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="ri-delete-bin-line text-2xl text-red-500"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-800 text-center mb-1">Delete Student?</h3>
            <p class="text-sm text-gray-500 text-center mb-6">
                This will permanently remove <strong><?= sanitize($s['full_name']) ?></strong> and all related data.
            </p>
            <div class="flex gap-3">
                <button onclick="document.getElementById('deleteModal').classList.add('hidden')"
                        class="flex-1 px-4 py-2.5 border border-gray-200 rounded-xl text-gray-600 text-sm font-medium hover:bg-gray-50 transition">
                    Cancel
                </button>
                <a href="delete_student.php?id=<?= $userId ?>"
                   class="flex-1 px-4 py-2.5 bg-red-500 hover:bg-red-600 rounded-xl text-white text-sm font-semibold text-center transition">
                    Delete
                </a>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete() {
            document.getElementById('deleteModal').classList.remove('hidden');
        }
    </script>
</body>
</html>
