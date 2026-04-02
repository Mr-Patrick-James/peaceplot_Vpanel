<?php
/**
 * Web-based Database Migration Script for Lots
 * Run this in your browser: http://localhost/peaceplot/public/migrate_lots.php
 */

require_once __DIR__ . '/../config/database.php';

echo "<h1>PeacePlot Lots Migration</h1>";

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die("<p style='color:red'>✗ Database connection failed.</p>");
}

try {
    echo "<h3>Migrating Lots Relationship...</h3>";

    // 1. Add section_id to cemetery_lots if not exists
    $columns = $db->query("PRAGMA table_info(cemetery_lots)")->fetchAll(PDO::FETCH_ASSOC);
    $hasSectionId = false;
    foreach ($columns as $column) {
        if ($column['name'] === 'section_id') {
            $hasSectionId = true;
            break;
        }
    }

    if (!$hasSectionId) {
        echo "<p>Adding <b>section_id</b> to cemetery_lots table...</p>";
        $db->exec("ALTER TABLE cemetery_lots ADD COLUMN section_id INTEGER REFERENCES sections(id) ON DELETE SET NULL");
        echo "<p style='color:green'>✓ Column <b>section_id</b> added.</p>";
    } else {
        echo "<p>✓ Column <b>section_id</b> already exists.</p>";
    }

    // 2. Populate section_id from section string
    echo "<p>Linking lots to sections based on name...</p>";
    $lots = $db->query("SELECT id, section, block FROM cemetery_lots WHERE section_id IS NULL")->fetchAll();
    $migratedCount = 0;
    
    foreach ($lots as $lot) {
        $sectionName = $lot['section'];
        $blockName = $lot['block'];
        
        if (!$sectionName) continue;

        // Find or create section
        $sectionStmt = $db->prepare("SELECT id FROM sections WHERE name = ?");
        $sectionStmt->execute([$sectionName]);
        $sectionId = $sectionStmt->fetchColumn();
        
        if (!$sectionId) {
            // If section doesn't exist, we might want to create it, 
            // but first we need a block_id if we want to follow the rule
            // Let's find or create the block first
            $blockId = null;
            if ($blockName) {
                $blockStmt = $db->prepare("SELECT id FROM blocks WHERE name = ?");
                $blockStmt->execute([$blockName]);
                $blockId = $blockStmt->fetchColumn();
                
                if (!$blockId) {
                    $db->prepare("INSERT INTO blocks (name) VALUES (?)")->execute([$blockName]);
                    $blockId = $db->lastInsertId();
                }
            }
            
            // Create the section
            $db->prepare("INSERT INTO sections (name, block_id) VALUES (?, ?)")->execute([$sectionName, $blockId]);
            $sectionId = $db->lastInsertId();
        }
        
        // Link the lot
        $db->prepare("UPDATE cemetery_lots SET section_id = ? WHERE id = ?")->execute([$sectionId, $lot['id']]);
        $migratedCount++;
    }
    
    echo "<p style='color:green'>✓ Successfully linked $migratedCount lots to sections.</p>";

    // 3. Optional: Create a clean table without the old section/block strings
    // In SQLite, it's safer to just leave them or rename the table.
    // Let's just keep them for now as backup and update the API/UI to use section_id.
    
    echo "<h2 style='color:green'>✓ Migration completed successfully!</h2>";
    echo "<p><a href='index.php'>Go to Lot Management</a></p>";

} catch (PDOException $e) {
    echo "<p style='color:red'>✗ Error: " . $e->getMessage() . "</p>";
}
?>