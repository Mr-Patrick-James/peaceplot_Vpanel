<?php
require_once __DIR__ . '/../config/auth.php';
requireLogin();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/logger.php';

$database = new Database();
$db = $database->getConnection();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $stmt = $db->query("SELECT * FROM blocks ORDER BY name ASC");
        $blocks = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $blocks]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Block name is required']);
        exit;
    }

    try {
        $stmt = $db->prepare("INSERT INTO blocks (name, description, map_x, map_y, map_width, map_height) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['name'], 
            $data['description'] ?? '',
            $data['map_x'] ?? null,
            $data['map_y'] ?? null,
            $data['map_width'] ?? null,
            $data['map_height'] ?? null
        ]);
        $lastId = $db->lastInsertId();
        
        logActivity($db, 'ADD_BLOCK', 'blocks', $lastId, "New block '" . $data['name'] . "' added");
        
        echo json_encode(['success' => true, 'id' => $lastId, 'message' => 'Block created successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        $message = $e->getMessage();
        if (strpos($message, 'UNIQUE constraint failed: blocks.name') !== false) {
            $message = "Block '" . $data['name'] . "' already exists.";
        }
        echo json_encode(['success' => false, 'message' => $message]);
    }
} elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id']) || empty($data['name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID and name are required']);
        exit;
    }

    try {
        $stmt = $db->prepare("UPDATE blocks SET name = ?, description = ?, map_x = ?, map_y = ?, map_width = ?, map_height = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([
            $data['name'], 
            $data['description'] ?? '', 
            $data['map_x'] ?? null,
            $data['map_y'] ?? null,
            $data['map_width'] ?? null,
            $data['map_height'] ?? null,
            $data['id']
        ]);
        
        logActivity($db, 'UPDATE_BLOCK', 'blocks', $data['id'], "Block '" . $data['name'] . "' updated");
        
        echo json_encode(['success' => true, 'message' => 'Block updated successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        $message = $e->getMessage();
        if (strpos($message, 'UNIQUE constraint failed: blocks.name') !== false) {
            $message = "Block '" . $data['name'] . "' already exists.";
        }
        echo json_encode(['success' => false, 'message' => $message]);
    }
} elseif ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;
    }
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        exit;
    }

    try {
        // Get name for logging before deletion
        $nameStmt = $db->prepare("SELECT name FROM blocks WHERE id = ?");
        $nameStmt->execute([$id]);
        $blockName = $nameStmt->fetchColumn() ?: "ID $id";

        $stmt = $db->prepare("DELETE FROM blocks WHERE id = ?");
        $stmt->execute([$id]);
        
        logActivity($db, 'DELETE_BLOCK', 'blocks', $id, "Block '$blockName' deleted");
        
        echo json_encode(['success' => true, 'message' => 'Block deleted successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>