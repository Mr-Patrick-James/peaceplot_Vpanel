<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
if (!$conn) { die("No connection"); }

// Fetch all records missing burial date but have a death date
$stmt = $conn->query("
    SELECT id, full_name, date_of_death 
    FROM deceased_records 
    WHERE (date_of_burial IS NULL OR date_of_burial = '') 
      AND date_of_death IS NOT NULL 
      AND date_of_death != ''
");
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
echo "Found " . count($records) . " records to update.\n\n";

$updated = 0;
foreach ($records as $r) {
    // Random 7 or 8 days after death
    $daysAfter = rand(7, 8);
    $burialDate = date('Y-m-d', strtotime($r['date_of_death'] . " +{$daysAfter} days"));

    $upd = $conn->prepare("UPDATE deceased_records SET date_of_burial = :burial WHERE id = :id");
    $upd->execute([':burial' => $burialDate, ':id' => $r['id']]);

    echo "ID {$r['id']} | {$r['full_name']} | Died: {$r['date_of_death']} | Buried: {$burialDate} (+{$daysAfter} days)\n";
    $updated++;
}

echo "\nDone. Updated $updated records.\n";
echo "</pre>";
