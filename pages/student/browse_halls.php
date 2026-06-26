<?php
/**
 * NestSync — Student: Browse Halls
 * View all active halls with occupancy info and CTA to book seats.
 */
require_once '../../config/config.php';
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/auth_check.php';
require_once ROOT . '/includes/functions.php';

requireRole(['STUDENT']);

$pageTitle = 'Browse Halls';
$activePage = 'browse_halls';

// ─── Filters ─────────────────────────────────────────────────────────────────
$search      = sanitize($_GET['search']      ?? '');
$genderFilter = sanitize($_GET['gender_type'] ?? 'ALL');
$validGenders = ['ALL', 'MALE', 'FEMALE', 'MIXED'];
if (!in_array($genderFilter, $validGenders, true)) $genderFilter = 'ALL';

// Build base WHERE — always ACTIVE halls only
$conditions = ['h.hall_status = ?'];
$types      = 's';
$params     = ['ACTIVE'];

if ($search !== '') {
    $conditions[] = '(h.hall_name LIKE ? OR h.hall_location LIKE ?)';
    $types .= 'ss';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}

if ($genderFilter !== 'ALL') {
    $conditions[] = 'h.gender_type = ?';
    $types .= 's';
    $params[] = $genderFilter;
}

$whereClause = 'WHERE ' . implode(' AND ', $conditions);

// ─── Count for pagination ─────────────────────────────────────────────────────
$countSql = "
    SELECT COUNT(*) AS cnt
    FROM vw_hall_occupancy h
    {$whereClause}
";
$stmtCount = $conn->prepare($countSql);
if ($params) {
    $stmtCount->bind_param($types, ...$params);
}
$stmtCount->execute();
$totalHalls = (int)$stmtCount->get_result()->fetch_assoc()['cnt'];
$stmtCount->close();

// Pagination — 9 cards per page (3×3 grid)
$perPage     = 9;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$totalPages  = (int)ceil($totalHalls / $perPage);
$offset      = ($currentPage - 1) * $perPage;

// ─── Fetch halls ──────────────────────────────────────────────────────────────
$sql = "
    SELECT
        h.hall_id,
        h.hall_name,
        h.hall_location,
        h.description,
        h.gender_type,
        h.hall_status,
        h.total_capacity,
        h.manager_name,
        h.total_seats,
        h.available_seats,
        h.booked_seats,
        h.reserved_seats,
        h.occupancy_pct
    FROM vw_hall_occupancy h
    {$whereClause}
    ORDER BY h.hall_name ASC
    LIMIT ? OFFSET ?
";
$typesWithPaging = $types . 'ii';
$paramsWithPaging = array_merge($params, [$perPage, $offset]);

$stmt = $conn->prepare($sql);
$stmt->bind_param($typesWithPaging, ...$paramsWithPaging);
$stmt->execute();
$halls = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ─── Build base URL for pagination ───────────────────────────────────────────
$baseUrl = BASE_URL . '/pages/student/browse_halls.php?search=' . urlencode($search)
         . '&gender_type=' . urlencode($genderFilter);

