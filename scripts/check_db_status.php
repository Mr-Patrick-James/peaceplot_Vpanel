<?php
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("Database connection failed.\n");
}

try {
    // Check for blocks table
    $blocksExists = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='blocks'")->fetch();
    echo "Blocks table: " . ($blocksExists ? "EXISTS" : "NOT FOUND") . "\n";

    // Check for block_id in sections table
    $columns = $db->query("PRAGMA table_info(sections)")->fetchAll(PDO::FETCH_ASSOC);
    $hasBlockId = false;
    foreach ($columns as $column) {
        if ($column['name'] === 'block_id') {
            $hasBlockId = true;
            break;
        }
    }
    echo "block_id in sections: " . ($hasBlockId ? "EXISTS" : "NOT FOUND") . "\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>