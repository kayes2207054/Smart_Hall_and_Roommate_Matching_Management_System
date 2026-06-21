CREATE OR REPLACE PROCEDURE approve_booking(p_booking_id NUMBER)
AS
BEGIN
    UPDATE bookings
    SET booking_status = 'APPROVED',
        reviewed_at = SYSTIMESTAMP
    WHERE booking_id = p_booking_id;

    COMMIT;
END;
/

CREATE OR REPLACE PROCEDURE reject_booking(p_booking_id NUMBER)
AS
BEGIN
    UPDATE bookings
    SET booking_status = 'REJECTED',
        reviewed_at = SYSTIMESTAMP
    WHERE booking_id = p_booking_id;

    COMMIT;
END;
/

