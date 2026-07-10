<?php
/**
 * NestSync — Admin: Roommate Matches (View All)
 * OCI8 / Oracle
 */
require_once '../../config/config.php';
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/auth_check.php';
require_once ROOT . '/includes/functions.php';

requireRole(['SYSTEM_ADMIN', 'HALL_ADMIN']);
$pageTitle  = 'All Roommate Matches';
$activePage = 'roommate_matches';
$uid        = currentUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'run_engine') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        global $conn;
        $stmt = oci_parse($conn, 'BEGIN PKG_NESTSYNC.sp_run_all_matches; END;');
        if (@oci_execute($stmt, OCI_COMMIT_ON_SUCCESS)) {
            setFlash('success', 'Matching engine executed successfully.');
        } else {
            setFlash('danger', 'Failed to run matching engine.');
        }
        oci_free_statement($stmt);
        redirect(BASE_URL . '/pages/admin/roommate_matches.php');
    }
}

$minScore    = max(0, (int)($_GET['min_score'] ?? 40));
$currentPage = max(1, (int)($_GET['page'] ?? 1));

$innerSql = 'SELECT * FROM vw_roommate_matches_detail WHERE student_id < matched_student_id AND match_score >= :min';
$binds = [':min' => $minScore];
$res = oci_paginate($innerSql, $binds, $currentPage, 12, 'match_score DESC');
$matches = $res['rows'];
$total = $res['total'];
$baseUrl = BASE_URL . '/pages/admin/roommate_matches.php?min_score='.$minScore;

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
    <h1 class="page-heading mb-0"><i class="fas fa-network-wired me-2 text-primary"></i>Global Matches</h1>
    <form method="POST">
        <?=csrfField()?><input type="hidden" name="action" value="run_engine">
        <button type="submit" class="btn btn-warning shadow-sm"><i class="fas fa-bolt me-1"></i>Run Matching Engine</button>
    </form>
</div>

<div class="card mb-4 shadow-sm">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3"><label class="small fw-semibold">Min Score</label><input type="number" name="min_score" class="form-control" value="<?=$minScore?>"></div>
            <div class="col-md-2 d-flex gap-1"><button type="submit" class="btn btn-primary w-100">Filter</button></div>
        </form>
    </div>
</div>

<div class="row g-3">
    <?php foreach($matches as $m): $s1Name = explode(' ', $m['STUDENT_NAME'])[0]; $s2Name = explode(' ', $m['MATCHED_NAME'])[0]; ?>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100 border-0 shadow-sm" style="border-radius:12px;">
            <div class="card-body p-4 text-center">
                <div class="d-flex align-items-center justify-content-center gap-3 mb-3">
                    <div class="avatar-circle" style="width:48px;height:48px;"><?=strtoupper(substr($s1Name,0,1))?></div>
                    <div class="text-muted"><i class="fas fa-exchange-alt"></i></div>
                    <div class="avatar-circle" style="width:48px;height:48px;background:var(--primary);"><?=strtoupper(substr($s2Name,0,1))?></div>
                </div>
                <h6 class="fw-bold"><?=htmlspecialchars($s1Name)?> & <?=htmlspecialchars($s2Name)?></h6>
                <!-- Score -->
                <div class="text-center mb-3">
                    <div class="score-badge <?= $m['MATCH_SCORE']>=70?'score-high':($m['MATCH_SCORE']>=40?'score-medium':'score-low') ?> shadow-sm" style="font-size:22px;width:64px;height:64px;margin:0 auto; display: flex; align-items: center; justify-content: center; font-weight: 700;">
                        <?= (int)$m['MATCH_SCORE'] ?><span style="font-size: 14px;">%</span>
                    </div>
                    <?php
                        $matchLabel = 'Low Match';
                        $labelColor = 'text-muted';
                        if ($m['MATCH_SCORE'] >= 85) { $matchLabel = 'Excellent Match'; $labelColor = 'text-success fw-bold'; }
                        elseif ($m['MATCH_SCORE'] >= 70) { $matchLabel = 'Great Match'; $labelColor = 'text-primary fw-bold'; }
                        elseif ($m['MATCH_SCORE'] >= 50) { $matchLabel = 'Good Match'; $labelColor = 'text-info fw-bold'; }
                    ?>
                    <div class="small mt-2 <?= $labelColor ?>"><?= $matchLabel ?></div>
                </div>

                <!-- Score bar -->
                <div class="score-bar-wrapper mb-3" style="height: 6px; background-color: #f1f5f9; border-radius: 10px; overflow: hidden;">
                    <div class="score-bar" style="height: 100%;">
                        <div class="score-bar-fill <?= $m['MATCH_SCORE']>=70?'score-high':($m['MATCH_SCORE']>=40?'score-medium':'score-low') ?>" style="height: 100%; width: <?= (int)$m['MATCH_SCORE'] ?>%; border-radius: 10px; transition: width 1s ease-in-out;"></div>
                    </div>
                </div>

                <!-- Reason -->
                <?php if (!empty($m['MATCH_REASON'])): ?>
                <div class="p-2 mb-3 rounded" style="background-color: #f8fafc; border-left: 3px solid var(--primary);">
                    <p class="text-muted text-center mb-0" style="font-size:12px; font-style: italic;">
                        <i class="fas fa-quote-left me-1" style="color: #cbd5e1; font-size: 10px;"></i>
                        <?= htmlspecialchars(truncate($m['MATCH_REASON'], 100), ENT_QUOTES, 'UTF-8') ?>
                        <i class="fas fa-quote-right ms-1" style="color: #cbd5e1; font-size: 10px;"></i>
                    </p>
                </div>
                <?php endif; ?>
                <?=statusBadge($m['MATCH_STATUS'])?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if(empty($matches)): ?><div class="col-12"><div class="p-5 text-center text-muted">No matches found above this score threshold.</div></div><?php endif; ?>
</div>
<?php if($total>12): ?><div class="mt-3"><?=renderPagination($total,$currentPage,12,$baseUrl)?></div><?php endif; ?>

</div>
</div>
<?php include ROOT . '/includes/footer.php'; ?>
