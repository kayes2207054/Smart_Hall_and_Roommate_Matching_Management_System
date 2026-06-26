<?php
/**
 * NestSync — Admin Dashboard
 * Roles: SYSTEM_ADMIN, HALL_ADMIN
 * Shows stats, recent bookings, top roommate matches, hall occupancy
 */

require_once '../../config/config.php';
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/auth_check.php';
require_once ROOT . '/includes/functions.php';

requireRole(['SYSTEM_ADMIN', 'HALL_ADMIN']);

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';

// ================================================================
// 1. Fetch Dashboard Stats from vw_dashboard_stats
// ================================================================
$stats = [
    'total_students'    => 0,
    'total_halls'       => 0,
    'total_rooms'       => 0,
    'total_seats'       => 0,
    'available_seats'   => 0,
    'booked_seats'      => 0,
    'pending_bookings'  => 0,
    'approved_bookings' => 0,
    'rejected_bookings' => 0,
    'total_hall_admins' => 0,
];

$stmtStats = $conn->query('SELECT * FROM vw_dashboard_stats LIMIT 1');
if ($stmtStats && $stmtStats->num_rows > 0) {
    $stats = $stmtStats->fetch_assoc();
}

// ================================================================
// 2. Recent Bookings — last 10 from vw_booking_summary
// ================================================================
$recentBookings = [];

// HALL_ADMIN: only bookings for their hall
if (isHallAdmin()) {
    $myId = currentUserId();
    $stmtRB = $conn->prepare(
        'SELECT b.booking_id, b.booking_status, b.requested_at,
                b.student_name, b.student_email,
                b.hall_name, b.room_number, b.seat_label
         FROM vw_booking_summary b
         JOIN halls h ON b.hall_id = h.hall_id
         WHERE h.managed_by = ?
         ORDER BY b.requested_at DESC
         LIMIT 10'
    );
    $stmtRB->bind_param('i', $myId);
} else {
    $stmtRB = $conn->prepare(
        'SELECT booking_id, booking_status, requested_at,
                student_name, student_email,
                hall_name, room_number, seat_label
         FROM vw_booking_summary
         ORDER BY requested_at DESC
         LIMIT 10'
    );
}
$stmtRB->execute();
$recentBookings = $stmtRB->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtRB->close();

// ================================================================
// 3. Top 5 Roommate Matches from vw_roommate_matches_detail
// ================================================================
$topMatches = [];
$stmtMatches = $conn->prepare(
    'SELECT match_id, match_score, dept_match, budget_match, pref_overlap,
            match_reason, match_status, matched_at,
            student_id, student_name, student_dept,
            matched_student_id, matched_name, matched_dept
     FROM vw_roommate_matches_detail
     ORDER BY match_score DESC
     LIMIT 5'
);
$stmtMatches->execute();
$topMatches = $stmtMatches->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtMatches->close();

// ================================================================
// 4. Hall Occupancy from vw_hall_occupancy
// ================================================================
$hallOccupancy = [];

if (isHallAdmin()) {
    $myId = currentUserId();
    $stmtHO = $conn->prepare(
        'SELECT hall_id, hall_name, hall_location, gender_type,
                total_seats, available_seats, booked_seats,
                reserved_seats, maintenance_seats, occupancy_pct, manager_name
         FROM vw_hall_occupancy
         WHERE hall_id IN (SELECT hall_id FROM halls WHERE managed_by = ?)
         ORDER BY occupancy_pct DESC'
    );
    $stmtHO->bind_param('i', $myId);
} else {
    $stmtHO = $conn->prepare(
        'SELECT hall_id, hall_name, hall_location, gender_type,
                total_seats, available_seats, booked_seats,
                reserved_seats, maintenance_seats, occupancy_pct, manager_name
         FROM vw_hall_occupancy
         ORDER BY occupancy_pct DESC'
    );
}
$stmtHO->execute();
$hallOccupancy = $stmtHO->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtHO->close();

// ================================================================
// Helper: score badge class
// ================================================================
function scoreBadgeClass(float $score): string
{
    if ($score >= 70) return 'score-high';
    if ($score >= 40) return 'score-medium';
    return 'score-low';
}

// Helper: occupancy bar colour
function occupancyColor(float $pct): string
{
    if ($pct >= 90) return '#ef4444';
    if ($pct >= 70) return '#f59e0b';
    return '#10b981';
}

