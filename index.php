<?php
/**
 * GyanSetu - Bilingual LMS Landing Page
 * Cybersecurity Course Provider
 * Color Scheme: #E87F24 (Orange), #FFC81E (Yellow), #FEFDDF (Cream), #73A5CA (Blue)
 */

// Error reporting for development (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define secure constant
define('GYANSETU_SECURE', true);

// Include required files
require_once __DIR__ . '/includes/db_conn.php';
// require_once __DIR__ . '/includes/functions.php';

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'use_strict_mode' => true,
        'cookie_samesite' => 'Strict',
    ]);
}

// Check if admin exists, if not create one
function ensureAdminExists() {
    try {
        $db = getDB();
        
        // Check if users table exists
        $stmt = $db->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() == 0) {
            // Create users table
            $createTable = "
            CREATE TABLE IF NOT EXISTS `users` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `username` varchar(100) NOT NULL,
                `email` varchar(100) NOT NULL,
                `password_hash` varchar(255) NOT NULL,
                `role` enum('super_admin','admin','trainer','student','sales','accounts') NOT NULL DEFAULT 'student',
                `full_name` varchar(150) DEFAULT NULL,
                `phone` varchar(20) DEFAULT NULL,
                `status` enum('active','inactive','suspended') DEFAULT 'active',
                `account_locked` tinyint(1) NOT NULL DEFAULT 0,
                `failed_login_attempts` int(11) NOT NULL DEFAULT 0,
                `last_login` datetime DEFAULT NULL,
                `last_ip` varchar(45) DEFAULT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `email` (`email`),
                UNIQUE KEY `username` (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $db->exec($createTable);
        }
        
        // Check if any admin exists
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role IN ('super_admin', 'admin')");
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            // Create default admin
            $username = 'Admin';
            $email = 'admin@gyansetu.com';
            $password_hash = password_hash('Admin123', PASSWORD_DEFAULT);
            $full_name = 'Super Administrator';
            
            $insertStmt = $db->prepare("INSERT INTO users (username, email, password_hash, role, full_name, status) VALUES (:username, :email, :password_hash, 'super_admin', :full_name, 'active')");
            $insertStmt->execute([
                ':username' => $username,
                ':email' => $email,
                ':password_hash' => $password_hash,
                ':full_name' => $full_name
            ]);
            
            return true;
        }
        return false;
    } catch (Exception $e) {
        error_log("Admin creation error: " . $e->getMessage());
        return false;
    }
}

// Run admin check
$adminCreated = ensureAdminExists();

// Language handling (Bilingual)
$lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'hi'; // hi for Hindi, en for English
$_SESSION['lang'] = $lang;

