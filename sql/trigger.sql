CREATE OR REPLACE TRIGGER trg_update_seat_status
AFTER INSERT ON bookings
FOR EACH ROW
BEGIN
    UPDATE seats
    SET seat_status = 'BOOKED',
        current_student_id = :NEW.student_id
    WHERE seat_id = :NEW.seat_id;
END;
/


