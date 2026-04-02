# PeacePlot - Cemetery Management System

A comprehensive web-based cemetery management system for tracking cemetery lots, burial records, reservations, and maintenance with a visual map editor.

## Project Structure

```
peaceplot/
├── api/                    # API endpoints for CRUD operations
│   ├── burial_images.php   # Handle burial photo uploads/retrieval
│   ├── burial_records.php  # CRUD for deceased records
│   ├── cemetery_lots.php   # CRUD for lot management
│   ├── lot_layers.php      # Multi-layer burial management
│   └── save_map_coordinates.php # Save lot positions on map
├── assets/                 # Static assets
│   ├── css/               # Stylesheets (styles.css)
│   ├── js/                # JavaScript files (api.js, app.js)
│   └── images/            # Map and burial images
├── config/                # Configuration files
│   ├── auth.php           # Session and login management
│   └── database.php       # Database connection class
├── database/              # Database files and scripts
│   ├── schema.sql         # Database schema
│   ├── seed.sql           # Sample data
│   ├── init.php           # Database initialization script
│   └── peaceplot.db       # SQLite database (generated)
└── public/                # Public PHP pages
    ├── index.php          # Lot Management dashboard
    ├── dashboard.php      # Main analytics dashboard
    ├── lot-availability.php # Lot status overview
    ├── cemetery-map.php   # Interactive visual map viewer
    ├── map-editor.php     # Visual lot coordinate editor
    ├── burial-records.php # Burial record management
    ├── history.php        # Activity history log
    └── reports.php        # System reports
```

## Features

- **Interactive Cemetery Map**: Visual representation of the cemetery layout with real-time status indicators (Vacant, Occupied, Maintenance).
- **Advanced Map Editor**: Draw and assign coordinates to lots directly on the map image.
- **Accurate Pinning & Centering**: High-precision coordinate system that ensures markers align perfectly across all zoom levels and screen sizes.
- **World-Space Centering**: Automatically focuses and zooms into specific lots when highlighted from other pages.
- **Multi-Layer Burials**: Manage multiple burials within a single lot using a layer-based system.
- **Burial Image Gallery**: Upload and view multiple photos per burial record with a modern lightbox viewer.
- **Dashboard Analytics**: Real-time overview of lot availability, section summaries, and system statistics.
- **Audit Logging**: Comprehensive activity history to track all changes made to lots and records.

## Setup Instructions

### Prerequisites
- WAMP/XAMPP server (Apache + PHP)
- PHP 7.4 or higher
- SQLite3 extension enabled in PHP (`php.ini`)

### Installation

1. **Clone or copy the project** to your WAMP `www` directory:
   ```
   c:\wamp64\www\peaceplot\
   ```

2. **Initialize the Database**:
   - Open your browser and navigate to:
     ```
     http://localhost/peaceplot/database/init.php
     ```
   - This script will create the SQLite database and seed it with initial data.

3. **Access the Application**:
   - The main entry point is the dashboard:
     ```
     http://localhost/peaceplot/public/dashboard.php
     ```

### Default Login Credentials
- **Username**: admin
- **Password**: admin123
- ⚠️ **Security**: Change the default password in a production environment.

## Technology Stack

- **Backend**: PHP 7.4+ (Core logic and API)
- **Frontend**: HTML5, CSS3 (Modern Responsive UI), Vanilla JavaScript
- **Database**: SQLite3 (Serverless data persistence)
- **Map System**: Custom JavaScript-based canvas with multi-touch zoom and pan support.

## Development Status

- ✅ **Core Architecture**: Project structure and database schema finalized.
- ✅ **Lot Management**: Full CRUD functionality implemented.
- ✅ **Interactive Map**: High-precision pinning and world-space centering logic completed.
- ✅ **Map Editor**: Visual drawing tools for coordinate assignment.
- ✅ **Burial Records**: Deceased tracking with multi-layer support.
- ✅ **Media Management**: Burial photo uploads and gallery viewer.
- ✅ **Authentication**: Secure login system with role-based access.
- ✅ **Audit Logs**: Activity tracking for system transparency.

## Support

For support or custom feature requests, contact: admin@peaceplot.com
