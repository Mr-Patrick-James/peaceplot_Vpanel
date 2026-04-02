# PeacePlot Setup Guide

## Quick Start Instructions

### Step 1: Enable SQLite in WAMP

1. Click the **WAMP icon** in your system tray (should be green)
2. Navigate to: **PHP** → **PHP Extensions**
3. Make sure these are **checked** (enabled):
   - ☑ `php_pdo_sqlite`
   - ☑ `php_sqlite3`
4. If you made changes, **restart WAMP** (click WAMP icon → Restart All Services)

### Step 2: Initialize the Database

Open your browser and go to:
```
http://localhost/peaceplot/database/web_init.php
```

This will:
- ✓ Check SQLite support
- ✓ Create the database file (`peaceplot.db`)
- ✓ Create all tables (cemetery_lots, deceased_records, etc.)
- ✓ Insert sample data

### Step 3: Access the Application

Once initialization is complete, access these pages:

**Main Application Pages:**
- Dashboard: `http://localhost/peaceplot/public/dashboard.html`
- Cemetery Lot Management: `http://localhost/peaceplot/public/index.html`
- Lot Availability: `http://localhost/peaceplot/public/lot-availability.html`
- Cemetery Map: `http://localhost/peaceplot/public/cemetery-map.html`
- Burial Records: `http://localhost/peaceplot/public/burial-records.html`
- Reports: `http://localhost/peaceplot/public/reports.html`

## CRUD Operations Available

### Cemetery Lot Management (index.html)

**Features:**
- ✅ **View All Lots** - Automatically loads from database
- ✅ **Add New Lot** - Click "Add New Cemetery Lot" button
- ✅ **Edit Lot** - Click "Edit" button on any row
- ✅ **Delete Lot** - Click "Delete" button on any row

**Form Fields:**
- Lot Number (required)
- Section (required)
- Block
- Position
- Status: Vacant, Occupied, Maintenance (required)
- Size (sqm)
- Price

## Testing CRUD Operations

1. Go to: `http://localhost/peaceplot/public/index.html`
2. You should see 8 sample lots loaded from the database
3. Try these actions:
   - **Add**: Click "Add New Cemetery Lot" → Fill form → Click "Create"
   - **Edit**: Click "Edit" on any lot → Modify data → Click "Update"
   - **Delete**: Click "Delete" on any lot → Confirm deletion

## Troubleshooting

### "Failed to load cemetery lots"
- Check if database is initialized (run web_init.php)
- Check browser console (F12) for errors
- Verify SQLite extensions are enabled

### "Database connection failed"
- SQLite extensions not enabled in WAMP
- Follow Step 1 to enable extensions
- Restart WAMP server

### Modal not appearing
- Check browser console for JavaScript errors
- Clear browser cache (Ctrl+F5)

## Database Location

The SQLite database file is located at:
```
c:\wamp64\www\peaceplot\database\peaceplot.db
```

## API Endpoints

All CRUD operations use the REST API:

**Endpoint:** `/peaceplot/api/cemetery_lots.php`

- `GET` - Fetch all lots or single lot (with ?id=X)
- `POST` - Create new lot
- `PUT` - Update existing lot
- `DELETE` - Delete lot

## Default Credentials

- **Username:** admin
- **Password:** admin123
- ⚠️ Change after first login (when authentication is implemented)

## Next Development Steps

- [ ] Implement authentication system
- [ ] Add search and filtering
- [ ] Create APIs for other modules (burial records, reservations)
- [ ] Add data validation
- [ ] Implement file uploads
- [ ] Create backup/restore functionality
