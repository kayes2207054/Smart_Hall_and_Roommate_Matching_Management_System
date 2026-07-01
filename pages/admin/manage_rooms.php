<?php
/**
 * NestSync — Admin: Manage Rooms
 * OCI8 / Oracle
 */
require_once '../../config/config.php';
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/auth_check.php';
require_once ROOT . '/includes/functions.php';

requireRole(['SYSTEM_ADMIN', 'HALL_ADMIN']);
$pageTitle  = 'Manage Rooms';
$activePage = 'rooms';
$uid        = currentUserId();

$errors = [];

function capForType($t) { return match($t){'SINGLE'=>1,'DOUBLE'=>2,'TRIPLE'=>3,'QUAD'=>4,default=>1}; }

// ── POST ACTIONS ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add_room') {
            $hid   = (int)$_POST['hall_id'];
            $rnum  = sanitize($_POST['room_number']);
            $rtype = sanitize($_POST['room_type']);
            $cap   = (int)($_POST['capacity'] ?? capForType($rtype));
            $floor = (int)$_POST['floor_number'];
            $rent  = (float)$_POST['monthly_rent'];
            $fac   = sanitize($_POST['facilities'] ?? '');
            $stat  = sanitize($_POST['room_status'] ?? 'ACTIVE');

            if (isHallAdmin()) {
                $mgr = (int)oci_fetch_scalar('SELECT managed_by FROM halls WHERE hall_id=:h', [':h'=>$hid]);
                if ($mgr !== $uid) $errors[] = 'You cannot add rooms to this hall.';
            }

            if (empty($errors)) {
                $chk = (int)oci_fetch_scalar('SELECT COUNT(*) FROM rooms WHERE hall_id=:h AND room_number=:r', [':h'=>$hid,':r'=>$rnum]);
                if ($chk > 0) $errors[] = 'Room number already exists in this hall.';
                else {
                    global $conn;
                    oci_execute(oci_parse($conn, 'SAVEPOINT sp_add_room'));
                    $rId = (int)oci_fetch_scalar("SELECT seq_rooms.NEXTVAL FROM DUAL");
                    $ok = oci_execute_dml(
                        'INSERT INTO rooms (room_id,hall_id,room_number,room_type,capacity,floor_number,monthly_rent,facilities,room_status)
                         VALUES (:rid,:h,:r,:t,:c,:f,:m,:fac,:s)',
                        [':rid'=>$rId,':h'=>$hid,':r'=>$rnum,':t'=>$rtype,':c'=>$cap,':f'=>$floor,':m'=>$rent,':fac'=>$fac,':s'=>$stat]
                    );
                    if ($ok) {
                        for($i=1; $i<=$cap; $i++) {
                            oci_execute_dml(
                                'INSERT INTO seats (room_id,seat_label,seat_status) VALUES (:r,:lbl,\'AVAILABLE\')',
                                [':r'=>$rId, ':lbl'=>"S$i"]
                            );
                        }
                        setFlash('success', 'Room and seats created.');
                        redirect(BASE_URL . '/pages/admin/manage_rooms.php');
                    } else {
                        oci_execute(oci_parse($conn, 'ROLLBACK TO sp_add_room'));
                        $errors[] = 'Database error creating room.';
                    }
                }
            }
        }

        if ($action === 'edit_room') {
            $rid   = (int)$_POST['room_id'];
            $rtype = sanitize($_POST['room_type']);
            $floor = (int)$_POST['floor_number'];
            $rent  = (float)$_POST['monthly_rent'];
            $fac   = sanitize($_POST['facilities'] ?? '');
            $stat  = sanitize($_POST['room_status'] ?? 'ACTIVE');
            
            if (empty($errors)) {
                $ok = oci_execute_dml(
                    'UPDATE rooms SET room_type=:t, floor_number=:f, monthly_rent=:m, facilities=:fac, room_status=:s WHERE room_id=:rid',
                    [':t'=>$rtype,':f'=>$floor,':m'=>$rent,':fac'=>$fac,':s'=>$stat,':rid'=>$rid]
                );
                if ($ok) { setFlash('success', 'Room updated.'); redirect(BASE_URL . '/pages/admin/manage_rooms.php'); }
                else $errors[] = 'Failed to update room.';
            }
        }

        if ($action === 'delete_room') {
            $rid = (int)$_POST['room_id'];
            $b = (int)oci_fetch_scalar('SELECT COUNT(*) FROM seats WHERE room_id=:r AND seat_status!=\'AVAILABLE\'', [':r'=>$rid]);
            if ($b > 0) $errors[] = 'Cannot delete room with booked or occupied seats.';
            else {
                oci_execute_dml('DELETE FROM seats WHERE room_id=:r', [':r'=>$rid]);
                if (oci_execute_dml('DELETE FROM rooms WHERE room_id=:r', [':r'=>$rid])) {
                    setFlash('success', 'Room and vacant seats deleted.'); redirect(BASE_URL . '/pages/admin/manage_rooms.php');
                } else $errors[] = 'Failed to delete room.';
            }
        }
    }
}

