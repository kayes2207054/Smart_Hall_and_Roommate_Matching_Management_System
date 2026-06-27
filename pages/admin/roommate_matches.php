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
                <div class="score-badge <?= $m['MATCH_SCORE']>=70?'score-high':($m['MATCH_SCORE']>=40?'score-medium':'score-low') ?> mx-auto mb-2" style="font-size:20px;width:56px;height:56px;"><?=(int)$m['MATCH_SCORE']?></div>
                <div class="score-bar-wrapper mb-2"><div class="score-bar"><div class="score-bar-fill" style="width:<?=(int)$m['MATCH_SCORE']?>%"></div></div></div>
                <div class="small text-muted mb-2 text-truncate" title="<?=htmlspecialchars($m['MATCH_REASON'])?>"><?=htmlspecialchars($m['MATCH_REASON'])?></div>
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