include ROOT . '/includes/header.php';
include ROOT . '/includes/sidebar.php';
?>
<div class="main-content">
<?php include ROOT . '/includes/navbar_top.php'; ?>
<div class="content-area">

    <!-- ============================================================
         PAGE HEADING
    ============================================================ -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="page-heading mb-1">Dashboard</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/admin/dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">Dashboard</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small"><i class="fas fa-clock me-1"></i><?= date('D, d M Y — h:i A') ?></span>
        </div>
    </div>

    <!-- ============================================================
         ROW 1: STAT CARDS
    ============================================================ -->
    <div class="row g-3 mb-4">

        <!-- Total Students -->
        <div class="col-xl-2 col-lg-4 col-sm-6">
            <div class="stat-card stat-card-blue fade-in-up">
                <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-body">
                    <p class="stat-number" data-count="<?= (int)$stats['total_students'] ?>">0</p>
                    <p class="stat-label">Total Students</p>
                    <p class="stat-sublabel"><?= (int)$stats['total_hall_admins'] ?> Hall Admin<?= $stats['total_hall_admins'] != 1 ? 's' : '' ?></p>
                </div>
            </div>
        </div>

        <!-- Available Seats -->
        <div class="col-xl-2 col-lg-4 col-sm-6">
            <div class="stat-card stat-card-green fade-in-up" style="animation-delay:.07s">
                <div class="stat-icon"><i class="fas fa-chair"></i></div>
                <div class="stat-body">
                    <p class="stat-number" data-count="<?= (int)$stats['available_seats'] ?>">0</p>
                    <p class="stat-label">Available Seats</p>
                    <p class="stat-sublabel"><?= (int)$stats['total_seats'] ?> total seats</p>
                </div>
            </div>
        </div>

        <!-- Pending Bookings -->
        <div class="col-xl-2 col-lg-4 col-sm-6">
            <div class="stat-card stat-card-orange fade-in-up" style="animation-delay:.14s">
                <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                <div class="stat-body">
                    <p class="stat-number" data-count="<?= (int)$stats['pending_bookings'] ?>">0</p>
                    <p class="stat-label">Pending Bookings</p>
                    <p class="stat-sublabel"><?= (int)$stats['approved_bookings'] ?> approved</p>
                </div>
            </div>
        </div>

        <!-- Booked Seats -->
        <div class="col-xl-2 col-lg-4 col-sm-6">
            <div class="stat-card stat-card-red fade-in-up" style="animation-delay:.21s">
                <div class="stat-icon"><i class="fas fa-bed"></i></div>
                <div class="stat-body">
                    <p class="stat-number" data-count="<?= (int)$stats['booked_seats'] ?>">0</p>
                    <p class="stat-label">Booked Seats</p>
                    <p class="stat-sublabel"><?= (int)$stats['rejected_bookings'] ?> rejected</p>
                </div>
            </div>
        </div>

        <!-- Total Halls -->
        <div class="col-xl-2 col-lg-4 col-sm-6">
            <div class="stat-card stat-card-purple fade-in-up" style="animation-delay:.28s">
                <div class="stat-icon"><i class="fas fa-building"></i></div>
                <div class="stat-body">
                    <p class="stat-number" data-count="<?= (int)$stats['total_halls'] ?>">0</p>
                    <p class="stat-label">Total Halls</p>
                    <p class="stat-sublabel">Active halls</p>
                </div>
            </div>
        </div>

        <!-- Total Rooms -->
        <div class="col-xl-2 col-lg-4 col-sm-6">
            <div class="stat-card stat-card-cyan fade-in-up" style="animation-delay:.35s">
                <div class="stat-icon"><i class="fas fa-door-open"></i></div>
                <div class="stat-body">
                    <p class="stat-number" data-count="<?= (int)$stats['total_rooms'] ?>">0</p>
                    <p class="stat-label">Total Rooms</p>
                    <p class="stat-sublabel">Available &amp; full</p>
                </div>
            </div>
        </div>

    </div><!-- /row stat cards -->

    <!-- ============================================================
         ROW 2: RECENT BOOKINGS + TOP ROOMMATE MATCHES
    ============================================================ -->
    <div class="row g-3 mb-4">

        <!-- ---- LEFT: Recent Bookings (col-lg-8) ---- -->
        <div class="col-lg-8">
            <div class="table-wrapper h-100">
                <div class="table-header">
                    <div>
                        <h5 class="table-title mb-0"><i class="fas fa-calendar-check me-2 text-primary"></i>Recent Bookings</h5>
                        <small class="text-muted">Latest 10 booking requests across the system</small>
                    </div>
                    <a href="<?= BASE_URL ?>/pages/admin/manage_bookings.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-arrow-right me-1"></i>View All
                    </a>
                </div>

                <?php if (empty($recentBookings)): ?>
                <div class="empty-state py-5">
                    <div class="empty-state-icon"><i class="fas fa-calendar-times"></i></div>
                    <h5>No Bookings Yet</h5>
                    <p>Booking requests will appear here once students start applying.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="recentBookingsTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student</th>
                                <th>Hall</th>
                                <th>Room / Seat</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentBookings as $i => $b): ?>
                            <tr>
                                <td class="text-muted fw-bold"><?= $i + 1 ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-circle" style="width:32px;height:32px;font-size:13px;flex-shrink:0;">
                                            <?= strtoupper(substr($b['student_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-semibold" style="font-size:13px;"><?= htmlspecialchars($b['student_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="text-muted" style="font-size:11px;"><?= htmlspecialchars($b['student_email'], ENT_QUOTES, 'UTF-8') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="fw-medium" style="font-size:13px;"><?= htmlspecialchars($b['hall_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border" style="font-size:11.5px;">
                                        Room <?= htmlspecialchars($b['room_number'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($b['seat_label'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td><?= statusBadge($b['booking_status']) ?></td>
                                <td>
                                    <span class="text-muted small" data-bs-toggle="tooltip" title="<?= htmlspecialchars(formatDate($b['requested_at']), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= timeAgo($b['requested_at']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <div class="table-footer text-end">
                    <a href="<?= BASE_URL ?>/pages/admin/manage_bookings.php" class="text-primary small fw-semibold">
                        Manage all bookings <i class="fas fa-external-link-alt ms-1"></i>
                    </a>
                </div>
            </div>
        </div><!-- /col-lg-8 -->

        <!-- ---- RIGHT: Top Roommate Matches (col-lg-4) ---- -->
        <div class="col-lg-4">
            <div class="table-wrapper h-100">
                <div class="table-header">
                    <div>
                        <h5 class="table-title mb-0"><i class="fas fa-user-friends me-2 text-purple" style="color:#7c3aed;"></i>Top Matches</h5>
                        <small class="text-muted">Highest compatibility pairs</small>
                    </div>
                    <a href="<?= BASE_URL ?>/pages/admin/roommate_matches.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-arrow-right me-1"></i>All
                    </a>
                </div>

                <?php if (empty($topMatches)): ?>
                <div class="empty-state py-5">
                    <div class="empty-state-icon"><i class="fas fa-user-slash"></i></div>
                    <h5>No Matches Yet</h5>
                    <p>Run the matching engine to generate roommate pairs.</p>
                </div>
                <?php else: ?>
                <div style="padding: 8px 0;">
                <?php foreach ($topMatches as $m):
                    $scoreClass  = scoreBadgeClass((float)$m['match_score']);
                    $initials1   = strtoupper(substr($m['student_name'], 0, 1));
                    $initials2   = strtoupper(substr($m['matched_name'], 0, 1));
                ?>
                    <div class="d-flex align-items-center gap-3 px-4 py-3 border-bottom" style="border-color:#f8fafc !important;">
                        <!-- Avatar 1 -->
                        <div class="avatar-circle flex-shrink-0" style="width:38px;height:38px;font-size:14px;" title="<?= htmlspecialchars($m['student_name'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= $initials1 ?>
                        </div>

                        <!-- Names + dept -->
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="fw-semibold text-truncate" style="font-size:12.5px;"><?= htmlspecialchars($m['student_name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="d-flex align-items-center gap-1 my-1">
                                <i class="fas fa-exchange-alt text-muted" style="font-size:10px;"></i>
                            </div>
                            <div class="fw-semibold text-truncate" style="font-size:12.5px;"><?= htmlspecialchars($m['matched_name'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="text-muted" style="font-size:10.5px;"><?= htmlspecialchars($m['student_dept'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
                        </div>

                        <!-- Avatar 2 -->
                        <div class="avatar-circle flex-shrink-0" style="width:38px;height:38px;font-size:14px;background:linear-gradient(135deg,#7c3aed,#4cc9f0);" title="<?= htmlspecialchars($m['matched_name'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= $initials2 ?>
                        </div>

                        <!-- Score badge -->
                        <div class="score-badge <?= $scoreClass ?> flex-shrink-0">
                            <?= (int)$m['match_score'] ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="table-footer text-end">
                    <a href="<?= BASE_URL ?>/pages/admin/roommate_matches.php" class="text-primary small fw-semibold">
                        View all matches <i class="fas fa-external-link-alt ms-1"></i>
                    </a>
                </div>
            </div>
        </div><!-- /col-lg-4 -->

    </div><!-- /row 2 -->

    <!-- ============================================================
         ROW 3: HALL OCCUPANCY TABLE
    ============================================================ -->
    <div class="row g-3">
        <div class="col-12">
            <div class="table-wrapper">
                <div class="table-header">
                    <div>
                        <h5 class="table-title mb-0"><i class="fas fa-chart-bar me-2 text-primary"></i>Hall Occupancy</h5>
                        <small class="text-muted">Real-time seat utilisation across all halls</small>
                    </div>
                    <?php if (isSystemAdmin()): ?>
                    <a href="<?= BASE_URL ?>/pages/admin/manage_halls.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-building me-1"></i>Manage Halls
                    </a>
                    <?php endif; ?>
                </div>

                <?php if (empty($hallOccupancy)): ?>
                <div class="empty-state py-5">
                    <div class="empty-state-icon"><i class="fas fa-building"></i></div>
                    <h5>No Halls Found</h5>
                    <p>Add halls to start tracking occupancy.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="hallOccupancyTable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Hall Name</th>
                                <th>Location</th>
                                <th>Gender</th>
                                <th class="text-center">Total Seats</th>
                                <th class="text-center">Booked</th>
                                <th class="text-center">Available</th>
                                <th>Occupancy</th>
                                <th>Manager</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($hallOccupancy as $i => $h):
                            $pct   = (float)($h['occupancy_pct'] ?? 0);
                            $color = occupancyColor($pct);
                        ?>
                            <tr>
                                <td class="text-muted fw-bold"><?= $i + 1 ?></td>
                                <td>
                                    <div class="fw-semibold" style="font-size:13.5px;"><?= htmlspecialchars($h['hall_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                </td>
                                <td>
                                    <span class="text-muted small"><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($h['hall_location'], ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td><?= statusBadge($h['gender_type']) ?></td>
                                <td class="text-center fw-semibold"><?= (int)$h['total_seats'] ?></td>
                                <td class="text-center">
                                    <span class="badge bg-primary"><?= (int)$h['booked_seats'] ?></span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success"><?= (int)$h['available_seats'] ?></span>
                                </td>
                                <td style="min-width:160px;">
                                    <div class="score-bar-wrapper">
                                        <div class="score-bar">
                                            <div class="score-bar-fill"
                                                 data-score="<?= $pct ?>"
                                                 style="background:<?= $color ?>;"></div>
                                        </div>
                                        <span class="score-value" style="color:<?= $color ?>; font-size:12px;">
                                            <?= number_format($pct, 1) ?>%
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($h['manager_name']): ?>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-circle" style="width:26px;height:26px;font-size:11px;flex-shrink:0;">
                                            <?= strtoupper(substr($h['manager_name'], 0, 1)) ?>
                                        </div>
                                        <span class="small"><?= htmlspecialchars($h['manager_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Occupancy Summary Cards -->
                <div class="table-footer">
                    <div class="row g-2 text-center">
                        <?php
                        $totalBooked    = array_sum(array_column($hallOccupancy, 'booked_seats'));
                        $totalAvailable = array_sum(array_column($hallOccupancy, 'available_seats'));
                        $totalSeatsAll  = array_sum(array_column($hallOccupancy, 'total_seats'));
                        $overallPct     = $totalSeatsAll > 0 ? round($totalBooked / $totalSeatsAll * 100, 1) : 0;
                        ?>
                        <div class="col-3">
                            <div class="p-2 rounded" style="background:#f8fafc;">
                                <div class="fw-bold fs-6"><?= count($hallOccupancy) ?></div>
                                <div class="text-muted" style="font-size:11px;">Total Halls</div>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="p-2 rounded" style="background:#eff6ff;">
                                <div class="fw-bold fs-6 text-primary"><?= $totalSeatsAll ?></div>
                                <div class="text-muted" style="font-size:11px;">Total Seats</div>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="p-2 rounded" style="background:#fef9ec;">
                                <div class="fw-bold fs-6" style="color:#f59e0b;"><?= $totalBooked ?></div>
                                <div class="text-muted" style="font-size:11px;">Occupied</div>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="p-2 rounded" style="background:#ecfdf5;">
                                <div class="fw-bold fs-6 text-success"><?= $overallPct ?>%</div>
                                <div class="text-muted" style="font-size:11px;">Overall Occupancy</div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div><!-- /table-wrapper -->
        </div>
    </div><!-- /row 3 -->

</div><!-- /.content-area -->
</div><!-- /.main-content -->
<?php include ROOT . '/includes/footer.php'; ?>
