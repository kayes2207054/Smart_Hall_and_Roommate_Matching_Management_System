-- ================================================================
-- NestSync: Smart Hall & Roommate Matching Management System
-- File        : sql/02_views.sql
-- Database    : Oracle 19c+ (PL/SQL)
-- Description : All reporting and data-access views.
--               Uses Oracle syntax: NVL, DECODE, ROUND, TO_CHAR.
-- Run AFTER   : sql/01_core_schema.sql
-- ================================================================


-- ================================================================
-- VIEW: vw_available_seats
-- All seats that are AVAILABLE in ACTIVE halls with AVAILABLE rooms
-- ================================================================
CREATE OR REPLACE VIEW vw_available_seats AS
SELECT
    s.seat_id,
    s.seat_label,
    s.seat_status,
    r.room_id,
    r.room_number,
    r.room_type,
    r.monthly_rent,
    r.floor_number,
    r.facilities,
    h.hall_id,
    h.hall_name,
    h.hall_location,
    h.gender_type
FROM seats s
JOIN rooms r ON s.room_id = r.room_id
JOIN halls h ON r.hall_id = h.hall_id
WHERE s.seat_status  = 'AVAILABLE'
  AND r.room_status  = 'AVAILABLE'
  AND h.hall_status  = 'ACTIVE';


-- ================================================================
-- VIEW: vw_booking_summary
-- Full booking details joined across all tables
-- ================================================================
CREATE OR REPLACE VIEW vw_booking_summary AS
SELECT
    b.booking_id,
    b.booking_status,
    b.semester,
    b.requested_at,
    b.reviewed_at,
    b.notes,
    b.admin_remarks,
    -- Student info
    u.user_id        AS student_user_id,
    u.full_name      AS student_name,
    u.email          AS student_email,
    u.department     AS student_dept,
    u.student_id_no,
    u.phone          AS student_phone,
    -- Hall info
    h.hall_id,
    h.hall_name,
    h.hall_location,
    -- Room info
    r.room_id,
    r.room_number,
    r.room_type,
    r.monthly_rent,
    r.floor_number,
    -- Seat info
    s.seat_id,
    s.seat_label,
    -- Reviewer info
    rev.user_id     AS reviewed_by_id,
    rev.full_name   AS reviewed_by_name
FROM bookings b
JOIN users    u   ON b.student_id  = u.user_id
JOIN halls    h   ON b.hall_id     = h.hall_id
JOIN rooms    r   ON b.room_id     = r.room_id
JOIN seats    s   ON b.seat_id     = s.seat_id
LEFT JOIN users rev ON b.reviewed_by = rev.user_id;


-- ================================================================
-- VIEW: vw_pending_bookings
-- Quick filter on vw_booking_summary for PENDING status
-- ================================================================
CREATE OR REPLACE VIEW vw_pending_bookings AS
SELECT *
FROM vw_booking_summary
WHERE booking_status = 'PENDING';


-- ================================================================
-- VIEW: vw_hall_occupancy
-- Per-hall seat statistics and occupancy percentage
-- ================================================================
CREATE OR REPLACE VIEW vw_hall_occupancy AS
SELECT
    h.hall_id,
    h.hall_name,
    h.hall_location,
    h.total_capacity,
    h.gender_type,
    h.hall_status,
    mgr.full_name                                       AS manager_name,
    COUNT(DISTINCT r.room_id)                           AS total_rooms,
    COUNT(s.seat_id)                                    AS total_seats,
    SUM(CASE WHEN s.seat_status = 'AVAILABLE'    THEN 1 ELSE 0 END)  AS available_seats,
    SUM(CASE WHEN s.seat_status = 'BOOKED'       THEN 1 ELSE 0 END)  AS booked_seats,
    SUM(CASE WHEN s.seat_status = 'RESERVED'     THEN 1 ELSE 0 END)  AS reserved_seats,
    SUM(CASE WHEN s.seat_status = 'MAINTENANCE'  THEN 1 ELSE 0 END)  AS maintenance_seats,
    ROUND(
        NVL(SUM(CASE WHEN s.seat_status = 'BOOKED' THEN 1 ELSE 0 END), 0)
        * 100
        / NULLIF(COUNT(s.seat_id), 0),
    2)                                                  AS occupancy_pct
FROM halls h
LEFT JOIN users  mgr ON h.managed_by = mgr.user_id
LEFT JOIN rooms  r   ON h.hall_id    = r.hall_id
LEFT JOIN seats  s   ON r.room_id    = s.room_id
GROUP BY
    h.hall_id, h.hall_name, h.hall_location,
    h.total_capacity, h.gender_type, h.hall_status, mgr.full_name;


