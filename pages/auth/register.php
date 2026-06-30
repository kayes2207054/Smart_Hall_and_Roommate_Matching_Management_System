<?php
/**
 * NestSync – Student Registration Page
 * Secure registration with password_hash() and prepared statements
 */
require_once '../../config/config.php';
require_once ROOT . '/config/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();
require_once ROOT . '/includes/functions.php';

// Already logged in?
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/pages/student/dashboard.php');
    exit();
}

$errors  = [];
$success = false;
$old     = []; // repopulate form fields on error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token. Please refresh and try again.';
    } else {
        // Collect & sanitize
        $old = $_POST;
        $fullName   = sanitize($_POST['full_name']    ?? '');
        $email      = sanitizeEmail($_POST['email']   ?? '');
        $password   = $_POST['password']              ?? '';
        $confirm    = $_POST['confirm_password']      ?? '';
        $department = sanitize($_POST['department']   ?? '');
        $phone      = sanitize($_POST['phone']        ?? '');
        $studentId  = sanitize($_POST['student_id_no'] ?? '');
        $budget     = (float)($_POST['monthly_budget'] ?? 0);
        $prefs      = sanitize($_POST['preferences']  ?? '');
        $gender     = $_POST['gender']               ?? '';

        // Validation
        if (strlen($fullName) < 3)                 $errors[] = 'Full name must be at least 3 characters.';
        if (!isValidEmail($email))                  $errors[] = 'Please enter a valid email address.';
        if (strlen($password) < 8)                  $errors[] = 'Password must be at least 8 characters.';
        if ($password !== $confirm)                 $errors[] = 'Passwords do not match.';
        if (!preg_match('/[A-Z]/', $password))      $errors[] = 'Password must contain at least one uppercase letter.';
        if (!preg_match('/[0-9]/', $password))      $errors[] = 'Password must contain at least one number.';
        if (empty($department))                     $errors[] = 'Please select your department.';
        if ($budget < 1000 || $budget > 20000)      $errors[] = 'Monthly budget must be between 1,000 and 20,000 BDT.';
        if (!in_array($gender, ['MALE','FEMALE','OTHER'], true)) $errors[] = 'Please select your gender.';

        // Check duplicate email (OCI8)
        if (empty($errors)) {
            $emailCount = oci_fetch_scalar(
                'SELECT COUNT(*) FROM users WHERE email = :email',
                [':email' => $email]
            );
            if ((int)$emailCount > 0) $errors[] = 'An account with this email already exists.';
        }

        // Check duplicate student ID (OCI8)
        if (empty($errors) && !empty($studentId)) {
            $sidCount = oci_fetch_scalar(
                'SELECT COUNT(*) FROM users WHERE student_id_no = :sid',
                [':sid' => $studentId]
            );
            if ((int)$sidCount > 0) $errors[] = 'A student with this Student ID already exists.';
        }

        // All good — insert (OCI8)
        if (empty($errors)) {
            $hash   = password_hash($password, PASSWORD_BCRYPT);
            $role   = 'STUDENT';
            $status = 'ACTIVE';

            $inserted = oci_execute_dml(
                'INSERT INTO users
                 (full_name, email, password_hash, role_name, department, phone,
                  student_id_no, monthly_budget, preferences, gender, account_status)
                 VALUES
                 (:name, :email, :hash, :role, :dept, :phone,
                  :sid, :budget, :prefs, :gender, :status)',
                [
                    ':name'   => $fullName,
                    ':email'  => $email,
                    ':hash'   => $hash,
                    ':role'   => $role,
                    ':dept'   => $department,
                    ':phone'  => $phone,
                    ':sid'    => $studentId ?: null,
                    ':budget' => $budget,
                    ':prefs'  => $prefs ?: null,
                    ':gender' => $gender,
                    ':status' => $status,
                ]
            );

            if ($inserted) {
                $newUserId = oci_last_insert_id('seq_users');

                // Welcome notification
                oci_execute_dml(
                    'INSERT INTO notifications (user_id, title, message, notif_type)
                     VALUES (:sid, :title, :msg, :type)',
                    [
                        ':sid'   => $newUserId,
                        ':title' => 'Welcome to NestSync! 🎉',
                        ':msg'   => 'Your account has been created. Browse halls and book your seat now!',
                        ':type'  => 'SYSTEM',
                    ]
                );

                // Run matching for the newly registered student
                runMatchingForStudent($conn, $newUserId);

                $success = true;
            } else {
                $errors[] = 'Registration failed. Please try again.';
            }
        }
    }
}

