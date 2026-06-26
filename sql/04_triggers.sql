-- ================================================================
-- NestSync: Smart Hall & Roommate Matching Management System
-- File        : sql/04_triggers.sql
-- Database    : Oracle 19c+ (PL/SQL)
-- Description : Business-logic triggers for automatic seat and
--               room status management.
-- Run AFTER   : sql/03_procedures.sql
-- ================================================================


-- ================================================================
-- TRIGGER: trg_booking_after_insert
-- Fires AFTER a new booking row is inserted.
-- Behaviour: If the new booking is PENDING, mark the target seat
-- as RESERVED so no other booking can claim it simultaneously.
-- ================================================================
CREATE OR REPLACE TRIGGER trg_booking_after_insert
AFTER INSERT ON bookings
FOR EACH ROW
BEGIN
    IF :NEW.booking_status = 'PENDING' THEN
        UPDATE seats
        SET    seat_status = 'RESERVED'
        WHERE  seat_id     = :NEW.seat_id
          AND  seat_status = 'AVAILABLE';
    END IF;
END;
/


-- ================================================================
-- TRIGGER: trg_booking_after_update
-- Fires AFTER a booking row's status changes.
--
-- APPROVED  → seat becomes BOOKED and is assigned to the student
-- REJECTED  → seat reverts to AVAILABLE, student cleared
-- CANCELLED → seat reverts to AVAILABLE, student cleared
-- ================================================================
CREATE OR REPLACE TRIGGER trg_booking_after_update
AFTER UPDATE OF booking_status ON bookings
FOR EACH ROW
BEGIN
    -- Booking just approved
    IF :NEW.booking_status = 'APPROVED'
       AND :OLD.booking_status != 'APPROVED'
    THEN
        UPDATE seats
        SET    seat_status        = 'BOOKED',
               current_student_id = :NEW.student_id
        WHERE  seat_id = :NEW.seat_id;

    -- Booking rejected or cancelled
    ELSIF :NEW.booking_status IN ('REJECTED','CANCELLED')
          AND :OLD.booking_status NOT IN ('REJECTED','CANCELLED')
    THEN
        UPDATE seats
        SET    seat_status        = 'AVAILABLE',
               current_student_id = NULL
        WHERE  seat_id = :NEW.seat_id
          AND  (current_student_id = :NEW.student_id
                OR seat_status IN ('RESERVED','BOOKED'));
    END IF;
END;
/


-- ================================================================
-- TRIGGER: trg_seat_status_to_room
-- Fires AFTER any seat's status is updated.
-- Recalculates the parent room's status:
--   No AVAILABLE seat left → room becomes FULL
--   At least one AVAILABLE  → room becomes AVAILABLE
-- Skips rooms in MAINTENANCE or INACTIVE status.
-- ================================================================
CREATE OR REPLACE TRIGGER trg_seat_status_to_room
AFTER UPDATE OF seat_status ON seats
FOR EACH ROW
DECLARE
    v_avail NUMBER;
    v_total NUMBER;
BEGIN
    SELECT COUNT(*),
           SUM(CASE WHEN seat_status = 'AVAILABLE' THEN 1 ELSE 0 END)
    INTO   v_total, v_avail
    FROM   seats
    WHERE  room_id = :NEW.room_id;

    IF v_avail = 0 THEN
        UPDATE rooms
        SET    room_status = 'FULL'
        WHERE  room_id     = :NEW.room_id
          AND  room_status NOT IN ('MAINTENANCE','INACTIVE');
    ELSE
        UPDATE rooms
        SET    room_status = 'AVAILABLE'
        WHERE  room_id     = :NEW.room_id
          AND  room_status NOT IN ('MAINTENANCE','INACTIVE');
    END IF;
END;
/


-- ================================================================
-- TRIGGER: trg_prevent_double_booking
-- Fires BEFORE INSERT on bookings.
-- Prevents a student from submitting a new PENDING booking if they
-- already have an active PENDING or APPROVED booking.
-- ================================================================
CREATE OR REPLACE TRIGGER trg_prevent_double_booking
BEFORE INSERT ON bookings
FOR EACH ROW
DECLARE
    v_existing NUMBER := 0;
BEGIN
    SELECT COUNT(*)
    INTO   v_existing
    FROM   bookings
    WHERE  student_id     = :NEW.student_id
      AND  booking_status IN ('PENDING','APPROVED');

    IF v_existing > 0 THEN
        RAISE_APPLICATION_ERROR(
            -20010,
            'Student already has an active booking. Cancel or wait for the current booking to be resolved.'
        );
    END IF;
END;
/


-- ================================================================
-- TRIGGER: trg_prevent_seat_double_reserve
-- Fires BEFORE INSERT on bookings.
-- Prevents booking a seat that is not AVAILABLE.
-- ================================================================
CREATE OR REPLACE TRIGGER trg_prevent_seat_double_reserve
BEFORE INSERT ON bookings
FOR EACH ROW
DECLARE
    v_status seats.seat_status%TYPE;
BEGIN
    SELECT seat_status
    INTO   v_status
    FROM   seats
    WHERE  seat_id = :NEW.seat_id;

    IF v_status != 'AVAILABLE' THEN
        RAISE_APPLICATION_ERROR(
            -20011,
            'Seat ' || :NEW.seat_id || ' is not available (current status: ' || v_status || ').'
        );
    END IF;

EXCEPTION
    WHEN NO_DATA_FOUND THEN
        RAISE_APPLICATION_ERROR(-20012, 'Seat ' || :NEW.seat_id || ' does not exist.');
END;
/

-- ================================================================
-- NEXT STEP: Run sql/05_sample_data.sql to load demo data.
-- ================================================================
