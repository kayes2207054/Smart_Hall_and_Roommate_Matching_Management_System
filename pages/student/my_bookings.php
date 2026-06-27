<?php
/**
 * NestSync — Student: My Bookings
 * OCI8 / Oracle
 */
require_once '../../config/config.php';
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/auth_check.php';
require_once ROOT . '/includes/functions.php';

requireRole(['STUDENT']);
$pageTitle  = 'My Bookings';
$activePage = 'my_bookings';
$uid        = currentUserId();

// ── POST: cancel_booking ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_booking') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid security token.');
    } else {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        // Verify it's mine and PENDING
        $valid = (int)oci_fetch_scalar(
            'SELECT COUNT(*) FROM bookings WHERE booking_id=:b AND student_id=:u AND booking_status=\'PENDING\'',
            [':b' => $bookingId, ':u' => $uid]
        );
        if (!$valid) {
            setFlash('danger', 'That booking cannot be cancelled (only PENDING bookings can be cancelled).');
        } else {
            global $conn;
            $stmt = oci_parse($conn, 'BEGIN PKG_NESTSYNC.sp_cancel_booking(:bid,:uid); END;');
            oci_bind_by_name($stmt, ':bid', $bookingId);
            oci_bind_by_name($stmt, ':uid', $uid);
            $ok = oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);
            oci_free_statement($stmt);
            setFlash($ok ? 'success' : 'danger', $ok ? 'Booking cancelled successfully.' : 'Failed to cancel booking.');
        }
    }
    redirect(BASE_URL . '/pages/student/my_bookings.php');
}

// ── Stats ──────────────────────────────────────────────────────────────────
$totalCount    = (int)oci_fetch_scalar('SELECT COUNT(*) FROM bookings WHERE student_id=:u', [':u'=>$uid]);
$pendingCount  = (int)oci_fetch_scalar('SELECT COUNT(*) FROM bookings WHERE student_id=:u AND booking_status=:s', [':u'=>$uid,':s'=>'PENDING']);
$approvedCount = (int)oci_fetch_scalar('SELECT COUNT(*) FROM bookings WHERE student_id=:u AND booking_status=:s', [':u'=>$uid,':s'=>'APPROVED']);
$rejectedCount = (int)oci_fetch_scalar('SELECT COUNT(*) FROM bookings WHERE student_id=:u AND booking_status=:s', [':u'=>$uid,':s'=>'REJECTED']);

// ── Filters ────────────────────────────────────────────────────────────────
$statusFilter = sanitize($_GET['status'] ?? 'ALL');
$validStatuses = ['ALL','PENDING','APPROVED','REJECTED','CANCELLED'];
if (!in_array($statusFilter, $validStatuses, true)) $statusFilter = 'ALL';
$currentPage  = max(1, (int)($_GET['page'] ?? 1));

$innerSql = 'SELECT * FROM vw_booking_summary WHERE student_user_id=:u';
$binds    = [':u' => $uid];
if ($statusFilter !== 'ALL') {
    $innerSql .= ' AND booking_status=:st';
    $binds[':st'] = $statusFilter;
}

