<?php
include "db.php";
session_start();

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
}

$user_id = $_SESSION['user_id'];

if(isset($_GET['seat_id'])){

    $seat_id = $_GET['seat_id'];

    $sql = "INSERT INTO bookings (student_id, seat_id, booking_status)
            VALUES ($user_id, $seat_id, 'PENDING')";

    if($conn->query($sql)){
        echo "Booking Request Sent!";
    } else {
        echo "Error!";
    }
}
?>