<?php
/**
 * NestSync — Admin: Manage Bookings
 * OCI8 / Oracle
 */
require_once '../../config/config.php';
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/auth_check.php';
require_once ROOT . '/includes/functions.php';

requireRole(['SYSTEM_ADMIN', 'HALL_ADMIN']);
$pageTitle  = 'Manage Bookings';
$activePage = 'bookings';
$uid        = currentUserId();

$errors = [];

// ── POST ACTIONS ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? '';

        // Approve Booking
        if ($action === 'approve_booking') {
            $bookingId = (int)($_POST['booking_id'] ?? 0);
            $remarks   = sanitize($_POST['admin_remarks'] ?? '');

            global $conn;
            $stmt = oci_parse($conn, 'BEGIN PKG_NESTSYNC.sp_approve_booking(:bid,:usr_id,:rem); END;');
            oci_bind_by_name($stmt, ':bid', $bookingId);
            oci_bind_by_name($stmt, ':usr_id', $uid);
            oci_bind_by_name($stmt, ':rem', $remarks);
            $ok = @oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);
            $e = oci_error($stmt);
            oci_free_statement($stmt);

            if ($ok) setFlash('success', 'Booking approved.');
            else {
                $msg = $e['message'] ?? 'Failed to approve booking.';
                setFlash('danger', $msg);
            }
            redirect(BASE_URL . '/pages/admin/manage_bookings.php');
        }

        // Reject / Revoke Booking
        if ($action === 'reject_booking') {
            $bookingId = (int)($_POST['booking_id'] ?? 0);
            $remarks   = sanitize($_POST['admin_remarks'] ?? '');

            global $conn;
            $stmt = oci_parse($conn, 'BEGIN PKG_NESTSYNC.sp_reject_booking(:bid,:usr_id,:rem); END;');
            oci_bind_by_name($stmt, ':bid', $bookingId);
            oci_bind_by_name($stmt, ':usr_id', $uid);
            oci_bind_by_name($stmt, ':rem', $remarks);
            $ok = @oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);
            $e = oci_error($stmt);
            oci_free_statement($stmt);

            if ($ok) setFlash('success', 'Booking rejected/revoked.');
            else {
                $msg = $e['message'] ?? 'Failed to reject booking.';
                setFlash('danger', $msg);
            }
            redirect(BASE_URL . '/pages/admin/manage_bookings.php');
        }
    }
}

// ── GET & LIST ─────────────────────────────────────────────────────────────
$statusFilter = sanitize($_GET['status'] ?? 'ALL');
$hallFilter   = (int)($_GET['hall_id'] ?? 0);
$search       = sanitize($_GET['search'] ?? '');
$dateFrom     = sanitize($_GET['date_from'] ?? '');
$dateTo       = sanitize($_GET['date_to'] ?? '');
$currentPage  = max(1, (int)($_GET['page'] ?? 1));

$innerSql = 'SELECT * FROM vw_booking_summary WHERE 1=1';
$binds    = [];

// Hall Admin restriction
if (isHallAdmin()) {
    $innerSql .= ' AND hall_id IN (SELECT hall_id FROM halls WHERE managed_by=:usr_id)';
    $binds[':usr_id'] = $uid;
}
if ($statusFilter !== 'ALL') {
    $innerSql .= ' AND booking_status=:st';
    $binds[':st'] = $statusFilter;
}
if ($hallFilter > 0) {
    $innerSql .= ' AND hall_id=:hid';
    $binds[':hid'] = $hallFilter;
}
if ($search !== '') {
    $innerSql .= ' AND UPPER(student_name) LIKE UPPER(:s)';
    $binds[':s'] = '%' . $search . '%';
}
if ($dateFrom !== '') {
    $innerSql .= ' AND requested_at >= TO_DATE(:dfrom,\'YYYY-MM-DD\')';
    $binds[':dfrom'] = $dateFrom;
}
if ($dateTo !== '') {
    $innerSql .= ' AND requested_at < TO_DATE(:dto,\'YYYY-MM-DD\') + 1';
    $binds[':dto'] = $dateTo;
}

