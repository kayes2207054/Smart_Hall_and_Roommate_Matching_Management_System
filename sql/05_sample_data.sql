-- ================================================================
-- NestSync: Smart Hall & Roommate Matching Management System
-- File        : sql/05_sample_data.sql
-- Database    : Oracle 19c+ (PL/SQL)
-- Description : Realistic seed data for development and grading.
--               All password_hash values are bcrypt hashes generated
--               from PHP: password_hash('Admin@123', PASSWORD_BCRYPT)
--               and   password_hash('Student@123', PASSWORD_BCRYPT)
--
-- IMPORTANT   : Run AFTER 04_triggers.sql.
--               The double-booking trigger is active during seeding,
--               so bookings are inserted ONE approved per student
--               and the constraint is temporarily disabled for seed.
-- ================================================================

-- Disable triggers that would interfere with controlled seed data
ALTER TRIGGER trg_prevent_double_booking   DISABLE;
ALTER TRIGGER trg_prevent_seat_dbl_res DISABLE;

-- ----------------------------------------------------------------
-- USERS
-- Passwords:
--   Admins  → Admin@123
--   Students → Student@123
--   (actual bcrypt hashes — verify with PHP password_verify())
-- ----------------------------------------------------------------
INSERT INTO users (full_name, email, password_hash, role_name, department, phone, student_id_no, monthly_budget, preferences, gender, account_status)
VALUES ('System Administrator', 'admin@nestsync.edu',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'SYSTEM_ADMIN', 'IT', '01700-000001', NULL, 0, NULL, 'MALE', 'ACTIVE');

INSERT INTO users (full_name, email, password_hash, role_name, department, phone, student_id_no, monthly_budget, preferences, gender, account_status)
VALUES ('Kamal Hossain', 'halladmin1@nestsync.edu',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'HALL_ADMIN', 'Administration', '01700-000002', NULL, 0, NULL, 'MALE', 'ACTIVE');

INSERT INTO users (full_name, email, password_hash, role_name, department, phone, student_id_no, monthly_budget, preferences, gender, account_status)
VALUES ('Rina Begum', 'halladmin2@nestsync.edu',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'HALL_ADMIN', 'Administration', '01700-000003', NULL, 0, NULL, 'FEMALE', 'ACTIVE');

INSERT INTO users (full_name, email, password_hash, role_name, department, phone, student_id_no, monthly_budget, preferences, gender, account_status)
VALUES ('Rahim Uddin', 'rahim@nestsync.edu',
        '$2y$10$TKh8H1.PfuBiDCTFKFW6AOtNTgmFyUcMRkm06WpDfS.Goo1MjdXYe',
        'STUDENT', 'CSE', '01711-111001', 'STU-2021-001', 4500,
        'Quiet, non-smoker, study-focused, clean', 'MALE', 'ACTIVE');

INSERT INTO users (full_name, email, password_hash, role_name, department, phone, student_id_no, monthly_budget, preferences, gender, account_status)
VALUES ('Nusrat Jahan', 'nusrat@nestsync.edu',
        '$2y$10$TKh8H1.PfuBiDCTFKFW6AOtNTgmFyUcMRkm06WpDfS.Goo1MjdXYe',
        'STUDENT', 'EEE', '01711-111002', 'STU-2021-002', 4000,
        'Clean room, early sleeper, quiet', 'FEMALE', 'ACTIVE');

INSERT INTO users (full_name, email, password_hash, role_name, department, phone, student_id_no, monthly_budget, preferences, gender, account_status)
VALUES ('Sabbir Hasan', 'sabbir@nestsync.edu',
        '$2y$10$TKh8H1.PfuBiDCTFKFW6AOtNTgmFyUcMRkm06WpDfS.Goo1MjdXYe',
        'STUDENT', 'CSE', '01711-111003', 'STU-2021-003', 5000,
        'Study-focused, quiet, clean environment', 'MALE', 'ACTIVE');

