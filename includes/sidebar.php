<?php
/**
 * NestSync — Sidebar Navigation
 * Role-aware: shows different links for SYSTEM_ADMIN, HALL_ADMIN, STUDENT
 * Expected: $activePage (string), session variables set
 */
$role = $_SESSION['role'] ?? '';
$name = $_SESSION['name'] ?? 'User';

function sidebarLink(string $href, string $icon, string $label, string $page, string $activePage): string
{
    $active = ($page === $activePage) ? 'active' : '';
    return <<<HTML
    <li class="nav-item">
      <a class="nav-link {$active}" href="{$href}">
        <i class="{$icon}"></i>
        <span>{$label}</span>
      </a>
    </li>
HTML;
}

$ap = $activePage ?? '';
?>
<!-- ===== SIDEBAR ===== -->
<nav id="sidebar" class="sidebar">

    <!-- Logo -->
    <div class="sidebar-header">
        <a href="<?= BASE_URL ?>" class="sidebar-logo">
            <span class="logo-icon">🏠</span>
            <span class="logo-text">Nest<span class="logo-accent">Sync</span></span>
        </a>
        <button class="btn sidebar-close-btn d-lg-none" id="sidebarClose">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- User Mini Profile -->
    <div class="sidebar-user">
        <div class="sidebar-user-avatar">
            <?= strtoupper(substr($name, 0, 1)) ?>
        </div>
        <div class="sidebar-user-info">
            <div class="sidebar-user-name"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="sidebar-user-role"><?= $role === 'SYSTEM_ADMIN' ? 'System Admin' : ($role === 'HALL_ADMIN' ? 'Hall Admin' : 'Student') ?></div>
        </div>
    </div>

    <!-- Navigation -->
    <div class="sidebar-nav">
        <ul class="nav flex-column">

        <?php if ($role === 'SYSTEM_ADMIN' || $role === 'HALL_ADMIN'): ?>
            <!-- ADMIN NAVIGATION -->
            <li class="nav-section-title">Main</li>
            <?= sidebarLink(BASE_URL.'/pages/admin/dashboard.php',         'fas fa-th-large',       'Dashboard',       'dashboard',        $ap) ?>
            <?= sidebarLink(BASE_URL.'/pages/admin/manage_bookings.php',   'fas fa-calendar-check', 'Bookings',        'bookings',         $ap) ?>

            <li class="nav-section-title">Management</li>
            <?php if ($role === 'SYSTEM_ADMIN'): ?>
            <?= sidebarLink(BASE_URL.'/pages/admin/manage_users.php',      'fas fa-users',          'Users',           'users',            $ap) ?>
            <?= sidebarLink(BASE_URL.'/pages/admin/manage_halls.php',      'fas fa-building',       'Halls',           'halls',            $ap) ?>
            <?php endif; ?>
            <?= sidebarLink(BASE_URL.'/pages/admin/manage_rooms.php',      'fas fa-door-open',      'Rooms',           'rooms',            $ap) ?>
            <?= sidebarLink(BASE_URL.'/pages/admin/manage_seats.php',      'fas fa-chair',          'Seats',           'seats',            $ap) ?>

            <li class="nav-section-title">Analytics</li>
            <?= sidebarLink(BASE_URL.'/pages/admin/reports.php',           'fas fa-chart-bar',      'Reports',         'reports',          $ap) ?>
            <?= sidebarLink(BASE_URL.'/pages/admin/roommate_matches.php',  'fas fa-user-friends',   'Roommate Matches','roommate_matches',  $ap) ?>

        <?php elseif ($role === 'STUDENT'): ?>
            <!-- STUDENT NAVIGATION -->
            <li class="nav-section-title">My Portal</li>
            <?= sidebarLink(BASE_URL.'/pages/student/dashboard.php',       'fas fa-th-large',       'Dashboard',       'dashboard',        $ap) ?>
            <?= sidebarLink(BASE_URL.'/pages/student/my_bookings.php',     'fas fa-calendar-check', 'My Bookings',     'my_bookings',      $ap) ?>
            <?= sidebarLink(BASE_URL.'/pages/student/notifications.php',   'fas fa-bell',           'Notifications',   'notifications',    $ap) ?>

            <li class="nav-section-title">Explore</li>
            <?= sidebarLink(BASE_URL.'/pages/student/browse_halls.php',    'fas fa-building',       'Browse Halls',    'browse_halls',     $ap) ?>
            <?= sidebarLink(BASE_URL.'/pages/student/browse_seats.php',    'fas fa-chair',          'Browse Seats',    'browse_seats',     $ap) ?>
            <?= sidebarLink(BASE_URL.'/pages/student/roommate_matches.php','fas fa-user-friends',   'Roommate Matches','roommate_matches',  $ap) ?>

            <li class="nav-section-title">Account</li>
            <?= sidebarLink(BASE_URL.'/pages/student/profile.php',         'fas fa-user-circle',    'My Profile',      'profile',          $ap) ?>
        <?php endif; ?>

        </ul>
    </div>

    <!-- Logout at bottom -->
    <div class="sidebar-footer">
        <a href="<?= BASE_URL ?>/logout.php" class="sidebar-logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</nav>
<!-- ===== END SIDEBAR ===== -->

<!-- Sidebar overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
