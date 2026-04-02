<?php
require_once 'config/database.php';

$database = new Database();
$conn = $database->getConnection();

if ($conn) {
    echo "Database connected successfully\n";
    
    // Check if lot_layers table exists
    $stmt = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='lot_layers'");
    $result = $stmt->fetch();
    
    if ($result) {
        echo "lot_layers table exists\n";
    } else {
        echo "lot_layers table does not exist - creating it...\n";
        
        // Create the lot_layers table
        $sql = "
        CREATE TABLE IF NOT EXISTS lot_layers (
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
        );
        
        CREATE INDEX IF NOT EXISTS idx_lot_layers_lot ON lot_layers(lot_id);
        CREATE INDEX IF NOT EXISTS idx_lot_layers_occupied ON lot_layers(is_occupied);
        ";
        
        try {
            $conn->exec($sql);
            echo "lot_layers table created successfully\n";
            
            // Initialize existing lots with default layer structure
            $initSql = "
            INSERT OR IGNORE INTO lot_layers (lot_id, layer_number, is_occupied)
            SELECT id, 1, 0 FROM cemetery_lots;
            
            UPDATE cemetery_lots SET layers = 1 WHERE layers IS NULL OR layers = 0;
            ";
            
            $conn->exec($initSql);
            echo "Existing lots initialized with layer 1\n";
            
        } catch (Exception $e) {
            echo "Error creating table: " . $e->getMessage() . "\n";
        }
    }
    
    // Check if deceased_records has layer column
    $stmt = $conn->query("PRAGMA table_info(deceased_records)");
    $columns = $stmt->fetchAll();
    $hasLayerColumn = false;
    
    foreach ($columns as $column) {
        if ($column['name'] === 'layer') {
            $hasLayerColumn = true;
            break;
        }
    }
    
    if (!$hasLayerColumn) {
        echo "Adding layer column to deceased_records table...\n";
        try {
            $conn->exec("ALTER TABLE deceased_records ADD COLUMN layer INTEGER DEFAULT 1");
            echo "Layer column added successfully\n";
        } catch (Exception $e) {
            echo "Error adding layer column: " . $e->getMessage() . "\n";
        }
    } else {
        echo "deceased_records table already has layer column\n";
    }
    
    // Check if cemetery_lots has layers column
    $stmt = $conn->query("PRAGMA table_info(cemetery_lots)");
    $columns = $stmt->fetchAll();
    $hasLayersColumn = false;
    
    foreach ($columns as $column) {
        if ($column['name'] === 'layers') {
            $hasLayersColumn = true;
            break;
        }
    }
    
    if (!$hasLayersColumn) {
        echo "Adding layers column to cemetery_lots table...\n";
        try {
            $conn->exec("ALTER TABLE cemetery_lots ADD COLUMN layers INTEGER DEFAULT 1");
            echo "Layers column added successfully\n";
        } catch (Exception $e) {
            echo "Error adding layers column: " . $e->getMessage() . "\n";
        }
    } else {
        echo "cemetery_lots table already has layers column\n";
    }
    
} else {
    echo "Database connection failed\n";
}

$database->closeConnection();
?>
