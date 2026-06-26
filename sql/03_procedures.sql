-- ================================================================
-- NestSync: Smart Hall & Roommate Matching Management System
-- File        : sql/03_procedures.sql
-- Database    : Oracle 19c+ (PL/SQL)
-- Description : Stored procedures for booking workflow and the
--               roommate matching engine.
-- Run AFTER   : sql/02_views.sql
-- ================================================================


-- ================================================================
-- PROCEDURE: sp_approve_booking
-- Approves a PENDING booking:
--   1. Validates status is PENDING
--   2. Updates booking to APPROVED
--   3. Marks seat as BOOKED, assigns student
--   4. Auto-cancels other PENDING bookings by same student
--   5. Sends an in-app notification to the student
-- ================================================================
CREATE OR REPLACE PROCEDURE sp_approve_booking(
    p_booking_id  IN NUMBER,
    p_reviewed_by IN NUMBER,
    p_remarks     IN VARCHAR2
)
IS
    v_seat_id    NUMBER;
    v_student_id NUMBER;
    v_status     VARCHAR2(15);
BEGIN
    -- Fetch booking details
    SELECT seat_id, student_id, booking_status
    INTO   v_seat_id, v_student_id, v_status
    FROM   bookings
    WHERE  booking_id = p_booking_id;

    -- Guard: only PENDING bookings can be approved
    IF v_status != 'PENDING' THEN
        RAISE_APPLICATION_ERROR(-20001, 'Only PENDING bookings can be approved.');
    END IF;

    -- Approve the booking
    UPDATE bookings
    SET    booking_status = 'APPROVED',
           reviewed_by   = p_reviewed_by,
           reviewed_at   = SYSTIMESTAMP,
           admin_remarks  = p_remarks
    WHERE  booking_id    = p_booking_id;

    -- Assign seat to student
    UPDATE seats
    SET    seat_status        = 'BOOKED',
           current_student_id = v_student_id
    WHERE  seat_id = v_seat_id;

    -- Auto-cancel any other PENDING bookings by same student
    UPDATE bookings
    SET    booking_status = 'CANCELLED',
           admin_remarks  = 'Auto-cancelled: another booking was approved.'
    WHERE  student_id     = v_student_id
      AND  booking_id    != p_booking_id
      AND  booking_status = 'PENDING';

    -- Release seats reserved by those cancelled bookings
    UPDATE seats
    SET    seat_status        = 'AVAILABLE',
           current_student_id = NULL
    WHERE  seat_id IN (
               SELECT seat_id FROM bookings
               WHERE  student_id     = v_student_id
                 AND  booking_id    != p_booking_id
                 AND  booking_status = 'CANCELLED'
           )
      AND  seat_status = 'RESERVED';

    -- Notify student
    INSERT INTO notifications (user_id, title, message, notif_type)
    VALUES (
        v_student_id,
        'Booking Approved',
        'Your booking request #' || p_booking_id || ' has been approved. Welcome to the hall!',
        'BOOKING'
    );

    COMMIT;

EXCEPTION
    WHEN NO_DATA_FOUND THEN
        RAISE_APPLICATION_ERROR(-20002, 'Booking #' || p_booking_id || ' not found.');
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END sp_approve_booking;
/


-- ================================================================
-- PROCEDURE: sp_reject_booking
-- Rejects a PENDING or APPROVED booking:
--   1. Updates booking to REJECTED
--   2. Frees the seat back to AVAILABLE
--   3. Notifies student
-- ================================================================
CREATE OR REPLACE PROCEDURE sp_reject_booking(
    p_booking_id  IN NUMBER,
    p_reviewed_by IN NUMBER,
    p_remarks     IN VARCHAR2
)
IS
    v_seat_id    NUMBER;
    v_student_id NUMBER;
    v_status     VARCHAR2(15);
BEGIN
    SELECT seat_id, student_id, booking_status
    INTO   v_seat_id, v_student_id, v_status
    FROM   bookings
    WHERE  booking_id = p_booking_id;

    IF v_status NOT IN ('PENDING','APPROVED') THEN
        RAISE_APPLICATION_ERROR(-20003, 'Only PENDING or APPROVED bookings can be rejected.');
    END IF;

    UPDATE bookings
    SET    booking_status = 'REJECTED',
           reviewed_by   = p_reviewed_by,
           reviewed_at   = SYSTIMESTAMP,
           admin_remarks  = p_remarks
    WHERE  booking_id = p_booking_id;

    -- Free the seat if it was BOOKED or RESERVED by this student
    UPDATE seats
    SET    seat_status        = 'AVAILABLE',
           current_student_id = NULL
    WHERE  seat_id            = v_seat_id
      AND  (current_student_id = v_student_id OR seat_status IN ('RESERVED','BOOKED'));

    INSERT INTO notifications (user_id, title, message, notif_type)
    VALUES (
        v_student_id,
        'Booking Rejected',
        'Your booking request #' || p_booking_id || ' was rejected. Reason: ' ||
        NVL(p_remarks, 'No reason provided.'),
        'BOOKING'
    );

    COMMIT;

