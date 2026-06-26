-- ================================================================
-- NestSync: Smart Hall & Roommate Matching Management System
-- File        : sql/01_core_schema.sql
-- Database    : Oracle 19c+ (PL/SQL)
-- Description : Core DDL — sequences, tables, PK triggers, FKs,
--               CHECK constraints, and performance indexes.
-- IMPORTANT   : Run as the nestsync schema owner (or SYSTEM with
--               the correct ALTER SESSION schema set).
--               Run this file FIRST, before any other SQL file.
-- ================================================================

-- ----------------------------------------------------------------
-- CLEANUP: Drop everything in reverse dependency order
-- (Safe to run on a fresh schema — all DROP ... IF EXISTS
--  equivalents use the "purge" approach via a helper block.)
-- ----------------------------------------------------------------
BEGIN
    -- Drop tables (cascades FKs)
    FOR t IN (SELECT table_name FROM user_tables
              WHERE table_name IN (
                  'NOTIFICATIONS','ROOMMATE_MATCHES',
                  'BOOKINGS','SEATS','ROOMS','HALLS','USERS'
              )
              ORDER BY DECODE(table_name,
                  'NOTIFICATIONS',1,'ROOMMATE_MATCHES',2,
                  'BOOKINGS',3,'SEATS',4,'ROOMS',5,
                  'HALLS',6,'USERS',7)) LOOP
        EXECUTE IMMEDIATE 'DROP TABLE ' || t.table_name || ' CASCADE CONSTRAINTS PURGE';
    END LOOP;

    -- Drop sequences
    FOR s IN (SELECT sequence_name FROM user_sequences
              WHERE sequence_name IN (
                  'SEQ_USERS','SEQ_HALLS','SEQ_ROOMS',
                  'SEQ_SEATS','SEQ_BOOKINGS',
                  'SEQ_ROOMMATE_MATCHES','SEQ_NOTIFICATIONS'
              )) LOOP
        EXECUTE IMMEDIATE 'DROP SEQUENCE ' || s.sequence_name;
    END LOOP;
END;
/


-- ================================================================
-- SEQUENCES (Oracle equivalent of AUTO_INCREMENT)
-- ================================================================
CREATE SEQUENCE seq_users            START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_halls            START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_rooms            START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_seats            START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_bookings         START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_roommate_matches START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;
CREATE SEQUENCE seq_notifications    START WITH 1 INCREMENT BY 1 NOCACHE NOCYCLE;


-- ================================================================
-- TABLE: USERS
-- Stores all platform users: System Admins, Hall Admins, Students
-- ================================================================
CREATE TABLE users (
    user_id        NUMBER          NOT NULL,
    full_name      VARCHAR2(120)   NOT NULL,
    email          VARCHAR2(150)   NOT NULL,
    password_hash  VARCHAR2(255)   NOT NULL,
    role_name      VARCHAR2(20)    NOT NULL,
    department     VARCHAR2(80)    DEFAULT NULL,
    phone          VARCHAR2(20)    DEFAULT NULL,
    student_id_no  VARCHAR2(30)    DEFAULT NULL,
    monthly_budget NUMBER(10,2)    DEFAULT 0 NOT NULL,
    preferences    VARCHAR2(500)   DEFAULT NULL,
    gender         VARCHAR2(10)    DEFAULT NULL,
    account_status VARCHAR2(15)    DEFAULT 'ACTIVE' NOT NULL,
    created_at     TIMESTAMP       DEFAULT SYSTIMESTAMP NOT NULL,
    updated_at     TIMESTAMP       DEFAULT SYSTIMESTAMP NOT NULL,
    --
    CONSTRAINT pk_users          PRIMARY KEY (user_id),
    CONSTRAINT uq_users_email    UNIQUE      (email),
    CONSTRAINT uq_users_studid   UNIQUE      (student_id_no),
    CONSTRAINT chk_users_role    CHECK (role_name IN ('SYSTEM_ADMIN','HALL_ADMIN','STUDENT')),
    CONSTRAINT chk_users_gender  CHECK (gender   IN ('MALE','FEMALE','OTHER') OR gender IS NULL),
    CONSTRAINT chk_users_status  CHECK (account_status IN ('ACTIVE','INACTIVE','SUSPENDED')),
    CONSTRAINT chk_users_budget  CHECK (monthly_budget >= 0)
);

