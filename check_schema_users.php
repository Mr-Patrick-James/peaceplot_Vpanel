<?php
require_once __DIR__ . '/config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("PRAGMA table_info(users)");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