INSERT INTO users (full_name, email, password_hash, role_name, department, phone, student_id_no, monthly_budget, preferences, gender, account_status)
VALUES ('Fatema Khatun', 'fatema@nestsync.edu',
        '$2y$10$TKh8H1.PfuBiDCTFKFW6AOtNTgmFyUcMRkm06WpDfS.Goo1MjdXYe',
        'STUDENT', 'BBA', '01711-111004', 'STU-2021-004', 3500,
        'Clean, early riser, organized', 'FEMALE', 'ACTIVE');

INSERT INTO users (full_name, email, password_hash, role_name, department, phone, student_id_no, monthly_budget, preferences, gender, account_status)
VALUES ('Karim Ahmed', 'karim@nestsync.edu',
        '$2y$10$TKh8H1.PfuBiDCTFKFW6AOtNTgmFyUcMRkm06WpDfS.Goo1MjdXYe',
        'STUDENT', 'CSE', '01711-111005', 'STU-2021-005', 4800,
        'Quiet, study-focused, non-smoker', 'MALE', 'ACTIVE');

INSERT INTO users (full_name, email, password_hash, role_name, department, phone, student_id_no, monthly_budget, preferences, gender, account_status)
VALUES ('Rifat Islam', 'rifat@nestsync.edu',
        '$2y$10$TKh8H1.PfuBiDCTFKFW6AOtNTgmFyUcMRkm06WpDfS.Goo1MjdXYe',
        'STUDENT', 'EEE', '01711-111006', 'STU-2021-006', 3800,
        'Study environment, clean room', 'MALE', 'ACTIVE');

INSERT INTO users (full_name, email, password_hash, role_name, department, phone, student_id_no, monthly_budget, preferences, gender, account_status)
VALUES ('Sumaiya Begum', 'sumaiya@nestsync.edu',
        '$2y$10$TKh8H1.PfuBiDCTFKFW6AOtNTgmFyUcMRkm06WpDfS.Goo1MjdXYe',
        'STUDENT', 'BBA', '01711-111007', 'STU-2021-007', 4200,
        'Quiet, clean, early riser', 'FEMALE', 'ACTIVE');

INSERT INTO users (full_name, email, password_hash, role_name, department, phone, student_id_no, monthly_budget, preferences, gender, account_status)
VALUES ('Imran Khan', 'imran@nestsync.edu',
        '$2y$10$TKh8H1.PfuBiDCTFKFW6AOtNTgmFyUcMRkm06WpDfS.Goo1MjdXYe',
        'STUDENT', 'ME', '01711-111008', 'STU-2021-008', 5500,
        'Non-smoker, quiet environment', 'MALE', 'ACTIVE');

INSERT INTO users (full_name, email, password_hash, role_name, department, phone, student_id_no, monthly_budget, preferences, gender, account_status)
VALUES ('Ayesha Siddiqua', 'ayesha@nestsync.edu',
        '$2y$10$TKh8H1.PfuBiDCTFKFW6AOtNTgmFyUcMRkm06WpDfS.Goo1MjdXYe',
        'STUDENT', 'CSE', '01711-111009', 'STU-2021-009', 4600,
        'Study-focused, quiet, clean', 'FEMALE', 'ACTIVE');

INSERT INTO users (full_name, email, password_hash, role_name, department, phone, student_id_no, monthly_budget, preferences, gender, account_status)
VALUES ('Tanvir Hossain', 'tanvir@nestsync.edu',
        '$2y$10$TKh8H1.PfuBiDCTFKFW6AOtNTgmFyUcMRkm06WpDfS.Goo1MjdXYe',
        'STUDENT', 'EEE', '01711-111010', 'STU-2021-010', 4100,
        'Clean room, study-focused environment', 'MALE', 'ACTIVE');

COMMIT;


