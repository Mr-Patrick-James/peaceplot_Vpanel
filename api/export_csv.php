<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/logger.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('HTTP/1.1 403 Forbidden');
    exit('Unauthorized');
}

$reportType = $_GET['type'] ?? 'all_lots';
$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    die("Database connection failed");
}

try {
    $data = [];
    $filename = "report_" . $reportType . "_" . date('Y-m-d') . ".csv";
    $headers = [];

    switch ($reportType) {
        case 'all_lots':
            $stmt = $conn->query("
                SELECT cl.lot_number, s.name as section, b.name as block, cl.position, cl.status, cl.price, dr.full_name as deceased_name 
                FROM cemetery_lots cl 
                LEFT JOIN sections s ON cl.section_id = s.id
                LEFT JOIN blocks b ON s.block_id = b.id
                LEFT JOIN deceased_records dr ON cl.id = dr.lot_id 
                ORDER BY cl.lot_number
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $headers = ['Lot Number', 'Section', 'Block', 'Position', 'Status', 'Price', 'Deceased Name'];
            break;

        case 'vacant_lots':
            $stmt = $conn->query("
                SELECT cl.lot_number, s.name as section, b.name as block, cl.position, cl.status, cl.price 
                FROM cemetery_lots cl
                LEFT JOIN sections s ON cl.section_id = s.id
                LEFT JOIN blocks b ON s.block_id = b.id
                WHERE cl.status = 'Vacant'
                ORDER BY cl.lot_number
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $headers = ['Lot Number', 'Section', 'Block', 'Position', 'Status', 'Price'];
            break;

        case 'occupied_lots':
            $stmt = $conn->query("
                SELECT cl.lot_number, s.name as section, b.name as block, dr.full_name, dr.date_of_birth, dr.date_of_death, dr.date_of_burial 
                FROM cemetery_lots cl 
                LEFT JOIN sections s ON cl.section_id = s.id
                LEFT JOIN blocks b ON s.block_id = b.id
                JOIN deceased_records dr ON cl.id = dr.lot_id 
                WHERE cl.status = 'Occupied'
                ORDER BY cl.lot_number
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $headers = ['Lot Number', 'Section', 'Block', 'Deceased Name', 'Date of Birth', 'Date of Death', 'Date of Burial'];
            break;

        case 'recent_burials':
            $stmt = $conn->query("
                SELECT dr.full_name, cl.lot_number, s.name as section, b.name as block, dr.date_of_birth, dr.date_of_death, dr.date_of_burial, dr.age 
                FROM deceased_records dr
                LEFT JOIN cemetery_lots cl ON dr.lot_id = cl.id
                LEFT JOIN sections s ON cl.section_id = s.id
                LEFT JOIN blocks b ON s.block_id = b.id
                ORDER BY dr.date_of_burial DESC
                LIMIT 100
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $headers = ['Full Name', 'Lot Number', 'Section', 'Block', 'Date of Birth', 'Date of Death', 'Date of Burial', 'Age'];
            break;

        case 'deceased_records':
            $stmt = $conn->query("
                SELECT dr.full_name, cl.lot_number, s.name as section, b.name as block, dr.date_of_birth, dr.date_of_death, dr.date_of_burial, dr.age, dr.gender, dr.religion 
                FROM deceased_records dr
                LEFT JOIN cemetery_lots cl ON dr.lot_id = cl.id
                LEFT JOIN sections s ON cl.section_id = s.id
                LEFT JOIN blocks b ON s.block_id = b.id
                ORDER BY dr.full_name ASC
            ");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $headers = ['Full Name', 'Lot Number', 'Section', 'Block', 'Date of Birth', 'Date of Death', 'Date of Burial', 'Age', 'Gender', 'Religion'];
            break;

        default:
            die("Invalid report type");
    }

    // Log the export before sending headers
    $reportLabels = [
        'all_lots'        => 'All Lots',
        'vacant_lots'     => 'Vacant Lots',
        'occupied_lots'   => 'Occupied Lots',
        'recent_burials'  => 'Recent Burials',
        'deceased_records'=> 'Deceased Records',
    ];
    $reportLabel = $reportLabels[$reportType] ?? $reportType;
    logActivity($conn, 'EXPORT_CSV', 'reports', null, "Exported CSV report: $reportLabel");

    // Set headers for download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    // Create a file pointer connected to the output stream
    $output = fopen('php://output', 'w');

    // Add CSV headers
    fputcsv($output, $headers);

    // Add data rows
    foreach ($data as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;

} catch (PDOException $e) {
    die("Error generating report: " . $e->getMessage());
}
?>