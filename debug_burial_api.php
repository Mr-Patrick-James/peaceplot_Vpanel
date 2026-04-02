<?php
header('Content-Type: application/json');

require_once __DIR__ . '/config/database.php';

echo json_encode(['debug' => 'Starting debug...']);

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    echo json_encode(['debug' => 'Database connected successfully']);
    
    // Test basic query
    $stmt = $conn->query("SELECT COUNT(*) as count FROM deceased_records");
    $count = $stmt->fetch();
    echo json_encode(['debug' => 'Found ' . $count['count'] . ' burial records']);
    
    // Test the actual query from burial_records.php
    $stmt = $conn->query("
        SELECT dr.*, cl.lot_number, cl.section, cl.block 
        FROM deceased_records dr 
        LEFT JOIN cemetery_lots cl ON dr.lot_id = cl.id 
        ORDER BY dr.date_of_death DESC
    ");
    $results = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true, 
        'data' => $results,
        'count' => count($results)
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'General error: ' . $e->getMessage()]);
}
?>
