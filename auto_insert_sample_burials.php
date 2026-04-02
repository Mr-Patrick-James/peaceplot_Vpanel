<?php
require_once __DIR__ . '/config/database.php';

$database = new Database();
$conn = $database->getConnection();

// First, get available lots
$availableLots = [];
try {
    $stmt = $conn->query("SELECT id, lot_number FROM cemetery_lots ORDER BY lot_number LIMIT 10");
    $availableLots = $stmt->fetchAll();
} catch (Exception $e) {
    echo "Error getting lots: " . $e->getMessage();
    exit;
}

if (empty($availableLots)) {
    echo "<h1>❌ No Available Lots</h1>";
    echo "<p>Please create some cemetery lots first using the Lot Management tool.</p>";
    exit;
}

// Sample burial records to insert automatically
$sampleBurials = [
    [
        'full_name' => 'Juan Dela Cruz',
        'age' => 75,
        'date_of_birth' => '1948-03-15',
        'date_of_death' => '2023-12-01',
        'date_of_burial' => '2023-12-03',
        'cause_of_death' => 'Natural Causes',
        'next_of_kin' => 'Maria Dela Cruz',
        'next_of_kin_contact' => '09123456789',
        'remarks' => 'Peaceful passing at home surrounded by family'
    ],
    [
        'full_name' => 'Maria Santos',
        'age' => 82,
        'date_of_birth' => '1941-07-22',
        'date_of_death' => '2023-11-15',
        'date_of_burial' => '2023-11-17',
        'cause_of_death' => 'Cardiac Arrest',
        'next_of_kin' => 'Jose Santos',
        'next_of_kin_contact' => '09876543210',
        'remarks' => 'Passed away in hospital after brief illness'
    ],
    [
        'full_name' => 'Antonio Reyes',
        'age' => 68,
        'date_of_birth' => '1955-11-08',
        'date_of_death' => '2024-01-10',
        'date_of_burial' => '2024-01-12',
        'cause_of_death' => 'Stroke',
        'next_of_kin' => 'Luz Reyes',
        'next_of_kin_contact' => '09123456789',
        'remarks' => 'Second burial in this lot, survived by spouse and children'
    ],
    [
        'full_name' => 'Rosa Martinez',
        'age' => 91,
        'date_of_birth' => '1932-09-30',
        'date_of_death' => '2024-02-01',
        'date_of_burial' => '2024-02-03',
        'cause_of_death' => 'Pneumonia',
        'next_of_kin' => 'Carlos Martinez',
        'next_of_kin_contact' => '09234567890',
        'remarks' => 'Beloved grandmother, lived a long and full life'
    ],
    [
        'full_name' => 'Francisco Lim',
        'age' => 57,
        'date_of_birth' => '1966-05-18',
        'date_of_death' => '2024-01-25',
        'date_of_burial' => '2024-01-27',
        'cause_of_death' => 'Vehicle Accident',
        'next_of_kin' => 'Elena Lim',
        'next_of_kin_contact' => '09345678901',
        'remarks' => 'Tragic accident, leaves behind wife and two children'
    ]
];

echo "<h1>🪦 Auto-Inserting Sample Burial Records</h1>";
echo "<p>Found " . count($availableLots) . " available lots. Inserting " . count($sampleBurials) . " sample burial records...</p>";
echo "<p><strong>Note:</strong> Each record will be assigned to the first available lot (unassigned).</p>";

$insertedCount = 0;
$errorCount = 0;

foreach ($sampleBurials as $index => $burial) {
    try {
        // Assign to the first available lot (or use lot_id = 1 as fallback)
        $lotId = !empty($availableLots) ? $availableLots[0]['id'] : 1;
        
        // Insert burial record with lot_id
        $stmt = $conn->prepare("
            INSERT INTO deceased_records 
            (lot_id, full_name, date_of_birth, date_of_death, date_of_burial, age, 
             cause_of_death, next_of_kin, next_of_kin_contact, remarks) 
            VALUES 
            (:lot_id, :full_name, :date_of_birth, :date_of_death, :date_of_burial, :age,
             :cause_of_death, :next_of_kin, :next_of_kin_contact, :remarks)
        ");
        
        $stmt->bindParam(':lot_id', $lotId);
        $stmt->bindParam(':full_name', $burial['full_name']);
        $stmt->bindParam(':date_of_birth', $burial['date_of_birth']);
        $stmt->bindParam(':date_of_death', $burial['date_of_death']);
        $stmt->bindParam(':date_of_burial', $burial['date_of_burial']);
        $stmt->bindParam(':age', $burial['age']);
        $stmt->bindParam(':cause_of_death', $burial['cause_of_death']);
        $stmt->bindParam(':next_of_kin', $burial['next_of_kin']);
        $stmt->bindParam(':next_of_kin_contact', $burial['next_of_kin_contact']);
        $stmt->bindParam(':remarks', $burial['remarks']);
        
        if ($stmt->execute()) {
            $burialId = $conn->lastInsertId();
            echo "<div style='color: #28a745; margin: 10px 0; padding: 10px; background: #d4edda; border-radius: 5px;'>";
            echo "✅ Record " . ($index + 1) . ": <strong>{$burial['full_name']}</strong> added successfully (ID: {$burialId}, Lot ID: {$lotId})";
            echo "</div>";
            $insertedCount++;
        } else {
            echo "<div style='color: #dc3545; margin: 10px 0; padding: 10px; background: #f8d7da; border-radius: 5px;'>";
            echo "❌ Record " . ($index + 1) . ": Failed to insert <strong>{$burial['full_name']}</strong>";
            echo "</div>";
            $errorCount++;
        }
    } catch (Exception $e) {
        echo "<div style='color: #dc3545; margin: 10px 0; padding: 10px; background: #f8d7da; border-radius: 5px;'>";
        echo "❌ Record " . ($index + 1) . ": Error - " . $e->getMessage();
        echo "</div>";
        $errorCount++;
    }
}

echo "<h2>📊 Summary</h2>";
echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<p><strong>Total Records:</strong> " . count($sampleBurials) . "</p>";
echo "<p><strong>✅ Successfully Inserted:</strong> <span style='color: #28a745; font-weight: bold;'>{$insertedCount}</span></p>";
echo "<p><strong>❌ Failed:</strong> <span style='color: #dc3545; font-weight: bold;'>{$errorCount}</span></p>";
echo "</div>";

if ($insertedCount > 0) {
    echo "<div style='background: #d4edda; color: #155724; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3>🎉 Success!</h3>";
    echo "<p>{$insertedCount} burial records have been added to the database.</p>";
    echo "<p>All records were assigned to Lot ID: " . (!empty($availableLots) ? $availableLots[0]['id'] : 1) . "</p>";
    echo "<p>You can now assign these records to specific lots and layers using the Cemetery Map or Lot Management tools.</p>";
    echo "<p><a href='burial-records.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>View Burial Records</a></p>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; color: #856404; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h3>⚠️ No Records Inserted</h3>";
    echo "<p>There were errors inserting the sample records. Please check the database connection.</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><small>Script executed at: " . date('Y-m-d H:i:s') . "</small></p>";
?>
