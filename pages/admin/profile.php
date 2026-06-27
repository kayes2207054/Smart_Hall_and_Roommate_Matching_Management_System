<?php
/**
 * NestSync — Admin Profile
 * OCI8 / Oracle
 */
require_once '../../config/config.php';
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/auth_check.php';
require_once ROOT . '/includes/functions.php';

requireRole(['SYSTEM_ADMIN', 'HALL_ADMIN']);
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
        $fullName   = sanitize($_POST['full_name'] ?? '');
        $email      = sanitize($_POST['email']     ?? '');
        $phone      = sanitize($_POST['phone']     ?? '');

        if (empty($fullName)) $errors[] = 'Full name is required.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';

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
                'UPDATE users SET full_name=:name, email=:email, phone=:phone WHERE user_id=:u',
                [':name'=>$fullName, ':email'=>$email, ':phone'=>$phone, ':u'=>$uid]
            );
            if ($ok) {
                $_SESSION['name'] = $fullName;
                setFlash('success', 'Profile updated successfully!');
                redirect(BASE_URL . '/pages/admin/profile.php');
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
                redirect(BASE_URL . '/pages/admin/profile.php');
            } else {
                $errors[] = 'Failed to change password.';
            }
        }
    }
}

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
            <h1 class="page-heading mb-1"><i class="fas fa-user-shield me-2 text-primary"></i>Admin Profile</h1>
            <nav aria-label="breadcrumb"><ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/admin/dashboard.php">Dashboard</a></li>
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
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['PHONE']??'', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="col-12 mt-4">
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
                        <div class="col-12 mt-4">
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
