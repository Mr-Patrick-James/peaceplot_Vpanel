<?php
/**
 * Web-based Database Update Script
 * Run this in your browser: http://localhost/peaceplot/public/run_update.php
 */

require_once __DIR__ . '/../config/database.php';

echo "<h1>PeacePlot Database Update</h1>";

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("<p style='color:red'>✗ Database connection failed. Please ensure PDO SQLite is enabled in WAMP.</p>");
}

try {
    echo "<h3>Updating Database Structure...</h3>";

    // 1. Create blocks table if not exists
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='blocks'")->fetch();
    if (!$result) {
        echo "<p>Creating <b>blocks</b> table...</p>";
        $db->exec("CREATE TABLE blocks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(100) NOT NULL UNIQUE,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        echo "<p style='color:green'>✓ Blocks table created.</p>";
    } else {
        echo "<p>✓ Blocks table already exists.</p>";
    }

    // 2. Check if sections table has block_id column
    $columns = $db->query("PRAGMA table_info(sections)")->fetchAll(PDO::FETCH_ASSOC);
    $hasBlockId = false;
    foreach ($columns as $column) {
        if ($column['name'] === 'block_id') {
            $hasBlockId = true;
            break;
        }
    }

    if (!$hasBlockId) {
        echo "<p>Adding <b>block_id</b> to sections table...</p>";
        $db->exec("ALTER TABLE sections ADD COLUMN block_id INTEGER REFERENCES blocks(id) ON DELETE SET NULL");
        echo "<p style='color:green'>✓ Column <b>block_id</b> added to sections table.</p>";
    } else {
        echo "<p>✓ Column <b>block_id</b> already exists in sections table.</p>";
    }

    echo "<h2 style='color:green'>✓ Database updated successfully!</h2>";
    echo "<p><a href='sections.php'>Go back to Section Management</a></p>";

} catch (PDOException $e) {
    echo "<p style='color:red'>✗ Error: " . $e->getMessage() . "</p>";
}
?>