$res      = oci_paginate($innerSql, $binds, $currentPage, 10, 'requested_at DESC');
$bookings = $res['rows'];
$total    = $res['total'];
$baseUrl  = BASE_URL . '/pages/admin/manage_bookings.php?status='.urlencode($statusFilter).'&hall_id='.$hallFilter.'&search='.urlencode($search).'&date_from='.urlencode($dateFrom).'&date_to='.urlencode($dateTo);

// Hall Dropdown
$hallsSql = 'SELECT hall_id, hall_name FROM halls WHERE hall_status=\'ACTIVE\'';
if (isHallAdmin()) $hallsSql .= ' AND managed_by=' . (int)$uid;
$hallsSql .= ' ORDER BY hall_name';
$halls = oci_fetch_all_assoc($hallsSql);

// Stats
$statsSql = 'SELECT booking_status, COUNT(*) as cnt FROM bookings';
if (isHallAdmin()) $statsSql .= ' WHERE hall_id IN (SELECT hall_id FROM halls WHERE managed_by=' . (int)$uid . ')';
$statsSql .= ' GROUP BY booking_status';
$statsRaw = oci_fetch_all_assoc($statsSql);
$stats = ['PENDING'=>0,'APPROVED'=>0,'REJECTED'=>0,'CANCELLED'=>0];
foreach($statsRaw as $sr) $stats[$sr['BOOKING_STATUS']] = (int)$sr['CNT'];

include ROOT . '/includes/header.php';
include ROOT . '/includes/sidebar.php';
?>
<div class="main-content">
<?php include ROOT . '/includes/navbar_top.php'; ?>
<div class="content-area">

<?php $flash = getFlash(); if($flash): ?>
<div class="alert alert-<?=$flash['type']?> alert-dismissible"><button class="btn-close" data-bs-dismiss="alert"></button><?=htmlspecialchars($flash['message'])?></div>
<?php endif; ?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <h1 class="page-heading mb-0"><i class="fas fa-calendar-check me-2 text-primary"></i>Manage Bookings</h1>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="stat-card stat-card-orange">
        <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
        <div class="stat-body"><p class="stat-number"><?=$stats['PENDING']?></p><p class="stat-label">Pending</p></div>
    </div></div>
    <div class="col-6 col-md-3"><div class="stat-card stat-card-green">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="stat-body"><p class="stat-number"><?=$stats['APPROVED']?></p><p class="stat-label">Approved</p></div>
    </div></div>
    <div class="col-6 col-md-3"><div class="stat-card stat-card-red">
        <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
        <div class="stat-body"><p class="stat-number"><?=$stats['REJECTED']?></p><p class="stat-label">Rejected</p></div>
    </div></div>
    <div class="col-6 col-md-3"><div class="stat-card stat-card-secondary">
        <div class="stat-icon"><i class="fas fa-ban"></i></div>
        <div class="stat-body"><p class="stat-number"><?=$stats['CANCELLED']?></p><p class="stat-label">Cancelled</p></div>
    </div></div>
</div>

