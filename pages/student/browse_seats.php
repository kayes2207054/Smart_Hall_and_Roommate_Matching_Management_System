<?php
/**
 * NestSync — Student: Browse & Book Seats
 * OCI8 / Oracle
 */
require_once '../../config/config.php';
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/auth_check.php';
require_once ROOT . '/includes/functions.php';

requireRole(['STUDENT']);
$pageTitle  = 'Browse Seats';
$activePage = 'browse_seats';
$uid        = currentUserId();

$errors = [];

// ── Check existing active booking ──────────────────────────────────────────
$activeBooking = oci_fetch_one_assoc(
    'SELECT booking_id, booking_status, hall_name, room_number, seat_label
     FROM vw_booking_summary
     WHERE student_user_id=:u AND booking_status IN (\'PENDING\',\'APPROVED\') AND ROWNUM=1',
    [':u' => $uid]
);

// ── POST: book_seat ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'book_seat') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } elseif ($activeBooking) {
        $errors[] = 'You already have an active booking. Cancel it first to book another seat.';
    } else {
        $seatId   = (int)($_POST['seat_id']  ?? 0);
        $hallId   = (int)($_POST['hall_id']  ?? 0);
        $roomId   = (int)($_POST['room_id']  ?? 0);
        $semester = sanitize($_POST['semester'] ?? '');
        $notes    = sanitize($_POST['notes'] ?? '');

        if (!$seatId || !$hallId || !$roomId) $errors[] = 'Invalid seat selection.';
        if (empty($semester))                  $errors[] = 'Semester is required.';

        if (empty($errors)) {
            // Verify seat still AVAILABLE
            $seatStatus = oci_fetch_scalar('SELECT seat_status FROM seats WHERE seat_id=:s', [':s' => $seatId]);
            if ($seatStatus !== 'AVAILABLE') {
                $errors[] = 'Sorry, that seat is no longer available. Please choose another.';
            } else {
                $ok = oci_execute_dml(
                    'INSERT INTO bookings (student_id,hall_id,room_id,seat_id,booking_status,semester,notes)
                     VALUES (:u,:h,:r,:s,\'PENDING\',:sem,:notes)',
                    [':u'=>$uid,':h'=>$hallId,':r'=>$roomId,':s'=>$seatId,':sem'=>$semester,':notes'=>($notes?:null)]
                );
                if ($ok) {
                    oci_execute_dml(
                        'INSERT INTO notifications (user_id,title,message,notif_type)
                         VALUES (:u,\'Booking Submitted\',\'Your seat booking has been submitted and is under review.\',\'BOOKING\')',
                        [':u' => $uid]
                    );
                    setFlash('success', 'Booking submitted successfully! You will be notified when it is reviewed.');
                    redirect(BASE_URL . '/pages/student/my_bookings.php');
                } else {
                    $errors[] = 'Failed to submit booking. Please try again.';
                }
            }
        }
    }
}

// ── Filters ────────────────────────────────────────────────────────────────
$hallIdFilter = (int)($_GET['hall_id']  ?? 0);
$roomType     = sanitize($_GET['room_type'] ?? 'ALL');
$maxRent      = (float)($_GET['max_rent'] ?? 0);
$currentPage  = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 12;

$validTypes = ['ALL','SINGLE','DOUBLE','TRIPLE','QUAD'];
if (!in_array($roomType, $validTypes, true)) $roomType = 'ALL';

$innerSql = 'SELECT seat_id, seat_label, room_id, room_number, room_type, monthly_rent, floor_number, facilities, hall_id, hall_name, gender_type FROM vw_available_seats WHERE 1=1';
$binds    = [];

if ($hallIdFilter > 0) {
    $innerSql .= ' AND hall_id=:hid';
    $binds[':hid'] = $hallIdFilter;
}
if ($roomType !== 'ALL') {
    $innerSql .= ' AND room_type=:rtype';
    $binds[':rtype'] = $roomType;
}
if ($maxRent > 0) {
    $innerSql .= ' AND monthly_rent<=:maxrent';
    $binds[':maxrent'] = $maxRent;
}

$result     = oci_paginate($innerSql, $binds, $currentPage, $perPage, 'monthly_rent ASC');
$seats      = $result['rows'];
$totalSeats = $result['total'];
$totalPages = (int)ceil($totalSeats / $perPage);

// Halls for filter dropdown
$halls = oci_fetch_all_assoc(
    'SELECT hall_id, hall_name, gender_type FROM halls WHERE hall_status=:s ORDER BY hall_name',
    [':s' => 'ACTIVE']
);

$baseUrl = BASE_URL . '/pages/student/browse_seats.php?hall_id=' . $hallIdFilter
         . '&room_type=' . urlencode($roomType) . '&max_rent=' . $maxRent;

include ROOT . '/includes/header.php';
include ROOT . '/includes/sidebar.php';
?>
<div class="main-content">
<?php include ROOT . '/includes/navbar_top.php'; ?>
<div class="content-area">

<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?php foreach ($errors as $e): ?><div><i class="fas fa-exclamation-circle me-1"></i><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h1 class="page-heading mb-1"><i class="fas fa-chair me-2 text-primary"></i>Browse Seats</h1>
            <nav aria-label="breadcrumb"><ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/student/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/student/browse_halls.php">Browse Halls</a></li>
                <li class="breadcrumb-item active">Browse Seats</li>
            </ol></nav>
        </div>
        <span class="badge bg-primary-soft fs-6 px-3 py-2">
            <i class="fas fa-chair me-1"></i><?= $totalSeats ?> Available Seat<?= $totalSeats !== 1 ? 's' : '' ?>
        </span>
    </div>

    <!-- Active Booking Warning -->
    <?php if ($activeBooking): ?>
    <div class="alert alert-warning d-flex align-items-center gap-3 mb-4" role="alert">
        <i class="fas fa-exclamation-triangle fa-lg"></i>
        <div>
            <strong>You have an active booking!</strong>
            Booking #<?= (int)$activeBooking['BOOKING_ID'] ?> — <?= htmlspecialchars($activeBooking['HALL_NAME'], ENT_QUOTES, 'UTF-8') ?>
            (Status: <?= statusBadge($activeBooking['BOOKING_STATUS']) ?>)<br>
            <small>Cancel or wait for resolution before booking another seat.</small>
            <a href="<?= BASE_URL ?>/pages/student/my_bookings.php" class="btn btn-sm btn-outline-warning ms-2">View My Bookings</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filter Bar -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body py-3">
            <form method="GET" action="" id="filterForm">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Hall</label>
                        <select name="hall_id" class="form-select">
                            <option value="0">All Halls</option>
                            <?php foreach ($halls as $h): ?>
                            <option value="<?= (int)$h['HALL_ID'] ?>" <?= $hallIdFilter===(int)$h['HALL_ID'] ? 'selected':'' ?>>
                                <?= htmlspecialchars($h['HALL_NAME'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($h['GENDER_TYPE'], ENT_QUOTES, 'UTF-8') ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Room Type</label>
                        <select name="room_type" class="form-select">
                            <?php foreach (['ALL'=>'All Types','SINGLE'=>'Single','DOUBLE'=>'Double','TRIPLE'=>'Triple','QUAD'=>'Quad'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= $roomType===$v ? 'selected':'' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-semibold">Max Rent (BDT)</label>
                        <input type="number" name="max_rent" class="form-control" min="0" step="100" value="<?= $maxRent > 0 ? $maxRent : '' ?>" placeholder="Any">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button>
                    </div>
                    <?php if ($hallIdFilter || $roomType !== 'ALL' || $maxRent > 0): ?>
                    <div class="col-md-2">
                        <a href="<?= BASE_URL ?>/pages/student/browse_seats.php" class="btn btn-outline-secondary w-100"><i class="fas fa-times me-1"></i>Clear</a>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Seats Grid -->
    <?php if (empty($seats)): ?>
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="empty-state py-5">
                <div class="empty-state-icon"><i class="fas fa-chair"></i></div>
                <h5>No Available Seats</h5>
                <p class="text-muted">No seats match your current filters. Try adjusting the criteria or <a href="<?= BASE_URL ?>/pages/student/browse_seats.php">clear all filters</a>.</p>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="row g-3">
    <?php foreach ($seats as $s): ?>
    <div class="col-md-6 col-lg-4 fade-in-up">
        <div class="card h-100 border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-header border-0 pb-0 pt-3 px-4" style="background:linear-gradient(135deg,#f0f4ff,#fff);border-radius:12px 12px 0 0;">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="fw-bold mb-1" style="font-size:22px;color:var(--primary);"><?= htmlspecialchars($s['SEAT_LABEL'], ENT_QUOTES, 'UTF-8') ?></h5>
                        <small class="text-muted"><?= htmlspecialchars($s['HALL_NAME'], ENT_QUOTES, 'UTF-8') ?> • Room <?= htmlspecialchars($s['ROOM_NUMBER'], ENT_QUOTES, 'UTF-8') ?></small>
                    </div>
                    <?= statusBadge($s['ROOM_TYPE']) ?>
                </div>
            </div>
            <div class="card-body px-4 py-3">
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="p-2 rounded-2 text-center" style="background:#f8fafc;border:1px solid #e2e8f0;">
                            <div class="text-muted" style="font-size:10px;font-weight:600;text-transform:uppercase;">Floor</div>
                            <div class="fw-bold"><?= (int)$s['FLOOR_NUMBER'] ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 rounded-2 text-center" style="background:#ecfdf5;border:1px solid #6ee7b7;">
                            <div class="text-muted" style="font-size:10px;font-weight:600;text-transform:uppercase;">Monthly Rent</div>
                            <div class="fw-bold text-success" style="font-size:13px;"><?= formatCurrency((float)$s['MONTHLY_RENT']) ?></div>
                        </div>
                    </div>
                </div>
                <?php if (!empty($s['FACILITIES'])): ?>
                <p class="text-muted small mb-3"><i class="fas fa-star me-1 text-warning"></i><?= htmlspecialchars(truncate($s['FACILITIES'], 60), ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <?php if ($activeBooking): ?>
                <button class="btn btn-secondary w-100" disabled><i class="fas fa-lock me-1"></i>Already Have a Booking</button>
                <?php else: ?>
                <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#bookModal<?= (int)$s['SEAT_ID'] ?>">
                    <i class="fas fa-bookmark me-1"></i>Book This Seat
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Book Modal for this seat -->
    <div class="modal fade" id="bookModal<?= (int)$s['SEAT_ID'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-bookmark me-2 text-primary"></i>Book Seat <?= htmlspecialchars($s['SEAT_LABEL'], ENT_QUOTES, 'UTF-8') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <?= csrfField() ?>
                    <input type="hidden" name="action"  value="book_seat">
                    <input type="hidden" name="seat_id" value="<?= (int)$s['SEAT_ID'] ?>">
                    <input type="hidden" name="hall_id" value="<?= (int)$s['HALL_ID'] ?>">
                    <input type="hidden" name="room_id" value="<?= (int)$s['ROOM_ID'] ?>">
                    <div class="modal-body">
                        <div class="p-3 rounded-2 mb-3" style="background:#f8fafc;border:1px solid #e2e8f0;">
                            <div class="row g-2 text-center">
                                <div class="col-4"><div class="fw-bold"><?= htmlspecialchars($s['HALL_NAME'], ENT_QUOTES, 'UTF-8') ?></div><small class="text-muted">Hall</small></div>
                                <div class="col-4"><div class="fw-bold"><?= htmlspecialchars($s['ROOM_NUMBER'], ENT_QUOTES, 'UTF-8') ?></div><small class="text-muted">Room</small></div>
                                <div class="col-4"><div class="fw-bold"><?= formatCurrency((float)$s['MONTHLY_RENT']) ?></div><small class="text-muted">Rent/Mo</small></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Semester <span class="text-danger">*</span></label>
                            <input type="text" name="semester" class="form-control" placeholder="e.g. Spring 2026" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Additional Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Any special requirements…"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i>Submit Booking</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="mt-4"><?= renderPagination($totalSeats, $currentPage, $perPage, $baseUrl) ?></div>
    <?php endif; ?>
    <?php endif; ?>

</div>
</div>
<?php include ROOT . '/includes/footer.php'; ?>
