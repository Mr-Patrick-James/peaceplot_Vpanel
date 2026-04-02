<?php
header('Content-Type: application/json');

require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    // Check if burial_record_images table exists
    $tableCheck = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='burial_record_images'");
    $tableExists = $tableCheck->fetch() !== false;
    
    if (!$tableExists) {
        echo json_encode(['success' => false, 'message' => 'burial_record_images table does not exist']);
        exit;
    }
    
    // Check if upload directory exists and is writable
    $uploadDir = __DIR__ . '/assets/images/burial_records/';
    $dirExists = file_exists($uploadDir);
    $dirWritable = $dirExists && is_writable($uploadDir);
    
    // Count existing images
    $countStmt = $conn->query("SELECT COUNT(*) as count FROM burial_record_images");
    $count = $countStmt->fetch();
    
    echo json_encode([
        'success' => true,
        'table_exists' => $tableExists,
        'upload_dir_exists' => $dirExists,
        'upload_dir_writable' => $dirWritable,
        'upload_dir_path' => $uploadDir,
        'existing_images_count' => $count['count']
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
