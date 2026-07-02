<?php
/**
 * NestSync — Oracle Database Connection (OCI8)
 * ================================================
 * Uses PHP's OCI8 extension to connect to Oracle XE / Oracle 19c.
 *
 * Prerequisites:
 *   1. PHP OCI8 extension must be installed and enabled in php.ini:
 *         extension=oci8  (or extension=oci8_19)
 *   2. Oracle Instant Client (or full Oracle client) must be installed.
 *   3. Adjust ORA_HOST, ORA_PORT, ORA_SID and credentials below to
 *      match your local Oracle installation.
 *
 * XAMPP users on Windows:
 *   - Install Oracle XE 21c from oracle.com/database/technologies/xe-downloads
 *   - Download Oracle Instant Client matching your PHP bitness (x64 or x86)
 *   - Enable extension=oci8_19 in C:\xampp\php\php.ini
 *   - Restart Apache
 */

// Load application config if not already loaded
if (!defined('ROOT')) {
    require_once dirname(__FILE__) . '/config.php';
}

// ─── Oracle Connection Parameters ────────────────────────────────
// Easy-connect string: "//host:port/service_name"
// For Oracle XE: service_name is typically 'XEPDB1' (pluggable DB) or 'XE'
$oraHost     = 'localhost';
$oraPort     = 1521;
$oraService  = 'XE';       // Oracle 11g uses 'XE'
$oraUser     = 'nestsync';     // Schema/user that owns all NestSync tables
$oraPass     = '123456';     // Set the same password when creating the schema
$oraCharset  = 'AL32UTF8';

$oraConnStr = "//{$oraHost}:{$oraPort}/{$oraService}";

