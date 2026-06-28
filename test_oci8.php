<?php
/**
 * Minimal OCI8 Connection Test Script
 * Place this in C:\xampp\htdocs\NestSync\test_oci8.php
 */

echo "<h2>Oracle OCI8 Connection Test</h2>";

// 1. Check if extension is loaded
if (!extension_loaded('oci8')) {
    die("<p style='color:red; font-weight:bold;'>ERROR: OCI8 extension is NOT loaded! Please check your php.ini and PATH variables.</p>");
} else {
    echo "<p style='color:green; font-weight:bold;'>SUCCESS: OCI8 extension is loaded.</p>";
}

// 2. Database Credentials
$username = 'nestsync';       // Replace with your Oracle DB username
$password = 'nestsync';       // Replace with your Oracle DB password
$connection_string = 'localhost/XE'; // Usually localhost/XE for Oracle 11g Express Edition

// 3. Attempt Connection
echo "<p>Attempting to connect to <strong>$connection_string</strong> as <strong>$username</strong>...</p>";

$conn = @oci_connect($username, $password, $connection_string);

if (!$conn) {
    $e = oci_error();
    echo "<div style='background-color:#ffebee; padding:10px; border:1px solid #f44336;'>";
    echo "<strong>Connection Failed!</strong><br>";
    echo "Error Message: " . htmlentities($e['message'], ENT_QUOTES) . "<br>";
    echo "</div>";
} else {
    echo "<div style='background-color:#e8f5e9; padding:10px; border:1px solid #4caf50;'>";
    echo "<strong>Connection Successful! 🎉</strong><br>";
    echo "Successfully connected to Oracle Database.<br>";
    echo "Server Version: " . oci_server_version($conn);
    echo "</div>";
    
    oci_close($conn);
}
?>