-- ----------------------------------------------------------------
-- HALLS
-- ----------------------------------------------------------------
INSERT INTO halls (hall_name, hall_location, total_capacity, gender_type, description, managed_by, hall_status)
SELECT 'North Hall', 'Campus Block A, Main Road', 200, 'MALE',
        'Six-storey male hall with modern facilities, cafeteria, and study rooms.',
        user_id, 'ACTIVE' FROM users WHERE email = 'halladmin1@nestsync.edu';

INSERT INTO halls (hall_name, hall_location, total_capacity, gender_type, description, managed_by, hall_status)
SELECT 'South Hall', 'Campus Block B, Park Road', 150, 'FEMALE',
        'Five-storey female hall with in-house laundry and reading lounge.',
        user_id, 'ACTIVE' FROM users WHERE email = 'halladmin2@nestsync.edu';

INSERT INTO halls (hall_name, hall_location, total_capacity, gender_type, description, managed_by, hall_status)
SELECT 'East Hall', 'Campus Block C, East Wing', 180, 'MIXED',
        'Mixed hall with segregated floors, common room, and gym access.',
        user_id, 'ACTIVE' FROM users WHERE email = 'halladmin1@nestsync.edu';

COMMIT;


-- ----------------------------------------------------------------
-- ROOMS
-- ----------------------------------------------------------------
-- North Hall (Male)
INSERT INTO rooms (hall_id, room_number, room_type, capacity, floor_number, monthly_rent, facilities, room_status)
SELECT hall_id, 'A-101', 'DOUBLE', 2, 1, 3500, 'Wi-Fi, Air Conditioning, Attached Bath', 'AVAILABLE' FROM halls WHERE hall_name='North Hall';
INSERT INTO rooms (hall_id, room_number, room_type, capacity, floor_number, monthly_rent, facilities, room_status)
SELECT hall_id, 'A-102', 'TRIPLE', 3, 1, 3000, 'Wi-Fi, Ceiling Fan, Common Bath', 'AVAILABLE' FROM halls WHERE hall_name='North Hall';
INSERT INTO rooms (hall_id, room_number, room_type, capacity, floor_number, monthly_rent, facilities, room_status)
SELECT hall_id, 'A-201', 'DOUBLE', 2, 2, 3800, 'Wi-Fi, Air Conditioning, Balcony', 'AVAILABLE' FROM halls WHERE hall_name='North Hall';
INSERT INTO rooms (hall_id, room_number, room_type, capacity, floor_number, monthly_rent, facilities, room_status)
SELECT hall_id, 'A-202', 'QUAD',   4, 2, 2500, 'Wi-Fi, Ceiling Fan, Common Bath', 'AVAILABLE' FROM halls WHERE hall_name='North Hall';

-- South Hall (Female)
INSERT INTO rooms (hall_id, room_number, room_type, capacity, floor_number, monthly_rent, facilities, room_status)
SELECT hall_id, 'B-101', 'DOUBLE', 2, 1, 3200, 'Wi-Fi, Air Conditioning, Attached Bath', 'AVAILABLE' FROM halls WHERE hall_name='South Hall';
INSERT INTO rooms (hall_id, room_number, room_type, capacity, floor_number, monthly_rent, facilities, room_status)
SELECT hall_id, 'B-102', 'TRIPLE', 3, 1, 2800, 'Wi-Fi, Ceiling Fan, Common Bath', 'AVAILABLE' FROM halls WHERE hall_name='South Hall';
INSERT INTO rooms (hall_id, room_number, room_type, capacity, floor_number, monthly_rent, facilities, room_status)
SELECT hall_id, 'B-201', 'SINGLE', 1, 2, 4500, 'Wi-Fi, Air Conditioning, Attached Bath, Balcony', 'AVAILABLE' FROM halls WHERE hall_name='South Hall';
INSERT INTO rooms (hall_id, room_number, room_type, capacity, floor_number, monthly_rent, facilities, room_status)
SELECT hall_id, 'B-202', 'DOUBLE', 2, 2, 3200, 'Wi-Fi, Ceiling Fan', 'AVAILABLE' FROM halls WHERE hall_name='South Hall';

