<?php
/**
 * NestSync – Database Seeder
 * Run this page once after importing sql/01_core_schema.sql
 * URL: http://localhost/NestSync/seed.php
 *
 * Creates all sample users with properly bcrypt-hashed passwords,
 * halls, rooms, seats, bookings, roommate matches, and notifications.
 */

require_once 'config/config.php';
require_once 'config/db.php';

// Security: only allow from localhost
$allowedIPs = ['127.0.0.1', '::1', 'localhost'];
if (!in_array($_SERVER['REMOTE_ADDR'], $allowedIPs, true)) {
    http_response_code(403);
    die('Access denied. Seed script can only run from localhost.');
}

$messages = [];
$errors   = [];
$seeded   = false;

// ================================================================
// Seed Data Definitions
// ================================================================

$users = [
    ['System Administrator',    'admin@nestsync.edu',       'Admin@123',    'SYSTEM_ADMIN', 'IT',             '01700-000001', null,       0,    null,                               'MALE'],
    ['Kamal Hossain',           'halladmin1@nestsync.edu',  'Admin@123',    'HALL_ADMIN',   'Administration', '01700-000002', null,       0,    null,                               'MALE'],
    ['Rina Begum',              'halladmin2@nestsync.edu',  'Admin@123',    'HALL_ADMIN',   'Administration', '01700-000003', null,       0,    null,                               'FEMALE'],
    ['Rahim Uddin',             'rahim@nestsync.edu',       'Student@123',  'STUDENT',      'CSE',            '01711-111001', 'STU-2021-001', 4500, 'Quiet, non-smoker, study-focused, clean',  'MALE'],
    ['Nusrat Jahan',            'nusrat@nestsync.edu',      'Student@123',  'STUDENT',      'EEE',            '01711-111002', 'STU-2021-002', 4000, 'Clean room, early sleeper, quiet',         'FEMALE'],
    ['Sabbir Hasan',            'sabbir@nestsync.edu',      'Student@123',  'STUDENT',      'CSE',            '01711-111003', 'STU-2021-003', 5000, 'Study-focused, quiet, clean environment',  'MALE'],
    ['Fatema Khatun',           'fatema@nestsync.edu',      'Student@123',  'STUDENT',      'BBA',            '01711-111004', 'STU-2021-004', 3500, 'Clean, early riser, organized',            'FEMALE'],
    ['Karim Ahmed',             'karim@nestsync.edu',       'Student@123',  'STUDENT',      'CSE',            '01711-111005', 'STU-2021-005', 4800, 'Quiet, study-focused, non-smoker',         'MALE'],
    ['Rifat Islam',             'rifat@nestsync.edu',       'Student@123',  'STUDENT',      'EEE',            '01711-111006', 'STU-2021-006', 3800, 'Study environment, clean room',            'MALE'],
    ['Sumaiya Begum',           'sumaiya@nestsync.edu',     'Student@123',  'STUDENT',      'BBA',            '01711-111007', 'STU-2021-007', 4200, 'Quiet, clean, early riser',                'FEMALE'],
    ['Imran Khan',              'imran@nestsync.edu',       'Student@123',  'STUDENT',      'ME',             '01711-111008', 'STU-2021-008', 5500, 'Non-smoker, quiet environment',            'MALE'],
    ['Ayesha Siddiqua',         'ayesha@nestsync.edu',      'Student@123',  'STUDENT',      'CSE',            '01711-111009', 'STU-2021-009', 4600, 'Study-focused, quiet, clean',              'FEMALE'],
    ['Tanvir Hossain',          'tanvir@nestsync.edu',      'Student@123',  'STUDENT',      'EEE',            '01711-111010', 'STU-2021-010', 4100, 'Clean room, study-focused environment',    'MALE'],
];

// ================================================================
// Run Seeder
// ================================================================

