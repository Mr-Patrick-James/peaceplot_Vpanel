<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Check if file was uploaded
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
        exit;
    }
    
    $file = $_FILES['image'];
    $burialRecordId = isset($_POST['burial_record_id']) ? intval($_POST['burial_record_id']) : null;
    $imageCaption = isset($_POST['image_caption']) ? $_POST['image_caption'] : '';
    $imageType = isset($_POST['image_type']) ? $_POST['image_type'] : 'grave_photo';
    $isPrimary = isset($_POST['is_primary']) ? ($_POST['is_primary'] === 'true' || $_POST['is_primary'] === '1') : false;
    
    if (!$burialRecordId) {
        echo json_encode(['success' => false, 'message' => 'Missing burial_record_id']);
        exit;
    }
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.']);
        exit;
    }
    
    // Validate file size (max 10MB)
    $maxSize = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 10MB.']);
        exit;
    }
    
    // Create upload directory if it doesn't exist
    $uploadDir = __DIR__ . '/../assets/images/burial_records/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'burial_' . $burialRecordId . '_' . time() . '_' . uniqid() . '.' . $extension;
    $uploadPath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
        exit;
    }
    
    // Save to database
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        // Clean up uploaded file if database fails
        unlink($uploadPath);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    // If this is set as primary, unset any existing primary
    if ($isPrimary) {
        $updateStmt = $conn->prepare("
            UPDATE burial_record_images 
            SET is_primary = 0 
            WHERE burial_record_id = :burial_record_id
        ");
        $updateStmt->bindParam(':burial_record_id', $burialRecordId);
        $updateStmt->execute();
    }
    
    // Get the next display order
    $orderStmt = $conn->prepare("
        SELECT COALESCE(MAX(display_order), 0) + 1 as next_order 
        FROM burial_record_images 
        WHERE burial_record_id = :burial_record_id
    ");
    $orderStmt->bindParam(':burial_record_id', $burialRecordId);
    $orderStmt->execute();
    $orderResult = $orderStmt->fetch();
    $displayOrder = $orderResult['next_order'];
    
    // Insert image record
    $stmt = $conn->prepare("
        INSERT INTO burial_record_images 
        (burial_record_id, image_path, image_caption, image_type, display_order, is_primary) 
        VALUES 
        (:burial_record_id, :image_path, :image_caption, :image_type, :display_order, :is_primary)
    ");
    
    $imagePath = 'assets/images/burial_records/' . $filename;
    $stmt->bindParam(':burial_record_id', $burialRecordId);
    $stmt->bindParam(':image_path', $imagePath);
    $stmt->bindParam(':image_caption', $imageCaption);
    $stmt->bindParam(':image_type', $imageType);
    $stmt->bindParam(':display_order', $displayOrder);
    $stmt->bindValue(':is_primary', $isPrimary ? 1 : 0, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        $lastId = $conn->lastInsertId();
        
        // Get burial record name for description
        $recordStmt = $conn->prepare("SELECT full_name FROM deceased_records WHERE id = :id");
        $recordStmt->bindParam(':id', $burialRecordId);
        $recordStmt->execute();
        $recordName = $recordStmt->fetchColumn() ?: 'ID ' . $burialRecordId;
        
        logActivity($conn, 'UPLOAD_IMAGE', 'burial_record_images', $lastId, "Uploaded image for $recordName");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Image uploaded successfully', 
            'id' => $lastId,
            'image_path' => $imagePath,
            'filename' => $filename
        ]);
    } else {
        // Clean up uploaded file if database insert fails
        unlink($uploadPath);
        echo json_encode(['success' => false, 'message' => 'Failed to save image to database']);
    }
    
} catch (Exception $e) {
    // Clean up uploaded file if it exists
    if (isset($uploadPath) && file_exists($uploadPath)) {
        unlink($uploadPath);
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
