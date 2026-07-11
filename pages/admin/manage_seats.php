<?php
/**
 * NestSync — Admin: Manage Seats
 * OCI8 / Oracle
 */
require_once '../../config/config.php';
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/auth_check.php';
require_once ROOT . '/includes/functions.php';

requireRole(['SYSTEM_ADMIN', 'HALL_ADMIN']);
$pageTitle  = 'Manage Seats';
$activePage = 'seats';
$uid        = currentUserId();

$errors = [];

// ── POST ACTIONS ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add_seat') {
            $rid = (int)($_POST['room_id'] ?? 0);
            $lbl = sanitize($_POST['seat_label'] ?? '');
            $st  = sanitize($_POST['seat_status'] ?? 'AVAILABLE');
            
            if(empty($errors)) {
                if(oci_execute_dml('INSERT INTO seats(room_id,seat_label,seat_status) VALUES(:r,:l,:s)', [':r'=>$rid,':l'=>$lbl,':s'=>$st])) {
                    // Update room capacity
                    oci_execute_dml('UPDATE rooms SET capacity = capacity + 1 WHERE room_id=:r', [':r'=>$rid]);
                    setFlash('success', 'Seat added.'); redirect(BASE_URL . '/pages/admin/manage_seats.php?room_id='.$rid);
                } else $errors[] = 'Failed to add seat.';
            }
        }

        if ($action === 'edit_seat') {
            $sid = (int)($_POST['seat_id'] ?? 0);
            $lbl = sanitize($_POST['seat_label'] ?? '');
            $st  = sanitize($_POST['seat_status'] ?? '');
            $stu = (int)($_POST['current_student_id'] ?? 0);
            
            if(empty($errors)) {
                $ok = oci_execute_dml('UPDATE seats SET seat_label=:l, seat_status=:s, current_student_id=:u WHERE seat_id=:sid', 
                                      [':l'=>$lbl, ':s'=>$st, ':u'=>$stu?:null, ':sid'=>$sid]);
                if($ok) { setFlash('success', 'Seat updated.'); redirect(BASE_URL . '/pages/admin/manage_seats.php'); }
                else $errors[] = 'Failed to update seat.';
            }
        }

        if ($action === 'unassign_seat') {
            $sid = (int)($_POST['seat_id'] ?? 0);
            global $conn;
            
            // Find if there is an APPROVED booking for this seat to properly revoke it
            $bId = (int)oci_fetch_scalar(
                "SELECT booking_id FROM bookings WHERE seat_id=:s AND booking_status='APPROVED'",
                [':s' => $sid]
            );

            if ($bId > 0) {
                // Call sp_reject_booking to handle everything gracefully (status update, seat release, notification)
                $stmt = oci_parse($conn, 'BEGIN PKG_NESTSYNC.sp_reject_booking(:bid,:uid,:rem); END;');
                oci_bind_by_name($stmt, ':bid', $bId);
                oci_bind_by_name($stmt, ':uid', $uid);
                $remarks = 'Administratively unassigned from seat management.';
                oci_bind_by_name($stmt, ':rem', $remarks);
                
                if (@oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
                    setFlash('success', 'Student unassigned and booking revoked successfully.');
                } else {
                    $errors[] = 'Failed to revoke booking and unassign seat.';
                }
                oci_free_statement($stmt);
            } else {
                // Fallback for data inconsistency: just release the seat if no active booking found
                if(oci_execute_dml('UPDATE seats SET seat_status=\'AVAILABLE\', current_student_id=NULL WHERE seat_id=:s', [':s'=>$sid])) {
                    setFlash('success', 'Student unassigned. Seat is now available.');
                } else {
                    $errors[] = 'Failed to unassign seat.';
                }
            }
            if (empty($errors)) redirect(BASE_URL . '/pages/admin/manage_seats.php');
        }

        if ($action === 'delete_seat') {
            $sid = (int)($_POST['seat_id'] ?? 0);
            $r = oci_fetch_one_assoc('SELECT room_id, seat_status FROM seats WHERE seat_id=:s', [':s'=>$sid]);
            if ($r && $r['SEAT_STATUS'] !== 'AVAILABLE') $errors[] = 'Cannot delete a booked or occupied seat.';
            else if ($r) {
                if(oci_execute_dml('DELETE FROM seats WHERE seat_id=:s', [':s'=>$sid])) {
                    oci_execute_dml('UPDATE rooms SET capacity = capacity - 1 WHERE room_id=:r', [':r'=>$r['ROOM_ID']]);
                    setFlash('success', 'Seat deleted.'); redirect(BASE_URL . '/pages/admin/manage_seats.php');
                } else $errors[] = 'Failed to delete seat.';
            }
        }
    }
}

