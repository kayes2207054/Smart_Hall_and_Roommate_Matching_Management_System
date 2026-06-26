<?php
/**
 * NestSync — Database Connection
 * MySQLi with utf8mb4 charset
 */

// Load application config if not already loaded
if (!defined('ROOT')) {
    require_once dirname(__FILE__) . '/config.php';
}

// --- Credentials (adjust for your environment) ---
$dbHost = 'localhost';
$dbName = 'nestsync';
$dbUser = 'root';
$dbPass = '';
$dbPort = 3306;

// Create connection
$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort);

// Handle connection failure gracefully
if ($conn->connect_error) {
    $errMsg = htmlspecialchars($conn->connect_error, ENT_QUOTES, 'UTF-8');
    http_response_code(503);
    die('<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Database Error – NestSync</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">
<div class="card shadow-lg p-5 text-center" style="max-width:520px;border-radius:16px">
  <div class="text-danger mb-3" style="font-size:48px"><i class="bi bi-exclamation-triangle-fill"></i>⚠️</div>
  <h4 class="fw-bold text-danger mb-2">Database Connection Failed</h4>
  <p class="text-muted mb-3">Could not connect to the MySQL database.</p>
  <code class="d-block bg-light border rounded p-2 mb-3 text-start small">' . $errMsg . '</code>
  <hr>
  <ol class="text-start small text-muted">
    <li>Ensure <strong>MySQL</strong> is running (XAMPP Control Panel).</li>
    <li>The database <strong>nestsync</strong> must exist — import <code>sql/01_core_schema.sql</code> in phpMyAdmin.</li>
    <li>Then visit <a href="' . BASE_URL . '/seed.php">seed.php</a> to load sample data.</li>
  </ol>
</div>
</body></html>');
}

// Use utf8mb4 for full Unicode + emoji support
$conn->set_charset('utf8mb4');