// Translations
$translations = [
    'en' => [
        'site_title' => 'GyanSetu - Cybersecurity Training Platform',
        'nav_home' => 'Home',
        'nav_courses' => 'Courses',
        'nav_about' => 'About',
        'nav_contact' => 'Contact',
        'nav_login' => 'Login',
        'hero_badge' => 'India\'s Leading Cybersecurity Training Platform',
        'hero_title' => 'Master Cybersecurity Skills',
        'hero_title_highlight' => 'From Basics to Advanced',
        'hero_desc' => 'Join GyanSetu\'s comprehensive cybersecurity program. Learn ethical hacking, network security, cryptography, and more from industry experts.',
        'hero_cta' => 'Enroll Now',
        'hero_cta_secondary' => 'View Courses',
        'stats_students' => 'Active Students',
        'stats_courses' => 'Expert Courses',
        'stats_placements' => 'Placement Rate',
        'stats_experts' => 'Industry Experts',
        'features_title' => 'Why Choose GyanSetu?',
        'features_subtitle' => 'World-class cybersecurity education with practical training',
        'feature_1_title' => 'Industry Expert Trainers',
        'feature_1_desc' => 'Learn from certified professionals with real-world experience',
        'feature_2_title' => 'Hands-on Labs',
        'feature_2_desc' => 'Practice in our virtual labs with real cybersecurity scenarios',
        'feature_3_title' => 'Placement Assistance',
        'feature_3_desc' => '100% placement assistance with top tech companies',
        'feature_4_title' => 'Certification',
        'feature_4_desc' => 'Get industry-recognized certificates upon completion',
        'courses_title' => 'Our Cybersecurity Courses',
        'courses_subtitle' => 'Comprehensive programs designed for career success',
        'course_1_title' => 'Ethical Hacking',
        'course_1_desc' => 'Learn penetration testing, vulnerability assessment, and ethical hacking techniques',
        'course_1_duration' => '6 Months',
        'course_1_level' => 'Beginner to Advanced',
        'course_2_title' => 'Network Security',
        'course_2_desc' => 'Master network defense, firewalls, IDS/IPS, and secure network design',
        'course_2_duration' => '4 Months',
        'course_2_level' => 'Intermediate',
        'course_3_title' => 'Cyber Forensics',
        'course_3_desc' => 'Digital forensics, incident response, and evidence collection techniques',
        'course_3_duration' => '5 Months',
        'course_3_level' => 'Advanced',
        'course_4_title' => 'Cloud Security',
        'course_4_desc' => 'Secure cloud infrastructure on AWS, Azure, and Google Cloud',
        'course_4_duration' => '4 Months',
        'course_4_level' => 'Intermediate',
        'btn_enroll' => 'Enroll Now',
        'btn_details' => 'View Details',
        'testimonials_title' => 'What Our Students Say',
        'testimonials_subtitle' => 'Success stories from our alumni',
        'testimonial_1_name' => 'Rahul Sharma',
        'testimonial_1_role' => 'Security Analyst at TechMahindra',
        'testimonial_1_text' => 'The practical approach and expert guidance helped me transition into cybersecurity. Highly recommended!',
        'testimonial_2_name' => 'Priya Patel',
        'testimonial_2_role' => 'Penetration Tester',
        'testimonial_2_text' => 'Best decision I made for my career. The hands-on labs were incredibly valuable.',
        'testimonial_3_name' => 'Amit Kumar',
        'testimonial_3_role' => 'Security Consultant',
        'testimonial_3_text' => 'GyanSetu\'s curriculum is up-to-date with industry standards. Got placed immediately after course.',
        'cta_title' => 'Ready to Start Your Cybersecurity Journey?',
        'cta_desc' => 'Join thousands of successful cybersecurity professionals who started with GyanSetu',
        'cta_button' => 'Get Started Today',
        'footer_about' => 'GyanSetu is India\'s premier cybersecurity training platform, dedicated to building the next generation of security professionals.',
        'footer_quick_links' => 'Quick Links',
        'footer_contact_us' => 'Contact Us',
        'footer_follow_us' => 'Follow Us',
        'footer_copyright' => '© 2024 GyanSetu. All rights reserved.',
        'footer_privacy' => 'Privacy Policy',
        'footer_terms' => 'Terms of Service',
        'login_modal_title' => 'Login to GyanSetu',
        'login_username' => 'Username or Email',
        'login_password' => 'Password',
        'login_remember' => 'Remember Me',
        'login_forgot' => 'Forgot Password?',
        'login_button' => 'Login',
        'login_error' => 'Invalid credentials. Please try again.',
        'admin_created_msg' => 'Admin account created! Username: Admin, Password: Admin123',
    ],
    'hi' => [
        'site_title' => 'ज्ञानसेतु - साइबर सुरक्षा प्रशिक्षण मंच',
        'nav_home' => 'होम',
        'nav_courses' => 'पाठ्यक्रम',
        'nav_about' => 'हमारे बारे में',
        'nav_contact' => 'संपर्क करें',
        'nav_login' => 'लॉगिन',
        'hero_badge' => 'भारत का अग्रणी साइबर सुरक्षा प्रशिक्षण मंच',
        'hero_title' => 'साइबर सुरक्षा कौशल में',
        'hero_title_highlight' => 'महारत हासिल करें',
        'hero_desc' => 'ज्ञानसेतु के व्यापक साइबर सुरक्षा कार्यक्रम से जुड़ें। उद्योग विशेषज्ञों से एथिकल हैकिंग, नेटवर्क सुरक्षा, क्रिप्टोग्राफी और अधिक सीखें।',
        'hero_cta' => 'अभी दाखिला लें',
        'hero_cta_secondary' => 'पाठ्यक्रम देखें',
        'stats_students' => 'सक्रिय छात्र',
        'stats_courses' => 'विशेषज्ञ पाठ्यक्रम',
        'stats_placements' => 'प्लेसमेंट दर',
        'stats_experts' => 'उद्योग विशेषज्ञ',
        'features_title' => 'ज्ञानसेतु क्यों चुनें?',
        'features_subtitle' => 'व्यावहारिक प्रशिक्षण के साथ विश्व स्तरीय साइबर सुरक्षा शिक्षा',
        'feature_1_title' => 'उद्योग विशेषज्ञ प्रशिक्षक',
        'feature_1_desc' => 'वास्तविक दुनिया के अनुभव वाले प्रमाणित पेशेवरों से सीखें',
        'feature_2_title' => 'हैंड्स-ऑन लैब्स',
        'feature_2_desc' => 'हमारे वर्चुअल लैब्स में वास्तविक साइबर सुरक्षा परिदृश्यों के साथ अभ्यास करें',
        'feature_3_title' => 'प्लेसमेंट सहायता',
        'feature_3_desc' => 'शीर्ष टेक कंपनियों के साथ 100% प्लेसमेंट सहायता',
        'feature_4_title' => 'प्रमाणन',
        'feature_4_desc' => 'पूरा होने पर उद्योग-मान्यता प्राप्त प्रमाणपत्र प्राप्त करें',
        'courses_title' => 'हमारे साइबर सुरक्षा पाठ्यक्रम',
        'courses_subtitle' => 'करियर सफलता के लिए डिज़ाइन किए गए व्यापक कार्यक्रम',
        'course_1_title' => 'एथिकल हैकिंग',
        'course_1_desc' => 'पenetration टेस्टिंग, वल्नरेबिलिटी असेसमेंट और एथिकल हैकिंग तकनीक सीखें',
        'course_1_duration' => '6 महीने',
        'course_1_level' => 'शुरुआती से उन्नत',
        'course_2_title' => 'नेटवर्क सुरक्षा',
        'course_2_desc' => 'नेटवर्क डिफेंस, फायरवॉल, IDS/IPS और सुरक्षित नेटवर्क डिजाइन में महारत हासिल करें',
        'course_2_duration' => '4 महीने',
        'course_2_level' => 'इंटरमीडिएट',
        'course_3_title' => 'साइबर फोरेंसिक्स',
        'course_3_desc' => 'डिजिटल फोरेंसिक्स, इंसीडेंट रिस्पॉन्स और साक्ष्य संग्रह तकनीक',
        'course_3_duration' => '5 महीने',
        'course_3_level' => 'उन्नत',
        'course_4_title' => 'क्लाउड सुरक्षा',
        'course_4_desc' => 'AWS, Azure और Google Cloud पर सुरक्षित क्लाउड इंफ्रास्ट्रक्चर',
        'course_4_duration' => '4 महीने',
        'course_4_level' => 'इंटरमीडिएट',
        'btn_enroll' => 'अभी दाखिला लें',
        'btn_details' => 'विवरण देखें',
        'testimonials_title' => 'हमारे छात्र क्या कहते हैं',
        'testimonials_subtitle' => 'हमारे पूर्व छात्रों की सफलता की कहानियां',
        'testimonial_1_name' => 'राहुल शर्मा',
        'testimonial_1_role' => 'टेकमहिंद्रा में सुरक्षा विश्लेषक',
        'testimonial_1_text' => 'व्यावहारिक दृष्टिकोण और विशेषज्ञ मार्गदर्शन ने मुझे साइबर सुरक्षा में संक्रमण करने में मदद की। अत्यधिक अनुशंसित!',
        'testimonial_2_name' => 'प्रिया पटेल',
        'testimonial_2_role' => 'पenetration टेस्टर',
        'testimonial_2_text' => 'अपने करियर के लिए मेरा सबसे अच्छा निर्णय। हैंड्स-ऑन लैब्स अविश्वसनीय रूप से मूल्यवान थे।',
        'testimonial_3_name' => 'अमित कुमार',
        'testimonial_3_role' => 'सुरक्षा सलाहकार',
        'testimonial_3_text' => 'ज्ञानसेतु का पाठ्यक्रम उद्योग मानकों के अनुसार अद्यतित है। पाठ्यक्रम के तुरंत बाद प्लेसमेंट मिला।',
        'cta_title' => 'अपनी साइबर सुरक्षा यात्रा शुरू करने के लिए तैयार हैं?',
        'cta_desc' => 'हजारों सफल साइबर सुरक्षा पेशेवरों से जुड़ें जिन्होंने ज्ञानसेतु से शुरुआत की',
        'cta_button' => 'आज ही शुरू करें',
        'footer_about' => 'ज्ञानसेतु भारत का प्रमुख साइबर सुरक्षा प्रशिक्षण मंच है, जो सुरक्षा पेशेवरों की अगली पीढ़ी के निर्माण के लिए समर्पित है।',
        'footer_quick_links' => 'त्वरित लिंक',
        'footer_contact_us' => 'संपर्क करें',
        'footer_follow_us' => 'हमें फॉलो करें',
        'footer_copyright' => '© 2024 ज्ञानसेतु। सर्वाधिकार सुरक्षित।',
        'footer_privacy' => 'गोपनीयता नीति',
        'footer_terms' => 'सेवा की शर्तें',
        'login_modal_title' => 'ज्ञानसेतु में लॉगिन करें',
        'login_username' => 'उपयोगकर्ता नाम या ईमेल',
        'login_password' => 'पासवर्ड',
        'login_remember' => 'मुझे याद रखें',
        'login_forgot' => 'पासवर्ड भूल गए?',
        'login_button' => 'लॉगिन',
        'login_error' => 'अमान्य क्रेडेंशियल्स। कृपया पुनः प्रयास करें।',
        'admin_created_msg' => 'एडमिन खाता बनाया गया! उपयोगकर्ता नाम: Admin, पासवर्ड: Admin123',
    ]
];

