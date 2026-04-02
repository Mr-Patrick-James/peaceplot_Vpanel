<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Testing SQLite via Web Server</h2>";

try {
    $pdo = new PDO('sqlite:database/peaceplot.db');
    echo "✅ SQLite connection successful<br>";
    
    // Test a simple query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM cemetery_lots");
    $result = $stmt->fetch();
    echo "📊 Found " . $result['count'] . " lots in database<br>";
    
    // Check for map coordinates
    $stmt = $pdo->query("SELECT id, lot_number, map_x, map_y FROM cemetery_lots WHERE map_x IS NOT NULL LIMIT 5");
    $lots = $stmt->fetchAll();
    echo "📍 Lots with map coordinates:<br>";
    foreach ($lots as $lot) {
        echo "- Lot {$lot['lot_number']} (ID: {$lot['id']}) at ({$lot['map_x']}, {$lot['map_y']})<br>";
    }
    
    if (empty($lots)) {
        echo "⚠️ No lots have map coordinates assigned yet. You need to use the Map Editor to set coordinates.<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<br><h3>Available PDO Drivers:</h3>";
print_r(PDO::getAvailableDrivers());
?>
