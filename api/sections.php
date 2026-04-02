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
        $search = $_GET['search'] ?? '';
        $block_id = $_GET['block_id'] ?? '';
        $lot_min = $_GET['lot_min'] ?? '';
        $lot_max = $_GET['lot_max'] ?? '';
        $start_date = $_GET['start_date'] ?? '';
        $end_date = $_GET['end_date'] ?? '';
        $sort_by = $_GET['sort_by'] ?? 'name';
        $sort_order = $_GET['sort_order'] ?? 'ASC';

        $params = [];
        $where = [];

        if ($search) {
            $where[] = "(s.name LIKE ? OR s.description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if ($block_id) {
            $ids = explode(',', $block_id);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $where[] = "s.block_id IN ($placeholders)";
            foreach ($ids as $id) {
                $params[] = trim($id);
            }
        }

        if ($start_date) {
            $where[] = "date(s.created_at) >= ?";
            $params[] = $start_date;
        }

        if ($end_date) {
            $where[] = "date(s.created_at) <= ?";
            $params[] = $end_date;
        }

        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        $having = [];
        $havingParams = [];
        if ($lot_min !== '') {
            $having[] = "lot_count >= ?";
            $havingParams[] = $lot_min;
        }
        if ($lot_max !== '') {
            $having[] = "lot_count <= ?";
            $havingParams[] = $lot_max;
        }
        $havingClause = !empty($having) ? "HAVING " . implode(" AND ", $having) : "";

        $allowedSort = ['name', 'block_name', 'lot_count', 'created_at'];
        if (!in_array($sort_by, $allowedSort)) $sort_by = 'name';
        $sort_order = strtoupper($sort_order) === 'DESC' ? 'DESC' : 'ASC';

        $query = "
            SELECT s.*, b.name as block_name,
                   (SELECT COUNT(*) FROM cemetery_lots WHERE section_id = s.id) as lot_count
            FROM sections s 
            LEFT JOIN blocks b ON s.block_id = b.id 
            $whereClause
            GROUP BY s.id
            $havingClause
            ORDER BY $sort_by $sort_order
        ";

        $stmt = $db->prepare($query);
        $stmt->execute(array_merge($params, $havingParams));
        $sections = $stmt->fetchAll();
        echo json_encode($sections);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Section name is required']);
        exit;
    }

    if (empty($data['block_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Block selection is required']);
        exit;
    }

    try {
        $stmt = $db->prepare("INSERT INTO sections (name, description, block_id, map_x, map_y, map_width, map_height) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['name'], 
            $data['description'] ?? '',
            $data['block_id'],
            $data['map_x'] ?? null,
            $data['map_y'] ?? null,
            $data['map_width'] ?? null,
            $data['map_height'] ?? null
        ]);
        $newId = $db->lastInsertId();
        logActivity($db, 'ADD_SECTION', 'sections', $newId, "New section '" . $data['name'] . "' added");
        echo json_encode(['id' => $newId, 'message' => 'Section created successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        $error = $e->getMessage();
        if (strpos($error, 'UNIQUE constraint failed: sections.name') !== false) {
            $error = "Section '" . $data['name'] . "' already exists.";
        }
        echo json_encode(['error' => $error]);
    }
} elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id']) || empty($data['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID and name are required']);
        exit;
    }

    if (empty($data['block_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Block selection is required']);
        exit;
    }

    try {
        $stmt = $db->prepare("UPDATE sections SET name = ?, description = ?, block_id = ?, map_x = ?, map_y = ?, map_width = ?, map_height = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([
            $data['name'], 
            $data['description'] ?? '', 
            $data['block_id'],
            $data['map_x'] ?? null,
            $data['map_y'] ?? null,
            $data['map_width'] ?? null,
            $data['map_height'] ?? null,
            $data['id']
        ]);
        logActivity($db, 'UPDATE_SECTION', 'sections', $data['id'], "Section '" . $data['name'] . "' updated");
        echo json_encode(['message' => 'Section updated successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        $error = $e->getMessage();
        if (strpos($error, 'UNIQUE constraint failed: sections.name') !== false) {
            $error = "Section '" . $data['name'] . "' already exists.";
        }
        echo json_encode(['error' => $error]);
    }
} elseif ($method === 'DELETE') {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID is required']);
        exit;
    }

    try {
        $stmt = $db->prepare("DELETE FROM sections WHERE id = ?");
        $stmt->execute([$id]);
        logActivity($db, 'DELETE_SECTION', 'sections', $id, "Section ID $id deleted");
        echo json_encode(['message' => 'Section deleted successfully']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>