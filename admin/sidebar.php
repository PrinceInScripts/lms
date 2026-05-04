<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Define menu items with permissions
$menu_items = [
    'dashboard' => [
        'name' => 'Dashboard',
        'icon' => 'ri-dashboard-line',
        'link' => '../dashboard/dashboard.php',
        'permission' => 'dashboard'
    ],
    'batches' => [
        'name' => 'Batches',
        'icon' => 'ri-group-line',
        'link' => '../batch/batches.php',
        'permission' => 'batches',
        'submenu' => [
            ['name' => 'All Batches', 'link' => '../batch/batches.php'],
            ['name' => 'Add Batch', 'link' => '../batch/add_batch.php']
        ]
    ],
    'students' => [
        'name' => 'Students',
        'icon' => 'ri-user-star-line',
        'link' => '../students/students.php',
        'permission' => 'students',
        'submenu' => [
            ['name' => 'All Students', 'link' => '../students/students.php'],
            ['name' => 'Add Student', 'link' => '../student/add_student.php']
        ]
    ],
    'schedule' => [
        'name' => 'Schedule',
        'icon' => 'ri-calendar-line',
        'link' => '../schedule/schedule.php',
        'permission' => 'schedule'
    ],
    'attendance' => [
        'name' => 'Attendance',
        'icon' => 'ri-checkbox-line',
        'link' => '../attendance/attendance.php',
        'permission' => 'attendance'
    ],
    'notes' => [
        'name' => 'Notes',
        'icon' => 'ri-file-text-line',
        'link' => '../notes/notes.php',
        'permission' => 'notes'
    ],
    'assignments' => [
        'name' => 'Assignments',
        'icon' => 'ri-task-line',
        'link' => '../assignments/assignments.php',
        'permission' => 'assignments'
    ],
    'tests' => [
        'name' => 'Tests',
        'icon' => 'ri-questionnaire-line',
        'link' => '../tests/test.php',
        'permission' => 'tests'
    ],
    'exams' => [
        'name' => 'Exams',
        'icon' => 'ri-file-copy-line',
        'link' => '../exams/exams.php',
        'permission' => 'exams'
    ],
    'payments' => [
        'name' => 'Payments',
        'icon' => 'ri-bank-card-line',
        'link' => '../payments/payments.php',
        'permission' => 'payments'
    ],
    'users' => [
        'name' => 'Users',
        'icon' => 'ri-user-settings-line',
        'link' => '../users/admins.php',
        'permission' => 'users',
        'submenu' => [
            ['name' => 'Admins', 'link' => '../users/admins.php'],
            ['name' => 'Trainers', 'link' => '../users/trainers.php'],
            ['name' => 'Sales', 'link' => '../users/sales.php'],
            ['name' => 'Accounts', 'link' => '../users/accounts.php']
        ]
    ],
    'notifications' => [
        'name' => 'Notifications',
        'icon' => 'ri-notification-line',
        'link' => '../notifications/notifications.php',
        'permission' => 'notifications'
    ],
    'settings' => [
        'name' => 'Settings',
        'icon' => 'ri-settings-line',
        'link' => '../settings/settings.php',
        'permission' => 'settings'
    ]
];

// Function to check if sidebar should be collapsed (from cookie)
$sidebar_collapsed = isset($_COOKIE['sidebar_collapsed']) && $_COOKIE['sidebar_collapsed'] === 'true';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            z-index: 1000;
            overflow-y: auto;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar.collapsed {
            width: 80px;
        }
        
        .sidebar.collapsed .menu-text,
        .sidebar.collapsed .submenu,
        .sidebar.collapsed .logo-text,
        .sidebar.collapsed .user-name {
            display: none;
        }
        
        .sidebar.collapsed .menu-item {
            justify-content: center;
            padding: 12px;
        }
        
        .sidebar.collapsed .menu-item i {
            margin: 0;
            font-size: 24px;
        }
        
        .sidebar.collapsed .logo i {
            font-size: 32px;
        }
        
        .sidebar::-webkit-scrollbar {
            width: 5px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: #1a1a2e;
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: #E87F24;
            border-radius: 10px;
        }
        
       .menu-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 12px 18px;
    margin: 6px 10px;
    border-radius: 14px;
    color: #9ca3af;
    font-weight: 500;
    transition: all 0.25s ease;
    position: relative;
}

/* Hover */
.menu-item:hover {
    background: rgba(255, 255, 255, 0.06);
    color: #fff;
    transform: translateX(6px);
}

