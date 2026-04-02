<?php
require_once __DIR__ . '/config/database.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cleanup Old Burials - PeacePlot</title>
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
        .danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-left: 4px solid #dc3545;
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
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .warning-box h3 {
            color: #856404;
            margin-top: 0;
        }
        .checkbox-group {
            margin: 20px 0;
        }
        .checkbox-group label {
            display: flex;
            align-items: center;
            margin: 10px 0;
            cursor: pointer;
        }
        .checkbox-group input[type="checkbox"] {
            margin-right: 10px;
            transform: scale(1.2);
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
            border-left: 4px solid #dc3545;
        }
        .summary-card h4 {
            margin: 0 0 5px 0;
            color: #333;
        }
        .summary-card .number {
            font-size: 24px;
            font-weight: bold;
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗑️ Cleanup Old Burial Records</h1>
        <p><strong>⚠️ DANGER:</strong> This will permanently delete old burial records that don't have proper layer assignments.</p>
        
        <?php
        $database = new Database();
        $conn = $database->getConnection();
        
        if ($conn) {
            echo '<div class="status success">✅ Database connection successful</div>';
            
            // Analyze what will be deleted
            $stmt = $conn->query("
                SELECT dr.*, cl.lot_number, cl.section, cl.block 
                FROM deceased_records dr 
                LEFT JOIN cemetery_lots cl ON dr.lot_id = cl.id 
                WHERE dr.layer IS NULL OR dr.layer = 0
                ORDER BY dr.date_of_death DESC
            ");
            $recordsToDelete = $stmt->fetchAll();
            
            // Also check for records with layer but no corresponding lot_layers entry
            $stmt = $conn->query("
                SELECT dr.*, cl.lot_number, cl.section, cl.block
                FROM deceased_records dr 
                LEFT JOIN cemetery_lots cl ON dr.lot_id = cl.id 
                LEFT JOIN lot_layers ll ON dr.lot_id = ll.lot_id AND dr.layer = ll.layer_number
                WHERE dr.layer IS NOT NULL AND dr.layer > 0 
                AND ll.id IS NULL
                ORDER BY dr.date_of_death DESC
            ");
            $orphanedRecords = $stmt->fetchAll();
            
            $totalToDelete = count($recordsToDelete) + count($orphanedRecords);
            
            echo '<div class="summary-grid">';
            echo '<div class="summary-card">';
            echo '<h4>Records Without Layer</h4>';
            echo '<div class="number">' . count($recordsToDelete) . '</div>';
            echo '</div>';
            echo '<div class="summary-card">';
            echo '<h4>Orphaned Records</h4>';
            echo '<div class="number">' . count($orphanedRecords) . '</div>';
            echo '</div>';
            echo '<div class="summary-card">';
            echo '<h4>Total to Delete</h4>';
            echo '<div class="number">' . $totalToDelete . '</div>';
            echo '</div>';
            echo '<div class="summary-card">';
            echo '<h4>Remaining Records</h4>';
            $stmt = $conn->query("SELECT COUNT(*) as count FROM deceased_records");
            $totalRecords = $stmt->fetch()['count'];
            echo '<div class="number">' . ($totalRecords - $totalToDelete) . '</div>';
            echo '</div>';
            echo '</div>';
            
            if ($totalToDelete > 0) {
                echo '<div class="danger">';
                echo '<h3>⚠️ WARNING: This action cannot be undone!</h3>';
                echo '<p>You are about to permanently delete <strong>' . $totalToDelete . '</strong> burial records:</p>';
                echo '<ul>';
                echo '<li>' . count($recordsToDelete) . ' records without layer assignments</li>';
                echo '<li>' . count($orphanedRecords) . ' records with orphaned layer assignments</li>';
                echo '</ul>';
                echo '<p>After deletion, you will have <strong>' . ($totalRecords - $totalToDelete) . '</strong> properly configured burial records remaining.</p>';
                echo '</div>';
                
                echo '<div class="warning-box">';
                echo '<h3>📋 Records That Will Be Deleted:</h3>';
                
                if (!empty($recordsToDelete)) {
                    echo '<h4>Records Without Layer Assignment:</h4>';
                    echo '<table>';
                    echo '<tr><th>Name</th><th>Lot</th><th>Section</th><th>Date of Death</th></tr>';
                    
                    foreach (array_slice($recordsToDelete, 0, 10) as $record) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($record['full_name']) . '</td>';
                        echo '<td>' . htmlspecialchars($record['lot_number'] ?? 'Unknown') . '</td>';
                        echo '<td>' . htmlspecialchars($record['section'] ?? 'Unknown') . '</td>';
                        echo '<td>' . ($record['date_of_death'] ? date('M d, Y', strtotime($record['date_of_death'])) : 'N/A') . '</td>';
                        echo '</tr>';
                    }
                    
                    if (count($recordsToDelete) > 10) {
                        echo '<tr><td colspan="4" style="text-align: center; font-style: italic;">... and ' . (count($recordsToDelete) - 10) . ' more records</td></tr>';
                    }
                    echo '</table>';
                }
                
                if (!empty($orphanedRecords)) {
                    echo '<h4>Orphaned Records:</h4>';
                    echo '<table>';
                    echo '<tr><th>Name</th><th>Lot</th><th>Layer</th><th>Section</th></tr>';
                    
                    foreach (array_slice($orphanedRecords, 0, 10) as $record) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($record['full_name']) . '</td>';
                        echo '<td>' . htmlspecialchars($record['lot_number'] ?? 'Unknown') . '</td>';
                        echo '<td>Layer ' . $record['layer'] . '</td>';
                        echo '<td>' . htmlspecialchars($record['section'] ?? 'Unknown') . '</td>';
                        echo '</tr>';
                    }
                    
                    if (count($orphanedRecords) > 10) {
                        echo '<tr><td colspan="4" style="text-align: center; font-style: italic;">... and ' . (count($orphanedRecords) - 10) . ' more records</td></tr>';
                    }
                    echo '</table>';
                }
                
                echo '</div>';
                
                echo '<div class="checkbox-group">';
                echo '<label>';
                echo '<input type="checkbox" id="confirmDelete" required>';
                echo '<strong>I understand this will permanently delete ' . $totalToDelete . ' burial records and cannot be undone</strong>';
                echo '</label>';
                echo '<label>';
                echo '<input type="checkbox" id="confirmBackup" required>';
                echo '<strong>I have backed up my data (if needed)</strong>';
                echo '</label>';
                echo '</div>';
                
                echo '<button onclick="deleteRecords()" id="deleteBtn" disabled>🗑️ Delete Old Records</button>';
                echo '<button class="safe" onclick="showDetails()">📋 Show All Records</button>';
                
                echo '<div id="details" style="display: none; margin-top: 20px;">';
                echo '<h3>📋 Complete List of Records to Delete</h3>';
                
                echo '<h4>All Records Without Layer Assignment (' . count($recordsToDelete) . '):</h4>';
                echo '<table>';
                echo '<tr><th>ID</th><th>Name</th><th>Lot</th><th>Section</th><th>Date of Death</th></tr>';
                
                foreach ($recordsToDelete as $record) {
                    echo '<tr>';
                    echo '<td>' . $record['id'] . '</td>';
                    echo '<td>' . htmlspecialchars($record['full_name']) . '</td>';
                    echo '<td>' . htmlspecialchars($record['lot_number'] ?? 'Unknown') . '</td>';
                    echo '<td>' . htmlspecialchars($record['section'] ?? 'Unknown') . '</td>';
                    echo '<td>' . ($record['date_of_death'] ? date('M d, Y', strtotime($record['date_of_death'])) : 'N/A') . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
                
                echo '<h4>All Orphaned Records (' . count($orphanedRecords) . '):</h4>';
                echo '<table>';
                echo '<tr><th>ID</th><th>Name</th><th>Lot</th><th>Layer</th><th>Section</th></tr>';
                
                foreach ($orphanedRecords as $record) {
                    echo '<tr>';
                    echo '<td>' . $record['id'] . '</td>';
                    echo '<td>' . htmlspecialchars($record['full_name']) . '</td>';
                    echo '<td>' . htmlspecialchars($record['lot_number'] ?? 'Unknown') . '</td>';
                    echo '<td>Layer ' . $record['layer'] . '</td>';
                    echo '<td>' . htmlspecialchars($record['section'] ?? 'Unknown') . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
                
                echo '</div>';
                
            } else {
                echo '<div class="status success">🎉 No old burial records to delete! All records are properly configured.</div>';
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
            
            // Enable/disable delete button based on checkboxes
            document.addEventListener('change', function() {
                const confirmDelete = document.getElementById('confirmDelete').checked;
                const confirmBackup = document.getElementById('confirmBackup').checked;
                const deleteBtn = document.getElementById('deleteBtn');
                
                deleteBtn.disabled = !(confirmDelete && confirmBackup);
            });
            
            function deleteRecords() {
                const confirmDelete = document.getElementById('confirmDelete').checked;
                const confirmBackup = document.getElementById('confirmBackup').checked;
                
                if (!confirmDelete || !confirmBackup) {
                    alert('Please confirm both checkboxes before proceeding.');
                    return;
                }
                
                if (!confirm('⚠️ FINAL WARNING: This will permanently delete all old burial records. Continue?')) {
                    return;
                }
                
                const button = event.target;
                button.disabled = true;
                button.textContent = '⏳ Deleting...';
                
                fetch('cleanup_old_burials.php?action=delete', {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    const resultsDiv = document.getElementById('results');
                    if (data.success) {
                        resultsDiv.innerHTML = '<div class="status success">' + data.message + '</div>';
                        setTimeout(() => {
                            window.location.href = 'burial-records.php';
                        }, 3000);
                    } else {
                        resultsDiv.innerHTML = '<div class="status error">❌ ' + data.message + '</div>';
                        button.disabled = false;
                        button.textContent = '🗑️ Delete Old Records';
                    }
                })
                .catch(error => {
                    document.getElementById('results').innerHTML = '<div class="status error">❌ Error: ' + error.message + '</div>';
                    button.disabled = false;
                    button.textContent = '🗑️ Delete Old Records';
                });
            }
        </script>
    </div>
</body>
</html>

<?php
// Handle the delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'delete') {
    header('Content-Type: application/json');
    
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        if (!$conn) {
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            exit;
        }
        
        $deletedCount = 0;
        $errorCount = 0;
        
        // Start transaction
        $conn->beginTransaction();
        
        try {
            // 1. Delete records without layer assignment
            $stmt = $conn->query("
                SELECT id FROM deceased_records 
                WHERE layer IS NULL OR layer = 0
            ");
            $recordsWithoutLayer = $stmt->fetchAll();
            
            foreach ($recordsWithoutLayer as $record) {
                // Delete any associated burial images first
                $imageStmt = $conn->prepare("DELETE FROM burial_record_images WHERE burial_record_id = :id");
                $imageStmt->bindParam(':id', $record['id']);
                $imageStmt->execute();
                
                // Delete the burial record
                $deleteStmt = $conn->prepare("DELETE FROM deceased_records WHERE id = :id");
                $deleteStmt->bindParam(':id', $record['id']);
                
                if ($deleteStmt->execute()) {
                    $deletedCount++;
                } else {
                    $errorCount++;
                }
            }
            
            // 2. Delete orphaned records (records with layer but no lot_layers entry)
            $stmt = $conn->query("
                SELECT dr.id FROM deceased_records dr 
                LEFT JOIN lot_layers ll ON dr.lot_id = ll.lot_id AND dr.layer = ll.layer_number
                WHERE dr.layer IS NOT NULL AND dr.layer > 0 
                AND ll.id IS NULL
            ");
            $orphanedRecords = $stmt->fetchAll();
            
            foreach ($orphanedRecords as $record) {
                // Delete any associated burial images first
                $imageStmt = $conn->prepare("DELETE FROM burial_record_images WHERE burial_record_id = :id");
                $imageStmt->bindParam(':id', $record['id']);
                $imageStmt->execute();
                
                // Delete the burial record
                $deleteStmt = $conn->prepare("DELETE FROM deceased_records WHERE id = :id");
                $deleteStmt->bindParam(':id', $record['id']);
                
                if ($deleteStmt->execute()) {
                    $deletedCount++;
                } else {
                    $errorCount++;
                }
            }
            
            // 3. Clean up lot_layers table - remove entries that don't correspond to existing burial records
            $stmt = $conn->query("
                UPDATE lot_layers 
                SET is_occupied = 0, burial_record_id = NULL 
                WHERE burial_record_id NOT IN (SELECT id FROM deceased_records)
            ");
            
            // 4. Update lot statuses based on remaining layer occupancy
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
                'message' => "✅ Successfully deleted $deletedCount old burial records. " . ($errorCount > 0 ? "$errorCount errors occurred." : "All records cleaned up properly.") . " Redirecting to burial records..."
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()]);
    }
    exit;
}
?>
