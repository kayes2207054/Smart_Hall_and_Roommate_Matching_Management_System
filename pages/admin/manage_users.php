<?php
/**
 * NestSync — Admin: Manage Users
 * OCI8 / Oracle
 */
require_once '../../config/config.php';
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/auth_check.php';
require_once ROOT . '/includes/functions.php';

requireRole(['SYSTEM_ADMIN']);
$pageTitle  = 'Manage Users';
$activePage = 'users';
$uid        = currentUserId();

$errors = [];

// ── POST ACTIONS ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';

        // Add User
        if ($action === 'add_user') {
            $name   = sanitize($_POST['full_name']);
            $email  = sanitize($_POST['email']);
            $pass   = $_POST['password'] ?? '';
            $role   = sanitize($_POST['role_name']);
            $dept   = sanitize($_POST['department'] ?? '');
            $phone  = sanitize($_POST['phone'] ?? '');
            $sid    = sanitize($_POST['student_id_no'] ?? '');
            $budget = (float)($_POST['monthly_budget'] ?? 0);
            $gender = sanitize($_POST['gender'] ?? '');

            if (!$name || !$email || !$pass || !$role) $errors[] = 'Required fields missing.';
            if (strlen($pass) < 8) $errors[] = 'Password must be at least 8 characters.';

            if (empty($errors)) {
                $count = (int)oci_fetch_scalar('SELECT COUNT(*) FROM users WHERE email=:e', [':e'=>$email]);
                if ($count > 0) $errors[] = 'Email already exists.';
                else {
                    $hash = password_hash($pass, PASSWORD_BCRYPT);
                    $ok = oci_execute_dml(
                        'INSERT INTO users (full_name,email,password_hash,role_name,department,phone,student_id_no,monthly_budget,gender,account_status)
                         VALUES (:n,:e,:h,:r,:d,:p,:sid,:b,:g,\'ACTIVE\')',
                        [':n'=>$name,':e'=>$email,':h'=>$hash,':r'=>$role,':d'=>$dept,':p'=>$phone,':sid'=>$sid,':b'=>$budget,':g'=>$gender]
                    );
                    if ($ok) {
                        setFlash('success', 'User added successfully.');
                        redirect(BASE_URL . '/pages/admin/manage_users.php');
                    } else $errors[] = 'Database error adding user.';
                }
            }
        }

        // Edit User
        if ($action === 'edit_user') {
            $editId = (int)$_POST['user_id'];
            $name   = sanitize($_POST['full_name']);
            $email  = sanitize($_POST['email']);
            $role   = sanitize($_POST['role_name']);
            $pass   = $_POST['password'] ?? '';
            $dept   = sanitize($_POST['department'] ?? '');
            $phone  = sanitize($_POST['phone'] ?? '');
            $sid    = sanitize($_POST['student_id_no'] ?? '');
            $budget = (float)($_POST['monthly_budget'] ?? 0);
            $gender = sanitize($_POST['gender'] ?? '');

            if (!$name || !$email || !$role) $errors[] = 'Required fields missing.';
            
            if (empty($errors)) {
                $count = (int)oci_fetch_scalar('SELECT COUNT(*) FROM users WHERE email=:e AND user_id!=:uid', [':e'=>$email, ':uid'=>$editId]);
                if ($count > 0) $errors[] = 'Email already taken by another user.';
                else {
                    $sql = 'UPDATE users SET full_name=:n, email=:e, role_name=:r, department=:d, phone=:p, student_id_no=:sid, monthly_budget=:b, gender=:g';
                    $binds = [':n'=>$name,':e'=>$email,':r'=>$role,':d'=>$dept,':p'=>$phone,':sid'=>$sid,':b'=>$budget,':g'=>$gender,':uid'=>$editId];
                    if (!empty($pass)) {
                        if (strlen($pass) < 8) $errors[] = 'Password must be at least 8 chars.';
                        else {
                            $sql .= ', password_hash=:h';
                            $binds[':h'] = password_hash($pass, PASSWORD_BCRYPT);
                        }
                    }
                    if (empty($errors)) {
                        $sql .= ' WHERE user_id=:uid';
                        if (oci_execute_dml($sql, $binds)) {
                            setFlash('success', 'User updated.');
                            redirect(BASE_URL . '/pages/admin/manage_users.php');
                        } else $errors[] = 'Failed to update user.';
                    }
                }
            }
        }

        // Delete User
        if ($action === 'delete_user') {
            $delId = (int)$_POST['user_id'];
            if ($delId === $uid) $errors[] = 'Cannot delete your own account.';
            else {
                $bCount = (int)oci_fetch_scalar('SELECT COUNT(*) FROM bookings WHERE student_id=:u', [':u'=>$delId]);
                if ($bCount > 0) $errors[] = 'Cannot delete user with existing bookings.';
                else {
                    if (oci_execute_dml('DELETE FROM users WHERE user_id=:u', [':u'=>$delId])) {
                        setFlash('success', 'User deleted.');
                        redirect(BASE_URL . '/pages/admin/manage_users.php');
                    } else $errors[] = 'Database error deleting user.';
                }
            }
        }

        // Toggle Status
        if ($action === 'toggle_status') {
            $tId = (int)$_POST['user_id'];
            if ($tId === $uid) $errors[] = 'Cannot toggle your own status.';
            else {
                $cur = oci_fetch_scalar('SELECT account_status FROM users WHERE user_id=:u', [':u'=>$tId]);
                if ($cur) {
                    $new = ($cur === 'ACTIVE') ? 'INACTIVE' : 'ACTIVE';
                    if (oci_execute_dml('UPDATE users SET account_status=:s WHERE user_id=:u', [':s'=>$new, ':u'=>$tId])) {
                        setFlash('success', "User status set to $new.");
                        redirect(BASE_URL . '/pages/admin/manage_users.php');
                    } else $errors[] = 'Failed to toggle status.';
                }
            }
        }
    }
}

