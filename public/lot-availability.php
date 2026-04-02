<?php
require_once __DIR__ . '/../config/auth.php';
requireLogin();

$user = getUserInfo();
$userInitials = getInitials($user['full_name']);

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

require_once __DIR__ . '/includes/page_tracker.php';

$sections = [];
$blocks = [];
$filterStatus = isset($_GET['status']) ? $_GET['status'] : 'Vacant';
$filterSection = isset($_GET['section']) ? $_GET['section'] : '';
$filterBlock = isset($_GET['block']) ? $_GET['block'] : '';

if ($conn) {
    try {
        // Get overall statistics
        $overallStats = $conn->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Vacant' THEN 1 ELSE 0 END) as vacant,
                SUM(CASE WHEN status = 'Occupied' THEN 1 ELSE 0 END) as occupied
            FROM cemetery_lots
        ")->fetch(PDO::FETCH_ASSOC);

        // Get section statistics (still needed for filter dropdown)
        $stmt = $conn->query("
            SELECT 
                s.name as section,
                COUNT(cl.id) as total,
                SUM(CASE WHEN cl.status = 'Vacant' THEN 1 ELSE 0 END) as vacant,
                SUM(CASE WHEN cl.status = 'Occupied' THEN 1 ELSE 0 END) as occupied
            FROM sections s
            LEFT JOIN cemetery_lots cl ON s.id = cl.section_id
            GROUP BY s.id
            ORDER BY s.name
        ");
        $sections = $stmt->fetchAll();

        // Get unique blocks
        $blockStmt = $conn->query("SELECT id, name FROM blocks ORDER BY name");
        $blocks = $blockStmt->fetchAll();
        
        // Pagination parameters
        $itemsPerPage = isset($_GET['print_all']) ? 999999 : 20;
        $currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $offset = ($currentPage - 1) * $itemsPerPage;

        // Get total count for filtered lots
        $countQuery = "
            SELECT COUNT(*) 
            FROM cemetery_lots cl
            LEFT JOIN sections s ON cl.section_id = s.id
            LEFT JOIN blocks b ON s.block_id = b.id
            WHERE cl.status = :status
        ";
        if ($filterSection) {
            $countQuery .= " AND s.name = :section";
        }
        if ($filterBlock) {
            $countQuery .= " AND b.name = :block";
        }
        $countStmt = $conn->prepare($countQuery);
        $countStmt->bindParam(':status', $filterStatus);
        if ($filterSection) {
            $countStmt->bindParam(':section', $filterSection);
        }
        if ($filterBlock) {
            $countStmt->bindParam(':block', $filterBlock);
        }
        $countStmt->execute();
        $totalItems = $countStmt->fetchColumn();
        $totalPages = ceil($totalItems / $itemsPerPage);

        // Get filtered and paginated lots
        $query = "SELECT cl.*, s.name as section_name, b.name as block_name,
                         (SELECT GROUP_CONCAT(full_name, ', ') FROM (SELECT full_name FROM deceased_records WHERE lot_id = cl.id AND is_archived = 0 ORDER BY created_at DESC)) as deceased_name,
                         COALESCE(NULLIF((SELECT COUNT(*) FROM lot_layers ll WHERE ll.lot_id = cl.id), 0), cl.layers, 1) as total_layers_count,
                         (SELECT COUNT(DISTINCT layer) FROM deceased_records WHERE lot_id = cl.id AND is_archived = 0) as occupied_layers_count
                  FROM cemetery_lots cl 
                  LEFT JOIN sections s ON cl.section_id = s.id
                  LEFT JOIN blocks b ON s.block_id = b.id
                  WHERE cl.status = :status";
        
        if ($filterSection) {
            $query .= " AND s.name = :section";
        }
        if ($filterBlock) {
            $query .= " AND b.name = :block";
        }
        
        $query .= " GROUP BY cl.id ORDER BY LENGTH(cl.lot_number), cl.lot_number LIMIT :limit OFFSET :offset";
        
        $lotsStmt = $conn->prepare($query);
        $lotsStmt->bindParam(':status', $filterStatus);
        if ($filterSection) {
            $lotsStmt->bindParam(':section', $filterSection);
        }
        if ($filterBlock) {
            $lotsStmt->bindParam(':block', $filterBlock);
        }
        $lotsStmt->bindParam(':limit', $itemsPerPage, PDO::PARAM_INT);
        $lotsStmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $lotsStmt->execute();
        $filteredLots = $lotsStmt->fetchAll();
        
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
  <title>PeacePlot Admin - Lots</title>
  <link rel="stylesheet" href="../assets/css/styles.css" />
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
            <a href="lot-availability.php" class="active"><span>Lots</span></a>
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
            <span class="current">Lots Monitoring</span>
          </div>
          <h1 class="title">Lots Monitoring</h1>
          <p class="subtitle">Quick overview of lot availability and statistics</p>
          <p class="print-only" style="display:none; font-size: 12px; color: #64748b; margin-top: 8px;">Report generated on: <?php echo date('F j, Y, g:i a'); ?></p>
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
          <div class="export-dropdown">
            <button class="btn-outline" id="exportBtn" style="padding: 10px 20px; border-radius: 12px; display: flex; align-items: center; gap: 8px; font-weight: 600; cursor: pointer;">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" /></svg>
              Export / Print
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
            </button>
            <div class="export-menu" id="exportMenu">
              <a href="#" onclick="printCurrentPage(); return false;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7" /><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" /><path d="M6 14h12v8H6z" /></svg>
                Print Current View
              </a>
              <a href="#" onclick="printAllRecords(); return false;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" /><line x1="16" y1="13" x2="8" y2="13" /><line x1="16" y1="17" x2="8" y2="17" /><polyline points="10 9 9 9 8 9" /></svg>
                Print All Records (PDF)
              </a>
              <a href="#" onclick="exportToExcel(); return false;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/></svg>
                Export to Excel (.csv)
              </a>
            </div>
          </div>
        </div>
      </header>

      <?php if (isset($error)): ?>
        <div class="card" style="padding:20px; color:#ef4444;">
          Error loading data: <?php echo htmlspecialchars($error); ?>
        </div>
      <?php else: ?>

      <div class="stats-summary">
        <div class="stat-card-modern">
          <div class="stat-icon-wrapper bg-blue-light">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" /><circle cx="12" cy="10" r="3" /></svg>
          </div>
          <div class="stat-content">
            <div class="stat-label">Total Lots</div>
            <div class="stat-value"><?php echo $overallStats['total']; ?></div>
          </div>
        </div>
        <div class="stat-card-modern">
          <div class="stat-icon-wrapper bg-green-light">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" /></svg>
          </div>
          <div class="stat-content">
            <div class="stat-label">Vacant</div>
            <div class="stat-value"><?php echo $overallStats['vacant']; ?></div>
          </div>
        </div>
        <div class="stat-card-modern">
          <div class="stat-icon-wrapper bg-orange-light">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M23 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" /></svg>
          </div>
          <div class="stat-content">
            <div class="stat-label">Occupied</div>
            <div class="stat-value"><?php echo $overallStats['occupied']; ?></div>
          </div>
        </div>
      </div>

      <div class="card-monitoring">
        <div class="monitoring-tabs">
          <a href="?status=Vacant<?php echo $filterSection ? '&section=' . urlencode($filterSection) : ''; ?><?php echo $filterBlock ? '&block=' . urlencode($filterBlock) : ''; ?>" 
             class="monitoring-tab <?php echo $filterStatus === 'Vacant' ? 'active' : ''; ?>">
            Vacant Lots
          </a>
          <a href="?status=Occupied<?php echo $filterSection ? '&section=' . urlencode($filterSection) : ''; ?><?php echo $filterBlock ? '&block=' . urlencode($filterBlock) : ''; ?>" 
             class="monitoring-tab <?php echo $filterStatus === 'Occupied' ? 'active' : ''; ?>">
            Occupied Lots
          </a>
        </div>

        <div class="monitoring-filters">
          <div>
            <h2 style="font-size: 18px; font-weight: 700; color: #1e293b; margin: 0;"><?php echo htmlspecialchars($filterStatus); ?> Lots Inventory</h2>
            <p style="font-size: 13px; color: #64748b; margin: 4px 0 0 0;">
              Showing <?php echo $totalItems > 0 ? ($offset + 1) : 0; ?>-<?php echo min($offset + $itemsPerPage, $totalItems); ?> of <?php echo $totalItems; ?> records
            </p>
          </div>
          <div class="filter-group">
            <select id="blockFilter" class="filter-select">
              <option value="">All Blocks</option>
              <?php foreach ($blocks as $block): ?>
                <option value="<?php echo htmlspecialchars($block['name']); ?>" 
                        <?php echo $filterBlock === $block['name'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($block['name']); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <select id="sectionFilter" class="filter-select">
              <option value="">All Sections</option>
              <?php foreach ($sections as $section): ?>
                <option value="<?php echo htmlspecialchars($section['section']); ?>" 
                        <?php echo $filterSection === $section['section'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($section['section']); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="table-wrap">
          <table class="table-modern">
            <thead>
              <tr>
                <th>Lot Number</th>
                <th>Section</th>
                <th>Block</th>
                <th>Position</th>
                <th>Status</th>
                <th>Layers</th>
                <?php if ($filterStatus === 'Occupied'): ?>
                  <th>Deceased Name</th>
                <?php endif; ?>
                <th align="right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($filteredLots)): ?>
                <tr>
                  <td colspan="<?php echo $filterStatus === 'Occupied' ? '8' : '7'; ?>" style="text-align:center; padding: 60px; color:#94a3b8;">
                    <div style="margin-bottom:12px; opacity:0.5;"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg></div>
                    No <?php echo strtolower($filterStatus); ?> lots found
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($filteredLots as $lot): ?>
                  <tr>
                    <td style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($lot['lot_number']); ?></td>
                    <td><?php echo htmlspecialchars($lot['section_name'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($lot['block_name'] ?: '—'); ?></td>
                    <td><span style="font-family: monospace; font-size: 12px; color: #64748b;"><?php echo htmlspecialchars($lot['position'] ?: '—'); ?></span></td>
                    <td>
                      <span class="lot-badge <?php echo $lot['status'] === 'Vacant' ? 'badge-vacant' : 'badge-occupied'; ?>">
                        <?php echo htmlspecialchars($lot['status']); ?>
                      </span>
                    </td>
                    <td>
                      <div style="display:flex; flex-direction:column; gap:4px;">
                        <span style="font-weight: 600; font-size: 13px;">
                          <?php echo intval($lot['occupied_layers_count'] ?: 0); ?> / <?php echo intval($lot['total_layers_count'] ?: 1); ?>
                        </span>
                        <div style="width: 60px; height: 4px; background: #f1f5f9; border-radius: 2px; overflow: hidden;">
                          <?php 
                            $percent = (intval($lot['occupied_layers_count'] ?: 0) / intval($lot['total_layers_count'] ?: 1)) * 100;
                          ?>
                          <div style="width: <?php echo $percent; ?>%; height: 100%; background: #3b82f6;"></div>
                        </div>
                      </div>
                    </td>
                    <?php if ($filterStatus === 'Occupied'): ?>
                      <td style="font-weight: 500; color: #334155;"><?php echo htmlspecialchars($lot['deceased_name'] ?: '—'); ?></td>
                    <?php endif; ?>
                    <td align="right">
                      <button class="btn-view-map" onclick="handleMapRedirect(<?php echo $lot['id']; ?>, '<?php echo htmlspecialchars($lot['lot_number']); ?>')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" /><circle cx="12" cy="10" r="3" /></svg>
                        View on Map
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination-wrap" style="padding: 20px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 8px; align-items: center;">
          <?php
            $base_url = "?status=" . urlencode($filterStatus) . 
                        ($filterSection ? "&section=" . urlencode($filterSection) : "") .
                        ($filterBlock ? "&block=" . urlencode($filterBlock) : "");
          ?>
          
          <a href="<?php echo $base_url; ?>&page=<?php echo $currentPage - 1; ?>" 
             class="pagination-btn <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>"
             style="text-decoration: none;">Previous</a>
          
          <?php
          $delta = 2;
          for ($i = 1; $i <= $totalPages; $i++):
            if ($i === 1 || $i === $totalPages || ($i >= $currentPage - $delta && $i <= $currentPage + $delta)):
          ?>
            <a href="<?php echo $base_url; ?>&page=<?php echo $i; ?>" 
               class="pagination-btn <?php echo $i === $currentPage ? 'active' : ''; ?>"
               style="text-decoration: none;"><?php echo $i; ?></a>
          <?php elseif ($i === $currentPage - $delta - 1 || $i === $currentPage + $delta + 1): ?>
            <span style="padding: 8px; color: #64748b;">...</span>
          <?php endif; endfor; ?>
          
          <a href="<?php echo $base_url; ?>&page=<?php echo $currentPage + 1; ?>" 
             class="pagination-btn <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>"
             style="text-decoration: none;">Next</a>
        </div>
        <?php endif; ?>
      </section>

      <?php endif; ?>
    </main>
  </div>

  <script src="../assets/js/app.js"></script>
  <style>
    /* Modern UI Refinements */
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
    
    .stats-summary {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 20px;
      margin-bottom: 32px;
    }
    .stat-card-modern {
      background: #fff;
      padding: 24px;
      border-radius: 20px;
      border: 1px solid #f1f5f9;
      display: flex;
      align-items: center;
      gap: 20px;
      transition: all 0.3s ease;
    }
    .stat-card-modern:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 30px rgba(0,0,0,0.06);
    }
    .stat-icon-wrapper {
      width: 56px;
      height: 56px;
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .stat-content .stat-label {
      font-size: 13px;
      font-weight: 500;
      color: #94a3b8;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-bottom: 4px;
    }
    .stat-content .stat-value {
      font-size: 28px;
      font-weight: 700;
      color: #1e293b;
      line-height: 1;
    }
    
    .bg-blue-light { background: #eff6ff; color: #3b82f6; }
    .bg-green-light { background: #f0fdf4; color: #22c55e; }
    .bg-orange-light { background: #fff7ed; color: #f97316; }

    .card-monitoring {
      background: #fff;
      border-radius: 20px;
      border: 1px solid #f1f5f9;
      overflow: hidden;
      box-shadow: 0 4px 20px rgba(0,0,0,0.02);
    }

    .monitoring-tabs {
      display: flex;
      background: #f8fafc;
      padding: 6px;
      gap: 4px;
      border-bottom: 1px solid #f1f5f9;
    }
    .monitoring-tab {
      flex: 1;
      padding: 12px;
      text-align: center;
      font-size: 14px;
      font-weight: 600;
      color: #64748b;
      text-decoration: none;
      border-radius: 12px;
      transition: all 0.2s;
    }
    .monitoring-tab.active {
      background: #fff;
      color: #3b82f6;
      box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }

    .monitoring-filters {
      padding: 20px 28px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 16px;
      flex-wrap: wrap;
    }
    .filter-group {
      display: flex;
      gap: 12px;
      align-items: center;
    }
    .filter-select {
      padding: 10px 16px;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      font-size: 14px;
      color: #475569;
      font-weight: 500;
      background: #fff;
      min-width: 160px;
      cursor: pointer;
      outline: none;
      transition: all 0.2s;
    }
    .filter-select:hover { border-color: #cbd5e1; }
    .filter-select:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }

    .table-modern {
      width: 100%;
      border-collapse: collapse;
    }
    .table-modern th {
      background: #f8fafc;
      padding: 16px 28px;
      text-align: left;
      font-size: 12px;
      font-weight: 600;
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
    .table-modern td {
      padding: 18px 28px;
      border-bottom: 1px solid #f1f5f9;
      font-size: 14px;
      color: #334155;
    }
    .table-modern tr:last-child td { border-bottom: none; }
    .table-modern tr:hover td { background: #fcfdfe; }

    .lot-badge {
      padding: 6px 12px;
      border-radius: 8px;
      font-size: 12px;
      font-weight: 600;
    }
    .badge-vacant { background: #f0fdf4; color: #166534; }
    .badge-occupied { background: #fff7ed; color: #9a3412; }

    .pagination-btn {
      padding: 8px 14px;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      background: white;
      color: #475569;
      font-size: 14px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .pagination-btn:hover:not(.disabled) {
      background: #f8fafc;
      border-color: #cbd5e1;
    }
    .pagination-btn.active {
      background: #3b82f6;
      color: white;
      border-color: #3b82f6;
    }
    .pagination-btn.disabled {
      opacity: 0.5;
      pointer-events: none;
      cursor: not-allowed;
    }

    .btn-view-map {
      padding: 8px 16px;
      background: #eff6ff;
      color: #3b82f6;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 600;
      border: none;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: all 0.2s;
    }
    .btn-view-map:hover { background: #dbeafe; }

    /* Export Dropdown Styles */
    .export-dropdown {
      position: relative;
      display: inline-block;
    }
    .export-menu {
      display: none;
      position: absolute;
      right: 0;
      top: 100%;
      background: white;
      min-width: 200px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.1);
      border-radius: 12px;
      padding: 8px;
      z-index: 1000;
      border: 1px solid #f1f5f9;
      margin-top: 8px;
    }
    .export-menu.active {
      display: block;
      animation: fadeIn 0.2s ease;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .export-menu a {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 12px 16px;
      text-decoration: none;
      color: #475569;
      font-size: 14px;
      font-weight: 500;
      border-radius: 8px;
      transition: all 0.2s;
    }
    .export-menu a:hover {
      background: #f8fafc;
      color: #3b82f6;
    }
    .export-menu a svg {
      color: #94a3b8;
    }
    .export-menu a:hover svg {
      color: #3b82f6;
    }

    /* Print Styles */
    @media print {
      @page {
        margin: 1cm;
        size: auto;
      }
      body {
        background: #fff !important;
        color: #000 !important;
      }
      .sidebar, .dashboard-header .header-search, .header-actions, 
      .monitoring-tabs, .monitoring-filters .filter-group, 
      .btn-view-map, .pagination-wrap, .universal-search-wrapper, .search-results-dropdown {
        display: none !important;
      }
      .app {
        display: block !important;
      }
      .main {
        margin-left: 0 !important;
        padding: 0 !important;
        width: 100% !important;
      }
      .dashboard-header {
        box-shadow: none !important;
        border-bottom: 2px solid #eee !important;
        margin-bottom: 30px !important;
        padding: 0 0 20px 0 !important;
      }
      .stats-summary {
        grid-template-columns: repeat(3, 1fr) !important;
        gap: 15px !important;
        margin-bottom: 30px !important;
      }
      .stat-card-modern {
        border: 1px solid #eee !important;
        box-shadow: none !important;
        padding: 15px !important;
      }
      .card-monitoring {
        border: none !important;
        box-shadow: none !important;
      }
      .table-modern {
        border: 1px solid #eee !important;
      }
      .table-modern th {
        background: #f9fafb !important;
        color: #000 !important;
        border-bottom: 2px solid #eee !important;
      }
      .table-modern td {
        border-bottom: 1px solid #eee !important;
      }
      .lot-badge {
        border: 1px solid #eee !important;
        background: transparent !important;
        color: #000 !important;
        padding: 2px 6px !important;
      }
      .table-modern td:last-child, .table-modern th:last-child {
        display: none !important;
      }
      .monitoring-filters {
        padding: 0 0 15px 0 !important;
      }
      .breadcrumbs {
        display: none !important;
      }
      .print-only {
        display: block !important;
      }
    }
  </style>

  <script>
    function handleMapRedirect(lotId, lotNumber) {
      // Redirect to cemetery map page with highlighted lot parameter
      window.location.href = `cemetery-map.php?highlight_lot=${lotId}`;
    }
    
    document.getElementById('sectionFilter')?.addEventListener('change', function() {
      const section = this.value;
      const status = '<?php echo $filterStatus; ?>';
      const block = '<?php echo $filterBlock; ?>';
      window.location.href = '?status=' + status + (section ? '&section=' + encodeURIComponent(section) : '') + (block ? '&block=' + encodeURIComponent(block) : '');
    });

    document.getElementById('blockFilter')?.addEventListener('change', function() {
      const block = this.value;
      const status = '<?php echo $filterStatus; ?>';
      const section = '<?php echo $filterSection; ?>';
      window.location.href = '?status=' + status + (section ? '&section=' + encodeURIComponent(section) : '') + (block ? '&block=' + encodeURIComponent(block) : '');
    });

    // Export Dropdown Logic
    const exportBtn = document.getElementById('exportBtn');
    const exportMenu = document.getElementById('exportMenu');

    if (exportBtn && exportMenu) {
      exportBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        exportMenu.classList.toggle('active');
      });

      document.addEventListener('click', () => {
        exportMenu.classList.remove('active');
      });
    }

    function printCurrentPage() {
      window.print();
    }

    function printAllRecords() {
      const url = new URL(window.location.href);
      url.searchParams.set('print_all', '1');
      const printWindow = window.open(url.href, '_blank');
      printWindow.addEventListener('load', () => {
        printWindow.print();
        // Optional: close window after print
        // printWindow.close();
      }, true);
    }

    function exportToExcel() {
      const status = '<?php echo $filterStatus; ?>';
      const reportType = status === 'Vacant' ? 'vacant_lots' : 'occupied_lots';
      window.location.href = `../api/export_csv.php?type=${reportType}`;
    }
  </script>
</body>
</html>
