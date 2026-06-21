CREATE OR REPLACE VIEW view_available_seats AS
SELECT 
    s.seat_id,
    s.seat_label,
    r.room_number,
    h.hall_name
FROM seats s
JOIN rooms r ON s.room_id = r.room_id
JOIN halls h ON r.hall_id = h.hall_id
WHERE s.seat_status = 'AVAILABLE';


CREATE OR REPLACE VIEW view_booking_summary AS
SELECT 
    b.booking_id,
    u.full_name,
    h.hall_name,
    b.booking_status,
    b.requested_at
FROM bookings b
JOIN users u ON b.student_id = u.user_id
JOIN halls h ON b.hall_id = h.hall_id;


