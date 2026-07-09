<?php
/**
 * NestSync — Student Dashboard
 * OCI8 / Oracle
 */
require_once '../../config/config.php';
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/auth_check.php';
require_once ROOT . '/includes/functions.php';

requireRole(['STUDENT']);
$pageTitle  = 'My Dashboard';
$activePage = 'dashboard';
$uid        = currentUserId();

// ── Stats ──────────────────────────────────────────────────────────────────
$myBookingsCount = (int)oci_fetch_scalar('SELECT COUNT(*) FROM bookings WHERE student_id=:u', [':u' => $uid]);
$approvedCount   = (int)oci_fetch_scalar('SELECT COUNT(*) FROM bookings WHERE student_id=:u AND booking_status=:s', [':u' => $uid, ':s' => 'APPROVED']);
$pendingCount    = (int)oci_fetch_scalar('SELECT COUNT(*) FROM bookings WHERE student_id=:u AND booking_status=:s', [':u' => $uid, ':s' => 'PENDING']);
$matchCount      = (int)oci_fetch_scalar('SELECT COUNT(*) FROM roommate_matches WHERE student_id=:u', [':u' => $uid]);

// ── Current Assignment ─────────────────────────────────────────────────────
$assignment = oci_fetch_one_assoc(
    'SELECT * FROM vw_student_assignment WHERE user_id=:u',
    [':u' => $uid]
);

// ── Recent 5 Bookings ──────────────────────────────────────────────────────
$recentBookings = oci_fetch_all_assoc(
    'SELECT * FROM (
         SELECT booking_id, booking_status, requested_at, hall_name, room_number, seat_label, semester
         FROM vw_booking_summary WHERE student_user_id=:u ORDER BY requested_at DESC
     ) WHERE ROWNUM<=5',
    [':u' => $uid]
);

// ── Top 3 Matches ──────────────────────────────────────────────────────────
$topMatches = oci_fetch_all_assoc(
    'SELECT * FROM (
         SELECT match_score, matched_name, matched_dept, matched_budget, match_status
         FROM vw_roommate_matches_detail WHERE student_id=:u ORDER BY match_score DESC
     ) WHERE ROWNUM<=3',
    [':u' => $uid]
);

// ── Unread Notifications ───────────────────────────────────────────────────
$unread = (int)oci_fetch_scalar('SELECT COUNT(*) FROM notifications WHERE user_id=:u AND is_read=0', [':u' => $uid]);

function scoreBadgeClass(float $s): string
{
    if ($s >= 70) return 'score-high';
    if ($s >= 40) return 'score-medium';
    return 'score-low';
}

include ROOT . '/includes/header.php';
include ROOT . '/includes/sidebar.php';
?>
<div class="main-content">
<?php include ROOT . '/includes/navbar_top.php'; ?>
<div class="content-area">

