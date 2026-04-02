<?php
try {
    // Direct SQLite connection
    $dbPath = __DIR__ . '/database/peaceplot.db';
    $conn = new PDO("sqlite:" . $dbPath);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully<br>";
    
    // Create the table
    $sql = "
    CREATE TABLE IF NOT EXISTS burial_record_images (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        burial_record_id INTEGER NOT NULL,
        image_path VARCHAR(500) NOT NULL,
        image_caption VARCHAR(255),
        image_type VARCHAR(50) DEFAULT 'grave_photo',
        display_order INTEGER DEFAULT 0,
        is_primary BOOLEAN DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (burial_record_id) REFERENCES deceased_records(id) ON DELETE CASCADE
    )";
    
    $conn->exec($sql);
    echo "Table 'burial_record_images' created successfully<br>";
    
    // Create indexes
    $conn->exec("CREATE INDEX IF NOT EXISTS idx_burial_images_record ON burial_record_images(burial_record_id)");
    $conn->exec("CREATE INDEX IF NOT EXISTS idx_burial_images_primary ON burial_record_images(burial_record_id, is_primary)");
    $conn->exec("CREATE INDEX IF NOT EXISTS idx_burial_images_order ON burial_record_images(burial_record_id, display_order)");
    echo "Indexes created successfully<br>";
    
    // Verify table exists
    $stmt = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='burial_record_images'");
    $result = $stmt->fetch();
    
    if ($result) {
        echo "✅ Table 'burial_record_images' exists and is ready!<br>";
        
        // Check count
        $countStmt = $conn->query("SELECT COUNT(*) as count FROM burial_record_images");
        $count = $countStmt->fetch();
        echo "Current images in table: " . $count['count'] . "<br>";
    } else {
        echo "❌ Table creation failed<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>
