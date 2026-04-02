<?php
echo "<h2>PHP Configuration Check</h2>";
echo "<h3>PDO Drivers:</h3>";
print_r(PDO::getAvailableDrivers());

echo "<h3>SQLite Extension:</h3>";
echo extension_loaded('sqlite3') ? 'SQLite3 extension loaded' : 'SQLite3 extension NOT loaded';
echo "<br>";
echo extension_loaded('pdo_sqlite') ? 'PDO SQLite extension loaded' : 'PDO SQLite extension NOT loaded';

echo "<h3>PHP Info:</h3>";
phpinfo();
?>