-- East Hall (Mixed)
INSERT INTO rooms (hall_id, room_number, room_type, capacity, floor_number, monthly_rent, facilities, room_status)
SELECT hall_id, 'C-101', 'DOUBLE', 2, 1, 3300, 'Wi-Fi, Air Conditioning, Attached Bath', 'AVAILABLE' FROM halls WHERE hall_name='East Hall';
INSERT INTO rooms (hall_id, room_number, room_type, capacity, floor_number, monthly_rent, facilities, room_status)
SELECT hall_id, 'C-102', 'TRIPLE', 3, 1, 2900, 'Wi-Fi, Ceiling Fan, Common Bath', 'AVAILABLE' FROM halls WHERE hall_name='East Hall';
INSERT INTO rooms (hall_id, room_number, room_type, capacity, floor_number, monthly_rent, facilities, room_status)
SELECT hall_id, 'C-201', 'DOUBLE', 2, 2, 3600, 'Wi-Fi, Air Conditioning, Balcony', 'AVAILABLE' FROM halls WHERE hall_name='East Hall';
INSERT INTO rooms (hall_id, room_number, room_type, capacity, floor_number, monthly_rent, facilities, room_status)
SELECT hall_id, 'C-202', 'QUAD',   4, 2, 2600, 'Wi-Fi, Ceiling Fan, Common Bath', 'AVAILABLE' FROM halls WHERE hall_name='East Hall';

COMMIT;


-- ----------------------------------------------------------------
-- SEATS (created per room capacity)
-- ----------------------------------------------------------------
DECLARE
    PROCEDURE add_seats(p_room_no VARCHAR2, p_capacity NUMBER) IS
        v_room_id rooms.room_id%TYPE;
    BEGIN
        SELECT room_id INTO v_room_id FROM rooms WHERE room_number = p_room_no;
        FOR i IN 1..p_capacity LOOP
            INSERT INTO seats (room_id, seat_label, seat_status, current_student_id)
            VALUES (v_room_id, 'S' || i, 'AVAILABLE', NULL);
        END LOOP;
    END;
BEGIN
    add_seats('A-101', 2);
    add_seats('A-102', 3);
    add_seats('A-201', 2);
    add_seats('A-202', 4);
    add_seats('B-101', 2);
    add_seats('B-102', 3);
    add_seats('B-201', 1);
    add_seats('B-202', 2);
    add_seats('C-101', 2);
    add_seats('C-102', 3);
    add_seats('C-201', 2);
    add_seats('C-202', 4);
    COMMIT;
END;
/


-- ----------------------------------------------------------------
-- BOOKINGS + SEAT ASSIGNMENTS
-- Insert approved bookings manually and update seats directly
-- ----------------------------------------------------------------
DECLARE
    v_rahim_id   users.user_id%TYPE;
    v_sabbir_id  users.user_id%TYPE;
    v_nusrat_id  users.user_id%TYPE;
    v_karim_id   users.user_id%TYPE;
    v_rifat_id   users.user_id%TYPE;
    v_fatema_id  users.user_id%TYPE;
    v_imran_id   users.user_id%TYPE;
    v_ha1_id     users.user_id%TYPE;
    v_ha2_id     users.user_id%TYPE;
    v_hall_n     halls.hall_id%TYPE;
    v_hall_s     halls.hall_id%TYPE;
    v_hall_e     halls.hall_id%TYPE;
    v_room_a101  rooms.room_id%TYPE;
    v_room_a102  rooms.room_id%TYPE;
    v_room_a201  rooms.room_id%TYPE;
    v_room_b101  rooms.room_id%TYPE;
    v_room_b102  rooms.room_id%TYPE;
    v_room_c101  rooms.room_id%TYPE;
    v_room_c202  rooms.room_id%TYPE;
    v_seat_a101s1 seats.seat_id%TYPE;
    v_seat_a102s1 seats.seat_id%TYPE;
    v_seat_a201s1 seats.seat_id%TYPE;
    v_seat_b101s1 seats.seat_id%TYPE;
    v_seat_b102s1 seats.seat_id%TYPE;
    v_seat_c101s1 seats.seat_id%TYPE;
    v_seat_c202s1 seats.seat_id%TYPE;
