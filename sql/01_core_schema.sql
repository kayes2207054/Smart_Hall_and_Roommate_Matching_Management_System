CREATE DATABASE IF NOT EXISTS nestsync;
USE nestsync;

-- USERS
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_name VARCHAR(20) NOT NULL,
    department VARCHAR(80),
    monthly_budget DECIMAL(10,2),
    preferences_text VARCHAR(300),
    account_status VARCHAR(20) DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- HALLS
CREATE TABLE halls (
    hall_id INT AUTO_INCREMENT PRIMARY KEY,
    hall_name VARCHAR(120) NOT NULL UNIQUE,
    hall_location VARCHAR(180) NOT NULL,
    total_capacity INT DEFAULT 0,
    managed_by_admin INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ROOMS
CREATE TABLE rooms (
    room_id INT AUTO_INCREMENT PRIMARY KEY,
    hall_id INT,
    room_number VARCHAR(30),
    room_type VARCHAR(30) DEFAULT 'STANDARD',
    monthly_rent DECIMAL(10,2) DEFAULT 0,
    room_status VARCHAR(20) DEFAULT 'AVAILABLE'
);

-- SEATS
CREATE TABLE seats (
    seat_id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT,
    seat_label VARCHAR(20),
    seat_status VARCHAR(20) DEFAULT 'AVAILABLE',
    current_student_id INT
);

-- BOOKINGS
CREATE TABLE bookings (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    hall_id INT,
    room_id INT,
    seat_id INT,
    booking_status VARCHAR(20) DEFAULT 'PENDING',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT,
    reviewed_at TIMESTAMP NULL,
    notes VARCHAR(300)
);

-- ROOMMATE MATCHES
CREATE TABLE roommate_matches (
    match_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT,
    matched_student_id INT,
    match_score DECIMAL(5,2),
    match_reason VARCHAR(300),
    matched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- INDEXES
CREATE INDEX idx_users_role ON users(role_name);
CREATE INDEX idx_rooms_hall ON rooms(hall_id);
CREATE INDEX idx_seats_room_status ON seats(room_id, seat_status);
CREATE INDEX idx_bookings_status ON bookings(booking_status);