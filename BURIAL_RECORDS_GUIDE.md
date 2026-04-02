# Burial Records - User Guide

## Access the Page
```
http://localhost/peaceplot/public/burial-records.php
```

## Features Available

### ✅ View Records
- See all burial records from database
- Shows: Full Name, Lot Number, Section, Dates, Age
- Real-time data from `deceased_records` table

### ✅ Add New Burial Record
**Click "Add New Burial Record" button**

**Form Fields:**
- **Full Name*** (required)
- **Cemetery Lot*** (required) - Dropdown shows available lots
- **Age** - Numeric field
- **Date of Birth** - Date picker
- **Date of Death** - Date picker
- **Date of Burial** - Date picker
- **Cause of Death** - Text field
- **Next of Kin** - Name of relative
- **Next of Kin Contact** - Phone/email
- **Remarks** - Additional notes

**What Happens:**
1. Click "Add New Burial Record"
2. Modal form opens
3. Fill in required fields (marked with *)
4. Click "Create"
5. Record saves to database
6. Selected lot automatically becomes "Occupied"
7. Table refreshes with new record

### ✅ View Details
**Click "View" button on any record**

Shows complete information:
- Personal Information (name, birth, death, age, cause)
- Burial Information (burial date, lot, section, block)
- Next of Kin details
- Remarks

### ✅ Edit Record
**Click "Edit" button on any record**

**What You Can Edit:**
- All fields from the add form
- Change assigned cemetery lot
- Update dates and personal information
- Modify next of kin details

**Process:**
1. Click "Edit" on a record
2. Modal opens with current data pre-filled
3. Modify any fields
4. Click "Update"
5. Changes save to database
6. Table refreshes automatically

### ✅ Delete Record
**Click "Delete" button on any record**

**What Happens:**
1. Confirmation dialog appears
2. If confirmed, record is deleted from database
3. If this was the last burial in that lot, the lot becomes "Vacant" again
4. Table refreshes

## Smart Features

### Automatic Lot Status Management
- **Add burial record** → Lot becomes "Occupied"
- **Delete last burial** → Lot becomes "Vacant"
- Only available lots shown in dropdown

### Data Validation
- Required fields must be filled
- Date fields use date picker
- Age must be numeric
- Lot must be selected from existing lots

### Real-Time Updates
- All changes save to SQLite database
- No page refresh needed
- Changes persist across sessions

## Sample Data

The database includes 4 sample burial records:
1. John Smith (Lot A-001)
2. Mary Johnson (Lot A-003)
3. Robert Williams (Lot B-001)
4. Patricia Brown (Lot B-003)

## Database Integration

**Table:** `deceased_records`

**Relationships:**
- Links to `cemetery_lots` table via `lot_id`
- Shows lot number, section, and block in table
- Maintains referential integrity

**API Endpoint:** `/api/burial_records.php`
- GET: Fetch records
- POST: Create new record
- PUT: Update record
- DELETE: Remove record

## Troubleshooting

### "No lots available in dropdown"
- All lots are occupied
- Add more lots in Cemetery Lot Management
- Or mark existing lots as Vacant

### "Failed to create record"
- Check all required fields are filled
- Ensure valid lot is selected
- Check database connection

### Changes not appearing
- Refresh the page
- Check browser console for errors
- Verify database is initialized

## Tips

1. **Always assign a lot** - Required for burial records
2. **Use View button** - See all details before editing
3. **Check dates** - Ensure burial date is after death date
4. **Add next of kin** - Important for contact purposes
5. **Use remarks** - Add any special notes or instructions
