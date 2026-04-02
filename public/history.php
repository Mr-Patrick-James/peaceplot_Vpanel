<?php
require_once __DIR__ . '/../config/auth.php';
requireLogin();

$user = getUserInfo();
$userInitials = getInitials($user['full_name']);

require_once __DIR__ . '/../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$logs = [];
$archivedLogs = [];
$showArchived = isset($_GET['view']) && $_GET['view'] === 'archived';

if ($conn) {
    try {
        // Ensure columns exist (migration helpers)
        $conn->exec("ALTER TABLE activity_logs ADD COLUMN is_archived BOOLEAN DEFAULT 0");
    } catch (PDOException $e) {
        // Ignore if already exists
    }
    try {
        $conn->exec("ALTER TABLE activity_logs ADD COLUMN session_id VARCHAR(128)");
    } catch (PDOException $e) {
        // Ignore if already exists
    }

    try {
        $archivedCondition = $showArchived ? "al.is_archived = 1" : "al.is_archived = 0";
        // Fetch all activity logs including session events
        $stmt = $conn->query("
            SELECT al.*, u.full_name as user_name 
            FROM activity_logs al 
            LEFT JOIN users u ON al.user_id = u.id 
            WHERE $archivedCondition
            ORDER BY al.created_at DESC
        ");
        $logs = $stmt->fetchAll();
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
  <title>PeacePlot Admin - System History</title>
  <link rel="stylesheet" href="../assets/css/styles.css" />
  <!-- Flatpickr for better date selection -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
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
        <a href="history.php" class="active"><span class="icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span><span>History</span></a>
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
            <span class="current">System History</span>
          </div>
          <h1 class="title">System History</h1>
          <p class="subtitle">Log of all activities and changes in the system</p>
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
          <a href="history.php?view=<?php echo $showArchived ? 'active' : 'archived'; ?>" class="btn-outline" style="text-decoration:none; display: none;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 8v13H3V8"></path>
              <path d="M1 3h22v5H1z"></path>
              <path d="M10 12h4"></path>
            </svg>
            <?php echo $showArchived ? 'Active Logs' : 'Archived Logs'; ?>
          </a>
        </div>
      </header>

      <section class="card">
        <div class="card-head" style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
          <div>
            <h2 class="card-title"><?php echo $showArchived ? 'Archived System Activity' : 'System Activity Log'; ?></h2>
            <p class="card-sub"><?php echo $showArchived ? 'History logs that have been moved to archive.' : 'A comprehensive log of all changes made in the system, from newest to oldest.'; ?></p>
          </div>
          <div style="display:flex; gap:10px; align-items:center;">
            <div style="display:flex; align-items:center; gap:8px; background:#fff; padding:8px 15px; border:2px solid #e2e8f0; border-radius:12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
              <label for="startDate" style="font-size:13px; font-weight:600; color:#64748b;">From:</label>
              <input type="text" id="startDate" placeholder="YYYY-MM-DD" style="border:none; outline:none; font-size:14px; color:#1e293b; width: 100px;">
              <div style="width:1px; height:20px; background:#e2e8f0; margin:0 5px;"></div>
              <label for="endDate" style="font-size:13px; font-weight:600; color:#64748b;">To:</label>
              <input type="text" id="endDate" placeholder="YYYY-MM-DD" style="border:none; outline:none; font-size:14px; color:#1e293b; width: 100px;">
            </div>
            <input 
              id="historySearch" 
              type="text" 
              placeholder="🔍 Search activity…" 
              style="padding:12px 20px; border:2px solid #e2e8f0; border-radius:12px; font-size:16px; width:300px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06); transition: all 0.2s ease; outline: none;"
              onfocus="this.style.borderColor='#3b82f6'; this.style.boxShadow='0 0 0 3px rgba(59, 130, 246, 0.3), 0 4px 6px -1px rgba(0,0,0,0.1)';"
              onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06)';">
          </div>
        </div>

        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th align="left">Date & Time</th>
                <th align="left">User</th>
                <th align="left">Action</th>
                <th align="left">Description</th>
                <th align="right">Actions</th>
              </tr>
            </thead>
            <tbody id="historyTableBody">
              <?php if (isset($error)): ?>
                <tr>
                  <td colspan="5" style="text-align:center; color:#ef4444;">
                    Error loading data: <?php echo htmlspecialchars($error); ?>
                  </td>
                </tr>
              <?php elseif (empty($logs)): ?>
                <tr>
                  <td colspan="5" style="text-align:center; color:#6b7280;">
                    No system activity found.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($logs as $log): ?>
                  <tr class="history-row" 
                      data-id="<?php echo $log['id']; ?>"
                      data-user="<?php echo strtolower(htmlspecialchars($log['user_name'] ?: 'System')); ?>"
                      data-action="<?php echo strtolower(htmlspecialchars($log['action'])); ?>"
                      data-desc="<?php echo strtolower(htmlspecialchars($log['description'])); ?>"
                      data-date="<?php echo date('Y-m-d', strtotime($log['created_at'])); ?>">
                    <td style="white-space: nowrap;"><span style="font-weight: 400; color: #1f2937;"><?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?></span></td>
                    <td><span style="color: #4b5563; font-weight: 400;"><?php echo htmlspecialchars($log['user_name'] ?: 'System'); ?></span></td>
                    <td>
                      <?php 
                        $badgeClass = 'badge-info';
                        if (strpos($log['action'], 'DELETE') !== false) $badgeClass = 'badge-danger';
                        if (strpos($log['action'], 'ADD') !== false) $badgeClass = 'badge-success';
                        if (strpos($log['action'], 'UPDATE') !== false) $badgeClass = 'badge-warning';
                        if ($log['action'] === 'LOGIN') $badgeClass = 'badge-login';
                        if ($log['action'] === 'LOGOUT') $badgeClass = 'badge-logout';
                        if ($log['action'] === 'FAILED_LOGIN') $badgeClass = 'badge-danger';
                        if ($log['action'] === 'PAGE_VIEW') $badgeClass = 'badge-page';
                        if ($log['action'] === 'EXPORT_CSV') $badgeClass = 'badge-warning';
                        if ($log['action'] === 'EXPORT_DB') $badgeClass = 'badge-warning';
                        if (strpos($log['action'], 'IMAGE') !== false) $badgeClass = 'badge-image';
                      ?>
                      <span class="badge <?php echo $badgeClass; ?>" style="padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 500; text-transform: uppercase;">
                        <?php echo htmlspecialchars($log['action']); ?>
                      </span>
                    </td>
                    <td style="color: #4b5563;"><?php echo htmlspecialchars($log['description']); ?></td>
                    <td align="right">
                      <button class="btn-action archive-single-btn" 
                              data-id="<?php echo $log['id']; ?>" 
                              data-action-type="<?php echo $showArchived ? 'restore' : 'archive'; ?>"
                              title="<?php echo $showArchived ? 'Restore to Active' : 'Move to Archive'; ?>"
                              style="display: none;">
                        <span class="icon">
                          <?php if ($showArchived): ?>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                              <polyline points="23 4 23 10 17 10"></polyline>
                              <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                            </svg>
                          <?php else: ?>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                              <polyline points="21 8 21 21 3 21 3 8"></polyline>
                              <rect x="1" y="3" width="22" height="5"></rect>
                            </svg>
                          <?php endif; ?>
                        </span>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>

  <style>
    .badge-success { background: #dcfce7; color: #166534; }
    .badge-danger { background: #fee2e2; color: #991b1b; }
    .badge-warning { background: #fef9c3; color: #854d0e; }
    .badge-info { background: #e0f2fe; color: #075985; }
    .badge-login { background: #ede9fe; color: #5b21b6; }
    .badge-logout { background: #f1f5f9; color: #475569; }
    .badge-page { background: #f0fdf4; color: #15803d; }
    .badge-image { background: #fdf4ff; color: #7e22ce; }
  </style>

  <script src="../assets/js/app.js"></script>
  <script>
    // Search and filter functionality
    document.addEventListener('DOMContentLoaded', () => {
      const searchInput = document.getElementById('historySearch');
      const startDateInput = document.getElementById('startDate');
      const endDateInput = document.getElementById('endDate');
      const rows = document.querySelectorAll('.history-row');

      // Initialize Flatpickr for date filters
      if (typeof flatpickr !== 'undefined') {
        flatpickr("#startDate", {
          dateFormat: "Y-m-d",
          altInput: true,
          altFormat: "M j, Y",
          allowInput: true,
          monthSelectorType: 'static',
          onChange: filterTable
        });
        flatpickr("#endDate", {
          dateFormat: "Y-m-d",
          altInput: true,
          altFormat: "M j, Y",
          allowInput: true,
          monthSelectorType: 'static',
          onChange: filterTable
        });
      }

      function filterTable() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        const startDate = startDateInput.value;
        const endDate = endDateInput.value;

        rows.forEach(row => {
          const user = row.getAttribute('data-user');
          const action = row.getAttribute('data-action');
          const desc = row.getAttribute('data-desc');
          const rowDate = row.getAttribute('data-date');

          const matchesSearch = user.includes(searchTerm) || 
                               action.includes(searchTerm) || 
                               desc.includes(searchTerm);
          
          let matchesDate = true;
          if (startDate && rowDate < startDate) matchesDate = false;
          if (endDate && rowDate > endDate) matchesDate = false;

          if (matchesSearch && matchesDate) {
            row.style.display = '';
          } else {
            row.style.display = 'none';
          }
        });
      }

      if (searchInput) searchInput.addEventListener('input', filterTable);
      if (startDateInput) startDateInput.addEventListener('change', filterTable);
      if (endDateInput) endDateInput.addEventListener('change', filterTable);

      // Handle single log archiving/restoring
      document.querySelectorAll('.archive-single-btn').forEach(btn => {
        btn.addEventListener('click', async (e) => {
          const logId = btn.getAttribute('data-id');
          const actionType = btn.getAttribute('data-action-type');
          const isArchive = actionType === 'archive';
          
          if (!confirm(`Are you sure you want to ${isArchive ? 'archive' : 'restore'} this activity log?`)) {
            return;
          }
          
          try {
            const response = await fetch('../api/archive_history.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ 
                action: isArchive ? 'archive_single' : 'restore_single', 
                id: logId 
              })
            });
            const result = await response.json();
            
            if (result.success) {
              // Smoothly remove the row
              const row = btn.closest('tr');
              row.style.transition = 'all 0.3s ease';
              row.style.opacity = '0';
              row.style.transform = 'translateX(20px)';
              setTimeout(() => {
                row.remove();
                // If no more rows, show the empty message
                if (document.querySelectorAll('.history-row').length === 0) {
                  window.location.reload();
                }
              }, 300);
            } else {
              alert('Error: ' + result.message);
            }
          } catch (error) {
            console.error('Error:', error);
            alert(`Failed to ${actionType} log.`);
          }
        });
      });
    });
  </script>
</body>
</html>
