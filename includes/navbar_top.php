<?php
/**
 * NestSync — Top Navbar
 * Expected: $pageTitle (string), $activePage (string), $_notifCount (int from header.php)
 */
$_notifCount = $_notifCount ?? 0;
$notifLink   = isStudent()
    ? BASE_URL . '/pages/student/notifications.php'
    : BASE_URL . '/pages/admin/dashboard.php';
$profileLink = isStudent()
    ? BASE_URL . '/pages/student/profile.php'
    : BASE_URL . '/pages/admin/dashboard.php';
?>
<nav class="navbar-top">
    <!-- Left: hamburger + breadcrumb -->
    <div class="d-flex align-items-center gap-3">
        <button class="sidebar-toggle-btn" id="sidebarToggle" title="Toggle sidebar">
            <i class="fas fa-bars"></i>
        </button>
        <div class="page-breadcrumb">
            <span class="page-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>

    <!-- Right: notifications + user menu -->
    <div class="navbar-right">

        <!-- Notification Bell -->
        <a href="<?= $notifLink ?>" class="navbar-icon-btn position-relative" title="Notifications">
            <i class="fas fa-bell"></i>
            <?php if ($_notifCount > 0): ?>
            <span class="notif-badge"><?= min(99, $_notifCount) ?></span>
            <?php endif; ?>
        </a>

        <!-- User Dropdown -->
        <div class="dropdown">
            <button class="user-menu-btn dropdown-toggle" type="button"
                    data-bs-toggle="dropdown" aria-expanded="false">
                <div class="user-avatar-sm">
                    <?= strtoupper(substr($_SESSION['name'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="user-menu-info d-none d-md-block">
                    <span class="user-menu-name"><?= htmlspecialchars($_SESSION['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="user-menu-role"><?= htmlspecialchars($_SESSION['role'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li>
                    <div class="dropdown-header">
                        <strong><?= htmlspecialchars($_SESSION['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>
                        <br><small class="text-muted"><?= htmlspecialchars($_SESSION['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></small>
                    </div>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= $profileLink ?>"><i class="fas fa-user-circle me-2 text-primary"></i>My Profile</a></li>
                <li><a class="dropdown-item" href="<?= $notifLink ?>"><i class="fas fa-bell me-2 text-warning"></i>Notifications <?php if($_notifCount > 0): ?><span class="badge bg-danger ms-1"><?= $_notifCount ?></span><?php endif; ?></a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
            </ul>
        </div>
    </div>
</nav>
