<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/logger.php';

// Log logout before session is destroyed
$user = getUserInfo();
if ($user) {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        if ($conn) {
            logActivity($conn, 'LOGOUT', 'users', $user['id'], 'User "' . $user['username'] . '" logged out');
        }
    } catch (Exception $e) { /* silent */ }
}

logout();

header('Location: /peaceplot/index.php');
exit;
?>
