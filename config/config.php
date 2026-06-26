<?php
/**
 * NestSync — Application Configuration
 * Central constants and settings
 */

// --- Application ---
define('APP_NAME',    'NestSync');
define('APP_TAGLINE', 'Smart Hall & Roommate Matching System');
define('APP_VERSION', '1.0.0');
define('APP_YEAR',    '2026');

// --- Paths ---
// ROOT = project root (one level up from this config/ directory)
define('ROOT', realpath(dirname(__FILE__) . '/..'));

// --- URLs ---
// Adjust BASE_URL if your XAMPP virtual host differs
define('BASE_URL', 'http://localhost/NestSync');

// --- Session ---
define('SESSION_LIFETIME', 7200); // 2 hours in seconds

// --- Pagination ---
define('PER_PAGE', 10);

// --- Upload directory (for future profile picture support) ---
define('UPLOAD_DIR',    ROOT . '/assets/uploads/');
define('UPLOAD_URL',    BASE_URL . '/assets/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB

// --- Matching Engine ---
define('MATCH_DEPT_SCORE',   40);   // Max points for department match
define('MATCH_BUDGET_SCORE', 30);   // Max points for budget match
define('MATCH_PREF_SCORE',   10);   // Points per preference keyword match
define('MATCH_BUDGET_RANGE', 0.25); // Budget tolerance (25 %)
