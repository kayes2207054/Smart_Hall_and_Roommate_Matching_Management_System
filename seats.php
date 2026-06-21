<?php
include "db.php";
session_start();

// login check
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Available Seats - NestSync</title>
</head>
<body>

<h2>Available Seats</h2>
<p>Welcome, <?php echo $_SESSION['name']; ?> (<?php echo $_SESSION['role']; ?>)</p>

<table border="1" cellpadding="10">
    <tr>
        <th>Seat ID</th>
        <th>Room ID</th>
        <th>Seat Label</th>
        <th>Status</th>
        <th>Action</th>
    </tr>

<?php
$sql = "SELECT * FROM seats";
$result = $conn->query($sql);

if($result->num_rows > 0){
    while($row = $result->fetch_assoc()){

        echo "<tr>
            <td>{$row['seat_id']}</td>
            <td>{$row['room_id']}</td>
            <td>{$row['seat_label']}</td>
            <td>{$row['seat_status']}</td>
            <td>";

        // only show book button if available
        if($row['seat_status'] == "AVAILABLE"){
            echo "<a href='book.php?seat_id={$row['seat_id']}'>Book</a>";
        } else {
            echo "Not Available";
        }

        echo "</td>
        </tr>";
    }
} else {
    echo "<tr><td colspan='5'>No seats found</td></tr>";
}
?>

</table>

<br>
<a href="dashboard.php">Back to Dashboard</a>

</body>
</html>