/* Active */
.menu-item.active {
    background: linear-gradient(135deg, #E87F24, #FFC81E);
    color: white;
    box-shadow: 0 8px 20px rgba(232, 127, 36, 0.35);
}

/* Icon glow on active */
.menu-item.active i {
    transform: scale(1.1);
}

/* Smooth icon */
.menu-item i {
    font-size: 20px;
    transition: all 0.3s ease;
}

/* Hover icon effect */
.menu-item:hover i {
    transform: scale(1.15);
}

.menu-item::before {
    content: "";
    position: absolute;
    left: 0;
    height: 0%;
    width: 4px;
    background: linear-gradient(#E87F24, #FFC81E);
    border-radius: 4px;
    transition: height 0.3s ease;
}

.menu-item.active::before {
    height: 70%;
}
        
        .submenu {
            margin-left: 56px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        
        .submenu.show {
            max-height: 300px;
        }
        
        .submenu-item {
            padding: 8px 12px;
            color: #a0a0a0;
            font-size: 14px;
            transition: all 0.3s ease;
            border-radius: 8px;
            margin: 2px 0;
            cursor: pointer;
        }
        
        .submenu-item:hover {
            background: rgba(232, 127, 36, 0.1);
            color: #E87F24;
            padding-left: 16px;
        }
        
        .submenu-item.active {
            color: #E87F24;
            font-weight: 500;
        }
        
        .toggle-sidebar {
            position: absolute;
            right: -12px;
            top: 20px;
            background: #E87F24;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: white;
            transition: all 0.3s ease;
            z-index: 1001;
        }
        
        .toggle-sidebar:hover {
            transform: scale(1.1);
            background: #FFC81E;
        }
        
        .main-content {
            margin-left: 280px;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            min-height: 100vh;
            background: #F5F7FA;
        }
        
        .main-content.expanded {
            margin-left: 80px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 1050;
            }
            
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0 !important;
            }
            
            .mobile-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 1040;
                display: none;
            }
            
            .mobile-overlay.show {
                display: block;
            }
        }

        .sidebar.collapsed .menu-item {
    position: relative;
}

.sidebar.collapsed .menu-item:hover::after {
    content: attr(data-name);
    position: absolute;
    left: 90px;
    background: #111827;
    color: white;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 12px;
    white-space: nowrap;
}

.main-content {
    margin-left: 280px;
    transition: margin-left 0.3s ease, padding 0.3s ease;
}

.main-content.expanded {
    margin-left: 80px !important;
}

/* Fix content spacing */
.main-content > div {
    max-width: 1400px;
    margin: 0 auto;
}
    </style>
