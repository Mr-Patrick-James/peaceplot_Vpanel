<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/logger.php';

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        handleGet($conn);
        break;
    case 'POST':
        handlePost($conn, $input);
        break;
    case 'PUT':
        handlePut($conn, $input);
        break;
    case 'DELETE':
        handleDelete($conn, $input);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
}

function handleGet($conn) {
    try {
        $burialRecordId = isset($_GET['burial_record_id']) ? intval($_GET['burial_record_id']) : null;
        $id = isset($_GET['id']) ? intval($_GET['id']) : null;
        
        if ($id) {
            // Get single image
            $stmt = $conn->prepare("
                SELECT * FROM burial_record_images 
                WHERE id = :id
            ");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result) {
                echo json_encode(['success' => true, 'data' => $result]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Image not found']);
            }
        } elseif ($burialRecordId) {
            // Get all images for a burial record
            $stmt = $conn->prepare("
                SELECT * FROM burial_record_images 
                WHERE burial_record_id = :burial_record_id 
                ORDER BY display_order ASC, created_at ASC
            ");
            $stmt->bindParam(':burial_record_id', $burialRecordId);
            $stmt->execute();
            $results = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $results]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Missing burial_record_id or id parameter']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handlePost($conn, $input) {
    try {
        if (!isset($input['burial_record_id']) || !isset($input['image_path'])) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            return;
        }
        
        // If this is set as primary, unset any existing primary
        if (isset($input['is_primary']) && $input['is_primary']) {
            $updateStmt = $conn->prepare("
                UPDATE burial_record_images 
                SET is_primary = 0 
                WHERE burial_record_id = :burial_record_id
            ");
            $updateStmt->bindParam(':burial_record_id', $input['burial_record_id']);
            $updateStmt->execute();
        }
        
        // Get the next display order if not provided
        if (!isset($input['display_order'])) {
            $orderStmt = $conn->prepare("
                SELECT COALESCE(MAX(display_order), 0) + 1 as next_order 
                FROM burial_record_images 
                WHERE burial_record_id = :burial_record_id
            ");
            $orderStmt->bindParam(':burial_record_id', $input['burial_record_id']);
            $orderStmt->execute();
            $orderResult = $orderStmt->fetch();
            $input['display_order'] = $orderResult['next_order'];
        }
        
        $stmt = $conn->prepare("
            INSERT INTO burial_record_images 
            (burial_record_id, image_path, image_caption, image_type, display_order, is_primary) 
            VALUES 
            (:burial_record_id, :image_path, :image_caption, :image_type, :display_order, :is_primary)
        ");
        
        $stmt->bindParam(':burial_record_id', $input['burial_record_id']);
        $stmt->bindParam(':image_path', $input['image_path']);
        $stmt->bindParam(':image_caption', $input['image_caption']);
        $stmt->bindParam(':image_type', $input['image_type']);
        $stmt->bindParam(':display_order', $input['display_order']);
        $stmt->bindParam(':is_primary', $input['is_primary']);
        
        if ($stmt->execute()) {
            $lastId = $conn->lastInsertId();
            // Get burial record name for logging
            $nameStmt = $conn->prepare("SELECT full_name FROM deceased_records WHERE id = :id");
            $nameStmt->bindParam(':id', $input['burial_record_id']);
            $nameStmt->execute();
            $recordName = $nameStmt->fetchColumn() ?: 'ID ' . $input['burial_record_id'];
            logActivity($conn, 'ADD_IMAGE', 'burial_record_images', $lastId, "Image added for burial record of $recordName");
            echo json_encode(['success' => true, 'message' => 'Image added successfully', 'id' => $lastId]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add image']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handlePut($conn, $input) {
    try {
        if (!isset($input['id'])) {
            echo json_encode(['success' => false, 'message' => 'Missing image ID']);
            return;
        }
        
        // If this is set as primary, unset any existing primary
        if (isset($input['is_primary']) && $input['is_primary']) {
            // Get the burial_record_id first
            $getStmt = $conn->prepare("SELECT burial_record_id FROM burial_record_images WHERE id = :id");
            $getStmt->bindParam(':id', $input['id']);
            $getStmt->execute();
            $image = $getStmt->fetch();
            
            if ($image) {
                $updateStmt = $conn->prepare("
                    UPDATE burial_record_images 
                    SET is_primary = 0 
                    WHERE burial_record_id = :burial_record_id AND id != :id
                ");
                $updateStmt->bindParam(':burial_record_id', $image['burial_record_id']);
                $updateStmt->bindParam(':id', $input['id']);
                $updateStmt->execute();
            }
        }
        
        $stmt = $conn->prepare("
            UPDATE burial_record_images 
            SET image_path = :image_path,
                image_caption = :image_caption,
                image_type = :image_type,
                display_order = :display_order,
                is_primary = :is_primary,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        
        $stmt->bindParam(':id', $input['id']);
        $stmt->bindParam(':image_path', $input['image_path']);
        $stmt->bindParam(':image_caption', $input['image_caption']);
        $stmt->bindParam(':image_type', $input['image_type']);
        $stmt->bindParam(':display_order', $input['display_order']);
        $stmt->bindParam(':is_primary', $input['is_primary']);
        
        if ($stmt->execute()) {
            logActivity($conn, 'UPDATE_IMAGE', 'burial_record_images', $input['id'], "Image ID " . $input['id'] . " updated");
            echo json_encode(['success' => true, 'message' => 'Image updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update image']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleDelete($conn, $input) {
    try {
        $id = isset($input['id']) ? $input['id'] : (isset($_GET['id']) ? $_GET['id'] : null);
        
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Missing image ID']);
            return;
        }
        
        // Get burial record name before deletion
        $imgStmt = $conn->prepare("SELECT bri.burial_record_id, dr.full_name FROM burial_record_images bri LEFT JOIN deceased_records dr ON bri.burial_record_id = dr.id WHERE bri.id = :id");
        $imgStmt->bindParam(':id', $id);
        $imgStmt->execute();
        $imgInfo = $imgStmt->fetch();
        $recordName = $imgInfo ? ($imgInfo['full_name'] ?: 'ID ' . $imgInfo['burial_record_id']) : 'unknown';

        $stmt = $conn->prepare("DELETE FROM burial_record_images WHERE id = :id");
        $stmt->bindParam(':id', $id);
        
        if ($stmt->execute()) {
            logActivity($conn, 'DELETE_IMAGE', 'burial_record_images', $id, "Image deleted from burial record of $recordName");
            echo json_encode(['success' => true, 'message' => 'Image deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete image']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
