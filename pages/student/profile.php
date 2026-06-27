<?php
/**
 * NestSync — Student Profile
 * OCI8 / Oracle
 */
require_once '../../config/config.php';
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/auth_check.php';
require_once ROOT . '/includes/functions.php';

requireRole(['STUDENT']);
$pageTitle  = 'My Profile';
$activePage = 'profile';
$uid        = currentUserId();

$errors  = [];
$success = false;

// ── Fetch current user ─────────────────────────────────────────────────────
$user = oci_fetch_one_assoc('SELECT * FROM users WHERE user_id=:u', [':u' => $uid]);

// ── POST: update_profile ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $fullName   = sanitize($_POST['full_name']       ?? '');
        $email      = sanitize($_POST['email']           ?? '');
        $phone      = sanitize($_POST['phone']           ?? '');
        $dept       = sanitize($_POST['department']      ?? '');
        $budget     = (float)($_POST['monthly_budget']   ?? 0);
        $prefs      = sanitize($_POST['preferences']     ?? '');
        $gender     = sanitize($_POST['gender']          ?? '');

        if (empty($fullName)) $errors[] = 'Full name is required.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
        if ($budget < 0 || $budget > 99999) $errors[] = 'Budget must be between 0 and 99,999.';
        if (!in_array($gender, ['MALE','FEMALE','OTHER'], true)) $errors[] = 'Please select a valid gender.';

        // Check email uniqueness
        if (empty($errors) && strtolower($email) !== strtolower($user['EMAIL'])) {
            $taken = (int)oci_fetch_scalar(
                'SELECT COUNT(*) FROM users WHERE UPPER(email)=UPPER(:e) AND user_id!=:u',
                [':e' => $email, ':u' => $uid]
            );
            if ($taken > 0) $errors[] = 'That email is already in use by another account.';
        }

        if (empty($errors)) {
            $ok = oci_execute_dml(
                'UPDATE users SET full_name=:name, email=:email, phone=:phone,
                 department=:dept, monthly_budget=:budget, preferences=:prefs, gender=:gender
                 WHERE user_id=:u',
                [':name'=>$fullName,':email'=>$email,':phone'=>$phone,
                 ':dept'=>$dept,':budget'=>$budget,':prefs'=>($prefs?:null),':gender'=>$gender,':u'=>$uid]
            );
            if ($ok) {
                $_SESSION['name'] = $fullName;
                setFlash('success', 'Profile updated successfully!');
                redirect(BASE_URL . '/pages/student/profile.php');
            } else {
                $errors[] = 'Failed to update profile. Please try again.';
            }
        }
    }
}

