<?php
/**
 * sidebar.php — GyanSetu LMS
 * ROUTING FIX: All links use BASE_URL (absolute paths).
 * ACCESS CONTROL: Menu items are shown only if user has view permission.
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/admin/login.php');
    exit();
}

require_once __DIR__ . '/../includes/access_settings.php'; // for canView()

$B = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';

// Active state using REQUEST_URI (works from any depth)
$currentPath = strtok($_SERVER['REQUEST_URI'] ?? '', '?');

function sidebarLinkActive(string $link): bool {
    global $currentPath;
    $p = parse_url($link, PHP_URL_PATH);
    return $p !== false && $currentPath === $p;
}

function sidebarGroupActive(array $submenu): bool {
    global $currentPath;
    foreach ($submenu as $sub) {
        $p = parse_url($sub['link'], PHP_URL_PATH);
        if ($p && $currentPath === $p) return true;
        // Match any page inside the same folder
        $dir = rtrim(dirname($p), '/');
        if ($dir && str_starts_with($currentPath, $dir . '/')) return true;
    }
    return false;
}

// Build menu items dynamically with permission checks
$menu_items = [];

// Dashboard (always shown)
$menu_items['dashboard'] = ['name'=>'Dashboard', 'icon'=>'ri-dashboard-line', 'link'=>"$B/admin/dashboard/dashboard.php"];

// Batches
if (canView('batches')) {
    $menu_items['batches'] = [
        'name'=>'Batches', 'icon'=>'ri-group-line', 'link'=>"$B/admin/batch/batches.php",
        'submenu'=>[
            ['name'=>'All Batches', 'link'=>"$B/admin/batch/batches.php"],
            ['name'=>'Add Batch',   'link'=>"$B/admin/batch/add_batch.php"],
            ['name'=>'Assign Student', 'link'=>"$B/admin/batch/assign_student.php"],
        ]
    ];
}

// Students
if (canView('students')) {
    $menu_items['students'] = [
        'name'=>'Students', 'icon'=>'ri-user-star-line', 'link'=>"$B/admin/students/students.php",
        'submenu'=>[
            ['name'=>'All Students', 'link'=>"$B/admin/students/students.php"],
            ['name'=>'Add Student',  'link'=>"$B/admin/student/add_student.php"],
        ]
    ];
}

// Courses
if (canView('courses')) {
    $menu_items['courses'] = [
        'name'=>'Courses', 'icon'=>'ri-book-open-line', 'link'=>"$B/admin/course/courses.php",
        'submenu'=>[
            ['name'=>'All Courses',   'link'=>"$B/admin/course/courses.php"],
            ['name'=>'Add Course',    'link'=>"$B/admin/course/add_course.php"],
            ['name'=>'Batch Courses', 'link'=>"$B/admin/batch/course/batch_courses.php"],
            ['name'=>'Assign Course', 'link'=>"$B/admin/batch/course/assign_course.php"],
        ]
    ];
}

// Schedule
if (canView('schedule')) {
    $menu_items['schedule'] = [
        'name'=>'Schedule', 'icon'=>'ri-calendar-line', 'link'=>"$B/admin/schedule/schedule.php",
        'submenu'=>[
            ['name'=>'All Schedules', 'link'=>"$B/admin/schedule/schedule.php"],
            ['name'=>'Add Schedule',  'link'=>"$B/admin/schedule/add_schedule.php"],
        ]
    ];
}

// Attendance
if (canView('attendance')) {
    $menu_items['attendance'] = [
        'name'=>'Attendance', 'icon'=>'ri-checkbox-line', 'link'=>"$B/admin/attendance/attendance.php",
        'submenu'=>[
            ['name'=>'Mark Attendance', 'link'=>"$B/admin/attendance/attendance.php"],
            ['name'=>'View Records',    'link'=>"$B/admin/attendance/view_attendance.php"],
        ]
    ];
}

// Notes
if (canView('notes')) {
    $menu_items['notes'] = [
        'name'=>'Notes', 'icon'=>'ri-file-text-line', 'link'=>"$B/admin/notes/notes.php",
        'submenu'=>[
            ['name'=>'All Notes', 'link'=>"$B/admin/notes/notes.php"],
            ['name'=>'Add Note',  'link'=>"$B/admin/notes/add_note.php"],
        ]
    ];
}

// Assignments
if (canView('assignments')) {
    $menu_items['assignments'] = [
        'name'=>'Assignments', 'icon'=>'ri-task-line', 'link'=>"$B/admin/assignments/assignments.php",
        'submenu'=>[
            ['name'=>'All Assignments', 'link'=>"$B/admin/assignments/assignments.php"],
            ['name'=>'Add Assignment',  'link'=>"$B/admin/assignments/add_assignment.php"],
        ]
    ];
}

// Tests
if (canView('tests')) {
    $menu_items['tests'] = [
        'name'=>'Tests', 'icon'=>'ri-questionnaire-line', 'link'=>"$B/admin/tests/test.php",
        'submenu'=>[
            ['name'=>'All Tests',    'link'=>"$B/admin/tests/test.php"],
            ['name'=>'Create Test',  'link'=>"$B/admin/tests/create_test.php"],
        ]
    ];
}

// Exams
if (canView('exams')) {
    $menu_items['exams'] = [
        'name'=>'Exams', 'icon'=>'ri-file-copy-line', 'link'=>"$B/admin/exams/exams.php",
        'submenu'=>[
            ['name'=>'All Exams',    'link'=>"$B/admin/exams/exams.php"],
            ['name'=>'Create Exam',  'link'=>"$B/admin/exams/create_exam.php"],
        ]
    ];
}

// Payments
if (canView('payments')) {
    $menu_items['payments'] = ['name'=>'Payments', 'icon'=>'ri-bank-card-line', 'link'=>"$B/admin/payments/payments.php"];
}

// Users (Unified user management)
if (canView('users')) {
    $menu_items['users'] = [
        'name'=>'Users', 'icon'=>'ri-user-settings-line', 'link'=>"$B/admin/users/users.php",
        'submenu'=>[
            ['name'=>'All Users',  'link'=>"$B/admin/users/users.php"],
            ['name'=>'Add User',   'link'=>"$B/admin/users/add_user.php"],
        ]
    ];
}

// Notifications
if (canView('notifications')) {
    $menu_items['notifications'] = ['name'=>'Notifications', 'icon'=>'ri-notification-line', 'link'=>"$B/admin/notifications/notifications.php"];
}

// Settings
if (canView('settings')) {
    $menu_items['settings'] = [
        'name'=>'Settings', 'icon'=>'ri-settings-line', 'link'=>"$B/admin/settings/settings.php",
        'submenu'=>[
            ['name'=>'System Settings', 'link'=>"$B/admin/settings/settings.php"],
            ['name'=>'Access Control',  'link'=>"$B/admin/settings/access.php"],
        ]
    ];
}

// Collapse state
$sidebar_collapsed = isset($_COOKIE['sidebar_collapsed']) && $_COOKIE['sidebar_collapsed'] === 'true';
$sc = $sidebar_collapsed ? 'collapsed' : '';
$pw = $sidebar_collapsed ? 'expanded'  : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($page_title) ? htmlspecialchars($page_title) : 'GyanSetu Admin' ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
<?= isset($extra_head) ? $extra_head : '' ?>
<style>
/* (Same CSS as before – unchanged) */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%}
body{display:flex;min-height:100vh;background:#F5F7FA;font-family:'Inter',sans-serif}
.sidebar{width:280px;flex-shrink:0;background:linear-gradient(180deg,#1a1a2e 0%,#16213e 100%);transition:width .3s cubic-bezier(.4,0,.2,1);position:fixed;left:0;top:0;height:100vh;z-index:1000;overflow-y:auto;overflow-x:hidden;box-shadow:4px 0 20px rgba(0,0,0,.15)}
.sidebar.collapsed{width:80px}
.sidebar::-webkit-scrollbar{width:4px}
.sidebar::-webkit-scrollbar-track{background:#1a1a2e}
.sidebar::-webkit-scrollbar-thumb{background:#E87F24;border-radius:10px}
.logo{display:flex;align-items:center;gap:12px;padding:22px 20px;border-bottom:1px solid rgba(255,255,255,.08)}
.logo i{font-size:28px;color:#E87F24;flex-shrink:0}
.logo-text{font-size:18px;font-weight:700;color:#fff;white-space:nowrap}
.user-info-block{padding:14px 20px;border-bottom:1px solid rgba(255,255,255,.08);margin-bottom:6px}
.ui-inner{display:flex;align-items:center;gap:12px}
.user-avatar{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#E87F24,#FFC81E);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.user-avatar i{color:#fff;font-size:18px}
.user-name{color:#fff;font-size:13px;font-weight:500;white-space:nowrap}
.user-role{color:#9ca3af;font-size:11px;white-space:nowrap}
.menu-container{padding:4px 8px 80px}
.menu-item{display:flex;align-items:center;gap:12px;padding:11px 14px;margin:3px 0;border-radius:12px;color:#9ca3af;font-size:13.5px;font-weight:500;cursor:pointer;text-decoration:none;transition:all .22s ease;position:relative;white-space:nowrap}
.menu-item i{font-size:19px;flex-shrink:0;transition:transform .22s ease}
.menu-item:hover{background:rgba(255,255,255,.07);color:#fff}
.menu-item:hover i{transform:scale(1.12)}
.menu-item.active{background:linear-gradient(135deg,#E87F24,#FFC81E);color:#fff;box-shadow:0 6px 18px rgba(232,127,36,.35)}
.menu-text{flex:1;overflow:hidden}
.submenu-arrow{font-size:15px!important;transition:transform .3s ease}
.submenu{max-height:0;overflow:hidden;transition:max-height .35s ease;padding-left:44px}
.submenu.show{max-height:400px}
.submenu-item{display:block;padding:8px 10px;color:#a0a0a0;font-size:13px;border-radius:8px;text-decoration:none;transition:all .2s;margin:2px 0}
.submenu-item:hover{background:rgba(232,127,36,.1);color:#E87F24;padding-left:16px}
.submenu-item.active{color:#E87F24;font-weight:600}
.menu-divider{border-top:1px solid rgba(255,255,255,.08);margin:10px 6px}
.toggle-sidebar{position:absolute;right:-13px;top:22px;background:#E87F24;width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#fff;font-size:15px;transition:all .3s;z-index:1001;box-shadow:0 2px 8px rgba(0,0,0,.3)}
.toggle-sidebar:hover{transform:scale(1.1);background:#FFC81E}
.toggle-sidebar i{transition:transform .3s}
.sidebar.collapsed .toggle-sidebar i{transform:rotate(180deg)}
.sidebar.collapsed .logo-text,.sidebar.collapsed .user-name,.sidebar.collapsed .user-role,.sidebar.collapsed .menu-text,.sidebar.collapsed .submenu-arrow,.sidebar.collapsed .submenu{display:none!important}
.sidebar.collapsed .menu-item{justify-content:center;padding:12px}
.sidebar.collapsed .menu-item:hover::after{content:attr(data-name);position:absolute;left:calc(100% + 12px);top:50%;transform:translateY(-50%);background:#111827;color:#fff;padding:6px 12px;border-radius:8px;font-size:12px;white-space:nowrap;pointer-events:none;z-index:9999}
.page-wrapper{margin-left:280px;width:calc(100% - 280px);min-height:100vh;display:flex;flex-direction:column;transition:margin-left .3s cubic-bezier(.4,0,.2,1),width .3s cubic-bezier(.4,0,.2,1)}
.page-wrapper.expanded{margin-left:80px;width:calc(100% - 80px)}
.top-nav{background:#fff;box-shadow:0 1px 8px rgba(0,0,0,.07);position:sticky;top:0;z-index:500;padding:12px 24px;display:flex;align-items:center;justify-content:space-between;gap:16px}
.search-wrapper{position:relative}
.search-wrapper i{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:15px;pointer-events:none}
.search-wrapper input{border:1px solid #e5e7eb;border-radius:10px;padding:8px 12px 8px 34px;font-size:13px;width:240px;outline:none;transition:border-color .2s,box-shadow .2s}
.search-wrapper input:focus{border-color:#E87F24;box-shadow:0 0 0 3px rgba(232,127,36,.12)}
.nav-right{display:flex;align-items:center;gap:14px}
.notif-btn{position:relative;color:#6b7280;cursor:pointer;font-size:21px}
.notif-btn .badge{position:absolute;top:-4px;right:-5px;width:16px;height:16px;background:#ef4444;border-radius:50%;color:#fff;font-size:9px;display:flex;align-items:center;justify-content:center}
.user-menu{position:relative}
.user-menu-btn{display:flex;align-items:center;gap:8px;cursor:pointer;color:#374151;font-size:13px;font-weight:500;background:none;border:none}
.umenu-avatar{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#E87F24,#FFC81E);display:flex;align-items:center;justify-content:center}
.umenu-avatar i{color:#fff;font-size:15px}
.user-dropdown{position:absolute;right:0;top:calc(100% + 8px);background:#fff;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.12);min-width:170px;overflow:hidden;opacity:0;visibility:hidden;transform:translateY(-4px);transition:all .2s;z-index:600}
.user-menu:hover .user-dropdown{opacity:1;visibility:visible;transform:translateY(0)}
.user-dropdown a{display:flex;align-items:center;gap:8px;padding:10px 14px;font-size:13px;color:#374151;text-decoration:none;transition:background .15s}
.user-dropdown a:hover{background:#f9fafb}
.user-dropdown a.danger{color:#ef4444}
.user-dropdown hr{border:none;border-top:1px solid #f3f4f6;margin:3px 0}
.mobile-toggle{display:none;background:none;border:none;cursor:pointer;color:#6b7280;font-size:22px}
.content-area{flex:1;padding:24px;overflow-x:hidden}
.mobile-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999}
.mobile-overlay.show{display:block}
@media(max-width:1024px){
    .sidebar{transform:translateX(-100%);position:fixed;width:280px!important}
    .sidebar.mobile-open{transform:translateX(0)}
    .sidebar.collapsed{transform:translateX(-100%)}
    .page-wrapper,.page-wrapper.expanded{margin-left:0!important;width:100%!important}
    .mobile-toggle{display:block}
    .toggle-sidebar{display:none}
}
@media(max-width:640px){.search-wrapper input{width:150px}.content-area{padding:14px}}
</style>
</head>
<body>
<div class="mobile-overlay" id="mobileOverlay"></div>
<aside class="sidebar <?= $sc ?>" id="sidebar">
    <div class="toggle-sidebar" id="toggleSidebar"><i class="ri-arrow-left-s-line"></i></div>
    <div class="logo"><i class="ri-book-open-line"></i><span class="logo-text">GyanSetu</span></div>
    <div class="user-info-block">
        <div class="ui-inner">
            <div class="user-avatar"><i class="ri-user-line"></i></div>
            <div>
                <p class="user-name"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></p>
                <p class="user-role"><?= htmlspecialchars($_SESSION['role'] ?? 'super_admin') ?></p>
            </div>
        </div>
    </div>
    <nav class="menu-container">
        <?php foreach ($menu_items as $key => $item): ?>
            <?php if (isset($item['submenu'])): ?>
                <?php $ga = sidebarGroupActive($item['submenu']); ?>
                <div class="menu-item has-submenu <?= $ga ? 'active' : '' ?>"
                     data-menu="<?= $key ?>" data-name="<?= htmlspecialchars($item['name']) ?>">
                    <i class="<?= $item['icon'] ?>"></i>
                    <span class="menu-text"><?= htmlspecialchars($item['name']) ?></span>
                    <i class="ri-arrow-down-s-line submenu-arrow" style="<?= $ga ? 'transform:rotate(180deg)' : '' ?>"></i>
                </div>
                <div class="submenu <?= $ga ? 'show' : '' ?>" id="submenu-<?= $key ?>">
                    <?php foreach ($item['submenu'] as $sub): $sa = sidebarLinkActive($sub['link']); ?>
                        <a href="<?= $sub['link'] ?>" class="submenu-item <?= $sa ? 'active' : '' ?>">
                            <?= htmlspecialchars($sub['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <a href="<?= $item['link'] ?>"
                   class="menu-item <?= sidebarLinkActive($item['link']) ? 'active' : '' ?>"
                   data-name="<?= htmlspecialchars($item['name']) ?>">
                    <i class="<?= $item['icon'] ?>"></i>
                    <span class="menu-text"><?= htmlspecialchars($item['name']) ?></span>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
        <div class="menu-divider"></div>
        <a href="<?= $B ?>/admin/logout.php" class="menu-item" data-name="Logout">
            <i class="ri-logout-box-line"></i><span class="menu-text">Logout</span>
        </a>
    </nav>
</aside>
<div class="page-wrapper <?= $pw ?>" id="pageWrapper">
    <header class="top-nav">
        <div style="display:flex;align-items:center;gap:12px">
            <button class="mobile-toggle" id="mobileToggle"><i class="ri-menu-line"></i></button>
            <div class="search-wrapper"><i class="ri-search-line"></i><input type="text" placeholder="Search…" aria-label="Search"></div>
        </div>
        <div class="nav-right">
            <div class="notif-btn"><i class="ri-notification-3-line"></i><span class="badge">3</span></div>
            <div class="user-menu">
                <button class="user-menu-btn">
                    <div class="umenu-avatar"><i class="ri-user-line"></i></div>
                    <span><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
                    <i class="ri-arrow-down-s-line"></i>
                </button>
                <div class="user-dropdown">
                    <a href="<?= $B ?>/admin/profile.php"><i class="ri-user-line"></i> Profile</a>
                    <a href="<?= $B ?>/admin/settings/settings.php"><i class="ri-settings-line"></i> Settings</a>
                    <hr>
                    <a href="<?= $B ?>/admin/logout.php" class="danger"><i class="ri-logout-box-line"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>
    <div class="content-area" id="contentArea">
<script>
(function(){
    var sb=document.getElementById('sidebar'),pw=document.getElementById('pageWrapper'),
        tb=document.getElementById('toggleSidebar'),mb=document.getElementById('mobileToggle'),
        ov=document.getElementById('mobileOverlay');
    if(tb){tb.addEventListener('click',function(){sb.classList.toggle('collapsed');pw.classList.toggle('expanded');document.cookie='sidebar_collapsed='+sb.classList.contains('collapsed')+';path=/;max-age='+(30*24*60*60);});}
    if(mb){mb.addEventListener('click',function(){sb.classList.toggle('mobile-open');ov.classList.toggle('show');});}
    if(ov){ov.addEventListener('click',function(){sb.classList.remove('mobile-open');ov.classList.remove('show');});}
    document.querySelectorAll('.has-submenu').forEach(function(item){
        item.addEventListener('click',function(){
            var sub=document.getElementById('submenu-'+this.dataset.menu);
            var arrow=this.querySelector('.submenu-arrow');
            if(!sub)return;
            var open=sub.classList.toggle('show');
            if(arrow)arrow.style.transform=open?'rotate(180deg)':'rotate(0deg)';
        });
    });
})();
</script>