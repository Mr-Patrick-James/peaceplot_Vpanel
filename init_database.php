<?php
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/config/database.php';
    
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    // Read and execute schema
    $schemaFile = __DIR__ . '/database/schema.sql';
    if (file_exists($schemaFile)) {
        $schema = file_get_contents($schemaFile);
        
        // Split schema into individual statements
        $statements = array_filter(array_map('trim', explode(';', $schema)));
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $conn->exec($statement);
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Database schema initialized']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Schema file not found']);
    }
    
    // Read and execute seed data
    $seedFile = __DIR__ . '/database/seed.sql';
    if (file_exists($seedFile)) {
        $seed = file_get_contents($seedFile);
        
        // Split seed into individual statements
        $statements = array_filter(array_map('trim', explode(';', $seed)));
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $conn->exec($statement);
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Database schema and seed data initialized']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database initialization failed: ' . $e->getMessage()]);
}
?>