$departments = [
    'CSE'  => 'Computer Science & Engineering',
    'EEE'  => 'Electrical & Electronic Engineering',
    'ME'   => 'Mechanical Engineering',
    'CE'   => 'Civil Engineering',
    'BBA'  => 'Business Administration',
    'ECO'  => 'Economics',
    'ENG'  => 'English',
    'LAW'  => 'Law',
    'PHY'  => 'Physics',
    'MATH' => 'Mathematics',
    'CHEM' => 'Chemistry',
    'BIO'  => 'Biology',
    'SOC'  => 'Sociology',
    'PSY'  => 'Psychology',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register – NestSync</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🏠</text></svg>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
</head>
<body>
<div class="auth-wrapper">

    <!-- Left Panel -->
    <div class="auth-left">
        <div class="auth-left-content">
            <div class="mb-4" style="font-size:42px">🎓</div>
            <h1>Join NestSync</h1>
            <p>Create your student account to browse halls, book your seat, and find your perfect roommate.</p>
            <ul class="auth-feature-list">
                <li><i class="fas fa-check-circle"></i> Free student registration</li>
                <li><i class="fas fa-check-circle"></i> Browse available hall seats</li>
                <li><i class="fas fa-check-circle"></i> AI-powered roommate matching</li>
                <li><i class="fas fa-check-circle"></i> Track booking status live</li>
            </ul>
        </div>
    </div>

    <!-- Right Panel: Form -->
    <div class="auth-right" style="width:520px">
        <div class="auth-form-box" style="max-width:440px">

            <div class="auth-logo">
                <span>🏠</span>
                <span>Nest<span class="logo-accent">Sync</span></span>
            </div>

            <?php if ($success): ?>
            <!-- Success State -->
            <div class="text-center py-4">
                <div style="font-size:60px">✅</div>
                <h3 class="fw-800 mt-3">You're Registered!</h3>
                <p class="text-muted mb-4">Your NestSync account is ready. Login to find your perfect hall seat.</p>
                <a href="<?= BASE_URL ?>/login.php" class="btn-auth btn w-100">
                    <i class="fas fa-sign-in-alt me-2"></i>Login Now
                </a>
            </div>
            <?php else: ?>

            <h2 class="auth-title">Create Account</h2>
            <p class="auth-subtitle">Fill in your details to register as a student.</p>

            <!-- Errors -->
            <?php if ($errors): ?>
            <div class="alert alert-danger mb-4">
                <strong><i class="fas fa-exclamation-circle me-2"></i>Please fix the following:</strong>
                <ul class="mb-0 mt-2 ps-3">
                    <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form method="POST" class="needs-validation" novalidate>
                <?= csrfField() ?>

                <div class="row g-3">
                    <!-- Full Name -->
                    <div class="col-12">
                        <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" id="full_name" name="full_name" class="form-control"
                                   placeholder="Your full name" required minlength="3"
                                   value="<?= htmlspecialchars($old['full_name'] ?? '', ENT_QUOTES) ?>">
                            <div class="invalid-feedback">At least 3 characters.</div>
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="col-12">
                        <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" id="email" name="email" class="form-control"
                                   placeholder="you@university.edu" required
                                   value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES) ?>">
                            <div class="invalid-feedback">Valid email required.</div>
                        </div>
                    </div>

                    <!-- Student ID -->
                    <div class="col-md-6">
                        <label for="student_id_no" class="form-label">Student ID</label>
                        <input type="text" id="student_id_no" name="student_id_no" class="form-control"
                               placeholder="e.g. STU-2024-001"
                               value="<?= htmlspecialchars($old['student_id_no'] ?? '', ENT_QUOTES) ?>">
                    </div>

                    <!-- Phone -->
                    <div class="col-md-6">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="text" id="phone" name="phone" class="form-control"
                               placeholder="01XXXXXXXXX"
                               value="<?= htmlspecialchars($old['phone'] ?? '', ENT_QUOTES) ?>">
                    </div>

                    <!-- Department -->
                    <div class="col-md-6">
                        <label for="department" class="form-label">Department <span class="text-danger">*</span></label>
                        <select id="department" name="department" class="form-select" required>
                            <option value="">-- Select --</option>
                            <?php foreach ($departments as $code => $name): ?>
                            <option value="<?= $code ?>" <?= ($old['department'] ?? '') === $code ? 'selected' : '' ?>>
                                <?= $code ?> – <?= $name ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Please select a department.</div>
                    </div>

                    <!-- Gender -->
                    <div class="col-md-6">
                        <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                        <select id="gender" name="gender" class="form-select" required>
                            <option value="">-- Select --</option>
                            <option value="MALE"   <?= ($old['gender'] ?? '') === 'MALE'   ? 'selected' : '' ?>>Male</option>
                            <option value="FEMALE" <?= ($old['gender'] ?? '') === 'FEMALE' ? 'selected' : '' ?>>Female</option>
                            <option value="OTHER"  <?= ($old['gender'] ?? '') === 'OTHER'  ? 'selected' : '' ?>>Other</option>
                        </select>
                        <div class="invalid-feedback">Please select gender.</div>
                    </div>

                    <!-- Monthly Budget -->
                    <div class="col-12">
                        <label for="monthly_budget" class="form-label">Monthly Budget (BDT) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">৳</span>
                            <input type="number" id="monthly_budget" name="monthly_budget" class="form-control"
                                   placeholder="e.g. 4000" min="1000" max="20000" required
                                   value="<?= htmlspecialchars($old['monthly_budget'] ?? '', ENT_QUOTES) ?>">
                            <div class="invalid-feedback">Enter a budget between 1,000 and 20,000.</div>
                        </div>
                    </div>

                    <!-- Preferences -->
                    <div class="col-12">
                        <label for="preferences" class="form-label">Room Preferences</label>
                        <textarea id="preferences" name="preferences" class="form-control" rows="2"
                                  placeholder="e.g. Quiet room, non-smoker, study-focused, early riser..."><?= htmlspecialchars($old['preferences'] ?? '', ENT_QUOTES) ?></textarea>
                        <div class="form-text">Used for roommate matching. Keywords: quiet, study, clean, early, non-smoker.</div>
                    </div>

                    <!-- Password -->
                    <div class="col-12">
                        <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                        <div class="input-group password-toggle">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" id="password" name="password" class="form-control"
                                   placeholder="Min 8 chars with uppercase and number" required minlength="8">
                            <button type="button" class="password-toggle-btn"><i class="fas fa-eye"></i></button>
                            <div class="invalid-feedback">Min 8 characters required.</div>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div class="col-12">
                        <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                                   placeholder="Re-enter your password" required>
                            <div class="invalid-feedback">Passwords must match.</div>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn-auth">
                        <i class="fas fa-user-plus me-2"></i>Create My Account
                    </button>
                </div>
            </form>

            <p class="auth-footer-text mt-4">
                Already have an account?
                <a href="<?= BASE_URL ?>/login.php">Sign In</a>
            </p>
            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/public/js/main.js"></script>
</body>
</html>
