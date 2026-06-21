<?php
$conn = new mysqli("localhost", "root", "", "nestsync");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>