<?php if ($unread > 0): ?>
<div class="alert alert-info d-flex align-items-center gap-2 mb-3" role="alert">
    <i class="fas fa-bell"></i>
    <span>You have <strong><?= $unread ?></strong> unread notification<?= $unread > 1 ? 's' : '' ?>.
    <a href="<?= BASE_URL ?>/pages/student/notifications.php" class="alert-link ms-1">View all →</a></span>
    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

    <!-- Welcome Banner -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h1 class="page-heading mb-1">Welcome back, <?= htmlspecialchars(currentUserName(), ENT_QUOTES, 'UTF-8') ?>! <i class="fas fa-hand-sparkles text-warning ms-1"></i></h1>
            <p class="text-muted mb-0"><i class="fas fa-calendar-alt me-1"></i><?= date('l, d F Y') ?></p>
        </div>
        <a href="<?= BASE_URL ?>/pages/student/browse_halls.php" class="btn btn-primary">
            <i class="fas fa-search me-1"></i>Find a Seat
        </a>
    </div>

    <!-- ── Stat Cards ──────────────────────────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-card-blue fade-in-up">
                <div class="stat-icon"><i class="fas fa-bookmark"></i></div>
                <div class="stat-body">
                    <p class="stat-number" data-count="<?= $myBookingsCount ?>">0</p>
                    <p class="stat-label">My Bookings</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-card-green fade-in-up" style="animation-delay:.07s">
                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                <div class="stat-body">
                    <p class="stat-number" data-count="<?= $approvedCount ?>">0</p>
                    <p class="stat-label">Approved</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-card-orange fade-in-up" style="animation-delay:.14s">
                <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                <div class="stat-body">
                    <p class="stat-number" data-count="<?= $pendingCount ?>">0</p>
                    <p class="stat-label">Pending</p>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card stat-card-purple fade-in-up" style="animation-delay:.21s">
                <div class="stat-icon"><i class="fas fa-user-friends"></i></div>
                <div class="stat-body">
                    <p class="stat-number" data-count="<?= $matchCount ?>">0</p>
                    <p class="stat-label">Roommate Matches</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Current Assignment ──────────────────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <?php if ($assignment): ?>
            <div class="table-wrapper">
                <div class="table-header">
                    <div>
                        <h5 class="table-title mb-0"><i class="fas fa-home me-2 text-success"></i>Your Current Assignment</h5>
                        <small class="text-muted">Approved seat assignment</small>
                    </div>
                    <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Assigned</span>
                </div>
                <div class="p-4">
                    <div class="row g-3">
                        <div class="col-sm-4 col-md-2 text-center">
                            <div style="width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));display:flex;align-items:center;justify-content:center;margin:0 auto 8px;font-size:26px;color:#fff;">
                                <i class="fas fa-bed"></i>
                            </div>
                            <div class="fw-bold fs-5"><?= htmlspecialchars($assignment['SEAT_LABEL'], ENT_QUOTES, 'UTF-8') ?></div>
                            <small class="text-muted">Your Seat</small>
                        </div>
                        <div class="col-sm-8 col-md-10">
                            <div class="row g-2">
                                <div class="col-sm-6 col-md-4">
                                    <div class="p-3 rounded-3" style="background:#f8fafc;border:1px solid #e2e8f0;">
                                        <div class="text-muted" style="font-size:11px;font-weight:600;text-transform:uppercase;">Hall</div>
                                        <div class="fw-semibold"><?= htmlspecialchars($assignment['HALL_NAME'], ENT_QUOTES, 'UTF-8') ?></div>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="p-3 rounded-3" style="background:#f8fafc;border:1px solid #e2e8f0;">
                                        <div class="text-muted" style="font-size:11px;font-weight:600;text-transform:uppercase;">Room</div>
                                        <div class="fw-semibold"><?= htmlspecialchars($assignment['ROOM_NUMBER'], ENT_QUOTES, 'UTF-8') ?></div>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="p-3 rounded-3" style="background:#eff6ff;border:1px solid #bfdbfe;">
                                        <div class="text-muted" style="font-size:11px;font-weight:600;text-transform:uppercase;">Monthly Rent</div>
                                        <div class="fw-bold text-primary"><?= formatCurrency($assignment['MONTHLY_RENT']) ?></div>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4">
                                    <div class="p-3 rounded-3" style="background:#f8fafc;border:1px solid #e2e8f0;">
                                        <div class="text-muted" style="font-size:11px;font-weight:600;text-transform:uppercase;">Room Type</div>
                                        <div class="fw-semibold"><?= htmlspecialchars($assignment['ROOM_TYPE'], ENT_QUOTES, 'UTF-8') ?></div>
                                    </div>
                                </div>
                                <?php if (!empty($assignment['FACILITIES'])): ?>
                                <div class="col-md-8">
                                    <div class="p-3 rounded-3" style="background:#f8fafc;border:1px solid #e2e8f0;">
                                        <div class="text-muted" style="font-size:11px;font-weight:600;text-transform:uppercase;">Facilities</div>
                                        <div class="fw-semibold small"><?= htmlspecialchars($assignment['FACILITIES'], ENT_QUOTES, 'UTF-8') ?></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="table-wrapper text-center py-5">
                <div style="font-size:56px;margin-bottom:12px;"><i class="fas fa-building text-muted"></i></div>
                <h4 class="fw-semibold mb-2">No Seat Assigned Yet</h4>
                <p class="text-muted mb-4">Browse available halls and book your seat to get started!</p>
                <a href="<?= BASE_URL ?>/pages/student/browse_halls.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-search me-2"></i>Browse Available Halls
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Recent Bookings + Top Matches ──────────────────────────────────── -->
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="table-wrapper h-100">
                <div class="table-header">
                    <div>
                        <h5 class="table-title mb-0"><i class="fas fa-calendar-check me-2 text-primary"></i>Recent Bookings</h5>
                        <small class="text-muted">Your last 5 booking requests</small>
                    </div>
                    <a href="<?= BASE_URL ?>/pages/student/my_bookings.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <?php if (empty($recentBookings)): ?>
                <div class="empty-state py-4">
                    <div class="empty-state-icon"><i class="fas fa-calendar-times"></i></div>
                    <h5>No Bookings Yet</h5>
                    <p>Start by browsing available halls.</p>
                    <a href="<?= BASE_URL ?>/pages/student/browse_halls.php" class="btn btn-primary btn-sm">Browse Halls</a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead><tr><th>Hall</th><th>Room/Seat</th><th>Status</th><th>Semester</th><th>Date</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentBookings as $b): ?>
                        <tr>
                            <td class="fw-medium"><?= htmlspecialchars($b['HALL_NAME'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($b['ROOM_NUMBER'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($b['SEAT_LABEL'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= statusBadge($b['BOOKING_STATUS']) ?></td>
                            <td class="text-muted small"><?= htmlspecialchars($b['SEMESTER'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-muted small"><?= timeAgo($b['REQUESTED_AT']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                <div class="table-footer text-end">
                    <a href="<?= BASE_URL ?>/pages/student/my_bookings.php" class="text-primary small fw-semibold">View all bookings <i class="fas fa-external-link-alt ms-1"></i></a>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="table-wrapper h-100">
                <div class="table-header">
                    <div>
                        <h5 class="table-title mb-0"><i class="fas fa-user-friends me-2" style="color:#7c3aed;"></i>Top Matches</h5>
                        <small class="text-muted">Best compatibility roommates</small>
                    </div>
                    <a href="<?= BASE_URL ?>/pages/student/roommate_matches.php" class="btn btn-sm btn-outline-primary">All</a>
                </div>
                <?php if (empty($topMatches)): ?>
                <div class="empty-state py-4">
                    <div class="empty-state-icon"><i class="fas fa-user-slash"></i></div>
                    <h5>No Matches Yet</h5>
                    <p>Complete your profile to get matched.</p>
                </div>
                <?php else: ?>
                <div style="padding:8px 0;">
                <?php foreach ($topMatches as $m):
                    $cls = scoreBadgeClass((float)$m['MATCH_SCORE']);
                    $ini = strtoupper(substr($m['MATCHED_NAME'], 0, 1));
                ?>
                    <div class="d-flex align-items-center gap-3 px-4 py-3 border-bottom" style="border-color:#f8fafc!important;">
                        <div class="avatar-circle flex-shrink-0" style="width:40px;height:40px;font-size:15px;">
                            <?= $ini ?>
                        </div>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="fw-semibold text-truncate" style="font-size:13px;"><?= htmlspecialchars($m['MATCHED_NAME'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="text-muted" style="font-size:11px;"><?= htmlspecialchars($m['MATCHED_DEPT'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                        <div class="score-badge <?= $cls ?> flex-shrink-0"><?= (int)$m['MATCH_SCORE'] ?></div>
                    </div>
                <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="table-footer text-end">
                    <a href="<?= BASE_URL ?>/pages/student/roommate_matches.php" class="text-primary small fw-semibold">View all matches <i class="fas fa-external-link-alt ms-1"></i></a>
                </div>
            </div>
        </div>
    </div>

</div><!-- /.content-area -->
</div><!-- /.main-content -->
<?php include ROOT . '/includes/footer.php'; ?>