// ── GET & LIST ─────────────────────────────────────────────────────────────
$search       = sanitize($_GET['search'] ?? '');
$roleFilter   = sanitize($_GET['role'] ?? 'ALL');
$statusFilter = sanitize($_GET['status'] ?? 'ALL');
$currentPage  = max(1, (int)($_GET['page'] ?? 1));

$innerSql = 'SELECT * FROM users WHERE 1=1';
$binds    = [];

if ($search !== '') {
    $innerSql .= ' AND (UPPER(full_name) LIKE UPPER(:s) OR UPPER(email) LIKE UPPER(:s2))';
    $binds[':s']  = '%' . $search . '%';
    $binds[':s2'] = '%' . $search . '%';
}
if ($roleFilter !== 'ALL') {
    $innerSql .= ' AND role_name=:r';
    $binds[':r'] = $roleFilter;
}
if ($statusFilter !== 'ALL') {
    $innerSql .= ' AND account_status=:st';
    $binds[':st'] = $statusFilter;
}

$res   = oci_paginate($innerSql, $binds, $currentPage, 10, 'created_at DESC');
$users = $res['rows'];
$total = $res['total'];
$baseUrl = BASE_URL . '/pages/admin/manage_users.php?search='.urlencode($search).'&role='.urlencode($roleFilter).'&status='.urlencode($statusFilter);

$depts = ['CSE','EEE','ME','CE','BBA','ECO','ENG','LAW','PHY','MATH','CHEM','BIO','SOC','PSY'];

include ROOT . '/includes/header.php';
include ROOT . '/includes/sidebar.php';
?>
<div class="main-content">
<?php include ROOT . '/includes/navbar_top.php'; ?>
<div class="content-area">

