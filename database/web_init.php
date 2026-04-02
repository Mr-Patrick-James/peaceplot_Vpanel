<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PeacePlot Database Initialization</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: #22c55e; }
        .error { color: #ef4444; }
        .info { color: #3b82f6; }
        pre { background: #f3f4f6; padding: 15px; border-radius: 5px; overflow-x: auto; }
        h1 { color: #1f2937; }
        .step { margin: 20px 0; padding: 15px; border-left: 4px solid #3b82f6; background: #f9fafb; }
    </style>
</head>
<body>
    <h1>PeacePlot Database Initialization</h1>
    
    <?php
    echo "<div class='step'>";
    echo "<h3>Step 1: Checking SQLite Support</h3>";
    
    if (!extension_loaded('pdo_sqlite')) {
        echo "<p class='error'>✗ PDO SQLite extension is NOT loaded</p>";
        echo "<p class='info'><strong>To enable SQLite in WAMP:</strong></p>";
        echo "<ol>";
        echo "<li>Click WAMP icon in system tray</li>";
        echo "<li>Go to PHP → PHP Extensions</li>";
        echo "<li>Check: <code>php_pdo_sqlite</code></li>";
        echo "<li>Check: <code>php_sqlite3</code></li>";
        echo "<li>Restart WAMP (All Services)</li>";
        echo "<li>Refresh this page</li>";
        echo "</ol>";
        echo "</div>";
        exit;
    }
    
    echo "<p class='success'>✓ PDO SQLite extension is loaded</p>";
    
    try {
        $test_db = new PDO('sqlite::memory:');
        $version = $test_db->query('SELECT sqlite_version()')->fetch()[0];
        echo "<p class='success'>✓ SQLite Version: {$version}</p>";
    } catch (PDOException $e) {
        echo "<p class='error'>✗ Error testing SQLite: " . $e->getMessage() . "</p>";
        echo "</div>";
        exit;
    }
    echo "</div>";
    
    // Include database class
    require_once __DIR__ . '/../config/database.php';
    
    echo "<div class='step'>";
    echo "<h3>Step 2: Creating Database Connection</h3>";
    
    $database = new Database();
    $conn = $database->getConnection();
    
    if ($conn) {
        echo "<p class='success'>✓ Database connection established</p>";
        $db_path = __DIR__ . '/peaceplot.db';
        echo "<p class='info'>Database location: <code>{$db_path}</code></p>";
    } else {
        echo "<p class='error'>✗ Failed to connect to database</p>";
        echo "</div>";
        exit;
    }
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h3>Step 3: Creating Database Schema</h3>";
    
    if ($database->initializeDatabase()) {
        echo "<p class='success'>✓ Database schema created successfully</p>";
        
        // Show created tables
        $tables = $conn->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll();
        echo "<p class='info'>Tables created:</p><ul>";
        foreach ($tables as $table) {
            echo "<li>{$table['name']}</li>";
        }
        echo "</ul>";
    } else {
        echo "<p class='error'>✗ Failed to create database schema</p>";
        echo "</div>";
        exit;
    }
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h3>Step 4: Seeding Database with Sample Data</h3>";
    
    if ($database->seedDatabase()) {
        echo "<p class='success'>✓ Database seeded successfully</p>";
        
        // Show counts
        $lot_count = $conn->query("SELECT COUNT(*) FROM cemetery_lots")->fetchColumn();
        $deceased_count = $conn->query("SELECT COUNT(*) FROM deceased_records")->fetchColumn();
        $user_count = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
        
        echo "<p class='info'>Sample data inserted:</p>";
        echo "<ul>";
        echo "<li>Cemetery Lots: {$lot_count}</li>";
        echo "<li>Deceased Records: {$deceased_count}</li>";
        echo "<li>Users: {$user_count}</li>";
        echo "</ul>";
    } else {
        echo "<p class='error'>✗ Failed to seed database</p>";
        echo "</div>";
        exit;
    }
    echo "</div>";
    
    $database->closeConnection();
    
    echo "<div class='step' style='border-left-color: #22c55e; background: #f0fdf4;'>";
    echo "<h3>✓ Database Initialization Complete!</h3>";
    echo "<p>Your PeacePlot database is ready for CRUD operations.</p>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ul>";
    echo "<li><a href='../public/dashboard.html'>Go to Dashboard</a></li>";
    echo "<li><a href='../public/index.html'>Manage Cemetery Lots</a></li>";
    echo "</ul>";
    echo "</div>";
    ?>
</body>
</html>
