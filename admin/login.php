<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple database connection
$host = 'localhost';
$dbname = 'u621399201_guru';
$user = 'u621399201_guru';
$pass = 'u$|R1&Tg';

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard/dashboard.php');
    exit();
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

// Check if admin exists, create if not
try {
    $stmt = $db->prepare("SELECT id FROM users WHERE role = 'super_admin' LIMIT 1");
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        $username = 'Admin';
        $email = 'admin@techedu.com';
        $password_hash = password_hash('Admin123', PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, role, status) VALUES (?, ?, ?, 'super_admin', 'active')");
        $stmt->execute([$username, $email, $password_hash]);
        
        $user_id = $db->lastInsertId();
        
        // Create admin details
        $stmt = $db->prepare("INSERT INTO admin_details (user_id, full_name, joining_date) VALUES (?, ?, CURDATE())");
        $stmt->execute([$user_id, $username]);
    }
} catch (Exception $e) {
    error_log("Admin creation check failed: " . $e->getMessage());
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $error = "Invalid security token. Please try again.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $error = "Please enter both username and password.";
        } else {
            try {
                $stmt = $db->prepare("SELECT id, username, email, password_hash, role, status FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch();
                
                if ($user && $user['status'] === 'active' && password_verify($password, $user['password_hash'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    
                    header('Location: dashboard/dashboard.php');
                    exit();
                } else {
                    $error = "Invalid username or password.";
                }
            } catch (Exception $e) {
                $error = "An error occurred. Please try again later.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Guru Education</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * { font-family: 'Inter', sans-serif; }
        
        body {
            background: linear-gradient(135deg, #E87F24 0%, #FFC81E 100%);
            min-height: 100vh;
        }
        
        .login-card {
            background: rgba(254, 253, 223, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 32px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            transition: transform 0.3s ease;
        }
        
        .login-card:hover {
            transform: translateY(-5px);
        }
        
        .input-group input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #e5e7eb;
            border-radius: 16px;
            transition: all 0.3s ease;
        }
        
        .input-group input:focus {
            outline: none;
            border-color: #E87F24;
            box-shadow: 0 0 0 3px rgba(232, 127, 36, 0.1);
        }
        
        .input-group {
            position: relative;
            margin-bottom: 20px;
        }
        
        .input-group i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 20px;
            color: #9ca3af;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #E87F24, #FFC81E);
            color: white;
            padding: 14px;
            border-radius: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            width: 100%;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(232, 127, 36, 0.4);
        }
        
        .error-message {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            padding: 12px;
            border-radius: 12px;
            color: #991b1b;
            font-size: 14px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <div class="inline-block p-4 bg-white/20 backdrop-blur rounded-full mb-4">
                <i class="ri-book-open-line text-6xl text-white"></i>
            </div>
            <h1 class="text-4xl font-bold text-white mb-2">Guru Education</h1>
            <p class="text-white/90">Administrative Portal</p>
        </div>
        
        <div class="login-card p-8 md:p-10">
            <h2 class="text-2xl font-bold text-gray-800 text-center mb-6">Welcome Back</h2>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="ri-alert-line mr-2"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="input-group">
                    <input type="text" name="username" placeholder="Username or Email" required autocomplete="username">
                    <i class="ri-user-line"></i>
                </div>
                
                <div class="input-group">
                    <input type="password" name="password" id="password" placeholder="Password" required autocomplete="current-password">
                    <i class="ri-lock-line"></i>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="ri-login-circle-line mr-2"></i> Sign In
                </button>
            </form>
            
            <div class="mt-6 text-center text-xs text-gray-500">
                <i class="ri-shield-check-line"></i> Default Admin: Admin / Admin123
            </div>
        </div>
    </div>
</body>
</html>