<?php if(!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible"><button class="btn-close" data-bs-dismiss="alert"></button>
<?php foreach($errors as $e) echo "<div><i class='fas fa-exclamation-circle me-1'></i>".htmlspecialchars($e)."</div>"; ?>
</div>
<?php endif; ?>
<?php $flash = getFlash(); if($flash): ?>
<div class="alert alert-<?=$flash['type']?> alert-dismissible"><button class="btn-close" data-bs-dismiss="alert"></button><?=htmlspecialchars($flash['message'])?></div>
<?php endif; ?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <h1 class="page-heading mb-0"><i class="fas fa-users me-2 text-primary"></i>Manage Users</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="fas fa-plus me-1"></i>Add New User</button>
</div>

<div class="card mb-4 shadow-sm">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Search name or email..." value="<?=htmlspecialchars($search)?>">
            </div>
            <div class="col-md-3">
                <select name="role" class="form-select">
                    <option value="ALL">All Roles</option>
                    <option value="SYSTEM_ADMIN" <?=$roleFilter==='SYSTEM_ADMIN'?'selected':''?>>System Admin</option>
                    <option value="HALL_ADMIN" <?=$roleFilter==='HALL_ADMIN'?'selected':''?>>Hall Admin</option>
                    <option value="STUDENT" <?=$roleFilter==='STUDENT'?'selected':''?>>Student</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="ALL">All Statuses</option>
                    <option value="ACTIVE" <?=$statusFilter==='ACTIVE'?'selected':''?>>Active</option>
                    <option value="INACTIVE" <?=$statusFilter==='INACTIVE'?'selected':''?>>Inactive</option>
                    <option value="SUSPENDED" <?=$statusFilter==='SUSPENDED'?'selected':''?>>Suspended</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i></button>
                <a href="<?=BASE_URL?>/pages/admin/manage_users.php" class="btn btn-outline-secondary w-100"><i class="fas fa-times"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="table-wrapper table-responsive">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>User</th>
                <th>Role</th>
                <th>Department</th>
                <th>Student ID</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($users as $u): $isMe = ($u['USER_ID'] == $uid); ?>
            <tr>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar-circle" style="width:36px;height:36px;font-size:14px;"><?=strtoupper(substr($u['FULL_NAME'],0,1))?></div>
                        <div>
                            <div class="fw-semibold text-truncate" style="max-width:150px;"><?=htmlspecialchars($u['FULL_NAME'])?></div>
                            <div class="text-muted small text-truncate" style="max-width:150px;"><?=htmlspecialchars($u['EMAIL'])?></div>
                        </div>
                    </div>
                </td>
                <td><?=roleBadge($u['ROLE_NAME'])?></td>
                <td><?=htmlspecialchars($u['DEPARTMENT']??'—')?></td>
                <td><?=htmlspecialchars($u['STUDENT_ID_NO']??'—')?></td>
                <td><?=statusBadge($u['ACCOUNT_STATUS'])?></td>
                <td><?=formatDateShort($u['CREATED_AT'])?></td>
                <td>
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-primary edit-user-btn"
                                data-user_id="<?=(int)$u['USER_ID']?>"
                                data-full_name="<?=htmlspecialchars($u['FULL_NAME'])?>"
                                data-email="<?=htmlspecialchars($u['EMAIL'])?>"
                                data-role_name="<?=htmlspecialchars($u['ROLE_NAME'])?>"
                                data-department="<?=htmlspecialchars($u['DEPARTMENT']??'')?>"
                                data-phone="<?=htmlspecialchars($u['PHONE']??'')?>"
                                data-student_id_no="<?=htmlspecialchars($u['STUDENT_ID_NO']??'')?>"
                                data-monthly_budget="<?=(float)$u['MONTHLY_BUDGET']?>"
                                data-gender="<?=htmlspecialchars($u['GENDER']??'')?>"
                                data-bs-toggle="modal" data-bs-target="#editUserModal"><i class="fas fa-edit"></i></button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Toggle status for this user?');">
                            <?=csrfField()?><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="user_id" value="<?=(int)$u['USER_ID']?>">
                            <button type="submit" class="btn btn-sm btn-outline-warning" <?=$isMe?'disabled':''?>><i class="fas fa-sync-alt"></i></button>
                        </form>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this user completely? This cannot be undone.');">
                            <?=csrfField()?><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?=(int)$u['USER_ID']?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" <?=$isMe?'disabled':''?>><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if(empty($users)): ?><div class="p-4 text-center text-muted">No users found.</div><?php endif; ?>
</div>
<?php if($total>10): ?><div class="mt-3"><?=renderPagination($total,$currentPage,10,$baseUrl)?></div><?php endif; ?>

<!-- Modals -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="POST">
            <?=csrfField()?><input type="hidden" name="action" value="add_user">
            <div class="modal-header"><h5 class="modal-title">Add New User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-3">
                <div class="col-md-6"><label>Full Name *</label><input type="text" name="full_name" class="form-control" required></div>
                <div class="col-md-6"><label>Email *</label><input type="email" name="email" class="form-control" required></div>
                <div class="col-md-6"><label>Password *</label><input type="password" name="password" class="form-control" required minlength="8"></div>
                <div class="col-md-6"><label>Role *</label><select name="role_name" class="form-select" required><option value="STUDENT">Student</option><option value="HALL_ADMIN">Hall Admin</option><option value="SYSTEM_ADMIN">System Admin</option></select></div>
                <div class="col-md-6"><label>Department</label><select name="department" class="form-select"><option value="">-- None --</option><?php foreach($depts as $d): ?><option value="<?=$d?>"><?=$d?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label>Student ID No</label><input type="text" name="student_id_no" class="form-control"></div>
                <div class="col-md-4"><label>Phone</label><input type="text" name="phone" class="form-control"></div>
                <div class="col-md-4"><label>Monthly Budget</label><input type="number" name="monthly_budget" class="form-control" min="0" step="100"></div>
                <div class="col-md-4"><label>Gender</label><select name="gender" class="form-select"><option value="">-- Select --</option><option value="MALE">Male</option><option value="FEMALE">Female</option><option value="OTHER">Other</option></select></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Add User</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="POST">
            <?=csrfField()?><input type="hidden" name="action" value="edit_user"><input type="hidden" name="user_id" id="edit_user_id">
            <div class="modal-header"><h5 class="modal-title">Edit User</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-3">
                <div class="col-md-6"><label>Full Name *</label><input type="text" name="full_name" id="edit_full_name" class="form-control" required></div>
                <div class="col-md-6"><label>Email *</label><input type="email" name="email" id="edit_email" class="form-control" required></div>
                <div class="col-md-6"><label>Password</label><input type="password" name="password" class="form-control" placeholder="Leave blank to keep current"></div>
                <div class="col-md-6"><label>Role *</label><select name="role_name" id="edit_role_name" class="form-select" required><option value="STUDENT">Student</option><option value="HALL_ADMIN">Hall Admin</option><option value="SYSTEM_ADMIN">System Admin</option></select></div>
                <div class="col-md-6"><label>Department</label><select name="department" id="edit_department" class="form-select"><option value="">-- None --</option><?php foreach($depts as $d): ?><option value="<?=$d?>"><?=$d?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label>Student ID No</label><input type="text" name="student_id_no" id="edit_student_id_no" class="form-control"></div>
                <div class="col-md-4"><label>Phone</label><input type="text" name="phone" id="edit_phone" class="form-control"></div>
                <div class="col-md-4"><label>Monthly Budget</label><input type="number" name="monthly_budget" id="edit_monthly_budget" class="form-control" min="0" step="100"></div>
                <div class="col-md-4"><label>Gender</label><select name="gender" id="edit_gender" class="form-select"><option value="">-- Select --</option><option value="MALE">Male</option><option value="FEMALE">Female</option><option value="OTHER">Other</option></select></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Save Changes</button></div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.edit-user-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            ['user_id','full_name','email','role_name','department','phone','student_id_no','monthly_budget','gender'].forEach(f => {
                let val = btn.getAttribute('data-' + f);
                let el = document.getElementById('edit_' + f);
                if(el) el.value = val;
            });
        });
    });
});
</script>

</div>
</div>
<?php include ROOT . '/includes/footer.php'; ?>
