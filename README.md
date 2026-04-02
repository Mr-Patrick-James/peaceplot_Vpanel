# PeacePlot Cemetery Management System

A web-based cemetery management system for tracking burial records, cemetery lots, and related administrative tasks.

## Tech Stack

- PHP (backend / API)
- SQLite (database via PDO)
- Vanilla JavaScript (frontend)
- CSS (custom styles)

## Features

- Cemetery lot management with map coordinates and layer support
- Burial / deceased records with image attachments
- Block and section organization
- Universal search across lots and burial records
- CSV export
- Activity logging
- Role-based user access (admin, staff, viewer)
- Archive support for records and logs

## Project Structure

```
peaceplot/
├── api/                  # REST API endpoints
│   ├── burial_records.php
│   ├── cemetery_lots.php
│   ├── sections.php
│   ├── blocks.php
│   ├── burial_images.php
│   ├── universal_search.php
│   ├── export_csv.php
│   └── ...
├── assets/
│   ├── css/styles.css
│   ├── js/               # Frontend JS modules
│   └── images/           # Uploaded burial images
├── config/
│   ├── database.php      # SQLite PDO connection
│   ├── auth.php
│   └── logger.php
├── database/
│   ├── schema.sql        # DB schema
│   ├── init.php          # DB initializer
│   └── peaceplot.db      # SQLite database file
└── add_burial_record.php
```

## Getting Started

### Requirements

- PHP 7.4+
- SQLite3 extension enabled
- A local web server (e.g. XAMPP, Laragon, or PHP built-in server)

### Setup

1. Clone the repository:
   ```bash
   git clone <repo-url>
   cd peaceplot
   ```

2. Initialize the database:
   ```
   http://localhost/peaceplot/database/init.php
   ```
   Or via CLI:
   ```bash
   php database/init.php
   ```

3. Start your local server and open the app in your browser:
   ```
   http://localhost/peaceplot/
   ```

## Database Schema

| Table               | Description                          |
|---------------------|--------------------------------------|
| `blocks`            | Top-level cemetery block groupings   |
| `sections`          | Sections within blocks               |
| `cemetery_lots`     | Individual lots with map coordinates |
| `deceased_records`  | Burial / deceased person records     |
| `payments`          | Lot payment records                  |
| `maintenance_records` | Lot maintenance history            |
| `users`             | Admin/staff user accounts            |
| `activity_logs`     | Audit trail of system actions        |

## User Roles

| Role    | Access Level          |
|---------|-----------------------|
| admin   | Full access           |
| staff   | Read + write records  |
| viewer  | Read-only             |

## License

For internal/institutional use.
