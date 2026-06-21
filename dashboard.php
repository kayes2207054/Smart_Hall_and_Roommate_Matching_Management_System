<?php
session_start();

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
}
?>

<h1>Welcome <?php echo $_SESSION['name']; ?></h1>

<p>Role: <?php echo $_SESSION['role']; ?></p>

<a href="logout.php">Logout</a>