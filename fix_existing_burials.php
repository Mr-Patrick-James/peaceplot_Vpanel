<?php
require_once __DIR__ . '/config/database.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Existing Burials - PeacePlot</title>
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
        .missing {
            color: #dc3545;
            font-weight: 600;
        }
        .exists {
            color: #28a745;
            font-weight: 600;
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            border: 1px solid #e9ecef;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Fix Existing Burial Records</h1>
        <p>This tool will fix existing burial records that don't have proper layer assignments.</p>
        
        <?php
        $database = new Database();
        $conn = $database->getConnection();
        
        if ($conn) {
            echo '<div class="status success">✅ Database connection successful</div>';
            
            // Check for burial records without layer assignment
            $stmt = $conn->query("
                SELECT dr.*, cl.lot_number, cl.section, cl.block 
                FROM deceased_records dr 
                LEFT JOIN cemetery_lots cl ON dr.lot_id = cl.id 
                WHERE dr.layer IS NULL OR dr.layer = 0
                ORDER BY dr.date_of_death DESC
            ");
            $recordsWithoutLayer = $stmt->fetchAll();
            
            // Check for burial records with layer but no corresponding lot_layers entry
            $stmt = $conn->query("
                SELECT dr.*, cl.lot_number, cl.section, cl.block,
                       ll.id as layer_id, ll.is_occupied, ll.burial_record_id
                FROM deceased_records dr 
                LEFT JOIN cemetery_lots cl ON dr.lot_id = cl.id 
                LEFT JOIN lot_layers ll ON dr.lot_id = ll.lot_id AND dr.layer = ll.layer_number
                WHERE dr.layer IS NOT NULL AND dr.layer > 0 
                AND (ll.id IS NULL OR ll.burial_record_id != dr.id)
                ORDER BY dr.date_of_death DESC
            ");
            $orphanedAssignments = $stmt->fetchAll();
            
            echo '<h3>📊 Analysis Results</h3>';
            echo '<div class="status info">';
            echo '📝 Records without layer: ' . count($recordsWithoutLayer) . '<br>';
            echo '🔗 Orphaned layer assignments: ' . count($orphanedAssignments);
            echo '</div>';
            
            if (count($recordsWithoutLayer) > 0 || count($orphanedAssignments) > 0) {
                echo '<div class="status warning">⚠️ Found issues that need to be fixed.</div>';
                echo '<button onclick="fixIssues()">🔧 Fix All Issues</button>';
                echo '<button class="safe" onclick="showDetails()">📋 Show Details</button>';
                
                echo '<div id="details" style="display: none; margin-top: 20px;">';
                
                if (count($recordsWithoutLayer) > 0) {
                    echo '<h4>Records Without Layer Assignment:</h4>';
                    echo '<table>';
                    echo '<tr><th>Name</th><th>Lot</th><th>Section</th><th>Date of Death</th><th>Issue</th></tr>';
                    
                    foreach ($recordsWithoutLayer as $record) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($record['full_name']) . '</td>';
                        echo '<td>' . htmlspecialchars($record['lot_number'] ?? 'Unknown') . '</td>';
                        echo '<td>' . htmlspecialchars($record['section'] ?? 'Unknown') . '</td>';
                        echo '<td>' . ($record['date_of_death'] ? date('M d, Y', strtotime($record['date_of_death'])) : 'N/A') . '</td>';
                        echo '<td class="missing">No layer assigned</td>';
                        echo '</tr>';
                    }
                    
                    echo '</table>';
                }
                
                if (count($orphanedAssignments) > 0) {
                    echo '<h4>Orphaned Layer Assignments:</h4>';
                    echo '<table>';
                    echo '<tr><th>Name</th><th>Lot</th><th>Assigned Layer</th><th>Layer Status</th><th>Issue</th></tr>';
                    
                    foreach ($orphanedAssignments as $record) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($record['full_name']) . '</td>';
                        echo '<td>' . htmlspecialchars($record['lot_number'] ?? 'Unknown') . '</td>';
                        echo '<td>Layer ' . $record['layer'] . '</td>';
                        echo '<td>' . ($record['is_occupied'] ? 'Occupied' : 'Vacant') . '</td>';
                        echo '<td class="missing">Not linked to layer table</td>';
                        echo '</tr>';
                    }
                    
                    echo '</table>';
                }
                
                echo '</div>';
            } else {
                echo '<div class="status success">🎉 All burial records are properly linked to layers!</div>';
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
            
            function fixIssues() {
                if (!confirm('This will fix all burial records by assigning them to Layer 1 and updating the layer table. Continue?')) {
                    return;
                }
                
                const button = event.target;
                button.disabled = true;
                button.textContent = '⏳ Fixing...';
                
                fetch('fix_existing_burials.php?action=fix', {
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
                        button.textContent = '🔧 Fix All Issues';
                    }
                })
                .catch(error => {
                    document.getElementById('results').innerHTML = '<div class="status error">❌ Error: ' + error.message + '</div>';
                    button.disabled = false;
                    button.textContent = '🔧 Fix All Issues';
                });
            }
        </script>
    </div>
</body>
</html>

<?php
// Handle the fix request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'fix') {
    header('Content-Type: application/json');
    
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        if (!$conn) {
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            exit;
        }
        
        $fixedCount = 0;
        $errorCount = 0;
        
        // Start transaction
        $conn->beginTransaction();
        
        try {
            // 1. Fix records without layer assignment
            $stmt = $conn->query("
                SELECT dr.*, cl.lot_number 
                FROM deceased_records dr 
                LEFT JOIN cemetery_lots cl ON dr.lot_id = cl.id 
                WHERE dr.layer IS NULL OR dr.layer = 0
            ");
            $recordsWithoutLayer = $stmt->fetchAll();
            
            foreach ($recordsWithoutLayer as $record) {
                // Update the burial record to assign layer 1
                $updateStmt = $conn->prepare("UPDATE deceased_records SET layer = 1 WHERE id = :id");
                $updateStmt->bindParam(':id', $record['id']);
                
                if ($updateStmt->execute()) {
                    // Update or create the lot_layers entry
                    $upsertStmt = $conn->prepare("
                        INSERT OR REPLACE INTO lot_layers (lot_id, layer_number, is_occupied, burial_record_id)
                        VALUES (:lot_id, 1, 1, :burial_record_id)
                    ");
                    $upsertStmt->bindParam(':lot_id', $record['lot_id']);
                    $upsertStmt->bindParam(':burial_record_id', $record['id']);
                    
                    if ($upsertStmt->execute()) {
                        $fixedCount++;
                    } else {
                        $errorCount++;
                    }
                } else {
                    $errorCount++;
                }
            }
            
            // 2. Fix orphaned layer assignments
            $stmt = $conn->query("
                SELECT dr.*, cl.lot_number
                FROM deceased_records dr 
                LEFT JOIN cemetery_lots cl ON dr.lot_id = cl.id 
                LEFT JOIN lot_layers ll ON dr.lot_id = ll.lot_id AND dr.layer = ll.layer_number
                WHERE dr.layer IS NOT NULL AND dr.layer > 0 
                AND (ll.id IS NULL OR ll.burial_record_id != dr.id)
            ");
            $orphanedAssignments = $stmt->fetchAll();
            
            foreach ($orphanedAssignments as $record) {
                // Update the lot_layers entry to link to this burial record
                $upsertStmt = $conn->prepare("
                    INSERT OR REPLACE INTO lot_layers (lot_id, layer_number, is_occupied, burial_record_id)
                    VALUES (:lot_id, :layer, 1, :burial_record_id)
                ");
                $upsertStmt->bindParam(':lot_id', $record['lot_id']);
                $upsertStmt->bindParam(':layer', $record['layer']);
                $upsertStmt->bindParam(':burial_record_id', $record['id']);
                
                if ($upsertStmt->execute()) {
                    $fixedCount++;
                } else {
                    $errorCount++;
                }
            }
            
            // 3. Update lot statuses based on layer occupancy
            $stmt = $conn->query("
                UPDATE cemetery_lots 
                SET status = CASE 
                    WHEN EXISTS (
                        SELECT 1 FROM lot_layers ll 
                        WHERE ll.lot_id = cemetery_lots.id AND ll.is_occupied = 1
                    ) THEN 'Occupied'
                    ELSE 'Vacant'
                END
            ");
            
            $conn->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => "✅ Fixed $fixedCount burial records successfully. " . ($errorCount > 0 ? "$errorCount errors occurred." : "All records updated properly.")
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Fix failed: ' . $e->getMessage()]);
    }
    exit;
}
?>
