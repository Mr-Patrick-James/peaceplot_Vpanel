<?php
// Script to add map_x and map_y columns to cemetery_lots table

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    die("Database connection failed");
}

try {
    // Check if columns already exist
    $stmt = $conn->query("PRAGMA table_info(cemetery_lots)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasMapX = false;
    $hasMapY = false;
    $hasMapWidth = false;
    $hasMapHeight = false;
    
    foreach ($columns as $column) {
        if ($column['name'] === 'map_x') $hasMapX = true;
        if ($column['name'] === 'map_y') $hasMapY = true;
        if ($column['name'] === 'map_width') $hasMapWidth = true;
        if ($column['name'] === 'map_height') $hasMapHeight = true;
    }
    
    // Add columns if they don't exist
    if (!$hasMapX) {
        $conn->exec("ALTER TABLE cemetery_lots ADD COLUMN map_x DECIMAL(10,4)");
        echo "Added map_x column<br>";
    } else {
        echo "map_x column already exists<br>";
    }
    
    if (!$hasMapY) {
        $conn->exec("ALTER TABLE cemetery_lots ADD COLUMN map_y DECIMAL(10,4)");
        echo "Added map_y column<br>";
    } else {
        echo "map_y column already exists<br>";
    }
    
    if (!$hasMapWidth) {
        $conn->exec("ALTER TABLE cemetery_lots ADD COLUMN map_width DECIMAL(10,4)");
        echo "Added map_width column<br>";
    } else {
        echo "map_width column already exists<br>";
    }
    
    if (!$hasMapHeight) {
        $conn->exec("ALTER TABLE cemetery_lots ADD COLUMN map_height DECIMAL(10,4)");
        echo "Added map_height column<br>";
    } else {
        echo "map_height column already exists<br>";
    }
    
    echo "\nDatabase updated successfully!";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
