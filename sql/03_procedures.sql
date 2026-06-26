-- ================================================================
-- NestSync: Smart Hall & Roommate Matching Management System
-- File: 03_procedures.sql
-- Description: MySQL stored procedures
-- Run AFTER 02_views.sql
-- ================================================================

USE nestsync;

-- Drop existing procedures if any
DROP PROCEDURE IF EXISTS sp_approve_booking;
DROP PROCEDURE IF EXISTS sp_reject_booking;
DROP PROCEDURE IF EXISTS sp_cancel_booking;
DROP PROCEDURE IF EXISTS sp_calculate_match;
DROP PROCEDURE IF EXISTS sp_run_all_matches;
DROP PROCEDURE IF EXISTS sp_send_notification;

DELIMITER //

-- ================================================================
-- PROCEDURE: sp_approve_booking
-- Approves a pending booking, updates seat status, notifies student
-- ================================================================
CREATE PROCEDURE sp_approve_booking(
    IN p_booking_id   INT,
    IN p_reviewed_by  INT,
    IN p_remarks      TEXT
)
BEGIN
    DECLARE v_seat_id    INT;
    DECLARE v_student_id INT;
    DECLARE v_status     VARCHAR(20);

    -- Fetch current state
    SELECT seat_id, student_id, booking_status
    INTO   v_seat_id, v_student_id, v_status
    FROM   bookings
    WHERE  booking_id = p_booking_id;

    IF v_status != 'PENDING' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Only PENDING bookings can be approved.';
    END IF;

    START TRANSACTION;

    -- Update booking record
    UPDATE bookings
    SET    booking_status = 'APPROVED',
           reviewed_by   = p_reviewed_by,
           reviewed_at   = NOW(),
           admin_remarks  = p_remarks
    WHERE  booking_id = p_booking_id;

    -- Update seat to BOOKED
    UPDATE seats
    SET    seat_status        = 'BOOKED',
           current_student_id = v_student_id
    WHERE  seat_id = v_seat_id;

    -- Reject any other PENDING bookings by the same student
    UPDATE bookings
    SET    booking_status = 'CANCELLED',
           admin_remarks  = 'Auto-cancelled: another booking was approved.'
    WHERE  student_id     = v_student_id
      AND  booking_id    != p_booking_id
      AND  booking_status = 'PENDING';

    -- Send notification to student
    INSERT INTO notifications (user_id, title, message, notif_type)
    VALUES (
        v_student_id,
        'Booking Approved ✓',
        CONCAT('Your booking request #', p_booking_id, ' has been approved. Welcome to the hall!'),
        'BOOKING'
    );

    COMMIT;
END //

-- ================================================================
-- PROCEDURE: sp_reject_booking
-- Rejects a pending booking and frees the reserved seat
-- ================================================================
CREATE PROCEDURE sp_reject_booking(
    IN p_booking_id   INT,
    IN p_reviewed_by  INT,
    IN p_remarks      TEXT
)
BEGIN
    DECLARE v_seat_id    INT;
    DECLARE v_student_id INT;
    DECLARE v_status     VARCHAR(20);

    SELECT seat_id, student_id, booking_status
    INTO   v_seat_id, v_student_id, v_status
    FROM   bookings
    WHERE  booking_id = p_booking_id;

    IF v_status NOT IN ('PENDING', 'APPROVED') THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Only PENDING or APPROVED bookings can be rejected.';
    END IF;

    START TRANSACTION;

    UPDATE bookings
    SET    booking_status = 'REJECTED',
           reviewed_by   = p_reviewed_by,
           reviewed_at   = NOW(),
           admin_remarks  = p_remarks
    WHERE  booking_id = p_booking_id;

    -- Free the seat if it was BOOKED or RESERVED by this student
    UPDATE seats
    SET    seat_status        = 'AVAILABLE',
           current_student_id = NULL
    WHERE  seat_id = v_seat_id
      AND  current_student_id = v_student_id;

    INSERT INTO notifications (user_id, title, message, notif_type)
    VALUES (
        v_student_id,
        'Booking Rejected',
        CONCAT('Your booking request #', p_booking_id, ' was rejected. Reason: ',
               COALESCE(p_remarks, 'No reason provided.')),
        'BOOKING'
    );

    COMMIT;
END //

-- ================================================================
-- PROCEDURE: sp_cancel_booking
-- Allows a student to cancel their own PENDING booking
-- ================================================================
CREATE PROCEDURE sp_cancel_booking(
    IN p_booking_id INT,
    IN p_student_id INT
)
BEGIN
    DECLARE v_seat_id INT;
    DECLARE v_status  VARCHAR(20);
    DECLARE v_owner   INT;

    SELECT seat_id, booking_status, student_id
    INTO   v_seat_id, v_status, v_owner
    FROM   bookings
    WHERE  booking_id = p_booking_id;

    IF v_owner != p_student_id THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'You can only cancel your own bookings.';
    END IF;

    IF v_status != 'PENDING' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Only PENDING bookings can be cancelled by students.';
    END IF;

    START TRANSACTION;

    UPDATE bookings
    SET    booking_status = 'CANCELLED'
    WHERE  booking_id = p_booking_id;

    -- Release the reserved seat
    UPDATE seats
    SET    seat_status        = 'AVAILABLE',
           current_student_id = NULL
    WHERE  seat_id = v_seat_id;

    COMMIT;
END //

-- ================================================================
-- PROCEDURE: sp_send_notification
-- Generic notification sender
-- ================================================================
CREATE PROCEDURE sp_send_notification(
    IN p_user_id    INT,
    IN p_title      VARCHAR(200),
    IN p_message    TEXT,
    IN p_type       VARCHAR(20)
)
BEGIN
    INSERT INTO notifications (user_id, title, message, notif_type)
    VALUES (p_user_id, p_title, p_message, p_type);
