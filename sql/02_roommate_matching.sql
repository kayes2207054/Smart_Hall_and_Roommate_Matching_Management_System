-- ==============================================================
-- NestSync - Step 1 Sample Data (Oracle SQL)
-- ==============================================================
-- Run this file after 01_core_schema.sql.
-- Password values below are placeholder hashes for learning purposes.

-- ------------------------------
-- USERS
-- ------------------------------
INSERT INTO users (full_name, email, password_hash, role_name, department, monthly_budget, preferences_text)
VALUES ('System Administrator', 'sysadmin@nestsync.com', 'HASHED_ADMIN_123', 'SYSTEM_ADMIN', 'IT', 0, 'Manages full platform');

INSERT INTO users (full_name, email, password_hash, role_name, department, monthly_budget, preferences_text)
VALUES ('Hall Admin One', 'halladmin1@nestsync.com', 'HASHED_HALLADMIN_123', 'HALL_ADMIN', 'Administration', 0, 'Handles North Hall operations');

INSERT INTO users (full_name, email, password_hash, role_name, department, monthly_budget, preferences_text)
VALUES ('Rahim Uddin', 'rahim@student.com', 'HASHED_STUDENT_123', 'STUDENT', 'CSE', 4500, 'Quiet room, non-smoker');

INSERT INTO users (full_name, email, password_hash, role_name, department, monthly_budget, preferences_text)
VALUES ('Nusrat Jahan', 'nusrat@student.com', 'HASHED_STUDENT_456', 'STUDENT', 'EEE', 4000, 'Likes clean room, early sleeper');

INSERT INTO users (full_name, email, password_hash, role_name, department, monthly_budget, preferences_text)
VALUES ('Sabbir Hasan', 'sabbir@student.com', 'HASHED_STUDENT_789', 'STUDENT', 'CSE', 5000, 'Study-focused, quiet environment');

-- ------------------------------
-- HALLS
-- ------------------------------
INSERT INTO halls (hall_name, hall_location, total_capacity, managed_by_admin)
VALUES ('North Hall', 'Campus Block A', 200,
        (SELECT user_id FROM users WHERE email = 'halladmin1@nestsync.com'));

INSERT INTO halls (hall_name, hall_location, total_capacity, managed_by_admin)
VALUES ('South Hall', 'Campus Block B', 150,
        (SELECT user_id FROM users WHERE email = 'halladmin1@nestsync.com'));

-- ------------------------------
-- ROOMS
-- ------------------------------
INSERT INTO rooms (hall_id, room_number, room_type, monthly_rent, room_status)
VALUES ((SELECT hall_id FROM halls WHERE hall_name = 'North Hall'), 'A-101', 'DOUBLE', 3500, 'AVAILABLE');

INSERT INTO rooms (hall_id, room_number, room_type, monthly_rent, room_status)
VALUES ((SELECT hall_id FROM halls WHERE hall_name = 'North Hall'), 'A-102', 'TRIPLE', 3000, 'PARTIAL');

INSERT INTO rooms (hall_id, room_number, room_type, monthly_rent, room_status)
VALUES ((SELECT hall_id FROM halls WHERE hall_name = 'South Hall'), 'B-201', 'DOUBLE', 3200, 'AVAILABLE');

-- ------------------------------
-- SEATS
-- ------------------------------
INSERT INTO seats (room_id, seat_label, seat_status, current_student_id)
VALUES ((SELECT room_id FROM rooms WHERE room_number = 'A-101'), 'S1', 'BOOKED',
        (SELECT user_id FROM users WHERE email = 'rahim@student.com'));

INSERT INTO seats (room_id, seat_label, seat_status)
VALUES ((SELECT room_id FROM rooms WHERE room_number = 'A-101'), 'S2', 'AVAILABLE');

INSERT INTO seats (room_id, seat_label, seat_status, current_student_id)
VALUES ((SELECT room_id FROM rooms WHERE room_number = 'A-102'), 'S1', 'BOOKED',
        (SELECT user_id FROM users WHERE email = 'nusrat@student.com'));

INSERT INTO seats (room_id, seat_label, seat_status)
VALUES ((SELECT room_id FROM rooms WHERE room_number = 'A-102'), 'S2', 'AVAILABLE');

INSERT INTO seats (room_id, seat_label, seat_status)
VALUES ((SELECT room_id FROM rooms WHERE room_number = 'A-102'), 'S3', 'AVAILABLE');

INSERT INTO seats (room_id, seat_label, seat_status)
VALUES ((SELECT room_id FROM rooms WHERE room_number = 'B-201'), 'S1', 'AVAILABLE');

INSERT INTO seats (room_id, seat_label, seat_status)
VALUES ((SELECT room_id FROM rooms WHERE room_number = 'B-201'), 'S2', 'AVAILABLE');

-- ------------------------------
-- BOOKINGS
-- ------------------------------
INSERT INTO bookings (student_id, hall_id, room_id, seat_id, booking_status, reviewed_by, reviewed_at, notes)
VALUES (
    (SELECT user_id FROM users WHERE email = 'rahim@student.com'),
    (SELECT hall_id FROM halls WHERE hall_name = 'North Hall'),
    (SELECT room_id FROM rooms WHERE room_number = 'A-101'),
    (SELECT seat_id FROM seats WHERE seat_label = 'S1' AND room_id = (SELECT room_id FROM rooms WHERE room_number = 'A-101')),
    'APPROVED',
    (SELECT user_id FROM users WHERE email = 'halladmin1@nestsync.com'),
    SYSTIMESTAMP,
    'Approved for current semester'
);

INSERT INTO bookings (student_id, hall_id, room_id, seat_id, booking_status, notes)
VALUES (
    (SELECT user_id FROM users WHERE email = 'sabbir@student.com'),
    (SELECT hall_id FROM halls WHERE hall_name = 'South Hall'),
    (SELECT room_id FROM rooms WHERE room_number = 'B-201'),
    (SELECT seat_id FROM seats WHERE seat_label = 'S1' AND room_id = (SELECT room_id FROM rooms WHERE room_number = 'B-201')),
    'PENDING',
    'Applied for new term'
);

-- ------------------------------
-- ROOMMATE_MATCHES
-- ------------------------------
INSERT INTO roommate_matches (student_id, matched_student_id, match_score, match_reason)
VALUES (
    (SELECT user_id FROM users WHERE email = 'rahim@student.com'),
    (SELECT user_id FROM users WHERE email = 'sabbir@student.com'),
    88.50,
    'Same department and similar room preferences'
);

INSERT INTO roommate_matches (student_id, matched_student_id, match_score, match_reason)
VALUES (
    (SELECT user_id FROM users WHERE email = 'nusrat@student.com'),
    (SELECT user_id FROM users WHERE email = 'rahim@student.com'),
    72.00,
    'Budget range and lifestyle overlap'
);

COMMIT;
