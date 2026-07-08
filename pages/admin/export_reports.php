<?php
/**
 * NestSync — Admin: Export Reports to CSV
 * OCI8 / Oracle
 */
require_once '../../config/config.php';
require_once ROOT . '/config/db.php';
require_once ROOT . '/includes/auth_check.php';
require_once ROOT . '/includes/functions.php';

requireRole(['SYSTEM_ADMIN', 'HALL_ADMIN']);
$uid = currentUserId();

$type = sanitize($_GET['type'] ?? 'occupancy');

$filename = "NestSync_Report_{$type}_" . date('Ymd_His') . ".csv";

header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

$output = fopen("php://output", "w");

if ($type === 'occupancy') {
    fputcsv($output, ['Hall Name', 'Gender Type', 'Total Seats', 'Booked Seats', 'Available Seats', 'Maintenance Seats', 'Occupancy (%)']);
    $hallsOcc = oci_fetch_all_assoc('SELECT * FROM vw_hall_occupancy ORDER BY hall_name');
    if (isHallAdmin()) {
        $hallsOcc = array_filter($hallsOcc, fn($h) => (int)$h['MANAGED_BY'] === $uid);
    }
    foreach ($hallsOcc as $h) {
        fputcsv($output, [
            $h['HALL_NAME'],
            $h['GENDER_TYPE'],
            $h['TOTAL_SEATS'],
            $h['BOOKED_SEATS'],
            $h['AVAILABLE_SEATS'],
            $h['MAINTENANCE_SEATS'],
            $h['OCCUPANCY_PCT'] . '%'
        ]);
    }
} elseif ($type === 'bookings') {
    fputcsv($output, ['Booking ID', 'Student Name', 'Hall Name', 'Status', 'Date Requested']);
    $recentBookings = oci_fetch_all_assoc('SELECT * FROM vw_booking_summary ORDER BY requested_at DESC');
    // If Hall Admin, filter bookings by their managed hall
    if (isHallAdmin()) {
        $managed = oci_fetch_all_assoc('SELECT hall_id FROM halls WHERE managed_by = :u', [':u' => $uid]);
        $managedIds = array_column($managed, 'HALL_ID');
        $recentBookings = array_filter($recentBookings, fn($b) => in_array($b['HALL_ID'], $managedIds));
    }
    foreach ($recentBookings as $b) {
        fputcsv($output, [
            $b['BOOKING_ID'],
            $b['STUDENT_NAME'],
            $b['HALL_NAME'],
            $b['BOOKING_STATUS'],
            formatDateShort($b['REQUESTED_AT'])
        ]);
    }
} elseif ($type === 'students') {
    fputcsv($output, ['Department', 'Total Students', 'Average Budget (BDT)']);
    
    $whereClause = "role_name='STUDENT'";
    $binds = [];
    if (isHallAdmin()) {
        $whereClause .= " AND user_id IN (SELECT current_student_id FROM seats s JOIN rooms r ON s.room_id = r.room_id JOIN halls h ON r.hall_id = h.hall_id WHERE h.managed_by = :u AND current_student_id IS NOT NULL)";
        $binds[':u'] = $uid;
    }
    
    $stuDeptStats = oci_fetch_all_assoc("SELECT department, count(*) as cnt, avg(monthly_budget) as avg_budget FROM users WHERE $whereClause GROUP BY department ORDER BY cnt DESC", $binds);
    foreach ($stuDeptStats as $s) {
        if (!$s['DEPARTMENT']) continue;
        fputcsv($output, [
            $s['DEPARTMENT'],
            $s['CNT'],
            number_format((float)$s['AVG_BUDGET'], 2)
        ]);
    }
    
    // Empty line to separate Gender stats
    fputcsv($output, []);
    fputcsv($output, ['Gender', 'Total Students']);
    $genderStats = oci_fetch_all_assoc("SELECT gender, count(*) as cnt FROM users WHERE $whereClause GROUP BY gender", $binds);
    foreach ($genderStats as $g) {
        fputcsv($output, [
            $g['GENDER'] ?? 'Unknown',
            $g['CNT']
        ]);
    }
}

fclose($output);
exit();