// ── GET & LIST ─────────────────────────────────────────────────────────────
$hallFilter  = (int)($_GET['hall_id'] ?? 0);
$roomFilter  = (int)($_GET['room_id'] ?? 0);
$statFilter  = sanitize($_GET['status'] ?? 'ALL');
$currentPage = max(1, (int)($_GET['page'] ?? 1));

// Stats
$statsSql = "SELECT s.seat_status, COUNT(*) as cnt FROM seats s JOIN rooms r ON s.room_id=r.room_id JOIN halls h ON r.hall_id=h.hall_id WHERE 1=1";
if(isHallAdmin()) $statsSql .= " AND h.managed_by=".(int)$uid;
$statsSql .= " GROUP BY s.seat_status";
$statsRaw = oci_fetch_all_assoc($statsSql);
$stats = ['AVAILABLE'=>0,'BOOKED'=>0,'OCCUPIED'=>0,'MAINTENANCE'=>0,'TOTAL'=>0];
foreach($statsRaw as $sr) { $stats[$sr['SEAT_STATUS']] = (int)$sr['CNT']; $stats['TOTAL'] += (int)$sr['CNT']; }

$innerSql = "SELECT s.*, r.room_number, h.hall_name, u.full_name as student_name, u.email as student_email 
             FROM seats s 
             JOIN rooms r ON s.room_id=r.room_id 
             JOIN halls h ON r.hall_id=h.hall_id 
             LEFT JOIN users u ON s.current_student_id=u.user_id 
             WHERE 1=1";
$binds = [];
if(isHallAdmin()) { $innerSql .= ' AND h.managed_by=:usr_id'; $binds[':usr_id'] = $uid; }
if($hallFilter > 0) { $innerSql .= ' AND h.hall_id=:hid'; $binds[':hid'] = $hallFilter; }
if($roomFilter > 0) { $innerSql .= ' AND r.room_id=:rid'; $binds[':rid'] = $roomFilter; }
if($statFilter !== 'ALL') { $innerSql .= ' AND s.seat_status=:st'; $binds[':st'] = $statFilter; }

$res = oci_paginate($innerSql, $binds, $currentPage, 20, 'hall_name ASC, room_number ASC, seat_label ASC');
$seats = $res['rows'];
$total = $res['total'];
$baseUrl = BASE_URL . '/pages/admin/manage_seats.php?hall_id='.$hallFilter.'&room_id='.$roomFilter.'&status='.urlencode($statFilter);

$hallsSql = 'SELECT hall_id, hall_name FROM halls';
if (isHallAdmin()) $hallsSql .= ' WHERE managed_by=' . (int)$uid;
$halls = oci_fetch_all_assoc($hallsSql);
$rooms = [];
if ($hallFilter > 0) {
    $rooms = oci_fetch_all_assoc('SELECT room_id, room_number FROM rooms WHERE hall_id=:h ORDER BY room_number', [':h'=>$hallFilter]);
}