EXCEPTION
    WHEN NO_DATA_FOUND THEN
        RAISE_APPLICATION_ERROR(-20004, 'Booking #' || p_booking_id || ' not found.');
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END sp_reject_booking;
/


-- ================================================================
-- PROCEDURE: sp_cancel_booking
-- Allows a student to cancel their own PENDING booking
-- ================================================================
CREATE OR REPLACE PROCEDURE sp_cancel_booking(
    p_booking_id IN NUMBER,
    p_student_id IN NUMBER
)
IS
    v_seat_id NUMBER;
    v_status  VARCHAR2(15);
    v_owner   NUMBER;
BEGIN
    SELECT seat_id, booking_status, student_id
    INTO   v_seat_id, v_status, v_owner
    FROM   bookings
    WHERE  booking_id = p_booking_id;

    IF v_owner != p_student_id THEN
        RAISE_APPLICATION_ERROR(-20005, 'You can only cancel your own bookings.');
    END IF;

    IF v_status != 'PENDING' THEN
        RAISE_APPLICATION_ERROR(-20006, 'Only PENDING bookings can be cancelled by students.');
    END IF;

    UPDATE bookings
    SET    booking_status = 'CANCELLED'
    WHERE  booking_id = p_booking_id;

    UPDATE seats
    SET    seat_status        = 'AVAILABLE',
           current_student_id = NULL
    WHERE  seat_id = v_seat_id;

    COMMIT;

EXCEPTION
    WHEN NO_DATA_FOUND THEN
        RAISE_APPLICATION_ERROR(-20007, 'Booking #' || p_booking_id || ' not found.');
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END sp_cancel_booking;
/


-- ================================================================
-- PROCEDURE: sp_send_notification
-- Generic in-app notification sender
-- ================================================================
CREATE OR REPLACE PROCEDURE sp_send_notification(
    p_user_id IN NUMBER,
    p_title   IN VARCHAR2,
    p_message IN VARCHAR2,
    p_type    IN VARCHAR2
)
IS
BEGIN
    INSERT INTO notifications (user_id, title, message, notif_type)
    VALUES (p_user_id, p_title, p_message, p_type);
    COMMIT;
END sp_send_notification;
/


-- ================================================================
-- PROCEDURE: sp_calculate_match
-- Computes compatibility score between two students.
-- Scoring model:
--   Department match      = 40 pts (exact match)
--   Budget compatibility  = 30 pts (within 25% of each other)
--   Preference keywords   = up to 30 pts (10 each: quiet, study, clean)
--   Early riser keyword   =  5 pts bonus
-- Stores result in both directions (student1→2 and student2→1)
-- using MERGE to handle existing records.
-- ================================================================
CREATE OR REPLACE PROCEDURE sp_calculate_match(
    p_student1 IN NUMBER,
    p_student2 IN NUMBER
)
IS
    v_dept1   users.department%TYPE;
    v_dept2   users.department%TYPE;
    v_bud1    users.monthly_budget%TYPE;
    v_bud2    users.monthly_budget%TYPE;
    v_pref1   users.preferences%TYPE;
    v_pref2   users.preferences%TYPE;
    v_score   NUMBER(5,2) := 0;
    v_dm      NUMBER(1)   := 0;
    v_bm      NUMBER(1)   := 0;
    v_po      NUMBER      := 0;
    v_reason  VARCHAR2(1000) := '';

    FUNCTION has_kw(p_pref IN VARCHAR2, p_kw IN VARCHAR2) RETURN BOOLEAN IS
    BEGIN
        RETURN INSTR(LOWER(NVL(p_pref,'')), p_kw) > 0;
    END;

