<?php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/config/database.php';
    
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    // Create burial_record_images table
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
    
    // Create indexes
    $conn->exec("CREATE INDEX IF NOT EXISTS idx_burial_images_record ON burial_record_images(burial_record_id)");
    $conn->exec("CREATE INDEX IF NOT EXISTS idx_burial_images_primary ON burial_record_images(burial_record_id, is_primary)");
    $conn->exec("CREATE INDEX IF NOT EXISTS idx_burial_images_order ON burial_record_images(burial_record_id, display_order)");
    
    echo json_encode(['success' => true, 'message' => 'burial_record_images table created successfully']);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error creating table: ' . $e->getMessage()]);
}
?>
