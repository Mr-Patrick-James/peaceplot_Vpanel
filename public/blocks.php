<?php
require_once __DIR__ . '/../config/auth.php';
requireLogin();

$user = getUserInfo();
$userInitials = getInitials($user['full_name']);

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

$blocks = [];
$stats = [
    'total' => 0,
    'with_lots' => 0,
    'empty' => 0
];

if ($db) {
    try {
        // Fetch stats
        $stats['total'] = $db->query("SELECT COUNT(*) FROM blocks")->fetchColumn();
        $stats['with_lots'] = $db->query("
            SELECT COUNT(DISTINCT b.id) 
            FROM blocks b
            JOIN sections s ON s.block_id = b.id
            JOIN cemetery_lots cl ON cl.section_id = s.id
        ")->fetchColumn();
        $stats['empty'] = max(0, $stats['total'] - $stats['with_lots']);

        $stmt = $db->query("
            SELECT b.*, 
                   (SELECT COUNT(*) 
                    FROM cemetery_lots cl
                    JOIN sections s ON cl.section_id = s.id
                    WHERE s.block_id = b.id) as lot_count
            FROM blocks b 
            ORDER BY b.name ASC
        ");
        $blocks = $stmt->fetchAll();

        // Fetch all blocks with defined areas for the map picker
        $blocksWithAreas = array_filter($blocks, function($b) {
            return !empty($b['map_x']) && !empty($b['map_y']);
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
  <title>PeacePlot Admin - Block Management</title>
  <link rel="stylesheet" href="../assets/css/styles.css" />
  <style>
    /* Specific styles for the modern blocks UI */
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
    
    /* Universal Search Styles */
    .search-container {
      position: relative;
      width: 400px;
    }
    .universal-search-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }
    .universal-search-wrapper svg {
      position: absolute;
      left: 16px;
      color: #94a3b8;
      pointer-events: none;
    }
    .universal-search-input {
      width: 100%;
      padding: 12px 16px 12px 48px;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      font-size: 14px;
      outline: none;
      transition: all 0.2s;
      background: #f8fafc;
    }
    .universal-search-input:focus {
      background: #fff;
      border-color: #3b82f6;
      box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }
    .search-results-dropdown {
      position: absolute;
      top: calc(100% + 8px);
      left: 0;
      right: 0;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
      border: 1px solid #e2e8f0;
      z-index: 1000;
      display: none;
      overflow: hidden;
    }
    .search-result-item {
      padding: 12px 16px;
      display: flex;
      align-items: center;
      gap: 12px;
      cursor: pointer;
      transition: background 0.2s;
      text-decoration: none;
      border-bottom: 1px solid #f1f5f9;
      text-align: left;
    }
    .search-result-item:last-child { border-bottom: none; }
    .search-result-item:hover { background: #f8fafc; }
    .result-icon {
      width: 32px;
      height: 32px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .icon-lot { background: #eff6ff; color: #3b82f6; }
    .icon-deceased { background: #fef2f2; color: #ef4444; }
    .result-info { flex: 1; min-width: 0; }
    .result-title {
      font-size: 14px;
      font-weight: 600;
      color: #1e293b;
      display: block;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .result-subtitle {
      font-size: 12px;
      color: #64748b;
      display: block;
    }

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
      overflow: hidden;
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

    .filter-controls {
      display: flex;
      gap: 12px;
      align-items: center;
    }

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
    
    .block-name-cell { display: flex; align-items: center; gap: 16px; }
    .block-icon {
      width: 36px;
      height: 36px;
      background: #3b82f6;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
    }
    .block-info .name { font-weight: 600; color: #1e293b; display: block; }
    .block-info .sub { font-size: 12px; color: #94a3b8; }

    /* Modal styles are already in styles.css, but we'll add some specific overrides for Block Management if needed */
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
            <a href="blocks.php" class="active"><span>Manage Blocks</span></a>
            <a href="sections.php"><span>Manage Sections</span></a>
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
              <path d="M8 6h8" /><path d="M8 10h8" />
            </svg>
          </span>
          <span>Burial Records</span>
        </a>

        <a href="reports.php">
          <span class="icon">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 3v18h18" /><path d="M7 14v4" /><path d="M11 10v8" /><path d="M15 6v12" /><path d="M19 12v6" />
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
            <span class="current">Manage Blocks</span>
          </div>
          <h1 class="title">Block Management</h1>
          <p class="subtitle">Manage and categorize cemetery lots by blocks</p>
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
          <button class="btn-blue" onclick="openAddModal()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Add New Block
          </button>
        </div>
      </header>

      <div class="dashboard-stats">
        <div class="dash-stat-card">
          <div class="dash-stat-info">
            <div class="label">Total Blocks</div>
            <div class="value"><?php echo $stats['total']; ?></div>
          </div>
          <div class="dash-stat-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/></svg>
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
            <div class="label">Unused Blocks</div>
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
            <h2 class="title">Block List</h2>
            <p class="subtitle">All cemetery blocks and their assigned lots</p>
          </div>
          <div class="filter-controls">
            <div class="search-wrapper" style="position: relative;">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
              <input id="blockSearch" type="text" placeholder="Search blocks..." style="padding: 10px 16px 10px 40px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; width: 280px; outline: none; transition: all 0.2s;">
            </div>
          </div>
        </div>

        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th align="left">Block Details</th>
                <th align="left">Description</th>
                <th align="center">Lot Count</th>
                <th align="left">Created At</th>
                <th align="right">Actions</th>
              </tr>
            </thead>
            <tbody id="blocksTableBody">
              <?php foreach ($blocks as $block): ?>
                <tr>
                  <td>
                    <div class="block-name-cell">
                      <div class="block-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/></svg>
                      </div>
                      <div class="block-info">
                        <span class="name"><?php echo htmlspecialchars($block['name'] ?? ''); ?></span>
                        <span class="sub">ID: #<?php echo $block['id']; ?></span>
                      </div>
                    </div>
                  </td>
                  <td><?php echo htmlspecialchars($block['description'] ?? 'No description provided'); ?></td>
                  <td align="center">
                    <span style="background: #eff6ff; color: #3b82f6; padding: 4px 12px; border-radius: 20px; font-weight: 600; font-size: 12px;">
                      <?php echo $block['lot_count']; ?> Lots
                    </span>
                  </td>
                  <td><?php echo date('M d, Y', strtotime($block['created_at'])); ?></td>
                  <td align="right">
                    <div style="display: flex; justify-content: flex-end; gap: 8px;">
                      <?php if (!empty($block['map_x']) && !empty($block['map_y'])): ?>
                        <a href="cemetery-map.php?highlight_block=<?php echo $block['id']; ?>" class="btn-action btn-map" title="View on Map" style="background: #eff6ff; color: #3b82f6; display: flex; align-items: center; justify-content: center; text-decoration: none;">
                          <span class="icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                              <circle cx="12" cy="10" r="3" />
                            </svg>
                          </span>
                        </a>
                      <?php endif; ?>
                      <button class="btn-action btn-edit" onclick='openEditModal(<?php echo htmlspecialchars(json_encode($block), ENT_QUOTES, "UTF-8"); ?>)' title="Edit Block">
                        <span class="icon">
                          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 20h9" />
                            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" />
                          </svg>
                        </span>
                      </button>
                      <button class="btn-action btn-delete" onclick='deleteBlock(<?php echo json_encode($block); ?>)' title="Delete Block" style="display: none;">
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
              <?php if (empty($blocks)): ?>
                <tr>
                  <td colspan="5" style="text-align: center; padding: 60px; color: #94a3b8;">
                    <div style="margin-bottom: 12px; opacity: 0.5;">
                      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/></svg>
                    </div>
                    No blocks found. Add your first block to get started!
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
  <div id="blockModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 id="modalTitle" class="modal-title">Add New Block</h2>
        <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
      </div>
      <form id="blockForm">
        <div class="modal-body">
          <input type="hidden" id="blockId" name="id">
          <input type="hidden" id="map_x" name="map_x">
          <input type="hidden" id="map_y" name="map_y">
          <input type="hidden" id="map_width" name="map_width">
          <input type="hidden" id="map_height" name="map_height">
          <div class="form-group">
            <label for="name">Block Name</label>
            <input type="text" id="name" name="name" required placeholder="e.g. Block 1">
          </div>
          <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4" placeholder="Brief description of the block..."></textarea>
          </div>
          <div class="form-group">
            <label>Block Area on Map</label>
            <div id="blockMapPreview" style="width: 100%; height: 120px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #64748b; font-size: 13px; cursor: pointer; transition: all 0.2s; overflow: hidden; position: relative;" onclick="openMapPicker()">
              <div id="previewContent" style="display: flex; flex-direction: column; align-items: center; gap: 8px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2V6z"/><path d="M9 4v14"/><path d="M15 6v14"/></svg>
                <span>Draw Block Area on Map</span>
              </div>
              <div id="coordinatesInfo" style="display: none; position: absolute; bottom: 8px; right: 8px; background: rgba(255,255,255,0.9); padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: 600; color: #3b82f6; border: 1px solid #dbeafe;">
                Area Defined
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
          <button type="submit" class="btn-blue">Save Block</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Confirmation Modal -->
  <div id="confirmModal" class="confirm-modal">
    <div class="confirm-modal-content">
      <div class="confirm-icon">⚠</div>
      <h3 class="confirm-title">Delete Block?</h3>
      <p id="confirmMessage" class="confirm-message">Are you sure you want to delete this block? This action cannot be undone.</p>
      <div class="confirm-actions">
        <button class="btn-confirm-cancel" onclick="closeConfirmModal()">Cancel</button>
        <button id="confirmDeleteBtn" class="btn-confirm-delete">Delete</button>
      </div>
    </div>
  </div>

  <script>
    // Local Table Search Logic
    const blockSearch = document.getElementById('blockSearch');
    const tableBody = document.getElementById('blocksTableBody');

    if (blockSearch && tableBody) {
      blockSearch.addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase();
        const rows = tableBody.querySelectorAll('tr');

        rows.forEach(row => {
          if (row.cells.length < 2) return; // Skip empty row
          const name = row.querySelector('.block-info .name')?.textContent.toLowerCase() || '';
          const desc = row.cells[1]?.textContent.toLowerCase() || '';
          
          if (name.includes(query) || desc.includes(query)) {
            row.style.display = '';
          } else {
            row.style.display = 'none';
          }
        });
      });
    }
  </script>
  <!-- Map Area Picker Overlay -->
  <div id="mapPickerOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.95); z-index: 10000; flex-direction: column;">
    <div style="padding: 20px 32px; background: white; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
      <div>
        <h2 style="font-size: 18px; font-weight: 700; color: #1e293b; margin: 0;">Define Block Area</h2>
        <p style="font-size: 13px; color: #64748b; margin: 4px 0 0 0;">Click and drag on the map to draw a rectangle for the block area.</p>
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

  <script>
    window.existingBlockAreas = <?php echo json_encode(array_values($blocksWithAreas)); ?>;
  </script>
  <script src="../assets/js/api.js"></script>
  <script src="../assets/js/blocks.js"></script>
</body>
</html>