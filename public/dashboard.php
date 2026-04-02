<?php
require_once __DIR__ . '/../config/auth.php';
requireLogin();

$user = getUserInfo();
$userInitials = getInitials($user['full_name']);

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

require_once __DIR__ . '/includes/page_tracker.php';

$stats = [
    'total_lots' => 0,
    'available_lots' => 0,
    'occupied_lots' => 0,
    'total_sections' => 0,
    'total_blocks' => 0,
    'total_burials' => 0,
    'sections' => []
];

if ($conn) {
    try {
        // Use subquery to get actual status for each lot first
        $statusQuery = "
            SELECT 
                cl.id,
                s.name as section_name,
                b.name as block_name,
                CASE 
                    WHEN (SELECT COUNT(*) FROM deceased_records dr WHERE dr.lot_id = cl.id) > 0 THEN 'Occupied'
                    WHEN (SELECT COUNT(*) FROM lot_layers ll WHERE ll.lot_id = cl.id AND ll.is_occupied = 1) > 0 THEN 'Occupied'
                    ELSE cl.status
                END as actual_status
            FROM cemetery_lots cl
            LEFT JOIN sections s ON cl.section_id = s.id
            LEFT JOIN blocks b ON s.block_id = b.id
        ";

        $stats['total_lots'] = $conn->query("SELECT COUNT(*) FROM cemetery_lots")->fetchColumn();
        
        $stats['available_lots'] = $conn->query("
            SELECT COUNT(*) FROM ($statusQuery) as lots WHERE actual_status = 'Vacant'
        ")->fetchColumn();
        
        $stats['occupied_lots'] = $conn->query("
            SELECT COUNT(*) FROM ($statusQuery) as lots WHERE actual_status = 'Occupied'
        ")->fetchColumn();

        // Fetch Section and Block counts
        $stats['total_sections'] = $conn->query("SELECT COUNT(*) FROM sections")->fetchColumn();
        $stats['total_blocks'] = $conn->query("SELECT COUNT(*) FROM blocks")->fetchColumn();
        $stats['total_burials'] = $conn->query("SELECT COUNT(*) FROM deceased_records")->fetchColumn();
        
        $stmt = $conn->query("
            SELECT 
                section_name as section,
                block_name as block,
                COUNT(*) as total,
                SUM(CASE WHEN actual_status = 'Occupied' THEN 1 ELSE 0 END) as occupied,
                SUM(CASE WHEN actual_status = 'Vacant' THEN 1 ELSE 0 END) as vacant
            FROM ($statusQuery) as lots
            WHERE section_name IS NOT NULL AND block_name IS NOT NULL
            GROUP BY section_name, block_name
            ORDER BY section_name, block_name
        ");
        $stats['sections'] = $stmt->fetchAll();
        
        // Fetch Recent Burials
        $stmt = $conn->query("
            SELECT dr.*, cl.lot_number, s.name as section_name
            FROM deceased_records dr
            LEFT JOIN cemetery_lots cl ON dr.lot_id = cl.id
            LEFT JOIN sections s ON cl.section_id = s.id
            ORDER BY dr.created_at DESC
            LIMIT 5
        ");
        $recent_burials = $stmt->fetchAll();
        
        $available_percent = $stats['total_lots'] > 0 ? round(($stats['available_lots'] / $stats['total_lots']) * 100, 1) : 0;
        $occupied_percent = $stats['total_lots'] > 0 ? round(($stats['occupied_lots'] / $stats['total_lots']) * 100, 1) : 0;
        
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
  <title>PeacePlot Admin - Dashboard</title>
  <link rel="stylesheet" href="../assets/css/styles.css" />
  <style>
    /* Dashboard Modern UI Refinements */
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
    
    /* Stats Cards Styles */
    .dashboard-stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
      margin-bottom: 32px;
    }
    .dash-stat-card {
      background: #fff;
      padding: 24px;
      border-radius: 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: 0 4px 20px rgba(0,0,0,0.02);
      border: 1px solid #f1f5f9;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .dash-stat-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 30px rgba(0,0,0,0.06);
      border-color: #e2e8f0;
    }
    .dash-stat-info .label {
      font-size: 12px;
      font-weight: 500;
      color: #94a3b8;
      margin-bottom: 6px;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    .dash-stat-info .value {
      font-size: 32px;
      font-weight: 600;
      color: #0f172a;
      line-height: 1;
      letter-spacing: -0.02em;
    }
    .dash-stat-info .subtext {
      font-size: 12px;
      color: #64748b;
      margin-top: 10px;
      font-weight: 400;
      display: flex;
      align-items: center;
      gap: 4px;
    }
    .dash-stat-icon {
      width: 52px;
      height: 52px;
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: transform 0.3s ease;
    }
    .dash-stat-card:hover .dash-stat-icon {
      transform: scale(1.1) rotate(-5deg);
    }
    
    .bg-blue-soft { background: #eff6ff; color: #3b82f6; }
    .bg-green-soft { background: #f0fdf4; color: #22c55e; }
    .bg-orange-soft { background: #fff7ed; color: #f97316; }
    .bg-indigo-soft { background: #f5f3ff; color: #6366f1; }

    .content-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 24px;
      margin-bottom: 24px;
    }

    .content-card {
      background: #fff;
      border-radius: 20px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.02);
      border: 1px solid #f1f5f9;
      margin-bottom: 24px;
      overflow: hidden;
    }
    .content-card-header {
      padding: 24px 28px;
      border-bottom: 1px solid #f8fafc;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .content-card-title {
      font-size: 18px;
      font-weight: 600;
      color: #0f172a;
      margin: 0;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    /* Table Styles */
    .table-modern {
      width: 100%;
      border-collapse: collapse;
    }
    .table-modern th {
      background: #f8fafc;
      padding: 14px 28px;
      text-align: left;
      font-size: 11px;
      font-weight: 600;
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      border-bottom: 1px solid #f1f5f9;
    }
    .table-modern td {
      padding: 18px 28px;
      border-bottom: 1px solid #f8fafc;
      color: #334155;
      font-size: 14px;
    }
    .table-modern tr:last-child td {
      border-bottom: none;
    }
    .table-modern tr:hover td {
      background: #fcfdfe;
    }

    /* Chart Refinements */
    .chart-section {
      padding: 32px;
    }
    .chart-bar-group {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      min-width: 80px;
    }
    .bar-container {
      height: 200px;
      display: flex;
      align-items: flex-end;
      gap: 6px;
      margin-bottom: 16px;
      width: 100%;
      justify-content: center;
    }
    .bar-segment {
      width: 24px;
      border-radius: 6px 6px 0 0;
      transition: all 0.3s ease;
      cursor: pointer;
    }
    .bar-segment:hover {
      filter: brightness(1.1);
      transform: scaleX(1.1);
    }

    @media (max-width: 1200px) {
      .content-grid { grid-template-columns: 1fr; }
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
        <a href="dashboard.php" class="active"><span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 13h8V3H3v10z" /><path d="M13 21h8V11h-8v10z" /><path d="M13 3h8v6h-8V3z" /><path d="M3 21h8v-6H3v6z" /></svg></span><span>Dashboard</span></a>
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
          <h1 class="title">Dashboard Overview</h1>
          <p class="subtitle">Quick overview of cemetery operations and statistics</p>
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
      </header>

      <?php if (isset($error)): ?>
        <div class="card" style="padding:20px; color:#ef4444;">
          Error loading dashboard data: <?php echo htmlspecialchars($error); ?>
        </div>
      <?php else: ?>

      <div class="dashboard-stats">
        <div class="dash-stat-card" onclick="window.location.href='index.php'" style="cursor:pointer;">
          <div class="dash-stat-info">
            <div class="label">Total Cemetery Lots</div>
            <div class="value"><?php echo $stats['total_lots']; ?></div>
            <div class="subtext">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="22 7 13.5 16 8.5 11 2 16"></polyline><polyline points="16 7 22 7 22 13"></polyline></svg>
              Across all sections
            </div>
          </div>
          <div class="dash-stat-icon bg-blue-soft">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
              <circle cx="12" cy="10" r="3" />
            </svg>
          </div>
        </div>

        <div class="dash-stat-card" onclick="window.location.href='sections.php'" style="cursor:pointer;">
          <div class="dash-stat-info">
            <div class="label">Total Sections</div>
            <div class="value"><?php echo $stats['total_sections']; ?></div>
            <div class="subtext">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path></svg>
              Defined areas
            </div>
          </div>
          <div class="dash-stat-icon bg-indigo-soft">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 7h16" /><path d="M4 12h16" /><path d="M4 17h16" />
            </svg>
          </div>
        </div>

        <div class="dash-stat-card" onclick="window.location.href='blocks.php'" style="cursor:pointer;">
          <div class="dash-stat-info">
            <div class="label">Total Blocks</div>
            <div class="value"><?php echo $stats['total_blocks']; ?></div>
            <div class="subtext">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="3" y1="15" x2="21" y2="15"></line><line x1="9" y1="3" x2="9" y2="21"></line><line x1="15" y1="3" x2="15" y2="21"></line></svg>
              Categorized
            </div>
          </div>
          <div class="dash-stat-icon bg-purple-soft" style="background:#f5f3ff; color:#a855f7;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/>
            </svg>
          </div>
        </div>

        <div class="dash-stat-card" onclick="window.location.href='burial-records.php'" style="cursor:pointer;">
          <div class="dash-stat-info">
            <div class="label">Total Burial Records</div>
            <div class="value"><?php echo $stats['total_burials']; ?></div>
            <div class="subtext">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
              Registered records
            </div>
          </div>
          <div class="dash-stat-icon" style="background:#fff1f2; color:#f43f5e;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" /><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" /><path d="M8 6h8" /><path d="M8 10h8" />
            </svg>
          </div>
        </div>

        <div class="dash-stat-card" onclick="window.location.href='lot-availability.php?status=Vacant'" style="cursor:pointer;">
          <div class="dash-stat-info">
            <div class="label">Available Lots</div>
            <div class="value"><?php echo $stats['available_lots']; ?></div>
            <div class="subtext">
              <span style="color:#22c55e"><?php echo $available_percent; ?>%</span> availability rate
            </div>
          </div>
          <div class="dash-stat-icon bg-green-soft">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
              <circle cx="12" cy="12" r="3" />
            </svg>
          </div>
        </div>

        <div class="dash-stat-card" onclick="window.location.href='lot-availability.php?status=Occupied'" style="cursor:pointer;">
          <div class="dash-stat-info">
            <div class="label">Occupied Lots</div>
            <div class="value"><?php echo $stats['occupied_lots']; ?></div>
            <div class="subtext">
              <span style="color:#f97316"><?php echo $occupied_percent; ?>%</span> current occupancy
            </div>
          </div>
          <div class="dash-stat-icon bg-orange-soft">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
              <circle cx="9" cy="7" r="4" />
              <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
              <path d="M16 3.13a4 4 0 0 1 0 7.75" />
            </svg>
          </div>
        </div>
      </div>

      <div class="content-grid">
        <!-- Recent Burials -->
        <div class="content-card">
          <div class="content-card-header">
            <h2 class="content-card-title">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
              Recent Burials
            </h2>
            <a href="burial-records.php" class="btn-outline" style="padding: 8px 16px; font-size: 13px; text-decoration: none; border-radius: 10px;">View All</a>
          </div>

          <div class="table-wrap">
            <table class="table-modern">
              <thead>
                <tr>
                  <th align="left">Full Name</th>
                  <th align="left">Lot Details</th>
                  <th align="left">Date of Death</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($recent_burials)): ?>
                  <tr>
                    <td colspan="3" style="text-align:center; padding: 60px; color:#94a3b8;">
                      <div style="margin-bottom:12px; opacity:0.5;"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg></div>
                      No recent burials found
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($recent_burials as $burial): ?>
                    <tr>
                      <td>
                        <div style="display:flex; align-items:center; gap:12px;">
                          <div style="width:32px; height:32px; border-radius:50%; background:#f1f5f9; display:flex; align-items:center; justify-content:center; font-weight:600; color:#475569; font-size:12px;">
                            <?php echo substr($burial['full_name'], 0, 1); ?>
                          </div>
                          <span style="font-weight: 500; color: #0f172a;"><?php echo htmlspecialchars($burial['full_name']); ?></span>
                        </div>
                      </td>
                      <td>
                        <div style="display:flex; flex-direction:column; gap:2px;">
                          <span style="font-weight:500; font-size:13px; color:#334155;">Lot <?php echo htmlspecialchars($burial['lot_number'] ?: 'N/A'); ?></span>
                          <span style="font-size: 11px; color: #64748b;"><?php echo htmlspecialchars($burial['section_name'] ?: 'No Section'); ?></span>
                        </div>
                      </td>
                      <td style="font-weight:400; color:#64748b;"><?php echo $burial['date_of_death'] ? date('M j, Y', strtotime($burial['date_of_death'])) : 'N/A'; ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Chart Analytics -->
      <div class="content-card">
        <div class="content-card-header">
          <div>
            <h2 class="content-card-title">Inventory Analytics (by Block)</h2>
            <p style="font-size:12px; color:#94a3b8; margin:4px 0 0 0;">Visual distribution of lot availability across all blocks</p>
          </div>
        </div>
        <div class="chart-section">
          <div class="bar-container" style="gap:20px; overflow-x: auto; padding-bottom: 20px; justify-content: flex-start;">
            <?php foreach ($stats['sections'] as $item): 
              $total = max($item['total'], 1);
              $vHeight = ($item['vacant'] / $total) * 180;
              $oHeight = ($item['occupied'] / $total) * 180;
            ?>
              <div class="chart-bar-group" style="min-width: 100px;">
                <div style="display:flex; gap:6px; align-items:flex-end; height:180px;">
                  <div class="bar-segment" style="height:<?php echo $vHeight; ?>px; background:#22c55e;" title="Vacant: <?php echo $item['vacant']; ?>"></div>
                  <div class="bar-segment" style="height:<?php echo $oHeight; ?>px; background:#f97316;" title="Occupied: <?php echo $item['occupied']; ?>"></div>
                </div>
                <span class="chart-label" style="margin-top:16px; color:#0f172a; font-weight:600; font-size: 11px;"><?php echo htmlspecialchars($item['block']); ?></span>
                <span style="font-size:9px; color:#94a3b8;"><?php echo htmlspecialchars($item['section']); ?></span>
                <div style="display:flex; gap:8px; margin-top:4px;">
                  <span style="font-size:10px; font-weight:600; color:#22c55e;"><?php echo $item['vacant']; ?>V</span>
                  <span style="font-size:10px; font-weight:600; color:#f97316;"><?php echo $item['occupied']; ?>O</span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          
          <div style="display:flex; justify-content:center; gap:32px; margin-top:48px; padding-top:24px; border-top:1px solid #f8fafc;">
            <div style="display:flex; align-items:center; gap:10px; font-size:13px; font-weight:500; color:#475569;">
              <div style="width:12px; height:12px; background:#22c55e; border-radius:4px;"></div> Vacant Lots
            </div>
            <div style="display:flex; align-items:center; gap:10px; font-size:13px; font-weight:500; color:#475569;">
              <div style="width:12px; height:12px; background:#f97316; border-radius:4px;"></div> Occupied Lots
            </div>
          </div>
        </div>
      </div>

      <?php endif; ?>
    </main>
  </div>

  <script src="../assets/js/app.js"></script>
</body>
</html>
