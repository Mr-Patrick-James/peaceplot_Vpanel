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
$sections = [];
$blocks = [];
$mapImage = 'cemetery.jpg';

if ($conn) {
    try {
        // Fetch sections
        $sectionStmt = $conn->query("SELECT * FROM sections ORDER BY name ASC");
        $sections = $sectionStmt->fetchAll();

        // Fetch blocks
        $blockStmt = $conn->query("SELECT * FROM blocks ORDER BY name ASC");
        $blocks = $blockStmt->fetchAll();

        $stmt = $conn->query("
            SELECT cl.*, s.name as section_name, b.name as block_name,
                   (SELECT COUNT(*) FROM lot_layers ll WHERE ll.lot_id = cl.id) as total_layers,
                   (SELECT COUNT(*) FROM lot_layers ll WHERE ll.lot_id = cl.id AND ll.is_occupied = 1) as occupied_layers,
                   COUNT(DISTINCT dr.id) as burial_count,
                   CASE 
                       WHEN COUNT(DISTINCT dr.id) > 0 THEN 'Occupied'
                       WHEN EXISTS (SELECT 1 FROM lot_layers ll WHERE ll.lot_id = cl.id AND ll.is_occupied = 1) THEN 'Occupied'
                       ELSE cl.status
                   END as actual_status,
                   dr.full_name as deceased_name 
            FROM cemetery_lots cl 
            LEFT JOIN sections s ON cl.section_id = s.id
            LEFT JOIN blocks b ON s.block_id = b.id
            LEFT JOIN deceased_records dr ON cl.id = dr.lot_id 
            GROUP BY cl.id
            ORDER BY LENGTH(cl.lot_number), cl.lot_number
        ");
        $lots = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PeacePlot Admin - Map Editor</title>
  <link rel="stylesheet" href="../assets/css/styles.css" />
  <style>
    .dashboard-header {
      background: #fff;
      padding: 28px 32px;
      border-radius: 20px;
      margin-bottom: 28px;
      box-shadow: 0 4px 24px rgba(0,0,0,0.02);
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: relative;
      border: 1px solid #f1f5f9;
    }
    .header-left .title {
      font-size: 26px;
      font-weight: 600;
      color: #0f172a;
      margin: 0 0 6px 0;
      letter-spacing: -0.02em;
    }
    .header-left .subtitle {
      font-size: 14px;
      color: #64748b;
      margin: 0;
      font-weight: 400;
    }
    .breadcrumbs {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      color: #94a3b8;
      margin-bottom: 8px;
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
    .btn-outline:hover { 
      background: #f8fafc; 
      border-color: #cbd5e1;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }

    .btn-primary-modern {
      background: #3b82f6;
      color: #fff;
      padding: 10px 20px;
      border-radius: 10px;
      border: none;
      font-weight: 700;
      font-size: 14px;
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
      transition: all 0.2s;
    }
    .btn-primary-modern:hover { 
      background: #2563eb; 
      transform: translateY(-1px);
      box-shadow: 0 6px 16px rgba(59, 130, 246, 0.4);
    }

    .editor-container {
      background: white;
      border-radius: 20px;
      padding: 32px;
      margin-top: 24px;
      box-shadow: 0 4px 24px rgba(0,0,0,0.02);
      border: 1px solid #f1f5f9;
    }
    
    .editor-toolbar {
      display: flex;
      gap: 16px;
      margin-bottom: 24px;
      padding: 16px 20px;
      background: #f8fafc;
      border-radius: 12px;
      flex-wrap: wrap;
      align-items: center;
      border: 1px solid #f1f5f9;
    }
    
    .tool-btn {
      padding: 10px 18px;
      border: 1px solid #e2e8f0;
      background: white;
      border-radius: 10px;
      cursor: pointer;
      font-weight: 600;
      color: #475569;
      font-size: 14px;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      gap: 10px;
      box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    
    .tool-btn:hover {
      border-color: var(--primary);
      background: #eff6ff;
      color: var(--primary);
    }
    
    .tool-btn.active {
      background: var(--primary);
      color: white;
      border-color: var(--primary);
      box-shadow: 0 4px 12px rgba(47, 109, 246, 0.2);
    }
    
    .zoom-controls {
      background: white;
      padding: 6px;
      border-radius: 12px;
      border: 1px solid #e2e8f0;
      display: flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    }
    
    .zoom-divider {
      width: 1px;
      height: 24px;
      background: #e2e8f0;
      margin: 0 4px;
    }
    
    .zoom-btn {
      width: 36px;
      height: 36px;
      border: 1px solid #e2e8f0;
      background: white;
      border-radius: 8px;
      cursor: pointer;
      font-size: 20px;
      font-weight: 500;
      color: #64748b;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
    }
    
    .zoom-btn:hover {
      border-color: var(--primary);
      color: var(--primary);
      background: #eff6ff;
    }
    
    .zoom-level {
      font-weight: 600;
      min-width: 50px;
      text-align: center;
      color: #475569;
      font-size: 13px;
      user-select: none;
    }
    
    .map-canvas-wrapper {
      position: relative;
      width: 100%;
      height: 650px; /* Increased height */
      overflow: hidden;
      border-radius: 16px;
      border: 1px solid #e2e8f0;
      background: #f8fafc;
      cursor: grab;
      box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);
    }
    
    .map-canvas-wrapper.grabbing {
      cursor: grabbing;
    }
    
    .map-canvas-wrapper.crosshair {
      cursor: crosshair;
    }
    
    .map-canvas {
      position: absolute;
      transform-origin: 0 0;
    }
    
    .btn-reset {
      padding: 0 12px;
      height: 36px;
      min-width: auto;
      border-radius: 8px;
      box-shadow: none;
      border: none;
      background: transparent;
      color: #64748b;
      font-weight: 500;
    }
    
    .btn-reset:hover {
       background: #f1f5f9;
       color: var(--primary);
     }

     .map-canvas img {
       display: block;
       user-select: none;
       pointer-events: none;
     }
    
    .lot-rectangle {
      position: absolute;
      border: calc(1.5px + 1.5px / var(--current-zoom, 1)) solid;
      box-sizing: border-box;
      pointer-events: all;
      cursor: pointer;
      transition: background-color 0.2s;
    }

    .lot-remove-btn {
      position: absolute;
      top: calc(-5px - 5px / var(--current-zoom, 1));
      right: calc(-5px - 5px / var(--current-zoom, 1));
      width: calc(11px + 11px / var(--current-zoom, 1));
      height: calc(11px + 11px / var(--current-zoom, 1));
      border-radius: 999px;
      border: calc(1px + 1px / var(--current-zoom, 1)) solid rgba(0,0,0,0.2);
      background: rgba(255,255,255,0.95);
      color: #111827;
      font-weight: 900;
      font-size: calc(7px + 7px / var(--current-zoom, 1));
      line-height: calc(8px + 8px / var(--current-zoom, 1));
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      z-index: 200;
    }

    .lot-remove-btn:hover {
      background: #ef4444;
      color: white;
      border-color: rgba(239,68,68,0.6);
    }
    
    .lot-rectangle:hover {
      border-width: calc(2px + 2px / var(--current-zoom, 1));
      box-shadow: 0 0 calc(6px + 6px / var(--current-zoom, 1)) rgba(0,0,0,0.3);
      z-index: 100;
    }
    
    .lot-rectangle.vacant {
      border-color: #22c55e;
      background: rgba(34, 197, 94, 0.4);
    }
    
    .lot-rectangle.occupied {
      border-color: #f97316;
      background: rgba(249, 115, 22, 0.4);
    }
    
    .lot-rectangle.maintenance {
      border-color: #f59e0b;
      background: rgba(245, 158, 11, 0.4);
    }
    
    .hidden-marker {
      display: none !important;
    }
    
    .highlighted-marker {
      z-index: 1000 !important;
      border-width: calc(3px + 3px / var(--current-zoom, 1)) !important;
      border-color: #ef4444 !important;
      background: rgba(239, 68, 68, 0.3) !important;
      box-shadow: 0 0 0 calc(2px + 2px / var(--current-zoom, 1)) white, 0 0 20px rgba(239, 68, 68, 0.8) !important;
    }
    
    .highlighted-marker::after {
      content: '📍';
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -100%);
      font-size: calc(14px + 14px / var(--current-zoom, 1));
      filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
      animation: pinBounce 2s infinite;
      pointer-events: none;
      z-index: 1010;
    }
    
    @keyframes pinBounce {
      0%, 100% { transform: translate(-50%, -100%); }
      50% { transform: translate(-50%, -120%); }
    }
    
    .lot-label {
      position: absolute;
      top: 0.5px;
      left: 0.5px;
      background: rgba(0,0,0,0.8);
      color: white;
      padding: calc(0.2px + 0.3px / var(--current-zoom, 1)) calc(1px + 1px / var(--current-zoom, 1));
      border-radius: calc(0.5px + 0.5px / var(--current-zoom, 1));
      font-size: calc(3.5px + 3.5px / var(--current-zoom, 1));
      font-weight: 700;
      pointer-events: none;
      display: flex;
      flex-direction: column;
      line-height: 1.1;
      z-index: 2;
    }

    .lot-rectangle.vertical .lot-label {
      width: auto;
      max-width: 100%;
    }

    .lot-label .section-tag {
      font-size: 0.7em;
      opacity: 0.9;
      font-weight: 500;
    }

    .lot-rectangle.vertical .section-tag {
      position: absolute;
      bottom: 0;
      left: 0;
      width: 100%;
      background: rgba(0,0,0,0.85);
      padding: calc(0.2px + 0.3px / var(--current-zoom, 1)) 0;
      border-radius: 0 0 calc(0.5px + 0.5px / var(--current-zoom, 1)) calc(0.5px + 0.5px / var(--current-zoom, 1));
      text-align: center;
      font-size: calc(2.5px + 2.5px / var(--current-zoom, 1));
      opacity: 1;
      font-weight: 600;
      line-height: 1;
      z-index: 2;
      box-sizing: border-box;
      color: white;
    }

    .lot-rectangle.vertical .lot-label .section-tag {
      display: none;
    }

    .lot-layer-indicator {
      position: absolute;
      top: calc(0.5px + 0.5px / var(--current-zoom, 1));
      right: calc(0.5px + 0.5px / var(--current-zoom, 1));
      background: rgba(0,0,0,0.8);
      color: white;
      border-radius: 50%;
      width: calc(5px + 3px / var(--current-zoom, 1));
      height: calc(5px + 3px / var(--current-zoom, 1));
      font-size: calc(2.5px + 2.5px / var(--current-zoom, 1));
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      pointer-events: none;
    }
    
    .drawing-rect {
      position: absolute;
      border: calc(1.5px + 1.5px / var(--current-zoom, 1)) dashed var(--primary);
      background: rgba(47, 109, 246, 0.1);
      pointer-events: none;
    }
    
    .assign-modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.6);
      backdrop-filter: blur(4px);
      align-items: center;
      justify-content: center;
      padding: 20px;
      box-sizing: border-box;
    }
    
    .assign-modal.show {
      display: flex;
    }
    
    .assign-modal-content {
      background: white;
      border-radius: 12px;
      width: 100%;
      max-width: 600px;
      max-height: 90vh;
      overflow-y: auto;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
      animation: modalSlideIn 0.3s ease-out;
    }
    
    @keyframes modalSlideIn {
      from {
        opacity: 0;
        transform: translateY(-20px) scale(0.95);
      }
      to {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }
    
    .assign-modal-header {
      padding: 20px 24px;
      border-bottom: 1px solid var(--border);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .assign-modal-header h3 {
      margin: 0;
      font-size: 18px;
      color: var(--text);
    }
    
    .modal-close {
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
    
    .modal-close:hover {
      background: var(--page);
      color: var(--text);
    }
    
    .assign-modal-body {
      padding: 24px;
    }
    
    .assign-modal-footer {
      padding: 20px 24px;
      border-top: 1px solid var(--border);
      display: flex;
      justify-content: flex-end;
      gap: 12px;
    }
    
    .form-group {
      margin-bottom: 16px;
    }
    
    .form-group label {
      display: block;
      margin-bottom: 6px;
      font-weight: 600;
      font-size: 14px;
      color: var(--text);
    }
    
    .form-group input,
    .form-group select {
      width: 100%;
      padding: 10px 12px;
      border: 2px solid var(--border);
      border-radius: 8px;
      font-size: 14px;
      transition: border-color 0.2s;
      box-sizing: border-box;
    }
    
    .form-group input:focus,
    .form-group select:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(47, 109, 246, 0.1);
    }
    
    .form-group input[type="radio"] {
      width: auto;
      margin-right: 8px;
      margin-bottom: 0;
    }
    
    .form-group label:has(input[type="radio"]) {
      display: inline-flex;
      align-items: center;
      margin-bottom: 0;
      margin-right: 20px;
      font-weight: 500;
      cursor: pointer;
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

    .notification.success { background: #22c55e; } /* Vibrant Green */
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
        <div class="dropdown active">
          <a href="#" class="dropdown-toggle active" onclick="this.parentElement.classList.toggle('active'); return false;">
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
            <a href="map-editor.php" class="active"><span>Map Editor</span></a>
          </div>
        </div>
        <a href="cemetery-map.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2V6z" /><path d="M9 4v14" /><path d="M15 6v14" /></svg></span><span>Cemetery Map</span></a>
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
            <span class="current">Map Editor</span>
          </div>
          <h1 class="title">Map Editor</h1>
          <p class="subtitle">Design and organize cemetery lots on the visual map</p>
        </div>

        <div class="header-actions">
          <button class="btn-outline" onclick="window.location.href='cemetery-map.php'">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
            </svg>
            View Map
          </button>
        </div>
      </header>

      <div class="editor-container">
        <div class="editor-toolbar">
          <button class="tool-btn active" id="drawBtn" onclick="setTool('draw')">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="3" width="18" height="18" rx="2"/>
            </svg>
            Draw Rectangle
          </button>
          
          <button class="tool-btn" id="panBtn" onclick="setTool('pan')">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M5 9l-3 3 3 3M9 5l3-3 3 3M15 19l-3 3-3-3M19 9l3 3-3 3M2 12h20M12 2v20"/>
            </svg>
            Pan
          </button>
          
          <div class="zoom-controls">
            <button class="zoom-btn" onclick="zoomOut()">−</button>
            <span class="zoom-level" id="zoomLevel">100%</span>
            <button class="zoom-btn" onclick="zoomIn()">+</button>
            <div class="zoom-divider"></div>
            <button class="btn-reset" onclick="resetView()">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                <path d="M3 3v5h5"/>
              </svg>
              Reset View
            </button>
          </div>
          
          <button class="btn-primary-modern" onclick="saveAllLots()" style="margin-left:auto;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
              <polyline points="17 21 17 13 7 13 7 21"/>
              <polyline points="7 3 7 8 15 8"/>
            </svg>
            Save All Changes
          </button>
        </div>

        <div class="map-canvas-wrapper" id="mapWrapper">
          <div class="map-canvas" id="mapCanvas">
            <img src="../assets/images/<?php echo htmlspecialchars($mapImage); ?>" 
                 alt="Cemetery Map" 
                 id="mapImage">
            <div id="rectanglesContainer"></div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <div id="assignModal" class="assign-modal">
    <div class="assign-modal-content" style="max-width:600px;">
      <div class="assign-modal-header">
        <h3>Assign or Create Lot</h3>
        <button class="modal-close" onclick="closeAssignModal()">&times;</button>
      </div>
      <div class="assign-modal-body">
        <div class="form-group">
          <label>
            <input type="radio" name="assignMode" value="existing" checked onchange="toggleAssignMode()">
            Assign Existing Lot
          </label>
          <label style="margin-left:20px;">
            <input type="radio" name="assignMode" value="new" onchange="toggleAssignMode()">
            Create New Lot
          </label>
        </div>
        
        <!-- Existing Lot Selection -->
        <div id="existingLotSection" class="form-group">
          <label>Select Cemetery Lot:</label>
          <select id="lotSelect">
            <option value="">-- Select a lot --</option>
            <?php foreach ($lots as $lot): ?>
              <?php 
                // Skip lots that are already marked on the map
                if ($lot['map_x'] !== null && $lot['map_y'] !== null) continue; 
              ?>
              <option value="<?php echo $lot['id']; ?>" 
                      data-lot='<?php echo htmlspecialchars(json_encode($lot)); ?>'>
                <?php echo htmlspecialchars($lot['lot_number']); ?> - 
                <?php echo htmlspecialchars($lot['section_name'] ?? 'No Section'); ?> 
                (<?php echo htmlspecialchars($lot['status']); ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <!-- New Lot Creation Form -->
        <div id="newLotSection" style="display:none;">
          <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
            <div class="form-group">
              <label>Lot Number *</label>
              <input type="text" id="newLotNumber" placeholder="e.g., A-001" required>
              <div id="newLotLatestHint" style="margin-top: 6px; font-size: 12px; color: #64748b;"></div>
            </div>
            
            <div class="form-group">
              <label>Section (Block) *</label>
              <select id="newSectionId" required>
                <option value="">Select Section</option>
                <?php foreach ($sections as $section): ?>
                  <?php 
                    // Find the block name for this section
                    $blockName = 'No Block';
                    foreach ($blocks as $b) {
                      if ($b['id'] == $section['block_id']) {
                        $blockName = $b['name'];
                        break;
                      }
                    }
                  ?>
                  <option value="<?php echo $section['id']; ?>" data-section-name="<?php echo htmlspecialchars($section['name']); ?>">
                    <?php echo htmlspecialchars($section['name']); ?> (<?php echo htmlspecialchars($blockName); ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label>Position</label>
              <input type="text" id="newPosition" placeholder="e.g., North Corner">
            </div>
            
            <div class="form-group">
              <label>Status</label>
              <select id="newStatus">
                <option value="Vacant" selected>Vacant</option>
                <option value="Occupied">Occupied</option>
                <option value="Maintenance">Maintenance</option>
              </select>
            </div>
          </div>
        </div>
      </div>
      <div class="assign-modal-footer">
        <button class="btn-secondary" onclick="closeAssignModal()">Cancel</button>
        <button class="btn-primary" onclick="assignLot()">Assign</button>
      </div>
    </div>
  </div>

  <script>
    const lotsData = <?php echo json_encode($lots); ?>;
    let currentTool = 'draw';
    let zoom = 1;
    let panX = 0;
    let panY = 0;
    let isPanning = false;
    let startPanX, startPanY;
    let isDrawing = false;
    let startX, startY;
    let currentRect = null;
    let rectangles = [];
    let pendingRect = null;
    let isAnimating = false;

    const mapWrapper = document.getElementById('mapWrapper');
    const mapCanvas = document.getElementById('mapCanvas');
    const mapImage = document.getElementById('mapImage');
    const rectanglesContainer = document.getElementById('rectanglesContainer');

    // State persistence functions
    function saveMapState() {
      if (isAnimating) return;
      const state = {
        zoom: zoom,
        panX: panX,
        panY: panY,
        timestamp: Date.now()
      };
      sessionStorage.setItem('map_editor_state', JSON.stringify(state));
    }

    function loadMapState() {
      const saved = sessionStorage.getItem('map_editor_state');
      if (!saved) return null;
      
      try {
        const state = JSON.parse(saved);
        // Expire state after 30 minutes of inactivity
        if (Date.now() - state.timestamp > 30 * 60 * 1000) {
          sessionStorage.removeItem('map_editor_state');
          return null;
        }
        return state;
      } catch (e) {
        return null;
      }
    }

    // Load existing rectangles from lots
    lotsData.forEach(lot => {
      if (lot.map_x !== null && lot.map_y !== null && lot.map_width !== null && lot.map_height !== null) {
        addRectangle(lot.map_x, lot.map_y, lot.map_width, lot.map_height, lot);
      }
    });

    // Initialize map view
    mapImage.onload = () => {
        const containerWidth = mapWrapper.clientWidth;
        const containerHeight = mapWrapper.clientHeight;
        const imgWidth = mapImage.naturalWidth;
        const imgHeight = mapImage.naturalHeight;

        const urlParams = new URLSearchParams(window.location.search);
        const highlightLotId = urlParams.get('highlight_lot');
        const savedState = loadMapState();

        if (highlightLotId) {
            // If highlighting, let highlightLotOnMap handle it
            zoom = 1;
            panX = 0;
            panY = 0;
        } else if (savedState) {
            zoom = savedState.zoom;
            panX = savedState.panX;
            panY = savedState.panY;
        } else {
            // Calculate zoom to fit width or height (whichever is smaller)
            // Or set a fixed initial zoom for large images
            if (imgWidth > 3000 || imgHeight > 3000) {
                zoom = 0.2; // Start zoomed out for large maps
            } else {
                const scaleX = containerWidth / imgWidth;
                const scaleY = containerHeight / imgHeight;
                zoom = Math.min(scaleX, scaleY, 1);
            }

            // Center the map
            const displayedWidth = imgWidth * zoom;
            const displayedHeight = imgHeight * zoom;

            panX = (containerWidth - displayedWidth) / 2;
            panY = (containerHeight - displayedHeight) / 2;
        }

        updateTransform();
    };
    // Trigger onload if image is already cached
    if (mapImage.complete) {
        mapImage.onload();
    }

    // Check for highlight lot on page load
    document.addEventListener('DOMContentLoaded', () => {
      setTimeout(highlightLotOnMap, 500);
    });

    function setTool(tool) {
      currentTool = tool;
      document.getElementById('drawBtn').classList.toggle('active', tool === 'draw');
      document.getElementById('panBtn').classList.toggle('active', tool === 'pan');
      
      if (tool === 'draw') {
        mapWrapper.classList.add('crosshair');
        mapWrapper.classList.remove('grabbing');
      } else {
        mapWrapper.classList.remove('crosshair');
      }
    }

    function zoomIn() {
      zoom = Math.min(zoom + 0.25, 3);
      updateTransform();
    }

    function zoomOut() {
      zoom = Math.max(zoom - 0.25, 0.05);
      updateTransform();
    }

    function resetView() {
      const containerWidth = mapWrapper.clientWidth;
      const containerHeight = mapWrapper.clientHeight;
      const imgWidth = mapImage.naturalWidth;
      const imgHeight = mapImage.naturalHeight;

      if (imgWidth > 3000 || imgHeight > 3000) {
          zoom = 0.2;
      } else {
          const scaleX = containerWidth / imgWidth;
          const scaleY = containerHeight / imgHeight;
          zoom = Math.min(scaleX, scaleY, 1);
      }

      const displayedWidth = imgWidth * zoom;
      const displayedHeight = imgHeight * zoom;
      panX = (containerWidth - displayedWidth) / 2;
      panY = (containerHeight - displayedHeight) / 2;

      updateTransform();
    }

    function setZoomAt(newZoom, clientX, clientY) {
      const rect = mapWrapper.getBoundingClientRect();
      const mouseX = clientX - rect.left;
      const mouseY = clientY - rect.top;

      const worldX = (mouseX - panX) / zoom;
      const worldY = (mouseY - panY) / zoom;

      zoom = newZoom;
      panX = mouseX - worldX * zoom;
      panY = mouseY - worldY * zoom;
      updateTransform();
    }

    /**
     * Programmatically zoom to a specific percentage coordinate
     */
    function zoomToPoint(percentX, percentY, targetZoom = 1.8) {
      const containerWidth = mapWrapper.clientWidth;
      const containerHeight = mapWrapper.clientHeight;
      const imgWidth = mapImage.naturalWidth;
      const imgHeight = mapImage.naturalHeight;

      // Target position in pixels on natural image
      const targetPxX = (percentX / 100) * imgWidth;
      const targetPxY = (percentY / 100) * imgHeight;

      // Center it: targetPxX * targetZoom + panX = containerWidth / 2
      panX = (containerWidth / 2) - (targetPxX * targetZoom);
      panY = (containerHeight / 2) - (targetPxY * targetZoom);
      zoom = targetZoom;

      updateTransform();
    }

    function highlightLotOnMap() {
      const urlParams = new URLSearchParams(window.location.search);
      const highlightLotId = urlParams.get('highlight_lot');
      
      if (!highlightLotId) return;

      const lotRects = document.querySelectorAll('.lot-rectangle');
      let targetRect = null;
      
      lotRects.forEach(rect => {
        if (rect.getAttribute('data-lot-id') === highlightLotId) {
          targetRect = rect;
          rect.classList.add('highlighted-marker');
        } else {
          rect.classList.add('hidden-marker');
        }
      });
      
      if (targetRect) {
        // Position relative to map canvas using percentages
        const lotX = parseFloat(targetRect.style.left);
        const lotY = parseFloat(targetRect.style.top);
        const lotW = parseFloat(targetRect.style.width);
        const lotH = parseFloat(targetRect.style.height);
        
        // Smoothly zoom to the lot
        setTimeout(() => {
          const centerX = lotX + (lotW / 2);
          const centerY = lotY + (lotH / 2);
          zoomToPoint(centerX, centerY, 2.5);
        }, 300);

        // Add clear highlight button
        const toolbar = document.querySelector('.editor-toolbar');
        if (toolbar && !document.getElementById('clearHighlightBtn')) {
           const clearBtn = document.createElement('button');
           clearBtn.id = 'clearHighlightBtn';
           clearBtn.className = 'tool-btn';
           clearBtn.style.background = '#6b7280';
           clearBtn.style.color = 'white';
           clearBtn.innerHTML = '✕ Clear Highlight';
           clearBtn.onclick = () => window.location.href = 'map-editor.php';
           toolbar.appendChild(clearBtn);
        }
      }
    }

    function updateTransform() {
      mapCanvas.style.transform = `translate(${panX}px, ${panY}px) scale(${zoom})`;
      document.getElementById('zoomLevel').textContent = Math.round(zoom * 100) + '%';
      mapWrapper.style.setProperty('--current-zoom', zoom);
      saveMapState();
    }

    mapWrapper.addEventListener('wheel', (e) => {
      e.preventDefault();

      const step = 0.15;
      const direction = e.deltaY > 0 ? -1 : 1;
      const newZoom = Math.min(3, Math.max(0.05, zoom + direction * step));

      if (newZoom !== zoom) {
        setZoomAt(newZoom, e.clientX, e.clientY);
      }
    }, { passive: false });

    mapWrapper.addEventListener('mousedown', (e) => {
      // Ignore right clicks
      if (e.button !== 0) return;
      
      const rect = mapWrapper.getBoundingClientRect();
      const x = (e.clientX - rect.left - panX) / zoom;
      const y = (e.clientY - rect.top - panY) / zoom;

      if (currentTool === 'pan') {
        isPanning = true;
        startPanX = e.clientX - panX;
        startPanY = e.clientY - panY;
        mapWrapper.classList.add('grabbing');
      } else if (currentTool === 'draw') {
        isDrawing = true;
        startX = x;
        startY = y;
        
        currentRect = document.createElement('div');
        currentRect.className = 'drawing-rect';
        currentRect.style.left = x + 'px';
        currentRect.style.top = y + 'px';
        rectanglesContainer.appendChild(currentRect);
      }
    });

    window.addEventListener('mousemove', (e) => {
      if (isPanning) {
        panX = e.clientX - startPanX;
        panY = e.clientY - startPanY;
        updateTransform();
      } else if (isDrawing && currentRect) {
        const rect = mapWrapper.getBoundingClientRect();
        const x = (e.clientX - rect.left - panX) / zoom;
        const y = (e.clientY - rect.top - panY) / zoom;
        
        const width = Math.abs(x - startX);
        const height = Math.abs(y - startY);
        const left = Math.min(x, startX);
        const top = Math.min(y, startY);
        
        currentRect.style.left = left + 'px';
        currentRect.style.top = top + 'px';
        currentRect.style.width = width + 'px';
        currentRect.style.height = height + 'px';
      }
    });

    window.addEventListener('mouseup', (e) => {
      if (isPanning) {
        isPanning = false;
        mapWrapper.classList.remove('grabbing');
      } else if (isDrawing && currentRect) {
        isDrawing = false;
        
        const imageWidth = mapImage.offsetWidth;
        const imageHeight = mapImage.offsetHeight;

        const leftPx = parseFloat(currentRect.style.left) || 0;
        const topPx = parseFloat(currentRect.style.top) || 0;
        const widthPx = parseFloat(currentRect.style.width) || 0;
        const heightPx = parseFloat(currentRect.style.height) || 0;

        const x = (leftPx / imageWidth) * 100;
        const y = (topPx / imageHeight) * 100;
        const width = (widthPx / imageWidth) * 100;
        const height = (heightPx / imageHeight) * 100;
        
        currentRect.remove();
        currentRect = null;
        
        if (width > 0.05 && height > 0.05) { // Adjusted threshold for zoom
          pendingRect = { x, y, width, height };
          showAssignModal();
        }
      }
    });

    function addRectangle(x, y, width, height, lotData) {
      const rect = document.createElement('div');
      const statusClass = (lotData.actual_status || lotData.status || 'vacant').toLowerCase();
      const isVertical = parseFloat(height) > parseFloat(width);
      rect.className = 'lot-rectangle ' + statusClass + (isVertical ? ' vertical' : '');
      rect.setAttribute('data-lot-id', lotData.id);
      rect.style.left = x + '%';
      rect.style.top = y + '%';
      rect.style.width = width + '%';
      rect.style.height = height + '%';
      
      const label = document.createElement('div');
      label.className = 'lot-label';
      label.innerHTML = `<span>${lotData.lot_number}</span><span class="section-tag">${lotData.section_name || lotData.section || ''}</span>`;
      rect.appendChild(label);

      if (isVertical) {
        const sectionTag = document.createElement('div');
        sectionTag.className = 'section-tag';
        sectionTag.textContent = lotData.section_name || lotData.section || '';
        rect.appendChild(sectionTag);
      }

      // Add layer indicator if multiple layers exist
      const totalLayers = parseInt(lotData.total_layers) || 1;
      const occupiedLayers = parseInt(lotData.occupied_layers) || 0;
      if (totalLayers > 1) {
        const layerIndicator = document.createElement('div');
        layerIndicator.className = 'lot-layer-indicator';
        layerIndicator.textContent = `${occupiedLayers}/${totalLayers}`;
        layerIndicator.title = `${occupiedLayers}/${totalLayers} layers occupied`;
        rect.appendChild(layerIndicator);
      }

      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'lot-remove-btn';
      removeBtn.textContent = '×';
      removeBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        removeMarkForLot(lotData.id);
      });
      rect.appendChild(removeBtn);
      
      rect.onclick = (e) => {
        if (e && e.shiftKey) {
          removeMarkForLot(lotData.id);
          return;
        }
        showLotDetails(lotData);
      };
      rect.addEventListener('contextmenu', (e) => {
        e.preventDefault();
        removeMarkForLot(lotData.id);
      });
      
      rectanglesContainer.appendChild(rect);
      rectangles.push({ rect, lotData, x, y, width, height });
    }

    function toggleAssignMode() {
      const mode = document.querySelector('input[name="assignMode"]:checked').value;
      const existingSection = document.getElementById('existingLotSection');
      const newSection = document.getElementById('newLotSection');
      
      if (mode === 'existing') {
        existingSection.style.display = 'block';
        newSection.style.display = 'none';
      } else {
        existingSection.style.display = 'none';
        newSection.style.display = 'block';
      }
    }

    function showAssignModal() {
      const modal = document.getElementById('assignModal');
      modal.classList.add('show');
      // Reset to existing lot mode by default
      document.querySelector('input[name="assignMode"][value="existing"]').checked = true;
      toggleAssignMode();
      
      // Add event listeners for closing modal
      modal.addEventListener('click', handleModalClick);
      document.addEventListener('keydown', handleModalKeydown);
    }

    function closeAssignModal() {
      const modal = document.getElementById('assignModal');
      modal.classList.remove('show');
      pendingRect = null;
      
      // Clear new lot form
      const lotNoEl = document.getElementById('newLotNumber');
      const sectionEl = document.getElementById('newSectionId');
      const positionEl = document.getElementById('newPosition');
      const statusEl = document.getElementById('newStatus');
      const hintEl = document.getElementById('newLotLatestHint');

      if (lotNoEl) lotNoEl.value = '';
      if (sectionEl) sectionEl.value = '';
      if (positionEl) positionEl.value = '';
      if (statusEl) statusEl.value = 'Vacant';
      if (hintEl) hintEl.textContent = '';

      
      // Remove event listeners
      modal.removeEventListener('click', handleModalClick);
      document.removeEventListener('keydown', handleModalKeydown);
    }
    
    function handleModalClick(e) {
      if (e.target === e.currentTarget) {
        closeAssignModal();
      }
    }
    
    function handleModalKeydown(e) {
      if (e.key === 'Escape') {
        closeAssignModal();
      }
    }

    async function createNewLot(lotData) {
      try {
        const response = await fetch('../api/cemetery_lots.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(lotData)
        });
        
        const result = await response.json();
        return result;
      } catch (error) {
        console.error('Error creating lot:', error);
        return { success: false, message: error.message };
      }
    }

    function suggestNextLotNumber(latestLotNumber) {
      if (!latestLotNumber || typeof latestLotNumber !== 'string') return '';
      const match = latestLotNumber.match(/(\d+)(?!.*\d)/);
      if (!match) return '';
      const digits = match[1];
      const num = parseInt(digits, 10);
      if (Number.isNaN(num)) return '';
      const nextDigits = String(num + 1).padStart(digits.length, '0');
      const idx = match.index ?? latestLotNumber.lastIndexOf(digits);
      return latestLotNumber.slice(0, idx) + nextDigits + latestLotNumber.slice(idx + digits.length);
    }

    async function refreshNewLotLatestHint() {
      const sectionEl = document.getElementById('newSectionId');
      const hintEl = document.getElementById('newLotLatestHint');
      const lotNoEl = document.getElementById('newLotNumber');
      if (!sectionEl || !hintEl || !lotNoEl) return;

      const sectionId = sectionEl.value;
      if (!sectionId) {
        hintEl.textContent = '';
        return;
      }

      hintEl.textContent = 'Loading latest lot number...';
      try {
        const response = await fetch(`../api/cemetery_lots.php?latest_lot=1&section_id=${encodeURIComponent(sectionId)}`);
        const result = await response.json();
        if (!result.success) {
          hintEl.textContent = '';
          return;
        }

        const latestLotNumber = result.data && result.data.lot_number ? result.data.lot_number : null;
        if (!latestLotNumber) {
          hintEl.textContent = 'No existing lots in this section yet';
          return;
        }

        const suggested = result.data && result.data.suggested_next
          ? result.data.suggested_next
          : suggestNextLotNumber(latestLotNumber);
        hintEl.textContent = suggested
          ? `Latest: ${latestLotNumber} • Suggested next: ${suggested}`
          : `Latest: ${latestLotNumber}`;

        if (lotNoEl.value.trim() === '' && suggested) {
          lotNoEl.value = suggested;
        }
      } catch (e) {
        hintEl.textContent = '';
      }
    }

    // Notification System
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

    async function updateLotCoordinates(lotId, coordinates) {
      try {
        const response = await fetch('../api/save_map_coordinates.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            lots: [{
              id: lotId,
              map_x: coordinates.x,
              map_y: coordinates.y,
              map_width: coordinates.width,
              map_height: coordinates.height
            }]
          })
        });
        
        const result = await response.json();
        return result;
      } catch (error) {
        console.error('Error updating coordinates:', error);
        return { success: false, message: error.message };
      }
    }

    async function assignLot() {
      if (!pendingRect) {
        showNotification('No rectangle to assign', 'warning');
        return;
      }

      const mode = document.querySelector('input[name="assignMode"]:checked').value;
      let lotId = null;
      let lotData = null;

      if (mode === 'existing') {
        const select = document.getElementById('lotSelect');
        lotId = select.value;
        
        if (!lotId) {
          showNotification('Please select a lot', 'warning');
          return;
        }
        
        const option = select.querySelector(`option[value="${lotId}"]`);
        lotData = JSON.parse(option.getAttribute('data-lot'));
      } else {
        // Create new lot
        const lotNumber = document.getElementById('newLotNumber').value.trim();
        const sectionId = document.getElementById('newSectionId').value;
        const position = document.getElementById('newPosition').value.trim();
        const status = document.getElementById('newStatus').value;

        if (!lotNumber || !sectionId) {
          showNotification('Please fill in required fields (Lot Number and Section)', 'warning');
          return;
        }

        const sectionSelect = document.getElementById('newSectionId');
        const sectionName = sectionSelect.options[sectionSelect.selectedIndex].getAttribute('data-section-name');

        const newLotData = {
          lot_number: lotNumber,
          section_id: sectionId,
          position: position || null,
          status: status
        };

        const createResult = await createNewLot(newLotData);
        if (!createResult.success) {
          if (createResult.message.includes('already exists')) {
            showConfirmModal({
              title: 'Lot Already Exists',
              message: createResult.message + "\n\nWould you like to search for this existing lot and assign it to the map instead?",
              confirmText: 'Search Existing',
              onConfirm: () => {
                // Switch to existing mode and try to select it
                document.querySelector('input[name="assignMode"][value="existing"]').checked = true;
                toggleAssignMode();
                
                // We need to refresh the page or dynamically find the ID if it's not in the dropdown
                // For now, let's just alert them to select it from the list
                showNotification("Please select '" + lotNumber + "' from the dropdown.", 'info');
              }
            });
          } else {
            showNotification('Failed to create lot: ' + createResult.message, 'error');
          }
          return;
        }

        lotId = createResult.id;
        lotData = {
          id: lotId,
          lot_number: lotNumber,
          section_id: sectionId,
          section_name: sectionName,
          position: position,
          status: status
        };
      }

      // Update coordinates
      const updateResult = await updateLotCoordinates(lotId, pendingRect);
      if (!updateResult.success) {
        showNotification('Failed to save coordinates: ' + updateResult.message, 'error');
        return;
      }

      // Add rectangle to map
      addRectangle(pendingRect.x, pendingRect.y, pendingRect.width, pendingRect.height, lotData);
      
      // Remove assigned lot from dropdown selection
      if (mode === 'existing') {
        const select = document.getElementById('lotSelect');
        const option = select.querySelector(`option[value="${lotId}"]`);
        if (option) option.remove();
      }

      // Add to lots data
      lotsData.push(lotData);

      closeAssignModal();
      showNotification(mode === 'existing' ? 'Lot assigned successfully!' : 'New lot created and assigned successfully!', 'success');
      // window.location.reload(); // Refresh to update selection dropdown
    }

    const newSectionIdEl = document.getElementById('newSectionId');
    if (newSectionIdEl) {
      newSectionIdEl.addEventListener('change', refreshNewLotLatestHint);
    }

    async function saveAllLots(silent = false) {
      const updates = rectangles.map(r => ({
        id: r.lotData.id,
        map_x: r.x,
        map_y: r.y,
        map_width: r.width,
        map_height: r.height
      }));
      
      try {
        const response = await fetch('../api/save_map_coordinates.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ lots: updates })
        });
        
        const result = await response.json();
        if (result.success) {
          if (!silent) showNotification('All lot positions saved successfully!', 'success');
        } else {
          showNotification('Error saving: ' + result.message, 'error');
        }
      } catch (error) {
        showNotification('Error: ' + error.message, 'error');
      }
    }

    async function removeMarkForLot(lotId) {
      const targetIndex = rectangles.findIndex(r => String(r.lotData.id) === String(lotId));
      if (targetIndex === -1) return;

      const target = rectangles[targetIndex];
      showConfirmModal({
        title: 'Remove Map Mark',
        message: `Are you sure you want to remove the mark for Lot ${target.lotData.lot_number}?`,
        type: 'danger',
        confirmText: 'Remove Mark',
        onConfirm: async () => {
          target.rect.remove();
          rectangles.splice(targetIndex, 1);

          try {
            const response = await fetch('../api/save_map_coordinates.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                lots: [{ id: target.lotData.id, map_x: null, map_y: null, map_width: null, map_height: null }]
              })
            });

            const result = await response.json();
            if (result.success) {
              // Restore to dropdown
              const select = document.getElementById('lotSelect');
              const option = document.createElement('option');
              option.value = target.lotData.id;
              option.textContent = `${target.lotData.lot_number} - ${target.lotData.section_name || target.lotData.section || ''} (${target.lotData.status})`;
              
              const resetLotData = { ...target.lotData, map_x: null, map_y: null, map_width: null, map_height: null };
              option.setAttribute('data-lot', JSON.stringify(resetLotData));
              
              select.appendChild(option);
              
              // Sort options
              const options = Array.from(select.options);
              options.sort((a, b) => {
                  if (a.value === "") return -1;
                  if (b.value === "") return 1;
                  return a.text.localeCompare(b.text, undefined, { numeric: true, sensitivity: 'base' });
              });
              select.innerHTML = '';
              options.forEach(opt => select.appendChild(opt));
              
              showNotification('Mark removed successfully', 'success');
            } else {
              showNotification('Error removing mark: ' + result.message, 'error');
            }
          } catch (error) {
            showNotification('Error removing mark: ' + error.message, 'error');
          }
        }
      });
    }
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
