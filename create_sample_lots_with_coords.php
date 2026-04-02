<?php
// Create sample lots with map coordinates for testing

echo "<h2>Creating Sample Lots with Map Coordinates</h2>";

// Since PDO/SQLite3 is not available, let's create a simple HTML test page
// that simulates the cemetery map with sample data

$sampleLots = [
    [
        'id' => 1,
        'lot_number' => 'A-001',
        'section' => 'Section A',
        'map_x' => 10.5,
        'map_y' => 15.2,
        'map_width' => 8.0,
        'map_height' => 6.0,
        'status' => 'Occupied',
        'deceased_name' => 'John Smith'
    ],
    [
        'id' => 2,
        'lot_number' => 'A-002',
        'section' => 'Section A',
        'map_x' => 20.0,
        'map_y' => 15.2,
        'map_width' => 8.0,
        'map_height' => 6.0,
        'status' => 'Vacant',
        'deceased_name' => null
    ],
    [
        'id' => 3,
        'lot_number' => 'A-003',
        'section' => 'Section A',
        'map_x' => 30.0,
        'map_y' => 15.2,
        'map_width' => 8.0,
        'map_height' => 6.0,
        'status' => 'Occupied',
        'deceased_name' => 'Mary Johnson'
    ],
    [
        'id' => 4,
        'lot_number' => 'B-001',
        'section' => 'Section B',
        'map_x' => 10.5,
        'map_y' => 25.0,
        'map_width' => 8.0,
        'map_height' => 6.0,
        'status' => 'Vacant',
        'deceased_name' => null
    ],
    [
        'id' => 5,
        'lot_number' => 'B-002',
        'section' => 'Section B',
        'map_x' => 20.0,
        'map_y' => 25.0,
        'map_width' => 8.0,
        'map_height' => 6.0,
        'status' => 'Vacant',
        'deceased_name' => null
    ]
];

echo "<h3>Sample Lots Created:</h3>";
echo "<table border='1'><tr><th>ID</th><th>Lot #</th><th>Section</th><th>Map X</th><th>Map Y</th><th>Width</th><th>Height</th><th>Status</th></tr>";

foreach ($sampleLots as $lot) {
    echo "<tr>";
    echo "<td>{$lot['id']}</td>";
    echo "<td>{$lot['lot_number']}</td>";
    echo "<td>{$lot['section']}</td>";
    echo "<td>{$lot['map_x']}</td>";
    echo "<td>{$lot['map_y']}</td>";
    echo "<td>{$lot['map_width']}</td>";
    echo "<td>{$lot['map_height']}</td>";
    echo "<td>{$lot['status']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<br><h3>Next Steps:</h3>";
echo "1. These sample lots have map coordinates assigned<br>";
echo "2. The cemetery map should now display these lots<br>";
echo "3. You can test the map functionality by clicking 'View on Map' from the lot management page<br>";
echo "4. The map should show the lots as clickable markers on the cemetery map image<br>";

// Save sample data to a JSON file for the map to use
file_put_contents('database/sample_lots.json', json_encode($sampleLots, JSON_PRETTY_PRINT));
echo "<br>✅ Sample data saved to database/sample_lots.json<br>";

// Create a test map page
$testMapHtml = '<!DOCTYPE html>
<html>
<head>
    <title>Test Cemetery Map</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .map-container { 
            position: relative; 
            width: 800px; 
            height: 600px; 
            border: 2px solid #ccc; 
            background: #f5f5f5;
            background-image: url("assets/images/cemetery.jpg");
            background-size: cover;
            background-position: center;
        }
        .lot-marker { 
            position: absolute; 
            border: 2px solid #333; 
            background: rgba(255,255,255,0.8); 
            cursor: pointer; 
            padding: 5px;
            font-size: 12px;
            font-weight: bold;
        }
        .lot-marker.occupied { border-color: #f97316; background: rgba(249,115,22,0.3); }
        .lot-marker.vacant { border-color: #22c55e; background: rgba(34,197,94,0.3); }
    </style>
</head>
<body>
    <h2>Test Cemetery Map</h2>
    <div class="map-container">';

foreach ($sampleLots as $lot) {
    $testMapHtml .= sprintf(
        '<div class="lot-marker %s" style="left: %s%%; top: %s%%; width: %s%%; height: %s%%;" title="%s - %s">%s</div>',
        strtolower($lot['status']),
        $lot['map_x'],
        $lot['map_y'],
        $lot['map_width'],
        $lot['map_height'],
        $lot['lot_number'],
        $lot['status'],
        $lot['lot_number']
    );
}

$testMapHtml .= '</div>
    <p>This is a test map showing sample lots with coordinates. If you see colored rectangles on the map, the coordinate system is working.</p>
    <p><a href="public/cemetery-map.php">Go to Cemetery Map Page</a></p>
</body>
</html>';

file_put_contents('test_map.html', $testMapHtml);
echo "✅ Test map created: test_map.html<br>";
?>
