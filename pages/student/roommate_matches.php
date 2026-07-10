<?php
/**
 * NestSync — Student: Roommate Matches
 * OCI8 / Oracle
 */
require_once '../../config/config.php';
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/auth_check.php';
require_once ROOT . '/includes/functions.php';

requireRole(['STUDENT']);
$pageTitle  = 'Roommate Matches';
$activePage = 'roommate_matches';
$uid        = currentUserId();

// ── POST: update match status ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid security token.');
    } else {
        $matchId   = (int)($_POST['match_id']   ?? 0);
        $newStatus = sanitize($_POST['new_status'] ?? '');
        if (!in_array($newStatus, ['ACCEPTED','DECLINED'], true)) {
            setFlash('danger', 'Invalid status.');
        } else {
            $ok = oci_execute_dml(
                'UPDATE roommate_matches SET match_status=:s WHERE match_id=:m AND student_id=:u',
                [':s' => $newStatus, ':m' => $matchId, ':u' => $uid]
            );
            setFlash($ok ? 'success' : 'danger', $ok ? 'Match status updated.' : 'Failed to update.');
        }
    }
    redirect(BASE_URL . '/pages/student/roommate_matches.php');
}

// ── Filters ────────────────────────────────────────────────────────────────
$minScore    = max(0, (int)($_GET['min_score'] ?? 40));
$deptFilter  = sanitize($_GET['department'] ?? 'ALL');
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$perPage     = 12;

$innerSql = 'SELECT * FROM vw_roommate_matches_detail WHERE student_id=:u AND match_score>=:min';
$binds    = [':u' => $uid, ':min' => $minScore];

if ($deptFilter !== 'ALL') {
    $innerSql .= ' AND matched_dept=:dept';
    $binds[':dept'] = $deptFilter;
}

$result  = oci_paginate($innerSql, $binds, $currentPage, $perPage, 'match_score DESC');
$matches = $result['rows'];
$total   = $result['total'];

// Distinct departments in matches
$deptRows = oci_fetch_all_assoc(
    'SELECT DISTINCT matched_dept FROM vw_roommate_matches_detail WHERE student_id=:u AND matched_dept IS NOT NULL ORDER BY matched_dept',
    [':u' => $uid]
);

$baseUrl = BASE_URL . '/pages/student/roommate_matches.php?min_score=' . $minScore . '&department=' . urlencode($deptFilter);

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

