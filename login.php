<?php
/**
 * NestSync – Login Page
 * Secure login with prepared statements and password_verify()
 */
require_once 'config/config.php';
require_once 'config/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/functions.php';

// Redirect already-logged-in users
if (!empty($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? '';
    header('Location: ' . BASE_URL . ($role === 'STUDENT'
        ? '/pages/student/dashboard.php'
        : '/pages/admin/dashboard.php'));
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Prepared statement — no SQL injection
            $stmt = $conn->prepare(
                'SELECT user_id, full_name, email, password_hash, role, account_status
                 FROM users WHERE email = ? LIMIT 1'
            );
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user   = $result->fetch_assoc();
            $stmt->close();

            if (!$user) {
                $error = 'No account found with that email address.';
            } elseif ($user['account_status'] !== 'ACTIVE') {
                $error = 'Your account is ' . strtolower($user['account_status']) . '. Please contact an administrator.';
            } elseif (!password_verify($password, $user['password_hash'])) {
                $error = 'Incorrect password. Please try again.';
            } else {
                // Successful login — regenerate session ID (prevents session fixation)
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['name']    = $user['full_name'];
                $_SESSION['email']   = $user['email'];
                $_SESSION['role']    = $user['role'];

                // Role-based redirect
                $destination = ($user['role'] === 'STUDENT')
                    ? BASE_URL . '/pages/student/dashboard.php'
                    : BASE_URL . '/pages/admin/dashboard.php';

                // Honor redirect-after-login if set
                if (!empty($_SESSION['redirect_after_login'])) {
                    $destination = $_SESSION['redirect_after_login'];
                    unset($_SESSION['redirect_after_login']);
                }

                header('Location: ' . $destination);
                exit();
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
    <title>Login – NestSync</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🏠</text></svg>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body>
<div class="auth-wrapper">

    <!-- Left Panel -->
    <div class="auth-left">
        <div class="auth-left-content">
            <div class="mb-4" style="font-size:42px">🏠</div>
            <h1>Welcome Back!</h1>
            <p>Sign in to your NestSync account to manage your hall booking, track your roommate matches, and more.</p>
            <ul class="auth-feature-list">
                <li><i class="fas fa-check-circle"></i> Secure role-based access</li>
                <li><i class="fas fa-check-circle"></i> Real-time booking status</li>
                <li><i class="fas fa-check-circle"></i> Smart roommate matching</li>
                <li><i class="fas fa-check-circle"></i> Instant notifications</li>
            </ul>
        </div>
    </div>

    <!-- Right Panel: Form -->
    <div class="auth-right">
        <div class="auth-form-box">

            <!-- Logo -->
            <div class="auth-logo">
                <span>🏠</span>
                <span>Nest<span class="logo-accent">Sync</span></span>
            </div>

            <h2 class="auth-title">Sign In</h2>
            <p class="auth-subtitle">Enter your credentials to access your dashboard.</p>

            <!-- Error Alert -->
            <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="login.php" class="needs-validation" novalidate>
                <?= csrfField() ?>

                <!-- Email -->
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        <input type="email" id="email" name="email" class="form-control"
                               placeholder="you@nestsync.edu"
                               value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               required autocomplete="email">
                        <div class="invalid-feedback">Please enter a valid email address.</div>
                    </div>
                </div>

                <!-- Password -->
                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group password-toggle">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" id="password" name="password" class="form-control"
                               placeholder="Your password" required autocomplete="current-password">
                        <button type="button" class="password-toggle-btn" title="Show/hide password">
                            <i class="fas fa-eye"></i>
                        </button>
                        <div class="invalid-feedback">Please enter your password.</div>
                    </div>
                </div>

                <!-- Submit -->
                <button type="submit" class="btn-auth">
                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                </button>
            </form>

            <!-- Divider -->
            <div class="divider-text">or</div>

            <!-- Quick Login Hints -->
            <div class="p-3 rounded-3 mb-3" style="background:#f8fafc;border:1px solid #e2e8f0">
                <p class="mb-2 fw-600" style="font-size:12px;color:#64748b">DEMO ACCOUNTS</p>
                <div class="d-flex flex-column gap-1" style="font-size:12px">
                    <div><span class="badge bg-danger me-2">Admin</span><code>admin@nestsync.edu</code> / <code>Admin@123</code></div>
                    <div><span class="badge bg-warning text-dark me-2">Hall Admin</span><code>halladmin1@nestsync.edu</code> / <code>Admin@123</code></div>
                    <div><span class="badge bg-primary me-2">Student</span><code>rahim@nestsync.edu</code> / <code>Student@123</code></div>
                </div>
            </div>

            <p class="auth-footer-text">
                Don't have an account?
                <a href="pages/auth/register.php">Register as Student</a>
            </p>
            <p class="auth-footer-text">
                <a href="index.php"><i class="fas fa-arrow-left me-1"></i>Back to Home</a>
            </p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="public/js/main.js"></script>
</body>
</html>