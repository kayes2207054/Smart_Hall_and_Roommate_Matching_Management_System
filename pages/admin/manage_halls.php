<?php
/**
 * NestSync — Admin: Manage Halls
 * OCI8 / Oracle
 */
require_once '../../config/config.php';
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/auth_check.php';
require_once ROOT . '/includes/functions.php';

requireRole(['SYSTEM_ADMIN']);
$pageTitle  = 'Manage Halls';
$activePage = 'halls';
$uid        = currentUserId();

$errors = [];

// ── POST ACTIONS ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add_hall') {
            $name     = sanitize($_POST['hall_name'] ?? '');
            $location = sanitize($_POST['hall_location'] ?? '');
            $cap      = (int)($_POST['total_capacity'] ?? 0);
            $gender   = sanitize($_POST['gender_type'] ?? '');
            $desc     = sanitize($_POST['description'] ?? '');
            $mgr      = (int)($_POST['managed_by'] ?? 0);
            $status   = sanitize($_POST['hall_status'] ?? 'ACTIVE');

            if (!$name || !$location || !$cap || !$gender) $errors[] = 'Required fields missing.';
            
            if (empty($errors)) {
                $ok = oci_execute_dml(
                    'INSERT INTO halls (hall_name,hall_location,total_capacity,gender_type,description,managed_by,hall_status)
                     VALUES (:n,:l,:c,:g,:d,:m,:s)',
                    [':n'=>$name,':l'=>$location,':c'=>$cap,':g'=>$gender,':d'=>$desc,':m'=>($mgr?:null),':s'=>$status]
                );
                if ($ok) { setFlash('success', 'Hall added.'); redirect(BASE_URL . '/pages/admin/manage_halls.php'); }
                else $errors[] = 'Failed to add hall.';
            }
        }

        if ($action === 'edit_hall') {
            $hid      = (int)($_POST['hall_id'] ?? 0);
            $name     = sanitize($_POST['hall_name'] ?? '');
            $location = sanitize($_POST['hall_location'] ?? '');
            $cap      = (int)($_POST['total_capacity'] ?? 0);
            $gender   = sanitize($_POST['gender_type'] ?? '');
            $desc     = sanitize($_POST['description'] ?? '');
            $mgr      = (int)($_POST['managed_by'] ?? 0);
            $status   = sanitize($_POST['hall_status'] ?? 'ACTIVE');

            if (!$name || !$location || !$cap || !$gender) $errors[] = 'Required fields missing.';

            if (empty($errors)) {
                $ok = oci_execute_dml(
                    'UPDATE halls SET hall_name=:n, hall_location=:l, total_capacity=:c, gender_type=:g, description=:d, managed_by=:m, hall_status=:s WHERE hall_id=:hid',
                    [':n'=>$name,':l'=>$location,':c'=>$cap,':g'=>$gender,':d'=>$desc,':m'=>($mgr?:null),':s'=>$status,':hid'=>$hid]
                );
                if ($ok) { setFlash('success', 'Hall updated.'); redirect(BASE_URL . '/pages/admin/manage_halls.php'); }
                else $errors[] = 'Failed to update hall.';
            }
        }

        if ($action === 'delete_hall') {
            $hid = (int)($_POST['hall_id'] ?? 0);
            $rcount = (int)oci_fetch_scalar('SELECT COUNT(*) FROM rooms WHERE hall_id=:h', [':h'=>$hid]);
            if ($rcount > 0) {
                $errors[] = 'Cannot delete hall containing rooms.';
            } else {
                if (oci_execute_dml('DELETE FROM halls WHERE hall_id=:h', [':h'=>$hid])) {
                    setFlash('success', 'Hall deleted.'); redirect(BASE_URL . '/pages/admin/manage_halls.php');
                } else $errors[] = 'Failed to delete hall.';
            }
        }
    }
}