$unassignedStudents = oci_fetch_all_assoc("SELECT user_id, full_name, student_id_no FROM users WHERE role_name='STUDENT' AND account_status='ACTIVE' AND user_id NOT IN (SELECT current_student_id FROM seats WHERE current_student_id IS NOT NULL)");

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
    <h1 class="page-heading mb-0"><i class="fas fa-chair me-2 text-primary"></i>Manage Seats</h1>
    <?php if($roomFilter>0): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSeatModal"><i class="fas fa-plus me-1"></i>Add Seat to Room</button>
    <?php endif; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col"><div class="stat-card stat-card-blue"><div class="stat-body"><p class="stat-number"><?=$stats['TOTAL']?></p><p class="stat-label">Total Seats</p></div></div></div>
    <div class="col"><div class="stat-card stat-card-green"><div class="stat-body"><p class="stat-number"><?=$stats['AVAILABLE']?></p><p class="stat-label">Available</p></div></div></div>
    <div class="col"><div class="stat-card stat-card-orange"><div class="stat-body"><p class="stat-number"><?=$stats['BOOKED']?></p><p class="stat-label">Booked</p></div></div></div>
    <div class="col"><div class="stat-card stat-card-purple"><div class="stat-body"><p class="stat-number"><?=$stats['OCCUPIED']?></p><p class="stat-label">Occupied</p></div></div></div>
    <div class="col"><div class="stat-card stat-card-secondary"><div class="stat-body"><p class="stat-number"><?=$stats['MAINTENANCE']?></p><p class="stat-label">Maintenance</p></div></div></div>
</div>

