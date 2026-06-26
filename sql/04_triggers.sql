-- ================================================================
-- NestSync: Smart Hall & Roommate Matching Management System
-- File: 04_triggers.sql
-- Description: MySQL triggers for automatic status updates
-- Run AFTER 03_procedures.sql
-- ================================================================

USE nestsync;

-- Drop existing triggers
DROP TRIGGER IF EXISTS trg_booking_insert;
DROP TRIGGER IF EXISTS trg_booking_update;
DROP TRIGGER IF EXISTS trg_seat_update_room_status;

DELIMITER //

-- ================================================================
-- TRIGGER: trg_booking_insert
-- After a new booking is inserted (status = PENDING):
--   • Mark the seat as RESERVED so it cannot be double-booked
-- ================================================================
CREATE TRIGGER trg_booking_insert
AFTER INSERT ON bookings
FOR EACH ROW
BEGIN
    IF NEW.booking_status = 'PENDING' THEN
        UPDATE seats
        SET    seat_status = 'RESERVED'
        WHERE  seat_id     = NEW.seat_id
          AND  seat_status = 'AVAILABLE';
    END IF;
END //

-- ================================================================
-- TRIGGER: trg_booking_update
-- After a booking status changes:
--   APPROVED  → seat becomes BOOKED, assigned to student
--   REJECTED / CANCELLED → seat reverts to AVAILABLE
-- ================================================================
CREATE TRIGGER trg_booking_update
AFTER UPDATE ON bookings
FOR EACH ROW
BEGIN
    -- Booking just approved
    IF NEW.booking_status = 'APPROVED' AND OLD.booking_status != 'APPROVED' THEN
        UPDATE seats
        SET    seat_status        = 'BOOKED',
               current_student_id = NEW.student_id
        WHERE  seat_id = NEW.seat_id;
    END IF;

    -- Booking rejected or cancelled
    IF NEW.booking_status IN ('REJECTED','CANCELLED')
       AND OLD.booking_status NOT IN ('REJECTED','CANCELLED') THEN
        UPDATE seats
        SET    seat_status        = 'AVAILABLE',
               current_student_id = NULL
        WHERE  seat_id            = NEW.seat_id
          AND  (current_student_id = NEW.student_id
                OR seat_status IN ('RESERVED','BOOKED'));
    END IF;
END //

-- ================================================================
-- TRIGGER: trg_seat_update_room_status
-- After any seat's status changes, recalculate the parent room status:
--   All BOOKED/RESERVED → room is FULL
--   At least one AVAILABLE → room is AVAILABLE
-- ================================================================
CREATE TRIGGER trg_seat_update_room_status
AFTER UPDATE ON seats
FOR EACH ROW
BEGIN
    DECLARE v_avail INT;
    DECLARE v_total INT;

    SELECT COUNT(*),
           SUM(CASE WHEN seat_status = 'AVAILABLE' THEN 1 ELSE 0 END)
    INTO   v_total, v_avail
    FROM   seats
    WHERE  room_id = NEW.room_id;

    IF v_avail = 0 THEN
        UPDATE rooms SET room_status = 'FULL'      WHERE room_id = NEW.room_id;
    ELSE
        UPDATE rooms SET room_status = 'AVAILABLE' WHERE room_id = NEW.room_id
          AND  room_status NOT IN ('MAINTENANCE','INACTIVE');
    END IF;
END //

DELIMITER ;

-- ================================================================
-- DONE: All schema files applied.
-- Visit http://localhost/NestSync/seed.php to load sample data.
-- ================================================================