// ── GET & LIST ─────────────────────────────────────────────────────────────
$halls = oci_fetch_all_assoc('SELECT * FROM vw_hall_occupancy ORDER BY hall_name');
$admins = oci_fetch_all_assoc('SELECT user_id, full_name, email FROM users WHERE role_name=\'HALL_ADMIN\' ORDER BY full_name');

$totHalls = count($halls);
$actHalls = 0; $mtnHalls = 0;
foreach($halls as $h) {
    if($h['HALL_STATUS']==='ACTIVE') $actHalls++;
    elseif($h['HALL_STATUS']==='MAINTENANCE') $mtnHalls++;
}

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
    <h1 class="page-heading mb-0"><i class="fas fa-building me-2 text-primary"></i>Manage Halls</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHallModal"><i class="fas fa-plus me-1"></i>Add New Hall</button>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="stat-card stat-card-blue"><div class="stat-icon"><i class="fas fa-building"></i></div><div class="stat-body"><p class="stat-number"><?=$totHalls?></p><p class="stat-label">Total Halls</p></div></div></div>
    <div class="col-md-4"><div class="stat-card stat-card-green"><div class="stat-icon"><i class="fas fa-check-circle"></i></div><div class="stat-body"><p class="stat-number"><?=$actHalls?></p><p class="stat-label">Active</p></div></div></div>
    <div class="col-md-4"><div class="stat-card stat-card-orange"><div class="stat-icon"><i class="fas fa-tools"></i></div><div class="stat-body"><p class="stat-number"><?=$mtnHalls?></p><p class="stat-label">Maintenance</p></div></div></div>
</div>

