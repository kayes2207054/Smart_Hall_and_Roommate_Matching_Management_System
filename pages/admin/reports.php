<?php
/**
 * NestSync — Admin: Reports
 * OCI8 / Oracle
 */
require_once '../../config/config.php';
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/auth_check.php';
require_once ROOT . '/includes/functions.php';

requireRole(['SYSTEM_ADMIN', 'HALL_ADMIN']);
$pageTitle  = 'Reports & Analytics';
$activePage = 'reports';
$uid        = currentUserId();

$hallsOcc = oci_fetch_all_assoc('SELECT * FROM vw_hall_occupancy ORDER BY hall_name');
if(isHallAdmin()) $hallsOcc = array_filter($hallsOcc, fn($h) => (int)$h['MANAGED_BY'] === $uid);

$bookStatsRaw = oci_fetch_all_assoc('SELECT booking_status, count(*) as cnt FROM bookings GROUP BY booking_status');
$bookStats = ['PENDING'=>0,'APPROVED'=>0,'REJECTED'=>0,'CANCELLED'=>0];
foreach($bookStatsRaw as $r) $bookStats[$r['BOOKING_STATUS']] = (int)$r['CNT'];

$stuDeptStats = oci_fetch_all_assoc("SELECT department, count(*) as cnt, avg(monthly_budget) as avg_budget FROM users WHERE role_name='STUDENT' GROUP BY department ORDER BY cnt DESC");
$genderStats = oci_fetch_all_assoc("SELECT gender, count(*) as cnt FROM users WHERE role_name='STUDENT' GROUP BY gender");

$recentBookings = oci_fetch_all_assoc('SELECT * FROM vw_booking_summary WHERE ROWNUM<=10 ORDER BY requested_at DESC');

include ROOT . '/includes/header.php';
include ROOT . '/includes/sidebar.php';
?>
<style>@media print { .sidebar, .navbar, .btn, .nav-tabs { display: none !important; } .main-content { margin-left: 0 !important; padding: 0 !important; } .card { border: none !important; box-shadow: none !important; } }</style>
<div class="main-content">
<?php include ROOT . '/includes/navbar_top.php'; ?>
<div class="content-area">

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <h1 class="page-heading mb-1 fw-bold" style="color: var(--dark);">
            <i class="fas fa-chart-pie me-2" style="color: var(--primary);"></i>Reports & Analytics
        </h1>
        <p class="text-muted mb-0" style="font-size: 0.95rem;">
            View hall occupancy, booking statistics, and student demographics.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="export_reports.php?type=occupancy" class="btn btn-primary shadow-sm rounded-pill px-4 hover-lift" id="exportBtn">
            <i class="fas fa-file-csv me-2"></i>Export CSV
        </a>
        <button class="btn btn-outline-secondary shadow-sm rounded-pill px-4 hover-lift" onclick="window.print()">
            <i class="fas fa-print me-2"></i>Print
        </button>
    </div>
</div>

<ul class="nav nav-pills mb-4 gap-2" id="reportTabs">
    <li class="nav-item"><button class="nav-link active rounded-pill px-4" data-bs-toggle="tab" data-bs-target="#tab-occ"><i class="fas fa-building me-2"></i>Occupancy</button></li>
    <li class="nav-item"><button class="nav-link rounded-pill px-4" data-bs-toggle="tab" data-bs-target="#tab-book"><i class="fas fa-calendar-check me-2"></i>Bookings</button></li>
    <li class="nav-item"><button class="nav-link rounded-pill px-4" data-bs-toggle="tab" data-bs-target="#tab-stu"><i class="fas fa-user-graduate me-2"></i>Students</button></li>
</ul>

