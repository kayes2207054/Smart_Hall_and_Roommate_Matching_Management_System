# NestSync - Step 1 Project Structure

This is a beginner-friendly folder structure for a Core PHP + Oracle project.

## Recommended Folder Tree

```text
Smart Hall & Roommate Matching Management System/
|-- README.md
|-- docs/
|   |-- scope.md
|   `-- step-01-project-structure.md
|-- sql/
|   |-- 01_core_schema.sql
|   |-- 02_roommate_matching.sql   (used as sample data in Step 1)
|   `-- 03_booking_workflow.sql    (placeholder for later steps)
|-- public/
|   |-- css/
|   `-- js/
|-- pages/
|   |-- auth/
|   |-- student/
|   `-- admin/
|-- config/
|-- includes/
`-- assets/
    `-- images/
```

## What Each Folder Will Be Used For

- `public/css`: global stylesheet files
- `public/js`: vanilla JavaScript files
- `pages/auth`: login and registration pages
- `pages/student`: student dashboard and student features
- `pages/admin`: hall admin and system admin pages
- `config`: Oracle database connection file and app-level settings
- `includes`: reusable PHP files (header, footer, auth checks, helper functions)
- `assets/images`: logos and static images

## Step 1 Database Files

- `sql/01_core_schema.sql`: creates required tables with constraints and indexes
- `sql/02_roommate_matching.sql`: inserts sample data for testing
- `sql/03_booking_workflow.sql`: intentionally empty placeholder for future PL/SQL work