<div class="row g-4">
    <?php foreach($halls as $h): $occ = (float)$h['OCCUPANCY_PCT']; $hid=(int)$h['HALL_ID']; ?>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm border-0" style="border-radius:12px;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h5 class="fw-bold mb-0 text-primary"><?=htmlspecialchars($h['HALL_NAME'])?></h5>
                    <?=statusBadge($h['GENDER_TYPE'])?>
                </div>
                <p class="text-muted small mb-2"><i class="fas fa-map-marker-alt me-2"></i><?=htmlspecialchars($h['HALL_LOCATION'])?></p>
                <p class="text-muted small mb-3"><i class="fas fa-user-tie me-2"></i>Manager: <?=htmlspecialchars($h['MANAGER_NAME']??'Unassigned')?></p>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span>Capacity: <?=(int)$h['TOTAL_SEATS']?></span>
                        <span>Occupied: <?=(int)$h['BOOKED_SEATS']?></span>
                    </div>
                    <div class="score-bar"><div class="score-bar-fill <?=$occ>=90?'bg-danger':($occ>=70?'bg-warning':'bg-success')?>" style="width:<?=$occ?>%"></div></div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-auto pt-3 border-top">
                    <?=statusBadge($h['HALL_STATUS'])?>
                    <div class="d-flex gap-1">
                        <a href="<?=BASE_URL?>/pages/admin/manage_rooms.php?hall_id=<?=$hid?>" class="btn btn-sm btn-outline-info" title="Rooms"><i class="fas fa-door-open"></i></a>
                        <button class="btn btn-sm btn-outline-primary edit-hall-btn"
                                data-id="<?=$hid?>" data-name="<?=htmlspecialchars($h['HALL_NAME'])?>" data-loc="<?=htmlspecialchars($h['HALL_LOCATION'])?>"
                                data-cap="<?=(int)$h['TOTAL_CAPACITY']?>" data-gender="<?=htmlspecialchars($h['GENDER_TYPE'])?>"
                                data-desc="<?=htmlspecialchars($h['DESCRIPTION']??'')?>" data-mgr="<?=(int)($h['MANAGED_BY']??0)?>"
                                data-status="<?=htmlspecialchars($h['HALL_STATUS'])?>"
                                data-bs-toggle="modal" data-bs-target="#editHallModal"><i class="fas fa-edit"></i></button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this hall?');">
                            <?=csrfField()?><input type="hidden" name="action" value="delete_hall"><input type="hidden" name="hall_id" value="<?=$hid?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if(empty($halls)): ?><div class="col-12"><div class="p-5 text-center text-muted">No halls configured yet.</div></div><?php endif; ?>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addHallModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST">
            <?=csrfField()?><input type="hidden" name="action" value="add_hall">
            <div class="modal-header"><h5 class="modal-title">Add New Hall</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-3">
                <div class="col-md-12"><label>Hall Name *</label><input type="text" name="hall_name" class="form-control" required></div>
                <div class="col-md-12"><label>Location *</label><input type="text" name="hall_location" class="form-control" required></div>
                <div class="col-md-6"><label>Capacity *</label><input type="number" name="total_capacity" class="form-control" required min="1"></div>
                <div class="col-md-6"><label>Gender Type *</label><select name="gender_type" class="form-select" required><option value="MALE">Male</option><option value="FEMALE">Female</option><option value="MIXED">Mixed</option></select></div>
                <div class="col-md-6"><label>Managed By</label><select name="managed_by" class="form-select"><option value="0">-- Unassigned --</option><?php foreach($admins as $a): ?><option value="<?=(int)$a['USER_ID']?>"><?=htmlspecialchars($a['FULL_NAME'])?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label>Status</label><select name="hall_status" class="form-select"><option value="ACTIVE">Active</option><option value="INACTIVE">Inactive</option><option value="MAINTENANCE">Maintenance</option></select></div>
                <div class="col-12"><label>Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Add Hall</button></div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editHallModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST">
            <?=csrfField()?><input type="hidden" name="action" value="edit_hall"><input type="hidden" name="hall_id" id="edit_hall_id">
            <div class="modal-header"><h5 class="modal-title">Edit Hall</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-3">
                <div class="col-md-12"><label>Hall Name *</label><input type="text" name="hall_name" id="edit_hall_name" class="form-control" required></div>
                <div class="col-md-12"><label>Location *</label><input type="text" name="hall_location" id="edit_hall_location" class="form-control" required></div>
                <div class="col-md-6"><label>Capacity *</label><input type="number" name="total_capacity" id="edit_total_capacity" class="form-control" required min="1"></div>
                <div class="col-md-6"><label>Gender Type *</label><select name="gender_type" id="edit_gender_type" class="form-select" required><option value="MALE">Male</option><option value="FEMALE">Female</option><option value="MIXED">Mixed</option></select></div>
                <div class="col-md-6"><label>Managed By</label><select name="managed_by" id="edit_managed_by" class="form-select"><option value="0">-- Unassigned --</option><?php foreach($admins as $a): ?><option value="<?=(int)$a['USER_ID']?>"><?=htmlspecialchars($a['FULL_NAME'])?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label>Status</label><select name="hall_status" id="edit_hall_status" class="form-select"><option value="ACTIVE">Active</option><option value="INACTIVE">Inactive</option><option value="MAINTENANCE">Maintenance</option></select></div>
                <div class="col-12"><label>Description</label><textarea name="description" id="edit_description" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Save Changes</button></div>
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.edit-hall-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('edit_hall_id').value = btn.getAttribute('data-id');
            document.getElementById('edit_hall_name').value = btn.getAttribute('data-name');
            document.getElementById('edit_hall_location').value = btn.getAttribute('data-loc');
            document.getElementById('edit_total_capacity').value = btn.getAttribute('data-cap');
            document.getElementById('edit_gender_type').value = btn.getAttribute('data-gender');
            document.getElementById('edit_managed_by').value = btn.getAttribute('data-mgr');
            document.getElementById('edit_hall_status').value = btn.getAttribute('data-status');
            document.getElementById('edit_description').value = btn.getAttribute('data-desc');
        });
    });
});
</script>

</div>
</div>
<?php include ROOT . '/includes/footer.php'; ?>
