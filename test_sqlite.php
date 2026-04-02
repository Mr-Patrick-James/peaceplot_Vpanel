<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing SQLite connection...\n";

try {
    $pdo = new PDO('sqlite:database/peaceplot.db');
    echo "SQLite connection successful\n";
    
    // Test a simple query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM cemetery_lots");
    $result = $stmt->fetch();
    echo "Found " . $result['count'] . " lots in database\n";
    
    // Check for map coordinates
    $stmt = $pdo->query("SELECT id, lot_number, map_x, map_y FROM cemetery_lots WHERE map_x IS NOT NULL LIMIT 3");
    $lots = $stmt->fetchAll();
    echo "Lots with map coordinates:\n";
    foreach ($lots as $lot) {
        echo "- Lot {$lot['lot_number']} (ID: {$lot['id']}) at ({$lot['map_x']}, {$lot['map_y']})\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
