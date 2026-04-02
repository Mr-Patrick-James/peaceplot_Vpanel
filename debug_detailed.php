<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

echo json_encode(['step' => 'Starting debug...']);

try {
    // Check if database file exists
    $dbPath = __DIR__ . '/database/peaceplot.db';
    echo json_encode(['step' => 'Database file: ' . $dbPath . ' - ' . (file_exists($dbPath) ? 'EXISTS' : 'MISSING')]);
    
    if (!file_exists($dbPath)) {
        echo json_encode(['error' => 'Database file does not exist']);
        exit;
    }
    
    // Check if database is readable
    if (!is_readable($dbPath)) {
        echo json_encode(['error' => 'Database file is not readable']);
        exit;
    }
    
    echo json_encode(['step' => 'Including database config...']);
    require_once __DIR__ . '/config/database.php';
    
    echo json_encode(['step' => 'Creating Database object...']);
    $database = new Database();
    
    echo json_encode(['step' => 'Getting connection...']);
    $conn = $database->getConnection();
    
    if (!$conn) {
        echo json_encode(['error' => 'Database connection returned null']);
        exit;
    }
    
    echo json_encode(['step' => 'Connection successful']);
    
    // Test simple query
    echo json_encode(['step' => 'Testing simple query...']);
    $result = $conn->query("SELECT 1 as test");
    $row = $result->fetch();
    echo json_encode(['step' => 'Simple query result: ' . json_encode($row)]);
    
    // Check tables
    echo json_encode(['step' => 'Checking tables...']);
    $tablesQuery = $conn->query("SELECT name FROM sqlite_master WHERE type='table'");
    $tables = $tablesQuery->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(['step' => 'Tables found: ' . implode(', ', $tables)]);
    
    // Check deceased_records table
    if (in_array('deceased_records', $tables)) {
        echo json_encode(['step' => 'Checking deceased_records table...']);
        $countQuery = $conn->query("SELECT COUNT(*) as count FROM deceased_records");
        $count = $countQuery->fetch();
        echo json_encode(['step' => 'Deceased records count: ' . $count['count']]);
        
        // Get actual records
        $recordsQuery = $conn->query("SELECT * FROM deceased_records LIMIT 5");
        $records = $recordsQuery->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['step' => 'Sample records: ' . json_encode($records)]);
    } else {
        echo json_encode(['error' => 'deceased_records table does not exist']);
    }
    
    echo json_encode(['success' => true, 'message' => 'Debug completed successfully']);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Exception: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
} catch (Error $e) {
    echo json_encode(['error' => 'Fatal Error: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
}
?>