BEGIN
    -- Fetch user IDs
    SELECT user_id INTO v_rahim_id  FROM users WHERE email = 'rahim@nestsync.edu';
    SELECT user_id INTO v_sabbir_id FROM users WHERE email = 'sabbir@nestsync.edu';
    SELECT user_id INTO v_nusrat_id FROM users WHERE email = 'nusrat@nestsync.edu';
    SELECT user_id INTO v_karim_id  FROM users WHERE email = 'karim@nestsync.edu';
    SELECT user_id INTO v_rifat_id  FROM users WHERE email = 'rifat@nestsync.edu';
    SELECT user_id INTO v_fatema_id FROM users WHERE email = 'fatema@nestsync.edu';
    SELECT user_id INTO v_imran_id  FROM users WHERE email = 'imran@nestsync.edu';
    SELECT user_id INTO v_ha1_id    FROM users WHERE email = 'halladmin1@nestsync.edu';
    SELECT user_id INTO v_ha2_id    FROM users WHERE email = 'halladmin2@nestsync.edu';

    -- Fetch hall IDs
    SELECT hall_id INTO v_hall_n FROM halls WHERE hall_name = 'North Hall';
    SELECT hall_id INTO v_hall_s FROM halls WHERE hall_name = 'South Hall';
    SELECT hall_id INTO v_hall_e FROM halls WHERE hall_name = 'East Hall';

    -- Fetch room IDs
    SELECT room_id INTO v_room_a101 FROM rooms WHERE room_number = 'A-101';
    SELECT room_id INTO v_room_a102 FROM rooms WHERE room_number = 'A-102';
    SELECT room_id INTO v_room_a201 FROM rooms WHERE room_number = 'A-201';
    SELECT room_id INTO v_room_b101 FROM rooms WHERE room_number = 'B-101';
    SELECT room_id INTO v_room_b102 FROM rooms WHERE room_number = 'B-102';
    SELECT room_id INTO v_room_c101 FROM rooms WHERE room_number = 'C-101';
    SELECT room_id INTO v_room_c202 FROM rooms WHERE room_number = 'C-202';

    -- Fetch seat IDs
    SELECT seat_id INTO v_seat_a101s1 FROM seats WHERE room_id=v_room_a101 AND seat_label='S1';
    SELECT seat_id INTO v_seat_a102s1 FROM seats WHERE room_id=v_room_a102 AND seat_label='S1';
    SELECT seat_id INTO v_seat_a201s1 FROM seats WHERE room_id=v_room_a201 AND seat_label='S1';
    SELECT seat_id INTO v_seat_b101s1 FROM seats WHERE room_id=v_room_b101 AND seat_label='S1';
    SELECT seat_id INTO v_seat_b102s1 FROM seats WHERE room_id=v_room_b102 AND seat_label='S1';
    SELECT seat_id INTO v_seat_c101s1 FROM seats WHERE room_id=v_room_c101 AND seat_label='S1';
    SELECT seat_id INTO v_seat_c202s1 FROM seats WHERE room_id=v_room_c202 AND seat_label='S1';

    -- ── APPROVED BOOKINGS ──────────────────────────────────────────
    -- Rahim → North Hall A-101 S1
    INSERT INTO bookings (student_id,hall_id,room_id,seat_id,booking_status,semester,reviewed_by,reviewed_at,admin_remarks)
    VALUES (v_rahim_id, v_hall_n, v_room_a101, v_seat_a101s1,
            'APPROVED','Spring 2026', v_ha1_id, SYSTIMESTAMP, 'Approved for current semester.');

    UPDATE seats SET seat_status='BOOKED', current_student_id=v_rahim_id WHERE seat_id=v_seat_a101s1;

    -- Sabbir → North Hall A-102 S1
    INSERT INTO bookings (student_id,hall_id,room_id,seat_id,booking_status,semester,reviewed_by,reviewed_at,admin_remarks)
    VALUES (v_sabbir_id, v_hall_n, v_room_a102, v_seat_a102s1,
            'APPROVED','Spring 2026', v_ha1_id, SYSTIMESTAMP, 'Welcome to North Hall.');

    UPDATE seats SET seat_status='BOOKED', current_student_id=v_sabbir_id WHERE seat_id=v_seat_a102s1;

    -- Nusrat → South Hall B-101 S1
    INSERT INTO bookings (student_id,hall_id,room_id,seat_id,booking_status,semester,reviewed_by,reviewed_at,admin_remarks)
    VALUES (v_nusrat_id, v_hall_s, v_room_b101, v_seat_b101s1,
            'APPROVED','Spring 2026', v_ha2_id, SYSTIMESTAMP, 'Approved for female hall.');

    UPDATE seats SET seat_status='BOOKED', current_student_id=v_nusrat_id WHERE seat_id=v_seat_b101s1;

    -- ── PENDING BOOKINGS ───────────────────────────────────────────
    -- Karim → North Hall A-201 S1
    INSERT INTO bookings (student_id,hall_id,room_id,seat_id,booking_status,semester,notes)
    VALUES (v_karim_id, v_hall_n, v_room_a201, v_seat_a201s1,
            'PENDING','Spring 2026','Prefer a study-focused floor.');

    UPDATE seats SET seat_status='RESERVED' WHERE seat_id=v_seat_a201s1;

    -- Rifat → East Hall C-101 S1
    INSERT INTO bookings (student_id,hall_id,room_id,seat_id,booking_status,semester,notes)
    VALUES (v_rifat_id, v_hall_e, v_room_c101, v_seat_c101s1,
            'PENDING','Spring 2026','First preference for East Hall.');

    UPDATE seats SET seat_status='RESERVED' WHERE seat_id=v_seat_c101s1;

    -- Fatema → South Hall B-102 S1
    INSERT INTO bookings (student_id,hall_id,room_id,seat_id,booking_status,semester,notes)
    VALUES (v_fatema_id, v_hall_s, v_room_b102, v_seat_b102s1,
            'PENDING','Spring 2026','Need a clean and quiet environment.');

    UPDATE seats SET seat_status='RESERVED' WHERE seat_id=v_seat_b102s1;

    -- ── REJECTED BOOKING ───────────────────────────────────────────
    -- Imran → East Hall C-202 S1 (rejected)
    INSERT INTO bookings (student_id,hall_id,room_id,seat_id,booking_status,semester,reviewed_by,reviewed_at,admin_remarks)
    VALUES (v_imran_id, v_hall_e, v_room_c202, v_seat_c202s1,
            'REJECTED','Spring 2026', v_ha1_id, SYSTIMESTAMP,
            'Capacity full. Please reapply next semester.');

    COMMIT;
