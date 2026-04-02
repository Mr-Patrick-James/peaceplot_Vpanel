<?php
require_once __DIR__ . '/../config/auth.php';
requireLogin();

$user = getUserInfo();
$userInitials = getInitials($user['full_name']);

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

require_once __DIR__ . '/includes/page_tracker.php';

$lots = [];
$mapImage = 'cemetery.jpg'; // Default map image name

// Check if map image exists
$mapPath = __DIR__ . '/../assets/images/' . $mapImage;
if (!file_exists($mapPath)) {
    $mapImage = null;
}

if ($conn) {
    try {
        // Get all lots with their map coordinates and layer information
        $stmt = $conn->query("
        SELECT cl.*, s.name as section_name, b.name as block_name,
               (SELECT COUNT(*) FROM lot_layers ll WHERE ll.lot_id = cl.id) as total_layers,
               (SELECT COUNT(*) FROM lot_layers ll WHERE ll.lot_id = cl.id AND ll.is_occupied = 1) as occupied_layers,
               COUNT(DISTINCT dr.id) as burial_count,
               GROUP_CONCAT(DISTINCT dr.full_name || '|' || COALESCE(dr.layer, 1)) as burial_info,
               GROUP_CONCAT(DISTINCT dr.next_of_kin) as kin_names,
               CASE 
                   WHEN COUNT(DISTINCT dr.id) > 0 THEN 'Occupied'
                   WHEN EXISTS (SELECT 1 FROM lot_layers ll WHERE ll.lot_id = cl.id AND ll.is_occupied = 1) THEN 'Occupied'
                   ELSE cl.status
               END as actual_status
        FROM cemetery_lots cl 
        LEFT JOIN sections s ON cl.section_id = s.id
        LEFT JOIN blocks b ON s.block_id = b.id
        LEFT JOIN deceased_records dr ON cl.id = dr.lot_id
        GROUP BY cl.id
        ORDER BY LENGTH(cl.lot_number), cl.lot_number
    ");
    $lots = $stmt->fetchAll();

    // Fetch all sections with their map coordinates and block name
    $stmt_sections = $conn->query("
        SELECT s.id, s.name, s.map_x, s.map_y, s.map_width, s.map_height, s.block_id, b.name as block_name 
        FROM sections s 
        LEFT JOIN blocks b ON s.block_id = b.id 
        WHERE s.map_x IS NOT NULL AND s.map_y IS NOT NULL
    ");
    $map_sections = $stmt_sections->fetchAll();

    // Fetch all blocks with their map coordinates
    $stmt_blocks = $conn->query("SELECT id, name, map_x, map_y, map_width, map_height FROM blocks WHERE map_x IS NOT NULL AND map_y IS NOT NULL");
    $map_blocks = $stmt_blocks->fetchAll();
        
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
} else {
    // Use sample data if database connection fails
    $sampleFile = __DIR__ . '/../database/sample_lots.json';
    if (file_exists($sampleFile)) {
        $lots = json_decode(file_get_contents($sampleFile), true);
        $error = "Using sample data - database connection failed";
    } else {
        $lots = [];
        $error = "No database connection and no sample data available";
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PeacePlot Admin - Cemetery Map</title>
  <link rel="stylesheet" href="../assets/css/styles.css" />
  <style>
    .map-container {
      background: white;
      border-radius: 12px;
      padding: 24px;
      margin-top: 16px;
      width: 95%;
      max-width: 1400px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.08);
      position: relative;
    }
    
    .map-image-wrapper {
      position: relative;
      width: 100%;
      height: 70vh;
      min-height: 500px;
      max-height: 850px;
      overflow: hidden;
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.12);
      cursor: grab;
      background: #f8f9fa;
      border: 1px solid #e9ecef;
    }

    .map-image-wrapper.grabbing {
      cursor: grabbing;
    }

    .map-canvas {
      position: relative;
      transform-origin: 0 0;
    }

    .section-rectangle {
      position: absolute;
      border: 3px solid #3b82f6;
      background: rgba(59, 130, 246, 0.15);
      pointer-events: none;
      z-index: 5;
      display: none;
      border-radius: 4px;
      box-shadow: 0 0 15px rgba(59, 130, 246, 0.5);
    }

    .section-rectangle.active {
      display: block;
      animation: pulse-border 2s infinite;
    }

    .block-rectangle {
      position: absolute;
      border: 3px solid #10b981;
      background: rgba(16, 185, 129, 0.15);
      pointer-events: none;
      z-index: 4;
      display: none;
      border-radius: 4px;
      box-shadow: 0 0 15px rgba(16, 185, 129, 0.5);
    }

    .block-rectangle.active {
      display: block;
      animation: pulse-border-green 2s infinite;
    }

    @keyframes pulse-border-green {
      0% { border-color: rgba(16, 185, 129, 1); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
      70% { border-color: rgba(16, 185, 129, 0.8); box-shadow: 0 0 0 15px rgba(16, 185, 129, 0); }
      100% { border-color: rgba(16, 185, 129, 1); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }

    .block-label-badge {
      position: absolute;
      top: -24px;
      left: 0;
      background: #10b981;
      color: white;
      padding: 2px 8px;
      border-radius: 4px;
      font-size: 9px;
      font-weight: 600;
      white-space: nowrap;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    @keyframes pulse-border {
      0% { border-color: rgba(59, 130, 246, 1); box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.7); }
      70% { border-color: rgba(59, 130, 246, 0.8); box-shadow: 0 0 0 15px rgba(59, 130, 246, 0); }
      100% { border-color: rgba(59, 130, 246, 1); box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); }
    }

    .section-label-badge {
      position: absolute;
      top: -24px;
      left: 0;
      background: #3b82f6;
      color: white;
      padding: 2px 8px;
      border-radius: 4px;
      font-size: 9px;
      font-weight: 600;
      white-space: nowrap;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .map-image {
      width: 100%;
      height: auto;
      display: block;
      -webkit-user-drag: none;
      user-select: none;
      pointer-events: none;
    }

    /* Modern Dashboard Header & UI */
    .dashboard-header {
      background: #fff;
      padding: 24px 32px;
      border-radius: 16px;
      margin-bottom: 24px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.03);
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: relative; /* Added for absolute search positioning */
    }
    .header-left .title {
      font-size: 24px;
      font-weight: 700;
      color: #1e293b;
      margin: 0 0 4px 0;
    }
    .header-left .subtitle {
      font-size: 14px;
      color: #64748b;
      margin: 0 0 16px 0;
    }
    .breadcrumbs {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      color: #94a3b8;
    }
    .breadcrumbs a { color: #94a3b8; text-decoration: none; }
    .breadcrumbs .current { color: #1e293b; font-weight: 600; }
    .header-actions { display: flex; gap: 12px; }
    
    .btn-outline {
      padding: 10px 16px;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      background: #fff;
      color: #475569;
      font-size: 14px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      transition: all 0.2s;
    }
    .btn-outline:hover { background: #f8fafc; border-color: #cbd5e1; }
    
    .lot-marker {
      position: absolute;
      border: calc(1px + 1px / var(--current-zoom, 1)) solid;
      cursor: pointer;
      transition: all 0.3s;
      box-shadow: 0 calc(0.5px + 0.5px / var(--current-zoom, 1)) calc(2px + 2px / var(--current-zoom, 1)) rgba(0,0,0,0.3);
      box-sizing: border-box; /* Ensures border is inside dimensions */
    }
    
    .lot-marker:hover {
      border-width: calc(1.5px + 1.5px / var(--current-zoom, 1));
      z-index: 100;
      box-shadow: 0 calc(1px + 1px / var(--current-zoom, 1)) calc(4px + 4px / var(--current-zoom, 1)) rgba(0,0,0,0.5);
    }
    
    .lot-marker.vacant {
      border-color: #22c55e;
      background: rgba(34, 197, 94, 0.4);
    }
    
    .lot-marker.occupied {
      border-color: #f97316;
      background: rgba(249, 115, 22, 0.4);
    }
    
    .lot-marker.maintenance {
      border-color: #64748b;
      background: rgba(100, 116, 139, 0.4);
    }
    
    .lot-label {
      position: absolute;
      top: 0.2px;
      left: 0.2px;
      background: rgba(0,0,0,0.9);
      color: white;
      padding: 0.3px 0.6px;
      border-radius: 0.2px;
      font-size: 3.2px; /* Lot name a bit bigger */
      font-weight: 900;
      pointer-events: none;
      display: flex;
      flex-direction: column;
      line-height: 1.2; /* Increased line-height for better vertical spacing */
      z-index: 2;
      box-sizing: border-box;
      max-width: calc(100% - 0.4px);
    }

    .lot-label .kin-tag {
      font-size: 2.2px; /* Increased font-size for readability */
      opacity: 1; /* Full opacity for better readability */
      font-weight: 700;
      margin-top: 0.4px; /* More vertical separation */
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      text-transform: uppercase;
      display: block;
      width: 100%;
      letter-spacing: 0.1px;
    }

    .hidden-marker {
      display: none !important;
    }
    
    .highlighted-marker {
      z-index: 105 !important;
      border-width: calc(2px + 2px / var(--current-zoom, 1)) !important;
      border-color: #ef4444 !important;
      background: rgba(239, 68, 68, 0.2) !important;
      box-shadow: 0 0 0 calc(2px + 2px / var(--current-zoom, 1)) white, 0 0 20px rgba(239, 68, 68, 0.6) !important;
    }
    
    .highlighted-marker::after {
      content: '📍';
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -100%);
      font-size: calc(10px + 12px / var(--current-zoom, 1));
      filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
      animation: pinBounce 2s infinite;
      pointer-events: none;
      z-index: 110;
    }
    
    @media (max-width: 768px) {
      .highlighted-marker::after {
        font-size: calc(8px + 10px / var(--current-zoom, 1));
      }
    }
    
    @keyframes pinBounce {
      0%, 100% { transform: translate(-50%, -100%); }
      50% { transform: translate(-50%, -120%); }
    }
    
    @keyframes pinDrop {
      0% { transform: translate(-50%, -200%); opacity: 0; }
      100% { transform: translate(-50%, -100%); opacity: 1; }
    }
    
    @keyframes pulse-ring {
      0% { box-shadow: 0 0 0 calc(2px + 2px / var(--current-zoom, 1)) white, 0 0 0 calc(4px + 4px / var(--current-zoom, 1)) rgba(239, 68, 68, 0.8); }
      50% { box-shadow: 0 0 0 calc(2px + 2px / var(--current-zoom, 1)) white, 0 0 0 calc(6px + 6px / var(--current-zoom, 1)) rgba(239, 68, 68, 0.4); }
      100% { box-shadow: 0 0 0 calc(2px + 2px / var(--current-zoom, 1)) white, 0 0 0 calc(4px + 4px / var(--current-zoom, 1)) rgba(239, 68, 68, 0.8); }
    }
    
    .no-map-message {
      text-align: center;
      padding: 60px 20px;
      color: var(--muted);
    }
    
    .no-map-message h3 {
      margin-bottom: 12px;
      color: var(--text);
    }
    
    /* Google Maps Style Card */
    .google-maps-card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.15);
      overflow: hidden;
      margin: 0;
    }
    
    .google-maps-card {
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      background: white;
      max-height: 100%;
      display: flex;
      flex-direction: column;
    }
    
    @media (max-width: 768px) {
      .map-container {
        width: 100%;
        padding: 16px;
      }
      
      .map-image-wrapper {
        height: 50vh;
        min-height: 350px;
        max-height: 600px;
        border-radius: 8px;
      }
      
      .map-legend {
        flex-wrap: wrap;
        gap: 8px;
        padding: 8px 12px;
        font-size: 12px;
      }
      
      .legend-item {
        gap: 4px;
      }
      
      .legend-box {
        width: 16px;
        height: 16px;
      }
    }
    
    @media (max-width: 480px) {
      .map-image-wrapper {
        height: 40vh;
        min-height: 300px;
        max-height: 500px;
        border-radius: 6px;
      }
      
      .map-legend {
        padding: 6px 8px;
        font-size: 11px;
      }
      
      .legend-box {
        width: 14px;
        height: 14px;
      }
    }
    
    .card-header {
      padding: 16px 20px;
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
      border-bottom: 1px solid #e9ecef;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-shrink: 0;
    }
    
    @media (max-width: 768px) {
      .card-header {
        padding: 12px 16px;
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
      }
    }
    
    .lot-info h2 {
      margin: 0;
      font-size: 18px;
      color: #202124;
      font-weight: 600;
    }
    
    @media (max-width: 768px) {
      .lot-info h2 {
        font-size: 16px;
      }
    }
    
    .lot-location {
      display: flex;
      align-items: center;
      gap: 6px;
      color: #5f6368;
      font-size: 14px;
      margin-top: 4px;
    }
    
    @media (max-width: 768px) {
      .lot-location {
        font-size: 13px;
      }
    }
    
    .status-badge {
      padding: 6px 12px;
      border-radius: 16px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .status-badge.vacant {
      background: #e8f5e8;
      color: #2e7d32;
    }
    
    .status-badge.occupied {
      background: #fff3e0;
      color: #f57c00;
    }
    
    .status-badge.maintenance {
      background: #f1f5f9;
      color: #64748b;
    }
    
    .card-content {
      padding: 20px;
      overflow-y: auto;
      flex: 1;
    }
    
    @media (max-width: 768px) {
      .card-content {
        padding: 16px;
      }
    }
    
    @media (max-width: 480px) {
      .card-content {
        padding: 12px;
      }
    }
    
    .info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
      gap: 16px;
      margin-bottom: 24px;
    }
    
    .info-item {
      display: flex;
      align-items: flex-start;
      gap: 12px;
    }
    
    .info-item svg {
      color: #5f6368;
      flex-shrink: 0;
      margin-top: 2px;
    }
    
    .info-label {
      font-size: 12px;
      color: #5f6368;
      font-weight: 500;
      margin-bottom: 2px;
    }
    
    .info-value {
      font-size: 14px;
      color: #202124;
      font-weight: 500;
    }
    
    .deceased-section {
      background: #f8f9fa;
      border-radius: 8px;
      padding: 16px;
      margin-bottom: 20px;
    }
    
    .section-title {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 14px;
      font-weight: 600;
      color: #202124;
      margin-bottom: 12px;
    }
    
    .section-title svg {
      color: #5f6368;
    }
    
    .deceased-name {
      font-size: 16px;
      color: #202124;
      font-weight: 500;
    }
    
    .images-section {
      margin-bottom: 24px;
    }
    
    .images-section:not(:first-child) {
      border-top: 1px solid #e8eaed;
      padding-top: 20px;
    }
    
    .images-section .section-title {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 16px;
    }
    
    .view-images-btn {
      display: flex;
      align-items: center;
      gap: 6px;
      background: #1a73e8;
      color: white;
      border: none;
      padding: 8px 16px;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    
    .view-images-btn:hover {
      background: #1557b0;
      transform: translateY(-1px);
      box-shadow: 0 2px 8px rgba(26, 115, 232, 0.3);
    }
    
    .images-container {
      background: #f8f9fa;
      border-radius: 8px;
      padding: 16px;
      margin-top: 12px;
    }
    
    .images-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
      font-size: 14px;
      font-weight: 600;
      color: #202124;
    }
    
    .close-images-btn {
      background: none;
      border: none;
      font-size: 20px;
      color: #5f6368;
      cursor: pointer;
      width: 24px;
      height: 24px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      transition: background 0.2s ease;
    }
    
    .close-images-btn:hover {
      background: #e8eaed;
    }
    
    .images-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
      gap: 12px;
    }
    
    .images-grid img {
      width: 100%;
      height: 100px;
      object-fit: cover;
      border-radius: 8px;
      cursor: pointer;
      transition: transform 0.2s ease;
    }
    
    .images-grid img:hover {
      transform: scale(1.05);
    }
    
    .map-legend {
      display: flex;
      gap: 16px;
      margin-bottom: 16px;
      padding: 12px 16px;
      background: rgba(255,255,255,0.95);
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      position: relative;
      z-index: 10;
      flex-wrap: wrap;
    }
    
    .legend-item {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 13px;
      font-weight: 500;
    }
    
    .legend-box {
      width: 20px;
      height: 20px;
      border-radius: 3px;
      border: 2px solid rgba(0,0,0,0.2);
      box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    
    .legend-box.vacant { 
      background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%);
    }
    .legend-box.occupied { 
      background: linear-gradient(135deg, #fb923c 0%, #f97316 100%);
    }
    .legend-box.maintenance { 
      background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
    }
    
    
    .modal-map {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
      align-items: center;
      justify-content: center;
    }
    
    .modal-map-content {
      background: white;
      border-radius: 12px;
      width: 90%;
      max-width: 500px;
      max-height: 90vh;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }
    
    @media (max-width: 768px) {
      .modal-map-content {
        width: 95%;
        max-width: none;
        max-height: 95vh;
        border-radius: 12px 12px 0 0;
        margin: 0;
      }
    }
    
    @media (max-width: 480px) {
      .modal-map-content {
        width: 100%;
        max-height: 100vh;
        border-radius: 0;
      }
    }
    
    .modal-map-header {
      padding: 20px 24px;
      border-bottom: 1px solid var(--border);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .modal-map-header h3 {
      margin: 0;
      font-size: 18px;
      color: var(--text);
    }
    
    .modal-map-close {
      background: none;
      border: none;
      font-size: 24px;
      color: var(--muted);
      cursor: pointer;
      padding: 0;
      width: 32px;
      height: 32px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 6px;
      transition: all 0.2s;
    }
    
    .modal-map-close:hover {
      background: var(--page);
      color: var(--text);
    }
    
    .modal-map-body {
      padding: 24px;
      overflow-y: auto;
      flex: 1;
      scrollbar-width: thin;
      scrollbar-color: rgba(47, 109, 246, 0.3) transparent;
    }
    
    /* Modern transparent scrollbar for Webkit browsers */
    .modal-map-body::-webkit-scrollbar {
      width: 6px;
    }
    
    .modal-map-body::-webkit-scrollbar-track {
      background: transparent;
    }
    
    .modal-map-body::-webkit-scrollbar-thumb {
      background: rgba(47, 109, 246, 0.3);
      border-radius: 3px;
      transition: background 0.3s ease;
    }
    
    .modal-map-body::-webkit-scrollbar-thumb:hover {
      background: rgba(47, 109, 246, 0.5);
    }
    
    @media (max-width: 768px) {
      .modal-map-body {
        padding: 16px;
      }
    }
    
    @media (max-width: 480px) {
      .modal-map-body {
        padding: 12px;
      }
    }
    
    .detail-row {
      display: flex;
      justify-content: space-between;
      padding: 12px 0;
      border-bottom: 1px solid var(--border);
    }
    
    .detail-row:last-child {
      border-bottom: none;
    }
    
    .detail-label {
      font-weight: 500;
      color: var(--muted);
    }
    
    .detail-value {
      font-weight: 600;
      color: var(--text);
    }
    
    /* Layer Management Styles */
    .layer-info {
      background: #f8f9fa;
      border-radius: 8px;
      padding: 16px;
      margin-bottom: 16px;
    }
    
    .layer-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
    }
    
    .layer-title {
      font-size: 14px;
      font-weight: 600;
      color: #202124;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .layer-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
      gap: 16px;
      padding: 4px;
    }
    
    @media (max-width: 768px) {
      .layer-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 12px;
      }
    }
    
    .layer-item {
      background: white;
      border: 1px solid #e2e8f0;
      border-radius: 16px;
      padding: 20px;
      text-align: left;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
      overflow: hidden;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      min-height: 140px;
    }
    
    .layer-item:hover {
      transform: translateY(-4px);
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
      border-color: #3b82f6;
    }
    
    .layer-item.occupied {
      background: #fff;
    }
    
    .layer-item.vacant {
      background: #f8fafc;
    }
    
    .layer-number-badge {
      font-size: 11px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #64748b;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 4px;
    }
    
    .layer-deceased-name {
      font-weight: 700;
      font-size: 15px;
      color: #1e293b;
      line-height: 1.3;
      margin-bottom: 8px;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    
    .layer-status-pill {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 4px 10px;
      border-radius: 9999px;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
    }
    
    .layer-status-pill.occupied {
      background: #fef3c7;
      color: #92400e;
    }
    
    .layer-status-pill.vacant {
      background: #d1fae5;
      color: #065f46;
    }
    
    .ash-burial-badge {
      display: flex;
      align-items: center;
      gap: 4px;
      font-size: 10px;
      font-weight: 600;
      color: #3b82f6;
      margin-top: 8px;
      background: #eff6ff;
      padding: 4px 8px;
      border-radius: 6px;
      width: fit-content;
    }
    
    .layer-indicator-dot {
      position: absolute;
      top: 12px;
      right: 12px;
      width: 8px;
      height: 8px;
      border-radius: 50%;
    }
    
    .layer-indicator-dot.occupied {
      background: #f59e0b;
      box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.1);
    }
    
    .layer-indicator-dot.vacant {
      background: #10b981;
      box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
    }
    
    .layer-view-btn {
      margin-top: 12px;
      font-size: 12px;
      font-weight: 700;
      color: #3b82f6;
      display: flex;
      align-items: center;
      gap: 4px;
      transition: all 0.2s ease;
    }
    
    .layer-item:hover .layer-view-btn {
      gap: 8px;
    }
    
    .layer-actions {
      display: flex;
      gap: 12px;
      align-items: center;
      background: #f8fafc;
      padding: 12px 20px;
      border-radius: 12px;
      border: 1px solid #e2e8f0;
    }
    
    .add-layer-btn, .remove-layer-btn {
      padding: 8px 16px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-size: 13px;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 6px;
      transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .add-layer-btn {
      background: #3b82f6;
      color: white;
      box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.2);
    }
    
    .add-layer-btn:hover {
      background: #2563eb;
      transform: translateY(-1px);
      box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.3);
    }
    
    .remove-layer-btn {
      background: #ef4444;
      color: white;
      box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.2);
    }
    
    .remove-layer-btn:hover {
      background: #dc2626;
      transform: translateY(-1px);
      box-shadow: 0 10px 15px -3px rgba(239, 68, 68, 0.3);
    }
    
    .remove-layer-btn:disabled {
      background: #cbd5e1;
      color: #64748b;
      cursor: not-allowed;
      box-shadow: none;
      transform: none;
    }
    
    .lot-layer-indicator {
      position: absolute;
      bottom: 0.2px;
      left: 50%;
      transform: translateX(-50%);
      background: rgba(0,0,0,0.9);
      color: white;
      font-size: 2.2px; /* Increased a tiny bit (from 1.5px to 2.2px) */
      font-weight: 900;
      padding: 0.1px 0.4px;
      border-radius: 0.2px;
      z-index: 3;
      pointer-events: none;
      line-height: 1;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    /* Notification Styles */
    .notification {
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 10px 16px;
      border-radius: 8px;
      color: white;
      font-weight: 500;
      z-index: 10000;
      min-width: 240px;
      font-size: 14px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.15);
      transform: translateX(120%);
      transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .notification.show {
      transform: translateX(0);
    }

    .notification.success { background: #22c55e; }
    .notification.error { background: #ef4444; }
    .notification.warning { background: #f59e0b; }
    .notification.info { background: #3b82f6; }

    .notification-icon {
      font-size: 16px;
      background: rgba(255,255,255,0.25);
      width: 28px;
      height: 28px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .notification-content {
      flex-grow: 1;
    }

    .notification-title {
      font-weight: 700;
      font-size: 13px;
      margin-bottom: 2px;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .notification-message {
      font-size: 14px;
      line-height: 1.4;
      opacity: 0.95;
    }

    .notification-close {
      background: none;
      border: none;
      color: white;
      font-size: 20px;
      cursor: pointer;
      padding: 0 0 0 10px;
      opacity: 0.7;
      transition: opacity 0.2s;
    }

    .notification-close:hover {
      opacity: 1;
    }

    /* Confirmation Modal Styles */
    .confirm-modal {
      display: none;
      position: fixed;
      z-index: 2000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background: rgba(15, 23, 42, 0.6);
      backdrop-filter: blur(4px);
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .confirm-modal-content {
      background: white;
      border-radius: 16px;
      width: 100%;
      max-width: 400px;
      padding: 32px;
      text-align: center;
      box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
      animation: modalScaleIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    @keyframes modalScaleIn {
      from { transform: scale(0.9); opacity: 0; }
      to { transform: scale(1); opacity: 1; }
    }

    .confirm-icon {
      width: 64px;
      height: 64px;
      background: #eff6ff;
      color: #3b82f6;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 20px;
      font-size: 32px;
    }

    .confirm-icon.danger {
      background: #fee2e2;
      color: #ef4444;
    }

    .confirm-title {
      font-size: 20px;
      font-weight: 700;
      color: #1e293b;
      margin-bottom: 12px;
    }

    .confirm-message {
      font-size: 15px;
      color: #64748b;
      line-height: 1.6;
      margin-bottom: 24px;
    }

    .confirm-actions {
      display: flex;
      gap: 12px;
      justify-content: center;
    }

    .btn-confirm-action {
      background: #3b82f6;
      color: white;
      border: none;
      padding: 12px 24px;
      border-radius: 10px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
    }

    .btn-confirm-action:hover {
      background: #2563eb;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
    }

    .btn-confirm-action.danger {
      background: #ef4444;
    }

    .btn-confirm-action.danger:hover {
      background: #dc2626;
      box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
    }

    .btn-confirm-cancel {
      background: #f1f5f9;
      color: #475569;
      border: none;
      padding: 12px 24px;
      border-radius: 10px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
    }

    .btn-confirm-cancel:hover {
      background: #e2e8f0;
    }
  </style>
</head>
<body>
  <div class="app">
    <aside class="sidebar">
      <div class="brand">
        <div class="brand-title">PeacePlot Admin</div>
        <div class="brand-sub">Cemetery Management</div>
      </div>

      <nav class="nav">
        <a href="dashboard.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 13h8V3H3v10z" /><path d="M13 21h8V11h-8v10z" /><path d="M13 3h8v6h-8V3z" /><path d="M3 21h8v-6H3v6z" /></svg></span><span>Dashboard</span></a>
        <div class="dropdown">
          <a href="#" class="dropdown-toggle" onclick="this.parentElement.classList.toggle('active'); return false;">
            <div style="display: flex; align-items: center;">
              <span class="icon">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M4 7h16" />
                  <path d="M4 12h16" />
                  <path d="M4 17h16" />
                  <path d="M8 7v10" />
                  <path d="M16 7v10" />
                </svg>
              </span>
              <span>Lot Management</span>
            </div>
            <svg class="arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
          </a>
          <div class="dropdown-content">
            <a href="index.php"><span>Manage Lots</span></a>
            <a href="blocks.php"><span>Manage Blocks</span></a>
            <a href="sections.php"><span>Manage Sections</span></a>
            <a href="lot-availability.php"><span>Lots</span></a>
            <a href="map-editor.php"><span>Map Editor</span></a>
          </div>
        </div>
        <a href="cemetery-map.php" class="active"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2V6z" /><path d="M9 4v14" /><path d="M15 6v14" /></svg></span><span>Cemetery Map</span></a>
        <a href="burial-records.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" /><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" /><path d="M8 6h8" /><path d="M8 10h8" /></svg></span><span>Burial Records</span></a>
        <a href="reports.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18" /><path d="M7 14v4" /><path d="M11 10v8" /><path d="M15 6v12" /><path d="M19 12v6" /></svg></span><span>Reports</span></a>
        <a href="history.php"><span class="icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span><span>History</span></a>
      </nav>

      <div class="sidebar-footer">
        <div class="user" onclick="window.location.href='settings.php'" style="cursor:pointer;">
          <div class="avatar"><?php echo htmlspecialchars($userInitials); ?></div>
          <div class="user-info-text">
            <div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
            <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
          </div>
        </div>

        <a class="logout" href="logout.php">
          <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><path d="M16 17l5-5-5-5" /><path d="M21 12H9" /></svg></span>
          <span>Logout</span>
        </a>
      </div>
    </aside>

    <main class="main">
      <header class="dashboard-header">
        <div class="header-left">
          <div class="breadcrumbs">
            <a href="dashboard.php">Dashboard</a>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
            <span class="current">Cemetery Map</span>
          </div>
          <h1 class="title">Cemetery Map</h1>
          <p class="subtitle">Visual representation of cemetery lots and occupancy</p>
        </div>

        <div class="header-actions">
          <button onclick="window.print()" class="btn-outline">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7" /><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" /><path d="M6 14h12v8H6z" /></svg>
            Print Map
          </button>
        </div>
      </header>

      <?php if (isset($error)): ?>
        <div class="card" style="padding:20px; color:#ef4444;">
          Error loading data: <?php echo htmlspecialchars($error); ?>
        </div>
      <?php else: ?>

      <div class="map-container">
        <div class="map-legend">
          <div class="legend-item">
            <div class="legend-box vacant"></div>
            <span>Vacant</span>
          </div>
          <div class="legend-item">
            <div class="legend-box occupied"></div>
            <span>Occupied</span>
          </div>
         
        </div>

        <?php if ($mapImage): ?>
          <div class="map-image-wrapper">
            <div class="map-canvas" id="mapCanvas">
              <img src="../assets/images/<?php echo htmlspecialchars($mapImage); ?>" 
                   alt="Cemetery Map" 
                   class="map-image"
                   id="cemeteryMapImage"
                   draggable="false">
              
              <!-- Section Rectangles -->
              <?php if (!empty($map_sections)): ?>
                <?php foreach ($map_sections as $section): ?>
                  <div class="section-rectangle" 
                       id="section-rect-<?php echo $section['id']; ?>"
                       data-section-id="<?php echo $section['id']; ?>"
                       data-block-id="<?php echo $section['block_id']; ?>"
                       style="left: <?php echo $section['map_x']; ?>%; 
                              top: <?php echo $section['map_y']; ?>%; 
                              width: <?php echo $section['map_width']; ?>%; 
                              height: <?php echo $section['map_height']; ?>%;">
                    <div class="section-label-badge">
                      <?php if (!empty($section['block_name'])): ?>
                        <span style="opacity: 0.8; font-weight: 500; margin-right: 4px;"><?php echo htmlspecialchars($section['block_name']); ?></span>
                      <?php endif; ?>
                      <?php echo htmlspecialchars($section['name']); ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>

              <!-- Block Rectangles -->
              <?php if (!empty($map_blocks)): ?>
                <?php foreach ($map_blocks as $block): ?>
                  <div class="block-rectangle" 
                       id="block-rect-<?php echo $block['id']; ?>"
                       data-block-id="<?php echo $block['id']; ?>"
                       style="left: <?php echo $block['map_x']; ?>%; 
                              top: <?php echo $block['map_y']; ?>%; 
                              width: <?php echo $block['map_width']; ?>%; 
                              height: <?php echo $block['map_height']; ?>%;">
                    <div class="block-label-badge"><?php echo htmlspecialchars($block['name']); ?></div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>

              <?php foreach ($lots as $lot): ?>
              <?php 
              // Check if lot has map coordinates (works for both DB and sample data)
              $hasCoords = isset($lot['map_x']) && isset($lot['map_y']) && 
                          isset($lot['map_width']) && isset($lot['map_height']) &&
                          $lot['map_x'] !== null && $lot['map_y'] !== null && 
                          $lot['map_width'] !== null && $lot['map_height'] !== null;
              ?>
              <?php if ($hasCoords): ?>
                <?php 
                // Handle different data structures
                $totalLayers = isset($lot['total_layers']) ? $lot['total_layers'] : 1;
                $occupiedLayers = isset($lot['occupied_layers']) ? $lot['occupied_layers'] : 0;
                $actualStatus = isset($lot['actual_status']) ? $lot['actual_status'] : $lot['status'];
                $deceasedName = isset($lot['deceased_name']) ? $lot['deceased_name'] : null;
                $isVertical = isset($lot['map_height']) && isset($lot['map_width']) && $lot['map_height'] > $lot['map_width'];
                ?>
                <div class="lot-marker <?php echo strtolower($actualStatus); ?> <?php echo $isVertical ? 'vertical' : ''; ?>"
                     data-lot-id="<?php echo $lot['id']; ?>"
                     style="left: <?php echo $lot['map_x']; ?>%; 
                            top: <?php echo $lot['map_y']; ?>%;
                            width: <?php echo $lot['map_width']; ?>%;
                            height: <?php echo $lot['map_height']; ?>%;"
                     onclick="showLotDetails(<?php echo htmlspecialchars(json_encode($lot)); ?>)"
                     title="<?php echo htmlspecialchars($lot['lot_number']); ?> - <?php echo $actualStatus; ?>">
                  <div class="lot-label">
                    <span><?php echo htmlspecialchars($lot['lot_number']); ?></span>
                    <?php if (!empty($lot['kin_names'])): ?>
                      <span class="kin-tag">Kin: <?php echo htmlspecialchars($lot['kin_names']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($lot['deceased_names'])): ?>
                      <span class="deceased-tag">Deceased: <?php echo htmlspecialchars($lot['deceased_names']); ?></span>
                    <?php endif; ?>
                  </div>
                  <?php if ($totalLayers > 1): ?>
                    <div class="lot-layer-indicator" title="<?php echo $occupiedLayers; ?>/<?php echo $totalLayers; ?> layers occupied"><?php echo $occupiedLayers; ?>/<?php echo $totalLayers; ?></div>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            <?php endforeach; ?>
            </div>
          </div>
          
          <?php 
          $lotsWithoutCoordinates = array_filter($lots, function($lot) {
            return $lot['map_x'] === null || $lot['map_y'] === null || $lot['map_width'] === null || $lot['map_height'] === null;
          });
          ?>
          
          <?php if (!empty($lotsWithoutCoordinates)): ?>
            <div style="margin-top: 24px; padding: 16px 20px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; color: #92400e; font-size: 14px; display: flex; align-items: center; gap: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
              <span style="font-size: 20px;">⚠️</span>
              <div>
                <strong>Note:</strong> <?php echo count($lotsWithoutCoordinates); ?> lot(s) don't have map coordinates assigned yet.
                <a href="map-editor.php" style="color: #2563eb; text-decoration: underline; font-weight: 600; margin-left: 4px;">
                  Open Map Editor to mark lots on the map
                </a>
              </div>
            </div>
          <?php endif; ?>
          
        <?php else: ?>
          <div class="no-map-message">
            <h3>No Map Image Found</h3>
            <p>Please upload a cemetery map image to <code>assets/images/cemetery-map.jpg</code></p>
          </div>
        <?php endif; ?>
      </div>

      <?php endif; ?>
    </main>
  </div>

  <div id="lotModal" class="modal-map">
    <div class="modal-map-content">
      <div class="modal-map-header">
        <h3 id="modalTitle">Lot Details</h3>
        <button class="modal-map-close" onclick="closeLotModal()">&times;</button>
      </div>
      <div class="modal-map-body" id="modalBody">
        <!-- Details will be populated by JavaScript -->
      </div>
    </div>
  </div>

  <script>
    let zoom = 1;
    let panX = 0;
    let panY = 0;
    let isPanning = false;
    let startPanX, startPanY;
    let panStartClientX = 0;
    let panStartClientY = 0;
    let didPan = false;
    let isAnimating = false;

    const mapWrapper = document.querySelector('.map-image-wrapper');
    const mapCanvas = document.getElementById('mapCanvas');
    const mapImage = document.getElementById('cemeteryMapImage');

    // State persistence functions
    function saveMapState() {
      if (isAnimating) return;
      const state = {
        zoom: zoom,
        panX: panX,
        panY: panY,
        timestamp: Date.now()
      };
      sessionStorage.setItem('cemetery_map_state', JSON.stringify(state));
    }

    function loadMapState() {
      const saved = sessionStorage.getItem('cemetery_map_state');
      if (!saved) return null;
      
      try {
        const state = JSON.parse(saved);
        // Expire state after 30 minutes of inactivity
        if (Date.now() - state.timestamp > 30 * 60 * 1000) {
          sessionStorage.removeItem('cemetery_map_state');
          return null;
        }
        return state;
      } catch (e) {
        return null;
      }
    }

    // Initialize map view
    if (mapImage) {
        const initMap = () => {
            const containerWidth = mapWrapper.clientWidth;
            const containerHeight = mapWrapper.clientHeight;
            
            // Check for saved state first
            const urlParams = new URLSearchParams(window.location.search);
            const highlightLotId = urlParams.get('highlight_lot');
            const savedState = loadMapState();

            if (highlightLotId) {
                // If highlighting a lot, use a good default and let highlightLotOnMap handle the rest
                zoom = 1;
                panX = 0;
                panY = 0;
            } else if (savedState) {
                // Restore saved state
                zoom = savedState.zoom;
                panX = savedState.panX;
                panY = savedState.panY;
            } else {
                // Calculate default zoom to fit the image properly
                // Base width is 100% of containerWidth because of CSS width: 100%
                const baseWidth = containerWidth;
                const baseHeight = (mapImage.naturalHeight / mapImage.naturalWidth) * baseWidth;

                // We want to fit the image in the container with some padding
                const padding = 40;
                const availableW = containerWidth - padding;
                const availableH = containerHeight - padding;

                const scaleX = availableW / baseWidth;
                const scaleY = availableH / baseHeight;
                
                // Fit scale to container
                const fitScale = Math.min(scaleX, scaleY, 1);
                
                // Set default zoom to be 40% larger than the fit-to-container view
                // This makes the map appear more practical and closer by default
                zoom = Math.min(fitScale * 1.4, 5);
                
                // Center the map initially
                const displayedWidth = baseWidth * zoom;
                const displayedHeight = baseHeight * zoom;

                panX = (containerWidth - displayedWidth) / 2;
                panY = (containerHeight - displayedHeight) / 2;
            }

            updateTransform();
            
            // Check for highlighted lot after initialization
            if (highlightLotId) {
                setTimeout(highlightLotOnMap, 300);
            }
        };

        if (mapImage.complete) {
            initMap();
        } else {
            mapImage.onload = initMap;
        }
    }

    function clampPan(targetPanX, targetPanY, targetZoom) {
      if (!mapWrapper || !mapCanvas || !mapImage) return { x: targetPanX, y: targetPanY };

      const wrapperW = mapWrapper.clientWidth;
      const wrapperH = mapWrapper.clientHeight;
      
      // Use natural dimensions scaled by targetZoom
      // Since map-image has width: 100%, its base width is mapWrapper.clientWidth
      const baseWidth = mapWrapper.clientWidth;
      const baseHeight = (mapImage.naturalHeight / mapImage.naturalWidth) * baseWidth;
      
      const contentW = baseWidth * targetZoom;
      const contentH = baseHeight * targetZoom;

      let x = targetPanX;
      let y = targetPanY;

      if (contentW <= wrapperW) {
        x = (wrapperW - contentW) / 2;
      } else {
        const minX = wrapperW - contentW;
        const maxX = 0;
        x = Math.min(maxX, Math.max(minX, x));
      }

      if (contentH <= wrapperH) {
        y = (wrapperH - contentH) / 2;
      } else {
        const minY = wrapperH - contentH;
        const maxY = 0;
        y = Math.min(maxY, Math.max(minY, y));
      }
      
      return { x, y };
    }

    function updateTransform(smooth = false) {
      if (!mapCanvas) return;
      
      const clamped = clampPan(panX, panY, zoom);
      panX = clamped.x;
      panY = clamped.y;

      if (smooth) {
        mapCanvas.style.transition = 'transform 0.5s cubic-bezier(0.2, 0.8, 0.2, 1)';
        isAnimating = true;
        setTimeout(() => {
          mapCanvas.style.transition = '';
          isAnimating = false;
          saveMapState();
        }, 500);
      } else {
        mapCanvas.style.transition = '';
      }

      mapCanvas.style.transform = `translate(${panX}px, ${panY}px) scale(${zoom})`;
      if (mapWrapper) {
        mapWrapper.style.setProperty('--current-zoom', zoom);
      }
      
      // Save state for persistence
      saveMapState();
    }

    function setZoomAt(newZoom, clientX, clientY) {
      if (!mapWrapper || isAnimating) return;
      const rect = mapWrapper.getBoundingClientRect();
      const mouseX = clientX - rect.left;
      const mouseY = clientY - rect.top;

      // Current point in image coordinates (unscaled)
      const worldX = (mouseX - panX) / zoom;
      const worldY = (mouseY - panY) / zoom;

      zoom = newZoom;
      
      // New pan to keep the same image point under the mouse
      panX = mouseX - worldX * zoom;
      panY = mouseY - worldY * zoom;
      
      updateTransform();
    }

    /**
     * Programmatically zoom to a specific percentage coordinate
     */
    function zoomToPoint(percentX, percentY, targetZoom = 1.8) {
      if (!mapWrapper || !mapImage) return;

      const containerWidth = mapWrapper.clientWidth;
      const containerHeight = mapWrapper.clientHeight;
      
      // Calculate base dimensions (zoom = 1)
      const baseWidth = containerWidth;
      const baseHeight = (mapImage.naturalHeight / mapImage.naturalWidth) * baseWidth;

      // Target position on base image in pixels
      const targetPxX = (percentX / 100) * baseWidth;
      const targetPxY = (percentY / 100) * baseHeight;

      // We want: targetPxX * targetZoom + panX = containerWidth / 2
      const targetPanX = (containerWidth / 2) - (targetPxX * targetZoom);
      const targetPanY = (containerHeight / 2) - (targetPxY * targetZoom);

      // Set globals and update
      zoom = targetZoom;
      panX = targetPanX;
      panY = targetPanY;

      updateTransform(true);
    }

    if (mapWrapper && mapCanvas) {
      mapWrapper.addEventListener('dragstart', (e) => {
        e.preventDefault();
      });

      mapWrapper.addEventListener('wheel', (e) => {
        e.preventDefault();
        if (isAnimating) return;

        const step = 0.2;
        const direction = e.deltaY > 0 ? -1 : 1;
        const newZoom = Math.min(5, Math.max(0.1, zoom + direction * step));

        if (newZoom !== zoom) {
          setZoomAt(newZoom, e.clientX, e.clientY);
        }
      }, { passive: false });

      mapWrapper.addEventListener('click', (e) => {
        if (didPan) {
          e.preventDefault();
          e.stopPropagation();
          didPan = false;
        }
      }, true);

      mapWrapper.addEventListener('mousedown', (e) => {
        const isExtraButton = e.button === 1 || e.button === 2;
        if ((e.button !== 0 && !isExtraButton) || isAnimating) return;
        
        isPanning = true;
        startPanX = e.clientX - panX;
        startPanY = e.clientY - panY;
        panStartClientX = e.clientX;
        panStartClientY = e.clientY;
        didPan = false;
        mapWrapper.classList.add('grabbing');
        document.body.style.userSelect = 'none';
        if (isExtraButton) e.preventDefault();
      });

      // Prevent context menu on right click
      mapWrapper.addEventListener('contextmenu', (e) => {
        if (didPan || e.button === 2) {
          e.preventDefault();
        }
      });

      window.addEventListener('mousemove', (e) => {
        if (!isPanning) return;

        const dx = e.clientX - panStartClientX;
        const dy = e.clientY - panStartClientY;
        if (!didPan && (Math.abs(dx) > 3 || Math.abs(dy) > 3)) {
          didPan = true;
        }

        panX = e.clientX - startPanX;
        panY = e.clientY - startPanY;
        updateTransform();
      });

      window.addEventListener('mouseup', () => {
        if (!isPanning) return;
        isPanning = false;
        mapWrapper.classList.remove('grabbing');
        document.body.style.userSelect = '';
      });
    }

    function showLotDetails(lot) {
      const modal = document.getElementById('lotModal');
      const modalBody = document.getElementById('modalBody');
      const modalTitle = document.getElementById('modalTitle');
      
      modalTitle.textContent = 'Lot ' + lot.lot_number;
      
      // Parse burial info if available
      let burialInfo = [];
      if (lot.burial_info) {
        burialInfo = lot.burial_info.split(',').map(info => {
          const [name, layer] = info.split('|');
          return { name, layer: parseInt(layer) || 1 };
        });
      }
      
      let html = `
        <div class="google-maps-card">
          <div class="card-header">
            <div class="lot-info">
              <h2 class="lot-title">Lot ${lot.lot_number}</h2>
              <div class="lot-location">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                  <circle cx="12" cy="10" r="3"/>
                </svg>
                <span>${lot.block_name ? lot.block_name + ', ' : ''}${lot.section_name || 'No Section'}</span>
              </div>
            </div>
            <div class="status-badge ${lot.status.toLowerCase()}">
              ${lot.status}
            </div>
          </div>
          
          <div class="card-content">
            <!-- Layer Management Section -->
            <div class="layer-info">
              <div class="layer-header">
                <div class="layer-title">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5z"/>
                    <circle cx="12" cy="12" r="3"/>
                  </svg>
                  <span>Burial Layers</span>
                </div>
                <div class="layer-actions">
                  <button class="add-layer-btn" onclick="addNewLayer(${lot.id})">
                    + Add Layer
                  </button>
                  <button class="remove-layer-btn" onclick="removeLayer(${lot.id})" title="Remove highest layer">
                    ✕ Remove Layer
                  </button>
                </div>
              </div>
              <div id="layerGrid" class="layer-grid">
                <!-- Layers will be populated by JavaScript -->
              </div>
            </div>
            
            <div class="images-section">
              <div class="section-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                  <circle cx="8.5" cy="8.5" r="1.5"/>
                  <polyline points="21 15 16 10 5 21"/>
                </svg>
                <span>Grave Images</span>
              </div>
              <div id="graveImagesContainer" class="images-container">
                <div class="images-header">
                  <span>Grave Photos</span>
                </div>
                <div id="graveImagesGrid" class="images-grid"></div>
              </div>
            </div>
            
            <div class="info-grid">
              ${lot.position ? `
                <div class="info-item">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/>
                  </svg>
                  <div>
                    <div class="info-label">Position</div>
                    <div class="info-value">${lot.position}</div>
                  </div>
                </div>
              ` : ''}
            </div>
            
            ${burialInfo.length > 0 ? `
              <div class="deceased-section">
                <div class="section-title">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                  </svg>
                  <span>Deceased Information</span>
                </div>
                ${burialInfo.map(burial => `
                  <div style="margin-bottom: 8px; padding: 8px; background: white; border-radius: 4px;">
                    <strong>${burial.name}</strong> - Layer ${burial.layer}
                  </div>
                `).join('')}
              </div>
            ` : ''}
          </div>
        </div>
      `;
      
      modalBody.innerHTML = html;
      modal.style.display = 'flex';
      
      // Load layer information
      loadLotLayers(lot.id);
      
      // Auto-load grave images when modal opens
      loadGraveImages(lot.id);
    }
    
    async function loadGraveImages(lotId) {
      const grid = document.getElementById('graveImagesGrid');
      
      grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: var(--muted);">Loading images...</div>';
      
      try {
        const burialResponse = await fetch(`../api/burial_records.php`);
        const burialData = await burialResponse.json();
        
        if (burialData.success && burialData.data) {
          const lotBurials = burialData.data.filter(record => record.lot_id == lotId);
          if (lotBurials.length > 0) {
            const imageFetches = lotBurials.map(record => 
              fetch(`../api/burial_images.php?burial_record_id=${record.id}`)
                .then(res => res.json())
                .then(data => ({
                  recordId: record.id,
                  images: (data.success && data.data) ? data.data : []
                }))
                .catch(() => ({ recordId: record.id, images: [] }))
            );
            const results = await Promise.all(imageFetches);
            const allImages = results.flatMap(r => r.images.map(img => ({ ...img, burial_record_id: r.recordId })));
            if (allImages.length > 0) {
              grid.innerHTML = allImages.map(img => `
                <div style="border: 1px solid var(--border); border-radius: 8px; overflow: hidden; cursor: pointer;" onclick="showImageGallery('${img.burial_record_id}', '${img.id}')">
                  <img src="../${img.image_path}" alt="${img.image_caption || 'Grave image'}" style="width: 100%; height: 120px; object-fit: cover;">
                  <div style="padding: 8px; font-size: 12px; color: var(--muted); text-align: center;">${img.image_caption || 'No caption'}</div>
                </div>
              `).join('');
            } else {
              grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: var(--muted); padding: 20px;">No images available</div>';
            }
          } else {
            grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: var(--muted); padding: 20px;">No burial record found</div>';
          }
        } else {
          grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: var(--muted); padding: 20px;">Failed to load burial records</div>';
        }
      } catch (error) {
        console.error('Error loading grave images:', error);
        grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: #ef4444; padding: 20px;">Error loading images</div>';
      }
    }
    
    function showImageGallery(burialRecordId, currentImageId = null) {
      // Fetch all images for the burial record
      fetch(`../api/burial_images.php?burial_record_id=${burialRecordId}`)
        .then(response => response.json())
        .then(result => {
          if (result.success && result.data && result.data.length > 0) {
            const modal = createImageGalleryModal(result.data, currentImageId);
            document.body.appendChild(modal);
            modal.style.display = 'flex';
          } else {
            showNotification('No images available for this burial record', 'warning');
          }
        })
        .catch(error => {
          console.error('Error fetching images:', error);
          showNotification('Error loading images', 'error');
        });
    }
    
    function createImageGalleryModal(images, currentImageId = null) {
      const modal = document.createElement('div');
      modal.className = 'modal-map';
      modal.style.cssText = 'background:rgba(0,0,0,0.95); z-index: 2000;';
      
      const currentIndex = currentImageId ? images.findIndex(img => img.id == currentImageId) : 0;
      const currentImage = images[currentIndex] || images[0];
      
      modal.innerHTML = `
        <div class="modal-map-content" style="max-width:95vw; max-height:95vh; background:transparent; box-shadow:none; border:none;">
          <div class="modal-map-header" style="border:none; padding:15px 20px;">
            <h3 style="color:white; margin:0;">Grave Images</h3>
            <button class="modal-map-close" style="color:white; font-size:28px;" onclick="closeImageGallery(this.closest('.modal-map'))">&times;</button>
          </div>
          <div style="padding:0 20px 20px; text-align:center;">
            <div style="position:relative; display:inline-block; max-width:100%;">
              <img src="../${currentImage.image_path}" alt="${currentImage.image_caption || 'Grave image'}" style="max-width:100%; max-height:75vh; object-fit:contain; border-radius:8px; box-shadow: 0 10px 40px rgba(0,0,0,0.5);">
              <div style="position:absolute; bottom:0; left:0; right:0; background:linear-gradient(transparent, rgba(0,0,0,0.9)); color:white; padding:25px; border-radius:0 0 8px 8px; text-align:left;">
                <h4 style="margin:0 0 8px; font-size:18px;">${currentImage.image_caption || 'Grave Image'}</h4>
                <p style="margin:0; opacity:0.8; font-size:14px;">${currentImage.image_type || 'grave_photo'}</p>
              </div>
            </div>
            
            ${images.length > 1 ? `
            <div style="display:flex; justify-content:center; gap:12px; margin-top:25px; flex-wrap:wrap; max-height:120px; overflow-y:auto;">
              ${images.map((img, index) => `
                <img src="../${img.image_path}" alt="${img.image_caption || 'Grave image'}" 
                     style="width:90px; height:70px; object-fit:cover; border:3px solid ${index === currentIndex ? 'white' : 'transparent'}; border-radius:6px; cursor:pointer; opacity:${index === currentIndex ? '1' : '0.7'}; transition:all 0.2s;"
                     onclick="updateGalleryImage(${index})"
                     onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity=${index === currentIndex ? '1' : '0.7'}">
              `).join('')}
            </div>
            
            <button onclick="navigateGallery(-1)" style="position:absolute; left:25px; top:50%; transform:translateY(-50%); background:rgba(255,255,255,0.2); color:white; border:none; border-radius:50%; width:60px; height:60px; cursor:pointer; font-size:24px; backdrop-filter:blur(10px); transition:all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">‹</button>
            <button onclick="navigateGallery(1)" style="position:absolute; right:25px; top:50%; transform:translateY(-50%); background:rgba(255,255,255,0.2); color:white; border:none; border-radius:50%; width:60px; height:60px; cursor:pointer; font-size:24px; backdrop-filter:blur(10px); transition:all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">›</button>
            ` : ''}
          </div>
        </div>
      `;
      
      // Store images and current index for navigation
      modal.galleryImages = images;
      modal.galleryIndex = currentIndex;
      
      modal.onclick = (e) => { 
        if (e.target === modal) closeImageGallery(modal); 
      };
      
      // Add navigation functions to global scope
      window.updateGalleryImage = function(index) {
        modal.galleryIndex = index;
        updateGalleryDisplay(modal);
      };
      
      window.navigateGallery = function(direction) {
        modal.galleryIndex = (modal.galleryIndex + direction + modal.galleryImages.length) % modal.galleryImages.length;
        updateGalleryDisplay(modal);
      };
      
      // Keyboard navigation
      const handleKeydown = (e) => {
        if (e.key === 'ArrowLeft') window.navigateGallery(-1);
        if (e.key === 'ArrowRight') window.navigateGallery(1);
        if (e.key === 'Escape') closeImageGallery(modal);
      };
      
      document.addEventListener('keydown', handleKeydown);
      modal.addEventListener('close', () => document.removeEventListener('keydown', handleKeydown));
      
      return modal;
    }
    
    function updateGalleryDisplay(modal) {
      const images = modal.galleryImages;
      const index = modal.galleryIndex;
      const currentImage = images[index];
      
      const mainImg = modal.querySelector('.modal-map-content img');
      const caption = modal.querySelector('.modal-map-content h4');
      const type = modal.querySelector('.modal-map-content p');
      
      mainImg.src = `../${currentImage.image_path}`;
      mainImg.alt = currentImage.image_caption || 'Grave image';
      caption.textContent = currentImage.image_caption || 'Grave Image';
      type.textContent = currentImage.image_type || 'grave_photo';
      
      // Update thumbnails
      const thumbnails = modal.querySelectorAll('.modal-map-content div[style*="flex-wrap"] img');
      thumbnails.forEach((thumb, i) => {
        thumb.style.borderColor = i === index ? 'white' : 'transparent';
        thumb.style.opacity = i === index ? '1' : '0.7';
      });
    }
    
    function closeImageGallery(modal) {
      modal.style.display = 'none';
      modal.remove();
      
      // Clean up global functions
      delete window.updateGalleryImage;
      delete window.navigateGallery;
    }
    
    async function removeLayer(lotId) {
      try {
        // Get current layers to find the highest one
        const response = await fetch(`../api/lot_layers.php?lot_id=${lotId}`);
        const data = await response.json();
        
        if (!data.success || !data.data || data.data.length === 0) {
          showNotification('No layers found for this lot', 'warning');
          return;
        }
        
        // Find the highest layer number
        const layers = data.data;
        const highestLayer = Math.max(...layers.map(l => l.layer_number));
        
        // Check if the highest layer is occupied
        const highestLayerData = layers.find(l => l.layer_number === highestLayer);
        if (highestLayerData.is_occupied) {
          showNotification(`Cannot remove Layer ${highestLayer} - it is occupied by ${highestLayerData.deceased_name || 'someone'}`, 'error');
          return;
        }
        
        showConfirmModal({
          title: 'Remove Burial Layer',
          message: `Are you sure you want to remove Layer ${highestLayer}? This action cannot be undone.`,
          type: 'danger',
          confirmText: 'Remove Layer',
          onConfirm: async () => {
            try {
              // Delete the highest layer
              const deleteResponse = await fetch(`../api/lot_layers.php`, {
                method: 'DELETE',
                headers: {
                  'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                  lot_id: lotId,
                  layer_number: highestLayer
                })
              });
              
              const deleteResult = await deleteResponse.json();
              
              if (deleteResult.success) {
                showNotification(`Layer ${highestLayer} has been removed successfully`, 'success');
                // Reload layers and lot details
                loadLotLayers(lotId);
                // Update the map marker dynamically
                updateLotMarkerOnMap(lotId);
              } else {
                showNotification('Failed to remove layer: ' + deleteResult.message, 'error');
              }
            } catch (error) {
              console.error('Error removing layer:', error);
              showNotification('Error removing layer: ' + error.message, 'error');
            }
          }
        });
        
      } catch (error) {
        console.error('Error removing layer:', error);
        showNotification('Error removing layer: ' + error.message, 'error');
      }
    }
    
    
    function showLayerDetails(lotId, layerNumber, isOccupied, deceasedName) {
      if (isOccupied === 'false') {
        // For vacant layers, redirect to burial records with pre-selected lot and layer
        window.location.href = `burial-records.php?lot_id=${lotId}&layer=${layerNumber}`;
        return;
      }
      
      // For occupied layers, show detailed modal with burial information and images
      showLayerBurialDetails(lotId, layerNumber, deceasedName);
    }
    
    async function showLayerBurialDetails(lotId, layerNumber, deceasedName) {
      try {
        // Fetch all burial records for this lot and layer
        const response = await fetch(`../api/burial_records.php`);
        const data = await response.json();
        
        if (!data.success || !data.data) {
          showNotification('Error loading burial records', 'error');
          return;
        }
        
        // Find all records for this lot and layer
        const burialRecords = data.data.filter(record => 
          record.lot_id == lotId && record.layer == layerNumber && record.is_archived == 0
        );
        
        if (burialRecords.length === 0) {
          showNotification('Burial records not found', 'warning');
          return;
        }
        
        // Fetch full details for all records
        const fullRecords = await Promise.all(burialRecords.map(async (record) => {
          const detailResponse = await fetch(`../api/burial_records.php?id=${record.id}`);
          const detailData = await detailResponse.json();
          return detailData.success ? detailData.data : record;
        }));
        
        const layerModal = document.createElement('div');
        layerModal.id = 'layerDetailsModal';
        layerModal.className = 'modal-map';
        layerModal.style.zIndex = '2000';
        
        // Format dates
        const formatDate = (dateStr) => {
            if (!dateStr || dateStr === 'N/A') return 'N/A';
            const date = new Date(dateStr);
            if (isNaN(date.getTime())) return 'N/A';
            return (date.getMonth() + 1) + '/' + date.getDate() + '/' + date.getFullYear();
        };

        const modalTitle = fullRecords.length > 1 ? `Layer ${layerNumber} - Multiple Burials (${fullRecords.length})` : `Layer ${layerNumber} - Burial Details`;

        layerModal.innerHTML = `
          <div class="modal-map-content" style="max-width: 800px; width: 95%; max-height: 90vh; overflow-y: auto; border-radius: 24px; background: #fff; padding: 0; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
            <div class="modal-map-header" style="padding: 24px 32px; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; background: #fff; z-index: 10; border-radius: 24px 24px 0 0;">
              <div>
                <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: #1e293b;">${modalTitle}</h3>
                <p style="margin: 4px 0 0 0; font-size: 14px; color: #64748b;">Lot ${fullRecords[0].lot_number} • Section ${fullRecords[0].section_name || 'N/A'}</p>
              </div>
              <button class="modal-map-close" onclick="closeLayerModal()" style="background: #f1f5f9; border: none; color: #64748b; cursor: pointer; width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; transition: all 0.2s;">&times;</button>
            </div>
            
            <div class="modal-map-body" style="padding: 32px;">
              ${fullRecords.map((fullRecord, index) => `
                <div class="burial-record-card" style="${index > 0 ? 'margin-top: 40px; padding-top: 40px; border-top: 2px dashed #e2e8f0;' : ''}">
                  <div style="background: #f8fafc; border-radius: 20px; padding: 30px; border: 1px solid #f1f5f9; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
                    
                    <!-- Header with Name and Status -->
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px;">
                      <div>
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                          ${index > 0 ? '<span style="background: #eff6ff; color: #3b82f6; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase;">Ash Burial</span>' : '<span style="background: #f0fdf4; color: #16a34a; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase;">Primary Burial</span>'}
                        </div>
                        <h2 style="margin: 0 0 4px 0; font-size: 1.75rem; font-weight: 800; color: #1e293b; letter-spacing: -0.025em;">${fullRecord.full_name}</h2>
                        <div style="display: flex; align-items: center; gap: 12px; color: #64748b; font-size: 14px; font-weight: 500;">
                          <div style="display: flex; align-items: center; gap: 4px;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            Lot ${fullRecord.lot_number} - Layer ${layerNumber}
                          </div>
                          <div style="width: 4px; height: 4px; background: #cbd5e1; border-radius: 50%;"></div>
                          <div>ID: #${fullRecord.id}</div>
                        </div>
                      </div>
                      <div style="background: #fff; color: #3b82f6; padding: 8px 16px; border-radius: 12px; font-weight: 700; font-size: 12px; border: 1px solid #dbeafe; box-shadow: 0 2px 4px rgba(59, 130, 246, 0.05);">OCCUPIED</div>
                    </div>

                    <div style="height: 1px; background: #e2e8f0; margin-bottom: 30px;"></div>

                    <!-- Info Grid -->
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-bottom: 30px;">
                      <div style="display: flex; align-items: flex-start; gap: 12px;">
                        <div style="width: 36px; height: 36px; background: #fff; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #64748b; border: 1px solid #e2e8f0;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                        <div>
                          <div style="font-size: 11px; color: #94a3b8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Age</div>
                          <div style="font-size: 15px; font-weight: 700; color: #1e293b;">${fullRecord.age ? fullRecord.age + ' years old' : 'N/A'}</div>
                        </div>
                      </div>
                      <div style="display: flex; align-items: flex-start; gap: 12px;">
                        <div style="width: 36px; height: 36px; background: #fff; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #64748b; border: 1px solid #e2e8f0;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
                        <div>
                          <div style="font-size: 11px; color: #94a3b8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Date of Birth</div>
                          <div style="font-size: 15px; font-weight: 700; color: #1e293b;">${formatDate(fullRecord.date_of_birth)}</div>
                        </div>
                      </div>
                      <div style="display: flex; align-items: flex-start; gap: 12px;">
                        <div style="width: 36px; height: 36px; background: #fff; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #64748b; border: 1px solid #e2e8f0;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
                        <div>
                          <div style="font-size: 11px; color: #94a3b8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Date of Death</div>
                          <div style="font-size: 15px; font-weight: 700; color: #1e293b;">${formatDate(fullRecord.date_of_death)}</div>
                        </div>
                      </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; margin-bottom: 30px; padding-top: 24px; border-top: 1px solid #e2e8f0;">
                      <div style="display: flex; align-items: flex-start; gap: 12px;">
                        <div style="width: 36px; height: 36px; background: #fff; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #64748b; border: 1px solid #e2e8f0;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></div>
                        <div>
                          <div style="font-size: 11px; color: #94a3b8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Date of Burial</div>
                          <div style="font-size: 15px; font-weight: 700; color: #1e293b;">${formatDate(fullRecord.date_of_burial)}</div>
                        </div>
                      </div>
                      <!-- Cause of Death hidden temporarily -->
                      <!--
                      <div style="display: flex; align-items: flex-start; gap: 12px;">
                        <div style="width: 36px; height: 36px; background: #fff; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #64748b; border: 1px solid #e2e8f0;"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
                        <div>
                          <div style="font-size: 11px; color: #94a3b8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Cause of Death</div>
                          <div style="font-size: 15px; font-weight: 700; color: #1e293b;">${fullRecord.cause_of_death || 'N/A'}</div>
                        </div>
                      </div>
                      -->
                    </div>

                    <!-- Next of Kin hidden temporarily -->
                    <!--
                    <div style="background: #fff; border-radius: 16px; padding: 20px; border: 1px solid #e2e8f0; margin-bottom: 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                      <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px; color: #3b82f6;">
                        <div style="width: 30px; height: 30px; background: #eff6ff; border-radius: 8px; display: flex; align-items: center; justify-content: center;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg></div>
                        <span style="font-size: 14px; font-weight: 800; letter-spacing: 0.02em;">NEXT OF KIN INFORMATION</span>
                      </div>
                      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                        <div>
                          <div style="font-size: 11px; color: #94a3b8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Name</div>
                          <div style="font-size: 15px; color: #1e293b; font-weight: 700;">${fullRecord.next_of_kin || 'N/A'}</div>
                        </div>
                        <div>
                          <div style="font-size: 11px; color: #94a3b8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Contact Details</div>
                          <div style="font-size: 15px; color: #1e293b; font-weight: 700;">${fullRecord.next_of_kin_contact || 'N/A'}</div>
                        </div>
                      </div>
                    </div>
                    -->

                    <!-- Remarks & Info -->
                    <div style="display: grid; grid-template-columns: 1fr; gap: 24px; margin-bottom: 24px;">
                      <!-- Farewell Notes (deceased_info) hidden temporarily -->
                      <!--
                      <div style="background: #fff; border-radius: 16px; padding: 20px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px; color: #64748b;">
                          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                          <span style="font-size: 13px; font-weight: 800;">DECEASED INFORMATION</span>
                        </div>
                        <div style="font-size: 14px; color: #475569; line-height: 1.6; font-weight: 500;">${fullRecord.deceased_info || 'N/A'}</div>
                      </div>
                      -->
                      <div style="background: #fff; border-radius: 16px; padding: 20px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px; color: #64748b;">
                          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                          <span style="font-size: 13px; font-weight: 800;">RELATIONSHIP / REMARKS</span>
                        </div>
                        <div style="font-size: 14px; color: #475569; line-height: 1.6; font-weight: 500;">${fullRecord.remarks || 'N/A'}</div>
                      </div>
                    </div>

                    <!-- Actions -->
                    <div style="display: flex; justify-content: flex-end; gap: 12px; padding-top: 20px;">
                      <button onclick="window.location.href='burial-records.php?edit_id=${fullRecord.id}'" style="padding: 10px 24px; border-radius: 12px; border: none; background: #3b82f6; color: #fff; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s; box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.2);">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        Edit Record
                      </button>
                    </div>

                    <!-- Audit Logs Section -->
                    <div id="auditLogSection_${fullRecord.id}" style="margin-top: 30px; display: none;">
                      <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px; color: #64748b;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="M12 6v6l4 2"/></svg>
                        <span style="font-size: 14px; font-weight: 800;">MOVE HISTORY & AUDIT</span>
                      </div>
                      <div id="auditLogContent_${fullRecord.id}" style="display: grid; gap: 12px;">
                        <!-- Logs will be injected here -->
                      </div>
                    </div>

                    <!-- Grave Images -->
                    <div style="margin-top: 30px;">
                      <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px; color: #64748b;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        <span style="font-size: 14px; font-weight: 800;">GRAVE PHOTOS</span>
                      </div>
                      <div id="layerGraveImagesGrid_${fullRecord.id}" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 16px; background: #fff; border-radius: 16px; padding: 20px; border: 1px solid #e2e8f0; min-height: 100px;">
                        <div style="grid-column: 1/-1; text-align: center; color: #94a3b8; font-size: 13px;">Loading images...</div>
                      </div>
                    </div>
                  </div>
                </div>
              `).join('')}
            </div>
            
            <div style="padding: 24px 32px; border-top: 1px solid #f1f5f9; display: flex; justify-content: center; background: #f8fafc; border-radius: 0 0 24px 24px;">
              <button onclick="closeLayerModal()" style="padding: 12px 40px; border-radius: 12px; border: 1px solid #e2e8f0; background: #fff; color: #1e293b; font-weight: 700; cursor: pointer; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">Close Details</button>
            </div>
          </div>
        `;
        
        document.body.appendChild(layerModal);
        layerModal.style.display = 'flex';
        
        // Load grave images and audit logs for each record
        fullRecords.forEach(record => {
          loadLayerGraveImages(record.id, `layerGraveImagesGrid_${record.id}`);
          fetchAuditLogs(record.id);
        });
        
        layerModal.onclick = (e) => { 
          if (e.target === layerModal) closeLayerModal(); 
        };
        
      } catch (error) {
        console.error('Error loading layer details:', error);
        showNotification('Error loading layer details: ' + error.message, 'error');
      }
    }

    async function fetchAuditLogs(recordId) {
      const logContainer = document.getElementById(`auditLogSection_${recordId}`);
      const logContent = document.getElementById(`auditLogContent_${recordId}`);
      if (!logContainer || !logContent) return;

      try {
        const response = await fetch(`../api/activity_logs.php?record_id=${recordId}&table=deceased_records`);
        const result = await response.json();

        if (result.success && result.data && result.data.length > 0) {
          // Filter for movement or significant updates
          const relevantLogs = result.data.filter(log => 
            log.action === 'MOVE_RECORD' || 
            log.action === 'UNASSIGN_RECORD' ||
            (log.action === 'UPDATE_RECORD' && (log.description.toLowerCase().includes('move') || log.description.toLowerCase().includes('layer')))
          );

          if (relevantLogs.length > 0) {
            logContainer.style.display = 'block';
            logContent.innerHTML = relevantLogs.map(log => `
              <div style="background: #fffbeb; border-left: 4px solid #f59e0b; padding: 12px 16px; border-radius: 8px; border-top: 1px solid #fef3c7; border-right: 1px solid #fef3c7; border-bottom: 1px solid #fef3c7;">
                <div style="font-size: 13px; font-weight: 700; color: #92400e; margin-bottom: 4px;">${log.description}</div>
                <div style="font-size: 11px; color: #b45309; display: flex; align-items: center; gap: 4px;">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                  ${new Date(log.created_at).toLocaleString()}
                </div>
              </div>
            `).join('');
          }
        }
      } catch (e) {
        console.error('Error fetching logs:', e);
      }
    }

    // Updated image loader to accept container ID
    async function loadLayerGraveImages(recordId, containerId = 'layerGraveImagesGrid') {
      const grid = document.getElementById(containerId);
      if (!grid) return;
      
      try {
        const response = await fetch(`../api/burial_images.php?burial_record_id=${recordId}`);
        const result = await response.json();
        
        if (result.success && result.data && result.data.length > 0) {
          grid.innerHTML = result.data.map(img => `
            <div class="grave-image-item" style="aspect-ratio: 1; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; cursor: pointer; position: relative; group;">
              <img src="../${img.image_path}" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s;" onclick="window.open('../${img.image_path}', '_blank')" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
            </div>
          `).join('');
        } else {
          grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: #94a3b8; font-size: 13px; padding: 20px;">No photos available for this record</div>';
        }
      } catch (error) {
        grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: #ef4444; font-size: 13px; padding: 20px;">Error loading images</div>';
      }
    }

    function closeLayerModal() {
      const layerModal = document.getElementById('layerDetailsModal');
      if (layerModal) {
        layerModal.remove();
      }
    }
    
    function closeLotModal() {
      document.getElementById('lotModal').style.display = 'none';
    }
    
    // Layer Management Functions
    async function loadLotLayers(lotId) {
      const layerGrid = document.getElementById('layerGrid');
      
      try {
        // Get both layers and burial records for this lot
        const [layersResponse, burialsResponse] = await Promise.all([
          fetch(`../api/lot_layers.php?lot_id=${lotId}`),
          fetch(`../api/burial_records.php`)
        ]);
        
        const layersData = await layersResponse.json();
        const burialsData = await burialsResponse.json();
        
        if (!layersData.success) {
          layerGrid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: #ef4444;">Error loading layers</div>';
          return;
        }
        
        // Get burial records for this lot
        const lotBurials = burialsData.success && burialsData.data ? 
          burialsData.data.filter(record => record.lot_id == lotId) : [];
        
        // Create a map of occupied layers from burial records (can have multiple burials per layer)
        const occupiedLayers = {};
        lotBurials.forEach(burial => {
          if (burial.layer) {
            if (!occupiedLayers[burial.layer]) {
              occupiedLayers[burial.layer] = [];
            }
            occupiedLayers[burial.layer].push(burial.full_name);
          }
        });
        
        // Update lot_layers table to match burial records (sync primary burial)
        await syncLayersWithBurials(lotId, layersData.data || [], occupiedLayers);
        
        // Get updated layers after sync
        const updatedLayersResponse = await fetch(`../api/lot_layers.php?lot_id=${lotId}`);
        const updatedLayersData = await updatedLayersResponse.json();
        
        const layers = updatedLayersData.success ? updatedLayersData.data : [];
        
        if (layers.length === 0) {
          layerGrid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: #6b7280;">No layers available</div>';
          return;
        }
        
        layerGrid.innerHTML = layers.map(layer => {
          const layerBurials = layer.burials || [];
          const isOccupied = layerBurials.length > 0 || layer.is_occupied;
          const deceasedName = layerBurials.length > 0 ? layerBurials.map(b => b.full_name).join(', ') : (layer.deceased_name || '');
          
          let kinInfo = '';
          if (layerBurials.length > 0) {
            const kinEntries = [...new Set(
              layerBurials
                .filter(b => b.next_of_kin)
                .map(b => {
                  const contact = b.next_of_kin_contact ? ` (${b.next_of_kin_contact})` : '';
                  return `${b.next_of_kin}${contact}`;
                })
            )];

            if (kinEntries.length === 1) {
              kinInfo = kinEntries[0];
            } else if (kinEntries.length === 2) {
              kinInfo = kinEntries.join(' • ');
            } else if (kinEntries.length > 2) {
              kinInfo = `${kinEntries[0]} +${kinEntries.length - 1} more`;
            }
          }
          
          return `
            <div class="layer-item ${isOccupied ? 'occupied' : 'vacant'}" 
                 onclick="showLayerDetails(${lotId}, ${layer.layer_number}, '${isOccupied}', '${deceasedName.replace(/'/g, "\\'")}')">
              
              <div>
                <div class="layer-number-badge">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                  Layer ${layer.layer_number}
                </div>
                
                ${isOccupied && kinInfo ? `
                  <div class="layer-kin-info" style="font-weight: 700; font-size: 15px; color: #1e293b; line-height: 1.3; margin-top: 8px; display: flex; align-items: center; gap: 6px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/></svg>
                    <span>Kin: ${kinInfo}</span>
                  </div>
                ` : ''}

                <div class="layer-deceased-name" style="${isOccupied && kinInfo ? 'font-size: 14px; color: #64748b; font-weight: 500; margin-top: 2px;' : ''}">
                  ${isOccupied ? 'Deceased: ' + deceasedName : '<span style="color: #94a3b8; font-weight: 500;">Vacant Layer</span>'}
                </div>

                ${layerBurials.length > 1 ? `
                  <div class="ash-burial-badge">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Multiple Burials
                  </div>
                ` : ''}
              </div>

              <div style="display: flex; justify-content: space-between; align-items: center; margin-top: auto;">
                <div class="layer-status-pill ${isOccupied ? 'occupied' : 'vacant'}">
                  ${isOccupied ? 'Occupied' : 'Vacant'}
                </div>
                
                ${isOccupied ? `
                  <div class="layer-view-btn">
                    View Details
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                  </div>
                ` : ''}
              </div>

              <div class="layer-indicator-dot ${isOccupied ? 'occupied' : 'vacant'}"></div>
            </div>
          `;
        }).join('');
        
      } catch (error) {
        console.error('Error loading layers:', error);
        layerGrid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: #ef4444;">Error loading layers</div>';
      }
    }
    
    // Function to sync layers with burial records
    async function syncLayersWithBurials(lotId, layers, occupiedLayers) {
      try {
        for (const layer of layers) {
          const shouldBeOccupied = occupiedLayers[layer.layer_number];
          const currentlyOccupied = layer.is_occupied;
          
          // Update layer if occupation status doesn't match
          if (shouldBeOccupied && !currentlyOccupied) {
            // Find the burial record for this layer
            const burialResponse = await fetch(`../api/burial_records.php`);
            const burialsData = await burialResponse.json();
            
            if (burialsData.success) {
              const burial = burialsData.data.find(record => 
                record.lot_id == lotId && record.layer == layer.layer_number
              );
              
              if (burial) {
                // Update the layer to mark it as occupied
                await fetch(`../api/lot_layers.php`, {
                  method: 'PUT',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({
                    id: layer.id,
                    is_occupied: 1,
                    burial_record_id: burial.id
                  })
                });
              }
            }
          } else if (!shouldBeOccupied && currentlyOccupied) {
            // Update the layer to mark it as vacant
            await fetch(`../api/lot_layers.php`, {
              method: 'PUT',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                id: layer.id,
                is_occupied: 0,
                burial_record_id: null
              })
            });
          }
        }
      } catch (error) {
        console.error('Error syncing layers:', error);
      }
    }
    
    function selectLayer(lotId, layerNumber) {
      // Redirect to burial records page with pre-selected lot and layer
      window.location.href = `burial-records.php?lot_id=${lotId}&layer=${layerNumber}`;
    }
    
    async function updateLotMarkerOnMap(lotId) {
      try {
        const response = await fetch(`../api/cemetery_lots.php?id=${lotId}`);
        const result = await response.json();
        
        if (result.success && result.data) {
          const lot = result.data;
          const marker = document.querySelector(`.lot-marker[data-lot-id="${lotId}"]`);
          if (marker) {
            // Determine actual status (logic from PHP)
            const actualStatus = lot.occupied_layers_count > 0 ? 'Occupied' : lot.status;
            
            // Update status class
            marker.className = 'lot-marker ' + actualStatus.toLowerCase();
            
            // Re-apply highlight/hidden classes if they were there
            const urlParams = new URLSearchParams(window.location.search);
            const highlightLotId = urlParams.get('highlight_lot');
            if (highlightLotId) {
              if (lotId == highlightLotId) marker.classList.add('highlighted-marker');
              else marker.classList.add('hidden-marker');
            }
            
            // Update title
            marker.title = `${lot.lot_number} - ${actualStatus}`;
            
            // Update layer indicator
            const totalLayers = lot.total_layers_count || 1;
            const occupiedLayers = lot.occupied_layers_count || 0;
            
            let indicator = marker.querySelector('.lot-layer-indicator');
            if (totalLayers > 1) {
              if (!indicator) {
                indicator = document.createElement('div');
                indicator.className = 'lot-layer-indicator';
                marker.appendChild(indicator);
              }
              indicator.textContent = `${occupiedLayers}/${totalLayers}`;
              indicator.title = `${occupiedLayers}/${totalLayers} layers occupied`;
            } else if (indicator) {
              indicator.remove();
            }
            
            // Update onclick with new lot data
            // We need to transform API data to match the format expected by showLotDetails
            const mappedLot = {
              ...lot,
              actual_status: actualStatus,
              total_layers: totalLayers,
              occupied_layers: occupiedLayers,
              deceased_names: lot.deceased_names,
              kin_names: lot.kin_names,
              burial_info: lot.deceased_names // Simple version
            };
            marker.onclick = () => showLotDetails(mappedLot);

            // Update label content to match PHP initial render
            const label = marker.querySelector('.lot-label');
            if (label) {
                let labelHtml = `<span>${lot.lot_number}</span>`;
                if (lot.kin_names) {
                    labelHtml += `<span class="kin-tag">Kin: ${lot.kin_names}</span>`;
                }
                if (lot.deceased_names) {
                    labelHtml += `<span class="deceased-tag">Deceased: ${lot.deceased_names}</span>`;
                }
                label.innerHTML = labelHtml;
            }
          }
        }
      } catch (error) {
        console.error('Error updating lot marker:', error);
      }
    }
    
    async function addNewLayer(lotId) {
      showConfirmModal({
        title: 'Add New Layer',
        message: 'Add a new burial layer to this lot? This will allow additional burials in the same location.',
        confirmText: 'Add Layer',
        icon: '➕',
        onConfirm: async () => {
          try {
            const response = await fetch('../api/lot_layers.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
              },
              body: JSON.stringify({
                lot_id: lotId,
                action: 'add_layer'
              })
            });
            
            const data = await response.json();
            
            if (data.success) {
              showNotification('New layer added successfully!', 'success');
              loadLotLayers(lotId); // Reload the layer display
              // Update the map marker dynamically
              updateLotMarkerOnMap(lotId);
            } else {
              showNotification('Error adding layer: ' + data.message, 'error');
            }
          } catch (error) {
            console.error('Error adding layer:', error);
            showNotification('Error adding layer', 'error');
          }
        }
      });
    }
    
    // Close modal when clicking outside
    document.getElementById('lotModal').onclick = function(e) {
      if (e.target === this) {
        closeLotModal();
      }
    };


    
    function highlightLotOnMap() {
      const urlParams = new URLSearchParams(window.location.search);
      const highlightLotIds = urlParams.get('highlight_lot') ? urlParams.get('highlight_lot').split(',') : [];
      const highlightSectionIds = urlParams.get('highlight_section') ? urlParams.get('highlight_section').split(',') : [];
      const highlightBlockIds = urlParams.get('highlight_block') ? urlParams.get('highlight_block').split(',') : [];
      
      if (highlightLotIds.length === 0 && highlightSectionIds.length === 0 && highlightBlockIds.length === 0) return;

      const lotMarkers = document.querySelectorAll('.lot-marker');
      const sectionRects = document.querySelectorAll('.section-rectangle');
      const blockRects = document.querySelectorAll('.block-rectangle');
      let targetMarkers = [];
      let targetRects = [];
      
      // Handle lot highlighting
      lotMarkers.forEach(marker => {
        const lotId = marker.getAttribute('data-lot-id');
        const isHighlightedLot = highlightLotIds.includes(lotId);

        if (isHighlightedLot) {
          marker.classList.add('highlighted-marker');
          targetMarkers.push(marker);
        } else if (highlightLotIds.length > 0) {
          marker.classList.add('hidden-marker');
        }
      });

      // Handle section highlighting
      sectionRects.forEach(rect => {
        const sectionId = rect.getAttribute('data-section-id');
        const blockId = rect.getAttribute('data-block-id');
        const isHighlightedSection = highlightSectionIds.includes(sectionId);
        const isPartOfHighlightedBlock = highlightBlockIds.includes(blockId);

        if (isHighlightedSection || isPartOfHighlightedBlock) {
          rect.classList.add('active');
          targetRects.push(rect);
        } else {
          rect.classList.remove('active');
        }
      });

      // Handle block highlighting
      blockRects.forEach(rect => {
        const blockId = rect.getAttribute('data-block-id');
        const isHighlightedBlock = highlightBlockIds.includes(blockId);

        if (isHighlightedBlock) {
          rect.classList.add('active');
          targetRects.push(rect);
        } else {
          rect.classList.remove('active');
        }
      });
      
      if (targetMarkers.length > 0 || targetRects.length > 0) {
        let minX = 100, minY = 100, maxX = 0, maxY = 0;
        
        // Calculate bounding box for lots
        targetMarkers.forEach(marker => {
          const x = parseFloat(marker.style.left);
          const y = parseFloat(marker.style.top);
          const w = parseFloat(marker.style.width);
          const h = parseFloat(marker.style.height);
          minX = Math.min(minX, x);
          minY = Math.min(minY, y);
          maxX = Math.max(maxX, x + w);
          maxY = Math.max(maxY, y + h);
        });

        // Calculate bounding box for sections
        targetRects.forEach(rect => {
          const x = parseFloat(rect.style.left);
          const y = parseFloat(rect.style.top);
          const w = parseFloat(rect.style.width);
          const h = parseFloat(rect.style.height);
          minX = Math.min(minX, x);
          minY = Math.min(minY, y);
          maxX = Math.max(maxX, x + w);
          maxY = Math.max(maxY, y + h);
        });

        const centerX = (minX + maxX) / 2;
        const centerY = (minY + maxY) / 2;
        
        const spanX = maxX - minX;
        const spanY = maxY - minY;
        const maxSpan = Math.max(spanX, spanY);
        
        let targetZoom = 1.5;
        if (maxSpan > 0) {
            targetZoom = Math.min(2.5, Math.max(0.5, 40 / maxSpan));
        }

        setTimeout(() => {
          zoomToPoint(centerX, centerY, targetZoom);
        }, 300);
        
        const actionsContainer = document.querySelector('.dashboard-header .header-actions');
        if (actionsContainer && !document.getElementById('clearHighlightBtn')) {
           const clearBtn = document.createElement('button');
           clearBtn.id = 'clearHighlightBtn';
           clearBtn.className = 'btn-outline';
           clearBtn.style.color = '#ef4444';
           clearBtn.style.borderColor = '#fee2e2';
           clearBtn.style.background = '#fef2f2';
           clearBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg> Clear Highlight';
           clearBtn.onclick = () => window.location.href = 'cemetery-map.php';
           actionsContainer.insertBefore(clearBtn, actionsContainer.firstChild);
        }
      }
    }
    
    // Confirmation Modal System
    function showConfirmModal(options) {
      const modal = document.getElementById('confirmModal');
      const title = document.getElementById('confirmTitle');
      const message = document.getElementById('confirmMessage');
      const icon = document.getElementById('confirmIcon');
      const confirmBtn = document.getElementById('confirmBtn');
      
      title.textContent = options.title || 'Confirm Action';
      message.textContent = options.message || 'Are you sure you want to proceed?';
      confirmBtn.textContent = options.confirmText || 'Confirm';
      
      // Reset classes
      icon.className = 'confirm-icon';
      confirmBtn.className = 'btn-confirm-action';
      
      if (options.type === 'danger') {
        icon.classList.add('danger');
        confirmBtn.classList.add('danger');
        icon.textContent = '⚠';
      } else {
        icon.textContent = options.icon || 'ℹ';
      }
      
      modal.style.display = 'flex';
      
      confirmBtn.onclick = () => {
        closeConfirmModal();
        if (options.onConfirm) options.onConfirm();
      };
    }
    
    function closeConfirmModal() {
      document.getElementById('confirmModal').style.display = 'none';
    }
    
    function showNotification(message, type = 'info') {
      const notification = document.createElement('div');
      notification.className = `notification ${type}`;
      
      const iconMap = {
        success: '✓',
        error: '✕',
        warning: '!',
        info: 'i'
      };

      const titleMap = {
        success: 'Success',
        error: 'Error',
        warning: 'Warning',
        info: 'Info'
      };

      notification.innerHTML = `
        <div class="notification-icon">${iconMap[type]}</div>
        <div class="notification-content">
          <div class="notification-title">${titleMap[type]}</div>
          <div class="notification-message">${message}</div>
        </div>
        ${type === 'error' ? '<button class="notification-close" onclick="this.parentElement.remove()">&times;</button>' : ''}
      `;
      
      document.body.appendChild(notification);
      
      // Trigger animation
      setTimeout(() => notification.classList.add('show'), 10);

      // Auto-remove unless it's an error
      if (type !== 'error') {
        setTimeout(() => {
          notification.classList.remove('show');
          setTimeout(() => notification.remove(), 400);
        }, 4000);
      } else {
        // Errors stay longer (10s) or until closed
        setTimeout(() => {
          if (notification.parentNode) {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 400);
          }
        }, 10000);
      }
    }
    
    // Initialize highlighting when page loads
    document.addEventListener('DOMContentLoaded', function() {
      setTimeout(highlightLotOnMap, 500); // Small delay to ensure map is loaded
    });
  </script>
  <!-- Confirmation Modal -->
  <div id="confirmModal" class="confirm-modal">
    <div class="confirm-modal-content">
      <div id="confirmIcon" class="confirm-icon">⚠</div>
      <h3 id="confirmTitle" class="confirm-title">Confirm Action</h3>
      <p id="confirmMessage" class="confirm-message">Are you sure you want to proceed?</p>
      <div class="confirm-actions">
        <button class="btn-confirm-cancel" onclick="closeConfirmModal()">Cancel</button>
        <button id="confirmBtn" class="btn-confirm-action">Confirm</button>
      </div>
    </div>
  </div>

  <script src="../assets/js/app.js"></script>
</body>
</html>
