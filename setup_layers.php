<?php
require_once __DIR__ . '/config/database.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Layers - PeacePlot</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .status {
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        button {
            background: #007bff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 5px;
        }
        button:hover {
            background: #0056b3;
        }
        button:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            border: 1px solid #e9ecef;
        }
        .check-item {
            padding: 10px;
            margin: 5px 0;
            border-left: 4px solid #ddd;
            background: #f9f9f9;
        }
        .check-item.ok {
            border-left-color: #28a745;
            background: #f8fff9;
        }
        .check-item.error {
            border-left-color: #dc3545;
            background: #fff8f8;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🪦 PeacePlot Layer Setup</h1>
        <p>This script will set up the database tables needed for multi-layer burial functionality.</p>
        
        <?php
        $database = new Database();
        $conn = $database->getConnection();
        
        if ($conn) {
            echo '<div class="status success">✅ Database connection successful</div>';
            
            // Check existing tables
            echo '<h3>📋 Database Status Check</h3>';
            
            // Check lot_layers table
            $stmt = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='lot_layers'");
            $lotLayersExists = $stmt->fetch() !== false;
            
            echo '<div class="check-item ' . ($lotLayersExists ? 'ok' : 'error') . '">';
            echo 'lot_layers table: ' . ($lotLayersExists ? '✅ Exists' : '❌ Missing');
            echo '</div>';
            
            // Check deceased_records layer column
            $stmt = $conn->query("PRAGMA table_info(deceased_records)");
            $columns = $stmt->fetchAll();
            $hasLayerColumn = false;
            foreach ($columns as $column) {
                if ($column['name'] === 'layer') {
                    $hasLayerColumn = true;
                    break;
                }
            }
            
            echo '<div class="check-item ' . ($hasLayerColumn ? 'ok' : 'error') . '">';
            echo 'deceased_records.layer column: ' . ($hasLayerColumn ? '✅ Exists' : '❌ Missing');
            echo '</div>';
            
            // Check cemetery_lots layers column
            $stmt = $conn->query("PRAGMA table_info(cemetery_lots)");
            $columns = $stmt->fetchAll();
            $hasLayersColumn = false;
            foreach ($columns as $column) {
                if ($column['name'] === 'layers') {
                    $hasLayersColumn = true;
                    break;
                }
            }
            
            echo '<div class="check-item ' . ($hasLayersColumn ? 'ok' : 'error') . '">';
            echo 'cemetery_lots.layers column: ' . ($hasLayersColumn ? '✅ Exists' : '❌ Missing');
            echo '</div>';
            
            // Show setup button if anything is missing
            if (!$lotLayersExists || !$hasLayerColumn || !$hasLayersColumn) {
                echo '<div class="status info">⚠️ Some database components are missing. Click the button below to set them up.</div>';
                echo '<button onclick="setupDatabase()">🔧 Setup Database Tables</button>';
            } else {
                echo '<div class="status success">🎉 All database components are already set up!</div>';
                
                // Show current layer data
                echo '<h3>📊 Current Layer Data</h3>';
                $stmt = $conn->query("
                    SELECT cl.lot_number, cl.layers, COUNT(ll.id) as layer_count
                    FROM cemetery_lots cl
                    LEFT JOIN lot_layers ll ON cl.id = ll.lot_id
                    GROUP BY cl.id
                    ORDER BY cl.lot_number
                    LIMIT 10
                ");
                $lots = $stmt->fetchAll();
                
                if ($lots) {
                    echo '<table border="1" style="width: 100%; border-collapse: collapse;">';
                    echo '<tr><th>Lot Number</th><th>Total Layers</th><th>Configured Layers</th></tr>';
                    foreach ($lots as $lot) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($lot['lot_number']) . '</td>';
                        echo '<td>' . $lot['layers'] . '</td>';
                        echo '<td>' . $lot['layer_count'] . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                }
            }
            
        } else {
            echo '<div class="status error">❌ Database connection failed</div>';
        }
        ?>
        
        <div id="results"></div>
        
        <script>
            function setupDatabase() {
                const button = event.target;
                button.disabled = true;
                button.textContent = '⏳ Setting up...';
                
                fetch('setup_layers.php?action=setup', {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    const resultsDiv = document.getElementById('results');
                    if (data.success) {
                        resultsDiv.innerHTML = '<div class="status success">' + data.message + '</div>';
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        resultsDiv.innerHTML = '<div class="status error">❌ ' + data.message + '</div>';
                        button.disabled = false;
                        button.textContent = '🔧 Setup Database Tables';
                    }
                })
                .catch(error => {
                    document.getElementById('results').innerHTML = '<div class="status error">❌ Error: ' + error.message + '</div>';
                    button.disabled = false;
                    button.textContent = '🔧 Setup Database Tables';
                });
            }
        </script>
    </div>
</body>
</html>

<?php
// Handle the setup request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'setup') {
    header('Content-Type: application/json');
    
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        if (!$conn) {
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            exit;
        }
        
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
        
        // Add layer column to deceased_records
        try {
            $conn->exec("ALTER TABLE deceased_records ADD COLUMN layer INTEGER DEFAULT 1");
        } catch (Exception $e) {
            // Column might already exist
        }
        
        // Add layers column to cemetery_lots
        try {
            $conn->exec("ALTER TABLE cemetery_lots ADD COLUMN layers INTEGER DEFAULT 1");
        } catch (Exception $e) {
            // Column might already exist
        }
        
        // Initialize existing lots with layer 1
        $conn->exec("INSERT OR IGNORE INTO lot_layers (lot_id, layer_number, is_occupied) SELECT id, 1, 0 FROM cemetery_lots");
        
        // Update cemetery_lots layers count
        $conn->exec("UPDATE cemetery_lots SET layers = 1 WHERE layers IS NULL OR layers = 0");
        
        // Create indexes
        $conn->exec("CREATE INDEX IF NOT EXISTS idx_lot_layers_lot ON lot_layers(lot_id)");
        $conn->exec("CREATE INDEX IF NOT EXISTS idx_lot_layers_occupied ON lot_layers(is_occupied)");
        $conn->exec("CREATE INDEX IF NOT EXISTS idx_deceased_layer ON deceased_records(layer)");
        
        echo json_encode(['success' => true, 'message' => '✅ Database setup completed successfully! The page will refresh in 2 seconds.']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Setup failed: ' . $e->getMessage()]);
    }
    exit;
}
?>