-- Auto-increment trigger
CREATE OR REPLACE TRIGGER trg_users_bi
BEFORE INSERT ON users
FOR EACH ROW
BEGIN
    IF :NEW.user_id IS NULL THEN
        :NEW.user_id := seq_users.NEXTVAL;
    END IF;
    :NEW.updated_at := SYSTIMESTAMP;
END;
/

CREATE OR REPLACE TRIGGER trg_users_bu
BEFORE UPDATE ON users
FOR EACH ROW
BEGIN
    :NEW.updated_at := SYSTIMESTAMP;
END;
/


-- ================================================================
-- TABLE: HALLS
-- Residential halls managed by hall admins
-- ================================================================
CREATE TABLE halls (
    hall_id        NUMBER          NOT NULL,
    hall_name      VARCHAR2(120)   NOT NULL,
    hall_location  VARCHAR2(180)   NOT NULL,
    total_capacity NUMBER          DEFAULT 0 NOT NULL,
    description    CLOB            DEFAULT NULL,
    gender_type    VARCHAR2(10)    DEFAULT 'MIXED' NOT NULL,
    managed_by     NUMBER          DEFAULT NULL,
    hall_status    VARCHAR2(15)    DEFAULT 'ACTIVE' NOT NULL,
    created_at     TIMESTAMP       DEFAULT SYSTIMESTAMP NOT NULL,
    updated_at     TIMESTAMP       DEFAULT SYSTIMESTAMP NOT NULL,
    --
    CONSTRAINT pk_halls            PRIMARY KEY (hall_id),
    CONSTRAINT uq_halls_name       UNIQUE      (hall_name),
    CONSTRAINT fk_hall_admin       FOREIGN KEY (managed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT chk_halls_gender    CHECK (gender_type IN ('MALE','FEMALE','MIXED')),
    CONSTRAINT chk_halls_status    CHECK (hall_status  IN ('ACTIVE','INACTIVE','MAINTENANCE')),
    CONSTRAINT chk_halls_capacity  CHECK (total_capacity >= 0)
);

CREATE OR REPLACE TRIGGER trg_halls_bi
BEFORE INSERT ON halls
FOR EACH ROW
BEGIN
    IF :NEW.hall_id IS NULL THEN
        :NEW.hall_id := seq_halls.NEXTVAL;
    END IF;
    :NEW.updated_at := SYSTIMESTAMP;
END;
/

CREATE OR REPLACE TRIGGER trg_halls_bu
BEFORE UPDATE ON halls
FOR EACH ROW
BEGIN
    :NEW.updated_at := SYSTIMESTAMP;
END;
/


-- ================================================================
-- TABLE: ROOMS
-- Individual rooms inside halls
-- ================================================================
CREATE TABLE rooms (
    room_id       NUMBER          NOT NULL,
    hall_id       NUMBER          NOT NULL,
    room_number   VARCHAR2(30)    NOT NULL,
    room_type     VARCHAR2(10)    DEFAULT 'DOUBLE' NOT NULL,
    capacity      NUMBER          DEFAULT 2 NOT NULL,
    floor_number  NUMBER          DEFAULT 1 NOT NULL,
    monthly_rent  NUMBER(10,2)    DEFAULT 0 NOT NULL,
    facilities    VARCHAR2(300)   DEFAULT NULL,
    room_status   VARCHAR2(15)    DEFAULT 'AVAILABLE' NOT NULL,
    created_at    TIMESTAMP       DEFAULT SYSTIMESTAMP NOT NULL,
    updated_at    TIMESTAMP       DEFAULT SYSTIMESTAMP NOT NULL,
    --
    CONSTRAINT pk_rooms           PRIMARY KEY (room_id),
    CONSTRAINT uq_room_in_hall    UNIQUE      (hall_id, room_number),
    CONSTRAINT fk_room_hall       FOREIGN KEY (hall_id) REFERENCES halls(hall_id) ON DELETE CASCADE,
    CONSTRAINT chk_rooms_type     CHECK (room_type   IN ('SINGLE','DOUBLE','TRIPLE','QUAD')),
    CONSTRAINT chk_rooms_status   CHECK (room_status IN ('AVAILABLE','FULL','MAINTENANCE','INACTIVE')),
    CONSTRAINT chk_rooms_capacity CHECK (capacity    >= 1),
    CONSTRAINT chk_rooms_rent     CHECK (monthly_rent >= 0)
);

CREATE OR REPLACE TRIGGER trg_rooms_bi
BEFORE INSERT ON rooms
FOR EACH ROW
BEGIN
    IF :NEW.room_id IS NULL THEN
        :NEW.room_id := seq_rooms.NEXTVAL;
    END IF;
    :NEW.updated_at := SYSTIMESTAMP;
END;
/

CREATE OR REPLACE TRIGGER trg_rooms_bu
BEFORE UPDATE ON rooms
FOR EACH ROW
BEGIN
    :NEW.updated_at := SYSTIMESTAMP;
END;
/


-- ================================================================
-- TABLE: SEATS
-- Individual bed/seat within a room
-- ================================================================
CREATE TABLE seats (
    seat_id            NUMBER          NOT NULL,
    room_id            NUMBER          NOT NULL,
    seat_label         VARCHAR2(20)    NOT NULL,
    seat_status        VARCHAR2(15)    DEFAULT 'AVAILABLE' NOT NULL,
    current_student_id NUMBER          DEFAULT NULL,
    created_at         TIMESTAMP       DEFAULT SYSTIMESTAMP NOT NULL,
    updated_at         TIMESTAMP       DEFAULT SYSTIMESTAMP NOT NULL,
    --
    CONSTRAINT pk_seats           PRIMARY KEY (seat_id),
    CONSTRAINT uq_seat_in_room    UNIQUE      (room_id, seat_label),
    CONSTRAINT fk_seat_room       FOREIGN KEY (room_id)            REFERENCES rooms(room_id)  ON DELETE CASCADE,
    CONSTRAINT fk_seat_student    FOREIGN KEY (current_student_id) REFERENCES users(user_id)  ON DELETE SET NULL,
    CONSTRAINT chk_seats_status   CHECK (seat_status IN ('AVAILABLE','BOOKED','RESERVED','MAINTENANCE'))
);

CREATE OR REPLACE TRIGGER trg_seats_bi
BEFORE INSERT ON seats
FOR EACH ROW
BEGIN
    IF :NEW.seat_id IS NULL THEN
        :NEW.seat_id := seq_seats.NEXTVAL;
    END IF;
    :NEW.updated_at := SYSTIMESTAMP;
END;
/

CREATE OR REPLACE TRIGGER trg_seats_bu
BEFORE UPDATE ON seats
FOR EACH ROW
BEGIN
    :NEW.updated_at := SYSTIMESTAMP;
END;
/


-- ================================================================
-- TABLE: BOOKINGS
-- Hall seat booking requests from students
-- ================================================================
CREATE TABLE bookings (
    booking_id      NUMBER          NOT NULL,
    student_id      NUMBER          NOT NULL,
    hall_id         NUMBER          NOT NULL,
    room_id         NUMBER          NOT NULL,
    seat_id         NUMBER          NOT NULL,
    booking_status  VARCHAR2(15)    DEFAULT 'PENDING' NOT NULL,
    semester        VARCHAR2(30)    DEFAULT NULL,
    requested_at    TIMESTAMP       DEFAULT SYSTIMESTAMP NOT NULL,
    reviewed_by     NUMBER          DEFAULT NULL,
    reviewed_at     TIMESTAMP       DEFAULT NULL,
    notes           CLOB            DEFAULT NULL,
    admin_remarks   CLOB            DEFAULT NULL,
    --
    CONSTRAINT pk_bookings          PRIMARY KEY (booking_id),
    CONSTRAINT fk_booking_student   FOREIGN KEY (student_id)  REFERENCES users(user_id)  ON DELETE CASCADE,
    CONSTRAINT fk_booking_hall      FOREIGN KEY (hall_id)     REFERENCES halls(hall_id)  ON DELETE CASCADE,
    CONSTRAINT fk_booking_room      FOREIGN KEY (room_id)     REFERENCES rooms(room_id)  ON DELETE CASCADE,
    CONSTRAINT fk_booking_seat      FOREIGN KEY (seat_id)     REFERENCES seats(seat_id)  ON DELETE CASCADE,
    CONSTRAINT fk_booking_reviewer  FOREIGN KEY (reviewed_by) REFERENCES users(user_id)  ON DELETE SET NULL,
    CONSTRAINT chk_bookings_status  CHECK (booking_status IN ('PENDING','APPROVED','REJECTED','CANCELLED'))
);

CREATE OR REPLACE TRIGGER trg_bookings_bi
BEFORE INSERT ON bookings
FOR EACH ROW
BEGIN
    IF :NEW.booking_id IS NULL THEN
        :NEW.booking_id := seq_bookings.NEXTVAL;
    END IF;
END;
/


-- ================================================================
-- TABLE: ROOMMATE_MATCHES
-- Compatibility scores between student pairs
-- ================================================================
CREATE TABLE roommate_matches (
    match_id           NUMBER          NOT NULL,
    student_id         NUMBER          NOT NULL,
    matched_student_id NUMBER          NOT NULL,
    match_score        NUMBER(5,2)     DEFAULT 0 NOT NULL,
    dept_match         NUMBER(1)       DEFAULT 0 NOT NULL,
    budget_match       NUMBER(1)       DEFAULT 0 NOT NULL,
    pref_overlap       NUMBER          DEFAULT 0 NOT NULL,
    match_reason       VARCHAR2(1000)  DEFAULT NULL,
    match_status       VARCHAR2(15)    DEFAULT 'SUGGESTED' NOT NULL,
    matched_at         TIMESTAMP       DEFAULT SYSTIMESTAMP NOT NULL,
    --
    CONSTRAINT pk_matches          PRIMARY KEY (match_id),
    CONSTRAINT uq_match_pair       UNIQUE      (student_id, matched_student_id),
    CONSTRAINT fk_match_student    FOREIGN KEY (student_id)         REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_match_partner    FOREIGN KEY (matched_student_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT chk_match_status    CHECK (match_status IN ('SUGGESTED','ACCEPTED','DECLINED')),
    CONSTRAINT chk_dept_match      CHECK (dept_match   IN (0,1)),
    CONSTRAINT chk_budget_match    CHECK (budget_match IN (0,1)),
    CONSTRAINT chk_match_score     CHECK (match_score  BETWEEN 0 AND 100)
);

CREATE OR REPLACE TRIGGER trg_matches_bi
BEFORE INSERT ON roommate_matches
FOR EACH ROW
BEGIN
    IF :NEW.match_id IS NULL THEN
        :NEW.match_id := seq_roommate_matches.NEXTVAL;
    END IF;
END;
/


-- ================================================================
-- TABLE: NOTIFICATIONS
-- In-app notifications for users
-- ================================================================
CREATE TABLE notifications (
    notification_id NUMBER          NOT NULL,
    user_id         NUMBER          NOT NULL,
    title           VARCHAR2(200)   NOT NULL,
    message         CLOB            NOT NULL,
    notif_type      VARCHAR2(15)    DEFAULT 'SYSTEM' NOT NULL,
    is_read         NUMBER(1)       DEFAULT 0 NOT NULL,
    created_at      TIMESTAMP       DEFAULT SYSTIMESTAMP NOT NULL,
    --
    CONSTRAINT pk_notifications    PRIMARY KEY (notification_id),
    CONSTRAINT fk_notif_user       FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT chk_notif_type      CHECK (notif_type IN ('BOOKING','MATCH','SYSTEM','ALERT')),
    CONSTRAINT chk_notif_read      CHECK (is_read IN (0,1))
);

CREATE OR REPLACE TRIGGER trg_notifications_bi
BEFORE INSERT ON notifications
FOR EACH ROW
BEGIN
    IF :NEW.notification_id IS NULL THEN
        :NEW.notification_id := seq_notifications.NEXTVAL;
    END IF;
END;
/


-- ================================================================
-- PERFORMANCE INDEXES
-- ================================================================
CREATE INDEX idx_users_role        ON users(role_name);
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

COMMIT;

-- ================================================================
-- NEXT STEP: Run sql/02_views.sql
-- ================================================================