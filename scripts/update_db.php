<?php
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

if ($conn) {
    try {
        // Check if is_archived column exists
        $stmt = $conn->prepare("PRAGMA table_info(activity_logs)");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $hasArchived = false;
        foreach ($columns as $column) {
            if ($column['name'] === 'is_archived') {
                $hasArchived = true;
                break;
            }
        }
        
        if (!$hasArchived) {
            $conn->exec("ALTER TABLE activity_logs ADD COLUMN is_archived BOOLEAN DEFAULT 0");
            echo "Successfully added is_archived column to activity_logs table.\n";
        } else {
            echo "is_archived column already exists in activity_logs table.\n";
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "Database connection failed.\n";
}
?>
