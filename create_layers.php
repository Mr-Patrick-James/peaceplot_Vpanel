<?php
try {
    // Direct SQLite connection
    $db_file = __DIR__ . '/database/peaceplot.db';
    $conn = new PDO("sqlite:" . $db_file);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully\n";
    
    // Create lot_layers table
    $sql = "CREATE TABLE IF NOT EXISTS lot_layers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        lot_id INTEGER NOT NULL,
        layer_number INTEGER NOT NULL,
        is_occupied BOOLEAN DEFAULT 0,
        burial_record_id INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (lot_id) REFERENCES cemetery_lots(id) ON DELETE CASCADE,
        FOREIGN KEY (burial_record_id) REFERENCES deceased_records(id) ON DELETE SET NULL,
        UNIQUE(lot_id, layer_number)
    )";
    
    $conn->exec($sql);
    echo "lot_layers table created successfully\n";
    
    // Add layer column to deceased_records if it doesn't exist
    try {
        $conn->exec("ALTER TABLE deceased_records ADD COLUMN layer INTEGER DEFAULT 1");
        echo "Added layer column to deceased_records\n";
    } catch (Exception $e) {
        echo "Layer column already exists in deceased_records\n";
    }
    
    // Add layers column to cemetery_lots if it doesn't exist
    try {
        $conn->exec("ALTER TABLE cemetery_lots ADD COLUMN layers INTEGER DEFAULT 1");
        echo "Added layers column to cemetery_lots\n";
    } catch (Exception $e) {
        echo "Layers column already exists in cemetery_lots\n";
    }
    
    // Initialize existing lots with layer 1
    $conn->exec("INSERT OR IGNORE INTO lot_layers (lot_id, layer_number, is_occupied) SELECT id, 1, 0 FROM cemetery_lots");
    echo "Initialized existing lots with layer 1\n";
    
    // Update cemetery_lots layers count
    $conn->exec("UPDATE cemetery_lots SET layers = 1 WHERE layers IS NULL OR layers = 0");
    echo "Updated cemetery_lots layers count\n";
    
    // Create indexes
    $conn->exec("CREATE INDEX IF NOT EXISTS idx_lot_layers_lot ON lot_layers(lot_id)");
    $conn->exec("CREATE INDEX IF NOT EXISTS idx_lot_layers_occupied ON lot_layers(is_occupied)");
    $conn->exec("CREATE INDEX IF NOT EXISTS idx_deceased_layer ON deceased_records(layer)");
    echo "Created indexes\n";
    
    echo "Database setup completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
