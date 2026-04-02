<?php
require_once 'config/database.php';

echo "Testing database connection...\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($conn) {
        echo "✓ Database connected successfully\n";
        
        // Test if tables exist
        $tables = ['cemetery_lots', 'deceased_records', 'burial_record_images'];
        foreach ($tables as $table) {
            $stmt = $conn->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=:table");
            $stmt->execute([':table' => $table]);
            $exists = $stmt->fetch();
            echo $exists ? "✓ Table '$table' exists\n" : "✗ Table '$table' missing\n";
        }
        
        // Test burial records query
        $stmt = $conn->query("SELECT COUNT(*) as count FROM deceased_records");
        $count = $stmt->fetch();
        echo "✓ Found {$count['count']} burial records\n";
        
        // Test burial images query
        $stmt = $conn->query("SELECT COUNT(*) as count FROM burial_record_images");
        $count = $stmt->fetch();
        echo "✓ Found {$count['count']} burial images\n";
        
    } else {
        echo "✗ Database connection failed\n";
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
