<?php
define('GYANSETU_APP', true);
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/db_conn.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/activity_logger.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db      = getDB();
$batchId = trim($_GET['id'] ?? '');

if (empty($batchId)) { setFlash('error','Invalid batch.'); redirect(BASE_URL.'/admin/batch/batches.php'); }

$stmt = $db->prepare("SELECT * FROM batches WHERE batch_id = ?");
$stmt->execute([$batchId]);
$batch = $stmt->fetch();
if (!$batch) { setFlash('error','Batch not found.'); redirect(BASE_URL.'/admin/batch/batches.php'); }

// Enrolled students
$enrollStmt = $db->prepare("
    SELECT sb.id, sb.student_id, sb.status AS enroll_status, sb.enrolled_at,
           sd.full_name, sd.phone, sd.stream
    FROM student_batches sb
    JOIN student_details sd ON sd.student_id = sb.student_id
    WHERE sb.batch_id = ?
    ORDER BY sb.enrolled_at DESC
");
$enrollStmt->execute([$batchId]);
$enrolled = $enrollStmt->fetchAll();

// Stats
$activeCount    = count(array_filter($enrolled, fn($r) => $r['enroll_status'] === 'active'));
$droppedCount   = count(array_filter($enrolled, fn($r) => $r['enroll_status'] === 'dropped'));
$completedCount = count(array_filter($enrolled, fn($r) => $r['enroll_status'] === 'completed'));
$maxStudents    = (int) $batch['max_students'];

// Status helpers
$batchStatusMap = [
    'upcoming'  => 'bg-yellow-100 text-yellow-700',
    'ongoing'   => 'bg-green-100 text-green-700',
    'completed' => 'bg-blue-100 text-blue-700',
    'cancelled' => 'bg-red-100 text-red-700',
];
$enrollStatusMap = [
    'active'    => ['bg-green-100 text-green-700',  'bg-green-500'],
    'dropped'   => ['bg-red-100 text-red-700',      'bg-red-500'],
    'completed' => ['bg-blue-100 text-blue-700',    'bg-blue-500'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($batch['batch_name']) ?> — GyanSetu</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family:'Inter',sans-serif; } body { background:#FEFDDF; }
        .card { background:white; border-radius:20px; box-shadow:0 2px 16px rgba(0,0,0,.06); }
        .section-title { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:#E87F24; margin-bottom:1rem; }
        .stat-box { background:white; border-radius:16px; padding:1rem 1.25rem; box-shadow:0 2px 12px rgba(0,0,0,.06); }
        tr:hover td { background:#fff8f0; }
        .action-btn { transition:all .2s; } .action-btn:hover { transform:scale(1.15); }
    </style>
</head>
<body class="flex">
    <?php include __DIR__ . '/../sidebar.php'; ?>

    <!-- <main class="flex-1 p-6 md:p-8 ml-0 md:ml-64 min-h-screen"> -->

        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
            <div class="flex items-center gap-4">
                <a href="batches.php" class="w-9 h-9 flex items-center justify-center rounded-xl bg-white shadow text-gray-500 hover:text-[#E87F24] transition"><i class="ri-arrow-left-line"></i></a>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800"><?= sanitize($batch['batch_name']) ?></h1>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="font-mono text-xs font-bold text-[#E87F24] bg-orange-50 px-2 py-0.5 rounded-lg"><?= sanitize($batch['batch_id']) ?></span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $batchStatusMap[$batch['status']] ?? 'bg-gray-100 text-gray-600' ?>">
                            <?= ucfirst($batch['status']) ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex gap-3">
                <a href="assign_student.php?batch_id=<?= urlencode($batchId) ?>"
                   class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-purple-500 hover:bg-purple-600 text-white text-sm font-semibold shadow transition">
                    <i class="ri-user-add-line"></i> Enroll Student
                </a>
                <a href="edit_batch.php?id=<?= urlencode($batchId) ?>"
                   class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-[#E87F24] hover:opacity-90 text-white text-sm font-semibold shadow transition">
                    <i class="ri-edit-line"></i> Edit
                </a>
            </div>
        </div>

        <?php renderFlashMessages(); ?>

        <!-- Stat row -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="stat-box">
                <p class="text-xs text-gray-400 font-medium">Active Students</p>
                <p class="text-2xl font-bold text-green-600 mt-1"><?= $activeCount ?></p>
            </div>
            <div class="stat-box">
                <p class="text-xs text-gray-400 font-medium">Capacity</p>
                <p class="text-2xl font-bold text-gray-700 mt-1"><?= $maxStudents > 0 ? $maxStudents : '∞' ?></p>
            </div>
            <div class="stat-box">
                <p class="text-xs text-gray-400 font-medium">Dropped</p>
                <p class="text-2xl font-bold text-red-500 mt-1"><?= $droppedCount ?></p>
            </div>
            <div class="stat-box">
                <p class="text-xs text-gray-400 font-medium">Completed</p>
                <p class="text-2xl font-bold text-blue-500 mt-1"><?= $completedCount ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Batch info -->
            <div class="card p-6">
                <p class="section-title"><i class="ri-information-line mr-1"></i> Batch Details</p>
                <div class="space-y-3 text-sm">
                    <?php
                    $rows = [
                        ['ri-calendar-line',    'Start Date',  $batch['start_date'] ? date('d M Y', strtotime($batch['start_date'])) : '—'],
                        ['ri-calendar-check-line','End Date',  $batch['end_date']   ? date('d M Y', strtotime($batch['end_date'])) : '—'],
                        ['ri-time-line',        'Time Slot',   $batch['time_slot']  ?? '—'],
                        ['ri-computer-line',    'Platform',    $batch['platform']   ?? '—'],
                        ['ri-wifi-line',        'Mode',        ucfirst($batch['mode'] ?? 'online')],
                        ['ri-book-2-line',      'Academic Year',$batch['academic_year'] ?? '—'],
                    ];
                    foreach ($rows as [$icon,$label,$val]):
                    ?>
                    <div class="flex items-start gap-3">
                        <i class="<?=$icon?> text-[#73A5CA] mt-0.5 flex-shrink-0"></i>
                        <div>
                            <p class="text-xs text-gray-400"><?=$label?></p>
                            <p class="font-semibold text-gray-700"><?= sanitize($val) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php if ($batch['meeting_link']): ?>
                    <div class="flex items-start gap-3">
                        <i class="ri-links-line text-[#73A5CA] mt-0.5 flex-shrink-0"></i>
                        <div>
                            <p class="text-xs text-gray-400">Meeting Link</p>
                            <a href="<?= sanitize($batch['meeting_link']) ?>" target="_blank" rel="noopener noreferrer"
                               class="text-[#73A5CA] hover:underline text-xs break-all">
                                <?= truncate(sanitize($batch['meeting_link']), 40) ?>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Fill bar -->
                <?php if ($maxStudents > 0): ?>
                <?php $pct = min(100, round($activeCount / $maxStudents * 100)); $col = $pct>=90 ? 'bg-red-400':($pct>=70?'bg-yellow-400':'bg-green-400'); ?>
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <div class="flex justify-between text-xs text-gray-500 mb-1">
                        <span>Seat Usage</span>
                        <span class="font-semibold"><?=$activeCount?> / <?=$maxStudents?> (<?=$pct?>%)</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2">
                        <div class="<?=$col?> h-2 rounded-full" style="width:<?=$pct?>%"></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Enrolled students table -->
            <div class="lg:col-span-2">
                <div class="card overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="font-bold text-gray-800 text-sm">Enrolled Students</h3>
                        <span class="text-xs text-gray-400"><?= count($enrolled) ?> total</span>
                    </div>

                    <?php if (empty($enrolled)): ?>
                        <div class="flex flex-col items-center justify-center py-14 text-gray-400">
                            <i class="ri-user-search-line text-4xl mb-2 text-[#E87F24] opacity-40"></i>
                            <p class="text-sm">No students enrolled yet</p>
                            <a href="assign_student.php?batch_id=<?= urlencode($batchId) ?>"
                               class="mt-3 text-xs text-[#E87F24] hover:underline font-medium">+ Enroll first student</a>
                        </div>
                    <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                                    <th class="px-4 py-3 text-left font-semibold">Student</th>
                                    <th class="px-4 py-3 text-left font-semibold">ID</th>
                                    <th class="px-4 py-3 text-left font-semibold">Status</th>
                                    <th class="px-4 py-3 text-left font-semibold">Enrolled On</th>
                                    <th class="px-4 py-3 text-center font-semibold">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <?php foreach ($enrolled as $row): ?>
                                <?php [$esBg,$esDot] = $enrollStatusMap[$row['enroll_status']] ?? ['bg-gray-100 text-gray-600','bg-gray-400']; ?>
                                <tr class="transition-colors duration-150">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-7 h-7 rounded-full bg-gradient-to-br from-[#E87F24] to-[#FFC81E] flex items-center justify-center text-white font-bold text-xs flex-shrink-0">
                                                <?= strtoupper(mb_substr($row['full_name'],0,1)) ?>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-gray-800 text-xs"><?= sanitize($row['full_name']) ?></p>
                                                <p class="text-gray-400 text-xs"><?= sanitize($row['stream']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="font-mono text-xs text-[#E87F24] bg-orange-50 px-1.5 py-0.5 rounded">
                                            <?= sanitize($row['student_id']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold <?= $esBg ?>">
                                            <span class="w-1.5 h-1.5 rounded-full <?= $esDot ?>"></span>
                                            <?= ucfirst($row['enroll_status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-500 text-xs">
                                        <?= $row['enrolled_at'] ? date('d M Y', strtotime($row['enrolled_at'])) : '—' ?>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <?php if ($row['enroll_status'] === 'active'): ?>
                                        <button onclick="confirmRemove('<?= sanitize($row['student_id']) ?>','<?= sanitize($row['full_name']) ?>')"
                                                class="action-btn text-red-400 hover:text-red-600" title="Remove from batch">
                                            <i class="ri-user-unfollow-line text-base"></i>
                                        </button>
                                        <?php else: ?>
                                        <span class="text-gray-300 text-xs">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <!-- </main> -->

    <!-- Remove Student Modal -->
    <div id="removeModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full p-6">
            <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="ri-user-unfollow-line text-2xl text-red-500"></i>
            </div>
            <h3 class="text-lg font-bold text-gray-800 text-center mb-1">Remove from Batch?</h3>
            <p class="text-sm text-gray-500 text-center mb-6">
                Remove <strong id="removeStudentName"></strong> from this batch?
                Their status will be marked as <em>dropped</em>.
            </p>
            <div class="flex gap-3">
                <button onclick="closeRemoveModal()" class="flex-1 px-4 py-2.5 border border-gray-200 rounded-xl text-gray-600 text-sm font-medium hover:bg-gray-50 transition">Cancel</button>
                <a id="removeLink" href="#" class="flex-1 px-4 py-2.5 bg-red-500 hover:bg-red-600 rounded-xl text-white text-sm font-semibold text-center transition">Remove</a>
            </div>
        </div>
    </div>

    <script>
        function confirmRemove(studentId, name) {
            document.getElementById('removeStudentName').textContent = name;
            document.getElementById('removeLink').href =
                'remove_student.php?student_id=' + encodeURIComponent(studentId) +
                '&batch_id=<?= urlencode($batchId) ?>';
            document.getElementById('removeModal').classList.remove('hidden');
        }
        function closeRemoveModal() { document.getElementById('removeModal').classList.add('hidden'); }
        document.getElementById('removeModal').addEventListener('click', e => {
            if (e.target === document.getElementById('removeModal')) closeRemoveModal();
        });
    </script>
</body>
</html>
