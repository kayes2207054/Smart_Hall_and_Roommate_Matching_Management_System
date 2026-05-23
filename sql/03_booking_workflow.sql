-- Booking workflow helpers for approval/rejection history tracking.

CREATE OR REPLACE TRIGGER trg_booking_request_history
AFTER UPDATE OF request_status ON booking_request
FOR EACH ROW
BEGIN
    INSERT INTO booking_history (
        request_id,
        action_type,
        action_by,
        action_at,
        notes
    ) VALUES (
        :NEW.request_id,
        :NEW.request_status,
        NVL(:NEW.reviewed_by, :NEW.student_id),
        SYSTIMESTAMP,
        :NEW.remarks
    );
END;
/

CREATE OR REPLACE PROCEDURE approve_booking_request(
    p_request_id NUMBER,
    p_reviewer_id NUMBER,
    p_notes      VARCHAR2 DEFAULT NULL
) AS
BEGIN
    UPDATE booking_request
    SET request_status = 'APPROVED',
        reviewed_by = p_reviewer_id,
        reviewed_at = SYSTIMESTAMP,
        remarks = p_notes
    WHERE request_id = p_request_id
      AND request_status = 'PENDING';

    UPDATE seat s
    SET seat_status = 'BOOKED'
    WHERE s.seat_id = (
        SELECT br.seat_id
        FROM booking_request br
        WHERE br.request_id = p_request_id
    );

    UPDATE room r
    SET room_status = CASE
        WHEN (SELECT COUNT(*) FROM seat s WHERE s.room_id = r.room_id AND s.seat_status = 'AVAILABLE') = 0 THEN 'FULL'
        ELSE 'PARTIAL'
    END
    WHERE r.room_id = (
        SELECT br.room_id
        FROM booking_request br
        WHERE br.request_id = p_request_id
    );

    COMMIT;
END approve_booking_request;
/
