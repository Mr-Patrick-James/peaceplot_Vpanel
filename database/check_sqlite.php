<?php
// Check if SQLite PDO driver is available
echo "Checking SQLite Support...\n\n";

if (extension_loaded('pdo_sqlite')) {
    echo "✓ PDO SQLite extension is loaded\n";
    
    // Try to create a test database
    try {
        $db = new PDO('sqlite::memory:');
        echo "✓ SQLite PDO connection successful\n";
        echo "SQLite Version: " . $db->query('SELECT sqlite_version()')->fetch()[0] . "\n";
    } catch (PDOException $e) {
        echo "✗ Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "✗ PDO SQLite extension is NOT loaded\n";
    echo "\nTo enable SQLite in WAMP:\n";
    echo "1. Open php.ini file (WAMP icon > PHP > php.ini)\n";
    echo "2. Find and uncomment these lines (remove semicolon):\n";
    echo "   extension=pdo_sqlite\n";
    echo "   extension=sqlite3\n";
    echo "3. Restart WAMP server\n";
}

echo "\nLoaded PDO drivers:\n";
print_r(PDO::getAvailableDrivers());
?>
