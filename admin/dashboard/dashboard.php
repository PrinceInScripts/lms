<?php
// Error reporting for debugging (remove in production)

define('GYANSETU_APP', true);
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/functions.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
// if (session_status() === PHP_SESSION_NONE) {
//     session_start();
// }

// Check login
if (!isset($_SESSION['user_id'])) {
    redirect(BASE_URL . '/admin/login.php');
    exit();
}

// Include database connection
require_once '../../includes/db_conn.php';

// Get database connection
$db = getDB();

// Get statistics with proper error handling
$stats = [
    'total_students' => 0,
    'active_batches' => 0,
    'total_trainers' => 0,
    'pending_payments' => 0,
    'total_courses' => 0,
    'today_attendance' => 0,
    'completed_assignments' => 0,
    'upcoming_tests' => 0
];

try {
    // Total students
    $stmt = $db->query("SELECT COUNT(*) as count FROM student_details WHERE current_status = 'active'");
    $stats['total_students'] = $stmt->fetch()['count'] ?? 0;
    
    // Active batches
    $stmt = $db->query("SELECT COUNT(*) as count FROM batches WHERE status IN ('upcoming', 'ongoing')");
    $stats['active_batches'] = $stmt->fetch()['count'] ?? 0;
    
    // Total trainers
    $stmt = $db->query("SELECT COUNT(*) as count FROM trainer_details");
    $stats['total_trainers'] = $stmt->fetch()['count'] ?? 0;
    
    // Pending payments
    $stmt = $db->query("SELECT COUNT(*) as count FROM payments WHERE status = 'pending'");
    $stats['pending_payments'] = $stmt->fetch()['count'] ?? 0;
    
    // Total courses
    $stmt = $db->query("SELECT COUNT(*) as count FROM courses WHERE status = 'active'");
    $stats['total_courses'] = $stmt->fetch()['count'] ?? 0;
    
    // Today's attendance
    $stmt = $db->query("SELECT COUNT(DISTINCT student_id) as count FROM attendance WHERE attendance_date = CURDATE()");
    $stats['today_attendance'] = $stmt->fetch()['count'] ?? 0;
    
    // Completed assignments (last 30 days)
    $stmt = $db->query("SELECT COUNT(*) as count FROM assignment_submissions WHERE status = 'graded' AND submitted_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stats['completed_assignments'] = $stmt->fetch()['count'] ?? 0;
    
    // Upcoming tests
    $stmt = $db->query("SELECT COUNT(*) as count FROM tests WHERE start_date >= NOW() AND is_active = 1");
    $stats['upcoming_tests'] = $stmt->fetch()['count'] ?? 0;
    
} catch(Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
}

// Get recent students
$recent_students = [];
try {
    $stmt = $db->query("SELECT student_id, full_name, stream, enrollment_date, phone FROM student_details ORDER BY id DESC LIMIT 5");
    $recent_students = $stmt->fetchAll();
} catch(Exception $e) {
    $recent_students = [];
}

// Get upcoming schedules
$upcoming_schedules = [];
try {
    $stmt = $db->query("
        SELECT s.*, b.batch_name, b.batch_id 
        FROM schedule s 
        JOIN batches b ON s.batch_id = b.batch_id 
        WHERE s.schedule_date >= CURDATE() AND s.is_cancelled = 0
        ORDER BY s.schedule_date ASC, s.start_time ASC 
        LIMIT 5
    ");
    $upcoming_schedules = $stmt->fetchAll();
} catch(Exception $e) {
    $upcoming_schedules = [];
}

// Get recent activities
$recent_activities = [];
try {
    $stmt = $db->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 5");
    $recent_activities = $stmt->fetchAll();
} catch(Exception $e) {
    $recent_activities = [];
}

// Calculate percentages for progress bars
$attendance_percentage = $stats['total_students'] > 0 ? round(($stats['today_attendance'] / $stats['total_students']) * 100) : 0;
?>
<?php
// Extra head tags needed for dashboard
$page_title  = 'Dashboard - Guru Education System';
$extra_head  = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
include '../sidebar.php';
?>
<style>
        * { font-family: 'Inter', sans-serif; }
        
        /* Custom animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.05);
                opacity: 0.8;
            }
        }
        
        .animate-fadeInUp {
            animation: fadeInUp 0.6s ease-out;
        }
        
        .animate-slideInLeft {
            animation: slideInLeft 0.5s ease-out;
        }
        
        .stat-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s ease;
        }
        
        .stat-card:hover::before {
            left: 100%;
        }
        
        .stat-card:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.2);
        }
        
        .stat-icon {
            transition: all 0.3s ease;
        }
        
        .stat-card:hover .stat-icon {
            transform: scale(1.1) rotate(5deg);
        }
        
        .dashboard-card {
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .dashboard-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 40px -15px rgba(0, 0, 0, 0.15);
            border-color: rgba(232, 127, 36, 0.2);
        }
        
        .activity-item {
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }
        
        .activity-item:hover {
            background: #F9FAFB;
            border-left-color: #E87F24;
            transform: translateX(5px);
        }
        
        .progress-bar {
            transition: width 1s ease-in-out;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #F1F1F1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #E87F24, #FFC81E);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #E87F24;
        }
        
        /* Gradient text */
        .gradient-text {
            background: linear-gradient(135deg, #E87F24, #FFC81E);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        /* Loading spinner */
        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #E87F24;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .stat-card {
                margin-bottom: 16px;
            }
            
            .dashboard-card {
                margin-bottom: 20px;
            }
        }
    </style>
    <!-- Main Content -->
   <div class="animate-fadeInUp">
        <!-- Welcome Section -->
        <div class="mb-8 bg-gradient-to-r from-[#E87F24]/10 to-[#FFC81E]/10 rounded-2xl p-6 backdrop-blur-sm">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-12 h-12 rounded-full bg-gradient-to-r from-[#E87F24] to-[#FFC81E] flex items-center justify-center pulse-animation">
                            <i class="ri-user-smile-line text-white text-2xl"></i>
                        </div>
                        <div>
                            <h1 class="text-2xl md:text-3xl font-bold text-gray-800">
                                Welcome back, <span class="gradient-text"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>!
                            </h1>
                            <p class="text-gray-600 mt-1">Here's what's happening with your academy today.</p>
                        </div>
                    </div>
                </div>
                <div class="flex gap-3">
                    <button onclick="window.location.href='../schedule/add_schedule.php'" class="px-5 py-2.5 bg-gradient-to-r from-[#E87F24] to-[#FFC81E] text-white rounded-xl font-medium hover:shadow-lg transition-all transform hover:scale-105 flex items-center gap-2">
                        <i class="ri-add-line"></i>
                        <span>Add Schedule</span>
                    </button>
                    <button onclick="window.location.href='../student/add_student.php'" class="px-5 py-2.5 border-2 border-[#E87F24] text-[#E87F24] rounded-xl font-medium hover:bg-[#E87F24] hover:text-white transition-all flex items-center gap-2">
                        <i class="ri-user-add-line"></i>
                        <span>Add Student</span>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Stats Grid - Row 1 -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <!-- Total Students -->
            <div class="stat-card bg-white rounded-2xl p-6 cursor-pointer shadow-sm" onclick="window.location.href='../students/students.php'">
                <div class="flex items-center justify-between mb-4">
                    <div class="stat-icon w-14 h-14 rounded-xl bg-gradient-to-br from-orange-100 to-orange-50 flex items-center justify-center">
                        <i class="ri-user-star-line text-2xl text-[#E87F24]"></i>
                    </div>
                    <div class="bg-green-100 rounded-full px-2 py-1">
                        <span class="text-green-600 text-xs font-medium">+12%</span>
                    </div>
                </div>
                <div>
                    <p class="text-gray-500 text-sm mb-1">Total Students</p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo number_format($stats['total_students']); ?></p>
                    <div class="mt-3 flex items-center gap-2">
                        <div class="flex-1 bg-gray-200 rounded-full h-1.5">
                            <div class="bg-gradient-to-r from-[#E87F24] to-[#FFC81E] rounded-full h-1.5" style="width: 75%"></div>
                        </div>
                        <span class="text-xs text-gray-500">Active: 75%</span>
                    </div>
                </div>
            </div>
            
            <!-- Active Batches -->
            <div class="stat-card bg-white rounded-2xl p-6 cursor-pointer shadow-sm" onclick="window.location.href='../batch/batches.php'">
                <div class="flex items-center justify-between mb-4">
                    <div class="stat-icon w-14 h-14 rounded-xl bg-gradient-to-br from-yellow-100 to-yellow-50 flex items-center justify-center">
                        <i class="ri-group-line text-2xl text-[#FFC81E]"></i>
                    </div>
                    <div class="bg-green-100 rounded-full px-2 py-1">
                        <span class="text-green-600 text-xs font-medium">+3 new</span>
                    </div>
                </div>
                <div>
                    <p class="text-gray-500 text-sm mb-1">Active Batches</p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo number_format($stats['active_batches']); ?></p>
                    <p class="text-xs text-gray-500 mt-2">Running programs</p>
                </div>
            </div>
            
            <!-- Trainers -->
            <div class="stat-card bg-white rounded-2xl p-6 cursor-pointer shadow-sm" onclick="window.location.href='../users/trainers.php'">
                <div class="flex items-center justify-between mb-4">
                    <div class="stat-icon w-14 h-14 rounded-xl bg-gradient-to-br from-blue-100 to-blue-50 flex items-center justify-center">
                        <i class="ri-user-settings-line text-2xl text-[#73A5CA]"></i>
                    </div>
                    <div class="bg-blue-100 rounded-full px-2 py-1">
                        <span class="text-blue-600 text-xs font-medium">Faculty</span>
                    </div>
                </div>
                <div>
                    <p class="text-gray-500 text-sm mb-1">Trainers</p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo number_format($stats['total_trainers']); ?></p>
                    <p class="text-xs text-gray-500 mt-2">Active faculty members</p>
                </div>
            </div>
            
            <!-- Pending Payments -->
            <div class="stat-card bg-white rounded-2xl p-6 cursor-pointer shadow-sm" onclick="window.location.href='../payments/payments.php'">
                <div class="flex items-center justify-between mb-4">
                    <div class="stat-icon w-14 h-14 rounded-xl bg-gradient-to-br from-red-100 to-red-50 flex items-center justify-center">
                        <i class="ri-bank-card-line text-2xl text-red-500"></i>
                    </div>
                    <div class="bg-orange-100 rounded-full px-2 py-1">
                        <span class="text-orange-600 text-xs font-medium">Pending</span>
                    </div>
                </div>
                <div>
                    <p class="text-gray-500 text-sm mb-1">Pending Payments</p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo number_format($stats['pending_payments']); ?></p>
                    <p class="text-xs text-orange-600 mt-2">Need verification</p>
                </div>
            </div>
        </div>
        
        <!-- Stats Grid - Row 2 -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Courses -->
            <div class="stat-card bg-white rounded-2xl p-6 cursor-pointer shadow-sm" onclick="window.location.href='../batch/batches.php'">
                <div class="flex items-center justify-between mb-4">
                    <div class="stat-icon w-14 h-14 rounded-xl bg-gradient-to-br from-purple-100 to-purple-50 flex items-center justify-center">
                        <i class="ri-book-open-line text-2xl text-purple-500"></i>
                    </div>
                </div>
                <div>
                    <p class="text-gray-500 text-sm mb-1">Total Courses</p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo number_format($stats['total_courses']); ?></p>
                    <p class="text-xs text-gray-500 mt-2">Active courses</p>
                </div>
            </div>
            
            <!-- Today's Attendance -->
            <div class="stat-card bg-white rounded-2xl p-6 cursor-pointer shadow-sm" onclick="window.location.href='../attendance/attendance.php'">
                <div class="flex items-center justify-between mb-4">
                    <div class="stat-icon w-14 h-14 rounded-xl bg-gradient-to-br from-green-100 to-green-50 flex items-center justify-center">
                        <i class="ri-checkbox-circle-line text-2xl text-green-500"></i>
                    </div>
                </div>
                <div>
                    <p class="text-gray-500 text-sm mb-1">Today's Attendance</p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo number_format($stats['today_attendance']); ?></p>
                    <div class="mt-3 flex items-center gap-2">
                        <div class="flex-1 bg-gray-200 rounded-full h-1.5">
                            <div class="bg-gradient-to-r from-green-500 to-green-400 rounded-full h-1.5 progress-bar" style="width: <?php echo $attendance_percentage; ?>%"></div>
                        </div>
                        <span class="text-xs text-gray-500"><?php echo $attendance_percentage; ?>%</span>
                    </div>
                </div>
            </div>
            
            <!-- Completed Assignments -->
            <div class="stat-card bg-white rounded-2xl p-6 cursor-pointer shadow-sm" onclick="window.location.href='../assignments/assignments.php'">
                <div class="flex items-center justify-between mb-4">
                    <div class="stat-icon w-14 h-14 rounded-xl bg-gradient-to-br from-indigo-100 to-indigo-50 flex items-center justify-center">
                        <i class="ri-task-line text-2xl text-indigo-500"></i>
                    </div>
                </div>
                <div>
                    <p class="text-gray-500 text-sm mb-1">Assignments Completed</p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo number_format($stats['completed_assignments']); ?></p>
                    <p class="text-xs text-gray-500 mt-2">Last 30 days</p>
                </div>
            </div>
            
            <!-- Upcoming Tests -->
            <div class="stat-card bg-white rounded-2xl p-6 cursor-pointer shadow-sm" onclick="window.location.href='../tests/test.php'">
                <div class="flex items-center justify-between mb-4">
                    <div class="stat-icon w-14 h-14 rounded-xl bg-gradient-to-br from-pink-100 to-pink-50 flex items-center justify-center">
                        <i class="ri-questionnaire-line text-2xl text-pink-500"></i>
                    </div>
                </div>
                <div>
                    <p class="text-gray-500 text-sm mb-1">Upcoming Tests</p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo number_format($stats['upcoming_tests']); ?></p>
                    <p class="text-xs text-gray-500 mt-2">Scheduled this week</p>
                </div>
            </div>
        </div>
        
        <!-- Charts and Analytics Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Enrollment Chart -->
            <div class="dashboard-card bg-white rounded-2xl p-6 shadow-sm">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                            <i class="ri-bar-chart-2-line text-[#E87F24]"></i>
                            Student Enrollment Trend
                        </h3>
                        <p class="text-sm text-gray-500 mt-1">Monthly enrollment statistics</p>
                    </div>
                    <select class="px-3 py-1.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-[#E87F24] bg-white">
                        <option>2024</option>
                        <option>2023</option>
                        <option>2022</option>
                    </select>
                </div>
                <canvas id="enrollmentChart" height="280"></canvas>
            </div>
            
            <!-- Revenue Chart -->
            <div class="dashboard-card bg-white rounded-2xl p-6 shadow-sm">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                            <i class="ri-money-rupee-circle-line text-[#FFC81E]"></i>
                            Revenue Overview
                        </h3>
                        <p class="text-sm text-gray-500 mt-1">Monthly revenue collection</p>
                    </div>
                    <div class="flex gap-2">
                        <button class="px-3 py-1 text-xs bg-[#E87F24] text-white rounded-lg">Month</button>
                        <button class="px-3 py-1 text-xs bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200">Year</button>
                    </div>
                </div>
                <canvas id="revenueChart" height="280"></canvas>
            </div>
        </div>
        
        <!-- Bottom Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Recent Students -->
            <div class="dashboard-card bg-white rounded-2xl p-6 shadow-sm lg:col-span-1">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                        <i class="ri-user-star-line text-[#E87F24]"></i>
                        Recent Students
                    </h3>
                    <a href="../students/students.php" class="text-sm text-[#E87F24] hover:underline flex items-center gap-1">
                        View All <i class="ri-arrow-right-line"></i>
                    </a>
                </div>
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    <?php if (empty($recent_students)): ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="ri-user-line text-4xl mb-2 block"></i>
                            <p>No students found</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_students as $student): ?>
                            <div class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition cursor-pointer" onclick="window.location.href='../student/view_student.php?id=<?php echo $student['student_id']; ?>'">
                                <div class="w-12 h-12 rounded-full bg-gradient-to-r from-[#E87F24] to-[#FFC81E] flex items-center justify-center text-white font-bold">
                                    <?php echo strtoupper(substr($student['full_name'], 0, 2)); ?>
                                </div>
                                <div class="flex-1">
                                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($student['full_name']); ?></p>
                                    <p class="text-xs text-gray-500">ID: <?php echo htmlspecialchars($student['student_id']); ?></p>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-xs px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full"><?php echo htmlspecialchars($student['stream']); ?></span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-gray-500"><?php echo date('d M Y', strtotime($student['enrollment_date'])); ?></p>
                                    <?php if ($student['phone']): ?>
                                        <p class="text-xs text-gray-400 mt-1">📞 <?php echo htmlspecialchars($student['phone']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Upcoming Schedule -->
            <div class="dashboard-card bg-white rounded-2xl p-6 shadow-sm lg:col-span-1">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                        <i class="ri-calendar-line text-[#FFC81E]"></i>
                        Upcoming Schedule
                    </h3>
                    <a href="../schedule/schedule.php" class="text-sm text-[#E87F24] hover:underline flex items-center gap-1">
                        View All <i class="ri-arrow-right-line"></i>
                    </a>
                </div>
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    <?php if (empty($upcoming_schedules)): ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="ri-calendar-line text-4xl mb-2 block"></i>
                            <p>No upcoming schedules</p>
                            <a href="../schedule/add_schedule.php" class="text-sm text-[#E87F24] hover:underline mt-2 inline-block">Create Schedule →</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($upcoming_schedules as $schedule): ?>
                            <div class="flex items-start gap-3 p-3 rounded-xl hover:bg-gray-50 transition cursor-pointer" onclick="window.location.href='../schedule/view_schedule.php?id=<?php echo $schedule['id']; ?>'">
                                <div class="flex-shrink-0 text-center min-w-[60px]">
                                    <div class="bg-gradient-to-br from-[#E87F24] to-[#FFC81E] text-white rounded-xl p-2">
                                        <div class="text-xl font-bold"><?php echo date('d', strtotime($schedule['schedule_date'])); ?></div>
                                        <div class="text-xs"><?php echo date('M', strtotime($schedule['schedule_date'])); ?></div>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-semibold text-gray-800"><?php echo htmlspecialchars($schedule['topic']); ?></h4>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($schedule['batch_name']); ?></p>
                                    <div class="flex items-center gap-3 mt-2">
                                        <span class="text-xs text-gray-500 flex items-center gap-1">
                                            <i class="ri-time-line"></i> <?php echo date('h:i A', strtotime($schedule['start_time'])); ?>
                                        </span>
                                        <span class="text-xs text-gray-500 flex items-center gap-1">
                                            <i class="ri-map-pin-line"></i> Online
                                        </span>
                                    </div>
                                </div>
                                <i class="ri-arrow-right-s-line text-gray-400 text-xl"></i>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Activities -->
            <div class="dashboard-card bg-white rounded-2xl p-6 shadow-sm lg:col-span-1">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
                        <i class="ri-history-line text-[#73A5CA]"></i>
                        Recent Activities
                    </h3>
                    <button onclick="location.reload()" class="text-sm text-gray-500 hover:text-[#E87F24]">
                        <i class="ri-refresh-line"></i>
                    </button>
                </div>
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    <?php if (empty($recent_activities)): ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="ri-inbox-line text-4xl mb-2 block"></i>
                            <p>No recent activities</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="activity-item p-3 rounded-lg transition">
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-r from-[#E87F24] to-[#FFC81E] flex items-center justify-center flex-shrink-0">
                                        <i class="ri-user-line text-white text-sm"></i>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-sm text-gray-800">
                                            <span class="font-semibold"><?php echo htmlspecialchars($activity['username'] ?? 'System'); ?></span>
                                            <span class="text-gray-600"> <?php echo htmlspecialchars($activity['action'] ?? 'performed an action'); ?></span>
                                        </p>
                                        <?php if (!empty($activity['description'])): ?>
                                            <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($activity['description']); ?></p>
                                        <?php endif; ?>
                                        <p class="text-xs text-gray-400 mt-1 flex items-center gap-1">
                                            <i class="ri-time-line"></i> 
                                            <?php echo date('d M Y, h:i A', strtotime($activity['created_at'])); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Enrollment Chart
        const enrollmentCtx = document.getElementById('enrollmentChart').getContext('2d');
        new Chart(enrollmentCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'New Enrollments',
                    data: [45, 52, 68, 75, 82, 95, 108, 115, 122, 130, 138, 145],
                    borderColor: '#E87F24',
                    backgroundColor: 'rgba(232, 127, 36, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#E87F24',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 10
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#E87F24',
                        borderWidth: 2,
                        callbacks: {
                            label: function(context) {
                                return `Enrollments: ${context.raw}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#e5e7eb',
                            drawBorder: false
                        },
                        title: {
                            display: true,
                            text: 'Number of Students',
                            color: '#6b7280'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });
        
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [
                    {
                        label: 'Revenue (₹)',
                        data: [125000, 135000, 148000, 162000, 175000, 189000, 205000, 218000, 232000, 245000, 258000, 275000],
                        backgroundColor: '#E87F24',
                        borderRadius: 8,
                        barPercentage: 0.6,
                        categoryPercentage: 0.8
                    },
                    {
                        label: 'Expenses (₹)',
                        data: [85000, 88000, 92000, 95000, 98000, 102000, 105000, 108000, 112000, 115000, 118000, 122000],
                        backgroundColor: '#FFC81E',
                        borderRadius: 8,
                        barPercentage: 0.6,
                        categoryPercentage: 0.8
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 10,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        callbacks: {
                            label: function(context) {
                                return `${context.dataset.label}: ₹${context.raw.toLocaleString()}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#e5e7eb'
                        },
                        ticks: {
                            callback: function(value) {
                                return '₹' + value.toLocaleString();
                            }
                        },
                        title: {
                            display: true,
                            text: 'Amount (₹)',
                            color: '#6b7280'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        
        // Animate progress bars on load
        document.addEventListener('DOMContentLoaded', function() {
            const progressBars = document.querySelectorAll('.progress-bar');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 100);
            });
            
            // Add loading animation for stats
            const statNumbers = document.querySelectorAll('.stat-card .text-3xl');
            statNumbers.forEach(el => {
                const finalValue = parseInt(el.innerText);
                if (!isNaN(finalValue)) {
                    let currentValue = 0;
                    const increment = finalValue / 30;
                    const timer = setInterval(() => {
                        currentValue += increment;
                        if (currentValue >= finalValue) {
                            el.innerText = finalValue.toLocaleString();
                            clearInterval(timer);
                        } else {
                            el.innerText = Math.floor(currentValue).toLocaleString();
                        }
                    }, 30);
                }
            });
        });
        
        // Auto-refresh dashboard every 60 seconds (optional)
        let autoRefresh = true;
        if (autoRefresh) {
            setInterval(function() {
                // Only refresh if page is visible
                if (!document.hidden) {
                    location.reload();
                }
            }, 60000);
        }
        
        // Handle page visibility
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                console.log('Dashboard visible, refreshing data...');
            }
        });
    </script>
    </div><!-- /.animate-fadeInUp -->
    </div><!-- /.content-area (sidebar) -->
</div><!-- /.page-wrapper (sidebar) -->
</body>
</html>