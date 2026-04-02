<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Debug Cemetery Map Data</h2>";

require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        echo "❌ Database connection failed<br>";
        exit;
    }
    
    echo "✅ Database connected<br><br>";
    
    // Check table structure
    echo "<h3>Table Structure:</h3>";
    $stmt = $conn->query("PRAGMA table_info(cemetery_lots)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'><tr><th>Column</th><th>Type</th></tr>";
    foreach ($columns as $col) {
        echo "<tr><td>{$col['name']}</td><td>{$col['type']}</td></tr>";
    }
    echo "</table><br>";
    
    // Check lots data
    echo "<h3>Lots Data:</h3>";
    $stmt = $conn->query("SELECT COUNT(*) as total FROM cemetery_lots");
    $total = $stmt->fetch();
    echo "Total lots: " . $total['total'] . "<br><br>";
    
    if ($total['total'] > 0) {
        $stmt = $conn->query("SELECT id, lot_number, section, map_x, map_y, map_width, map_height FROM cemetery_lots LIMIT 10");
        $lots = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1'><tr><th>ID</th><th>Lot #</th><th>Section</th><th>Map X</th><th>Map Y</th><th>Width</th><th>Height</th><th>Status</th></tr>";
        
        foreach ($lots as $lot) {
            $hasCoords = $lot['map_x'] !== null && $lot['map_y'] !== null && 
                        $lot['map_width'] !== null && $lot['map_height'] !== null;
            $status = $hasCoords ? "✅ Has coords" : "❌ No coords";
            
            echo "<tr>";
            echo "<td>{$lot['id']}</td>";
            echo "<td>{$lot['lot_number']}</td>";
            echo "<td>{$lot['section']}</td>";
            echo "<td>" . ($lot['map_x'] ?? 'NULL') . "</td>";
            echo "<td>" . ($lot['map_y'] ?? 'NULL') . "</td>";
            echo "<td>" . ($lot['map_width'] ?? 'NULL') . "</td>";
            echo "<td>" . ($lot['map_height'] ?? 'NULL') . "</td>";
            echo "<td>$status</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check map image
    echo "<h3>Map Image:</h3>";
    $mapPath = __DIR__ . '/assets/images/cemetery.jpg';
    if (file_exists($mapPath)) {
        echo "✅ Map image found: assets/images/cemetery.jpg<br>";
        echo "Size: " . filesize($mapPath) . " bytes<br>";
    } else {
        echo "❌ Map image not found: assets/images/cemetery.jpg<br>";
        echo "Please add a cemetery map image to see lots on the map.<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>