$result   = oci_paginate($innerSql, $binds, $currentPage, 10, 'requested_at DESC');
$bookings = $result['rows'];
$total    = $result['total'];
$baseUrl  = BASE_URL . '/pages/student/my_bookings.php?status=' . urlencode($statusFilter);

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

    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h1 class="page-heading mb-1"><i class="fas fa-calendar-check me-2 text-primary"></i>My Bookings</h1>
            <nav aria-label="breadcrumb"><ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/student/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">My Bookings</li>
            </ol></nav>
        </div>
        <a href="<?= BASE_URL ?>/pages/student/browse_halls.php" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i>New Booking
        </a>
    </div>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="stat-card stat-card-blue fade-in-up">
            <div class="stat-icon"><i class="fas fa-bookmark"></i></div>
            <div class="stat-body"><p class="stat-number" data-count="<?= $totalCount ?>">0</p><p class="stat-label">Total</p></div>
        </div></div>
        <div class="col-6 col-md-3"><div class="stat-card stat-card-green fade-in-up" style="animation-delay:.07s">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-body"><p class="stat-number" data-count="<?= $approvedCount ?>">0</p><p class="stat-label">Approved</p></div>
        </div></div>
        <div class="col-6 col-md-3"><div class="stat-card stat-card-orange fade-in-up" style="animation-delay:.14s">
            <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-body"><p class="stat-number" data-count="<?= $pendingCount ?>">0</p><p class="stat-label">Pending</p></div>
        </div></div>
        <div class="col-6 col-md-3"><div class="stat-card stat-card-red fade-in-up" style="animation-delay:.21s">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <div class="stat-body"><p class="stat-number" data-count="<?= $rejectedCount ?>">0</p><p class="stat-label">Rejected</p></div>
        </div></div>
    </div>

    <!-- Status Filter Tabs -->
    <ul class="nav nav-tabs mb-3">
        <?php foreach (['ALL'=>'All','PENDING'=>'Pending','APPROVED'=>'Approved','REJECTED'=>'Rejected','CANCELLED'=>'Cancelled'] as $s => $l): ?>
        <li class="nav-item">
            <a class="nav-link <?= $statusFilter===$s ? 'active' : '' ?>"
               href="<?= BASE_URL ?>/pages/student/my_bookings.php?status=<?= $s ?>">
                <?= $l ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <div class="table-wrapper">
        <?php if (empty($bookings)): ?>
        <div class="empty-state py-5">
            <div class="empty-state-icon"><i class="fas fa-calendar-times"></i></div>
            <h5>No <?= $statusFilter !== 'ALL' ? ucfirst(strtolower($statusFilter)) . ' ' : '' ?>Bookings</h5>
            <p class="text-muted">
                <?= $statusFilter !== 'ALL' ? 'No bookings with this status found.' : 'You have not made any bookings yet.' ?>
            </p>
            <a href="<?= BASE_URL ?>/pages/student/browse_halls.php" class="btn btn-primary btn-sm">Browse Halls</a>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#</th><th>Hall</th><th>Room / Seat</th><th>Status</th>
                        <th>Semester</th><th>Requested</th><th>Reviewed By</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($bookings as $i => $b): ?>
                <tr>
                    <td class="text-muted fw-bold"><?= ($currentPage-1)*10 + $i + 1 ?></td>
                    <td class="fw-medium"><?= htmlspecialchars($b['HALL_NAME'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <span class="badge bg-light text-dark border">
                            Room <?= htmlspecialchars($b['ROOM_NUMBER'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($b['SEAT_LABEL'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td><?= statusBadge($b['BOOKING_STATUS']) ?></td>
                    <td class="text-muted small"><?= htmlspecialchars($b['SEMESTER']??'—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <span class="text-muted small" data-bs-toggle="tooltip" title="<?= formatDate($b['REQUESTED_AT']) ?>">
                            <?= timeAgo($b['REQUESTED_AT']) ?>
                        </span>
                    </td>
                    <td class="text-muted small"><?= htmlspecialchars($b['REVIEWED_BY_NAME']??'—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            <!-- View Details -->
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#detailModal<?= (int)$b['BOOKING_ID'] ?>">
                                <i class="fas fa-eye"></i>
                            </button>
                            <!-- Cancel (only PENDING) -->
                            <?php if ($b['BOOKING_STATUS'] === 'PENDING'): ?>
                            <button class="btn btn-sm btn-outline-danger"
                                    data-bs-toggle="modal" data-bs-target="#cancelModal<?= (int)$b['BOOKING_ID'] ?>">
                                <i class="fas fa-times"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>

                <!-- Detail Modal -->
                <div class="modal fade" id="detailModal<?= (int)$b['BOOKING_ID'] ?>" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="fas fa-calendar-check me-2 text-primary"></i>Booking #<?= (int)$b['BOOKING_ID'] ?> Details</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-sm-6"><div class="p-3 rounded-2 bg-light"><small class="text-muted d-block">Status</small><?= statusBadge($b['BOOKING_STATUS']) ?></div></div>
                                    <div class="col-sm-6"><div class="p-3 rounded-2 bg-light"><small class="text-muted d-block">Hall</small><strong><?= htmlspecialchars($b['HALL_NAME'], ENT_QUOTES, 'UTF-8') ?></strong></div></div>
                                    <div class="col-sm-6"><div class="p-3 rounded-2 bg-light"><small class="text-muted d-block">Room</small><strong><?= htmlspecialchars($b['ROOM_NUMBER'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($b['ROOM_TYPE']??'', ENT_QUOTES, 'UTF-8') ?>)</strong></div></div>
                                    <div class="col-sm-6"><div class="p-3 rounded-2 bg-light"><small class="text-muted d-block">Seat</small><strong><?= htmlspecialchars($b['SEAT_LABEL'], ENT_QUOTES, 'UTF-8') ?></strong></div></div>
                                    <div class="col-sm-6"><div class="p-3 rounded-2 bg-light"><small class="text-muted d-block">Monthly Rent</small><strong class="text-success"><?= formatCurrency((float)($b['MONTHLY_RENT']??0)) ?></strong></div></div>
                                    <div class="col-sm-6"><div class="p-3 rounded-2 bg-light"><small class="text-muted d-block">Semester</small><strong><?= htmlspecialchars($b['SEMESTER']??'—', ENT_QUOTES, 'UTF-8') ?></strong></div></div>
                                    <div class="col-sm-6"><div class="p-3 rounded-2 bg-light"><small class="text-muted d-block">Requested</small><?= formatDate($b['REQUESTED_AT']) ?></div></div>
                                    <div class="col-sm-6"><div class="p-3 rounded-2 bg-light"><small class="text-muted d-block">Reviewed At</small><?= $b['REVIEWED_AT'] ? formatDate($b['REVIEWED_AT']) : '—' ?></div></div>
                                    <?php if (!empty($b['NOTES'])): ?>
                                    <div class="col-12"><div class="p-3 rounded-2 bg-light"><small class="text-muted d-block">Your Notes</small><?= htmlspecialchars($b['NOTES'], ENT_QUOTES, 'UTF-8') ?></div></div>
                                    <?php endif; ?>
                                    <?php if (!empty($b['ADMIN_REMARKS'])): ?>
                                    <div class="col-12"><div class="p-3 rounded-2" style="background:#fef9ec;border:1px solid #fde68a;"><small class="text-muted d-block">Admin Remarks</small><?= htmlspecialchars($b['ADMIN_REMARKS'], ENT_QUOTES, 'UTF-8') ?></div></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
                        </div>
                    </div>
                </div>

                <!-- Cancel Modal -->
                <?php if ($b['BOOKING_STATUS'] === 'PENDING'): ?>
                <div class="modal fade" id="cancelModal<?= (int)$b['BOOKING_ID'] ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="fas fa-times-circle me-2 text-danger"></i>Cancel Booking</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p>Are you sure you want to cancel booking #<?= (int)$b['BOOKING_ID'] ?> for
                                   <strong><?= htmlspecialchars($b['HALL_NAME'], ENT_QUOTES, 'UTF-8') ?></strong> — Room <?= htmlspecialchars($b['ROOM_NUMBER'], ENT_QUOTES, 'UTF-8') ?>?</p>
                                <p class="text-muted small">The seat will be released and available for others.</p>
                            </div>
                            <div class="modal-footer">
                                <button class="btn btn-secondary" data-bs-dismiss="modal">Keep Booking</button>
                                <form method="POST">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="cancel_booking">
                                    <input type="hidden" name="booking_id" value="<?= (int)$b['BOOKING_ID'] ?>">
                                    <button type="submit" class="btn btn-danger"><i class="fas fa-times me-1"></i>Yes, Cancel</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total > 10): ?>
        <div class="p-3"><?= renderPagination($total, $currentPage, 10, $baseUrl) ?></div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

</div>
</div>
<?php include ROOT . '/includes/footer.php'; ?>