<div class="tab-content">
    <!-- Occupancy -->
    <div class="tab-pane fade show active" id="tab-occ">
        <div class="row g-4 mb-4">
            <?php foreach($hallsOcc as $h): $occ = (float)$h['OCCUPANCY_PCT']; ?>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm" style="border-radius: 12px; transition: transform 0.2s;">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h5 class="fw-bold text-dark mb-0"><?=htmlspecialchars($h['HALL_NAME'])?></h5>
                            <span class="badge <?= $occ>=90?'bg-danger-soft text-danger':($occ>=70?'bg-warning-soft text-warning':'bg-success-soft text-success') ?> rounded-pill px-2 py-1">
                                <?= $occ>=90 ? 'High' : ($occ>=70 ? 'Moderate' : 'Optimal') ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 small fw-semibold">
                            <span class="text-muted">Occupancy</span><span class="<?= $occ>=90?'text-danger':($occ>=70?'text-warning':'text-success') ?>"><?=$occ?>%</span>
                        </div>
                        <div class="progress mb-4" style="height: 8px; border-radius: 10px; background-color: #f1f5f9;">
                            <div class="progress-bar <?= $occ>=90?'bg-danger':($occ>=70?'bg-warning':'bg-success') ?>" style="width:<?=$occ?>%; border-radius: 10px;"></div>
                        </div>
                        <div class="row text-center g-0 border-top pt-3 mt-auto">
                            <div class="col-6 border-end">
                                <div class="text-muted small mb-1">Booked</div>
                                <h5 class="mb-0 fw-bold text-dark"><?=(int)$h['BOOKED_SEATS']?></h5>
                            </div>
                            <div class="col-6">
                                <div class="text-muted small mb-1">Available</div>
                                <h5 class="mb-0 fw-bold text-primary"><?=(int)$h['AVAILABLE_SEATS']?></h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="table-wrapper table-responsive">
            <table class="table table-sm table-hover align-middle">
                <thead><tr><th>Hall</th><th>Type</th><th>Total</th><th>Booked</th><th>Available</th><th>Maint.</th><th>Occupancy %</th></tr></thead>
                <tbody>
                    <?php foreach($hallsOcc as $h): ?>
                    <tr>
                        <td class="fw-medium"><?=htmlspecialchars($h['HALL_NAME'])?></td>
                        <td><?=htmlspecialchars($h['GENDER_TYPE'])?></td>
                        <td><?=(int)$h['TOTAL_SEATS']?></td>
                        <td class="text-success"><?=(int)$h['BOOKED_SEATS']?></td>
                        <td class="text-primary"><?=(int)$h['AVAILABLE_SEATS']?></td>
                        <td class="text-warning"><?=(int)$h['MAINTENANCE_SEATS']?></td>
                        <td><strong><?=(float)$h['OCCUPANCY_PCT']?>%</strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Bookings -->
    <div class="tab-pane fade" id="tab-book">
        <div class="row g-4 mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; border-bottom: 4px solid #f59e0b !important;">
                    <div class="card-body text-center p-4">
                        <div class="mb-2"><i class="fas fa-clock text-warning" style="font-size: 24px;"></i></div>
                        <h2 class="fw-bold text-dark mb-0"><?=$bookStats['PENDING']?></h2>
                        <div class="text-muted small text-uppercase fw-semibold mt-1">Pending</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; border-bottom: 4px solid #10b981 !important;">
                    <div class="card-body text-center p-4">
                        <div class="mb-2"><i class="fas fa-check-circle text-success" style="font-size: 24px;"></i></div>
                        <h2 class="fw-bold text-dark mb-0"><?=$bookStats['APPROVED']?></h2>
                        <div class="text-muted small text-uppercase fw-semibold mt-1">Approved</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; border-bottom: 4px solid #ef4444 !important;">
                    <div class="card-body text-center p-4">
                        <div class="mb-2"><i class="fas fa-times-circle text-danger" style="font-size: 24px;"></i></div>
                        <h2 class="fw-bold text-dark mb-0"><?=$bookStats['REJECTED']?></h2>
                        <div class="text-muted small text-uppercase fw-semibold mt-1">Rejected</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="card border-0 shadow-sm h-100" style="border-radius: 12px; border-bottom: 4px solid #6c757d !important;">
                    <div class="card-body text-center p-4">
                        <div class="mb-2"><i class="fas fa-ban text-secondary" style="font-size: 24px;"></i></div>
                        <h2 class="fw-bold text-dark mb-0"><?=$bookStats['CANCELLED']?></h2>
                        <div class="text-muted small text-uppercase fw-semibold mt-1">Cancelled</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-wrapper table-responsive">
            <div class="table-header"><h5 class="mb-0">Recent Bookings</h5></div>
            <table class="table table-sm table-hover">
                <thead><tr><th>ID</th><th>Student</th><th>Hall</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                    <?php foreach($recentBookings as $b): ?>
                    <tr>
                        <td>#<?=(int)$b['BOOKING_ID']?></td>
                        <td><?=htmlspecialchars($b['STUDENT_NAME'])?></td>
                        <td><?=htmlspecialchars($b['HALL_NAME'])?></td>
                        <td><?=statusBadge($b['BOOKING_STATUS'])?></td>
                        <td><?=formatDateShort($b['REQUESTED_AT'])?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Students -->
    <div class="tab-pane fade" id="tab-stu">
        <div class="row g-4">
            <div class="col-md-6">
                <div class="table-wrapper h-100">
                    <div class="table-header"><h5 class="mb-0">By Department</h5></div>
                    <table class="table table-sm m-0">
                        <thead><tr><th>Dept</th><th>Students</th><th>Avg Budget</th></tr></thead>
                        <tbody>
                            <?php foreach($stuDeptStats as $s): if(!$s['DEPARTMENT']) continue; ?>
                            <tr>
                                <td class="fw-medium"><?=htmlspecialchars($s['DEPARTMENT'])?></td>
                                <td><?=(int)$s['CNT']?></td>
                                <td><?=formatCurrency((float)$s['AVG_BUDGET'])?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
                    <div class="card-header bg-white border-0 pt-4 pb-0 px-4">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-venus-mars me-2 text-primary"></i>By Gender</h5>
                    </div>
                    <div class="card-body p-4 d-flex justify-content-around align-items-center">
                        <?php if (empty($genderStats)): ?>
                            <div class="text-muted small">No data available.</div>
                        <?php else: ?>
                            <?php foreach($genderStats as $g): 
                                $genderIcon = 'fa-user';
                                $genderColor = 'text-secondary';
                                if (strtoupper($g['GENDER']) === 'MALE') { $genderIcon = 'fa-male'; $genderColor = 'text-primary'; }
                                if (strtoupper($g['GENDER']) === 'FEMALE') { $genderIcon = 'fa-female'; $genderColor = 'text-danger'; }
                            ?>
                            <div class="text-center">
                                <div class="mb-2"><i class="fas <?= $genderIcon ?> <?= $genderColor ?>" style="font-size: 32px;"></i></div>
                                <div class="display-6 fw-bold text-dark"><?=(int)$g['CNT']?></div>
                                <div class="text-muted text-uppercase small fw-semibold mt-1"><?=htmlspecialchars($g['GENDER']??'Unknown')?></div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const exportBtn = document.getElementById('exportBtn');
    const tabs = document.querySelectorAll('#reportTabs button[data-bs-toggle="tab"]');
    tabs.forEach(tab => {
        tab.addEventListener('shown.bs.tab', function (e) {
            const targetId = e.target.getAttribute('data-bs-target');
            if(targetId === '#tab-occ') exportBtn.href = 'export_reports.php?type=occupancy';
            else if(targetId === '#tab-book') exportBtn.href = 'export_reports.php?type=bookings';
            else if(targetId === '#tab-stu') exportBtn.href = 'export_reports.php?type=students';
        });
    });
});
</script>
<style>
.bg-primary-soft { background:var(--primary-light,#eff6ff); color:var(--primary,#4361ee); }
.bg-success-soft { background:#ecfdf5; color:#059669; }
.bg-warning-soft { background:#fffbeb; color:#d97706; }
.bg-danger-soft { background:#fef2f2; color:#dc2626; }
.hover-lift { transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; }
.hover-lift:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
.nav-pills .nav-link { color: var(--dark); font-weight: 500; transition: all 0.2s; }
.nav-pills .nav-link.active { background: var(--primary); color: white; box-shadow: 0 4px 6px rgba(67, 97, 238, 0.2); }
</style>
<?php include ROOT . '/includes/footer.php'; ?>
