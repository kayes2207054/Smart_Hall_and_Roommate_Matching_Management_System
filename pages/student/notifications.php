<?php
/**
 * NestSync — Student Notifications
 * OCI8 / Oracle
 */
require_once '../../config/config.php';
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/auth_check.php';
require_once ROOT . '/includes/functions.php';

requireRole(['STUDENT']);
$pageTitle  = 'Notifications';
$activePage = 'notifications';
$uid        = currentUserId();

// ── POST: mark_all_read ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_all_read') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        oci_execute_dml('UPDATE notifications SET is_read=1 WHERE user_id=:u', [':u' => $uid]);
        setFlash('success', 'All notifications marked as read.');
    }
    redirect(BASE_URL . '/pages/student/notifications.php');
}

// ── POST: mark_one ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_one') {
    $nid = (int)($_POST['notif_id'] ?? 0);
    if (verifyCsrfToken($_POST['csrf_token'] ?? '') && $nid > 0) {
        oci_execute_dml('UPDATE notifications SET is_read=1 WHERE notification_id=:n AND user_id=:u', [':n' => $nid, ':u' => $uid]);
    }
    redirect(BASE_URL . '/pages/student/notifications.php');
}

// ── Filters ────────────────────────────────────────────────────────────────
$filter      = sanitize($_GET['filter'] ?? 'all');
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$perPage     = 20;

$innerSql = 'SELECT notification_id, user_id, title, message, notif_type, is_read, created_at FROM notifications WHERE user_id=:u';
$binds    = [':u' => $uid];
if ($filter === 'unread') {
    $innerSql .= ' AND is_read=0';
}

$result        = oci_paginate($innerSql, $binds, $currentPage, $perPage, 'created_at DESC');
$notifications = $result['rows'];
$total         = $result['total'];
$unreadCount   = (int)oci_fetch_scalar('SELECT COUNT(*) FROM notifications WHERE user_id=:u AND is_read=0', [':u' => $uid]);

$baseUrl = BASE_URL . '/pages/student/notifications.php?filter=' . urlencode($filter);

// Icon map
function notifIcon(string $type): string
{
    return match($type) {
        'BOOKING' => 'fas fa-calendar-check text-primary',
        'MATCH'   => 'fas fa-user-friends',
        'SYSTEM'  => 'fas fa-bell text-warning',
        default   => 'fas fa-info-circle text-muted',
    };
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
            <h1 class="page-heading mb-1">
                <i class="fas fa-bell me-2 text-warning"></i>Notifications
                <?php if ($unreadCount > 0): ?>
                <span class="badge bg-danger" style="font-size:14px;"><?= $unreadCount ?></span>
                <?php endif; ?>
            </h1>
            <nav aria-label="breadcrumb"><ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/student/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Notifications</li>
            </ol></nav>
        </div>
        <?php if ($unreadCount > 0): ?>
        <form method="POST" action="">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="mark_all_read">
            <button type="submit" class="btn btn-outline-secondary">
                <i class="fas fa-check-double me-1"></i>Mark All Read
            </button>
        </form>
        <?php endif; ?>
    </div>

    <!-- Filter Tabs -->
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link <?= $filter === 'all' ? 'active' : '' ?>" href="<?= BASE_URL ?>/pages/student/notifications.php?filter=all">
                All <span class="badge bg-secondary ms-1"><?= $total ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $filter === 'unread' ? 'active' : '' ?>" href="<?= BASE_URL ?>/pages/student/notifications.php?filter=unread">
                Unread <?php if ($unreadCount > 0): ?><span class="badge bg-danger ms-1"><?= $unreadCount ?></span><?php endif; ?>
            </a>
        </li>
    </ul>

    <div class="table-wrapper">
        <?php if (empty($notifications)): ?>
        <div class="empty-state py-5">
            <div class="empty-state-icon"><i class="fas fa-bell-slash"></i></div>
            <h5><?= $filter === 'unread' ? 'No Unread Notifications' : 'No Notifications' ?></h5>
            <p>You're all caught up!</p>
        </div>
        <?php else: ?>
        <div class="notification-list">
        <?php foreach ($notifications as $n):
            $isUnread = (int)$n['IS_READ'] === 0;
            $iconCls  = notifIcon($n['NOTIF_TYPE']);
        ?>
            <div class="d-flex align-items-start gap-3 p-4 border-bottom<?= $isUnread ? '' : ' opacity-75' ?>"
                 style="<?= $isUnread ? 'border-left:4px solid var(--primary);background:#f8fbff;' : 'border-left:4px solid transparent;' ?>">
                <!-- Icon -->
                <div class="flex-shrink-0 mt-1">
                    <div style="width:38px;height:38px;border-radius:50%;background:<?= $isUnread ? 'var(--primary-light)' : '#f1f5f9' ?>;display:flex;align-items:center;justify-content:center;">
                        <i class="<?= $iconCls ?>" style="font-size:15px;"></i>
                    </div>
                </div>
                <!-- Content -->
                <div class="flex-grow-1 min-width-0">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <div>
                            <h6 class="mb-1 fw-semibold<?= $isUnread ? ' text-primary' : '' ?>">
                                <?= htmlspecialchars($n['TITLE'], ENT_QUOTES, 'UTF-8') ?>
                                <?php if ($isUnread): ?><span class="ms-1" style="width:8px;height:8px;border-radius:50%;background:var(--primary);display:inline-block;vertical-align:middle;"></span><?php endif; ?>
                            </h6>
                            <p class="text-muted mb-1 small"><?= htmlspecialchars($n['MESSAGE'], ENT_QUOTES, 'UTF-8') ?></p>
                            <span class="text-muted" style="font-size:11px;"><i class="fas fa-clock me-1"></i><?= timeAgo($n['CREATED_AT']) ?></span>
                        </div>
                        <?php if ($isUnread): ?>
                        <form method="POST" class="flex-shrink-0">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="mark_one">
                            <input type="hidden" name="notif_id" value="<?= (int)$n['NOTIFICATION_ID'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Mark as read">
                                <i class="fas fa-check"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>

        <?php if ($total > $perPage): ?>
        <div class="p-3">
            <?= renderPagination($total, $currentPage, $perPage, $baseUrl) ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

</div>
</div>
<?php include ROOT . '/includes/footer.php'; ?>

<style>
.min-width-0 { min-width: 0; }
</style>