BEGIN
    -- Fetch student 1 profile
    SELECT department, monthly_budget, preferences
    INTO   v_dept1, v_bud1, v_pref1
    FROM   users
    WHERE  user_id = p_student1;

    -- Fetch student 2 profile
    SELECT department, monthly_budget, preferences
    INTO   v_dept2, v_bud2, v_pref2
    FROM   users
    WHERE  user_id = p_student2;

    -- Department match (40 pts)
    IF v_dept1 IS NOT NULL AND v_dept1 = v_dept2 THEN
        v_score  := v_score + 40;
        v_dm     := 1;
        v_reason := v_reason || 'Same department (' || v_dept1 || '). ';
    END IF;

    -- Budget compatibility (30 pts) — within 25%
    IF v_bud1 > 0 AND v_bud2 > 0 THEN
        IF ABS(v_bud1 - v_bud2) / GREATEST(v_bud1, v_bud2) <= 0.25 THEN
            v_score  := v_score + 30;
            v_bm     := 1;
            v_reason := v_reason || 'Compatible budget range. ';
        END IF;
    END IF;

    -- Preference keyword overlap (10 pts each, max 30)
    IF has_kw(v_pref1,'quiet') AND has_kw(v_pref2,'quiet') THEN
        v_score  := v_score + 10;
        v_po     := v_po + 1;
        v_reason := v_reason || 'Both prefer quiet environment. ';
    END IF;
    IF has_kw(v_pref1,'study') AND has_kw(v_pref2,'study') THEN
        v_score  := v_score + 10;
        v_po     := v_po + 1;
        v_reason := v_reason || 'Both are study-focused. ';
    END IF;
    IF has_kw(v_pref1,'clean') AND has_kw(v_pref2,'clean') THEN
        v_score  := v_score + 10;
        v_po     := v_po + 1;
        v_reason := v_reason || 'Both prefer a clean room. ';
    END IF;
    -- Bonus: early riser (5 pts, does not count toward pref_overlap)
    IF has_kw(v_pref1,'early') AND has_kw(v_pref2,'early') THEN
        v_score  := v_score + 5;
        v_reason := v_reason || 'Both are early risers. ';
    END IF;

    -- Cap at 100
    v_score := LEAST(v_score, 100);

    -- MERGE both directions
    MERGE INTO roommate_matches m
    USING (SELECT p_student1 AS sid, p_student2 AS mid FROM DUAL) src
    ON    (m.student_id = src.sid AND m.matched_student_id = src.mid)
    WHEN MATCHED THEN
        UPDATE SET m.match_score  = v_score, m.dept_match  = v_dm,
                   m.budget_match = v_bm,    m.pref_overlap = v_po,
                   m.match_reason = v_reason, m.matched_at  = SYSTIMESTAMP
    WHEN NOT MATCHED THEN
        INSERT (student_id, matched_student_id, match_score, dept_match, budget_match, pref_overlap, match_reason)
        VALUES (p_student1, p_student2, v_score, v_dm, v_bm, v_po, v_reason);

    MERGE INTO roommate_matches m
    USING (SELECT p_student2 AS sid, p_student1 AS mid FROM DUAL) src
    ON    (m.student_id = src.sid AND m.matched_student_id = src.mid)
    WHEN MATCHED THEN
        UPDATE SET m.match_score  = v_score, m.dept_match  = v_dm,
                   m.budget_match = v_bm,    m.pref_overlap = v_po,
                   m.match_reason = v_reason, m.matched_at  = SYSTIMESTAMP
    WHEN NOT MATCHED THEN
        INSERT (student_id, matched_student_id, match_score, dept_match, budget_match, pref_overlap, match_reason)
        VALUES (p_student2, p_student1, v_score, v_dm, v_bm, v_po, v_reason);

    COMMIT;

EXCEPTION
    WHEN NO_DATA_FOUND THEN
        NULL; -- Student not found — skip silently
    WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
END sp_calculate_match;
/


-- ================================================================
-- PROCEDURE: sp_run_all_matches
-- Iterates over all active student pairs and calls sp_calculate_match.
-- Uses an explicit cursor to loop over unique (student_a, student_b)
-- pairs where a.user_id < b.user_id to avoid duplicating work.
-- ================================================================
CREATE OR REPLACE PROCEDURE sp_run_all_matches
IS
    CURSOR c_pairs IS
        SELECT a.user_id AS s1, b.user_id AS s2
        FROM   users a
        JOIN   users b ON a.user_id < b.user_id
        WHERE  a.role_name     = 'STUDENT' AND a.account_status = 'ACTIVE'
          AND  b.role_name     = 'STUDENT' AND b.account_status = 'ACTIVE';
BEGIN
    FOR rec IN c_pairs LOOP
        sp_calculate_match(rec.s1, rec.s2);
    END LOOP;
    COMMIT;
END sp_run_all_matches;
/


-- ================================================================
-- FUNCTION: fn_occupancy_pct
-- Returns the current occupancy percentage of a given hall (0–100).
-- ================================================================
CREATE OR REPLACE FUNCTION fn_occupancy_pct(p_hall_id IN NUMBER)
RETURN NUMBER
IS
    v_total  NUMBER := 0;
    v_booked NUMBER := 0;
BEGIN
    SELECT COUNT(*),
           SUM(CASE WHEN s.seat_status = 'BOOKED' THEN 1 ELSE 0 END)
    INTO   v_total, v_booked
    FROM   seats s
    JOIN   rooms r ON s.room_id = r.room_id
    WHERE  r.hall_id = p_hall_id;

    IF v_total = 0 THEN RETURN 0; END IF;
    RETURN ROUND(v_booked * 100 / v_total, 2);
END fn_occupancy_pct;
/

-- ================================================================
-- NEXT STEP: Run sql/04_triggers.sql
-- ================================================================