// ─── Establish Connection ─────────────────────────────────────────
if (!function_exists('oci_connect')) {
    // Provide a clear error if OCI8 extension is missing
    http_response_code(503);
    die('<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>OCI8 Missing – NestSync</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">
<div class="card shadow-lg p-5 text-center" style="max-width:540px;border-radius:16px">
  <div style="font-size:48px">⚠️</div>
  <h4 class="fw-bold text-danger mt-2 mb-2">PHP OCI8 Extension Not Loaded</h4>
  <p class="text-muted">NestSync requires the <code>oci8</code> PHP extension to connect to Oracle.</p>
  <hr>
  <ol class="text-start small text-muted">
    <li>Install <strong>Oracle Instant Client</strong> for your OS.</li>
    <li>Enable <code>extension=oci8_19</code> in <code>php.ini</code>.</li>
    <li>Restart Apache / PHP-FPM.</li>
    <li>Verify: <a href="' . BASE_URL . '/phpinfo.php">phpinfo()</a> should show <em>oci8</em>.</li>
  </ol>
  <a href="https://www.php.net/manual/en/oci8.installation.php" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
    PHP OCI8 Installation Docs
  </a>
</div>
</body></html>');
}

$conn = @oci_connect($oraUser, $oraPass, $oraConnStr, $oraCharset);

if (!$conn) {
    $e = oci_error();
    $errMsg = htmlspecialchars($e['message'] ?? 'Unknown OCI error', ENT_QUOTES, 'UTF-8');
    http_response_code(503);
    die('<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Database Error – NestSync</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center min-vh-100">
<div class="card shadow-lg p-5 text-center" style="max-width:560px;border-radius:16px">
  <div style="font-size:48px">⚠️</div>
  <h4 class="fw-bold text-danger mt-2 mb-2">Oracle Connection Failed</h4>
  <p class="text-muted">Could not connect to the Oracle database.</p>
  <code class="d-block bg-light border rounded p-2 mb-3 text-start small">' . $errMsg . '</code>
  <hr>
  <ol class="text-start small text-muted">
    <li>Ensure <strong>Oracle XE / Oracle DB</strong> is running.</li>
    <li>Verify host <strong>' . htmlspecialchars($oraHost) . '</strong>, port <strong>' . $oraPort . '</strong>, service <strong>' . htmlspecialchars($oraService) . '</strong>.</li>
    <li>User <strong>' . htmlspecialchars($oraUser) . '</strong> must exist with <code>CREATE SESSION</code> privilege.</li>
    <li>Run <code>sql/01_core_schema.sql</code> then <code>sql/05_sample_data.sql</code> in SQL*Plus or SQL Developer.</li>
  </ol>
</div>
</body></html>');
}

// Set date format to match PHP's standard date expectations
$stmtFmt = oci_parse($conn, "ALTER SESSION SET NLS_DATE_FORMAT='YYYY-MM-DD HH24:MI:SS'");
oci_execute($stmtFmt);
oci_free_statement($stmtFmt);

$stmtTsFmt = oci_parse($conn, "ALTER SESSION SET NLS_TIMESTAMP_FORMAT='YYYY-MM-DD HH24:MI:SS'");
oci_execute($stmtTsFmt);
oci_free_statement($stmtTsFmt);

/**
 * ================================================================
 * OCI8 Helper Functions
 * ================================================================
 * These wrappers make OCI8 safer and easier to use across the app,
 * similar to how MySQLi prepared statements work.
 * ================================================================
 */

/**
 * Execute an OCI8 SELECT query and return all rows as an assoc array.
 *
 * @param string $sql    SQL with named Oracle bind variables (:var_name)
 * @param array  $binds  Associative array of [':name' => value]
 * @return array         Array of associative row arrays (column names uppercased by default in OCI8)
 */
function oci_fetch_all_assoc(string $sql, array $binds = []): array
{
    global $conn;
    $stmt = oci_parse($conn, $sql);
    if (!$stmt) {
        $e = oci_error($conn);
        trigger_error('oci_parse failed: ' . ($e['message'] ?? ''), E_USER_WARNING);
        return [];
    }
    foreach ($binds as $name => &$val) {
        oci_bind_by_name($stmt, $name, $val);
    }
    unset($val);
    oci_execute($stmt, OCI_DEFAULT);
    oci_fetch_all($stmt, $rows, 0, -1, OCI_FETCHSTATEMENT_BY_ROW + OCI_ASSOC);
    oci_free_statement($stmt);
    return $rows ?: [];
}

/**
 * Execute an OCI8 query and return the first row as an assoc array,
 * or null if no rows found.
 *
 * @param string $sql
 * @param array  $binds
 * @return array|null
 */
function oci_fetch_one_assoc(string $sql, array $binds = []): ?array
{
    global $conn;
    $stmt = oci_parse($conn, $sql);
    if (!$stmt) return null;
    foreach ($binds as $name => &$val) {
        oci_bind_by_name($stmt, $name, $val);
    }
    unset($val);
    oci_execute($stmt, OCI_DEFAULT);
    $row = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);
    return $row ?: null;
}

/**
 * Execute a DML statement (INSERT / UPDATE / DELETE) with bind variables.
 * Commits automatically unless $autoCommit is false.
 *
 * @param string $sql
 * @param array  $binds       Associative array of [':name' => value]
 * @param bool   $autoCommit  Commit immediately (default true)
 * @return bool
 */
function oci_execute_dml(string $sql, array $binds = [], bool $autoCommit = true): bool
{
    global $conn;
    $stmt = oci_parse($conn, $sql);
    if (!$stmt) return false;
    foreach ($binds as $name => &$val) {
        oci_bind_by_name($stmt, $name, $val);
    }
    unset($val);
    $mode   = $autoCommit ? OCI_COMMIT_ON_SUCCESS : OCI_DEFAULT;
    $result = oci_execute($stmt, $mode);
    oci_free_statement($stmt);
    return (bool)$result;
}

/**
 * Returns the number of rows returned by a SELECT query.
 *
 * @param string $sql
 * @param array  $binds
 * @return int
 */
function oci_count_rows(string $sql, array $binds = []): int
{
    $rows = oci_fetch_all_assoc($sql, $binds);
    return count($rows);
}

/**
 * Returns a scalar value from the first column of the first row.
 * Useful for COUNT(*), MAX(), etc.
 *
 * @param string $sql
 * @param array  $binds
 * @return mixed|null
 */
function oci_fetch_scalar(string $sql, array $binds = [])
{
    $row = oci_fetch_one_assoc($sql, $binds);
    if (!$row) return null;
    return reset($row); // first column value
}

/**
 * Get the last inserted ID by querying a sequence's CURRVAL.
 * Must be called in the same session immediately after INSERT.
 *
 * @param string $sequenceName  e.g. 'seq_users'
 * @return int|null
 */
function oci_last_insert_id(string $sequenceName): ?int
{
    $val = oci_fetch_scalar("SELECT {$sequenceName}.CURRVAL FROM DUAL");
    return $val !== null ? (int)$val : null;
}

/**
 * Build a safe LIKE pattern (escapes Oracle LIKE wildcards).
 *
 * @param string $input
 * @return string
 */
function oci_like(string $input): string
{
    return '%' . str_replace(['%','_'],['\\%','\\_'], $input) . '%';
}

/**
 * Paginate a query by wrapping it in Oracle's ROW_NUMBER() OVER() window function.
 * Returns data rows for the requested page only.
 *
 * @param string $innerSql   The base SELECT query (no ORDER BY at outer level)
 * @param array  $binds      Bind variables for the inner SQL
 * @param int    $page       Current page (1-indexed)
 * @param int    $perPage    Rows per page
 * @param string $orderBy    ORDER BY clause for the inner query (e.g. "created_at DESC")
 * @return array             ['rows' => [...], 'total' => N]
 */
function oci_paginate(string $innerSql, array $binds, int $page, int $perPage, string $orderBy = 'ROWNUM ASC'): array
{
    $offset = ($page - 1) * $perPage;
    $end    = $offset + $perPage;

    // Count total rows
    $countSql = "SELECT COUNT(*) FROM ({$innerSql})";
    $total    = (int)oci_fetch_scalar($countSql, $binds);

    // Paginated query via ROW_NUMBER
    $paginatedSql = "
        SELECT * FROM (
            SELECT inner_q.*, ROW_NUMBER() OVER (ORDER BY {$orderBy}) AS rn
            FROM ({$innerSql}) inner_q
        )
        WHERE rn > :oci_offset AND rn <= :oci_end
    ";

    $binds[':oci_offset'] = $offset;
    $binds[':oci_end']    = $end;

    $rows = oci_fetch_all_assoc($paginatedSql, $binds);

    return ['rows' => $rows, 'total' => $total];
}