</head>
<body>
    <div class="mobile-overlay" id="mobileOverlay"></div>
    
    <div class="sidebar <?php echo $sidebar_collapsed ? 'collapsed' : ''; ?>" id="sidebar">
        <div class="toggle-sidebar" id="toggleSidebar">
            <i class="ri-arrow-left-s-line"></i>
        </div>
        
        <div class="logo p-6 flex items-center gap-3 border-b border-white/10">
            <i class="ri-book-open-line text-3xl text-[#E87F24]"></i>
            <span class="logo-text text-xl font-bold text-white">Guru Edu</span>
        </div>
        
        <div class="user-info p-4 border-b border-white/10 mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-gradient-to-r from-[#E87F24] to-[#FFC81E] flex items-center justify-center">
                    <i class="ri-user-line text-white text-xl"></i>
                </div>
                <div class="user-info-text">
                    <p class="user-name text-white font-medium text-sm"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></p>
                    <p class="text-xs text-gray-400"><?php echo htmlspecialchars($_SESSION['role'] ?? 'Administrator'); ?></p>
                </div>
            </div>
        </div>
        
        <div class="menu-container px-2 pb-20">
            <?php foreach ($menu_items as $key => $item): ?>
                <?php if (isset($item['submenu'])): ?>
                    <div class="menu-item has-submenu" data-menu="<?php echo $key; ?>">
                        <i class="<?php echo $item['icon']; ?>"></i>
                        <span class="menu-text flex-1"><?php echo $item['name']; ?></span>
                        <i class="ri-arrow-down-s-line submenu-arrow"></i>
                    </div>
                    <div class="submenu" id="submenu-<?php echo $key; ?>">
                        <?php foreach ($item['submenu'] as $subitem): ?>
                            <a href="<?php echo $subitem['link']; ?>" class="submenu-item block">
                                <?php echo $subitem['name']; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <a href="<?php echo $item['link']; ?>" data-name="<?php echo $item['name']; ?>" class="menu-item <?php echo (strpos($item['link'], $current_dir) !== false || $current_page == basename($item['link'])) ? 'active' : ''; ?>">
                        <i class="<?php echo $item['icon']; ?>"></i>
                        <span class="menu-text"><?php echo $item['name']; ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
            
            <a href="../logout.php" class="menu-item mt-8" style="border-top: 1px solid rgba(255,255,255,0.1); margin-top: 20px; padding-top: 16px;">
                <i class="ri-logout-box-line"></i>
                <span class="menu-text">Logout</span>
            </a>
        </div>
    </div>
    
    <div class="main-content" id="mainContent">
        <!-- Top Navigation Bar -->
        <nav class="bg-white shadow-sm sticky top-0 z-50">
            <div class="px-4 md:px-6 py-3 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <button id="mobileMenuToggle" class="lg:hidden text-gray-600 hover:text-[#E87F24] transition">
                        <i class="ri-menu-line text-2xl"></i>
                    </button>
                    <div class="relative">
                        <i class="ri-search-line absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="text" placeholder="Search..." class="pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:border-[#E87F24] focus:ring-2 focus:ring-[#E87F24]/20 w-64">
                    </div>
                </div>
                
                <div class="flex items-center gap-4">
                    <button class="relative text-gray-600 hover:text-[#E87F24] transition">
                        <i class="ri-notification-3-line text-2xl"></i>
                        <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 rounded-full text-white text-xs flex items-center justify-center">3</span>
                    </button>
                    
                    <div class="relative group">
                        <button class="flex items-center gap-2 text-gray-700 hover:text-[#E87F24] transition">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-r from-[#E87F24] to-[#FFC81E] flex items-center justify-center">
                                <i class="ri-user-line text-white"></i>
                            </div>
                            <span class="hidden md:inline"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                            <i class="ri-arrow-down-s-line hidden md:inline"></i>
                        </button>
                        <div class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 z-50">
                            <a href="../profile.php" class="block px-4 py-2 hover:bg-gray-100">
                                <i class="ri-user-line mr-2"></i> Profile
                            </a>
                            <a href="../settings/settings.php" class="block px-4 py-2 hover:bg-gray-100">
                                <i class="ri-settings-line mr-2"></i> Settings
                            </a>
                            <hr class="my-1">
                            <a href="../logout.php" class="block px-4 py-2 hover:bg-gray-100 text-red-600">
                                <i class="ri-logout-box-line mr-2"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
        
        <div class="p-4 md:p-6">
            <!-- Content will be injected here -->
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Submenu toggles
                    document.querySelectorAll('.has-submenu').forEach(item => {
                        item.addEventListener('click', function(e) {
                            e.preventDefault();
                            const menuKey = this.dataset.menu;
                            const submenu = document.getElementById(`submenu-${menuKey}`);
                            const arrow = this.querySelector('.submenu-arrow');
                            
                            if (submenu.classList.contains('show')) {
                                submenu.classList.remove('show');
                                arrow.style.transform = 'rotate(0deg)';
                            } else {
                                submenu.classList.add('show');
                                arrow.style.transform = 'rotate(180deg)';
                            }
                        });
                    });
                    
                    // Sidebar toggle
                    const toggleBtn = document.getElementById('toggleSidebar');
                    const sidebar = document.getElementById('sidebar');
                    const mainContent = document.getElementById('mainContent');
                    const contentWrapper = mainContent.getElementById('content-wrapper');
                    
                    toggleBtn.addEventListener('click', function() {
                        sidebar.classList.toggle('collapsed');
                        mainContent.classList.toggle('expanded');
                        contentWrapper.classList.toggle('expanded');
                        
                        document.cookie = `sidebar_collapsed=${sidebar.classList.contains('collapsed')};path=/;max-age=${30*24*60*60}`;
                    });
                    
                    // Mobile menu toggle
                    const mobileToggle = document.getElementById('mobileMenuToggle');
                    const mobileOverlay = document.getElementById('mobileOverlay');
                    
                    mobileToggle.addEventListener('click', function() {
                        sidebar.classList.toggle('mobile-open');
                        mobileOverlay.classList.toggle('show');
                    });
                    
                    mobileOverlay.addEventListener('click', function() {
                        sidebar.classList.remove('mobile-open');
                        mobileOverlay.classList.remove('show');
                    });
                });
            </script>
        </div>
    </div>
</body>
</html>