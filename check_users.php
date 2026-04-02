<?php
require_once __DIR__ . '/config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->query("SELECT id, username, full_name, email FROM users");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
