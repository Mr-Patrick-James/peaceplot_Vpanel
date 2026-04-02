<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/logger.php';

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['lots']) || !is_array($input['lots'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

try {
    $conn->beginTransaction();
    
    foreach ($input['lots'] as $lot) {
        $stmt = $conn->prepare("
            UPDATE cemetery_lots 
            SET map_x = :map_x, 
                map_y = :map_y,
                map_width = :map_width,
                map_height = :map_height,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        
        $stmt->bindValue(':id', $lot['id'], PDO::PARAM_INT);

        if ($lot['map_x'] === null) {
            $stmt->bindValue(':map_x', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':map_x', (float)$lot['map_x']);
        }

        if ($lot['map_y'] === null) {
            $stmt->bindValue(':map_y', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':map_y', (float)$lot['map_y']);
        }

        if ($lot['map_width'] === null) {
            $stmt->bindValue(':map_width', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':map_width', (float)$lot['map_width']);
        }

        if ($lot['map_height'] === null) {
            $stmt->bindValue(':map_height', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':map_height', (float)$lot['map_height']);
        }
        
        $stmt->execute();
    }
    
    $conn->commit();
    
    $lotIds = array_column($input['lots'], 'id');
    $lotNumStr = "";
    if (count($lotIds) > 0) {
        $placeholders = implode(',', array_fill(0, count($lotIds), '?'));
        $lotStmt = $conn->prepare("SELECT lot_number FROM cemetery_lots WHERE id IN ($placeholders)");
        $lotStmt->execute($lotIds);
        $lotNums = $lotStmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($lotNums) <= 3) {
            $lotNumStr = implode(', ', $lotNums);
        } else {
            $lotNumStr = count($lotNums) . " lots";
        }
    }
    
    logActivity($conn, 'UPDATE_MAP', 'cemetery_lots', null, "Map position for $lotNumStr is updated");
    echo json_encode(['success' => true, 'message' => 'Coordinates saved successfully']);
    
} catch (PDOException $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
