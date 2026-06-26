-- ================================================================
-- NestSync: Smart Hall & Roommate Matching Management System
-- File: 01_core_schema.sql
-- Database: MySQL 5.7+ / MariaDB 10.3+
-- Description: Core schema — tables, constraints, indexes
-- Run this file FIRST in phpMyAdmin or MySQL CLI
-- ================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';
SET NAMES utf8mb4;

-- ----------------------------------------------------------------
-- Create & select database
-- ----------------------------------------------------------------
DROP DATABASE IF EXISTS nestsync;
CREATE DATABASE nestsync
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE nestsync;

-- ================================================================
-- TABLE: users
-- Stores all system users: System Admins, Hall Admins, Students
-- ================================================================
CREATE TABLE users (
    user_id        INT            NOT NULL AUTO_INCREMENT,
    full_name      VARCHAR(120)   NOT NULL,
    email          VARCHAR(150)   NOT NULL,
    password_hash  VARCHAR(255)   NOT NULL,
    role           ENUM('SYSTEM_ADMIN','HALL_ADMIN','STUDENT') NOT NULL DEFAULT 'STUDENT',
    department     VARCHAR(80)    DEFAULT NULL,
    phone          VARCHAR(20)    DEFAULT NULL,
    student_id_no  VARCHAR(30)    DEFAULT NULL,
    monthly_budget DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    preferences    VARCHAR(500)   DEFAULT NULL,
    gender         ENUM('MALE','FEMALE','OTHER') DEFAULT NULL,
    account_status ENUM('ACTIVE','INACTIVE','SUSPENDED') NOT NULL DEFAULT 'ACTIVE',
    created_at     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_email      (email),
    UNIQUE KEY uq_student_id (student_id_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='All platform users: admins and students';

-- ================================================================
-- TABLE: halls
-- Residential halls / hostels on campus
-- ================================================================
CREATE TABLE halls (
    hall_id        INT            NOT NULL AUTO_INCREMENT,
    hall_name      VARCHAR(120)   NOT NULL,
    hall_location  VARCHAR(180)   NOT NULL,
    total_capacity INT            NOT NULL DEFAULT 0,
    description    TEXT           DEFAULT NULL,
    gender_type    ENUM('MALE','FEMALE','MIXED') NOT NULL DEFAULT 'MIXED',
    managed_by     INT            DEFAULT NULL,
    hall_status    ENUM('ACTIVE','INACTIVE','MAINTENANCE') NOT NULL DEFAULT 'ACTIVE',
    created_at     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (hall_id),
    UNIQUE KEY uq_hall_name (hall_name),
    CONSTRAINT fk_hall_admin
        FOREIGN KEY (managed_by) REFERENCES users(user_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Residential halls managed by hall admins';

-- ================================================================
-- TABLE: rooms
-- Individual rooms inside halls
-- ================================================================
CREATE TABLE rooms (
    room_id       INT            NOT NULL AUTO_INCREMENT,
    hall_id       INT            NOT NULL,
    room_number   VARCHAR(30)    NOT NULL,
    room_type     ENUM('SINGLE','DOUBLE','TRIPLE','QUAD') NOT NULL DEFAULT 'DOUBLE',
    capacity      INT            NOT NULL DEFAULT 2,
    floor_number  INT            NOT NULL DEFAULT 1,
    monthly_rent  DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    facilities    VARCHAR(300)   DEFAULT NULL,
    room_status   ENUM('AVAILABLE','FULL','MAINTENANCE','INACTIVE') NOT NULL DEFAULT 'AVAILABLE',
    created_at    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (room_id),
    UNIQUE KEY uq_room_in_hall (hall_id, room_number),
    CONSTRAINT fk_room_hall
        FOREIGN KEY (hall_id) REFERENCES halls(hall_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Individual rooms within each hall';

-- ================================================================
-- TABLE: seats
-- Individual bed/seat within a room
-- ================================================================
CREATE TABLE seats (
    seat_id            INT            NOT NULL AUTO_INCREMENT,
    room_id            INT            NOT NULL,
    seat_label         VARCHAR(20)    NOT NULL,
    seat_status        ENUM('AVAILABLE','BOOKED','RESERVED','MAINTENANCE') NOT NULL DEFAULT 'AVAILABLE',
    current_student_id INT            DEFAULT NULL,
    created_at         TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (seat_id),
    UNIQUE KEY uq_seat_in_room (room_id, seat_label),
    CONSTRAINT fk_seat_room
        FOREIGN KEY (room_id) REFERENCES rooms(room_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_seat_student
        FOREIGN KEY (current_student_id) REFERENCES users(user_id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Individual beds/seats within rooms';

-- ================================================================
-- TABLE: bookings
-- Hall seat booking requests from students
-- ================================================================
CREATE TABLE bookings (
    booking_id      INT            NOT NULL AUTO_INCREMENT,
    student_id      INT            NOT NULL,
    hall_id         INT            NOT NULL,
    room_id         INT            NOT NULL,
    seat_id         INT            NOT NULL,
    booking_status  ENUM('PENDING','APPROVED','REJECTED','CANCELLED') NOT NULL DEFAULT 'PENDING',
    semester        VARCHAR(30)    DEFAULT NULL,
    requested_at    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_by     INT            DEFAULT NULL,
    reviewed_at     TIMESTAMP      NULL DEFAULT NULL,
    notes           TEXT           DEFAULT NULL,
    admin_remarks   TEXT           DEFAULT NULL,
    PRIMARY KEY (booking_id),
    CONSTRAINT fk_booking_student
        FOREIGN KEY (student_id)  REFERENCES users(user_id)  ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT fk_booking_hall
        FOREIGN KEY (hall_id)     REFERENCES halls(hall_id)  ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT fk_booking_room
        FOREIGN KEY (room_id)     REFERENCES rooms(room_id)  ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT fk_booking_seat
        FOREIGN KEY (seat_id)     REFERENCES seats(seat_id)  ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT fk_booking_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES users(user_id)  ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Student hall seat booking requests';

-- ================================================================
-- TABLE: roommate_matches
-- Computed roommate compatibility scores between students
-- ================================================================
CREATE TABLE roommate_matches (
    match_id           INT            NOT NULL AUTO_INCREMENT,
    student_id         INT            NOT NULL,
    matched_student_id INT            NOT NULL,
    match_score        DECIMAL(5,2)   NOT NULL DEFAULT 0.00,
    dept_match         TINYINT(1)     NOT NULL DEFAULT 0,
    budget_match       TINYINT(1)     NOT NULL DEFAULT 0,
    pref_overlap       INT            NOT NULL DEFAULT 0,
    match_reason       TEXT           DEFAULT NULL,
    match_status       ENUM('SUGGESTED','ACCEPTED','DECLINED') NOT NULL DEFAULT 'SUGGESTED',
    matched_at         TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (match_id),
    UNIQUE KEY uq_match_pair (student_id, matched_student_id),
    CONSTRAINT fk_match_student
        FOREIGN KEY (student_id)         REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_match_partner
        FOREIGN KEY (matched_student_id) REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Roommate compatibility scores generated by matching engine';

-- ================================================================
-- TABLE: notifications
-- In-app notifications sent to users
-- ================================================================
CREATE TABLE notifications (
    notification_id INT            NOT NULL AUTO_INCREMENT,
    user_id         INT            NOT NULL,
    title           VARCHAR(200)   NOT NULL,
    message         TEXT           NOT NULL,
    notif_type      ENUM('BOOKING','MATCH','SYSTEM','ALERT') NOT NULL DEFAULT 'SYSTEM',
    is_read         TINYINT(1)     NOT NULL DEFAULT 0,
    created_at      TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (notification_id),
    CONSTRAINT fk_notif_user
        FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='In-app notification messages for users';

-- ================================================================
-- PERFORMANCE INDEXES
-- ================================================================
CREATE INDEX idx_users_role        ON users(role);
CREATE INDEX idx_users_status      ON users(account_status);
CREATE INDEX idx_users_dept        ON users(department);
CREATE INDEX idx_halls_status      ON halls(hall_status);
CREATE INDEX idx_halls_manager     ON halls(managed_by);
CREATE INDEX idx_rooms_hall        ON rooms(hall_id);
CREATE INDEX idx_rooms_status      ON rooms(room_status);
CREATE INDEX idx_seats_room        ON seats(room_id);
CREATE INDEX idx_seats_status      ON seats(seat_status);
CREATE INDEX idx_seats_student     ON seats(current_student_id);
CREATE INDEX idx_bookings_student  ON bookings(student_id);
CREATE INDEX idx_bookings_status   ON bookings(booking_status);
CREATE INDEX idx_bookings_hall     ON bookings(hall_id);
CREATE INDEX idx_bookings_date     ON bookings(requested_at);
CREATE INDEX idx_matches_student   ON roommate_matches(student_id);
CREATE INDEX idx_matches_score     ON roommate_matches(match_score);
CREATE INDEX idx_notif_user        ON notifications(user_id);
CREATE INDEX idx_notif_read        ON notifications(is_read);

SET FOREIGN_KEY_CHECKS = 1;

-- ================================================================
-- DONE: Run sql/02_views.sql next
-- Then: sql/03_procedures.sql
-- Then: sql/04_triggers.sql
-- Finally: Visit http://localhost/NestSync/seed.php to load data
-- ================================================================