if (isset($_POST['action']) && $_POST['action'] === 'seed') {

    $conn->begin_transaction();

    try {
        // Step 0: Disable FK checks and clear tables
        $conn->query('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['notifications','roommate_matches','bookings','seats','rooms','halls','users'] as $tbl) {
            $conn->query("TRUNCATE TABLE `{$tbl}`");
        }
        $conn->query('SET FOREIGN_KEY_CHECKS = 1');
        $messages[] = '✓ All tables cleared.';

        // Step 1: Insert users
        $userIds = [];
        $stmtU = $conn->prepare(
            'INSERT INTO users (full_name,email,password_hash,role,department,phone,student_id_no,monthly_budget,preferences,gender)
             VALUES (?,?,?,?,?,?,?,?,?,?)'
        );

        foreach ($users as $u) {
            $hash = password_hash($u[2], PASSWORD_BCRYPT);
            $stmtU->bind_param('sssssssdss',
                $u[0], $u[1], $hash, $u[3], $u[4], $u[5], $u[6], $u[7], $u[8], $u[9]
            );
            $stmtU->execute();
            $userIds[$u[1]] = $conn->insert_id;
            $messages[] = "✓ User created: {$u[0]} ({$u[3]})";
        }
        $stmtU->close();

        // Step 2: Insert halls
        $halls = [
            ['North Hall', 'Campus Block A, Main Road', 200, 'MALE',   'Six-storey male hall with modern facilities, cafeteria, and study rooms.', $userIds['halladmin1@nestsync.edu']],
            ['South Hall', 'Campus Block B, Park Road', 150, 'FEMALE', 'Five-storey female hall with in-house laundry and reading lounge.',        $userIds['halladmin2@nestsync.edu']],
            ['East Hall',  'Campus Block C, East Wing', 180, 'MIXED',  'Mixed hall with segregated floors, common room, and gym access.',           $userIds['halladmin1@nestsync.edu']],
        ];

        $hallIds = [];
        $stmtH = $conn->prepare(
            'INSERT INTO halls (hall_name,hall_location,total_capacity,gender_type,description,managed_by)
             VALUES (?,?,?,?,?,?)'
        );

        foreach ($halls as $h) {
            $stmtH->bind_param('sisssi', $h[0], $h[1], $h[2], $h[3], $h[4], $h[5]);
            $stmtH->execute();
            $hallIds[$h[0]] = $conn->insert_id;
            $messages[] = "✓ Hall created: {$h[0]}";
        }
        $stmtH->close();

        // Step 3: Insert rooms
        $rooms = [
            // North Hall (Male)
            [$hallIds['North Hall'], 'A-101', 'DOUBLE', 2, 1, 3500.00, 'Wi-Fi, Air Conditioning, Attached Bath'],
            [$hallIds['North Hall'], 'A-102', 'TRIPLE', 3, 1, 3000.00, 'Wi-Fi, Ceiling Fan, Common Bath'],
            [$hallIds['North Hall'], 'A-201', 'DOUBLE', 2, 2, 3800.00, 'Wi-Fi, Air Conditioning, Balcony'],
            [$hallIds['North Hall'], 'A-202', 'QUAD',   4, 2, 2500.00, 'Wi-Fi, Ceiling Fan, Common Bath'],
            // South Hall (Female)
            [$hallIds['South Hall'], 'B-101', 'DOUBLE', 2, 1, 3200.00, 'Wi-Fi, Air Conditioning, Attached Bath'],
            [$hallIds['South Hall'], 'B-102', 'TRIPLE', 3, 1, 2800.00, 'Wi-Fi, Ceiling Fan, Common Bath'],
            [$hallIds['South Hall'], 'B-201', 'SINGLE', 1, 2, 4500.00, 'Wi-Fi, Air Conditioning, Attached Bath, Balcony'],
            [$hallIds['South Hall'], 'B-202', 'DOUBLE', 2, 2, 3200.00, 'Wi-Fi, Ceiling Fan'],
            // East Hall (Mixed)
            [$hallIds['East Hall'],  'C-101', 'DOUBLE', 2, 1, 3300.00, 'Wi-Fi, Air Conditioning, Attached Bath'],
            [$hallIds['East Hall'],  'C-102', 'TRIPLE', 3, 1, 2900.00, 'Wi-Fi, Ceiling Fan, Common Bath'],
            [$hallIds['East Hall'],  'C-201', 'DOUBLE', 2, 2, 3600.00, 'Wi-Fi, Air Conditioning, Balcony'],
            [$hallIds['East Hall'],  'C-202', 'QUAD',   4, 2, 2600.00, 'Wi-Fi, Ceiling Fan, Common Bath'],
        ];

        $roomIds = [];
        $stmtR = $conn->prepare(
            'INSERT INTO rooms (hall_id,room_number,room_type,capacity,floor_number,monthly_rent,facilities)
             VALUES (?,?,?,?,?,?,?)'
        );

        foreach ($rooms as $r) {
            $stmtR->bind_param('issiiids', $r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $r[6]);
            $stmtR->execute();
            $roomIds[$r[1]] = $conn->insert_id;
            $messages[] = "✓ Room created: {$r[1]} in hall_id={$r[0]}";
        }
        $stmtR->close();

        // Step 4: Insert seats (generate based on room capacity)
        $stmtS = $conn->prepare(
            'INSERT INTO seats (room_id, seat_label, seat_status, current_student_id)
             VALUES (?,?,?,?)'
        );

        $seatIds = []; // keyed by "roomNumber-label"
        foreach ($rooms as $r) {
            $roomNum  = $r[1];
            $capacity = $r[3];
            $roomId   = $roomIds[$roomNum];
            for ($i = 1; $i <= $capacity; $i++) {
                $label  = 'S' . $i;
                $status = 'AVAILABLE';
                $stuId  = null;
                $stmtS->bind_param('issi', $roomId, $label, $status, $stuId);
                $stmtS->execute();
                $seatIds[$roomNum . '-' . $label] = $conn->insert_id;
                $messages[] = "✓ Seat {$label} created in Room {$roomNum}";
            }
        }
        $stmtS->close();

        // Step 5: Insert bookings
        $bookings = [
            // Rahim — APPROVED in North Hall A-101 S1
            [
                $userIds['rahim@nestsync.edu'],
                $hallIds['North Hall'], $roomIds['A-101'], $seatIds['A-101-S1'],
                'APPROVED', 'Spring 2026',
                $userIds['halladmin1@nestsync.edu'],
                'Approved for current semester'
            ],
            // Sabbir — APPROVED in North Hall A-102 S1
            [
                $userIds['sabbir@nestsync.edu'],
                $hallIds['North Hall'], $roomIds['A-102'], $seatIds['A-102-S1'],
                'APPROVED', 'Spring 2026',
                $userIds['halladmin1@nestsync.edu'],
                'Approved. Welcome to North Hall.'
            ],
            // Nusrat — APPROVED in South Hall B-101 S1
            [
                $userIds['nusrat@nestsync.edu'],
                $hallIds['South Hall'], $roomIds['B-101'], $seatIds['B-101-S1'],
                'APPROVED', 'Spring 2026',
                $userIds['halladmin2@nestsync.edu'],
                'Approved for female hall.'
            ],
            // Karim — PENDING in North Hall A-201 S1
            [
                $userIds['karim@nestsync.edu'],
                $hallIds['North Hall'], $roomIds['A-201'], $seatIds['A-201-S1'],
                'PENDING', 'Spring 2026', null, null
            ],
            // Rifat — PENDING in East Hall C-101 S1
            [
                $userIds['rifat@nestsync.edu'],
                $hallIds['East Hall'], $roomIds['C-101'], $seatIds['C-101-S1'],
                'PENDING', 'Spring 2026', null, null
            ],
            // Fatema — PENDING in South Hall B-102 S1
            [
                $userIds['fatema@nestsync.edu'],
                $hallIds['South Hall'], $roomIds['B-102'], $seatIds['B-102-S1'],
                'PENDING', 'Spring 2026', null, null
            ],
            // Imran — REJECTED
            [
                $userIds['imran@nestsync.edu'],
                $hallIds['East Hall'], $roomIds['C-202'], $seatIds['C-202-S1'],
                'REJECTED', 'Spring 2026',
                $userIds['halladmin1@nestsync.edu'],
                'Capacity full. Please reapply next semester.'
            ],
        ];

        $stmtB = $conn->prepare(
            'INSERT INTO bookings (student_id,hall_id,room_id,seat_id,booking_status,semester,reviewed_by,admin_remarks,reviewed_at)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );

        foreach ($bookings as $b) {
            $reviewedAt = $b[6] ? date('Y-m-d H:i:s', strtotime('-' . rand(1,5) . ' days')) : null;
            $stmtB->bind_param('iiiiissis',
                $b[0], $b[1], $b[2], $b[3], $b[4], $b[5], $b[6], $b[7], $reviewedAt
            );
            $stmtB->execute();
            $messages[] = "✓ Booking created: student_id={$b[0]} status={$b[4]}";
        }
        $stmtB->close();

        // Step 6: Manually update approved seats
        $approvedMap = [
            'A-101-S1' => $userIds['rahim@nestsync.edu'],
            'A-102-S1' => $userIds['sabbir@nestsync.edu'],
            'B-101-S1' => $userIds['nusrat@nestsync.edu'],
        ];

        foreach ($approvedMap as $key => $stuId) {
            $sid = $seatIds[$key];
            $conn->query("UPDATE seats SET seat_status='BOOKED', current_student_id={$stuId} WHERE seat_id={$sid}");
        }

        $pendingMap = ['A-201-S1', 'C-101-S1', 'B-102-S1'];
        foreach ($pendingMap as $key) {
            $sid = $seatIds[$key];
            $conn->query("UPDATE seats SET seat_status='RESERVED' WHERE seat_id={$sid}");
        }

        $messages[] = '✓ Seat statuses updated.';

        // Step 7: Roommate matches (pre-computed pairs)
        $matchPairs = [
            // Rahim (CSE,4500,quiet+study+clean) ↔ Sabbir (CSE,5000,study+quiet+clean) — high match
            [$userIds['rahim@nestsync.edu'],   $userIds['sabbir@nestsync.edu'],  88.00, 1, 1, 3, 'Same department (CSE); Compatible budget range; Both prefer quiet environment; Both are study-focused; Both prefer a clean room.'],
            [$userIds['sabbir@nestsync.edu'],  $userIds['rahim@nestsync.edu'],   88.00, 1, 1, 3, 'Same department (CSE); Compatible budget range; Both prefer quiet environment; Both are study-focused; Both prefer a clean room.'],
            // Rahim ↔ Karim (CSE,4800,quiet+study)
            [$userIds['rahim@nestsync.edu'],   $userIds['karim@nestsync.edu'],   80.00, 1, 1, 2, 'Same department (CSE); Compatible budget range; Both prefer quiet environment; Both are study-focused.'],
            [$userIds['karim@nestsync.edu'],   $userIds['rahim@nestsync.edu'],   80.00, 1, 1, 2, 'Same department (CSE); Compatible budget range; Both prefer quiet environment; Both are study-focused.'],
            // Nusrat (EEE,4000,clean+quiet) ↔ Sumaiya (BBA,4200,quiet+clean+early)
            [$userIds['nusrat@nestsync.edu'],  $userIds['sumaiya@nestsync.edu'], 50.00, 0, 1, 2, 'Compatible budget range; Both prefer quiet environment; Both prefer a clean room.'],
            [$userIds['sumaiya@nestsync.edu'], $userIds['nusrat@nestsync.edu'],  50.00, 0, 1, 2, 'Compatible budget range; Both prefer quiet environment; Both prefer a clean room.'],
            // Rifat (EEE,3800) ↔ Tanvir (EEE,4100)
            [$userIds['rifat@nestsync.edu'],   $userIds['tanvir@nestsync.edu'],  60.00, 1, 1, 1, 'Same department (EEE); Compatible budget range; Both are study-focused.'],
            [$userIds['tanvir@nestsync.edu'],  $userIds['rifat@nestsync.edu'],   60.00, 1, 1, 1, 'Same department (EEE); Compatible budget range; Both are study-focused.'],
            // Ayesha (CSE,4600,study+quiet+clean) ↔ Sabbir
            [$userIds['ayesha@nestsync.edu'],  $userIds['sabbir@nestsync.edu'],  78.00, 1, 1, 2, 'Same department (CSE); Compatible budget range; Both prefer quiet environment; Both are study-focused.'],
            [$userIds['sabbir@nestsync.edu'],  $userIds['ayesha@nestsync.edu'],  78.00, 1, 1, 2, 'Same department (CSE); Compatible budget range; Both prefer quiet environment; Both are study-focused.'],
        ];

        $stmtM = $conn->prepare(
            'INSERT INTO roommate_matches (student_id,matched_student_id,match_score,dept_match,budget_match,pref_overlap,match_reason)
             VALUES (?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE match_score=VALUES(match_score), match_reason=VALUES(match_reason)'
        );

        foreach ($matchPairs as $m) {
            $stmtM->bind_param('iidiis', $m[0], $m[1], $m[2], $m[3], $m[4], $m[5], $m[6]);

            // Wait - need to fix bind_param types
            $sid1   = $m[0]; $sid2 = $m[1]; $score = $m[2];
            $dm     = $m[3]; $bm   = $m[4]; $po    = $m[5]; $reason = $m[6];
            $stmtM->bind_param('iidiis', $sid1, $sid2, $score, $dm, $bm, $po, $reason);
            $stmtM->execute();
        }
        $stmtM->close();

        // Redo with correct binding
        foreach ($matchPairs as $m) {
            $sid1 = (int)$m[0]; $sid2 = (int)$m[1];
            $score = (float)$m[2]; $dm = (int)$m[3]; $bm = (int)$m[4]; $po = (int)$m[5];
            $reason = $m[6];
            $stmt2 = $conn->prepare(
                'INSERT INTO roommate_matches (student_id,matched_student_id,match_score,dept_match,budget_match,pref_overlap,match_reason)
                 VALUES (?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE match_score=VALUES(match_score), match_reason=VALUES(match_reason)'
            );
            $stmt2->bind_param('iidiis', $sid1, $sid2, $score, $dm, $bm, $po, $reason);
            $stmt2->execute();
            $stmt2->close();
        }

        $messages[] = '✓ Roommate matches generated.';

        // Step 8: Notifications
        $notifs = [
            [$userIds['rahim@nestsync.edu'],   'Booking Approved ✓',   'Your booking for North Hall Room A-101 Seat S1 has been approved. Welcome!', 'BOOKING'],
            [$userIds['sabbir@nestsync.edu'],   'Booking Approved ✓',   'Your booking for North Hall Room A-102 Seat S1 has been approved. Welcome!', 'BOOKING'],
            [$userIds['nusrat@nestsync.edu'],   'Booking Approved ✓',   'Your booking for South Hall Room B-101 Seat S1 has been approved. Welcome!', 'BOOKING'],
            [$userIds['imran@nestsync.edu'],    'Booking Rejected',      'Your booking for East Hall Room C-202 has been rejected. Please reapply next semester.', 'BOOKING'],
            [$userIds['rahim@nestsync.edu'],    'Roommate Match Found',  'You have a new high-compatibility roommate match! Check your matches.', 'MATCH'],
            [$userIds['sabbir@nestsync.edu'],   'Roommate Match Found',  'Great news! A compatible roommate match has been found for you.', 'MATCH'],
            [$userIds['karim@nestsync.edu'],    'Booking Under Review',  'Your booking request for North Hall A-201 is under review by the hall admin.', 'BOOKING'],
            [$userIds['rifat@nestsync.edu'],    'Booking Under Review',  'Your booking request for East Hall C-101 is pending approval.', 'BOOKING'],
            [$userIds['admin@nestsync.edu'],    'System Ready',          'NestSync has been successfully configured with sample data.', 'SYSTEM'],
            [$userIds['fatema@nestsync.edu'],   'Application Received',  'Your hall seat application has been received and will be reviewed shortly.', 'BOOKING'],
        ];

        $stmtN = $conn->prepare(
            'INSERT INTO notifications (user_id,title,message,notif_type) VALUES (?,?,?,?)'
        );

        foreach ($notifs as $n) {
            $stmtN->bind_param('isss', $n[0], $n[1], $n[2], $n[3]);
            $stmtN->execute();
        }
        $stmtN->close();
        $messages[] = '✓ Notifications created.';

        $conn->commit();
        $seeded = true;
        $messages[] = '✅ Database seeded successfully! You can now log in.';

    } catch (Exception $e) {
        $conn->rollback();
        $errors[] = '❌ Error: ' . $e->getMessage();
    }
}

// Check if DB has data already
$existingCount = 0;
try {
    $res = $conn->query('SELECT COUNT(*) FROM users');
    if ($res) $existingCount = (int)$res->fetch_row()[0];
} catch (Exception $e) {
    $errors[] = 'Database not set up yet. Please import sql/01_core_schema.sql first.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup – NestSync</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f0f4ff; }
        .setup-card { max-width: 700px; margin: 40px auto; border-radius: 16px; border: none; box-shadow: 0 4px 30px rgba(0,0,0,0.1); }
        .log-box { background: #1e293b; color: #a7f3d0; font-family: monospace; font-size: 13px; border-radius: 10px; padding: 16px; max-height: 350px; overflow-y: auto; }
        .log-box .err { color: #fca5a5; }
        pre { margin: 0; white-space: pre-wrap; }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="card setup-card">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <div style="font-size:48px">🏠</div>
                <h2 class="fw-800 mt-2">NestSync Setup</h2>
                <p class="text-muted">Database Seeder – Loads all sample data</p>
            </div>

            <?php if (!empty($errors) && empty($messages)): ?>
            <div class="alert alert-danger">
                <strong>Error:</strong> <?= htmlspecialchars($errors[0], ENT_QUOTES) ?>
                <hr>
                <p class="mb-0 small">
                    Please open <strong>phpMyAdmin</strong>, select or create the <code>nestsync</code> database,
                    and import <code>sql/01_core_schema.sql</code> first.
                </p>
            </div>
            <?php endif; ?>

            <?php if ($seeded): ?>
            <div class="alert alert-success">
                <strong>✅ Success!</strong> Database seeded with all sample data.
                <hr>
                <a href="login.php" class="btn btn-success">
                    <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                </a>
            </div>
            <?php endif; ?>

            <?php if ($messages): ?>
            <div class="log-box mb-4">
                <pre><?php foreach ($messages as $m) echo htmlspecialchars($m, ENT_QUOTES) . "\n"; ?></pre>
                <?php foreach ($errors as $e): ?>
                <pre class="err"><?= htmlspecialchars($e, ENT_QUOTES) ?></pre>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!$seeded): ?>
            <div class="mb-4 p-3 rounded-3" style="background:#f8fafc;border:1px solid #e2e8f0">
                <h6 class="fw-700 mb-3"><i class="fas fa-info-circle text-primary me-2"></i>What this seeder creates:</h6>
                <ul class="small text-muted mb-0">
                    <li>1 System Admin + 2 Hall Admins</li>
                    <li>10 Student accounts</li>
                    <li>3 Halls (North · South · East)</li>
                    <li>12 Rooms across halls</li>
                    <li>~28 Seats across rooms</li>
                    <li>7 Bookings (mix of Approved/Pending/Rejected)</li>
                    <li>10 Roommate match records</li>
                    <li>10 Notifications</li>
                </ul>
            </div>

            <?php if ($existingCount > 0): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Database already has <strong><?= $existingCount ?> user(s)</strong>.
                Running the seeder will <strong>erase all existing data</strong>.
            </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="seed">
                <button type="submit" class="btn btn-primary btn-lg w-100"
                        onclick="return confirm('This will ERASE all existing data and re-seed. Continue?')">
                    <i class="fas fa-database me-2"></i>
                    <?= $existingCount > 0 ? 'Re-Seed Database (Reset All Data)' : 'Seed Database Now' ?>
                </button>
            </form>
            <?php endif; ?>

            <hr class="my-4">
            <h6 class="fw-700 mb-3">Default Login Credentials</h6>
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead class="table-light"><tr><th>Role</th><th>Email</th><th>Password</th></tr></thead>
                    <tbody>
                        <tr><td><span class="badge bg-danger">System Admin</span></td><td><code>admin@nestsync.edu</code></td><td><code>Admin@123</code></td></tr>
                        <tr><td><span class="badge bg-warning text-dark">Hall Admin</span></td><td><code>halladmin1@nestsync.edu</code></td><td><code>Admin@123</code></td></tr>
                        <tr><td><span class="badge bg-info text-dark">Hall Admin 2</span></td><td><code>halladmin2@nestsync.edu</code></td><td><code>Admin@123</code></td></tr>
                        <tr><td><span class="badge bg-primary">Student</span></td><td><code>rahim@nestsync.edu</code></td><td><code>Student@123</code></td></tr>
                        <tr><td><span class="badge bg-primary">Student</span></td><td><code>nusrat@nestsync.edu</code></td><td><code>Student@123</code></td></tr>
                    </tbody>
                </table>
            </div>

            <div class="text-center mt-3">
                <a href="login.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-sign-in-alt me-1"></i>Login
                </a>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-home me-1"></i>Home
                </a>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