<div class="card mb-4 shadow-sm">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="ALL">All Status</option>
                    <option value="PENDING" <?=$statusFilter==='PENDING'?'selected':''?>>Pending</option>
                    <option value="APPROVED" <?=$statusFilter==='APPROVED'?'selected':''?>>Approved</option>
                    <option value="REJECTED" <?=$statusFilter==='REJECTED'?'selected':''?>>Rejected</option>
                    <option value="CANCELLED" <?=$statusFilter==='CANCELLED'?'selected':''?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="hall_id" class="form-select">
                    <option value="0">All Halls</option>
                    <?php foreach($halls as $h): ?>
                    <option value="<?=(int)$h['HALL_ID']?>" <?=$hallFilter==(int)$h['HALL_ID']?'selected':''?>><?=htmlspecialchars($h['HALL_NAME'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Search student name..." value="<?=htmlspecialchars($search)?>">
            </div>
            <div class="col-md-2">
                <input type="date" name="date_from" class="form-control" value="<?=htmlspecialchars($dateFrom)?>" title="From Date">
            </div>
            <div class="col-md-2">
                <input type="date" name="date_to" class="form-control" value="<?=htmlspecialchars($dateTo)?>" title="To Date">
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary flex-fill" title="Search"><i class="fas fa-search"></i></button>
                <a href="<?=BASE_URL?>/pages/admin/manage_bookings.php" class="btn btn-outline-secondary flex-fill" title="Clear"><i class="fas fa-times"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="table-wrapper table-responsive">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>Booking#</th><th>Student</th><th>Hall</th><th>Room/Seat</th>
                <th>Status</th><th>Requested</th><th>Reviewed By</th><th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($bookings as $b): 
                $bId = (int)$b['BOOKING_ID'];
            ?>
            <tr>
                <td class="fw-bold text-muted">#<?=$bId?></td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar-circle" style="width:36px;height:36px;font-size:14px;"><?=strtoupper(substr($b['STUDENT_NAME'],0,1))?></div>
                        <div>
                            <div class="fw-semibold text-truncate" style="max-width:140px;"><?=htmlspecialchars($b['STUDENT_NAME'])?></div>
                            <div class="text-muted small"><?=htmlspecialchars($b['STUDENT_DEPT']??'—')?></div>
                        </div>
                    </div>
                </td>
                <td class="fw-medium"><?=htmlspecialchars($b['HALL_NAME'])?></td>
                <td><span class="badge bg-light text-dark border">R: <?=htmlspecialchars($b['ROOM_NUMBER'])?> - <?=htmlspecialchars($b['SEAT_LABEL'])?></span></td>
                <td><?=statusBadge($b['BOOKING_STATUS'])?></td>
                <td><div class="small" title="<?=formatDate($b['REQUESTED_AT'])?>"><?=timeAgo($b['REQUESTED_AT'])?></div></td>
                <td class="small text-muted"><?=htmlspecialchars($b['REVIEWED_BY_NAME']??'—')?></td>
                <td>
                    <div class="d-flex gap-1 flex-wrap">
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#detailModal<?=$bId?>" title="Details"><i class="fas fa-eye"></i></button>
                        <?php if($b['BOOKING_STATUS'] === 'PENDING'): ?>
                        <button class="btn btn-sm btn-success text-white" data-bs-toggle="modal" data-bs-target="#approveModal<?=$bId?>" title="Approve"><i class="fas fa-check"></i></button>
                        <button class="btn btn-sm btn-danger text-white" data-bs-toggle="modal" data-bs-target="#rejectModal<?=$bId?>" title="Reject"><i class="fas fa-times"></i></button>
                        <?php elseif($b['BOOKING_STATUS'] === 'APPROVED'): ?>
                        <button class="btn btn-sm btn-warning text-white" data-bs-toggle="modal" data-bs-target="#rejectModal<?=$bId?>" title="Revoke"><i class="fas fa-ban"></i></button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if(empty($bookings)): ?><div class="p-4 text-center text-muted">No bookings found.</div><?php endif; ?>
</div>

<?php foreach($bookings as $b): $bId = (int)$b['BOOKING_ID']; ?>
<!-- Approve Modal -->
<div class="modal fade" id="approveModal<?=$bId?>" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST">
            <?=csrfField()?><input type="hidden" name="action" value="approve_booking"><input type="hidden" name="booking_id" value="<?=$bId?>">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-check-circle text-success me-2"></i>Approve Booking</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p>Approve booking for <strong><?=htmlspecialchars($b['STUDENT_NAME'])?></strong>?<br>
                Seat: <strong><?=htmlspecialchars($b['HALL_NAME'])?> - Room <?=htmlspecialchars($b['ROOM_NUMBER'])?> - <?=htmlspecialchars($b['SEAT_LABEL'])?></strong></p>
                <label class="form-label fw-semibold">Admin Remarks (Optional)</label>
                <textarea name="admin_remarks" class="form-control" rows="2" placeholder="Visible to student"></textarea>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-success">Confirm Approve</button></div>
        </form>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal<?=$bId?>" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST">
            <?=csrfField()?><input type="hidden" name="action" value="reject_booking"><input type="hidden" name="booking_id" value="<?=$bId?>">
            <div class="modal-header"><h5 class="modal-title"><i class="fas fa-times-circle text-danger me-2"></i><?=($b['BOOKING_STATUS']==='APPROVED'?'Revoke':'Reject')?> Booking</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p><?=($b['BOOKING_STATUS']==='APPROVED'?'Revoke':'Reject')?> booking for <strong><?=htmlspecialchars($b['STUDENT_NAME'])?></strong>?<br>
                Seat: <strong><?=htmlspecialchars($b['HALL_NAME'])?> - Room <?=htmlspecialchars($b['ROOM_NUMBER'])?> - <?=htmlspecialchars($b['SEAT_LABEL'])?></strong></p>
                <label class="form-label fw-semibold">Admin Remarks (Optional)</label>
                <textarea name="admin_remarks" class="form-control" rows="2" placeholder="Reason for rejection/revocation"></textarea>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-danger">Confirm <?=($b['BOOKING_STATUS']==='APPROVED'?'Revoke':'Reject')?></button></div>
        </form>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal<?=$bId?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Booking #<?=$bId?> Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-sm-6"><div class="p-3 bg-light rounded"><small class="text-muted d-block">Student</small><strong><?=htmlspecialchars($b['STUDENT_NAME'])?></strong><br><?=htmlspecialchars($b['STUDENT_EMAIL'])?></div></div>
                    <div class="col-sm-6"><div class="p-3 bg-light rounded"><small class="text-muted d-block">Status</small><?=statusBadge($b['BOOKING_STATUS'])?></div></div>
                    <div class="col-sm-6"><div class="p-3 bg-light rounded"><small class="text-muted d-block">Hall & Room</small><strong><?=htmlspecialchars($b['HALL_NAME'])?> - Room <?=htmlspecialchars($b['ROOM_NUMBER'])?></strong></div></div>
                    <div class="col-sm-6"><div class="p-3 bg-light rounded"><small class="text-muted d-block">Seat & Rent</small><strong><?=htmlspecialchars($b['SEAT_LABEL'])?> - <?=formatCurrency((float)$b['MONTHLY_RENT'])?>/mo</strong></div></div>
                    <div class="col-sm-6"><div class="p-3 bg-light rounded"><small class="text-muted d-block">Requested At</small><?=formatDate($b['REQUESTED_AT'])?></div></div>
                    <div class="col-sm-6"><div class="p-3 bg-light rounded"><small class="text-muted d-block">Reviewed At & By</small><?=$b['REVIEWED_AT']?formatDate($b['REVIEWED_AT']).' by '.htmlspecialchars($b['REVIEWED_BY_NAME']):'—'?></div></div>
                    <?php if(!empty($b['NOTES'])): ?>
                    <div class="col-12"><div class="p-3 bg-light rounded"><small class="text-muted d-block">Student Notes</small><?=htmlspecialchars($b['NOTES'])?></div></div>
                    <?php endif; ?>
                    <?php if(!empty($b['ADMIN_REMARKS'])): ?>
                    <div class="col-12"><div class="p-3 rounded" style="background:#fef9ec;"><small class="text-muted d-block">Admin Remarks</small><?=htmlspecialchars($b['ADMIN_REMARKS'])?></div></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php if($total>10): ?><div class="mt-3"><?=renderPagination($total,$currentPage,10,$baseUrl)?></div><?php endif; ?>

</div>
</div>
<?php include ROOT . '/includes/footer.php'; ?>