$t = $translations[$lang];

// Handle login AJAX request
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_action'])) {
    try {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (!empty($username) && !empty($password)) {
            $db = getDB();
            $stmt = $db->prepare("SELECT id, username, email, password_hash, role, status, account_locked FROM users WHERE username = :username OR email = :email");
            $stmt->execute([':username' => $username, ':email' => $username]);
            $user = $stmt->fetch();
            
            if ($user && $user['status'] === 'active' && $user['account_locked'] == 0) {
                if (password_verify($password, $user['password_hash'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Update last login
                    $updateStmt = $db->prepare("UPDATE users SET last_login = NOW(), last_ip = :ip WHERE id = :id");
                    $updateStmt->execute([':ip' => $_SERVER['REMOTE_ADDR'] ?? '', ':id' => $user['id']]);
                    
                    // Return success for AJAX
                    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                        echo json_encode(['success' => true, 'redirect' => 'admin/dashboard/dashboard']);
                        exit();
                    } else {
                        header('Location: admin/dashboard/dashboard.php');
                        exit();
                    }
                }
            }
            $login_error = $t['login_error'];
        }
    } catch (Exception $e) {
        $login_error = $t['login_error'];
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <meta name="description" content="GyanSetu - India's Leading Cybersecurity Training Platform. Learn ethical hacking, network security, and cyber forensics from industry experts.">
    <meta name="keywords" content="cybersecurity, ethical hacking, network security, cyber forensics, cloud security, training, certification">
    <meta name="author" content="GyanSetu">
    <title><?php echo $t['site_title']; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🛡️</text></svg>">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Custom Tailwind Configuration -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary': '#E87F24',
                        'secondary': '#FFC81E',
                        'accent': '#73A5CA',
                        'bg-light': '#FEFDDF',
                    },
                    animation: {
                        'float': 'float 3s ease-in-out infinite',
                        'slide-up': 'slideUp 0.5s ease-out',
                        'slide-down': 'slideDown 0.3s ease-out',
                        'fade-in': 'fadeIn 0.8s ease-out',
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0px)' },
                            '50%': { transform: 'translateY(-20px)' },
                        },
                        slideUp: {
                            from: { opacity: '0', transform: 'translateY(30px)' },
                            to: { opacity: '1', transform: 'translateY(0)' },
                        },
                        slideDown: {
                            from: { opacity: '0', transform: 'translateY(-20px)' },
                            to: { opacity: '1', transform: 'translateY(0)' },
                        },
                        fadeIn: {
                            from: { opacity: '0' },
                            to: { opacity: '1' },
                        },
                    },
                    fontFamily: {
                        'sans': ['Poppins', 'system-ui', 'sans-serif'],
                        'hindi': ['Noto Sans Devanagari', 'Poppins', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        /* Custom Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            overflow-x: hidden;
            background: #FEFDDF;
        }
        
        /* Hindi font support */
        .lang-hi, [lang="hi"] {
            font-family: 'Noto Sans Devanagari', 'Poppins', sans-serif;
        }
        
        /* Skeuomorphic Elements */
        .skeuomorphic-card {
            background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 24px;
            box-shadow: 20px 20px 40px rgba(0, 0, 0, 0.08),
                        -10px -10px 20px rgba(255, 255, 255, 0.7),
                        0px 0px 0px 1px rgba(255, 255, 255, 0.3) inset;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .skeuomorphic-card:hover {
            transform: translateY(-8px);
            box-shadow: 25px 25px 50px rgba(0, 0, 0, 0.12),
                        -12px -12px 25px rgba(255, 255, 255, 0.8);
        }
        
        .skeuomorphic-button {
            background: linear-gradient(135deg, #E87F24 0%, #FFC81E 100%);
            border: none;
            border-radius: 50px;
            box-shadow: 0px 8px 20px rgba(232, 127, 36, 0.3),
                        0px 2px 0px 0px rgba(255, 255, 255, 0.5) inset;
            transition: all 0.3s ease;
        }
        
        .skeuomorphic-button:hover {
            transform: translateY(-2px);
            box-shadow: 0px 12px 28px rgba(232, 127, 36, 0.4);
        }
        
        .skeuomorphic-button:active {
            transform: translateY(1px);
        }
        
        .skeuomorphic-input {
            background: #ffffff;
            border: 2px solid #e9ecef;
            border-radius: 16px;
            box-shadow: inset 2px 2px 5px rgba(0, 0, 0, 0.05),
                        0px 1px 0px 0px rgba(255, 255, 255, 0.8) inset;
            transition: all 0.3s ease;
        }
        
        .skeuomorphic-input:focus {
            border-color: #E87F24;
            box-shadow: inset 2px 2px 5px rgba(0, 0, 0, 0.05),
                        0px 0px 0px 3px rgba(232, 127, 36, 0.1);
            outline: none;
        }
        
        /* Gradient Text */
        .gradient-text {
            background: linear-gradient(135deg, #E87F24 0%, #FFC81E 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
        }
        
        ::-webkit-scrollbar-track {
            background: #FEFDDF;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #E87F24, #FFC81E);
            border-radius: 10px;
        }
        
        /* Modal Animation */
        .modal-enter {
            animation: slideDown 0.3s ease-out;
        }
        
        /* Language Switcher */
        .lang-switch {
            position: relative;
            display: inline-block;
        }
        
        .lang-btn {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border-radius: 30px;
            padding: 8px 16px;
            transition: all 0.3s ease;
        }
        
        .lang-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        /* Loading Spinner */
        .loading-spinner {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #FEFDDF;
            border-top: 4px solid #E87F24;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Stats Counter Animation */
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #E87F24, #FFC81E);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        /* Glassmorphism Effect */
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .stat-number {
                font-size: 1.8rem;
            }
            
            .skeuomorphic-card {
                margin: 10px;
            }
        }
    </style>
</head>
<body class="bg-bg-light" data-aos-easing="ease-out" data-aos-duration="800">
    
    <!-- Loading Spinner -->
    <div class="loading-spinner" id="loadingSpinner">
        <div class="spinner"></div>
    </div>
    
    <!-- Admin Created Notification -->
    <?php if ($adminCreated): ?>
    <div class="fixed top-20 right-4 z-50 animate-slide-down" id="adminAlert">
        <div class="bg-green-500 text-white px-6 py-4 rounded-xl shadow-lg flex items-center gap-3">
            <i class="fas fa-check-circle text-xl"></i>
            <span><?php echo $t['admin_created_msg']; ?></span>
            <button onclick="document.getElementById('adminAlert').remove()" class="ml-4 text-white hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Navigation -->
    <nav class="fixed top-0 left-0 right-0 z-50 bg-white/95 backdrop-blur-md shadow-lg transition-all duration-300">
        <div class="container mx-auto px-4 md:px-6 py-3">
            <div class="flex items-center justify-between">
                <!-- Logo -->
                <div class="flex items-center gap-2 group cursor-pointer" onclick="window.location.href='#'">
                    <div class="w-10 h-10 bg-gradient-to-br from-primary to-secondary rounded-xl flex items-center justify-center shadow-lg group-hover:scale-110 transition-transform">
                        <i class="fas fa-shield-alt text-white text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold gradient-text">GyanSetu</h1>
                        <p class="text-xs text-gray-500"><?php echo $lang === 'hi' ? 'साइबर सुरक्षा प्रशिक्षण' : 'Cybersecurity Training'; ?></p>
                    </div>
                </div>
                
                <!-- Desktop Menu -->
                <div class="hidden md:flex items-center gap-8">
                    <a href="#" class="text-gray-700 hover:text-primary transition-colors font-medium"><?php echo $t['nav_home']; ?></a>
                    <a href="#courses" class="text-gray-700 hover:text-primary transition-colors font-medium"><?php echo $t['nav_courses']; ?></a>
                    <a href="#about" class="text-gray-700 hover:text-primary transition-colors font-medium"><?php echo $t['nav_about']; ?></a>
                    <a href="#contact" class="text-gray-700 hover:text-primary transition-colors font-medium"><?php echo $t['nav_contact']; ?></a>
                </div>
                
                <!-- Right Section -->
                <div class="flex items-center gap-3">
                    <!-- Language Switcher -->
                    <div class="lang-switch">
                        <button onclick="toggleLanguage()" class="lang-btn flex items-center gap-2 text-gray-700">
                            <i class="fas fa-globe"></i>
                            <span><?php echo $lang === 'hi' ? 'EN' : 'हिंदी'; ?></span>
                        </button>
                    </div>
                    
                    <!-- Login Button -->
                    <button onclick="openLoginModal()" class="skeuomorphic-button px-5 py-2 text-white font-semibold rounded-full flex items-center gap-2">
                        <i class="fas fa-sign-in-alt"></i>
                        <span><?php echo $t['nav_login']; ?></span>
                    </button>
                    
                    <!-- Mobile Menu Button -->
                    <button id="mobileMenuBtn" class="md:hidden text-2xl text-gray-700">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>
            
            <!-- Mobile Menu -->
            <div id="mobileMenu" class="hidden md:hidden mt-4 pb-4 animate-slide-down">
                <div class="flex flex-col gap-3">
                    <a href="#" class="text-gray-700 hover:text-primary transition-colors py-2"><?php echo $t['nav_home']; ?></a>
                    <a href="#courses" class="text-gray-700 hover:text-primary transition-colors py-2"><?php echo $t['nav_courses']; ?></a>
                    <a href="#about" class="text-gray-700 hover:text-primary transition-colors py-2"><?php echo $t['nav_about']; ?></a>
                    <a href="#contact" class="text-gray-700 hover:text-primary transition-colors py-2"><?php echo $t['nav_contact']; ?></a>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Hero Section -->
    <section class="pt-32 pb-20 px-4 relative overflow-hidden">
        <!-- Background Graphics -->
        <div class="absolute top-0 right-0 w-96 h-96 bg-primary/5 rounded-full blur-3xl -z-10 animate-float"></div>
        <div class="absolute bottom-0 left-0 w-80 h-80 bg-secondary/5 rounded-full blur-3xl -z-10 animate-float" style="animation-delay: 1s;"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full h-full bg-gradient-radial from-accent/5 via-transparent to-transparent -z-10"></div>
        
        <div class="container mx-auto max-w-6xl">
            <div class="grid md:grid-cols-2 gap-12 items-center">
                <div data-aos="fade-right">
                    <div class="inline-flex items-center gap-2 bg-primary/10 px-4 py-2 rounded-full mb-6">
                        <i class="fas fa-certificate text-primary text-sm"></i>
                        <span class="text-primary text-sm font-semibold"><?php echo $t['hero_badge']; ?></span>
                    </div>
                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold leading-tight mb-4">
                        <?php echo $t['hero_title']; ?> 
                        <span class="gradient-text"><?php echo $t['hero_title_highlight']; ?></span>
                    </h1>
                    <p class="text-gray-600 text-lg mb-8 leading-relaxed">
                        <?php echo $t['hero_desc']; ?>
                    </p>
                    <div class="flex flex-wrap gap-4">
                        <button onclick="scrollToCourses()" class="skeuomorphic-button px-8 py-3 text-white font-semibold rounded-full flex items-center gap-2">
                            <i class="fas fa-graduation-cap"></i>
                            <span><?php echo $t['hero_cta']; ?></span>
                        </button>
                        <button onclick="scrollToCourses()" class="bg-white border-2 border-primary/30 hover:border-primary text-primary px-8 py-3 font-semibold rounded-full transition-all">
                            <?php echo $t['hero_cta_secondary']; ?>
                        </button>
                    </div>
                </div>
                <div data-aos="fade-left" data-aos-delay="200">
                    <div class="relative">
                        <div class="absolute -top-10 -right-10 w-40 h-40 bg-secondary/20 rounded-full blur-2xl animate-pulse-slow"></div>
                        <div class="skeuomorphic-card p-4">
                            <img src="https://placehold.co/600x500/E87F24/FFFFFF?text=Cybersecurity+Illustration" alt="Cybersecurity" class="rounded-2xl w-full">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Stats Section -->
    <section class="py-16 px-4 bg-gradient-to-r from-primary/5 via-secondary/5 to-accent/5">
        <div class="container mx-auto max-w-6xl">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                <div class="text-center" data-aos="zoom-in">
                    <div class="stat-number" id="statStudents">5000+</div>
                    <p class="text-gray-600 mt-2"><?php echo $t['stats_students']; ?></p>
                </div>
                <div class="text-center" data-aos="zoom-in" data-aos-delay="100">
                    <div class="stat-number" id="statCourses">15+</div>
                    <p class="text-gray-600 mt-2"><?php echo $t['stats_courses']; ?></p>
                </div>
                <div class="text-center" data-aos="zoom-in" data-aos-delay="200">
                    <div class="stat-number" id="statPlacements">92%</div>
                    <p class="text-gray-600 mt-2"><?php echo $t['stats_placements']; ?></p>
                </div>
                <div class="text-center" data-aos="zoom-in" data-aos-delay="300">
                    <div class="stat-number" id="statExperts">25+</div>
                    <p class="text-gray-600 mt-2"><?php echo $t['stats_experts']; ?></p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Features Section -->
    <section class="py-20 px-4" id="about">
        <div class="container mx-auto max-w-6xl">
            <div class="text-center mb-12" data-aos="fade-up">
                <h2 class="text-3xl md:text-4xl font-bold mb-4"><?php echo $t['features_title']; ?></h2>
                <p class="text-gray-600 max-w-2xl mx-auto"><?php echo $t['features_subtitle']; ?></p>
            </div>
            
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
                <div class="skeuomorphic-card p-6 text-center" data-aos="flip-left">
                    <div class="w-16 h-16 bg-gradient-to-br from-primary to-secondary rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                        <i class="fas fa-chalkboard-user text-white text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2"><?php echo $t['feature_1_title']; ?></h3>
                    <p class="text-gray-600"><?php echo $t['feature_1_desc']; ?></p>
                </div>
                
                <div class="skeuomorphic-card p-6 text-center" data-aos="flip-left" data-aos-delay="100">
                    <div class="w-16 h-16 bg-gradient-to-br from-primary to-secondary rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                        <i class="fas fa-flask text-white text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2"><?php echo $t['feature_2_title']; ?></h3>
                    <p class="text-gray-600"><?php echo $t['feature_2_desc']; ?></p>
                </div>
                
                <div class="skeuomorphic-card p-6 text-center" data-aos="flip-left" data-aos-delay="200">
                    <div class="w-16 h-16 bg-gradient-to-br from-primary to-secondary rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                        <i class="fas fa-briefcase text-white text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2"><?php echo $t['feature_3_title']; ?></h3>
                    <p class="text-gray-600"><?php echo $t['feature_3_desc']; ?></p>
                </div>
                
                <div class="skeuomorphic-card p-6 text-center" data-aos="flip-left" data-aos-delay="300">
                    <div class="w-16 h-16 bg-gradient-to-br from-primary to-secondary rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                        <i class="fas fa-certificate text-white text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2"><?php echo $t['feature_4_title']; ?></h3>
                    <p class="text-gray-600"><?php echo $t['feature_4_desc']; ?></p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Courses Section -->
    <section class="py-20 px-4 bg-gradient-to-b from-white to-bg-light" id="courses">
        <div class="container mx-auto max-w-6xl">
            <div class="text-center mb-12" data-aos="fade-up">
                <h2 class="text-3xl md:text-4xl font-bold mb-4"><?php echo $t['courses_title']; ?></h2>
                <p class="text-gray-600 max-w-2xl mx-auto"><?php echo $t['courses_subtitle']; ?></p>
            </div>
            
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Course 1 -->
                <div class="skeuomorphic-card overflow-hidden group" data-aos="fade-up" data-aos-delay="100">
                    <div class="h-40 bg-gradient-to-r from-primary to-secondary flex items-center justify-center">
                        <i class="fas fa-user-secret text-white text-5xl group-hover:scale-110 transition-transform"></i>
                    </div>
                    <div class="p-5">
                        <h3 class="text-xl font-bold mb-2"><?php echo $t['course_1_title']; ?></h3>
                        <p class="text-gray-600 text-sm mb-3"><?php echo $t['course_1_desc']; ?></p>
                        <div class="flex justify-between items-center text-sm mb-4">
                            <span class="text-primary"><i class="far fa-clock"></i> <?php echo $t['course_1_duration']; ?></span>
                            <span class="text-accent"><i class="fas fa-chart-line"></i> <?php echo $t['course_1_level']; ?></span>
                        </div>
                        <button class="w-full bg-primary/10 text-primary py-2 rounded-full font-semibold hover:bg-primary hover:text-white transition-all">
                            <?php echo $t['btn_enroll']; ?>
                        </button>
                    </div>
                </div>
                
                <!-- Course 2 -->
                <div class="skeuomorphic-card overflow-hidden group" data-aos="fade-up" data-aos-delay="200">
                    <div class="h-40 bg-gradient-to-r from-primary to-secondary flex items-center justify-center">
                        <i class="fas fa-network-wired text-white text-5xl group-hover:scale-110 transition-transform"></i>
                    </div>
                    <div class="p-5">
                        <h3 class="text-xl font-bold mb-2"><?php echo $t['course_2_title']; ?></h3>
                        <p class="text-gray-600 text-sm mb-3"><?php echo $t['course_2_desc']; ?></p>
                        <div class="flex justify-between items-center text-sm mb-4">
                            <span class="text-primary"><i class="far fa-clock"></i> <?php echo $t['course_2_duration']; ?></span>
                            <span class="text-accent"><i class="fas fa-chart-line"></i> <?php echo $t['course_2_level']; ?></span>
                        </div>
                        <button class="w-full bg-primary/10 text-primary py-2 rounded-full font-semibold hover:bg-primary hover:text-white transition-all">
                            <?php echo $t['btn_enroll']; ?>
                        </button>
                    </div>
                </div>
                
                <!-- Course 3 -->
                <div class="skeuomorphic-card overflow-hidden group" data-aos="fade-up" data-aos-delay="300">
                    <div class="h-40 bg-gradient-to-r from-primary to-secondary flex items-center justify-center">
                        <i class="fas fa-search text-white text-5xl group-hover:scale-110 transition-transform"></i>
                    </div>
                    <div class="p-5">
                        <h3 class="text-xl font-bold mb-2"><?php echo $t['course_3_title']; ?></h3>
                        <p class="text-gray-600 text-sm mb-3"><?php echo $t['course_3_desc']; ?></p>
                        <div class="flex justify-between items-center text-sm mb-4">
                            <span class="text-primary"><i class="far fa-clock"></i> <?php echo $t['course_3_duration']; ?></span>
                            <span class="text-accent"><i class="fas fa-chart-line"></i> <?php echo $t['course_3_level']; ?></span>
                        </div>
                        <button class="w-full bg-primary/10 text-primary py-2 rounded-full font-semibold hover:bg-primary hover:text-white transition-all">
                            <?php echo $t['btn_enroll']; ?>
                        </button>
                    </div>
                </div>
                
                <!-- Course 4 -->
                <div class="skeuomorphic-card overflow-hidden group" data-aos="fade-up" data-aos-delay="400">
                    <div class="h-40 bg-gradient-to-r from-primary to-secondary flex items-center justify-center">
                        <i class="fas fa-cloud-upload-alt text-white text-5xl group-hover:scale-110 transition-transform"></i>
                    </div>
                    <div class="p-5">
                        <h3 class="text-xl font-bold mb-2"><?php echo $t['course_4_title']; ?></h3>
                        <p class="text-gray-600 text-sm mb-3"><?php echo $t['course_4_desc']; ?></p>
                        <div class="flex justify-between items-center text-sm mb-4">
                            <span class="text-primary"><i class="far fa-clock"></i> <?php echo $t['course_4_duration']; ?></span>
                            <span class="text-accent"><i class="fas fa-chart-line"></i> <?php echo $t['course_4_level']; ?></span>
                        </div>
                        <button class="w-full bg-primary/10 text-primary py-2 rounded-full font-semibold hover:bg-primary hover:text-white transition-all">
                            <?php echo $t['btn_enroll']; ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Testimonials Section -->
    <section class="py-20 px-4">
        <div class="container mx-auto max-w-6xl">
            <div class="text-center mb-12" data-aos="fade-up">
                <h2 class="text-3xl md:text-4xl font-bold mb-4"><?php echo $t['testimonials_title']; ?></h2>
                <p class="text-gray-600 max-w-2xl mx-auto"><?php echo $t['testimonials_subtitle']; ?></p>
            </div>
            
            <div class="grid md:grid-cols-3 gap-8">
                <div class="skeuomorphic-card p-6" data-aos="fade-right">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-primary to-secondary rounded-full flex items-center justify-center text-white font-bold text-xl">
                            R
                        </div>
                        <div>
                            <h4 class="font-bold"><?php echo $t['testimonial_1_name']; ?></h4>
                            <p class="text-sm text-gray-500"><?php echo $t['testimonial_1_role']; ?></p>
                        </div>
                    </div>
                    <div class="flex text-secondary mb-3">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                    <p class="text-gray-600 italic">"<?php echo $t['testimonial_1_text']; ?>"</p>
                </div>
                
                <div class="skeuomorphic-card p-6" data-aos="fade-up">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-primary to-secondary rounded-full flex items-center justify-center text-white font-bold text-xl">
                            P
                        </div>
                        <div>
                            <h4 class="font-bold"><?php echo $t['testimonial_2_name']; ?></h4>
                            <p class="text-sm text-gray-500"><?php echo $t['testimonial_2_role']; ?></p>
                        </div>
                    </div>
                    <div class="flex text-secondary mb-3">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                    <p class="text-gray-600 italic">"<?php echo $t['testimonial_2_text']; ?>"</p>
                </div>
                
                <div class="skeuomorphic-card p-6" data-aos="fade-left">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-primary to-secondary rounded-full flex items-center justify-center text-white font-bold text-xl">
                            A
                        </div>
                        <div>
                            <h4 class="font-bold"><?php echo $t['testimonial_3_name']; ?></h4>
                            <p class="text-sm text-gray-500"><?php echo $t['testimonial_3_role']; ?></p>
                        </div>
                    </div>
                    <div class="flex text-secondary mb-3">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                    </div>
                    <p class="text-gray-600 italic">"<?php echo $t['testimonial_3_text']; ?>"</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- CTA Section -->
    <section class="py-20 px-4 bg-gradient-to-r from-primary to-secondary">
        <div class="container mx-auto max-w-4xl text-center" data-aos="zoom-in">
            <h2 class="text-3xl md:text-4xl font-bold text-white mb-4"><?php echo $t['cta_title']; ?></h2>
            <p class="text-white/90 text-lg mb-8"><?php echo $t['cta_desc']; ?></p>
            <button onclick="openLoginModal()" class="bg-white text-primary px-8 py-3 rounded-full font-bold shadow-lg hover:shadow-xl transition-all transform hover:scale-105">
                <i class="fas fa-arrow-right"></i> <?php echo $t['cta_button']; ?>
            </button>
        </div>
    </section>
    
    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12 px-4" id="contact">
        <div class="container mx-auto max-w-6xl">
            <div class="grid md:grid-cols-4 gap-8">
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-10 h-10 bg-gradient-to-br from-primary to-secondary rounded-xl flex items-center justify-center">
                            <i class="fas fa-shield-alt text-white"></i>
                        </div>
                        <h3 class="text-xl font-bold">GyanSetu</h3>
                    </div>
                    <p class="text-gray-400 text-sm"><?php echo $t['footer_about']; ?></p>
                </div>
                
                <div>
                    <h4 class="font-bold mb-4"><?php echo $t['footer_quick_links']; ?></h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="#" class="hover:text-primary transition-colors"><?php echo $t['nav_home']; ?></a></li>
                        <li><a href="#courses" class="hover:text-primary transition-colors"><?php echo $t['nav_courses']; ?></a></li>
                        <li><a href="#about" class="hover:text-primary transition-colors"><?php echo $t['nav_about']; ?></a></li>
                        <li><a href="#" class="hover:text-primary transition-colors"><?php echo $t['footer_privacy']; ?></a></li>
                        <li><a href="#" class="hover:text-primary transition-colors"><?php echo $t['footer_terms']; ?></a></li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-bold mb-4"><?php echo $t['footer_contact_us']; ?></h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><i class="fas fa-map-marker-alt w-5"></i> 123, Cyber City, Gurugram, India</li>
                        <li><i class="fas fa-phone w-5"></i> +91 98765 43210</li>
                        <li><i class="fas fa-envelope w-5"></i> info@gyansetu.com</li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-bold mb-4"><?php echo $t['footer_follow_us']; ?></h4>
                    <div class="flex gap-4">
                        <a href="#" class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center hover:bg-primary transition-colors">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center hover:bg-primary transition-colors">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center hover:bg-primary transition-colors">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="#" class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center hover:bg-primary transition-colors">
                            <i class="fab fa-instagram"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-800 mt-8 pt-8 text-center text-gray-400 text-sm">
                <p><?php echo $t['footer_copyright']; ?></p>
            </div>
        </div>
    </footer>
    
    <!-- Login Modal -->
    <div id="loginModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4" style="backdrop-filter: blur(4px);">
        <div class="bg-white rounded-2xl max-w-md w-full animate-slide-down skeuomorphic-card overflow-hidden">
            <div class="bg-gradient-to-r from-primary to-secondary p-6 text-white">
                <div class="flex justify-between items-center">
                    <h2 class="text-2xl font-bold"><?php echo $t['login_modal_title']; ?></h2>
                    <button onclick="closeLoginModal()" class="text-white hover:text-gray-200 text-2xl">&times;</button>
                </div>
                <p class="text-white/80 text-sm mt-1">Access your learning dashboard</p>
            </div>
            
            <form id="loginForm" class="p-6" method="POST">
                <input type="hidden" name="login_action" value="1">
                <input type="hidden" name="csrf_token" value="<?php echo bin2hex(random_bytes(32)); ?>">
                
                <div id="loginError" class="hidden bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg mb-4 text-sm"></div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-2"><?php echo $t['login_username']; ?></label>
                    <div class="relative">
                        <i class="fas fa-user absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="text" name="username" class="skeuomorphic-input w-full pl-12 pr-4 py-3" required>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 font-medium mb-2"><?php echo $t['login_password']; ?></label>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="password" name="password" class="skeuomorphic-input w-full pl-12 pr-4 py-3" required>
                        <button type="button" onclick="togglePassword()" class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-primary">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="flex justify-between items-center mb-6">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="remember" class="w-4 h-4 text-primary">
                        <span class="text-sm text-gray-600"><?php echo $t['login_remember']; ?></span>
                    </label>
                    <a href="#" class="text-sm text-primary hover:underline"><?php echo $t['login_forgot']; ?></a>
                </div>
                
                <button type="submit" class="skeuomorphic-button w-full py-3 text-white font-semibold rounded-lg">
                    <i class="fas fa-sign-in-alt"></i> <?php echo $t['login_button']; ?>
                </button>
            </form>
            
            <div class="p-6 pt-0 text-center text-sm text-gray-500">
                <p>Demo credentials: <span class="font-mono">Admin / Admin123</span></p>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            once: true,
            offset: 100
        });
        
        // Mobile Menu Toggle
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mobileMenu = document.getElementById('mobileMenu');
        
        mobileMenuBtn?.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });
        
        // Stats Counter Animation
        function animateStats() {
            const stats = [
                { element: document.getElementById('statStudents'), target: 5000, suffix: '+' },
                { element: document.getElementById('statCourses'), target: 15, suffix: '+' },
                { element: document.getElementById('statPlacements'), target: 92, suffix: '%' },
                { element: document.getElementById('statExperts'), target: 25, suffix: '+' }
            ];
            
            stats.forEach(stat => {
                if (!stat.element) return;
                let current = 0;
                const increment = stat.target / 50;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= stat.target) {
                        stat.element.textContent = stat.target + stat.suffix;
                        clearInterval(timer);
                    } else {
                        stat.element.textContent = Math.floor(current) + stat.suffix;
                    }
                }, 30);
            });
        }
        
        // Trigger stats animation when in viewport
        const observerOptions = { threshold: 0.5 };
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateStats();
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);
        
        const statsSection = document.querySelector('.bg-gradient-to-r.from-primary\\/5');
        if (statsSection) observer.observe(statsSection);
        
        // Login Modal Functions
        function openLoginModal() {
            document.getElementById('loginModal').classList.remove('hidden');
            document.getElementById('loginModal').classList.add('flex');
            document.body.style.overflow = 'hidden';
        }
        
        function closeLoginModal() {
            document.getElementById('loginModal').classList.add('hidden');
            document.getElementById('loginModal').classList.remove('flex');
            document.body.style.overflow = '';
        }
        
        // Close modal on outside click
        document.getElementById('loginModal')?.addEventListener('click', (e) => {
            if (e.target === document.getElementById('loginModal')) {
                closeLoginModal();
            }
        });
        
        // Toggle Password Visibility
        function togglePassword() {
            const passwordInput = document.querySelector('#loginForm input[name="password"]');
            const icon = document.querySelector('#loginForm .absolute.right-4 i');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Login Form Submission with AJAX
        document.getElementById('loginForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Show loading
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
            submitBtn.disabled = true;
            
            // Hide previous error
            const errorDiv = document.getElementById('loginError');
            errorDiv.classList.add('hidden');
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Show success and redirect
                    window.location.href = data.redirect;
                } else {
                    // Show error
                    errorDiv.textContent = '<?php echo $t['login_error']; ?>';
                    errorDiv.classList.remove('hidden');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            } catch (error) {
                errorDiv.textContent = 'Network error. Please try again.';
                errorDiv.classList.remove('hidden');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });
        
        // Language Switcher
        function toggleLanguage() {
            const currentLang = '<?php echo $lang; ?>';
            const newLang = currentLang === 'hi' ? 'en' : 'hi';
            window.location.href = window.location.pathname + '?lang=' + newLang;
        }
        
        // Smooth Scroll
        function scrollToCourses() {
            document.getElementById('courses')?.scrollIntoView({ behavior: 'smooth' });
        }
        
        // Auto-hide admin alert after 5 seconds
        setTimeout(() => {
            const alert = document.getElementById('adminAlert');
            if (alert) {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }
        }, 5000);
        
        // Add animation on scroll for cards
        const cards = document.querySelectorAll('.skeuomorphic-card');
        const cardObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, { threshold: 0.1 });
        
        cards.forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            card.style.transition = 'all 0.6s ease';
            cardObserver.observe(card);
        });
        
        // Parallax effect on hero
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const hero = document.querySelector('section.pt-32');
            if (hero) {
                hero.style.transform = `translateY(${scrolled * 0.3}px)`;
            }
        });
    </script>
</body>
</html>