END //

-- ================================================================
-- PROCEDURE: sp_calculate_match
-- Computes compatibility score between two students and stores result
-- Scoring:
--   Department match  = 40 pts
--   Budget match      = 30 pts (within 25% of each other)
--   Preference words  = up to 30 pts (10 each for quiet/study/clean)
-- ================================================================
CREATE PROCEDURE sp_calculate_match(
    IN p_student1 INT,
    IN p_student2 INT
)
BEGIN
    DECLARE v_dept1   VARCHAR(80);
    DECLARE v_dept2   VARCHAR(80);
    DECLARE v_bud1    DECIMAL(10,2);
    DECLARE v_bud2    DECIMAL(10,2);
    DECLARE v_pref1   VARCHAR(500);
    DECLARE v_pref2   VARCHAR(500);
    DECLARE v_score   DECIMAL(5,2) DEFAULT 0;
    DECLARE v_dm      TINYINT      DEFAULT 0;
    DECLARE v_bm      TINYINT      DEFAULT 0;
    DECLARE v_po      INT          DEFAULT 0;
    DECLARE v_reason  TEXT         DEFAULT '';

    SELECT department, monthly_budget, preferences
    INTO   v_dept1, v_bud1, v_pref1
    FROM   users WHERE user_id = p_student1;

    SELECT department, monthly_budget, preferences
    INTO   v_dept2, v_bud2, v_pref2
    FROM   users WHERE user_id = p_student2;

    -- Department match (40 pts)
    IF v_dept1 IS NOT NULL AND v_dept1 = v_dept2 THEN
        SET v_score  = v_score + 40;
        SET v_dm     = 1;
        SET v_reason = CONCAT(v_reason, 'Same department (', v_dept1, '). ');
    END IF;

    -- Budget match (30 pts) — within 25%
    IF v_bud1 > 0 AND v_bud2 > 0 THEN
        IF ABS(v_bud1 - v_bud2) / GREATEST(v_bud1, v_bud2) <= 0.25 THEN
            SET v_score  = v_score + 30;
            SET v_bm     = 1;
            SET v_reason = CONCAT(v_reason, 'Compatible budget range. ');
        END IF;
    END IF;

    -- Preference overlap (10 pts each, max 30)
    IF v_pref1 IS NOT NULL AND v_pref2 IS NOT NULL THEN
        IF LOCATE('quiet', LOWER(v_pref1)) > 0 AND LOCATE('quiet', LOWER(v_pref2)) > 0 THEN
            SET v_score  = v_score + 10;
            SET v_po     = v_po + 1;
            SET v_reason = CONCAT(v_reason, 'Both prefer quiet environment. ');
        END IF;
        IF LOCATE('study', LOWER(v_pref1)) > 0 AND LOCATE('study', LOWER(v_pref2)) > 0 THEN
            SET v_score  = v_score + 10;
            SET v_po     = v_po + 1;
            SET v_reason = CONCAT(v_reason, 'Both are study-focused. ');
        END IF;
        IF LOCATE('clean', LOWER(v_pref1)) > 0 AND LOCATE('clean', LOWER(v_pref2)) > 0 THEN
            SET v_score  = v_score + 10;
            SET v_po     = v_po + 1;
            SET v_reason = CONCAT(v_reason, 'Both prefer a clean room. ');
        END IF;
        IF LOCATE('early', LOWER(v_pref1)) > 0 AND LOCATE('early', LOWER(v_pref2)) > 0 THEN
            SET v_score  = v_score + 5;
            SET v_reason = CONCAT(v_reason, 'Both are early risers. ');
        END IF;
    END IF;

    -- Insert or update both directions
    INSERT INTO roommate_matches
        (student_id, matched_student_id, match_score, dept_match, budget_match, pref_overlap, match_reason)
    VALUES (p_student1, p_student2, v_score, v_dm, v_bm, v_po, v_reason)
    ON DUPLICATE KEY UPDATE
        match_score   = v_score,
        dept_match    = v_dm,
        budget_match  = v_bm,
        pref_overlap  = v_po,
        match_reason  = v_reason,
        matched_at    = NOW();

    INSERT INTO roommate_matches
        (student_id, matched_student_id, match_score, dept_match, budget_match, pref_overlap, match_reason)
    VALUES (p_student2, p_student1, v_score, v_dm, v_bm, v_po, v_reason)
    ON DUPLICATE KEY UPDATE
        match_score   = v_score,
        dept_match    = v_dm,
        budget_match  = v_bm,
        pref_overlap  = v_po,
        match_reason  = v_reason,
        matched_at    = NOW();
END //

-- ================================================================
-- PROCEDURE: sp_run_all_matches
-- Calculates matches for all pairs of active students
-- ================================================================
CREATE PROCEDURE sp_run_all_matches()
BEGIN
    DECLARE done      INT DEFAULT FALSE;
    DECLARE v_s1      INT;
    DECLARE v_s2      INT;

    -- Cursor over all unique student pairs
    DECLARE cur CURSOR FOR
        SELECT a.user_id, b.user_id
        FROM users a
        JOIN users b ON a.user_id < b.user_id
        WHERE a.role = 'STUDENT' AND a.account_status = 'ACTIVE'
          AND b.role = 'STUDENT' AND b.account_status = 'ACTIVE';

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    OPEN cur;
    loop_label: LOOP
        FETCH cur INTO v_s1, v_s2;
        IF done THEN
            LEAVE loop_label;
        END IF;
        CALL sp_calculate_match(v_s1, v_s2);
    END LOOP;
    CLOSE cur;
END //

DELIMITER ;

-- ================================================================
-- DONE: Run sql/04_triggers.sql next
-- ================================================================
