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

<div class="d-flex align-items-center justify-content-between mb-4">
    <h1 class="page-heading mb-0"><i class="fas fa-chart-pie me-2 text-primary"></i>Reports & Analytics</h1>
    <div>
        <a href="export_reports.php?type=occupancy" class="btn btn-outline-success me-2" id="exportBtn"><i class="fas fa-file-csv me-1"></i>Export CSV</a>
        <button class="btn btn-outline-primary" onclick="window.print()"><i class="fas fa-print me-1"></i>Print Report</button>
    </div>
</div>

<ul class="nav nav-tabs mb-4" id="reportTabs">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-occ">Occupancy</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-book">Bookings</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-stu">Students</button></li>
</ul>

<div class="tab-content">
    <!-- Occupancy -->
    <div class="tab-pane fade show active" id="tab-occ">
        <div class="row g-4 mb-4">
            <?php foreach($hallsOcc as $h): $occ = (float)$h['OCCUPANCY_PCT']; ?>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="fw-bold text-primary mb-3"><?=htmlspecialchars($h['HALL_NAME'])?></h5>
                        <div class="d-flex justify-content-between mb-1 small">
                            <span>Occupancy</span><span><?=$occ?>%</span>
                        </div>
                        <div class="progress mb-3" style="height:10px;"><div class="progress-bar <?=$occ>=90?'bg-danger':($occ>=70?'bg-warning':'bg-success')?>" style="width:<?=$occ?>%"></div></div>
                        <div class="d-flex justify-content-between text-muted small">
                            <div><strong><?=(int)$h['BOOKED_SEATS']?></strong> Booked</div>
                            <div><strong><?=(int)$h['AVAILABLE_SEATS']?></strong> Available</div>
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
        <div class="row g-3 mb-4">
            <div class="col-3"><div class="p-3 bg-white rounded shadow-sm text-center"><h3 class="text-warning mb-0"><?=$bookStats['PENDING']?></h3><small class="text-muted">Pending</small></div></div>
            <div class="col-3"><div class="p-3 bg-white rounded shadow-sm text-center"><h3 class="text-success mb-0"><?=$bookStats['APPROVED']?></h3><small class="text-muted">Approved</small></div></div>
            <div class="col-3"><div class="p-3 bg-white rounded shadow-sm text-center"><h3 class="text-danger mb-0"><?=$bookStats['REJECTED']?></h3><small class="text-muted">Rejected</small></div></div>
            <div class="col-3"><div class="p-3 bg-white rounded shadow-sm text-center"><h3 class="text-secondary mb-0"><?=$bookStats['CANCELLED']?></h3><small class="text-muted">Cancelled</small></div></div>
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
                <div class="table-wrapper h-100">
                    <div class="table-header"><h5 class="mb-0">By Gender</h5></div>
                    <div class="p-4 d-flex justify-content-around">
                        <?php foreach($genderStats as $g): ?>
                        <div class="text-center">
                            <div class="display-6 fw-bold text-primary"><?=(int)$g['CNT']?></div>
                            <div class="text-muted text-uppercase small"><?=htmlspecialchars($g['GENDER']??'Unknown')?></div>
                        </div>
                        <?php endforeach; ?>
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
<?php include ROOT . '/includes/footer.php'; ?>
