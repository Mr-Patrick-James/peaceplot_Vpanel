<?php
header('Content-Type: application/json');

require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Check if tables exist
    $tables = ['deceased_records', 'cemetery_lots', 'burial_record_images'];
    $tableStatus = [];
    
    foreach ($tables as $table) {
        $stmt = $conn->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=:table");
        $stmt->execute([':table' => $table]);
        $exists = $stmt->fetch();
        
        $tableStatus[$table] = $exists ? 'exists' : 'missing';
        
        if ($exists) {
            $countStmt = $conn->query("SELECT COUNT(*) as count FROM $table");
            $count = $countStmt->fetch();
            $tableStatus[$table] .= " ({$count['count']} records)";
        }
    }
    
    echo json_encode([
        'success' => true,
        'tables' => $tableStatus,
        'database_path' => __DIR__ . '/database/peaceplot.db'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