<div class="card mb-4 shadow-sm">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end" id="filterForm">
            <div class="col-md-3">
                <select name="hall_id" class="form-select" onchange="document.getElementById('filterForm').submit()">
                    <option value="0">All Halls</option>
                    <?php foreach($halls as $h): ?>
                    <option value="<?=(int)$h['HALL_ID']?>" <?=$hallFilter==(int)$h['HALL_ID']?'selected':''?>><?=htmlspecialchars($h['HALL_NAME'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="room_id" class="form-select" <?=empty($rooms)?'disabled':''?>>
                    <option value="0">All Rooms</option>
                    <?php foreach($rooms as $r): ?>
                    <option value="<?=(int)$r['ROOM_ID']?>" <?=$roomFilter==(int)$r['ROOM_ID']?'selected':''?>>Room <?=htmlspecialchars($r['ROOM_NUMBER'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="ALL">All Status</option>
                    <option value="AVAILABLE" <?=$statFilter==='AVAILABLE'?'selected':''?>>Available</option>
                    <option value="BOOKED" <?=$statFilter==='BOOKED'?'selected':''?>>Booked</option>
                    <option value="OCCUPIED" <?=$statFilter==='OCCUPIED'?'selected':''?>>Occupied</option>
                    <option value="MAINTENANCE" <?=$statFilter==='MAINTENANCE'?'selected':''?>>Maintenance</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
                <a href="<?=BASE_URL?>/pages/admin/manage_seats.php" class="btn btn-outline-secondary w-100">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="table-wrapper table-responsive">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>Hall</th><th>Room</th><th>Seat Label</th><th>Current Occupant</th>
                <th>Status</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($seats as $s): $sid=(int)$s['SEAT_ID']; ?>
            <tr>
                <td class="fw-medium"><?=htmlspecialchars($s['HALL_NAME'])?></td>
                <td><?=htmlspecialchars($s['ROOM_NUMBER'])?></td>
                <td class="fw-bold fs-6 text-primary"><?=htmlspecialchars($s['SEAT_LABEL'])?></td>
                <td>
                    <?php if($s['STUDENT_NAME']): ?>
                        <div class="fw-semibold"><?=htmlspecialchars($s['STUDENT_NAME'])?></div>
                        <div class="small text-muted"><?=htmlspecialchars($s['STUDENT_EMAIL'])?></div>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td><?=statusBadge($s['SEAT_STATUS'])?></td>
                <td>
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm btn-outline-primary edit-seat-btn"
                                data-id="<?=$sid?>" data-lbl="<?=htmlspecialchars($s['SEAT_LABEL'])?>"
                                data-status="<?=htmlspecialchars($s['SEAT_STATUS'])?>" data-stu="<?=(int)($s['CURRENT_STUDENT_ID']??0)?>"
                                data-bs-toggle="modal" data-bs-target="#editSeatModal"><i class="fas fa-edit"></i></button>
                        <?php if($s['CURRENT_STUDENT_ID']): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Unassign student?');">
                            <?=csrfField()?><input type="hidden" name="action" value="unassign_seat"><input type="hidden" name="seat_id" value="<?=$sid?>">
                            <button type="submit" class="btn btn-sm btn-outline-warning" title="Unassign"><i class="fas fa-user-minus"></i></button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this seat?');">
                            <?=csrfField()?><input type="hidden" name="action" value="delete_seat"><input type="hidden" name="seat_id" value="<?=$sid?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" <?=$s['SEAT_STATUS']!=='AVAILABLE'?'disabled':''?>><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if(empty($seats)): ?><div class="p-4 text-center text-muted">No seats found.</div><?php endif; ?>
</div>
<?php if($total>20): ?><div class="mt-3"><?=renderPagination($total,$currentPage,20,$baseUrl)?></div><?php endif; ?>

<!-- Add Seat -->
<?php if($roomFilter>0): ?>
<div class="modal fade" id="addSeatModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST">
            <?=csrfField()?><input type="hidden" name="action" value="add_seat"><input type="hidden" name="room_id" value="<?=$roomFilter?>">
            <div class="modal-header"><h5 class="modal-title">Add Seat</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-3">
                <div class="col-md-6"><label>Seat Label *</label><input type="text" name="seat_label" class="form-control" required placeholder="e.g. S1"></div>
                <div class="col-md-6"><label>Status</label><select name="seat_status" class="form-select"><option value="AVAILABLE">Available</option><option value="MAINTENANCE">Maintenance</option></select></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Add Seat</button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Edit Seat -->
<div class="modal fade" id="editSeatModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST">
            <?=csrfField()?><input type="hidden" name="action" value="edit_seat"><input type="hidden" name="seat_id" id="edit_seat_id">
            <div class="modal-header"><h5 class="modal-title">Edit Seat</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-3">
                <div class="col-md-6"><label>Seat Label *</label><input type="text" name="seat_label" id="edit_lbl" class="form-control" required></div>
                <div class="col-md-6"><label>Status</label><select name="seat_status" id="edit_st" class="form-select"><option value="AVAILABLE">Available</option><option value="BOOKED">Booked</option><option value="OCCUPIED">Occupied</option><option value="MAINTENANCE">Maintenance</option></select></div>
                <div class="col-12">
                    <label>Assign to Student (Optional)</label>
                    <select name="current_student_id" id="edit_stu" class="form-select">
                        <option value="0">-- None (Unassigned) --</option>
                        <?php foreach($unassignedStudents as $stu): ?>
                        <option value="<?=(int)$stu['USER_ID']?>"><?=htmlspecialchars($stu['FULL_NAME'])?> (<?=htmlspecialchars($stu['STUDENT_ID_NO'])?>)</option>
                        <?php endforeach; ?>
                        <!-- To allow keeping current occupant if they are not in the unassigned list, JavaScript will inject an option if needed -->
                    </select>
                </div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-primary">Save Changes</button></div>
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.edit-seat-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('edit_seat_id').value = btn.getAttribute('data-id');
            document.getElementById('edit_lbl').value = btn.getAttribute('data-lbl');
            document.getElementById('edit_st').value = btn.getAttribute('data-status');
            
            let stuSel = document.getElementById('edit_stu');
            let stuId = btn.getAttribute('data-stu');
            
            if(stuId != "0" && !stuSel.querySelector(`option[value="${stuId}"]`)) {
                let opt = document.createElement('option');
                opt.value = stuId;
                opt.text = 'Current Occupant (ID ' + stuId + ')';
                stuSel.appendChild(opt);
            }
            stuSel.value = stuId;
        });
    });
});
</script>

</div>
</div>
<?php include ROOT . '/includes/footer.php'; ?>
