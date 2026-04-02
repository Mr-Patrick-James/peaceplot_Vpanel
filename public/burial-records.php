<?php
require_once __DIR__ . '/../config/auth.php';
requireLogin();

$user = getUserInfo();
$userInitials = getInitials($user['full_name']);

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

require_once __DIR__ . '/includes/page_tracker.php';

$availableLots = [];
$sections = [];
$blocks = [];

if ($conn) {
    try {
        // Fetch sections with their block names for filtering
        $sectionStmt = $conn->query("
            SELECT s.id, s.name, b.name as block_name 
            FROM sections s 
            LEFT JOIN blocks b ON s.block_id = b.id 
            ORDER BY b.name, s.name
        ");
        $sections = $sectionStmt->fetchAll();

        // Fetch unique blocks for filtering
        $blockStmt = $conn->query("SELECT id, name FROM blocks ORDER BY name");
        $blocks = $blockStmt->fetchAll();

        $lotsStmt = $conn->query("
            SELECT cl.id, cl.lot_number, s.name as section, b.name as block 
            FROM cemetery_lots cl
            LEFT JOIN sections s ON cl.section_id = s.id
            LEFT JOIN blocks b ON s.block_id = b.id
            ORDER BY cl.lot_number
        ");
        $availableLots = $lotsStmt->fetchAll();
        
    } catch (PDOException $e) {
        $error = $e->getMessage();
        $availableLots = [];
        $sections = [];
    }
} else {
    $availableLots = [];
    $sections = [];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PeacePlot Admin - Burial Records</title>
  <link rel="stylesheet" href="../assets/css/styles.css" />
  <!-- Flatpickr for better date selection -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <style>
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
    .btn-primary-modern:hover { background: #2563eb; transform: translateY(-1px); }

    /* Stats Row */
    .stats-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 24px;
      margin-bottom: 32px;
    }
    .stat-box {
      background: #fff;
      padding: 24px;
      border-radius: 16px;
      border-left: 5px solid #0e1f35;
      display: flex;
      align-items: center;
      gap: 20px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    }
    .stat-icon-wrap {
      width: 48px;
      height: 48px;
      background: #eff6ff;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #2563eb;
    }
    .stat-info .stat-label { font-size: 13px; font-weight: 600; color: #94a3b8; margin-bottom: 4px; }
    .stat-info .stat-number { font-size: 28px; font-weight: 700; color: #1e293b; line-height: 1; }
    .stat-info .stat-sub { font-size: 12px; margin-top: 8px; color: #64748b; }

    /* Filter Controls */
    .content-section {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    }
    .content-header {
      padding: 24px 32px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid #f1f5f9;
      position: relative;
      z-index: 1001;
    }
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

    /* Age Range Styles */
    .age-range-inputs {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-top: 8px;
    }
    .age-input-group {
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .age-input-group label {
      font-size: 11px;
      font-weight: 600;
      color: #94a3b8;
      text-transform: uppercase;
    }
    .age-input-group input {
      width: 100%;
      padding: 6px 10px;
      border: 1px solid #e2e8f0;
      border-radius: 6px;
      font-size: 13px;
      color: #1e293b;
      outline: none;
    }
    .age-input-group input:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
    }
    .age-range-separator {
      margin-top: 18px;
      color: #cbd5e1;
      font-weight: 600;
    }

    /* Table & Pagination */
    .table-wrap { width: 100%; overflow-x: auto; }
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
    
    .pagination-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 32px;
        background: #fff;
        border-top: 1px solid #f1f5f9;
    }
    .pagination-info { font-size: 13px; color: #94a3b8; }
    .pagination-controls { display: flex; gap: 8px; }
    .pagination-btn {
        min-width: 32px;
        height: 32px;
        padding: 0 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: #fff;
        color: #94a3b8;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    .pagination-btn:hover:not(:disabled) { background: #f8fafc; color: #3b82f6; border-color: #3b82f6; }
    .pagination-btn.active { background: #3b82f6; color: #fff; border-color: #3b82f6; }
    .pagination-btn:disabled { opacity: 0.5; cursor: not-allowed; }

    /* Bulk Action Bar (Control Bar) */
    .bulk-action-bar {
      position: fixed;
      bottom: 32px;
      left: 50%;
      transform: translateX(-50%) translateY(100px);
      background: #1e293b;
      color: #fff;
      padding: 12px 24px;
      border-radius: 16px;
      display: flex;
      align-items: center;
      gap: 24px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.2);
      z-index: 2000;
      transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
      opacity: 0;
      visibility: hidden;
    }
    .bulk-action-bar.active {
      transform: translateX(-50%) translateY(0);
      opacity: 1;
      visibility: visible;
    }
    .bulk-info {
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 14px;
      font-weight: 600;
      border-right: 1px solid rgba(255,255,255,0.1);
      padding-right: 24px;
    }
    .bulk-badge {
      background: #3b82f6;
      color: #fff;
      width: 24px;
      height: 24px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
    }
    .bulk-actions {
      display: flex;
      gap: 12px;
    }
    .btn-bulk {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 8px 16px;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
      border: 1px solid transparent;
    }
    .btn-bulk-remove {
      background: #ef4444;
      color: #fff;
    }
    .btn-bulk-remove:hover { background: #dc2626; }
    .btn-bulk-outline {
      background: transparent;
      border-color: rgba(255,255,255,0.2);
      color: #fff;
    }
    .btn-bulk-outline:hover { background: rgba(255,255,255,0.1); }

    /* Custom Checkbox */
    .checkbox-cell {
      width: 48px;
      padding-left: 32px !important;
      padding-right: 0 !important;
    }
    .custom-checkbox {
      width: 18px;
      height: 18px;
      border: 2px solid #e2e8f0;
      border-radius: 4px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
      background: #fff;
    }
    .custom-checkbox.active {
      background: #3b82f6;
      border-color: #3b82f6;
    }
    .custom-checkbox svg {
      width: 12px;
      height: 12px;
      color: #fff;
      display: none;
    }
    .custom-checkbox.active svg {
      display: block;
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
        <a href="cemetery-map.php"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2V6z" /><path d="M9 4v14" /><path d="M15 6v14" /></svg></span><span>Cemetery Map</span></a>
        <a href="burial-records.php" class="active"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" /><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" /><path d="M8 6h8" /><path d="M8 10h8" /></svg></span><span>Burial Records</span></a>
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
            <span class="current">Burial Records</span>
          </div>
          <h1 class="title">Deceased Records</h1>
          <p class="subtitle">Manage and organize deceased person records and burial information</p>
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
          <button id="viewArchivedBtn" class="btn-outline" style="display: none;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8v13H3V8M1 3h22v5H1zM10 12h4"/></svg>
            <span id="viewArchivedText">View Archived</span>
          </button>
          <button class="btn-primary-modern" data-action="add">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Add Record
          </button>
        </div>
      </header>

      <?php
      // Quick stats for the top row
      $totalRecords = 0;
      $thisMonth = 0;
      if ($conn) {
          $totalRecords = $conn->query("SELECT COUNT(*) FROM deceased_records WHERE is_archived = 0")->fetchColumn();
          $thisMonth = $conn->query("SELECT COUNT(*) FROM deceased_records WHERE is_archived = 0 AND strftime('%Y-%m', created_at) = strftime('%Y-%m', 'now')")->fetchColumn();
      }
      ?>

      <div class="stats-row">
        <div class="stat-box">
          <div class="stat-icon-wrap">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" /><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" /></svg>
          </div>
          <div class="stat-info">
            <div class="stat-label">Total Records</div>
            <div class="stat-number"><?php echo $totalRecords; ?></div>
            <div class="stat-sub">+<?php echo $thisMonth; ?> this month</div>
          </div>
        </div>
        <div class="stat-box">
          <div class="stat-icon-wrap" style="background: #f0fdf4; color: #10b981;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </div>
          <div class="stat-info">
            <div class="stat-label">Active Records</div>
            <div class="stat-number"><?php echo $totalRecords; ?></div>
            <div class="stat-sub" style="display: none;">Excluding archived</div>
          </div>
        </div>
        <div class="stat-box">
          <div class="stat-icon-wrap" style="background: #fff7ed; color: #f97316;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          </div>
          <div class="stat-info">
            <div class="stat-label">Recent Burials</div>
            <div class="stat-number"><?php echo $thisMonth; ?></div>
            <div class="stat-sub">Last 30 days</div>
          </div>
        </div>
      </div>

      <section class="content-section">
        <div class="content-header">
          <div class="content-title-wrap">
            <h2 class="title">Burial Records List</h2>
            <p class="subtitle">Manage and filter deceased person records</p>
          </div>
          <div class="filter-controls">
            <div class="date-range-wrapper">
              <label>From:</label>
              <input type="text" id="startDate" placeholder="YYYY-MM-DD" class="datepicker">
              <label>To:</label>
              <input type="text" id="endDate" placeholder="YYYY-MM-DD" class="datepicker">
            </div>
            <div class="search-wrapper">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
              <input id="recordSearch" type="text" placeholder="Search records...">
            </div>
            
            <div style="position: relative;">
              <button class="btn-filter" id="filterBtn">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                Filters
                <span class="filter-badge" id="filterBadge" style="display: none;">0</span>
              </button>
              
              <div class="filter-popover" id="filterPopover">
                <div class="popover-header">
                  <h3>Filters</h3>
                  <a href="#" class="btn-save-view" onclick="clearAllFilters(); return false;">Clear all</a>
                </div>
                <div class="popover-body">
                  <div class="popover-column">
                    <!-- Blocks Category -->
                    <div class="filter-category active">
                      <button class="category-toggle" onclick="toggleCategory(this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        Blocks
                      </button>
                      <div class="category-content">
                        <?php foreach ($blocks as $block): ?>
                          <label class="filter-option">
                            <input type="checkbox" name="block" value="<?php echo htmlspecialchars($block['name']); ?>" onchange="updateFilters()">
                            <?php echo htmlspecialchars($block['name']); ?>
                          </label>
                        <?php endforeach; ?>
                      </div>
                    </div>

                    <!-- Sections Category -->
                    <div class="filter-category">
                      <button class="category-toggle" onclick="toggleCategory(this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        Sections
                      </button>
                      <div class="category-content">
                        <?php foreach ($sections as $section): ?>
                          <label class="filter-option">
                            <input type="checkbox" name="section" value="<?php echo htmlspecialchars($section['name']); ?>" onchange="updateFilters()">
                            <?php echo htmlspecialchars($section['name']); ?> (<?php echo htmlspecialchars($section['block_name'] ?: 'No Block'); ?>)
                          </label>
                        <?php endforeach; ?>
                      </div>
                    </div>
                    
                    <!-- Status Category -->
                    <div class="filter-category">
                      <button class="category-toggle" onclick="toggleCategory(this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        Lot Status
                      </button>
                      <div class="category-content">
                        <label class="filter-option">
                          <input type="checkbox" name="status" value="Vacant" onchange="updateFilters()"> Vacant
                        </label>
                        <label class="filter-option">
                          <input type="checkbox" name="status" value="Occupied" onchange="updateFilters()"> Occupied
                        </label>
                        <label class="filter-option">
                          <input type="checkbox" name="status" value="Maintenance" onchange="updateFilters()"> Maintenance
                        </label>
                      </div>
                    </div>

                    <!-- Age Range Category -->
                    <div class="filter-category">
                      <button class="category-toggle" onclick="toggleCategory(this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        Age Range
                      </button>
                      <div class="category-content">
                        <div class="age-range-inputs">
                          <div class="age-input-group">
                            <label>Min</label>
                            <input type="number" id="ageMin" placeholder="0" onchange="updateFilters()">
                          </div>
                          <div class="age-range-separator">-</div>
                          <div class="age-input-group">
                            <label>Max</label>
                            <input type="number" id="ageMax" placeholder="120" onchange="updateFilters()">
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="popover-column">
                    <!-- Assignment Category -->
                    <div class="filter-category active">
                      <button class="category-toggle" onclick="toggleCategory(this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        Burial Assignment
                      </button>
                      <div class="category-content">
                        <label class="filter-option">
                          <input type="checkbox" name="assignment" value="Assigned" onchange="updateFilters()"> Assigned
                        </label>
                        <label class="filter-option">
                          <input type="checkbox" name="assignment" value="Unassigned" onchange="updateFilters()"> Unassigned
                        </label>
                      </div>
                    </div>

                    <!-- Sorting Category -->
                    <div class="filter-category">
                      <button class="category-toggle" onclick="toggleCategory(this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        Sorting
                      </button>
                      <div class="category-content">
                        <div class="sort-group">
                          <label style="font-size: 12px; font-weight: 600; color: #64748b;">Sort By</label>
                          <select class="sort-select" name="sort_by" onchange="updateSortFromFilter()">
                            <option value="created_at">Date Created</option>
                            <option value="full_name">Full Name</option>
                            <option value="date_of_death">Date of Death</option>
                            <option value="lot_number">Lot Number</option>
                            <option value="age">Age</option>
                          </select>
                        </div>
                        <div class="sort-group" style="margin-top: 10px;">
                          <label style="font-size: 12px; font-weight: 600; color: #64748b;">Order</label>
                          <select class="sort-select" name="sort_order" onchange="updateSortFromFilter()">
                            <option value="DESC">Descending</option>
                            <option value="ASC">Ascending</option>
                          </select>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="active-filters-row" id="activeFilters">
          <!-- Chips will be injected here -->
        </div>

        <!-- Bulk Action Bar -->
        <div class="bulk-action-bar" id="bulkActionBar">
          <div class="bulk-info">
            <span class="bulk-badge" id="selectedCount">0</span>
            <span>records selected</span>
          </div>
          <div class="bulk-actions">
            <button class="btn-bulk btn-bulk-remove" id="bulkArchiveBtn" style="display: none;">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2M10 11v6M14 11v6"/></svg>
              Archive Selected
            </button>
            <button class="btn-bulk btn-bulk-outline" id="bulkClearBtn">
              Clear Selection
            </button>
          </div>
        </div>

        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th class="checkbox-cell">
                  <div class="custom-checkbox" id="selectAll">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4"><polyline points="20 6 9 17 4 12"></polyline></svg>
                  </div>
                </th>
                <th align="left" data-sort="full_name" onclick="handleSort('full_name')">
                  <div class="th-content">
                    Full Name
                    <span class="sort-icon"></span>
                  </div>
                </th>
                <th align="left" data-sort="lot_number" onclick="handleSort('lot_number')">
                  <div class="th-content">
                    Lot Details
                    <span class="sort-icon"></span>
                  </div>
                </th>
                <th align="left">Layer</th>
                <th align="left">Position</th>
                <th align="left" data-sort="date_of_death" onclick="handleSort('date_of_death')">
                  <div class="th-content">
                    Dates
                    <span class="sort-icon"></span>
                  </div>
                </th>
                <th align="left" data-sort="age" onclick="handleSort('age')">
                  <div class="th-content">
                    Age
                    <span class="sort-icon"></span>
                  </div>
                </th>
                <th align="right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td colspan="8" style="text-align:center; padding: 40px; color:#94a3b8;">
                  <div class="loading-spinner" style="display: inline-block; width: 20px; height: 20px; border: 2px solid rgba(0,0,0,0.1); border-radius: 50%; border-top-color: #3b82f6; animation: spin 1s ease-in-out infinite;"></div>
                  <div style="margin-top: 8px; font-size: 13px;">Refreshing table...</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="pagination-container"></div>
      </section>
    </main>
  </div>

  <script>
    const availableLots = <?php echo json_encode($availableLots ?: []); ?>;
    console.log('Available lots loaded:', availableLots);
  </script>
  <script src="../assets/js/app.js"></script>
  <script src="../assets/js/api.js?v=<?php echo time(); ?>"></script>
  <script src="../assets/js/burial-records.js?v=<?php echo time(); ?>"></script>
</body>
</html>
