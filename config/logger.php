<?php
require_once __DIR__ . '/auth.php';

/**
 * Log an activity to the activity_logs table
 * 
 * @param PDO $conn Database connection
 * @param string $action Action performed (e.g., 'ADD_RECORD', 'DELETE_RECORD', 'LOGIN', 'PAGE_VIEW')
 * @param string $tableName Table being affected
 * @param int|null $recordId ID of the record being affected
 * @param string $description Detailed description of the activity
 * @return bool Success status
 */
function logActivity($conn, $action, $tableName, $recordId, $description) {
    try {
        $user = getUserInfo();
        $userId = $user ? $user['id'] : null;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $sessionId = session_id() ?: null;

        // Ensure session_id column exists
        try {
            $conn->exec("ALTER TABLE activity_logs ADD COLUMN session_id VARCHAR(128)");
        } catch (PDOException $e) { /* already exists */ }

        $stmt = $conn->prepare("
            INSERT INTO activity_logs (user_id, action, table_name, record_id, description, ip_address, session_id) 
            VALUES (:user_id, :action, :table_name, :record_id, :description, :ip_address, :session_id)
        ");

        $stmt->bindValue(':user_id', $userId);
        $stmt->bindValue(':action', $action);
        $stmt->bindValue(':table_name', $tableName);
        $stmt->bindValue(':record_id', $recordId);
        $stmt->bindValue(':description', $description);
        $stmt->bindValue(':ip_address', $ipAddress);
        $stmt->bindValue(':session_id', $sessionId);

        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Logging error: " . $e->getMessage());
        return false;
    }
}
?>
