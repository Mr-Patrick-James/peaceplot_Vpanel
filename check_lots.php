<?php
header('Content-Type: application/json');

require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    // Check if cemetery_lots table exists
    $tableCheck = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='cemetery_lots'");
    $tableExists = $tableCheck->fetch() !== false;
    
    if (!$tableExists) {
        echo json_encode(['success' => false, 'message' => 'cemetery_lots table does not exist']);
        exit;
    }
    
    // Count lots
    $countStmt = $conn->query("SELECT COUNT(*) as count FROM cemetery_lots");
    $count = $countStmt->fetch();
    
    // Get all lots
    $lotsStmt = $conn->query("SELECT id, lot_number, section, block, status FROM cemetery_lots ORDER BY lot_number");
    $lots = $lotsStmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'count' => $count['count'],
        'lots' => $lots
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