-- ================================================================
-- VIEW: vw_room_stats
-- Per-room seat statistics with hall name
-- ================================================================
CREATE OR REPLACE VIEW vw_room_stats AS
SELECT
    r.room_id,
    r.room_number,
    r.room_type,
    r.capacity,
    r.floor_number,
    r.monthly_rent,
    r.facilities,
    r.room_status,
    h.hall_id,
    h.hall_name,
    COUNT(s.seat_id)                                               AS total_seats,
    SUM(CASE WHEN s.seat_status = 'AVAILABLE' THEN 1 ELSE 0 END)  AS available_seats,
    SUM(CASE WHEN s.seat_status = 'BOOKED'    THEN 1 ELSE 0 END)  AS booked_seats,
    SUM(CASE WHEN s.seat_status = 'RESERVED'  THEN 1 ELSE 0 END)  AS reserved_seats
FROM rooms r
JOIN halls h  ON r.hall_id = h.hall_id
LEFT JOIN seats s ON r.room_id = s.room_id
GROUP BY
    r.room_id, r.room_number, r.room_type, r.capacity,
    r.floor_number, r.monthly_rent, r.facilities, r.room_status,
    h.hall_id, h.hall_name;


-- ================================================================
-- VIEW: vw_student_assignment
-- Students who currently occupy a seat
-- ================================================================
CREATE OR REPLACE VIEW vw_student_assignment AS
SELECT
    u.user_id,
    u.full_name,
    u.email,
    u.department,
    u.student_id_no,
    u.phone,
    u.monthly_budget,
    s.seat_id,
    s.seat_label,
    r.room_id,
    r.room_number,
    r.room_type,
    r.monthly_rent,
    h.hall_id,
    h.hall_name
FROM users u
JOIN seats s ON u.user_id  = s.current_student_id
JOIN rooms r ON s.room_id  = r.room_id
JOIN halls h ON r.hall_id  = h.hall_id
WHERE u.role_name = 'STUDENT';


-- ================================================================
-- VIEW: vw_roommate_matches_detail
-- Match pairs with full student information on both sides
-- ================================================================
CREATE OR REPLACE VIEW vw_roommate_matches_detail AS
SELECT
    rm.match_id,
    rm.match_score,
    rm.dept_match,
    rm.budget_match,
    rm.pref_overlap,
    rm.match_reason,
    rm.match_status,
    rm.matched_at,
    -- Student 1
    u1.user_id        AS student_id,
    u1.full_name      AS student_name,
    u1.email          AS student_email,
    u1.department     AS student_dept,
    u1.monthly_budget AS student_budget,
    -- Student 2
    u2.user_id        AS matched_student_id,
    u2.full_name      AS matched_name,
    u2.email          AS matched_email,
    u2.department     AS matched_dept,
    u2.monthly_budget AS matched_budget
FROM roommate_matches rm
JOIN users u1 ON rm.student_id         = u1.user_id
JOIN users u2 ON rm.matched_student_id = u2.user_id;


-- ================================================================
-- VIEW: vw_dashboard_stats
-- Aggregate platform statistics for admin dashboard
-- ================================================================
CREATE OR REPLACE VIEW vw_dashboard_stats AS
SELECT
    (SELECT COUNT(*) FROM users         WHERE role_name = 'STUDENT'   AND account_status = 'ACTIVE') AS total_students,
    (SELECT COUNT(*) FROM halls         WHERE hall_status = 'ACTIVE')                                AS total_halls,
    (SELECT COUNT(*) FROM rooms         WHERE room_status IN ('AVAILABLE','FULL'))                   AS total_rooms,
    (SELECT COUNT(*) FROM seats)                                                                      AS total_seats,
    (SELECT COUNT(*) FROM seats         WHERE seat_status = 'AVAILABLE')                             AS available_seats,
    (SELECT COUNT(*) FROM seats         WHERE seat_status = 'BOOKED')                                AS booked_seats,
    (SELECT COUNT(*) FROM bookings      WHERE booking_status = 'PENDING')                            AS pending_bookings,
    (SELECT COUNT(*) FROM bookings      WHERE booking_status = 'APPROVED')                           AS approved_bookings,
    (SELECT COUNT(*) FROM bookings      WHERE booking_status = 'REJECTED')                           AS rejected_bookings,
    (SELECT COUNT(*) FROM users         WHERE role_name = 'HALL_ADMIN')                              AS total_hall_admins
FROM DUAL;

-- ================================================================
-- NEXT STEP: Run sql/03_procedures.sql
-- ================================================================