// ─── Include layout ───────────────────────────────────────────────────────────
include ROOT . '/includes/header.php';
include ROOT . '/includes/sidebar.php';
?>
<div class="main-content">
<?php include ROOT . '/includes/navbar_top.php'; ?>
<div class="content-area">

    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h1 class="page-heading mb-1">
                <i class="fas fa-building me-2 text-primary"></i>Browse Halls
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="<?= BASE_URL ?>/pages/student/dashboard.php">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active">Browse Halls</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary-soft fs-6 px-3 py-2">
                <i class="fas fa-th-large me-1"></i>
                <?= $totalHalls ?> Hall<?= $totalHalls !== 1 ? 's' : '' ?> Found
            </span>
        </div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body py-3">
            <form method="GET" action="" id="filterForm">
                <div class="search-filter-bar">
                    <!-- Search -->
                    <div class="search-input-wrapper" style="flex:2; min-width:220px;">
                        <i class="fas fa-search"></i>
                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            placeholder="Search by hall name or location…"
                            value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>

                    <!-- Gender Type Filter -->
                    <div>
                        <select name="gender_type" class="filter-select" onchange="document.getElementById('filterForm').submit()">
                            <option value="ALL"   <?= $genderFilter === 'ALL'    ? 'selected' : '' ?>>All Genders</option>
                            <option value="MALE"  <?= $genderFilter === 'MALE'   ? 'selected' : '' ?>>🔵 Male Only</option>
                            <option value="FEMALE"<?= $genderFilter === 'FEMALE' ? 'selected' : '' ?>>🔴 Female Only</option>
                            <option value="MIXED" <?= $genderFilter === 'MIXED'  ? 'selected' : '' ?>>🟣 Mixed</option>
                        </select>
                    </div>

                    <!-- Search Button -->
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i>Search
                    </button>

                    <!-- Clear -->
                    <?php if ($search !== '' || $genderFilter !== 'ALL'): ?>
                    <a href="<?= BASE_URL ?>/pages/student/browse_halls.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>Clear
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Active Filter Pills -->
    <?php if ($search !== '' || $genderFilter !== 'ALL'): ?>
    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
        <small class="text-muted fw-600">Active filters:</small>
        <?php if ($search !== ''): ?>
            <span class="badge bg-primary-soft">
                <i class="fas fa-search me-1"></i>
                "<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
            </span>
        <?php endif; ?>
        <?php if ($genderFilter !== 'ALL'): ?>
            <span class="badge bg-info text-white">
                <i class="fas fa-venus-mars me-1"></i>
                <?= htmlspecialchars($genderFilter, ENT_QUOTES, 'UTF-8') ?>
            </span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Hall Cards Grid -->
    <?php if (empty($halls)): ?>
        <!-- Empty State -->
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="empty-state py-5">
                    <div class="empty-state-icon">🏛️</div>
                    <h5>No Halls Found</h5>
                    <p class="text-muted">
                        <?php if ($search !== '' || $genderFilter !== 'ALL'): ?>
                            No halls match your current filters. Try adjusting your search or clearing the filters.
                        <?php else: ?>
                            There are no active halls available at the moment. Please check back later.
                        <?php endif; ?>
                    </p>
                    <?php if ($search !== '' || $genderFilter !== 'ALL'): ?>
                        <a href="<?= BASE_URL ?>/pages/student/browse_halls.php" class="btn btn-primary mt-2">
                            <i class="fas fa-times me-1"></i>Clear Filters
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
        <?php foreach ($halls as $hall):
            $occupancy    = (float)($hall['occupancy_pct'] ?? 0);
            $isFull       = $occupancy >= 100;
            $totalSeats   = (int)($hall['total_seats'] ?? 0);
            $availSeats   = (int)($hall['available_seats'] ?? 0);
            $bookedSeats  = (int)($hall['booked_seats'] ?? 0);
            $reserveSeats = (int)($hall['reserved_seats'] ?? 0);
            $description  = $hall['description'] ?? '';
            $managerName  = $hall['manager_name'] ?? 'N/A';

            // Occupancy bar color
            if ($isFull) {
                $progressClass = 'bg-danger';
            } elseif ($occupancy > 75) {
                $progressClass = 'bg-warning';
            } else {
                $progressClass = 'bg-success';
            }

            // Occupancy label
            if ($isFull) {
                $occupancyLabel = '<span class="badge bg-danger ms-1"><i class="fas fa-times-circle me-1"></i>Full</span>';
            } elseif ($occupancy > 75) {
                $occupancyLabel = '<span class="badge bg-warning text-dark ms-1"><i class="fas fa-exclamation-triangle me-1"></i>Filling Up</span>';
            } else {
                $occupancyLabel = '<span class="badge bg-success ms-1"><i class="fas fa-check-circle me-1"></i>Available</span>';
            }
        ?>
        <div class="col-md-6 col-lg-4 fade-in-up">
            <div class="card h-100 border-0 shadow-sm hall-card" style="border-radius:14px; transition: transform 0.25s ease, box-shadow 0.25s ease;">
                <!-- Card Top Ribbon -->
                <div class="card-header border-0 pb-0 pt-3 px-4" style="background: linear-gradient(135deg, #f0f4ff 0%, #fff 100%); border-radius: 14px 14px 0 0;">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <div class="flex-grow-1 min-width-0">
                            <h5 class="fw-700 mb-1 text-truncate" style="font-size:16px; color:var(--text-primary);" title="<?= htmlspecialchars($hall['hall_name'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($hall['hall_name'], ENT_QUOTES, 'UTF-8') ?>
                            </h5>
                            <div class="d-flex align-items-center gap-1 flex-wrap">
                                <?= statusBadge($hall['gender_type']) ?>
                                <?= $occupancyLabel ?>
                            </div>
                        </div>
                        <!-- Hall Icon -->
                        <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-3"
                             style="width:44px;height:44px;background:var(--primary-light);font-size:20px;">
                            🏛️
                        </div>
                    </div>
                </div>

                <div class="card-body px-4 py-3 d-flex flex-column">

                    <!-- Location -->
                    <p class="mb-2 text-secondary" style="font-size:13px;">
                        <i class="fas fa-map-marker-alt text-danger me-1"></i>
                        <?= htmlspecialchars($hall['hall_location'], ENT_QUOTES, 'UTF-8') ?>
                    </p>

                    <!-- Description -->
                    <?php if ($description !== ''): ?>
                    <p class="mb-3 text-muted" style="font-size:13px; line-height:1.55;">
                        <?= htmlspecialchars(
                            strlen($description) > 100 ? substr($description, 0, 100) . '…' : $description,
                            ENT_QUOTES, 'UTF-8'
                        ) ?>
                    </p>
                    <?php else: ?>
                    <p class="mb-3 text-muted fst-italic" style="font-size:13px;">No description available.</p>
                    <?php endif; ?>

                    <!-- Manager -->
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="avatar-circle" style="width:28px;height:28px;font-size:11px;">
                            <?= strtoupper(substr($managerName, 0, 1)) ?>
                        </div>
                        <span style="font-size:12.5px; color:var(--text-secondary);">
                            Managed by <strong><?= htmlspecialchars($managerName, ENT_QUOTES, 'UTF-8') ?></strong>
                        </span>
                    </div>

                    <!-- Occupancy Progress -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <small class="fw-600 text-muted" style="font-size:11.5px;">OCCUPANCY</small>
                            <small class="fw-700" style="font-size:12px; color: <?= $isFull ? 'var(--danger)' : ($occupancy > 75 ? 'var(--warning)' : 'var(--success)') ?>;">
                                <?= number_format($occupancy, 0) ?>%
                            </small>
                        </div>
                        <div class="progress" style="height:8px; border-radius:4px; background:#e2e8f0;">
                            <div class="progress-bar <?= $progressClass ?>"
                                 role="progressbar"
                                 style="width:<?= min(100, $occupancy) ?>%; border-radius:4px; transition: width 0.6s ease;"
                                 aria-valuenow="<?= $occupancy ?>"
                                 aria-valuemin="0"
                                 aria-valuemax="100">
                            </div>
                        </div>
                    </div>

                    <!-- Stats Row -->
                    <div class="row g-0 text-center mb-3 rounded-3 overflow-hidden" style="background:#f8fafc; border:1px solid #e2e8f0;">
                        <div class="col-4 py-2" style="border-right:1px solid #e2e8f0;">
                            <div class="fw-700 mb-0" style="font-size:16px; color:var(--text-primary);"><?= $totalSeats ?></div>
                            <div style="font-size:10.5px; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing:0.4px;">Total</div>
                        </div>
                        <div class="col-4 py-2" style="border-right:1px solid #e2e8f0;">
                            <div class="fw-700 mb-0" style="font-size:16px; color:var(--success);"><?= $availSeats ?></div>
                            <div style="font-size:10.5px; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing:0.4px;">Available</div>
                        </div>
                        <div class="col-4 py-2">
                            <div class="fw-700 mb-0" style="font-size:16px; color:var(--primary);"><?= $bookedSeats + $reserveSeats ?></div>
                            <div style="font-size:10.5px; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing:0.4px;">Booked</div>
                        </div>
                    </div>

                    <!-- Spacer push CTA to bottom -->
                    <div class="mt-auto">
                        <?php if ($isFull): ?>
                            <!-- Full Hall — disabled button -->
                            <div class="d-flex gap-2">
                                <button class="btn btn-secondary w-100" disabled>
                                    <i class="fas fa-ban me-1"></i>Hall is Full
                                </button>
                            </div>
                        <?php else: ?>
                            <a href="<?= BASE_URL ?>/pages/student/browse_seats.php?hall_id=<?= (int)$hall['hall_id'] ?>"
                               class="btn btn-primary w-100">
                                <i class="fas fa-door-open me-1"></i>View Rooms &amp; Seats
                            </a>
                        <?php endif; ?>
                    </div>

                </div><!-- /.card-body -->
            </div><!-- /.card -->
        </div><!-- /.col -->
        <?php endforeach; ?>
        </div><!-- /.row -->

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="mt-4">
            <?= renderPagination($totalHalls, $currentPage, $perPage, $baseUrl) ?>
        </div>
        <?php endif; ?>

    <?php endif; ?>

</div><!-- /.content-area -->
</div><!-- /.main-content -->
<?php include ROOT . '/includes/footer.php'; ?>

<style>
/* Hall card hover effect */
.hall-card:hover {
    transform: translateY(-5px) !important;
    box-shadow: 0 10px 35px rgba(67, 97, 238, 0.15) !important;
}
.fw-700 { font-weight: 700 !important; }
.fw-600 { font-weight: 600 !important; }
.min-width-0 { min-width: 0; }
</style>
