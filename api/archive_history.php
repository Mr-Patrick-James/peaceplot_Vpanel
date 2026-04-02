<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

if ($method === 'POST') {
    $action = $input['action'] ?? '';
    
    if ($action === 'archive_all') {
        try {
            $stmt = $conn->prepare("UPDATE activity_logs SET is_archived = 1 WHERE is_archived = 0 AND action != 'LOGIN'");
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'All active logs have been archived']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } elseif ($action === 'archive_single') {
        $id = $input['id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Missing log ID']);
            return;
        }
        try {
            $stmt = $conn->prepare("UPDATE activity_logs SET is_archived = 1 WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Log archived successfully']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } elseif ($action === 'restore_single') {
        $id = $input['id'] ?? null;
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Missing log ID']);
            return;
        }
        try {
            $stmt = $conn->prepare("UPDATE activity_logs SET is_archived = 0 WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Log restored successfully']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>
