<?php
require_once __DIR__ . '/../config/auth.php';
requireLogin();

$user = getUserInfo();
$userInitials = getInitials($user['full_name']);

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

$sections = [];
$all_blocks = [];
$stats = [
    'total' => 0,
    'with_lots' => 0,
    'empty' => 0
];

if ($db) {
    try {
        // Fetch all blocks for the dropdown
        $blockStmt = $db->query("SELECT id, name FROM blocks ORDER BY name ASC");
        $all_blocks = $blockStmt->fetchAll();

        // Fetch stats
        $stats['total'] = $db->query("SELECT COUNT(*) FROM sections")->fetchColumn();
        $stats['with_lots'] = $db->query("SELECT COUNT(DISTINCT section_id) FROM cemetery_lots WHERE section_id IS NOT NULL")->fetchColumn();
        $stats['empty'] = max(0, $stats['total'] - $stats['with_lots']);

        $stmt = $db->query("
            SELECT s.*, b.name as block_name,
                   (SELECT COUNT(*) FROM cemetery_lots WHERE section_id = s.id) as lot_count
            FROM sections s 
            LEFT JOIN blocks b ON s.block_id = b.id
            ORDER BY s.name ASC
        ");
        $sections = $stmt->fetchAll();

        // Fetch all sections with defined areas for the map picker
        $sectionsWithAreas = array_filter($sections, function($s) {
            return !empty($s['map_x']) && !empty($s['map_y']);
        });
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
  <title>PeacePlot Admin - Section Management</title>
  <link rel="stylesheet" href="../assets/css/styles.css" />
  <!-- Flatpickr for better date selection -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <style>
    /* Specific styles for the modern sections UI */
    .dashboard-header {
      background: #fff;
      padding: 24px 32px;
      border-radius: 16px;
      margin-bottom: 24px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.03);
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: relative;
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
    
    .btn-blue {
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
    .btn-blue:hover { background: #2563eb; transform: translateY(-1px); }

    /* Filter Controls */
    .filter-controls { display: flex; gap: 12px; align-items: center; }
    
    .search-wrapper { position: relative; }
    .search-wrapper svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
    .search-wrapper input {
      padding: 10px 16px 10px 40px;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      font-size: 14px;
      width: 280px;
      outline: none;
      transition: all 0.2s;
    }
    .search-wrapper input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }

    .btn-filter {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 10px 16px;
      background: #3b82f6;
      color: #fff;
      border: none;
      border-radius: 10px;
      font-weight: 600;
      font-size: 14px;
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
      transition: all 0.2s;
      position: relative;
    }
    .btn-filter:hover { background: #2563eb; transform: translateY(-1px); }
    .filter-badge {
      background: #fff;
      color: #3b82f6;
      width: 20px;
      height: 20px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 11px;
      font-weight: 700;
    }
    .filter-popover {
      position: absolute;
      top: calc(100% + 12px);
      right: 0;
      width: 640px;
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.15);
      border: 1px solid #e2e8f0;
      z-index: 1000;
      display: none;
      overflow: hidden;
      color: #1e293b;
      text-align: left;
    }
    .filter-popover.active { display: block; }
    .popover-header {
      padding: 16px 20px;
      border-bottom: 1px solid #f1f5f9;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .popover-header h3 { font-size: 15px; font-weight: 700; margin: 0; }
    .popover-body { 
      display: flex;
      max-height: 480px; 
      overflow-y: auto; 
    }
    .popover-column {
      flex: 1;
      border-right: 1px solid #f1f5f9;
    }
    .popover-column:last-child {
      border-right: none;
    }
    
    .filter-category { border-bottom: 1px solid #f8fafc; }
    .filter-category:last-child { border-bottom: none; }
    .category-toggle {
      width: 100%;
      padding: 12px 20px;
      display: flex;
      align-items: center;
      gap: 10px;
      background: none;
      border: none;
      cursor: pointer;
      font-size: 14px;
      font-weight: 600;
      color: #1e293b;
      transition: background 0.2s;
    }
    .category-toggle:hover { background: #f8fafc; }
    .category-toggle svg { width: 16px; height: 16px; color: #94a3b8; transition: transform 0.2s; }
    .filter-category.active .category-toggle svg { transform: rotate(90deg); }
    .category-content { display: none; padding: 0 20px 12px 46px; }
    .filter-category.active .category-content { display: block; }
    
    .filter-option {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 6px 0;
      cursor: pointer;
      font-size: 13.5px;
      color: #475569;
    }
    .filter-option input[type="checkbox"] { width: 16px; height: 16px; border-radius: 4px; border: 2px solid #cbd5e1; cursor: pointer; }
    
    .active-filters-row {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin: 16px 32px 0 32px;
    }
    .filter-chip {
      display: flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      background: #eff6ff;
      color: #3b82f6;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
    }
    .filter-chip .remove { cursor: pointer; display: flex; align-items: center; justify-content: center; opacity: 0.7; }
    .filter-chip .remove:hover { opacity: 1; }

    /* Date Range Wrapper */
    .date-range-wrapper {
      display: flex;
      align-items: center;
      gap: 8px;
      background: #fff;
      padding: 8px 12px;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
    }
    .date-range-wrapper label { font-size: 12px; font-weight: 600; color: #64748b; }
    .date-range-wrapper input { border: none; outline: none; font-size: 13px; color: #1e293b; background: transparent; }

    /* Range Inputs */
    .range-inputs {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-top: 8px;
    }
    .range-input-group {
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .range-input-group label {
      font-size: 11px;
      font-weight: 600;
      color: #94a3b8;
      text-transform: uppercase;
    }
    .range-input-group input {
      width: 100%;
      padding: 6px 10px;
      border: 1px solid #e2e8f0;
      border-radius: 6px;
      font-size: 13px;
      color: #1e293b;
      outline: none;
    }
    .range-input-group input:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
    }
    .range-separator {
      margin-top: 18px;
      color: #cbd5e1;
      font-weight: 600;
    }

    /* Sorting UI Styles */
    .table th[data-sort] {
      cursor: pointer;
      user-select: none;
      transition: all 0.2s;
    }
    .table th[data-sort]:hover {
      background: #f1f5f9 !important;
      color: #3b82f6 !important;
    }
    .th-content {
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .sort-icon {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 14px;
      height: 14px;
    }
    .active-sort {
      color: #3b82f6 !important;
      background: #eff6ff !important;
    }
    
    .sort-group {
      display: flex;
      flex-direction: column;
      gap: 8px;
      padding: 4px 0;
    }
    .sort-select {
      width: 100%;
      padding: 8px 12px;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      font-size: 13px;
      color: #1e293b;
      outline: none;
      background: #fff;
    }
    .sort-select:focus {
      border-color: #3b82f6;
    }

    .dashboard-stats {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 24px;
      margin-bottom: 32px;
    }
    .dash-stat-card {
      background: #fff;
      padding: 24px;
      border-radius: 16px;
      border-left: 5px solid #0e1f35;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    }
    .dash-stat-info .label {
      font-size: 13px;
      font-weight: 600;
      color: #94a3b8;
      margin-bottom: 4px;
    }
    .dash-stat-info .value {
      font-size: 28px;
      font-weight: 700;
      color: #1e293b;
      line-height: 1;
    }
    .dash-stat-icon {
      width: 48px;
      height: 48px;
      background: #eff6ff;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #3b82f6;
    }

    .content-section {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.03);
      overflow: visible; /* Changed from hidden to allow popover */
    }
    .content-header {
      padding: 24px 32px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid #f1f5f9;
    }
    .content-title-wrap .title { font-size: 18px; font-weight: 700; color: #1e293b; margin: 0 0 4px 0; }
    .content-title-wrap .subtitle { font-size: 13px; color: #94a3b8; margin: 0; }

    .table thead th {
      background: #f8fafc;
      color: #94a3b8;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      padding: 16px 32px;
    }
    .table tbody td { padding: 16px 32px; font-size: 14px; color: #475569; vertical-align: middle; }
    
    .section-name-cell { display: flex; align-items: center; gap: 16px; }
    .section-icon {
      width: 36px;
      height: 36px;
      background: #3b82f6;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
    }
    .section-info .name { font-weight: 600; color: #1e293b; display: block; }
    .section-info .sub { font-size: 12px; color: #94a3b8; }

    /* Modal Overrides */
    .modal { display: none; position: fixed; z-index: 5000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(4px); align-items: center; justify-content: center; }
    .modal-content { background-color: #fff; border-radius: 16px; width: 500px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); overflow: hidden; border: none; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 20px 24px; border-bottom: 1px solid #e2e8f0; }
    .modal-title { font-size: 1.25rem; font-weight: 700; color: #1e293b; margin: 0; }
    .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b; }
    .modal-body { padding: 24px; }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #475569; font-size: 14px; }
    .form-group input, .form-group textarea { width: 100%; padding: 12px 16px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 15px; outline: none; transition: all 0.2s; }
    .form-group input:focus, .form-group textarea:focus { border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); }
    .modal-footer { padding: 16px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 12px; }

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
      background: #fee2e2;
      color: #ef4444;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 20px;
      font-size: 32px;
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

    .btn-confirm-delete {
      background: #ef4444;
      color: white;
      border: none;
      padding: 12px 24px;
      border-radius: 10px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
    }

    .btn-confirm-delete:hover {
      background: #dc2626;
      transform: translateY(-1px);
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
        <a href="dashboard.php">
          <span class="icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 13h8V3H3v10z" />
              <path d="M13 21h8V11h-8v10z" />
              <path d="M13 3h8v6h-8V3z" />
              <path d="M3 21h8v-6H3v6z" />
            </svg>
          </span>
          <span>Dashboard</span>
        </a>

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
            <a href="sections.php" class="active"><span>Manage Sections</span></a>
            <a href="lot-availability.php"><span>Lots</span></a>
            <a href="map-editor.php"><span>Map Editor</span></a>
          </div>
        </div>

        <a href="cemetery-map.php">
          <span class="icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2V6z" />
              <path d="M9 4v14" />
              <path d="M15 6v14" />
            </svg>
          </span>
          <span>Cemetery Map</span>
        </a>

        <a href="burial-records.php">
          <span class="icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" />
              <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" />
              <path d="M8 6h8" />
              <path d="M8 10h8" />
            </svg>
          </span>
          <span>Burial Records</span>
        </a>

        <a href="reports.php">
          <span class="icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 3v18h18" />
              <path d="M7 14v4" />
              <path d="M11 10v8" />
              <path d="M15 6v12" />
              <path d="M19 12v6" />
            </svg>
          </span>
          <span>Reports</span>
        </a>

        <a href="history.php">
          <span class="icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          </span>
          <span>History</span>
        </a>
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
            <span class="current">Manage Sections</span>
          </div>
          <h1 class="title">Section Management</h1>
          <p class="subtitle">Manage and categorize cemetery lots by sections</p>
        </div>

        <div class="header-search">
          <div class="universal-search-wrapper">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input type="text" class="universal-search-input" id="universalSearch" placeholder="Global Search lots, deceased names...">
          </div>
          <div class="search-results-dropdown" id="searchResults">
            <!-- Results will be injected here -->
          </div>
        </div>
        
        <div class="header-actions">
          <button id="addSectionBtn" class="btn-blue">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Add New Section
          </button>
        </div>
      </header>

      <div class="dashboard-stats">
        <div class="dash-stat-card">
          <div class="dash-stat-info">
            <div class="label">Total Sections</div>
            <div class="value"><?php echo $stats['total']; ?></div>
          </div>
          <div class="dash-stat-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16" /><path d="M4 12h16" /><path d="M4 17h16" /></svg>
          </div>
        </div>
        <div class="dash-stat-card">
          <div class="dash-stat-info">
            <div class="label">With Assigned Lots</div>
            <div class="value"><?php echo $stats['with_lots']; ?></div>
          </div>
          <div class="dash-stat-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
          </div>
        </div>
        <div class="dash-stat-card">
          <div class="dash-stat-info">
            <div class="label">Unused Sections</div>
            <div class="value"><?php echo $stats['empty']; ?></div>
          </div>
          <div class="dash-stat-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          </div>
        </div>
      </div>

      <section class="content-section">
        <div class="content-header">
          <div class="content-title-wrap">
            <h2 class="title">Section List</h2>
            <p class="subtitle">All cemetery sections and their assigned lots</p>
          </div>
          <div class="filter-controls">
            <div class="search-wrapper" style="position: relative;">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
              <input id="sectionSearch" type="text" placeholder="Search sections..." style="padding: 10px 16px 10px 40px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; width: 280px; outline: none; transition: all 0.2s;">
            </div>
            
            <div style="position: relative;">
              <button id="filterBtn" class="btn-filter">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                Filters
                <span id="filterBadge" class="filter-badge" style="display: none;">0</span>
              </button>

              <div id="filterPopover" class="filter-popover">
                <div class="popover-header">
                  <h3>Advanced Filters</h3>
                  <button class="btn-text" id="clearAllFilters" style="color: #ef4444; font-size: 12px; font-weight: 600; background: none; border: none; cursor: pointer;">Clear All</button>
                </div>
                <div class="popover-body">
                  <!-- Left Column: Categories -->
                  <div class="popover-column" style="flex: 0 0 200px; background: #f8fafc;">
                    <div class="filter-category active" data-category="blocks">
                      <button class="category-toggle">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        Blocks
                      </button>
                    </div>
                    <div class="filter-category" data-category="lots">
                      <button class="category-toggle">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        Lot Count
                      </button>
                    </div>
                    <div class="filter-category" data-category="date">
                      <button class="category-toggle">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        Created Date
                      </button>
                    </div>
                    <div class="filter-category" data-category="sort">
                      <button class="category-toggle">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        Sorting
                      </button>
                    </div>
                  </div>

                  <!-- Right Column: Options -->
                  <div class="popover-column">
                    <!-- Blocks Content -->
                    <div class="category-content" id="cat-blocks" style="display: block; padding: 20px;">
                      <div style="max-height: 300px; overflow-y: auto;">
                        <?php foreach ($all_blocks as $block): ?>
                          <label class="filter-option">
                            <input type="checkbox" name="block_filter" value="<?php echo $block['id']; ?>" data-name="<?php echo htmlspecialchars($block['name']); ?>">
                            <span><?php echo htmlspecialchars($block['name']); ?></span>
                          </label>
                        <?php endforeach; ?>
                      </div>
                    </div>

                    <!-- Lot Count Content -->
                    <div class="category-content" id="cat-lots" style="display: none; padding: 20px;">
                      <p style="font-size: 13px; color: #64748b; margin-bottom: 16px;">Filter sections by the number of lots they contain.</p>
                      <div class="range-inputs">
                        <div class="range-input-group">
                          <label>Min Lots</label>
                          <input type="number" id="lotMin" placeholder="0" min="0">
                        </div>
                        <div class="range-separator">-</div>
                        <div class="range-input-group">
                          <label>Max Lots</label>
                          <input type="number" id="lotMax" placeholder="Any" min="0">
                        </div>
                      </div>
                    </div>

                    <!-- Date Content -->
                    <div class="category-content" id="cat-date" style="display: none; padding: 20px;">
                      <p style="font-size: 13px; color: #64748b; margin-bottom: 16px;">Filter by the date the section was created.</p>
                      <div class="date-range-wrapper">
                        <label>Range:</label>
                        <input type="text" id="dateRange" placeholder="Select date range...">
                      </div>
                    </div>

                    <!-- Sorting Content -->
                    <div class="category-content" id="cat-sort" style="display: none; padding: 20px;">
                      <div class="sort-group">
                        <label style="font-size: 12px; font-weight: 600; color: #64748b;">Sort By</label>
                        <select id="sortBy" class="sort-select">
                          <option value="name">Section Name</option>
                          <option value="block_name">Block Name</option>
                          <option value="lot_count">Lot Count</option>
                          <option value="created_at">Date Created</option>
                        </select>
                        
                        <label style="font-size: 12px; font-weight: 600; color: #64748b; margin-top: 12px;">Order</label>
                        <div style="display: flex; gap: 12px;">
                          <label class="filter-option">
                            <input type="radio" name="sortOrder" value="ASC" checked>
                            <span>Ascending</span>
                          </label>
                          <label class="filter-option">
                            <input type="radio" name="sortOrder" value="DESC">
                            <span>Descending</span>
                          </label>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div id="activeFiltersRow" class="active-filters-row">
          <!-- Filter chips will be injected here -->
        </div>

        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th align="left">Section Details</th>
                <th align="left">Block</th>
                <th align="left">Description</th>
                <th align="center">Lot Count</th>
                <th align="left">Created At</th>
                <th align="right">Actions</th>
              </tr>
            </thead>
            <tbody id="sectionsTableBody">
              <?php foreach ($sections as $section): ?>
                <tr>
                  <td>
                    <div class="section-name-cell">
                      <div class="section-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16" /><path d="M4 12h16" /><path d="M4 17h16" /></svg>
                      </div>
                      <div class="section-info">
                        <span class="name"><?php echo htmlspecialchars($section['name'] ?? ''); ?></span>
                        <span class="sub">ID: #<?php echo $section['id']; ?></span>
                      </div>
                    </div>
                  </td>
                  <td>
                    <?php if (!empty($section['block_name'])): ?>
                      <span style="color: #1e293b; font-weight: 500;"><?php echo htmlspecialchars($section['block_name']); ?></span>
                    <?php else: ?>
                      <span style="color: #94a3b8; font-style: italic;">No Block</span>
                    <?php endif; ?>
                  </td>
                  <td><?php echo htmlspecialchars($section['description'] ?? 'No description provided'); ?></td>
                  <td align="center">
                    <span style="background: #eff6ff; color: #3b82f6; padding: 4px 12px; border-radius: 20px; font-weight: 600; font-size: 12px;">
                      <?php echo $section['lot_count']; ?> Lots
                    </span>
                  </td>
                  <td><?php echo date('M d, Y', strtotime($section['created_at'])); ?></td>
                  <td align="right">
                    <div style="display: flex; justify-content: flex-end; gap: 8px;">
                      <?php if (!empty($section['map_x']) && !empty($section['map_y'])): ?>
                        <a href="cemetery-map.php?highlight_section=<?php echo $section['id']; ?>" class="btn-action btn-map" title="View on Map" style="background: #eff6ff; color: #3b82f6; display: flex; align-items: center; justify-content: center; text-decoration: none;">
                          <span class="icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                              <circle cx="12" cy="10" r="3" />
                            </svg>
                          </span>
                        </a>
                      <?php endif; ?>
                      <button class="btn-action btn-edit" onclick='openEditModal(<?php echo htmlspecialchars(json_encode($section), ENT_QUOTES, "UTF-8"); ?>)' title="Edit Section">
                        <span class="icon">
                          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 20h9" />
                            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" />
                          </svg>
                        </span>
                      </button>
                      <button class="btn-action btn-delete" onclick='deleteSection(<?php echo htmlspecialchars(json_encode($section), ENT_QUOTES, "UTF-8"); ?>)' title="Delete Section" style="display: none;">
                        <span class="icon">
                          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 6h18" />
                            <path d="M8 6V4h8v2" />
                            <path d="M19 6l-1 14H6L5 6" />
                            <path d="M10 11v6" />
                            <path d="M14 11v6" />
                          </svg>
                        </span>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($sections)): ?>
                <tr>
                  <td colspan="6" style="text-align: center; padding: 60px; color: #94a3b8;">
                    <div style="margin-bottom: 12px; opacity: 0.5;">
                      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/></svg>
                    </div>
                    No sections found. Add your first section to get started!
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>

  <!-- Add/Edit Modal -->
  <div id="sectionModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 id="modalTitle" class="modal-title">Add New Section</h2>
        <button class="modal-close" onclick="closeModal()">&times;</button>
      </div>
      <form id="sectionForm">
        <div class="modal-body">
          <input type="hidden" id="sectionId" name="id">
          <input type="hidden" id="map_x" name="map_x">
          <input type="hidden" id="map_y" name="map_y">
          <input type="hidden" id="map_width" name="map_width">
          <input type="hidden" id="map_height" name="map_height">
          <div class="form-group">
            <label for="name">Section Name</label>
            <input type="text" id="name" name="name" required placeholder="e.g. Garden of Peace">
          </div>
          <div class="form-group">
            <label for="block_id">Block</label>
            <select id="block_id" name="block_id" required style="width: 100%; padding: 12px 16px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 15px; outline: none; transition: all 0.2s; background: white;">
              <option value="">-- Select Block --</option>
              <?php foreach ($all_blocks as $block): ?>
                <option value="<?php echo $block['id']; ?>"><?php echo htmlspecialchars($block['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4" placeholder="Brief description of the section..."></textarea>
          </div>
          <div class="form-group">
            <label>Section Area on Map</label>
            <div id="sectionMapPreview" style="width: 100%; height: 120px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #64748b; font-size: 13px; cursor: pointer; transition: all 0.2s; overflow: hidden; position: relative;" onclick="openMapPicker()">
              <div id="previewContent" style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2V6z"/><path d="M9 4v14"/><path d="M15 6v14"/></svg>
                <span>Draw Section Area on Map</span>
              </div>
              <div id="coordinatesInfo" style="display: none; position: absolute; bottom: 8px; right: 8px; background: rgba(255,255,255,0.9); padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: 600; color: #3b82f6; border: 1px solid #dbeafe;">
                Area Defined
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
          <button type="submit" class="btn-blue">Save Section</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Confirmation Modal -->
  <div id="confirmModal" class="confirm-modal">
    <div class="confirm-modal-content">
      <div class="confirm-icon">⚠</div>
      <h3 class="confirm-title">Delete Section?</h3>
      <p id="confirmMessage" class="confirm-message">Are you sure you want to delete this section? This action cannot be undone.</p>
      <div class="confirm-actions">
        <button class="btn-confirm-cancel" onclick="closeConfirmModal()">Cancel</button>
        <button id="confirmDeleteBtn" class="btn-confirm-delete">Delete</button>
      </div>
    </div>
  </div>

  <!-- Map Area Picker Overlay -->
  <div id="mapPickerOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.95); z-index: 10000; flex-direction: column;">
    <div style="padding: 20px 32px; background: white; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
      <div>
        <h2 style="font-size: 18px; font-weight: 700; color: #1e293b; margin: 0;">Define Section Area</h2>
        <p style="font-size: 13px; color: #64748b; margin: 4px 0 0 0;">Click and drag on the map to draw a rectangle for the section area.</p>
      </div>
      <div style="display: flex; gap: 12px;">
        <button type="button" class="btn-secondary" onclick="closeMapPicker()" style="padding: 8px 16px;">Cancel</button>
        <button type="button" class="btn-blue" onclick="saveMapArea()" style="padding: 8px 24px;">Confirm Area</button>
      </div>
    </div>
    <div id="mapPickerWrapper" style="flex: 1; overflow: hidden; position: relative; background: #f8fafc;">
      <div id="mapPickerCanvas" style="position: absolute; transform-origin: 0 0; transition: transform 0.1s ease-out;">
        <img src="../assets/images/cemetery.jpg" id="mapPickerImage" style="display: block; -webkit-user-drag: none; user-select: none; pointer-events: none;">
        <div id="selectionRect" style="display: none; position: absolute; border: 2px solid #3b82f6; background: rgba(59, 130, 246, 0.1); pointer-events: none; z-index: 100;"></div>
      </div>
      
      <!-- Toolbar Overlay -->
      <div style="position: absolute; top: 20px; right: 20px; display: flex; gap: 8px; z-index: 200;">
        <button type="button" id="pickerDrawBtn" onclick="setPickerTool('draw')" style="padding: 10px 18px; border: 1px solid #e2e8f0; background: white; border-radius: 10px; cursor: pointer; font-weight: 600; color: #475569; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);" class="active">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/></svg>
          Draw Area
        </button>
        <button type="button" id="pickerPanBtn" onclick="setPickerTool('pan')" style="padding: 10px 18px; border: 1px solid #e2e8f0; background: white; border-radius: 10px; cursor: pointer; font-weight: 600; color: #475569; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 9l-3 3 3 3M9 5l3-3 3 3M15 19l-3 3-3-3M19 9l3 3-3 3M2 12h20M12 2v20"/></svg>
          Pan Map
        </button>
      </div>

      <!-- Zoom Controls -->
      <div style="position: absolute; bottom: 32px; right: 32px; display: flex; flex-direction: column; gap: 8px; z-index: 200;">
        <button onclick="zoomMap(1.25)" style="width: 44px; height: 44px; border-radius: 12px; background: white; border: 1px solid #e2e8f0; color: #1e293b; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(0,0,0,0.1);"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg></button>
        <button onclick="zoomMap(0.8)" style="width: 44px; height: 44px; border-radius: 12px; background: white; border: 1px solid #e2e8f0; color: #1e293b; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(0,0,0,0.1);"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line></svg></button>
        <button onclick="resetPickerView()" style="width: 44px; height: 44px; border-radius: 12px; background: white; border: 1px solid #e2e8f0; color: #1e293b; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(0,0,0,0.1);"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg></button>
      </div>
    </div>
    
    <style>
      #pickerDrawBtn.active, #pickerPanBtn.active {
        background: #3b82f6 !important;
        color: white !important;
        border-color: #3b82f6 !important;
      }
      #mapPickerWrapper.grabbing { cursor: grabbing; }
      #mapPickerWrapper.crosshair { cursor: crosshair; }
    </style>
  </div>

  <script>
    window.existingSectionAreas = <?php echo json_encode(array_values($sectionsWithAreas)); ?>;
  </script>
  <script src="../assets/js/sections.js"></script>
</body>
</html>
