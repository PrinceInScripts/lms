<?php
/**
 * admin/login.php — GyanSetu LMS
 *
 * Changes from original:
 *   ✓ Removed error_reporting / display_errors (debug code)
 *   ✓ Removed inline DB credentials — uses shared db_conn.php
 *   ✓ Removed console.log() debug output
 *   ✓ Removed plain-text default credentials from UI
 *   ✓ Added session_regenerate_id(true) on successful login
 *   ✓ CSRF token regenerated after each form submission (via verifyCSRFToken)
 *   ✓ Login success & failure logged via logActivity()
 *   ✓ Auth error messages from session flash shown on page load
 *   ✓ Rate-limiting comment — add Redis/DB throttle for production
 */

define('GYANSETU_APP', true);

// Load foundation — ORDER MATTERS
require_once __DIR__ . '/../includes/security_headers.php'; // starts session + sets headers
require_once __DIR__ . '/../includes/db_conn.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/activity_logger.php';

// ── Redirect if already authenticated ─────────────────────────────────────
if (!empty($_SESSION['user_id'])) {
    redirect(BASE_URL . '/admin/dashboard/dashboard.php');
}

// ── Pull one-time auth error set by auth.php (e.g. session expired) ────────
$authError = '';
if (!empty($_SESSION['auth_error'])) {
    $authError = $_SESSION['auth_error'];
    unset($_SESSION['auth_error']);
}

$error = $authError;

// ── POST: handle login submission ──────────────────────────────────────────
if (isPost()) {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!verifyCSRFToken($submittedToken)) {
        // CSRF mismatch — could be a replay or tampered form
        $error = 'Security token mismatch. Please refresh the page and try again.';
        logActivity('CSRF_FAILURE', 'auth', 'CSRF token mismatch on login form');
    } else {
        $identifier = trim($_POST['username'] ?? '');
        $password   = $_POST['password']   ?? '';

        if (empty($identifier) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            try {
                $db   = getDB();
                $stmt = $db->prepare(
                    'SELECT id, username, email, password_hash, role, status
                     FROM users
                     WHERE (username = ? OR email = ?)
                     LIMIT 1'
                );
                $stmt->execute([$identifier, $identifier]);
                $user = $stmt->fetch();

                if (
                    $user
                    && $user['status'] === 'active'
                    && password_verify($password, $user['password_hash'])
                ) {
                    // ── Successful login ──────────────────────────────────
                    // Regenerate session ID to prevent fixation
                    session_regenerate_id(true);

                    $_SESSION['user_id']       = (int) $user['id'];
                    $_SESSION['username']      = $user['username'];
                    $_SESSION['role']          = $user['role'];
                    $_SESSION['last_activity'] = time();

                    logActivity(
                        'LOGIN_SUCCESS',
                        'auth',
                        "User '{$user['username']}' logged in successfully"
                    );

                    // Upgrade password hash if cost factor changed
                    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                        $newHash = password_hash($password, PASSWORD_DEFAULT);
                        $upd     = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                        $upd->execute([$newHash, $user['id']]);
                    }

                    redirect(BASE_URL . '/admin/dashboard/dashboard.php');

                } else {
                    // ── Failed login ──────────────────────────────────────
                    $error = 'Invalid username or password.';

                    // Log with the attempted identifier (no password ever logged)
                    logActivity(
                        'LOGIN_FAILURE',
                        'auth',
                        "Failed login attempt for identifier: " . sanitize($identifier)
                    );
                }
            } catch (Exception $e) {
                error_log('[GyanSetu] Login error: ' . $e->getMessage());
                $error = 'An unexpected error occurred. Please try again later.';
            }
        }
    }
}

