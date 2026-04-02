<?php
require_once __DIR__ . '/../config/auth.php';
requireLogin();

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

header('Content-Type: application/json');

$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    echo json_encode(['success' => true, 'data' => []]);
    exit;
}

try {
    $results = [];
    
    // Split query into keywords
    $keywords = array_filter(explode(' ', $query), function($k) { return strlen($k) >= 1; });
    
    $searchParams = [];
    $lotWhereConditions = [];
    $sectionWhereConditions = [];
    $blockWhereConditions = [];
    $deceasedWhereConditions = [];
    
    foreach ($keywords as $index => $keyword) {
        $p = ":p" . $index;
        $searchParams[$p] = "%$keyword%";
        
        // Lot search conditions: match any keyword against lot number, section name, or block name
        $lotWhereConditions[] = "(LOWER(cl.lot_number) LIKE LOWER($p) OR LOWER(s.name) LIKE LOWER($p) OR LOWER(b.name) LIKE LOWER($p))";
        
        // Section search conditions
        $sectionWhereConditions[] = "(LOWER(s.name) LIKE LOWER($p) OR LOWER(b.name) LIKE LOWER($p))";
        
        // Block search conditions
        $blockWhereConditions[] = "(LOWER(name) LIKE LOWER($p))";
        
        // Deceased search conditions
        $deceasedWhereConditions[] = "(LOWER(full_name) LIKE LOWER($p))";
    }
    
    $lotWhere = implode(" AND ", $lotWhereConditions);
    $sectionWhere = implode(" AND ", $sectionWhereConditions);
    $blockWhere = implode(" AND ", $blockWhereConditions);
    $deceasedWhere = implode(" AND ", $deceasedWhereConditions);

    // Search Lots
    $stmt = $db->prepare("
        SELECT cl.id, cl.lot_number as title, s.name as section_name, b.name as block_name, 'lot' as type 
        FROM cemetery_lots cl
        LEFT JOIN sections s ON cl.section_id = s.id
        LEFT JOIN blocks b ON s.block_id = b.id
        WHERE $lotWhere
        LIMIT 10
    ");
    $stmt->execute($searchParams);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'id' => $row['id'],
            'title' => "Lot " . $row['title'],
            'subtitle' => "Section: " . ($row['section_name'] ?: 'N/A') . " | Block: " . ($row['block_name'] ?: 'N/A'),
            'type' => 'lot',
            'url' => "index.php?search=" . urlencode($row['title']) . "&q=" . urlencode($query)
        ];
    }

    // Search Sections
    $stmt = $db->prepare("
        SELECT s.id, s.name as title, b.name as block_name, 'section' as type 
        FROM sections s
        LEFT JOIN blocks b ON s.block_id = b.id
        WHERE $sectionWhere
        LIMIT 5
    ");
    $stmt->execute($searchParams);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'id' => $row['id'],
            'title' => "Section: " . $row['title'],
            'subtitle' => "Block: " . ($row['block_name'] ?: 'N/A'),
            'type' => 'section',
            'url' => "sections.php?search=" . urlencode($row['title']) . "&q=" . urlencode($query)
        ];
    }

    // Search Blocks
    $stmt = $db->prepare("
        SELECT id, name as title, 'block' as type 
        FROM blocks 
        WHERE $blockWhere
        LIMIT 5
    ");
    $stmt->execute($searchParams);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'id' => $row['id'],
            'title' => "Block: " . $row['title'],
            'subtitle' => "Cemetery Block",
            'type' => 'block',
            'url' => "blocks.php?search=" . urlencode($row['title']) . "&q=" . urlencode($query)
        ];
    }

    // Search Deceased Records
    $stmt = $db->prepare("
        SELECT id, full_name as title, date_of_death, 'deceased' as type 
        FROM deceased_records 
        WHERE $deceasedWhere
        LIMIT 5
    ");
    $stmt->execute($searchParams);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $results[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'subtitle' => "Deceased Record" . ($row['date_of_death'] ? " (Died: " . $row['date_of_death'] . ")" : ""),
            'type' => 'deceased',
            'url' => "burial-records.php?search=" . urlencode($row['title']) . "&q=" . urlencode($query)
        ];
    }

    echo json_encode(['success' => true, 'data' => $results]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>