// ── POST: change_password ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $currentPwd  = $_POST['current_password']  ?? '';
        $newPwd      = $_POST['new_password']      ?? '';
        $confirmPwd  = $_POST['confirm_password']  ?? '';

        if (empty($currentPwd) || empty($newPwd) || empty($confirmPwd)) {
            $errors[] = 'All password fields are required.';
        } elseif (!password_verify($currentPwd, $user['PASSWORD_HASH'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif ($newPwd !== $confirmPwd) {
            $errors[] = 'New passwords do not match.';
        } elseif (strlen($newPwd) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } else {
            $hash = password_hash($newPwd, PASSWORD_BCRYPT);
            $ok   = oci_execute_dml('UPDATE users SET password_hash=:h WHERE user_id=:u', [':h'=>$hash, ':u'=>$uid]);
            if ($ok) {
                setFlash('success', 'Password changed successfully!');
                redirect(BASE_URL . '/pages/student/profile.php');
            } else {
                $errors[] = 'Failed to change password.';
            }
        }
    }
}

$departments = [
    'CSE'=>'Computer Science & Engineering','EEE'=>'Electrical & Electronic Engineering',
    'ME'=>'Mechanical Engineering','CE'=>'Civil Engineering','BBA'=>'Business Administration',
    'ECO'=>'Economics','ENG'=>'English','LAW'=>'Law','PHY'=>'Physics',
    'MATH'=>'Mathematics','CHEM'=>'Chemistry','BIO'=>'Biology','SOC'=>'Sociology','PSY'=>'Psychology',
];

include ROOT . '/includes/header.php';
include ROOT . '/includes/sidebar.php';
?>
<div class="main-content">
<?php include ROOT . '/includes/navbar_top.php'; ?>
<div class="content-area">

<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php foreach ($errors as $e): ?><div><i class="fas fa-exclamation-circle me-1"></i><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="page-heading mb-1"><i class="fas fa-user-circle me-2 text-primary"></i>My Profile</h1>
            <nav aria-label="breadcrumb"><ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/student/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">My Profile</li>
            </ol></nav>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left: Profile Card -->
        <div class="col-md-4">
            <div class="table-wrapper text-center p-4">
                <div class="avatar-circle mx-auto mb-3" style="width:80px;height:80px;font-size:32px;">
                    <?= strtoupper(substr($user['FULL_NAME'], 0, 1)) ?>
                </div>
                <h4 class="fw-bold mb-1"><?= htmlspecialchars($user['FULL_NAME'], ENT_QUOTES, 'UTF-8') ?></h4>
                <div class="mb-2"><?= roleBadge($user['ROLE_NAME']) ?></div>
                <p class="text-muted small mb-1"><i class="fas fa-envelope me-1"></i><?= htmlspecialchars($user['EMAIL'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php if (!empty($user['PHONE'])): ?>
                <p class="text-muted small mb-1"><i class="fas fa-phone me-1"></i><?= htmlspecialchars($user['PHONE'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if (!empty($user['STUDENT_ID_NO'])): ?>
                <p class="text-muted small mb-1"><i class="fas fa-id-card me-1"></i><?= htmlspecialchars($user['STUDENT_ID_NO'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if (!empty($user['DEPARTMENT'])): ?>
                <p class="text-muted small mb-1"><i class="fas fa-graduation-cap me-1"></i><?= htmlspecialchars($user['DEPARTMENT'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <hr>
                <div class="d-flex justify-content-center gap-3 text-center">
                    <div><div class="fw-bold"><?= formatCurrency((float)($user['MONTHLY_BUDGET']??0)) ?></div><div class="text-muted" style="font-size:11px;">Monthly Budget</div></div>
                    <div><div class="fw-bold"><?= htmlspecialchars($user['GENDER']??'—', ENT_QUOTES, 'UTF-8') ?></div><div class="text-muted" style="font-size:11px;">Gender</div></div>
                </div>
                <hr>
                <p class="text-muted small mb-0"><i class="fas fa-calendar me-1"></i>Member since <?= formatDateShort($user['CREATED_AT']) ?></p>
                <p class="text-muted small"><?= statusBadge($user['ACCOUNT_STATUS']) ?></p>
            </div>
        </div>

        <!-- Right: Forms -->
        <div class="col-md-8">
            <!-- Edit Profile Form -->
            <div class="table-wrapper mb-4">
                <div class="table-header">
                    <h5 class="table-title mb-0"><i class="fas fa-edit me-2 text-primary"></i>Edit Profile</h5>
                </div>
                <div class="p-4">
                <form method="POST" action="">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_profile">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['FULL_NAME'], ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['EMAIL'], ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['PHONE']??'', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Department</label>
                            <select name="department" class="form-select">
                                <option value="">— Select Department —</option>
                                <?php foreach ($departments as $code => $name): ?>
                                <option value="<?= $code ?>" <?= ($user['DEPARTMENT']??'')===$code ? 'selected':'' ?>><?= $code ?> — <?= $name ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Monthly Budget (BDT)</label>
                            <input type="number" name="monthly_budget" class="form-control" min="0" max="99999" step="100" value="<?= (float)($user['MONTHLY_BUDGET']??0) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="">— Select —</option>
                                <option value="MALE"   <?= ($user['GENDER']??'')==='MALE'   ? 'selected':'' ?>>Male</option>
                                <option value="FEMALE" <?= ($user['GENDER']??'')==='FEMALE' ? 'selected':'' ?>>Female</option>
                                <option value="OTHER"  <?= ($user['GENDER']??'')==='OTHER'  ? 'selected':'' ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Roommate Preferences</label>
                            <textarea name="preferences" class="form-control" rows="3" placeholder="e.g. Quiet, non-smoker, study-focused, clean room…"><?= htmlspecialchars($user['PREFERENCES']??'', ENT_QUOTES, 'UTF-8') ?></textarea>
                            <div class="form-text">Describe your lifestyle and preferences. This is used for roommate matching.</div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Changes</button>
                        </div>
                    </div>
                </form>
                </div>
            </div>

            <!-- Change Password Form -->
            <div class="table-wrapper">
                <div class="table-header">
                    <h5 class="table-title mb-0"><i class="fas fa-lock me-2 text-warning"></i>Change Password</h5>
                </div>
                <div class="p-4">
                <form method="POST" action="">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="change_password">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Current Password <span class="text-danger">*</span></label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">New Password <span class="text-danger">*</span></label>
                            <input type="password" name="new_password" class="form-control" minlength="8" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Confirm New Password <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_password" class="form-control" minlength="8" required>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-warning text-white"><i class="fas fa-key me-1"></i>Change Password</button>
                        </div>
                    </div>
                </form>
                </div>
            </div>
        </div>
    </div>

</div><!-- /.content-area -->
</div><!-- /.main-content -->
<?php include ROOT . '/includes/footer.php'; ?>