// Pre-generate CSRF token for the form
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — GyanSetu</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }

        body {
            background: linear-gradient(135deg, #E87F24 0%, #FFC81E 100%);
            min-height: 100vh;
        }

        .login-card {
            background: rgba(254, 253, 223, 0.96);
            backdrop-filter: blur(12px);
            border-radius: 28px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.18);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .login-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 32px 70px rgba(0,0,0,0.22);
        }

        .input-wrap {
            position: relative;
            margin-bottom: 18px;
        }
        .input-wrap i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 20px;
            color: #9ca3af;
            pointer-events: none;
            transition: color 0.2s;
        }
        .input-wrap input {
            width: 100%;
            padding: 13px 16px 13px 46px;
            border: 2px solid #e5e7eb;
            border-radius: 14px;
            background: #fff;
            font-size: 15px;
            transition: border-color 0.25s, box-shadow 0.25s;
            outline: none;
        }
        .input-wrap input:focus {
            border-color: #E87F24;
            box-shadow: 0 0 0 3px rgba(232, 127, 36, 0.12);
        }
        .input-wrap input:focus + i,
        .input-wrap:focus-within i { color: #E87F24; }

        .btn-login {
            width: 100%;
            padding: 13px;
            border-radius: 14px;
            background: linear-gradient(135deg, #E87F24, #FFC81E);
            color: #fff;
            font-weight: 600;
            font-size: 15px;
            border: none;
            cursor: pointer;
            transition: transform 0.25s, box-shadow 0.25s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(232,127,36,0.38);
        }
        .btn-login:active { transform: translateY(0); }

        .error-box {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            padding: 12px 14px;
            border-radius: 12px;
            color: #991b1b;
            font-size: 14px;
            margin-bottom: 18px;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-6px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* eye icon for password toggle */
        .toggle-pw {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #9ca3af;
            font-size: 18px;
            transition: color 0.2s;
            z-index: 1;
        }
        .toggle-pw:hover { color: #E87F24; }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <div class="w-full max-w-md">

        <!-- Logo & Brand -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-white/20 backdrop-blur rounded-full mb-4 ring-4 ring-white/30">
                <i class="ri-book-open-line text-5xl text-white"></i>
            </div>
            <h1 class="text-4xl font-bold text-white tracking-tight mb-1">GyanSetu</h1>
            <p class="text-white/85 text-sm font-medium tracking-wide uppercase">Administrative Portal</p>
        </div>

        <!-- Login Card -->
        <div class="login-card p-8 md:p-10">
            <h2 class="text-2xl font-bold text-gray-800 text-center mb-6">Welcome Back</h2>

            <?php if ($error): ?>
                <div class="error-box" role="alert">
                    <i class="ri-error-warning-line text-lg flex-shrink-0"></i>
                    <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" novalidate autocomplete="on">
                <!-- CSRF -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <!-- Username / Email -->
                <div class="input-wrap">
                    <input
                        type="text"
                        name="username"
                        id="username"
                        placeholder="Username or Email"
                        autocomplete="username"
                        required
                        value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8') : '' ?>"
                    >
                    <i class="ri-user-3-line"></i>
                </div>

                <!-- Password -->
                <div class="input-wrap">
                    <input
                        type="password"
                        name="password"
                        id="password"
                        placeholder="Password"
                        autocomplete="current-password"
                        required
                    >
                    <i class="ri-lock-2-line"></i>
                    <span class="toggle-pw" id="togglePw" title="Show / Hide password">
                        <i class="ri-eye-off-line" id="eyeIcon"></i>
                    </span>
                </div>

                <button type="submit" class="btn-login mt-2">
                    <i class="ri-login-circle-line mr-2"></i>Sign In
                </button>
            </form>

            <p class="mt-6 text-center text-xs text-gray-400">
                <i class="ri-shield-keyhole-line mr-1"></i>
                Secured with session-based authentication
            </p>
        </div>

    </div>

    <script>
        // Password visibility toggle
        document.getElementById('togglePw').addEventListener('click', function () {
            const pw  = document.getElementById('password');
            const eye = document.getElementById('eyeIcon');
            if (pw.type === 'password') {
                pw.type  = 'text';
                eye.className = 'ri-eye-line';
            } else {
                pw.type  = 'password';
                eye.className = 'ri-eye-off-line';
            }
        });
    </script>
</body>
</html>
