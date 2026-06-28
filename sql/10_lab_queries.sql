-- ================================================================
-- NestSync: Smart Hall & Roommate Matching Management System
-- File        : sql/10_lab_queries.sql
-- Database    : Oracle 11g/19c (SQL*Plus)
-- Description : 10 practical SQL queries for final lab submission.
-- ================================================================

-- 1. Student-Room-Hall Mapping (JOIN across 4 tables)
-- Retrieves a list of students with their currently assigned hall, room, and seat.
SELECT 
    u.full_name AS Student_Name, 
    u.student_id_no AS Student_ID, 
    h.hall_name AS Hall, 
    r.room_number AS Room, 
    s.seat_label AS Seat
FROM users u
JOIN seats s ON u.user_id = s.current_student_id
JOIN rooms r ON s.room_id = r.room_id
JOIN halls h ON r.hall_id = h.hall_id
WHERE u.role_name = 'STUDENT';


-- 2. Hall Occupancy Report (Aggregation & Group By)
-- Shows total capacity, booked seats, and occupancy percentage for each hall.
SELECT 
    h.hall_name, 
    COUNT(s.seat_id) AS Total_Seats,
    SUM(CASE WHEN s.seat_status = 'BOOKED' THEN 1 ELSE 0 END) AS Booked_Seats,
    ROUND((SUM(CASE WHEN s.seat_status = 'BOOKED' THEN 1 ELSE 0 END) / COUNT(s.seat_id)) * 100, 2) AS Occupancy_Percent
FROM halls h
JOIN rooms r ON h.hall_id = r.hall_id
JOIN seats s ON r.room_id = s.room_id
GROUP BY h.hall_name
ORDER BY Occupancy_Percent DESC;


-- 3. Room Availability Report (Filtering & JOINs)
-- Finds all rooms that still have at least one AVAILABLE seat.
SELECT 
    h.hall_name, 
    r.room_number, 
    r.room_type, 
    r.monthly_rent,
    COUNT(s.seat_id) AS Available_Seats
FROM rooms r
JOIN halls h ON r.hall_id = h.hall_id
JOIN seats s ON r.room_id = s.room_id
WHERE s.seat_status = 'AVAILABLE'
GROUP BY h.hall_name, r.room_number, r.room_type, r.monthly_rent
ORDER BY h.hall_name, r.room_number;


-- 4. Booking Status Filtering (Subqueries & Dates)
-- Lists all PENDING booking requests submitted in the last 30 days.
SELECT 
    b.booking_id, 
    u.full_name AS Applicant, 
    h.hall_name, 
    r.room_number, 
    b.requested_at
FROM bookings b
JOIN users u ON b.student_id = u.user_id
JOIN halls h ON b.hall_id = h.hall_id
JOIN rooms r ON b.room_id = r.room_id
WHERE b.booking_status = 'PENDING' 
  AND b.requested_at >= SYSDATE - 30
ORDER BY b.requested_at ASC;


-- 5. Top Roommate Matches (Self-Join concepts & Filtering)
-- Finds the highest compatibility scores (>75) between unassigned students.
SELECT 
    u1.full_name AS Student_A, 
    u2.full_name AS Student_B, 
    m.match_score, 
    m.match_reason
FROM roommate_matches m
JOIN users u1 ON m.student_id = u1.user_id
JOIN users u2 ON m.matched_student_id = u2.user_id
WHERE m.match_score > 75
ORDER BY m.match_score DESC;


-- 6. Revenue Projection per Hall (Aggregation)
-- Calculates the expected monthly revenue if all booked seats are paid for.
SELECT 
    h.hall_name, 
    SUM(r.monthly_rent) AS Expected_Monthly_Revenue
FROM seats s
JOIN rooms r ON s.room_id = r.room_id
JOIN halls h ON r.hall_id = h.hall_id
WHERE s.seat_status = 'BOOKED'
GROUP BY h.hall_name;


-- 7. Student Demographic Distribution (GROUP BY & Count)
-- Counts how many active students are enrolled in each academic department.
SELECT 
    department, 
    COUNT(user_id) AS Total_Students,
    AVG(monthly_budget) AS Average_Budget
FROM users
WHERE role_name = 'STUDENT' AND account_status = 'ACTIVE'
GROUP BY department
ORDER BY Total_Students DESC;


-- 8. Find Full Rooms (HAVING Clause)
-- Identifies rooms that are at maximum capacity (no available seats).
SELECT 
    h.hall_name, 
    r.room_number, 
    r.capacity
FROM rooms r
JOIN halls h ON r.hall_id = h.hall_id
JOIN seats s ON r.room_id = s.room_id
GROUP BY h.hall_name, r.room_number, r.capacity
HAVING SUM(CASE WHEN s.seat_status = 'AVAILABLE' THEN 1 ELSE 0 END) = 0;


-- 9. Unprocessed Bookings by Hall Admin
-- Shows which Hall Admins have pending bookings waiting for their approval.
SELECT 
    a.full_name AS Admin_Name, 
    h.hall_name, 
    COUNT(b.booking_id) AS Pending_Requests
FROM halls h
JOIN users a ON h.managed_by = a.user_id
LEFT JOIN bookings b ON h.hall_id = b.hall_id AND b.booking_status = 'PENDING'
GROUP BY a.full_name, h.hall_name;


-- 10. Security Audit (Nested Queries)
-- Finds users who have generated system notifications but haven't booked a seat.
SELECT 
    u.user_id, 
    u.full_name, 
    u.email
FROM users u
WHERE u.role_name = 'STUDENT'
  AND u.user_id IN (SELECT user_id FROM notifications)
  AND u.user_id NOT IN (SELECT current_student_id FROM seats WHERE current_student_id IS NOT NULL);
