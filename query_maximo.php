<?php
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$stmt = $conn->prepare("SELECT dr.*, cl.lot_number FROM deceased_records dr LEFT JOIN cemetery_lots cl ON dr.lot_id = cl.id WHERE dr.full_name LIKE :name");
$stmt->bindValue(':name', '%Maximo%');
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($results);

$stmt = $conn->prepare("SELECT * FROM lot_layers WHERE lot_id = 1");
$stmt->execute();
$layers = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($layers);
?>