// ── GET & LIST ─────────────────────────────────────────────────────────────
$hallFilter  = (int)($_GET['hall_id'] ?? 0);
$typeFilter  = sanitize($_GET['type'] ?? 'ALL');
$statFilter  = sanitize($_GET['status'] ?? 'ALL');
$search      = sanitize($_GET['search'] ?? '');
$currentPage = max(1, (int)($_GET['page'] ?? 1));

$innerSql = 'SELECT r.*, h.hall_name,
            (SELECT COUNT(*) FROM seats s WHERE s.room_id=r.room_id) as total_seats,
            (SELECT COUNT(*) FROM seats s WHERE s.room_id=r.room_id AND s.seat_status=\'AVAILABLE\') as avail_seats
            FROM rooms r JOIN halls h ON r.hall_id=h.hall_id WHERE 1=1';
$binds = [];
if (isHallAdmin()) {
    $innerSql .= ' AND h.managed_by=:usr_id';
    $binds[':usr_id'] = $uid;
}
if ($hallFilter > 0) { $innerSql .= ' AND r.hall_id=:hid'; $binds[':hid'] = $hallFilter; }
if ($typeFilter !== 'ALL') { $innerSql .= ' AND r.room_type=:t'; $binds[':t'] = $typeFilter; }
if ($statFilter !== 'ALL') { $innerSql .= ' AND r.room_status=:s'; $binds[':s'] = $statFilter; }
if ($search !== '') { $innerSql .= ' AND r.room_number LIKE :search'; $binds[':search'] = '%'.$search.'%'; }

$res = oci_paginate($innerSql, $binds, $currentPage, 15, 'hall_name ASC, floor_number ASC, room_number ASC');
$rooms = $res['rows'];
$total = $res['total'];
$baseUrl = BASE_URL . '/pages/admin/manage_rooms.php?hall_id='.$hallFilter.'&type='.urlencode($typeFilter).'&status='.urlencode($statFilter).'&search='.urlencode($search);

$hallsSql = 'SELECT hall_id, hall_name FROM halls';
if (isHallAdmin()) $hallsSql .= ' WHERE managed_by=' . (int)$uid;
$hallsSql .= ' ORDER BY hall_name';
$halls = oci_fetch_all_assoc($hallsSql);

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
    <h1 class="page-heading mb-0"><i class="fas fa-door-closed me-2 text-primary"></i>Manage Rooms</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoomModal"><i class="fas fa-plus me-1"></i>Add New Room</button>
</div>

<div class="card mb-4 shadow-sm">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <select name="hall_id" class="form-select">
                    <option value="0">All Halls</option>
                    <?php foreach($halls as $h): ?>
                    <option value="<?=(int)$h['HALL_ID']?>" <?=$hallFilter==(int)$h['HALL_ID']?'selected':''?>><?=htmlspecialchars($h['HALL_NAME'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="type" class="form-select">
                    <option value="ALL">All Types</option>
                    <option value="SINGLE" <?=$typeFilter==='SINGLE'?'selected':''?>>Single</option>
                    <option value="DOUBLE" <?=$typeFilter==='DOUBLE'?'selected':''?>>Double</option>
                    <option value="TRIPLE" <?=$typeFilter==='TRIPLE'?'selected':''?>>Triple</option>
                    <option value="QUAD" <?=$typeFilter==='QUAD'?'selected':''?>>Quad</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="ALL">All Status</option>
                    <option value="ACTIVE" <?=$statFilter==='ACTIVE'?'selected':''?>>Active</option>
                    <option value="MAINTENANCE" <?=$statFilter==='MAINTENANCE'?'selected':''?>>Maintenance</option>
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Search Room Number..." value="<?=htmlspecialchars($search)?>">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i></button>
                <a href="<?=BASE_URL?>/pages/admin/manage_rooms.php" class="btn btn-outline-secondary w-100"><i class="fas fa-times"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="table-wrapper table-responsive">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>Room No</th><th>Hall Name</th><th>Floor</th><th>Type</th>
                <th>Rent/Mo</th><th>Facilities</th><th>Seats (Avail/Tot)</th><th>Status</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($rooms as $r): $rid=(int)$r['ROOM_ID']; ?>
            <tr>
                <td class="fw-bold"><?=htmlspecialchars($r['ROOM_NUMBER'])?></td>
                <td class="fw-medium"><?=htmlspecialchars($r['HALL_NAME'])?></td>
                <td><?=(int)$r['FLOOR_NUMBER']?></td>
                <td><?=statusBadge($r['ROOM_TYPE'])?></td>
                <td class="text-success fw-bold"><?=formatCurrency((float)$r['MONTHLY_RENT'])?></td>
                <td><span class="small text-muted" title="<?=htmlspecialchars($r['FACILITIES']??'')?>"><?=htmlspecialchars(truncate($r['FACILITIES']??'', 30))?></span></td>
                <td><span class="badge <?=(int)$r['AVAIL_SEATS']>0?'bg-success':'bg-danger'?>"><?=(int)$r['AVAIL_SEATS']?> / <?=(int)$r['TOTAL_SEATS']?></span></td>
                <td><?=statusBadge($r['ROOM_STATUS'])?></td>
                <td>
                    <div class="d-flex gap-1">
                        <a href="<?=BASE_URL?>/pages/admin/manage_seats.php?room_id=<?=$rid?>" class="btn btn-sm btn-outline-info" title="Manage Seats"><i class="fas fa-chair"></i></a>
                        <button class="btn btn-sm btn-outline-primary edit-room-btn"
                                data-id="<?=$rid?>" data-type="<?=htmlspecialchars($r['ROOM_TYPE'])?>"
                                data-floor="<?=(int)$r['FLOOR_NUMBER']?>" data-rent="<?=(float)$r['MONTHLY_RENT']?>"
                                data-fac="<?=htmlspecialchars($r['FACILITIES']??'')?>" data-status="<?=htmlspecialchars($r['ROOM_STATUS'])?>"
                                data-bs-toggle="modal" data-bs-target="#editRoomModal"><i class="fas fa-edit"></i></button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete room and all unoccupied seats?');">
                            <?=csrfField()?><input type="hidden" name="action" value="delete_room"><input type="hidden" name="room_id" value="<?=$rid?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if(empty($rooms)): ?><div class="p-4 text-center text-muted">No rooms found.</div><?php endif; ?>
</div>
<?php if($total>15): ?><div class="mt-3"><?=renderPagination($total,$currentPage,15,$baseUrl)?></div><?php endif; ?>

<!-- Add Room -->
<div class="modal fade" id="addRoomModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="POST">
            <?=csrfField()?><input type="hidden" name="action" value="add_room">
            <div class="modal-header"><h5 class="modal-title">Add New Room</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-3">
                <div class="col-md-6"><label>Hall *</label><select name="hall_id" class="form-select" required><option value="">-- Select --</option><?php foreach($halls as $h): ?><option value="<?=(int)$h['HALL_ID']?>"><?=htmlspecialchars($h['HALL_NAME'])?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label>Room Number *</label><input type="text" name="room_number" class="form-control" required placeholder="e.g. 101A"></div>
                <div class="col-md-4"><label>Floor *</label><input type="number" name="floor_number" class="form-control" required min="1"></div>
                <div class="col-md-4"><label>Type *</label><select name="room_type" class="form-select" required onchange="document.getElementById('add_cap').value=this.value==='SINGLE'?1:this.value==='DOUBLE'?2:this.value==='TRIPLE'?3:4"><option value="SINGLE">Single</option><option value="DOUBLE">Double</option><option value="TRIPLE">Triple</option><option value="QUAD">Quad</option></select></div>
                <div class="col-md-4"><label>Capacity (Auto)</label><input type="number" name="capacity" id="add_cap" class="form-control" value="1" readonly></div>
                <div class="col-md-6"><label>Monthly Rent (BDT) *</label><input type="number" name="monthly_rent" class="form-control" required min="0" step="100"></div>
                <div class="col-md-6"><label>Status</label><select name="room_status" class="form-select"><option value="ACTIVE">Active</option><option value="MAINTENANCE">Maintenance</option></select></div>
                <div class="col-12"><label>Facilities</label><input type="text" name="facilities" class="form-control" placeholder="e.g. AC, Attached Bath"></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Create Room & Seats</button></div>
        </form>
    </div>
</div>

<!-- Edit Room -->
<div class="modal fade" id="editRoomModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="POST">
            <?=csrfField()?><input type="hidden" name="action" value="edit_room"><input type="hidden" name="room_id" id="edit_room_id">
            <div class="modal-header"><h5 class="modal-title">Edit Room Settings</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-3">
                <div class="col-md-4"><label>Floor *</label><input type="number" name="floor_number" id="edit_floor" class="form-control" required min="1"></div>
                <div class="col-md-4"><label>Type *</label><select name="room_type" id="edit_type" class="form-select" required><option value="SINGLE">Single</option><option value="DOUBLE">Double</option><option value="TRIPLE">Triple</option><option value="QUAD">Quad</option></select></div>
                <div class="col-md-4"><label>Monthly Rent (BDT) *</label><input type="number" name="monthly_rent" id="edit_rent" class="form-control" required min="0" step="100"></div>
                <div class="col-md-6"><label>Status</label><select name="room_status" id="edit_status" class="form-select"><option value="ACTIVE">Active</option><option value="MAINTENANCE">Maintenance</option></select></div>
                <div class="col-12"><label>Facilities</label><input type="text" name="facilities" id="edit_fac" class="form-control"></div>
                <div class="col-12"><p class="text-muted small">Note: Room number, hall, and capacity cannot be changed after creation. Delete and recreate if needed.</p></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Save Changes</button></div>
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.edit-room-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('edit_room_id').value = btn.getAttribute('data-id');
            document.getElementById('edit_type').value = btn.getAttribute('data-type');
            document.getElementById('edit_floor').value = btn.getAttribute('data-floor');
            document.getElementById('edit_rent').value = btn.getAttribute('data-rent');
            document.getElementById('edit_fac').value = btn.getAttribute('data-fac');
            document.getElementById('edit_status').value = btn.getAttribute('data-status');
        });
    });
});
</script>

</div>
</div>
<?php include ROOT . '/includes/footer.php'; ?>
