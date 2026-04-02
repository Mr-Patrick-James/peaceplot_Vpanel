<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
if (!$conn) { die("No connection"); }

$rows = $conn->query('SELECT id, full_name, date_of_birth, date_of_death, date_of_burial FROM deceased_records ORDER BY id LIMIT 30')->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
foreach($rows as $r) {
    echo $r['id'] . ' | ' . $r['full_name'] . ' | DOB:' . $r['date_of_birth'] . ' | DOD:' . $r['date_of_death'] . ' | Burial:' . $r['date_of_burial'] . "\n";
}
echo 'Total: ' . $conn->query('SELECT COUNT(*) FROM deceased_records')->fetchColumn() . "\n";
echo 'Missing burial date: ' . $conn->query("SELECT COUNT(*) FROM deceased_records WHERE date_of_burial IS NULL OR date_of_burial = ''")->fetchColumn() . "\n";
echo 'Missing death date: ' . $conn->query("SELECT COUNT(*) FROM deceased_records WHERE date_of_death IS NULL OR date_of_death = ''")->fetchColumn() . "\n";
echo 'Missing birth date: ' . $conn->query("SELECT COUNT(*) FROM deceased_records WHERE date_of_birth IS NULL OR date_of_birth = ''")->fetchColumn() . "\n";
echo "</pre>";
