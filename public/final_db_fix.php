<?php
/**
 * Final Database Structure Fix
 * Run this in your browser: http://localhost/peaceplot/public/final_db_fix.php
 */

require_once __DIR__ . '/../config/database.php';

echo "<h1>Finalizing PeacePlot Database Structure</h1>";

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("<p style='color:red'>✗ Database connection failed.</p>");
}

try {
    echo "<h3>Fixing NOT NULL Constraints...</h3>";

    // SQLite doesn't support ALTER TABLE DROP COLUMN or ALTER COLUMN
    // We must create a new table and migrate the data
    
    // 1. Check if section_id exists first
    $columns = $db->query("PRAGMA table_info(cemetery_lots)")->fetchAll(PDO::FETCH_ASSOC);
    $hasSectionId = false;
    foreach ($columns as $column) {
        if ($column['name'] === 'section_id') {
            $hasSectionId = true;
            break;
        }
    }

    if (!$hasSectionId) {
        echo "<p>Adding missing <b>section_id</b> column...</p>";
        $db->exec("ALTER TABLE cemetery_lots ADD COLUMN section_id INTEGER REFERENCES sections(id) ON DELETE SET NULL");
    }

    // 2. Perform the table recreation to remove NOT NULL constraints from old columns
    echo "<p>Updating table schema to allow ID-based relationships...</p>";
    
    // Start transaction
    $db->beginTransaction();

    // Create temporary table with the correct schema
    $db->exec("CREATE TABLE cemetery_lots_new (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lot_number VARCHAR(20) NOT NULL,
        section_id INTEGER,
        position VARCHAR(50),
        status VARCHAR(20) NOT NULL CHECK(status IN ('Vacant', 'Occupied', 'Maintenance')),
        size_sqm DECIMAL(10,2),
        price DECIMAL(10,2),
        map_x DECIMAL(10,4),
        map_y DECIMAL(10,4),
        map_width DECIMAL(10,4),
        map_height DECIMAL(10,4),
        layers INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE SET NULL,
        UNIQUE(lot_number, section_id)
    )");

    // Copy data from old table to new table
    // We map existing data to the new columns
    $db->exec("INSERT INTO cemetery_lots_new (
        id, lot_number, section_id, position, status, 
        size_sqm, price, map_x, map_y, map_width, map_height, 
        layers, created_at, updated_at
    ) SELECT 
        id, lot_number, section_id, position, status, 
        size_sqm, price, map_x, map_y, map_width, map_height, 
        layers, created_at, updated_at 
    FROM cemetery_lots");

    // Drop old table and rename new one
    $db->exec("DROP TABLE cemetery_lots");
    $db->exec("ALTER TABLE cemetery_lots_new RENAME TO cemetery_lots");

    // Recreate indexes
    $db->exec("CREATE INDEX IF NOT EXISTS idx_lots_status ON cemetery_lots(status)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_lots_section_id ON cemetery_lots(section_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_lots_number ON cemetery_lots(lot_number)");

    $db->commit();
    
    echo "<p style='color:green'>✓ Table constraints fixed successfully!</p>";
    echo "<h2 style='color:green'>✓ Everything is now perfectly synchronized!</h2>";
    echo "<p><a href='index.php'>Return to Lot Management</a></p>";

} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "<p style='color:red'>✗ Error: " . $e->getMessage() . "</p>";
}
?>