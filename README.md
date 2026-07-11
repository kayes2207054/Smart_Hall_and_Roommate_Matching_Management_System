# NestSync — Smart Hall & Roommate Matching Management System

## 📖 Project Overview
NestSync is a comprehensive and intelligent web-based management system designed to streamline university hall administration, student accommodation bookings, and roommate matching. By leveraging a sophisticated Oracle Database backend and a modern PHP-driven frontend, NestSync eliminates manual paperwork and introduces automated, data-driven roommate pairing based on student preferences, budget, and academic department.

## ✨ Key Features
- **Smart Roommate Matching Engine:** Automatically calculates compatibility scores between students to suggest the best roommate pairs based on lifestyle preferences, budget, and department.
- **Real-time Seat Booking & Allocation:** Students can browse available halls, view room types/facilities, and book specific seats seamlessly.
- **Multi-Level Dashboards:** Dedicated portal interfaces for Students, Hall Admins, and System Admins, displaying real-time statistics and tailored widgets.
- **Advanced Notification System:** Automated, real-time alerts for booking status changes and roommate match discoveries.
- **Occupancy Analytics & Reporting:** Comprehensive visual analytics and CSV report generation for hall occupancy and booking trends.
- **Robust Security:** Form validation, CSRF protection, secure password hashing, and role-based access control.

## 🛠️ Technology Stack
- **Frontend:** HTML5, Vanilla CSS3 (Custom Design System), JavaScript (ES6+), Bootstrap 5 (Layout & Components), FontAwesome 6 (Icons), Google Fonts (Inter)
- **Backend:** Core PHP (PHP 8.x recommended)
- **Database:** Oracle SQL & PL/SQL (accessed via OCI8 extension)
- **Architecture:** Procedural/Modular PHP Architecture

## 🗄️ Database Design Summary
The Oracle database utilizes a robust relational schema supported by advanced PL/SQL features:
- **Core Entities:** `USERS`, `HALLS`, `ROOMS`, `SEATS`, `BOOKINGS`, `ROOMMATE_MATCHES`, `NOTIFICATIONS`
- **PL/SQL Procedures:** Used for complex transactional operations, such as the `MATCH_ROOMMATES` engine.
- **Database Views:** Dedicated views (e.g., `vw_dashboard_stats`, `vw_hall_occupancy`, `vw_booking_summary`) aggregate complex queries for high-performance dashboard rendering.
- **Database Triggers:** Automated triggers handle timestamps, seat availability tracking, and audit logging.

## 👥 User Roles

### 🎓 Student
- Update profile and lifestyle preferences.
- Browse halls, rooms, and available seats.
- Submit seat booking requests.
- Track booking history and status.
- View AI-generated roommate matches based on compatibility.
- Receive system notifications regarding bookings.

### 🏢 Hall Admin
- Manage and monitor assigned halls.
- Approve or reject student booking requests.
- Manage rooms and seat statuses (e.g., set seats for maintenance).
- View hall-specific occupancy reports.

### ⚙️ System Admin
- Full access to system-wide dashboards and analytics.
- Manage all users (Students, Hall Admins).
- Create and manage university halls globally.
- Export occupancy and system reports to CSV.

## 📂 Project Structure
```text
NestSync/
├── assets/images/        # Static images (logos, placeholders)
├── config/               # Database connection and environment variables
│   └── db.php            # Oracle OCI8 connection script
├── docs/                 # Project documentation and schema references
├── includes/             # Reusable PHP templates (Header, Footer, Sidebar, Navbar)
├── pages/                # Main application views
│   ├── admin/            # Dashboards & views for Hall/System Admins
│   ├── auth/             # Registration logic
│   └── student/          # Dashboards & views for Students
├── public/               # Public-facing assets
│   ├── css/style.css     # Master custom stylesheet
│   └── js/main.js        # Global JavaScript interactions
├── sql/                  # Oracle SQL scripts (Schema, Views, PL/SQL, Seed Data)
├── index.php             # Landing Page
├── login.php             # Unified login portal
├── logout.php            # Session termination
└── seed.php              # Automated database seeder interface
```

## 🚀 Installation Guide

### Prerequisites
1. **XAMPP / WAMP** installed with PHP 8.0+.
2. **Oracle Database** (Express Edition 11g/19c/21c or higher).
3. **OCI8 PHP Extension** enabled in `php.ini`.
   - Uncomment `extension=oci8_12c` or `extension=oci8_19` (depending on your Oracle Client version).

### Setup Steps
1. **Clone/Extract the Project:**
   Place the `NestSync` folder inside your web server directory (e.g., `C:\xampp\htdocs\NestSync`).
2. **Database Initialization:**
   Log into your Oracle SQL interface (SQL*Plus, SQL Developer, or Oracle APEX) and execute the SQL scripts in the following order:
   - `sql/01_core_schema.sql` (Creates tables, sequences)
   - `sql/02_views.sql` (Creates dashboard views)
   - `sql/03_procedures.sql` (Creates matching engine and logic procedures)
   - `sql/04_triggers.sql` (Creates automated database triggers)
   - `sql/05_sample_data.sql` (Seeds initial admin/student accounts and halls)
3. **Configure Database Connection:**
   Open `config/db.php` and update the Oracle credentials:
   ```php
   define('DB_USER', 'your_oracle_username');
   define('DB_PASS', 'your_oracle_password');
   define('DB_CONN_STR', 'localhost/XEPDB1'); // Update host/service name if necessary
   ```
4. **Run the Application:**
   Open your browser and navigate to `http://localhost/NestSync`.
   - **System Admin Login:** `admin@nestsync.com` / `password`
   - **Student Login:** `student@nestsync.com` / `password`

## 📸 Screenshots

> *(Placeholder for UI Screenshots — Add actual image links before final submission)*

### 1. Landing Page
![Landing Page Screenshot](assets/images/placeholder.png)

### 2. Student Dashboard
![Student Dashboard Screenshot](assets/images/placeholder.png)

### 3. Roommate Matching Interface
![Roommate Matching Screenshot](assets/images/placeholder.png)

### 4. Admin Analytics & Occupancy
![Admin Analytics Screenshot](assets/images/placeholder.png)

## 🔮 Future Improvements
- **Payment Gateway Integration:** Direct online payment for monthly rent and admission fees.
- **In-App Messaging:** Allow matched roommates to chat securely within the platform before moving in.
- **Maintenance Ticketing System:** Students can report room issues directly to Hall Admins.
- **Mobile Application:** A dedicated Android/iOS version of NestSync.

## 👥 Team Members
- **[Team Member Name 1]** — [Role / ID]
- **[Team Member Name 2]** — [Role / ID]
- **[Team Member Name 3]** — [Role / ID]

---
*Developed as a Database Management Systems Project.*
