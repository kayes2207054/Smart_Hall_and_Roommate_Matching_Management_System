SELECT 
    s.seat_id,
    s.seat_label,
    s.seat_status,
    r.room_number,
    h.hall_name
FROM seats s
JOIN rooms r ON s.room_id = r.room_id
JOIN halls h ON r.hall_id = h.hall_id
WHERE s.seat_status = 'AVAILABLE';


SELECT 
    h.hall_name,
    r.room_number,
    r.room_type,
    r.monthly_rent
FROM halls h
JOIN rooms r ON h.hall_id = r.hall_id
ORDER BY h.hall_name;


SELECT 
    u.full_name,
    h.hall_name,
    r.room_number,
    s.seat_label,
    b.booking_status,
    b.requested_at
FROM bookings b
JOIN users u ON b.student_id = u.user_id
JOIN halls h ON b.hall_id = h.hall_id
JOIN rooms r ON b.room_id = r.room_id
JOIN seats s ON b.seat_id = s.seat_id;


SELECT *
FROM bookings
WHERE booking_status = 'PENDING';