END;
/


-- ----------------------------------------------------------------
-- ROOMMATE MATCHES (pre-computed pairs)
-- ----------------------------------------------------------------
DECLARE
    v_rahim_id   users.user_id%TYPE;
    v_sabbir_id  users.user_id%TYPE;
    v_karim_id   users.user_id%TYPE;
    v_nusrat_id  users.user_id%TYPE;
    v_sumaiya_id users.user_id%TYPE;
    v_rifat_id   users.user_id%TYPE;
    v_tanvir_id  users.user_id%TYPE;
    v_ayesha_id  users.user_id%TYPE;
    v_imran_id   users.user_id%TYPE;
    v_fatema_id  users.user_id%TYPE;
    v_admin_id   users.user_id%TYPE;
BEGIN
    SELECT user_id INTO v_rahim_id  FROM users WHERE email = 'rahim@nestsync.edu';
    SELECT user_id INTO v_sabbir_id FROM users WHERE email = 'sabbir@nestsync.edu';
    SELECT user_id INTO v_karim_id  FROM users WHERE email = 'karim@nestsync.edu';
    SELECT user_id INTO v_nusrat_id FROM users WHERE email = 'nusrat@nestsync.edu';
    SELECT user_id INTO v_sumaiya_id FROM users WHERE email = 'sumaiya@nestsync.edu';
    SELECT user_id INTO v_rifat_id  FROM users WHERE email = 'rifat@nestsync.edu';
    SELECT user_id INTO v_tanvir_id FROM users WHERE email = 'tanvir@nestsync.edu';
    SELECT user_id INTO v_ayesha_id FROM users WHERE email = 'ayesha@nestsync.edu';
    SELECT user_id INTO v_imran_id  FROM users WHERE email = 'imran@nestsync.edu';
    SELECT user_id INTO v_fatema_id FROM users WHERE email = 'fatema@nestsync.edu';
    SELECT user_id INTO v_admin_id  FROM users WHERE email = 'admin@nestsync.edu';

    -- Rahim <-> Sabbir
    sp_calculate_match(v_rahim_id, v_sabbir_id);
    -- Rahim <-> Karim
    sp_calculate_match(v_rahim_id, v_karim_id);
    -- Nusrat <-> Sumaiya
    sp_calculate_match(v_nusrat_id, v_sumaiya_id);
    -- Rifat <-> Tanvir
    sp_calculate_match(v_rifat_id, v_tanvir_id);
    -- Ayesha <-> Sabbir
    sp_calculate_match(v_ayesha_id, v_sabbir_id);

    -- NOTIFICATIONS
    sp_send_notification(v_rahim_id, 'Booking Approved', 'Your booking for North Hall A-101 S1 has been approved. Welcome!', 'BOOKING');
    sp_send_notification(v_sabbir_id, 'Booking Approved', 'Your booking for North Hall A-102 S1 has been approved. Welcome!', 'BOOKING');
    sp_send_notification(v_nusrat_id, 'Booking Approved', 'Your booking for South Hall B-101 S1 has been approved. Welcome!', 'BOOKING');
    sp_send_notification(v_imran_id, 'Booking Rejected', 'Your booking for East Hall C-202 was rejected. Please reapply next semester.', 'BOOKING');
    sp_send_notification(v_rahim_id, 'Roommate Match Found', 'You have a new high-compatibility roommate match! Check your matches.', 'MATCH');
    sp_send_notification(v_sabbir_id, 'Roommate Match Found', 'A compatible roommate has been found for you!', 'MATCH');
    sp_send_notification(v_karim_id, 'Booking Under Review', 'Your booking request for North Hall A-201 is being reviewed.', 'BOOKING');
    sp_send_notification(v_fatema_id, 'Application Received', 'Your hall seat application has been received and will be reviewed shortly.', 'BOOKING');
    sp_send_notification(v_admin_id, 'System Ready', 'NestSync has been successfully configured with sample data.', 'SYSTEM');

    COMMIT;
END;
/


-- Re-enable triggers
ALTER TRIGGER trg_prevent_double_booking      ENABLE;
ALTER TRIGGER trg_prevent_seat_dbl_res ENABLE;

COMMIT;

-- ================================================================
-- VERIFICATION QUERIES (run to confirm data loaded correctly)
-- ================================================================
-- SELECT * FROM vw_dashboard_stats;
-- SELECT * FROM vw_hall_occupancy;
-- SELECT * FROM vw_pending_bookings;
-- SELECT * FROM vw_roommate_matches_detail ORDER BY match_score DESC;
-- SELECT * FROM notifications ORDER BY created_at DESC;
-- ================================================================
