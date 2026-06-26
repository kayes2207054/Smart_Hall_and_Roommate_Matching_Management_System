<?php
/**
 * NestSync — Authentication & Session Guard
 * Include this in every protected page.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ----------------------------------------------------------------
// Core guard functions
// ----------------------------------------------------------------

/**
 * Redirect to login if user is not authenticated.
 */
function requireLogin(): void
{
    if (empty($_SESSION['user_id'])) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: ' . BASE_URL . '/login.php');
        exit();
    }
}

/**
 * Restrict page to one or more roles.
 * $roles can be a string or an array of role strings.
 */
function requireRole(mixed $roles): void
{
    requireLogin();
    $roles = (array) $roles;
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        setFlash('danger', 'You do not have permission to access that page.');
        header('Location: ' . BASE_URL . '/login.php');
        exit();
    }
}

// ----------------------------------------------------------------
// Role helpers
// ----------------------------------------------------------------

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function isSystemAdmin(): bool
{
    return ($_SESSION['role'] ?? '') === 'SYSTEM_ADMIN';
}

function isHallAdmin(): bool
{
    return ($_SESSION['role'] ?? '') === 'HALL_ADMIN';
}

function isAdmin(): bool
{
    return in_array($_SESSION['role'] ?? '', ['SYSTEM_ADMIN', 'HALL_ADMIN'], true);
}

function isStudent(): bool
{
    return ($_SESSION['role'] ?? '') === 'STUDENT';
}

// ----------------------------------------------------------------
// Session accessors
// ----------------------------------------------------------------

function currentUserId(): int|null
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function currentUserName(): string
{
    return $_SESSION['name'] ?? 'Unknown';
}

function currentUserRole(): string
{
    return $_SESSION['role'] ?? '';
}

function currentUserEmail(): string
{
    return $_SESSION['email'] ?? '';
}
