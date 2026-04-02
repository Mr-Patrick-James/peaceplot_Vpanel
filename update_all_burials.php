<?php
require_once __DIR__ . '/config/database.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update All Burials - PeacePlot</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 1200px;
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
        .warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        button {
            background: #dc3545;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 5px;
        }
        button:hover {
            background: #c82333;
        }
        button.safe {
            background: #28a745;
        }
        button.safe:hover {
            background: #218838;
        }
        button:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .progress {
            width: 100%;
            height: 20px;
            background: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            width: 0%;
            transition: width 0.3s ease;
        }
        .log {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 15px;
            max-height: 300px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            white-space: pre-wrap;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .summary-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        .summary-card h4 {
            margin: 0 0 5px 0;
            color: #333;
        }
        .summary-card .number {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Comprehensive Burial Records Update</h1>
        <p>This tool will completely rebuild and synchronize all burial records with the layer system.</p>
        
        <?php
        $database = new Database();
        $conn = $database->getConnection();
        
        if ($conn) {
            echo '<div class="status success">✅ Database connection successful</div>';
            
            // Comprehensive analysis
            $analysis = analyzeBurialData($conn);
            
            echo '<div class="summary-grid">';
            echo '<div class="summary-card">';
            echo '<h4>Total Burial Records</h4>';
            echo '<div class="number">' . $analysis['total_burials'] . '</div>';
            echo '</div>';
            echo '<div class="summary-card">';
            echo '<h4>Records Needing Layer</h4>';
            echo '<div class="number">' . $analysis['need_layer'] . '</div>';
            echo '</div>';
            echo '<div class="summary-card">';
            echo '<h4>Orphaned Layers</h4>';
            echo '<div class="number">' . $analysis['orphaned_layers'] . '</div>';
            echo '</div>';
            echo '<div class="summary-card">';
            echo '<h4>Lots Affected</h4>';
            echo '<div class="number">' . $analysis['lots_affected'] . '</div>';
            echo '</div>';
            echo '</div>';
            
            if ($analysis['total_issues'] > 0) {
                echo '<div class="status warning">⚠️ Found ' . $analysis['total_issues'] . ' issues that need to be fixed.</div>';
                echo '<button onclick="startUpdate()">🔄 Start Complete Update</button>';
                echo '<button class="safe" onclick="showDetails()">📋 Show Detailed Analysis</button>';
                
                echo '<div id="progressContainer" style="display: none; margin: 20px 0;">';
                echo '<h3>Update Progress</h3>';
                echo '<div class="progress">';
                echo '<div class="progress-bar" id="progressBar"></div>';
                echo '</div>';
                echo '<div id="progressText">Starting update...</div>';
                echo '<div class="log" id="updateLog"></div>';
                echo '</div>';
                
                echo '<div id="details" style="display: none; margin-top: 20px;">';
                echo '<h3>📋 Detailed Analysis</h3>';
                
                if (!empty($analysis['records_without_layer'])) {
                    echo '<h4>Records Without Layer Assignment:</h4>';
                    echo '<table>';
                    echo '<tr><th>Name</th><th>Lot</th><th>Section</th><th>Date of Death</th><th>Current Layer</th></tr>';
                    
                    foreach ($analysis['records_without_layer'] as $record) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($record['full_name']) . '</td>';
                        echo '<td>' . htmlspecialchars($record['lot_number'] ?? 'Unknown') . '</td>';
                        echo '<td>' . htmlspecialchars($record['section'] ?? 'Unknown') . '</td>';
                        echo '<td>' . ($record['date_of_death'] ? date('M d, Y', strtotime($record['date_of_death'])) : 'N/A') . '</td>';
                        echo '<td class="missing">' . ($record['layer'] ?? 'NULL') . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                }
                
                if (!empty($analysis['orphaned_assignments'])) {
                    echo '<h4>Orphaned Layer Assignments:</h4>';
                    echo '<table>';
                    echo '<tr><th>Name</th><th>Lot</th><th>Assigned Layer</th><th>Layer Status</th><th>Linked To</th></tr>';
                    
                    foreach ($analysis['orphaned_assignments'] as $record) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($record['full_name']) . '</td>';
                        echo '<td>' . htmlspecialchars($record['lot_number'] ?? 'Unknown') . '</td>';
                        echo '<td>Layer ' . $record['layer'] . '</td>';
                        echo '<td>' . ($record['is_occupied'] ? 'Occupied' : 'Vacant') . '</td>';
                        echo '<td class="missing">' . ($record['burial_record_id'] ? 'ID ' . $record['burial_record_id'] : 'Not linked') . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                }
                
                if (!empty($analysis['missing_lot_layers'])) {
                    echo '<h4>Lots Missing Layer Entries:</h4>';
                    echo '<table>';
                    echo '<tr><th>Lot Number</th><th>Section</th><th>Burials Count</th><th>Issue</th></tr>';
                    
                    foreach ($analysis['missing_lot_layers'] as $lot) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($lot['lot_number']) . '</td>';
                        echo '<td>' . htmlspecialchars($lot['section']) . '</td>';
                        echo '<td>' . $lot['burial_count'] . '</td>';
                        echo '<td class="missing">No layer entries</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                }
                
                echo '</div>';
            } else {
                echo '<div class="status success">🎉 All burial records are properly synchronized!</div>';
            }
            
        } else {
            echo '<div class="status error">❌ Database connection failed</div>';
        }
        ?>
        
        <div id="results"></div>
        
        <script>
            function showDetails() {
                const details = document.getElementById('details');
                details.style.display = details.style.display === 'none' ? 'block' : 'none';
            }
            
            function startUpdate() {
                if (!confirm('This will perform a complete update of all burial records and layer assignments. Continue?')) {
                    return;
                }
                
                const button = event.target;
                button.disabled = true;
                button.textContent = '⏳ Updating...';
                
                // Show progress container
                document.getElementById('progressContainer').style.display = 'block';
                document.getElementById('details').style.display = 'none';
                
                const progressBar = document.getElementById('progressBar');
                const progressText = document.getElementById('progressText');
                const updateLog = document.getElementById('updateLog');
                
                // Create EventSource for real-time updates
                const eventSource = new EventSource('update_all_burials.php?action=stream');
                
                eventSource.onmessage = function(event) {
                    const data = JSON.parse(event.data);
                    
                    if (data.type === 'progress') {
                        progressBar.style.width = data.progress + '%';
                        progressText.textContent = data.message;
                    } else if (data.type === 'log') {
                        updateLog.textContent += data.message + '\n';
                        updateLog.scrollTop = updateLog.scrollHeight;
                    } else if (data.type === 'complete') {
                        eventSource.close();
                        progressBar.style.width = '100%';
                        progressText.textContent = '✅ Update completed successfully!';
                        
                        const resultsDiv = document.getElementById('results');
                        resultsDiv.innerHTML = '<div class="status success">' + data.message + '</div>';
                        
                        setTimeout(() => {
                            window.location.reload();
                        }, 3000);
                    } else if (data.type === 'error') {
                        eventSource.close();
                        progressText.textContent = '❌ Update failed!';
                        updateLog.textContent += 'ERROR: ' + data.message + '\n';
                        
                        button.disabled = false;
                        button.textContent = '🔄 Start Complete Update';
                    }
                };
                
                eventSource.onerror = function() {
                    eventSource.close();
                    progressText.textContent = '❌ Connection lost!';
                    button.disabled = false;
                    button.textContent = '🔄 Start Complete Update';
                };
            }
        </script>
    </div>
</body>
</html>

<?php
function analyzeBurialData($conn) {
    $analysis = [
        'total_burials' => 0,
        'need_layer' => 0,
        'orphaned_layers' => 0,
        'lots_affected' => 0,
        'total_issues' => 0,
        'records_without_layer' => [],
        'orphaned_assignments' => [],
        'missing_lot_layers' => []
    ];
    
    try {
        // Get total burial records
        $stmt = $conn->query("SELECT COUNT(*) as count FROM deceased_records");
        $analysis['total_burials'] = $stmt->fetch()['count'];
        
        // Records without layer assignment
        $stmt = $conn->query("
            SELECT dr.*, cl.lot_number, cl.section 
            FROM deceased_records dr 
            LEFT JOIN cemetery_lots cl ON dr.lot_id = cl.id 
            WHERE dr.layer IS NULL OR dr.layer = 0
            ORDER BY dr.date_of_death DESC
        ");
        $analysis['records_without_layer'] = $stmt->fetchAll();
        $analysis['need_layer'] = count($analysis['records_without_layer']);
        
        // Orphaned assignments
        $stmt = $conn->query("
            SELECT dr.*, cl.lot_number, cl.section,
                   ll.id as layer_id, ll.is_occupied, ll.burial_record_id
            FROM deceased_records dr 
            LEFT JOIN cemetery_lots cl ON dr.lot_id = cl.id 
            LEFT JOIN lot_layers ll ON dr.lot_id = ll.lot_id AND dr.layer = ll.layer_number
            WHERE dr.layer IS NOT NULL AND dr.layer > 0 
            AND (ll.id IS NULL OR ll.burial_record_id != dr.id)
            ORDER BY dr.date_of_death DESC
        ");
        $analysis['orphaned_assignments'] = $stmt->fetchAll();
        $analysis['orphaned_layers'] = count($analysis['orphaned_assignments']);
        
        // Lots missing layer entries but have burials
        $stmt = $conn->query("
            SELECT cl.id, cl.lot_number, cl.section, COUNT(dr.id) as burial_count
            FROM cemetery_lots cl
            LEFT JOIN deceased_records dr ON cl.id = dr.lot_id
            LEFT JOIN lot_layers ll ON cl.id = ll.lot_id
            WHERE dr.id IS NOT NULL AND ll.id IS NULL
            GROUP BY cl.id, cl.lot_number, cl.section
        ");
        $analysis['missing_lot_layers'] = $stmt->fetchAll();
        
        // Calculate total affected lots
        $affectedLots = array_unique(array_merge(
            array_column($analysis['records_without_layer'], 'lot_id'),
            array_column($analysis['orphaned_assignments'], 'lot_id'),
            array_column($analysis['missing_lot_layers'], 'id')
        ));
        $analysis['lots_affected'] = count($affectedLots);
        
        $analysis['total_issues'] = $analysis['need_layer'] + $analysis['orphaned_layers'] + count($analysis['missing_lot_layers']);
        
    } catch (Exception $e) {
        error_log("Analysis error: " . $e->getMessage());
    }
    
    return $analysis;
}

// Handle the streaming update
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'stream') {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    
    function sendEvent($type, $data) {
        echo "data: " . json_encode(['type' => $type, 'message' => $data]) . "\n\n";
        ob_flush();
        flush();
    }
    
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        if (!$conn) {
            sendEvent('error', 'Database connection failed');
            exit;
        }
        
        sendEvent('progress', 5);
        sendEvent('log', 'Starting comprehensive burial records update...');
        
        $conn->beginTransaction();
        
        try {
            sendEvent('progress', 10);
            sendEvent('log', 'Step 1: Analyzing current data...');
            
            $analysis = analyzeBurialData($conn);
            sendEvent('log', "Found {$analysis['total_burials']} total burial records");
            sendEvent('log', "Records needing layer assignment: {$analysis['need_layer']}");
            sendEvent('log', "Orphaned assignments: {$analysis['orphaned_layers']}");
            sendEvent('log', "Lots missing layer entries: " . count($analysis['missing_lot_layers']));
            
            sendEvent('progress', 20);
            sendEvent('log', 'Step 2: Fixing records without layer assignment...');
            
            // Fix records without layer assignment
            $fixedCount = 0;
            foreach ($analysis['records_without_layer'] as $record) {
                // Update burial record to assign layer 1
                $updateStmt = $conn->prepare("UPDATE deceased_records SET layer = 1 WHERE id = :id");
                $updateStmt->bindParam(':id', $record['id']);
                $updateStmt->execute();
                
                // Ensure lot_layers entry exists and is linked
                $upsertStmt = $conn->prepare("
                    INSERT OR REPLACE INTO lot_layers (lot_id, layer_number, is_occupied, burial_record_id)
                    VALUES (:lot_id, 1, 1, :burial_record_id)
                ");
                $upsertStmt->bindParam(':lot_id', $record['lot_id']);
                $upsertStmt->bindParam(':burial_record_id', $record['id']);
                $upsertStmt->execute();
                
                $fixedCount++;
                if ($fixedCount % 10 == 0) {
                    sendEvent('progress', 20 + ($fixedCount / $analysis['need_layer']) * 20);
                    sendEvent('log', "Fixed $fixedCount records without layer assignment...");
                }
            }
            
            sendEvent('progress', 40);
            sendEvent('log', "Step 2 complete: Fixed $fixedCount records without layer assignment");
            
            sendEvent('progress', 45);
            sendEvent('log', 'Step 3: Fixing orphaned layer assignments...');
            
            // Fix orphaned assignments
            $orphanedFixed = 0;
            foreach ($analysis['orphaned_assignments'] as $record) {
                $upsertStmt = $conn->prepare("
                    INSERT OR REPLACE INTO lot_layers (lot_id, layer_number, is_occupied, burial_record_id)
                    VALUES (:lot_id, :layer, 1, :burial_record_id)
                ");
                $upsertStmt->bindParam(':lot_id', $record['lot_id']);
                $upsertStmt->bindParam(':layer', $record['layer']);
                $upsertStmt->bindParam(':burial_record_id', $record['id']);
                $upsertStmt->execute();
                
                $orphanedFixed++;
                if ($orphanedFixed % 5 == 0) {
                    sendEvent('progress', 45 + ($orphanedFixed / $analysis['orphaned_layers']) * 20);
                    sendEvent('log', "Fixed $orphanedFixed orphaned assignments...");
                }
            }
            
            sendEvent('progress', 65);
            sendEvent('log', "Step 3 complete: Fixed $orphanedFixed orphaned assignments");
            
            sendEvent('progress', 70);
            sendEvent('log', 'Step 4: Creating missing layer entries...');
            
            // Create missing layer entries for lots with burials
            $missingLayersFixed = 0;
            foreach ($analysis['missing_lot_layers'] as $lot) {
                // Get burial records for this lot
                $burialStmt = $conn->prepare("
                    SELECT id, layer FROM deceased_records 
                    WHERE lot_id = :lot_id 
                    ORDER BY COALESCE(layer, 1), id
                ");
                $burialStmt->bindParam(':lot_id', $lot['id']);
                $burialStmt->execute();
                $burials = $burialStmt->fetchAll();
                
                // Create layer entries
                $layerNum = 1;
                foreach ($burials as $burial) {
                    $layerInsertStmt = $conn->prepare("
                        INSERT OR REPLACE INTO lot_layers (lot_id, layer_number, is_occupied, burial_record_id)
                        VALUES (:lot_id, :layer_number, 1, :burial_record_id)
                    ");
                    $layerInsertStmt->bindParam(':lot_id', $lot['id']);
                    $layerInsertStmt->bindParam(':layer_number', $layerNum);
                    $layerInsertStmt->bindParam(':burial_record_id', $burial['id']);
                    $layerInsertStmt->execute();
                    
                    // Update burial record layer if needed
                    if (!$burial['layer'] || $burial['layer'] == 0) {
                        $updateBurialStmt = $conn->prepare("UPDATE deceased_records SET layer = :layer WHERE id = :id");
                        $updateBurialStmt->bindParam(':layer', $layerNum);
                        $updateBurialStmt->bindParam(':id', $burial['id']);
                        $updateBurialStmt->execute();
                    }
                    
                    $layerNum++;
                }
                
                $missingLayersFixed++;
                sendEvent('progress', 70 + ($missingLayersFixed / count($analysis['missing_lot_layers'])) * 15);
                sendEvent('log', "Created layer entries for lot {$lot['lot_number']}...");
            }
            
            sendEvent('progress', 85);
            sendEvent('log', "Step 4 complete: Created layer entries for $missingLayersFixed lots");
            
            sendEvent('progress', 90);
            sendEvent('log', 'Step 5: Updating lot statuses...');
            
            // Update all lot statuses based on layer occupancy
            $updateStmt = $conn->query("
                UPDATE cemetery_lots 
                SET status = CASE 
                    WHEN EXISTS (
                        SELECT 1 FROM lot_layers ll 
                        WHERE ll.lot_id = cemetery_lots.id AND ll.is_occupied = 1
                    ) THEN 'Occupied'
                    ELSE 'Vacant'
                END
            ");
            
            sendEvent('progress', 95);
            sendEvent('log', 'Step 5 complete: Updated all lot statuses');
            
            sendEvent('progress', 98);
            sendEvent('log', 'Step 6: Final verification...');
            
            // Final verification
            $stmt = $conn->query("
                SELECT COUNT(*) as count FROM deceased_records 
                WHERE layer IS NULL OR layer = 0
            ");
            $remainingIssues = $stmt->fetch()['count'];
            
            if ($remainingIssues == 0) {
                sendEvent('log', '✅ All burial records now have proper layer assignments');
            } else {
                sendEvent('log', "⚠️ $remainingIssues records still have issues");
            }
            
            $conn->commit();
            
            sendEvent('progress', 100);
            sendEvent('complete', "✅ Comprehensive update completed successfully! Fixed $fixedCount records without layers, $orphanedFixed orphaned assignments, and created layer entries for $missingLayersFixed lots.");
            
        } catch (Exception $e) {
            $conn->rollback();
            sendEvent('error', 'Update failed: ' . $e->getMessage());
        }
        
    } catch (Exception $e) {
        sendEvent('error', 'Database error: ' . $e->getMessage());
    }
    
    exit;
}
?>
