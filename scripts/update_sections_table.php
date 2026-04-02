<?php
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Check if blocks table exists
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='blocks'")->fetch();
    if (!$result) {
        echo "Creating blocks table...\n";
        $db->exec("CREATE TABLE blocks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(100) NOT NULL UNIQUE,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        echo "Blocks table created.\n";
    } else {
        echo "Blocks table already exists.\n";
    }

    // Check if sections table has block_id column
    $columns = $db->query("PRAGMA table_info(sections)")->fetchAll(PDO::FETCH_ASSOC);
    $hasBlockId = false;
    foreach ($columns as $column) {
        if ($column['name'] === 'block_id') {
            $hasBlockId = true;
            break;
        }
    }

    if (!$hasBlockId) {
        echo "Adding block_id to sections table...\n";
        $db->exec("ALTER TABLE sections ADD COLUMN block_id INTEGER REFERENCES blocks(id) ON DELETE SET NULL");
        echo "Column block_id added to sections table.\n";
    } else {
        echo "Column block_id already exists in sections table.\n";
    }

    echo "Database updated successfully.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>