<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h1 class="page-heading mb-1 fw-bold" style="color: var(--dark);">
                <i class="fas fa-user-friends me-2" style="color: var(--primary);"></i>Roommate Matches
                <span class="badge rounded-pill ms-2" style="background: linear-gradient(135deg, var(--primary), var(--secondary)); font-size: 14px; padding: 0.5em 0.8em;">
                    <?= $total ?> Suggestion<?= $total !== 1 ? 's' : '' ?>
                </span>
            </h1>
            <p class="text-muted mb-0" style="font-size: 0.95rem;">
                Discover compatible roommates based on your preferences, department, and budget.
            </p>
        </div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/student/dashboard.php" class="text-decoration-none">Dashboard</a></li>
                <li class="breadcrumb-item active">Roommate Matches</li>
            </ol>
        </nav>
    </div>

    <!-- Filter Bar -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body py-3">
            <form method="GET" action="">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Min Compatibility Score</label>
                        <input type="number" name="min_score" class="form-control" min="0" max="100" value="<?= $minScore ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Department</label>
                        <select name="department" class="form-select">
                            <option value="ALL">All Departments</option>
                            <?php foreach ($deptRows as $d): ?>
                            <option value="<?= htmlspecialchars($d['MATCHED_DEPT'], ENT_QUOTES, 'UTF-8') ?>"
                                    <?= $deptFilter === $d['MATCHED_DEPT'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d['MATCHED_DEPT'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button>
                    </div>
                    <?php if ($minScore !== 40 || $deptFilter !== 'ALL'): ?>
                    <div class="col-md-2">
                        <a href="<?= BASE_URL ?>/pages/student/roommate_matches.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-times me-1"></i>Reset
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Match Cards -->
    <?php if (empty($matches)): ?>
    <div class="card border-0 shadow-sm rounded-4 text-center mt-4" style="background: linear-gradient(to bottom right, #ffffff, #f8fafc);">
        <div class="card-body p-5">
            <div class="empty-state py-5">
                <div class="empty-state-icon mb-4" style="font-size: 4rem; color: #cbd5e1;">
                    <i class="fas fa-user-slash"></i>
                </div>
                <h4 class="fw-bold text-dark mb-3">No Roommate Suggestions Yet</h4>
                <p class="text-muted mx-auto" style="max-width: 500px; font-size: 1.05rem;">
                    <?php if ($minScore > 0 || $deptFilter !== 'ALL'): ?>
                        We couldn't find any matches matching your current filters. Try lowering the minimum score or exploring other departments.
                    <?php else: ?>
                        Your perfect roommate might not have signed up yet, or your profile needs more details for our matching engine to work effectively.
                    <?php endif; ?>
                </p>
                <div class="mt-4">
                    <a href="<?= BASE_URL ?>/pages/student/profile.php" class="btn btn-primary px-4 py-2 rounded-pill shadow-sm hover-lift">
                        <i class="fas fa-user-edit me-2"></i>Update My Preferences
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="row g-3">
    <?php foreach ($matches as $m):
        $scoreClass  = scoreBadgeClass((float)$m['MATCH_SCORE']);
        $initials    = strtoupper(substr($m['MATCHED_NAME'], 0, 1));
        $myInitial   = strtoupper(substr(currentUserName(), 0, 1));
        $matchStatus = $m['MATCH_STATUS'];
    ?>
    <div class="col-md-6 col-lg-4 fade-in-up">
        <div class="card h-100 border-0 shadow-sm" style="border-radius:14px;<?= $matchStatus==='ACCEPTED' ? 'border:2px solid var(--success)!important;' : ($matchStatus==='DECLINED' ? 'opacity:.75;' : '') ?>">
            <div class="card-body p-4">
                <!-- Avatar pair -->
                <div class="d-flex align-items-center justify-content-center gap-3 mb-3">
                    <div>
                        <div class="avatar-circle" style="width:48px;height:48px;font-size:18px;"><?= $myInitial ?></div>
                        <div class="text-center text-muted" style="font-size:9px;margin-top:3px;">You</div>
                    </div>
                    <div class="text-muted"><i class="fas fa-exchange-alt" style="font-size:18px;"></i></div>
                    <div>
                        <div class="avatar-circle" style="width:48px;height:48px;font-size:18px;background:linear-gradient(135deg,#7c3aed,#4cc9f0);"><?= $initials ?></div>
                        <div class="text-center text-muted" style="font-size:9px;margin-top:3px;"><?= htmlspecialchars(explode(' ', $m['MATCHED_NAME'])[0], ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                </div>

                <!-- Matched student info -->
                <h6 class="fw-bold text-center mb-1"><?= htmlspecialchars($m['MATCHED_NAME'], ENT_QUOTES, 'UTF-8') ?></h6>
                <div class="text-center text-muted small mb-3">
                    <span class="badge bg-light text-dark border me-1"><?= htmlspecialchars($m['MATCHED_DEPT']??'—', ENT_QUOTES, 'UTF-8') ?></span>
                    <?= formatCurrency((float)($m['MATCHED_BUDGET']??0)) ?>/mo
                </div>

                <!-- Score -->
                <div class="text-center mb-3">
                    <div class="score-badge <?= $scoreClass ?> shadow-sm" style="font-size:22px;width:64px;height:64px;margin:0 auto; display: flex; align-items: center; justify-content: center; font-weight: 700;">
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
                        <div class="score-bar-fill <?= $scoreClass ?>" style="height: 100%; width: <?= (int)$m['MATCH_SCORE'] ?>%; border-radius: 10px; transition: width 1s ease-in-out;"></div>
                    </div>
                </div>

                <!-- Match badges -->
                <div class="d-flex flex-wrap justify-content-center gap-2 mb-3">
                    <?php if ($m['DEPT_MATCH']): ?><span class="badge bg-primary-soft rounded-pill px-3 py-2"><i class="fas fa-graduation-cap me-1"></i>Same Dept</span><?php endif; ?>
                    <?php if ($m['BUDGET_MATCH']): ?><span class="badge bg-success-soft rounded-pill px-3 py-2"><i class="fas fa-dollar-sign me-1"></i>Budget Fit</span><?php endif; ?>
                    <?php if ((int)($m['PREF_OVERLAP']??0) > 0): ?><span class="badge bg-info-soft rounded-pill px-3 py-2 text-info"><i class="fas fa-heart me-1"></i><?= (int)$m['PREF_OVERLAP'] ?> Shared Prefs</span><?php endif; ?>
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

                <!-- Status + Actions -->
                <div class="d-flex justify-content-between align-items-center">
                    <?= statusBadge($matchStatus) ?>
                    <small class="text-muted"><?= formatDateShort($m['MATCHED_AT']) ?></small>
                </div>

                <?php if ($matchStatus === 'SUGGESTED'): ?>
                <div class="d-flex gap-2 mt-4 pt-3 border-top">
                    <form method="POST" class="flex-fill">
                        <?= csrfField() ?>
                        <input type="hidden" name="action"     value="update_status">
                        <input type="hidden" name="match_id"   value="<?= (int)$m['MATCH_ID'] ?>">
                        <input type="hidden" name="new_status" value="ACCEPTED">
                        <button type="submit" class="btn btn-success btn-sm w-100 rounded-pill hover-lift shadow-sm fw-semibold">
                            <i class="fas fa-check-circle me-1"></i> Accept Match
                        </button>
                    </form>
                    <form method="POST" class="flex-fill">
                        <?= csrfField() ?>
                        <input type="hidden" name="action"     value="update_status">
                        <input type="hidden" name="match_id"   value="<?= (int)$m['MATCH_ID'] ?>">
                        <input type="hidden" name="new_status" value="DECLINED">
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100 rounded-pill hover-lift fw-semibold">
                            <i class="fas fa-times-circle me-1"></i> Decline
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>

    <?php if ($total > $perPage): ?>
    <div class="mt-4"><?= renderPagination($total, $currentPage, $perPage, $baseUrl) ?></div>
    <?php endif; ?>
    <?php endif; ?>

</div>
</div>
<?php include ROOT . '/includes/footer.php'; ?>

<style>
.bg-primary-soft { background:var(--primary-light,#eff6ff); color:var(--primary,#4361ee); }
.bg-success-soft { background:#ecfdf5; color:#059669; }
.bg-info-soft { background:#cff4fc; }
.hover-lift { transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; }
.hover-lift:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
</style>
