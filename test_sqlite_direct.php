<?php
echo "<h2>Direct SQLite Test</h2>";

try {
    $db_file = __DIR__ . '/database/peaceplot.db';
    
    if (!file_exists($db_file)) {
        echo "❌ Database file not found: $db_file<br>";
        exit;
    }
    
    echo "✅ Database file found: $db_file<br>";
    echo "Size: " . filesize($db_file) . " bytes<br><br>";
    
    // Try to connect with SQLite3
    if (class_exists('SQLite3')) {
        echo "✅ SQLite3 class available<br>";
        
        $db = new SQLite3($db_file);
        $db->enableExceptions(true);
        
        echo "<h3>Tables in database:</h3>";
        $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'");
        
        echo "<ul>";
        while ($table = $tables->fetchArray(SQLITE3_ASSOC)) {
            echo "<li>" . htmlspecialchars($table['name']) . "</li>";
        }
        echo "</ul><br>";
        
        // Check cemetery_lots table
        echo "<h3>Cemetery Lots:</h3>";
        $result = $db->query("SELECT COUNT(*) as count FROM cemetery_lots");
        $count = $result->fetchArray(SQLITE3_ASSOC);
        echo "Total lots: " . $count['count'] . "<br><br>";
        
        if ($count['count'] > 0) {
            echo "<table border='1'><tr><th>Lot #</th><th>Section</th><th>Map X</th><th>Map Y</th><th>Width</th><th>Height</th></tr>";
            
            $lots = $db->query("SELECT lot_number, section, map_x, map_y, map_width, map_height FROM cemetery_lots LIMIT 10");
            while ($lot = $lots->fetchArray(SQLITE3_ASSOC)) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($lot['lot_number']) . "</td>";
                echo "<td>" . htmlspecialchars($lot['section']) . "</td>";
                echo "<td>" . ($lot['map_x'] ?? 'NULL') . "</td>";
                echo "<td>" . ($lot['map_y'] ?? 'NULL') . "</td>";
                echo "<td>" . ($lot['map_width'] ?? 'NULL') . "</td>";
                echo "<td>" . ($lot['map_height'] ?? 'NULL') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        $db->close();
        
    } else {
        echo "❌ SQLite3 class not available<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>
