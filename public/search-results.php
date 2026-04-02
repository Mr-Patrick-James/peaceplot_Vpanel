<?php
require_once __DIR__ . '/../config/auth.php';
requireLogin();

$user = getUserInfo();
$userInitials = getInitials($user['full_name']);

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

$query = $_GET['q'] ?? '';
$results = [];

if (strlen($query) >= 2) {
    try {
        $searchParam = "%$query%";

        // Search Lots
        $stmt = $db->prepare("
            SELECT cl.id, cl.lot_number, s.name as section_name, b.name as block_name, cl.status, 'lot' as type 
            FROM cemetery_lots cl
            LEFT JOIN sections s ON cl.section_id = s.id
            LEFT JOIN blocks b ON s.block_id = b.id
            WHERE cl.lot_number LIKE ? OR s.name LIKE ? OR b.name LIKE ?
            ORDER BY cl.lot_number
        ");
        $stmt->execute([$searchParam, $searchParam, $searchParam]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = [
                'id' => $row['id'],
                'title' => "Lot " . $row['lot_number'],
                'subtitle' => "Section: " . ($row['section_name'] ?: 'No Section') . " (Block: " . ($row['block_name'] ?: 'No Block') . ")",
                'status' => $row['status'],
                'type' => 'Lot',
                'url' => "index.php?search=" . urlencode($row['lot_number'])
            ];
        }

        // Search Deceased Records
        $stmt = $db->prepare("
            SELECT dr.id, dr.full_name, dr.date_of_death, cl.lot_number, 'deceased' as type 
            FROM deceased_records dr
            LEFT JOIN cemetery_lots cl ON dr.lot_id = cl.id
            WHERE dr.full_name LIKE ?
            ORDER BY dr.full_name
        ");
        $stmt->execute([$searchParam]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = [
                'id' => $row['id'],
                'title' => $row['full_name'],
                'subtitle' => "Deceased Record" . ($row['lot_number'] ? " (Lot: " . $row['lot_number'] . ")" : ""),
                'status' => $row['date_of_death'] ? "Died: " . $row['date_of_death'] : "N/A",
                'type' => 'Deceased',
                'url' => "burial-records.php?search=" . urlencode($row['full_name'])
            ];
        }
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
  <title>PeacePlot Admin - Search Results</title>
  <link rel="stylesheet" href="../assets/css/styles.css" />
  <style>
    .dashboard-header {
      background: #fff;
      padding: 24px 32px;
      border-radius: 16px;
      margin-bottom: 24px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.03);
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: relative; /* For absolute search centering */
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
      margin: 0;
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

    /* Global Search Centering */
    .header-search {
      position: absolute;
      left: 50%;
      transform: translateX(-50%);
      width: 100%;
      max-width: 400px;
      z-index: 1000;
    }

    .search-results-container {
      background: white;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 4px 25px rgba(0,0,0,0.05);
      border: 1px solid #e2e8f0;
      margin-top: 8px; /* Added spacing from header */
    }
    .search-query-header {
      padding: 24px 32px;
      background: #f8fafc;
      border-bottom: 1px solid #e2e8f0;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .search-query-header h2 {
      font-size: 18px;
      color: #1e293b;
      margin: 0;
      font-weight: 700;
    }
    .search-query-header h2 span {
      color: #3b82f6;
    }
    .results-count-badge {
      background: #fff;
      padding: 6px 14px;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 700;
      color: #64748b;
      border: 1px solid #e2e8f0;
    }
    
    .results-grid {
      padding: 24px 32px;
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 24px;
    }
    
    .result-card {
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 20px;
      transition: all 0.2s ease;
      display: flex;
      flex-direction: column;
      gap: 16px;
      background: #fff;
    }
    
    .result-card:hover {
      border-color: #3b82f6;
      box-shadow: 0 10px 20px rgba(59, 130, 246, 0.05);
      transform: translateY(-2px);
    }
    
    .result-header {
      display: flex;
      align-items: flex-start;
      gap: 12px;
    }
    
    .result-icon-box {
      width: 44px;
      height: 44px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    
    .icon-box-lot { background: #eff6ff; color: #3b82f6; }
    .icon-box-deceased { background: #fef2f2; color: #ef4444; }
    
    .result-main-info {
      flex: 1;
      min-width: 0;
    }
    
    .result-title {
      font-size: 16px;
      font-weight: 700;
      color: #1e293b;
      margin-bottom: 4px;
      display: block;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    
    .result-type-label {
      font-size: 11px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      padding: 2px 8px;
      border-radius: 4px;
      display: inline-block;
      margin-bottom: 8px;
    }
    
    .label-lot { background: #dbeafe; color: #1e40af; }
    .label-deceased { background: #fee2e2; color: #991b1b; }
    
    .result-details {
      font-size: 13px;
      color: #64748b;
      line-height: 1.5;
    }
    
    .result-footer {
      margin-top: auto;
      padding-top: 16px;
      border-top: 1px solid #f1f5f9;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .result-status-pill {
      font-size: 12px;
      font-weight: 600;
      padding: 4px 10px;
      border-radius: 6px;
    }
    
    .status-vacant { background: #f0fdf4; color: #166534; }
    .status-occupied { background: #fff7ed; color: #9a3412; }
    .status-deceased { background: #f8fafc; color: #475569; }
    
    .btn-view-result {
      padding: 8px 16px;
      background: #3b82f6;
      color: #fff;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 700;
      text-decoration: none;
      transition: all 0.2s;
      text-align: center;
    }
    
    .btn-view-result:hover {
      background: #2563eb;
      box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
    }
    
    .empty-state {
      text-align: center;
      padding: 80px 32px;
      background: #fff;
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
            <span class="current">Global Search Results</span>
          </div>
          <h1 class="title">Global Search Results</h1>
          <p class="subtitle">Search results for your query across the entire system</p>
        </div>

        <div class="header-search">
          <div class="universal-search-wrapper">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            <input type="text" class="universal-search-input" id="universalSearch" placeholder="Global Search lots, deceased names..." value="<?php echo htmlspecialchars($query); ?>">
          </div>
          <div class="search-results-dropdown" id="searchResults">
            <!-- Results will be injected here -->
          </div>
        </div>

        <div class="header-actions">
          <a href="dashboard.php" class="btn-outline" style="text-decoration:none;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
            Back to Dashboard
          </a>
        </div>
      </header>

      <div class="search-results-container">
        <div class="search-query-header">
          <h2>Showing results for <span>"<?php echo htmlspecialchars($query); ?>"</span></h2>
          <div class="results-count-badge"><?php echo count($results); ?> matches found</div>
        </div>

        <?php if (empty($results)): ?>
          <div class="empty-state">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" style="margin-bottom: 24px; opacity: 0.5;">
              <circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <h3 style="font-size: 18px; color: #1e293b; margin-bottom: 8px;">No results found</h3>
            <p style="color: #64748b; font-size: 14px; max-width: 400px; margin: 0 auto;">
              We couldn't find any lots or records matching your query. 
              Try searching for a different lot number or a person's full name.
            </p>
          </div>
        <?php else: ?>
          <div class="results-grid">
            <?php foreach ($results as $item): ?>
              <?php 
                $type = strtolower($item['type']);
                $status = strtolower($item['status']);
                $statusClass = ($type === 'deceased') ? 'deceased' : $status;
              ?>
              <div class="result-card">
                <div class="result-header">
                  <div class="result-icon-box icon-box-<?php echo $type; ?>">
                    <?php if ($type === 'lot'): ?>
                      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    <?php else: ?>
                      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <?php endif; ?>
                  </div>
                  <div class="result-main-info">
                    <span class="result-type-label label-<?php echo $type; ?>"><?php echo $item['type']; ?></span>
                    <span class="result-title"><?php echo htmlspecialchars($item['title']); ?></span>
                  </div>
                </div>
                
                <div class="result-details">
                  <?php echo htmlspecialchars($item['subtitle']); ?>
                </div>
                
                <div class="result-footer">
                  <span class="result-status-pill status-<?php echo $statusClass; ?>">
                    <?php echo htmlspecialchars($item['status']); ?>
                  </span>
                  <a href="<?php echo $item['url']; ?>" class="btn-view-result">View Details</a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>

  <script src="../assets/js/app.js"></script>
</body>
</html>