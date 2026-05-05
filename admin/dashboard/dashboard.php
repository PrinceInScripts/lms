<?php
/**
 * dashboard.php — GyanSetu LMS
 * Day 9+10: Optimised stats (single query), real activity log,
 * upcoming schedule, attendance progress bar, clean UI, no debug code.
 */
define('GYANSETU_APP', true);
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_conn.php';
require_once __DIR__ . '/../../includes/activity_logger.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db = getDB();
$B  = rtrim(BASE_URL, '/');

// ── 1. Aggregated stats — single efficient query ───────────────────────────
$stats = [
    'total_students'   => 0,
    'active_batches'   => 0,
    'total_courses'    => 0,
    'today_attendance' => 0,
];

try {
    $row = $db->query("
        SELECT
            (SELECT COUNT(*) FROM student_details  WHERE current_status = 'active')          AS total_students,
            (SELECT COUNT(*) FROM batches          WHERE status IN ('upcoming','ongoing'))    AS active_batches,
            (SELECT COUNT(*) FROM courses          WHERE status = 'active')                  AS total_courses,
            (SELECT COUNT(DISTINCT student_id) FROM attendance WHERE attendance_date = CURDATE()) AS today_attendance
    ")->fetch();

    if ($row) {
        $stats['total_students']   = (int) $row['total_students'];
        $stats['active_batches']   = (int) $row['active_batches'];
        $stats['total_courses']    = (int) $row['total_courses'];
        $stats['today_attendance'] = (int) $row['today_attendance'];
    }
} catch (Exception $e) {
    error_log('[GyanSetu] Dashboard stats error: ' . $e->getMessage());
}

// Attendance percentage
$attendance_pct = $stats['total_students'] > 0
    ? min(100, round(($stats['today_attendance'] / $stats['total_students']) * 100))
    : 0;

// ── 2. Recent activities (last 5) ─────────────────────────────────────────
$recent_activities = [];
try {
    $stmt = $db->query("
        SELECT username, action, module, description, created_at
        FROM activity_logs
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $recent_activities = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('[GyanSetu] Dashboard activities error: ' . $e->getMessage());
}

// ── 3. Upcoming schedules (next 5 from today) ────────────────────────────
$upcoming_schedules = [];
try {
    $stmt = $db->query("
        SELECT s.id, s.topic, s.schedule_date, s.start_time, s.batch_id,
               b.batch_name
        FROM schedule s
        JOIN batches b ON b.batch_id = s.batch_id
        WHERE s.schedule_date >= CURDATE()
          AND s.is_cancelled = 0
        ORDER BY s.schedule_date ASC, s.start_time ASC
        LIMIT 5
    ");
    $upcoming_schedules = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('[GyanSetu] Dashboard schedules error: ' . $e->getMessage());
}

// ── 4. Recent students (last 5) ───────────────────────────────────────────
$recent_students = [];
try {
    $stmt = $db->query("
        SELECT student_id, full_name, stream, enrollment_date, phone
        FROM student_details
        ORDER BY id DESC
        LIMIT 5
    ");
    $recent_students = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('[GyanSetu] Dashboard students error: ' . $e->getMessage());
}

// ── 5. Action icon helper ─────────────────────────────────────────────────
function activityIcon(string $action, string $module = ''): string {
    $action = strtoupper($action);
    if (str_contains($action, 'LOGIN'))      return 'ri-login-box-line text-green-500';
    if (str_contains($action, 'LOGOUT'))     return 'ri-logout-box-line text-gray-400';
    if (str_contains($action, 'CREATE') || str_contains($action, 'ADD'))
                                              return 'ri-add-circle-line text-blue-500';
    if (str_contains($action, 'UPDATE') || str_contains($action, 'EDIT'))
                                              return 'ri-edit-line text-yellow-500';
    if (str_contains($action, 'DELETE'))     return 'ri-delete-bin-line text-red-500';
    if (str_contains($action, 'ATTEND'))     return 'ri-checkbox-circle-line text-teal-500';
    return 'ri-information-line text-gray-400';
}

$page_title = 'Dashboard — GyanSetu';
$extra_head = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>';
include '../sidebar.php';
?>
<style>
/* ── Dashboard-specific styles ── */
.stat-card{transition:transform .3s ease,box-shadow .3s ease;position:relative;overflow:hidden}
.stat-card::after{content:'';position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(255,255,255,.25),transparent);transform:translateX(-100%);transition:transform .5s ease}
.stat-card:hover::after{transform:translateX(100%)}
.stat-card:hover{transform:translateY(-4px);box-shadow:0 16px 40px -12px rgba(0,0,0,.18)}
.stat-icon{transition:transform .3s ease}
.stat-card:hover .stat-icon{transform:scale(1.12) rotate(6deg)}
.dash-card{transition:transform .25s ease,box-shadow .25s ease,border-color .25s ease;border:1px solid transparent}
.dash-card:hover{transform:translateY(-2px);box-shadow:0 8px 32px -8px rgba(0,0,0,.12);border-color:rgba(232,127,36,.2)}
.activity-row{transition:background .2s ease,border-left-color .2s ease,transform .2s ease;border-left:3px solid transparent}
.activity-row:hover{background:#FFF8F0;border-left-color:#E87F24;transform:translateX(4px)}
.progress-bar{transition:width 1.2s cubic-bezier(.4,0,.2,1)}
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:#f1f1f1;border-radius:8px}
::-webkit-scrollbar-thumb{background:linear-gradient(135deg,#E87F24,#FFC81E);border-radius:8px}
.gradient-text{background:linear-gradient(135deg,#E87F24,#FFC81E);-webkit-background-clip:text;background-clip:text;color:transparent}
@keyframes fadeUp{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:translateY(0)}}
.fade-up{animation:fadeUp .55s ease-out both}
.fade-up-1{animation-delay:.05s}.fade-up-2{animation-delay:.12s}.fade-up-3{animation-delay:.19s}.fade-up-4{animation-delay:.26s}
</style>

<div class="fade-up">
    <!-- ── Welcome Banner ── -->
    <?php renderFlashMessages(); ?>

    <div class="mb-8 rounded-2xl bg-gradient-to-r from-[#E87F24]/10 to-[#FFC81E]/10 p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-[#E87F24] to-[#FFC81E]">
                    <i class="ri-dashboard-line text-2xl text-white"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">
                        Welcome, <span class="gradient-text"><?= sanitize($_SESSION['username'] ?? 'Admin') ?></span>!
                    </h1>
                    <p class="text-sm text-gray-500"><?= date('l, d F Y') ?> &mdash; Here's your academy overview.</p>
                </div>
            </div>
            <div class="flex gap-3">
                <a href="<?= $B ?>/admin/schedule/add_schedule.php"
                   class="flex items-center gap-2 rounded-xl bg-gradient-to-r from-[#E87F24] to-[#FFC81E] px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:shadow-md transition-all hover:scale-105">
                    <i class="ri-add-line"></i> Add Schedule
                </a>
                <a href="<?= $B ?>/admin/student/add_student.php"
                   class="flex items-center gap-2 rounded-xl border-2 border-[#E87F24] px-5 py-2.5 text-sm font-semibold text-[#E87F24] hover:bg-[#E87F24] hover:text-white transition-all">
                    <i class="ri-user-add-line"></i> Add Student
                </a>
            </div>
        </div>
    </div>

    <!-- ── Stat Cards ── -->
    <div class="mb-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">

        <!-- Total Students -->
        <a href="<?= $B ?>/admin/students/students.php"
           class="stat-card fade-up fade-up-1 block cursor-pointer rounded-2xl bg-white p-6 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <div class="stat-icon flex h-14 w-14 items-center justify-center rounded-xl bg-gradient-to-br from-orange-100 to-orange-50">
                    <i class="ri-user-star-line text-2xl text-[#E87F24]"></i>
                </div>
                <span class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700">Active</span>
            </div>
            <p class="text-sm text-gray-500">Total Students</p>
            <p class="mt-1 text-3xl font-extrabold text-gray-800 stat-num"><?= number_format($stats['total_students']) ?></p>
            <p class="mt-2 text-xs text-gray-400">Currently enrolled &amp; active</p>
        </a>

        <!-- Active Batches -->
        <a href="<?= $B ?>/admin/batch/batches.php"
           class="stat-card fade-up fade-up-2 block cursor-pointer rounded-2xl bg-white p-6 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <div class="stat-icon flex h-14 w-14 items-center justify-center rounded-xl bg-gradient-to-br from-yellow-100 to-yellow-50">
                    <i class="ri-group-line text-2xl text-[#FFC81E]"></i>
                </div>
                <span class="rounded-full bg-yellow-100 px-2.5 py-1 text-xs font-semibold text-yellow-700">Ongoing</span>
            </div>
            <p class="text-sm text-gray-500">Active Batches</p>
            <p class="mt-1 text-3xl font-extrabold text-gray-800 stat-num"><?= number_format($stats['active_batches']) ?></p>
            <p class="mt-2 text-xs text-gray-400">Upcoming + ongoing programs</p>
        </a>

        <!-- Total Courses -->
        <a href="<?= $B ?>/admin/course/courses.php"
           class="stat-card fade-up fade-up-3 block cursor-pointer rounded-2xl bg-white p-6 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <div class="stat-icon flex h-14 w-14 items-center justify-center rounded-xl bg-gradient-to-br from-purple-100 to-purple-50">
                    <i class="ri-book-open-line text-2xl text-purple-500"></i>
                </div>
                <span class="rounded-full bg-purple-100 px-2.5 py-1 text-xs font-semibold text-purple-700">Active</span>
            </div>
            <p class="text-sm text-gray-500">Total Courses</p>
            <p class="mt-1 text-3xl font-extrabold text-gray-800 stat-num"><?= number_format($stats['total_courses']) ?></p>
            <p class="mt-2 text-xs text-gray-400">Live curriculum offerings</p>
        </a>

        <!-- Today's Attendance -->
        <a href="<?= $B ?>/admin/attendance/attendance.php"
           class="stat-card fade-up fade-up-4 block cursor-pointer rounded-2xl bg-white p-6 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <div class="stat-icon flex h-14 w-14 items-center justify-center rounded-xl bg-gradient-to-br from-green-100 to-green-50">
                    <i class="ri-checkbox-circle-line text-2xl text-green-500"></i>
                </div>
                <span class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700"><?= $attendance_pct ?>%</span>
            </div>
            <p class="text-sm text-gray-500">Today's Attendance</p>
            <p class="mt-1 text-3xl font-extrabold text-gray-800 stat-num"><?= number_format($stats['today_attendance']) ?></p>
            <!-- Attendance Progress Bar -->
            <div class="mt-3">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs text-gray-400">Present today</span>
                    <span class="text-xs font-semibold text-green-600"><?= $attendance_pct ?>%</span>
                </div>
                <div class="h-2 w-full rounded-full bg-gray-100">
                    <div class="progress-bar h-2 rounded-full bg-gradient-to-r from-green-500 to-green-400"
                         style="width: 0%" data-target="<?= $attendance_pct ?>"></div>
                </div>
            </div>
        </a>
    </div>

    <!-- ── Bottom 3-Col Grid ── -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <!-- Recent Students -->
        <div class="dash-card rounded-2xl bg-white p-6 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="flex items-center gap-2 text-base font-semibold text-gray-800">
                    <i class="ri-user-star-line text-[#E87F24]"></i> Recent Students
                </h3>
                <a href="<?= $B ?>/admin/students/students.php"
                   class="flex items-center gap-1 text-sm text-[#E87F24] hover:underline">
                    View all <i class="ri-arrow-right-line"></i>
                </a>
            </div>

            <div class="max-h-96 space-y-2 overflow-y-auto pr-1">
                <?php if (empty($recent_students)): ?>
                    <div class="py-10 text-center text-gray-400">
                        <i class="ri-user-line mb-2 block text-4xl"></i>
                        <p class="text-sm">No students yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_students as $s): ?>
                        <a href="<?= $B ?>/admin/student/view_student.php?id=<?= urlencode($s['student_id']) ?>"
                           class="flex items-center gap-3 rounded-xl p-3 hover:bg-orange-50/60 transition">
                            <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[#E87F24] to-[#FFC81E] text-sm font-bold text-white">
                                <?= strtoupper(substr($s['full_name'], 0, 2)) ?>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-gray-800"><?= sanitize($s['full_name']) ?></p>
                                <p class="text-xs text-gray-400"><?= sanitize($s['student_id']) ?></p>
                            </div>
                            <div class="flex-shrink-0 text-right">
                                <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700"><?= sanitize($s['stream']) ?></span>
                                <p class="mt-1 text-xs text-gray-400"><?= date('d M', strtotime($s['enrollment_date'])) ?></p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upcoming Schedule -->
        <div class="dash-card rounded-2xl bg-white p-6 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="flex items-center gap-2 text-base font-semibold text-gray-800">
                    <i class="ri-calendar-line text-[#FFC81E]"></i> Upcoming Schedule
                </h3>
                <a href="<?= $B ?>/admin/schedule/schedule.php"
                   class="flex items-center gap-1 text-sm text-[#E87F24] hover:underline">
                    View all <i class="ri-arrow-right-line"></i>
                </a>
            </div>

            <?php
            $today    = date('Y-m-d');
            $tomorrow = date('Y-m-d', strtotime('+1 day'));
            ?>
            <div class="max-h-96 space-y-1 overflow-y-auto pr-1">
                <?php if (empty($upcoming_schedules)): ?>
                    <div class="py-10 text-center text-gray-400">
                        <i class="ri-calendar-check-line mb-2 block text-4xl opacity-40 text-[#E87F24]"></i>
                        <p class="text-sm">No upcoming sessions.</p>
                        <a href="<?= $B ?>/admin/schedule/add_schedule.php"
                           class="mt-2 inline-block text-sm font-semibold text-[#E87F24] hover:underline">
                            + Add Schedule
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($upcoming_schedules as $sch):
                        $isToday    = $sch['schedule_date'] === $today;
                        $isTomorrow = $sch['schedule_date'] === $tomorrow;
                        $label      = $isToday ? 'Today' : ($isTomorrow ? 'Tomorrow' : date('d M', strtotime($sch['schedule_date'])));
                        $labelCls   = $isToday ? 'bg-green-100 text-green-700' : ($isTomorrow ? 'bg-yellow-100 text-yellow-700' : 'bg-blue-50 text-blue-600');
                        $dotCls     = $isToday ? 'bg-green-500' : ($isTomorrow ? 'bg-yellow-400' : 'bg-blue-400');
                    ?>
                    <div class="group flex items-start gap-3 rounded-xl p-3 hover:bg-orange-50/50 transition-colors">
                        <!-- Day block -->
                        <div class="w-10 flex-shrink-0 text-center">
                            <p class="text-lg font-extrabold leading-none text-gray-800"><?= date('d', strtotime($sch['schedule_date'])) ?></p>
                            <p class="text-xs font-semibold uppercase text-gray-400"><?= date('M', strtotime($sch['schedule_date'])) ?></p>
                        </div>
                        <!-- Timeline dot -->
                        <div class="flex flex-shrink-0 flex-col items-center pt-1.5">
                            <span class="h-2 w-2 rounded-full <?= $dotCls ?>"></span>
                            <span class="mt-1 w-px flex-1 bg-gray-100" style="min-height:20px"></span>
                        </div>
                        <!-- Info -->
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <p class="truncate text-sm font-semibold text-gray-800"><?= sanitize($sch['topic']) ?></p>
                                <span class="rounded-full px-2 py-0.5 text-xs font-bold flex-shrink-0 <?= $labelCls ?>"><?= $label ?></span>
                            </div>
                            <div class="mt-0.5 flex gap-3">
                                <span class="flex items-center gap-1 text-xs text-gray-400">
                                    <i class="ri-group-line"></i><?= sanitize($sch['batch_name']) ?>
                                </span>
                                <span class="flex items-center gap-1 text-xs text-gray-400">
                                    <i class="ri-time-line"></i><?= date('h:i A', strtotime($sch['start_time'])) ?>
                                </span>
                            </div>
                        </div>
                        <!-- Hover actions -->
                        <div class="flex flex-shrink-0 items-center gap-1.5 opacity-0 transition-opacity group-hover:opacity-100">
                            <a href="<?= $B ?>/admin/attendance/attendance.php?schedule_id=<?= $sch['id'] ?>&batch_id=<?= urlencode($sch['batch_id']) ?>&date=<?= $sch['schedule_date'] ?>"
                               title="Mark Attendance"
                               class="flex h-6 w-6 items-center justify-center rounded-lg bg-green-100 text-green-600 hover:bg-green-200 transition">
                                <i class="ri-checkbox-circle-line text-xs"></i>
                            </a>
                            <a href="<?= $B ?>/admin/schedule/edit_schedule.php?id=<?= $sch['id'] ?>"
                               title="Edit"
                               class="flex h-6 w-6 items-center justify-center rounded-lg bg-orange-100 text-[#E87F24] hover:bg-orange-200 transition">
                                <i class="ri-edit-line text-xs"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div class="border-t border-gray-100 pt-2">
                        <a href="<?= $B ?>/admin/schedule/add_schedule.php"
                           class="flex items-center gap-1 text-xs font-semibold text-[#E87F24] hover:underline">
                            <i class="ri-add-circle-line"></i> Add New Schedule
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="dash-card rounded-2xl bg-white p-6 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="flex items-center gap-2 text-base font-semibold text-gray-800">
                    <i class="ri-history-line text-[#73A5CA]"></i> Recent Activity
                </h3>
                <button onclick="location.reload()" title="Refresh"
                        class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-[#E87F24] transition">
                    <i class="ri-refresh-line text-base"></i>
                </button>
            </div>

            <div class="max-h-96 space-y-1 overflow-y-auto pr-1">
                <?php if (empty($recent_activities)): ?>
                    <div class="py-10 text-center text-gray-400">
                        <i class="ri-inbox-line mb-2 block text-4xl"></i>
                        <p class="text-sm">No activity logged yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_activities as $act):
                        $iconClass = activityIcon($act['action'] ?? '', $act['module'] ?? '');
                    ?>
                    <div class="activity-row rounded-lg p-3 transition">
                        <div class="flex items-start gap-3">
                            <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-gray-100">
                                <i class="<?= $iconClass ?> text-sm"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-gray-800">
                                    <span class="font-semibold"><?= sanitize($act['username'] ?? 'System') ?></span>
                                    <span class="text-gray-500"> — <?= sanitize(ucwords(strtolower(str_replace('_', ' ', $act['action'] ?? '')))) ?></span>
                                </p>
                                <?php if (!empty($act['description'])): ?>
                                    <p class="truncate text-xs text-gray-400 mt-0.5"><?= sanitize(truncate($act['description'], 55)) ?></p>
                                <?php endif; ?>
                                <p class="mt-1 flex items-center gap-1 text-xs text-gray-400">
                                    <i class="ri-time-line"></i>
                                    <?= formatDate($act['created_at']) ?>
                                </p>
                            </div>
                            <?php if (!empty($act['module'])): ?>
                                <span class="flex-shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">
                                    <?= sanitize(ucfirst($act['module'])) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div><!-- /.3-col grid -->
</div><!-- /.fade-up -->

<script>
// Animate progress bars after paint
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.progress-bar[data-target]').forEach(bar => {
        const target = parseFloat(bar.dataset.target) || 0;
        requestAnimationFrame(() => {
            setTimeout(() => { bar.style.width = target + '%'; }, 120);
        });
    });

    // Count-up animation for stat numbers
    document.querySelectorAll('.stat-num').forEach(el => {
        const final = parseInt(el.textContent.replace(/,/g, ''), 10);
        if (!isNaN(final) && final > 0) {
            let cur = 0;
            const step = Math.ceil(final / 28);
            const tick = setInterval(() => {
                cur = Math.min(cur + step, final);
                el.textContent = cur.toLocaleString();
                if (cur >= final) clearInterval(tick);
            }, 30);
        }
    });

    // Auto-refresh every 90 s (only when tab is visible)
    setInterval(() => { if (!document.hidden) location.reload(); }, 90000);
});
</script>

</div><!-- /.content-area -->
</div><!-- /.page-wrapper -->
</body>
</html>
