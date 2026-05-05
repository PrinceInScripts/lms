<?php
/**
 * dashboard_schedule_widget.php — GyanSetu LMS
 *
 * HOW TO USE IN dashboard.php:
 *   require_once __DIR__ . '/../../includes/dashboard_schedule_widget.php';
 *   // Then echo the widget anywhere in your dashboard HTML:
 *   echo renderUpcomingScheduleWidget($db, BASE_URL);
 *
 * Or just paste the renderUpcomingScheduleWidget() function into your
 * existing dashboard.php and call it wherever the widget should appear.
 *
 * The $db variable must be a PDO instance (from getDB()).
 */

if (!function_exists('renderUpcomingScheduleWidget')) {

function renderUpcomingScheduleWidget(PDO $db, string $baseUrl = ''): string
{
    $B = rtrim($baseUrl, '/');

    $stmt = $db->prepare("
        SELECT s.id, s.batch_id, s.schedule_date, s.start_time, s.end_time,
               s.topic, s.description,
               b.batch_name
        FROM schedule s
        JOIN batches b ON b.batch_id = s.batch_id
        WHERE s.is_cancelled = 0
          AND s.schedule_date >= CURDATE()
        ORDER BY s.schedule_date ASC, s.start_time ASC
        LIMIT 5
    ");
    $stmt->execute();
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $today    = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));

    ob_start();
?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <!-- Widget header -->
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center">
                <i class="ri-calendar-line text-[#E87F24] text-base"></i>
            </div>
            <h3 class="font-bold text-gray-800 text-sm">Upcoming Schedule</h3>
        </div>
        <a href="<?= $B ?>/admin/schedule/schedule.php"
           class="text-xs font-semibold text-[#73A5CA] hover:text-blue-700 transition flex items-center gap-1">
            View All <i class="ri-arrow-right-line"></i>
        </a>
    </div>

    <?php if (empty($schedules)): ?>
    <!-- Empty state -->
    <div class="flex flex-col items-center justify-center py-10 text-gray-400">
        <i class="ri-calendar-check-line text-3xl mb-2 text-[#E87F24] opacity-40"></i>
        <p class="text-sm font-medium">No upcoming sessions</p>
        <a href="<?= $B ?>/admin/schedule/add_schedule.php"
           class="mt-2 text-xs text-[#E87F24] hover:underline font-semibold">+ Add Schedule</a>
    </div>

    <?php else: ?>
    <div class="divide-y divide-gray-50">
        <?php foreach ($schedules as $s): ?>
        <?php
            $dateLabel = match(true) {
                $s['schedule_date'] === $today    => 'Today',
                $s['schedule_date'] === $tomorrow => 'Tomorrow',
                default => date('d M', strtotime($s['schedule_date']))
            };
            $isToday    = $s['schedule_date'] === $today;
            $isTomorrow = $s['schedule_date'] === $tomorrow;
            $dotColor   = $isToday ? 'bg-green-500' : ($isTomorrow ? 'bg-yellow-400' : 'bg-blue-400');
            $labelColor = $isToday ? 'text-green-600 bg-green-50' : ($isTomorrow ? 'text-yellow-700 bg-yellow-50' : 'text-blue-600 bg-blue-50');
        ?>
        <div class="flex items-start gap-3 px-5 py-3.5 hover:bg-orange-50/40 transition-colors group">
            <!-- Date block -->
            <div class="flex-shrink-0 text-center w-11">
                <p class="text-lg font-extrabold text-gray-800 leading-none">
                    <?= date('d', strtotime($s['schedule_date'])) ?>
                </p>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">
                    <?= date('M', strtotime($s['schedule_date'])) ?>
                </p>
            </div>

            <!-- Divider dot -->
            <div class="flex flex-col items-center mt-1.5 flex-shrink-0">
                <span class="w-2 h-2 rounded-full <?= $dotColor ?>"></span>
                <span class="w-px flex-1 bg-gray-100 mt-1" style="min-height:28px"></span>
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <p class="text-sm font-semibold text-gray-800 truncate">
                        <?= htmlspecialchars($s['topic'], ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?= $labelColor ?> flex-shrink-0">
                        <?= $dateLabel ?>
                    </span>
                </div>
                <div class="flex items-center gap-3 mt-1">
                    <span class="text-xs text-gray-400 flex items-center gap-1">
                        <i class="ri-group-line"></i>
                        <?= htmlspecialchars($s['batch_name'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <span class="text-xs text-gray-400 flex items-center gap-1">
                        <i class="ri-time-line"></i>
                        <?= date('h:i A', strtotime($s['start_time'])) ?>
                        &ndash;
                        <?= date('h:i A', strtotime($s['end_time'])) ?>
                    </span>
                </div>
            </div>

            <!-- Quick actions -->
            <div class="flex items-center gap-2 flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                <a href="<?= $B ?>/admin/attendance/attendance.php?schedule_id=<?= $s['id'] ?>&batch_id=<?= urlencode($s['batch_id']) ?>&date=<?= $s['schedule_date'] ?>"
                   title="Mark attendance"
                   class="w-7 h-7 rounded-lg bg-green-100 flex items-center justify-center text-green-600 hover:bg-green-200 transition">
                    <i class="ri-checkbox-circle-line text-sm"></i>
                </a>
                <a href="<?= $B ?>/admin/schedule/edit_schedule.php?id=<?= $s['id'] ?>"
                   title="Edit schedule"
                   class="w-7 h-7 rounded-lg bg-orange-100 flex items-center justify-center text-[#E87F24] hover:bg-orange-200 transition">
                    <i class="ri-edit-line text-sm"></i>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Footer -->
    <div class="px-5 py-3 border-t border-gray-100 bg-gray-50">
        <a href="<?= $B ?>/admin/schedule/add_schedule.php"
           class="text-xs font-semibold text-[#E87F24] hover:underline flex items-center gap-1">
            <i class="ri-add-circle-line"></i> Add New Schedule
        </a>
    </div>
    <?php endif; ?>
</div>
<?php
    return ob_get_clean();
}

} // end function_exists guard
