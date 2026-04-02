<?php
require_once __DIR__ . '/../config/auth.php';
requireLogin();

$user = getUserInfo();
$userInitials = getInitials($user['full_name']);
$isAdmin = ($user['role'] === 'admin');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/logger.php';

$database = new Database();
$conn = $database->getConnection();

require_once __DIR__ . '/includes/page_tracker.php';

$error = '';
$success = '';

// Handle actions
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Change Password
    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match';
        } else {
            // Verify current password
            $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = :id");
            $stmt->bindParam(':id', $user['id']);
            $stmt->execute();
            $userData = $stmt->fetch();
            
            if ($userData && $userData['password_hash'] === $currentPassword) {
                $updateStmt = $conn->prepare("UPDATE users SET password_hash = :password WHERE id = :id");
                $updateStmt->bindParam(':password', $newPassword);
                $updateStmt->bindParam(':id', $user['id']);
                if ($updateStmt->execute()) {
                    logActivity($conn, 'CHANGE_PASSWORD', 'users', $user['id'], "User changed their password");
                    $success = 'Password updated successfully';
                } else {
                    $error = 'Failed to update password';
                }
            } else {
                $error = 'Incorrect current password';
            }
        }
    }
    
    // 2. Add New User (Admin Only)
    if (isset($_POST['add_user']) && $isAdmin) {
        $newUsername = $_POST['username'] ?? '';
        $newFullName = $_POST['full_name'] ?? '';
        $newEmail = $_POST['email'] ?? '';
        $newPassword = $_POST['password'] ?? '';
        $newRole = $_POST['role'] ?? 'staff';
        
        if ($newUsername && $newPassword) {
            $stmt = $conn->prepare("INSERT INTO users (username, password_hash, full_name, email, role) VALUES (:username, :password, :full_name, :email, :role)");
            $stmt->bindParam(':username', $newUsername);
            $stmt->bindParam(':password', $newPassword);
            $stmt->bindParam(':full_name', $newFullName);
            $stmt->bindParam(':email', $newEmail);
            $stmt->bindParam(':role', $newRole);
            
            if ($stmt->execute()) {
                $newUserId = $conn->lastInsertId();
                logActivity($conn, 'ADD_USER', 'users', $newUserId, "Admin added new user: $newUsername ($newRole)");
                $success = 'User account created successfully';
            } else {
                $error = 'Failed to add user. Username might already exist.';
            }
        }
    }

    // 3. Delete User (Admin Only)
    if (isset($_POST['delete_user']) && $isAdmin) {
        $userIdToDelete = $_POST['user_id'] ?? '';
        if ($userIdToDelete == $user['id']) {
            $error = "You cannot delete your own account";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = :id");
            $stmt->bindParam(':id', $userIdToDelete);
            if ($stmt->execute()) {
                logActivity($conn, 'DELETE_USER', 'users', $userIdToDelete, "Admin deleted user ID: $userIdToDelete");
                $success = 'User account removed successfully';
            } else {
                $error = 'Failed to delete user';
            }
        }
    }

    // 4. Admin edits a user account
    if (isset($_POST['edit_user']) && $isAdmin) {
        $editId       = intval($_POST['edit_user_id'] ?? 0);
        $editFullName = trim($_POST['edit_full_name'] ?? '');
        $editEmail    = trim($_POST['edit_email'] ?? '');
        $editRole     = $_POST['edit_role'] ?? 'staff';
        $editPassword = trim($_POST['edit_password'] ?? '');
        $editUsername = trim($_POST['edit_username'] ?? '');

        if ($editId) {
            $oldStmt = $conn->prepare("SELECT full_name, username FROM users WHERE id = :id");
            $oldStmt->execute([':id' => $editId]);
            $oldUser = $oldStmt->fetch();

            // Check username uniqueness if changed
            if ($editUsername && $editUsername !== $oldUser['username']) {
                $chk = $conn->prepare("SELECT id FROM users WHERE username = :u AND id != :id");
                $chk->execute([':u' => $editUsername, ':id' => $editId]);
                if ($chk->fetch()) {
                    $error = "Username \"$editUsername\" is already taken.";
                    goto skip_edit;
                }
            }

            $newUsername = $editUsername ?: $oldUser['username'];

            if ($editPassword !== '') {
                $upd = $conn->prepare("UPDATE users SET full_name=:fn, email=:em, role=:ro, username=:un, password_hash=:pw, updated_at=CURRENT_TIMESTAMP WHERE id=:id");
                $upd->execute([':fn'=>$editFullName,':em'=>$editEmail,':ro'=>$editRole,':un'=>$newUsername,':pw'=>$editPassword,':id'=>$editId]);
                logActivity($conn, 'UPDATE_USER', 'users', $editId, "Admin updated account & reset password for " . $oldUser['username']);
            } else {
                $upd = $conn->prepare("UPDATE users SET full_name=:fn, email=:em, role=:ro, username=:un, updated_at=CURRENT_TIMESTAMP WHERE id=:id");
                $upd->execute([':fn'=>$editFullName,':em'=>$editEmail,':ro'=>$editRole,':un'=>$newUsername,':id'=>$editId]);
                logActivity($conn, 'UPDATE_USER', 'users', $editId, "Admin updated account info for " . $oldUser['username']);
            }
            $success = 'Account updated successfully';
        }
        skip_edit:;
    }

    // 5. Staff submits a password reset request
    if (isset($_POST['request_password_reset']) && !$isAdmin) {
        try {
            $conn->exec("CREATE TABLE IF NOT EXISTS password_reset_requests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                status VARCHAR(20) DEFAULT 'pending',
                requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                resolved_at DATETIME,
                new_password VARCHAR(255),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )");
            // Only one pending request at a time
            $existing = $conn->prepare("SELECT id FROM password_reset_requests WHERE user_id=:uid AND status='pending'");
            $existing->execute([':uid' => $user['id']]);
            if ($existing->fetch()) {
                $error = 'You already have a pending reset request. Please wait for admin approval.';
            } else {
                $ins = $conn->prepare("INSERT INTO password_reset_requests (user_id) VALUES (:uid)");
                $ins->execute([':uid' => $user['id']]);
                logActivity($conn, 'PASSWORD_RESET_REQUEST', 'users', $user['id'], $user['username'] . " requested a password reset");
                $success = 'Password reset request sent to admin.';
            }
        } catch (PDOException $e) {
            $error = 'Failed to submit request.';
        }
    }

    // 6. Admin approves a reset request (sets new password)
    if (isset($_POST['approve_reset']) && $isAdmin) {
        $reqId      = intval($_POST['req_id'] ?? 0);
        $newPass    = trim($_POST['approved_password'] ?? '');
        $targetUid  = intval($_POST['req_user_id'] ?? 0);
        if ($reqId && $newPass && $targetUid) {
            $conn->prepare("UPDATE users SET password_hash=:pw, updated_at=CURRENT_TIMESTAMP WHERE id=:id")
                 ->execute([':pw'=>$newPass, ':id'=>$targetUid]);
            $conn->prepare("UPDATE password_reset_requests SET status='approved', resolved_at=CURRENT_TIMESTAMP WHERE id=:id")
                 ->execute([':id'=>$reqId]);
            $nameRow = $conn->prepare("SELECT username FROM users WHERE id=:id");
            $nameRow->execute([':id'=>$targetUid]);
            $uname = $nameRow->fetchColumn() ?: "ID $targetUid";
            logActivity($conn, 'CHANGE_PASSWORD', 'users', $targetUid, "Admin approved password reset for $uname");
            $success = 'Password reset approved and applied.';
        }
    }

    // 7. Admin denies a reset request
    if (isset($_POST['deny_reset']) && $isAdmin) {
        $reqId = intval($_POST['req_id'] ?? 0);
        if ($reqId) {
            $conn->prepare("UPDATE password_reset_requests SET status='denied', resolved_at=CURRENT_TIMESTAMP WHERE id=:id")
                 ->execute([':id'=>$reqId]);
            $success = 'Reset request denied.';
        }
    }
}

// 4. Database Export (Admin Only)
if ($action === 'export_db' && $isAdmin) {
    $dbPath = __DIR__ . '/../database/peaceplot.db';
    if (file_exists($dbPath)) {
        logActivity($conn, 'EXPORT_DB', 'database', null, 'Admin exported a full database backup');
        header('Content-Description: File Transfer');
        header('Content-Type: application/x-sqlite3');
        header('Content-Disposition: attachment; filename="peaceplot_backup_' . date('Y-m-d_His') . '.db"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($dbPath));
        readfile($dbPath);
        exit;
    } else {
        $error = 'Database file not found';
    }
}

// Fetch all users for Admin tab
$allUsers = [];
if ($conn && $isAdmin) {
    $stmt = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
    $allUsers = $stmt->fetchAll();
}

// Fetch pending password reset requests (admin)
$pendingResets = [];
if ($conn && $isAdmin) {
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS password_reset_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            resolved_at DATETIME,
            new_password VARCHAR(255),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        $rStmt = $conn->query("SELECT r.*, u.full_name, u.username FROM password_reset_requests r JOIN users u ON r.user_id=u.id WHERE r.status='pending' ORDER BY r.requested_at DESC");
        $pendingResets = $rStmt->fetchAll();
    } catch (PDOException $e) { /* table may not exist yet */ }
}

// Check if current staff has a pending request
$hasPendingRequest = false;
if ($conn && !$isAdmin) {
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS password_reset_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            resolved_at DATETIME,
            new_password VARCHAR(255),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        $chk = $conn->prepare("SELECT id FROM password_reset_requests WHERE user_id=:uid AND status='pending'");
        $chk->execute([':uid' => $user['id']]);
        $hasPendingRequest = (bool)$chk->fetch();
    } catch (PDOException $e) { /* ignore */ }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PeacePlot Admin - System Settings</title>
  <link rel="stylesheet" href="../assets/css/styles.css" />
  <style>
    :root {
        --settings-bg: #f1f5f9;
        --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        --input-focus: #3b82f6;
        --tab-active: #2f6df6;
        --tab-inactive: #64748b;
        --danger: #ef4444;
        --success: #10b981;
    }

    .settings-container {
        max-width: 1000px;
        margin: 0 auto;
        padding: 20px;
    }

    .modern-card {
        background: white;
        border-radius: 16px;
        box-shadow: var(--card-shadow);
        overflow: hidden;
        border: 1px solid #e2e8f0;
    }

    .settings-header {
        padding: 24px 32px;
        border-bottom: 1px solid #f1f5f9;
    }

    .settings-title {
        font-size: 22px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 20px;
    }

    .tabs-nav {
        display: flex;
        gap: 24px;
        margin-bottom: -1px;
    }

    .tab-link {
        padding: 12px 0;
        font-size: 14px;
        font-weight: 600;
        color: var(--tab-inactive);
        text-decoration: none;
        position: relative;
        transition: all 0.2s;
        cursor: pointer;
        background: none;
        border: none;
        outline: none;
    }

    .tab-link:hover {
        color: #1e293b;
    }

    .tab-link.active {
        color: var(--tab-active);
    }

    .tab-link.active::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 0;
        right: 0;
        height: 2px;
        background: var(--tab-active);
    }

    /* Content Area */
    .settings-content {
        padding: 32px;
    }

    .tab-panel {
        display: none;
        animation: fadeIn 0.3s ease;
    }

    .tab-panel.active {
        display: block;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(5px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .form-section {
        margin-bottom: 32px;
    }

    .section-label {
        font-size: 13px;
        font-weight: 600;
        color: #64748b;
        margin-bottom: 12px;
        display: block;
    }

    .modern-input-group {
        margin-bottom: 20px;
    }

    .modern-input {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid #d1d5db;
        border-radius: 10px;
        font-size: 14px;
        color: #1e293b;
        background: #fcfdfe;
        transition: all 0.2s;
        outline: none;
    }

    .modern-input:focus {
        border-color: var(--input-focus);
        background: white;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .modern-input:disabled {
        background: #f8fafc;
        color: #94a3b8;
        cursor: not-allowed;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    /* Button Styling */
    .modern-btn {
        padding: 10px 20px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-primary-modern {
        background: #2f6df6;
        color: white;
    }

    .btn-primary-modern:hover {
        background: #1e4fd6;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(47, 109, 246, 0.2);
    }

    .btn-danger-modern {
        background: #fee2e2;
        color: #ef4444;
    }

    .btn-danger-modern:hover {
        background: #fecaca;
        color: #dc2626;
    }

    .btn-secondary-modern {
        background: #f1f5f9;
        color: #475569;
    }

    .btn-secondary-modern:hover {
        background: #e2e8f0;
    }

    .team-grid {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .user-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 16px;
        background: #f8fafc;
        border-radius: 12px;
        border: 1px solid #f1f5f9;
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .user-avatar-sm {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: var(--primary);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 14px;
    }

    .user-details h4 {
        margin: 0;
        font-size: 14px;
        color: #1e293b;
    }

    .user-details p {
        margin: 0;
        font-size: 12px;
        color: #64748b;
    }

    .badge-modern {
        padding: 4px 10px;
        border-radius: 8px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .modern-dialog {
        background: #1c2128;
        color: white;
        border-radius: 12px;
        padding: 24px;
        max-width: 380px;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 1000;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        display: none;
    }

    .dialog-title {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 12px;
    }

    .dialog-text {
        color: #94a3b8;
        font-size: 14px;
        line-height: 1.5;
        margin-bottom: 24px;
    }

    .dialog-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    /* Sidebar Footer Clickable State */
    .sidebar-footer .user.active {
        background: rgba(255,255,255,0.1);
    }

    /* Alerts */
    .status-alert {
        padding: 14px 18px;
        border-radius: 10px;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
        font-weight: 500;
    }

    .status-alert.error { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
    .status-alert.success { background: #ecfdf5; color: #047857; border: 1px solid #d1fae5; }
    .role-admin { background: #dbeafe; color: #1d4ed8; }
    .role-staff { background: #f1f5f9; color: #475569; }
    .user-row:hover { background: #f1f5f9; border-color: #e2e8f0; }
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
        <div class="user active" onclick="window.location.href='settings.php'" style="cursor:pointer;">
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
      <div class="settings-container">
        <div class="modern-card">
          <div class="settings-header">
            <h1 class="settings-title">System Settings</h1>
            <div class="tabs-nav">
              <button class="tab-link active" onclick="switchTab('profile')">My Profile</button>
              <?php if ($isAdmin): ?>
                <button class="tab-link" onclick="switchTab('admin')">Admin Management</button>
                <button class="tab-link" onclick="switchTab('database')">Database</button>
              <?php endif; ?>
            </div>
          </div>

          <div class="settings-content">
            <?php if ($error): ?>
              <div class="status-alert error">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?php echo htmlspecialchars($error ?? ''); ?>
              </div>
            <?php endif; ?>

            <?php if ($success): ?>
              <div class="status-alert success">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <?php echo htmlspecialchars($success ?? ''); ?>
              </div>
            <?php endif; ?>

            <!-- Profile Tab -->
            <div id="profile-tab" class="tab-panel active">
              <div class="form-section">
                <span class="section-label">PERSONAL INFORMATION</span>
                <div class="form-row">
                  <div class="modern-input-group">
                    <label class="section-label">Full Name</label>
                    <input type="text" class="modern-input" value="<?php echo htmlspecialchars($user['full_name']); ?>" disabled>
                  </div>
                  <div class="modern-input-group">
                    <label class="section-label">Email address</label>
                    <input type="email" class="modern-input" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                  </div>
                </div>
              </div>

              <div class="form-section" style="margin-bottom: 0;">
                <span class="section-label">SECURITY</span>
                <?php if (!$isAdmin): ?>
                <p style="font-size:13px; color:#64748b; margin:0 0 18px;">To change your password, submit a reset request and an admin will set a new one for you.</p>
                <?php if ($hasPendingRequest): ?>
                  <div class="status-alert" style="background:#fef9c3; color:#854d0e; border:1px solid #fde68a; margin-bottom:0;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    Your password reset request is pending admin approval.
                  </div>
                <?php else: ?>
                  <form method="POST">
                    <button type="submit" name="request_password_reset" class="modern-btn btn-secondary-modern">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                      Request Password Reset
                    </button>
                  </form>
                <?php endif; ?>
                <?php else: ?>
                <p style="font-size:13px; color:#64748b; margin:0;">Manage your password from the <strong>Admin Management</strong> tab by clicking your account.</p>
                <?php endif; ?>

                <?php if (!$isAdmin): ?>
                <?php endif; ?>
              </div>
            </div>

            <?php if ($isAdmin): ?>
              <!-- Admin Tab -->
              <div id="admin-tab" class="tab-panel">

                <?php if (!empty($pendingResets)): ?>
                  <div style="margin-bottom: 28px;">
                    <span class="section-label">PENDING PASSWORD RESET REQUESTS</span>
                    <div style="display:flex; flex-direction:column; gap:10px; margin-top:8px;">
                      <?php foreach ($pendingResets as $req): ?>
                        <div style="display:flex; align-items:center; justify-content:space-between; background:#fef9c3; border:1px solid #fde68a; border-radius:12px; padding:14px 18px;">
                          <div>
                            <div style="font-size:14px; font-weight:600; color:#1e293b;"><?php echo htmlspecialchars($req['full_name']); ?> <span style="color:#64748b; font-weight:400;">(@<?php echo htmlspecialchars($req['username']); ?>)</span></div>
                            <div style="font-size:12px; color:#92400e; margin-top:2px;">Requested <?php echo date('M d, Y h:i A', strtotime($req['requested_at'])); ?></div>
                          </div>
                          <div style="display:flex; gap:8px; align-items:center;">
                            <button class="modern-btn btn-primary-modern" style="padding:8px 14px; font-size:13px;" onclick="showApproveReset(<?php echo $req['id']; ?>, <?php echo $req['user_id']; ?>, '<?php echo htmlspecialchars($req['full_name']); ?>')">
                              Approve & Set Password
                            </button>
                            <form method="POST" style="margin:0;">
                              <input type="hidden" name="req_id" value="<?php echo $req['id']; ?>">
                              <button type="submit" name="deny_reset" class="modern-btn btn-danger-modern" style="padding:8px 14px; font-size:13px;">Deny</button>
                            </form>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                  <span class="section-label" style="margin-bottom: 0;">ADMINISTRATORS & STAFF</span>
                  <button class="modern-btn btn-primary-modern" onclick="showAddUserModal()">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="14" y2="12"/></svg>
                    Add User
                  </button>
                </div>

                <div class="team-grid">
                  <?php foreach ($allUsers as $u): ?>
                    <div class="user-row" style="cursor:pointer;" onclick="showEditUser(<?php echo htmlspecialchars(json_encode($u)); ?>)">
                      <div class="user-info">
                        <div class="user-avatar-sm" style="background: <?php echo $u['role']==='admin' ? '#2f6df6' : '#64748b'; ?>;"><?php echo getInitials($u['full_name']); ?></div>
                        <div class="user-details">
                          <h4><?php echo htmlspecialchars($u['full_name']); ?></h4>
                          <p><?php echo htmlspecialchars($u['email'] ?: $u['username']); ?></p>
                        </div>
                      </div>
                      <div style="display: flex; align-items: center; gap: 16px;">
                        <span class="badge-modern role-<?php echo $u['role']; ?>"><?php echo ucfirst($u['role']); ?></span>
                        <div style="text-align: right; min-width: 110px;">
                          <div style="font-size: 12px; color: #64748b;">Last Login</div>
                          <div style="font-size: 13px; font-weight: 500; color: #1e293b;"><?php echo $u['last_login'] ? date('M d, Y', strtotime($u['last_login'])) : 'Never'; ?></div>
                        </div>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>

              <!-- Database Tab -->
              <div id="database-tab" class="tab-panel">
                <div class="form-section">
                  <span class="section-label">DATABASE MAINTENANCE</span>
                  <div style="background: #f8fafc; border-radius: 16px; padding: 30px; border: 1px dashed #cbd5e1; text-align: center;">
                    <div style="width: 60px; height: 60px; background: #e0f2fe; color: #0284c7; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                      <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                    </div>
                    <h3 style="margin: 0 0 10px; font-size: 18px; color: #1e293b;">Full System Backup</h3>
                    <p style="color: #64748b; font-size: 14px; max-width: 400px; margin: 0 auto 25px;">
                      Download the complete PeacePlot database file containing all lot records, burial records, and system history.
                    </p>
                    <a href="settings.php?action=export_db" class="modern-btn btn-primary-modern" style="text-decoration: none;">
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                      Download Database (.db)
                    </a>
                  </div>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Delete User Dialog -->
  <div id="delete-dialog" class="modern-dialog">
    <div style="width: 48px; height: 48px; background: #2d333b; color: white; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-bottom: 20px;">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
    </div>
    <div class="dialog-title">Are you sure?</div>
    <div class="dialog-text">
      You are going to permanently delete <strong id="delete-user-name" style="color: white;"></strong>'s account. This action cannot be undone.
    </div>
    <div class="dialog-actions">
      <button class="modern-btn btn-secondary-modern" style="background: #2d333b; color: #94a3b8;" onclick="closeDialog('delete-dialog')">Cancel</button>
      <form method="POST" style="margin: 0;">
        <input type="hidden" name="user_id" id="delete-user-id">
        <button type="submit" name="delete_user" class="modern-btn btn-primary-modern" style="background: #ef4444;">Delete Account</button>
      </form>
    </div>
  </div>

  <!-- Edit User Modal -->
  <div id="edit-user-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; z-index:1000; align-items:center; justify-content:center;">
    <div class="modern-card" style="width:100%; max-width:520px; position:relative; z-index:1001;">
      <div class="settings-header" style="padding:22px 28px; display:flex; justify-content:space-between; align-items:center;">
        <h2 style="font-size:18px; margin:0;">Account Details</h2>
        <button onclick="closeModal('edit-user-modal')" style="background:none; border:none; cursor:pointer; color:#64748b;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <form method="POST" style="padding:28px;">
        <input type="hidden" name="edit_user_id" id="edit_user_id">

        <!-- Account info display -->
        <div style="display:flex; align-items:center; gap:14px; background:#f8fafc; border-radius:12px; padding:16px; margin-bottom:22px;">
          <div id="edit_avatar" style="width:48px; height:48px; border-radius:12px; background:#2f6df6; color:white; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:18px;"></div>
          <div>
            <div id="edit_username_display" style="font-size:15px; font-weight:700; color:#1e293b;"></div>
            <div id="edit_created_display" style="font-size:12px; color:#64748b;"></div>
          </div>
        </div>

        <div class="form-row">
          <div class="modern-input-group">
            <label class="section-label">Full Name</label>
            <input type="text" name="edit_full_name" id="edit_full_name" class="modern-input" required>
          </div>
          <div class="modern-input-group">
            <label class="section-label">Email</label>
            <input type="email" name="edit_email" id="edit_email" class="modern-input">
          </div>
        </div>

        <div class="form-row">
          <div class="modern-input-group">
            <label class="section-label">Role</label>
            <select name="edit_role" id="edit_role" class="modern-input">
              <option value="staff">Staff Member</option>
              <option value="admin">Administrator</option>
            </select>
          </div>
          <div class="modern-input-group">
            <label class="section-label">Username</label>
            <input type="text" name="edit_username" id="edit_username_field" class="modern-input">
          </div>
        </div>

        <!-- Password section -->
        <div style="border-top:1px solid #f1f5f9; padding-top:18px; margin-top:4px;">
          <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px;">
            <span class="section-label" style="margin:0;">PASSWORD</span>
            <button type="button" onclick="togglePasswordField()" id="toggle_pw_btn" style="font-size:12px; color:#2f6df6; background:none; border:none; cursor:pointer; font-weight:600;">Change Password</button>
          </div>
          <div id="password_field_wrap" style="display:none;">
            <div class="modern-input-group" style="margin-bottom:8px;">
              <label class="section-label">Current Password</label>
              <div style="position:relative;">
                <input type="password" id="edit_pw_display" class="modern-input" disabled style="font-family:monospace; letter-spacing:2px;">
                <button type="button" onclick="toggleShowPassword()" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:#64748b;">
                  <svg id="eye_icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
            </div>
            <div class="modern-input-group" style="margin-bottom:0;">
              <label class="section-label">Set New Password</label>
              <input type="password" name="edit_password" id="edit_password" class="modern-input" placeholder="Leave blank to keep current">
            </div>
          </div>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:22px;">
          <div id="delete_btn_wrap"></div>
          <div style="display:flex; gap:10px;">
            <button type="button" class="modern-btn btn-secondary-modern" onclick="closeModal('edit-user-modal')">Cancel</button>
            <button type="submit" name="edit_user" class="modern-btn btn-primary-modern">Save Changes</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Approve Reset Modal -->
  <div id="approve-reset-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; z-index:1000; align-items:center; justify-content:center;">
    <div class="modern-card" style="width:100%; max-width:420px; position:relative; z-index:1001;">
      <div class="settings-header" style="padding:22px 28px;">
        <h2 style="font-size:18px; margin:0;">Approve Password Reset</h2>
      </div>
      <form method="POST" style="padding:28px;">
        <input type="hidden" name="req_id" id="approve_req_id">
        <input type="hidden" name="req_user_id" id="approve_user_id">
        <p style="font-size:14px; color:#475569; margin:0 0 18px;">Set a new password for <strong id="approve_user_name"></strong>.</p>
        <div class="modern-input-group">
          <label class="section-label">New Password</label>
          <input type="text" name="approved_password" class="modern-input" placeholder="Enter new password" required>
        </div>
        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:10px;">
          <button type="button" class="modern-btn btn-secondary-modern" onclick="closeModal('approve-reset-modal')">Cancel</button>
          <button type="submit" name="approve_reset" class="modern-btn btn-primary-modern">Approve & Apply</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Add User Modal -->
  <div id="add-user-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 999; align-items: center; justify-content: center;">
    <div class="modern-card" style="width: 100%; max-width: 500px; position:relative; z-index:1001;">
      <div class="settings-header" style="padding: 25px 35px;">
        <h2 style="font-size: 20px; margin: 0;">Create New User Account</h2>
      </div>
      <form method="POST" style="padding: 35px;">
        <div class="modern-input-group">
          <label class="section-label">Full Name</label>
          <input type="text" name="full_name" class="modern-input" required>
        </div>
        <div class="modern-input-group">
          <label class="section-label">Username</label>
          <input type="text" name="username" class="modern-input" required>
        </div>
        <div class="modern-input-group">
          <label class="section-label">Email address</label>
          <input type="email" name="email" class="modern-input">
        </div>
        <div class="form-row">
          <div class="modern-input-group">
            <label class="section-label">Initial password</label>
            <input type="password" name="password" class="modern-input" required>
          </div>
          <div class="modern-input-group">
            <label class="section-label">Role</label>
            <select name="role" class="modern-input">
              <option value="staff">Staff Member</option>
              <option value="admin">Administrator</option>
            </select>
          </div>
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 10px;">
          <button type="button" class="modern-btn btn-secondary-modern" onclick="closeModal('add-user-modal')">Cancel</button>
          <button type="submit" name="add_user" class="modern-btn btn-primary-modern">Create Account</button>
        </div>
      </form>
    </div>
  </div>

  <div id="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.4); z-index: 998;" onclick="closeAllModals()"></div>

  <script>
    const currentUserId = <?php echo $user['id']; ?>;

    function switchTab(tabId) {
        document.querySelectorAll('.tab-link').forEach(link => link.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.remove('active'));
        document.getElementById(tabId + '-tab').classList.add('active');
        // match button by onclick attribute
        document.querySelectorAll('.tab-link').forEach(link => {
            if (link.getAttribute('onclick') && link.getAttribute('onclick').includes("'" + tabId + "'")) {
                link.classList.add('active');
            }
        });
        const url = new URL(window.location);
        url.searchParams.set('tab', tabId);
        window.history.pushState({}, '', url);
    }

    window.onload = function() {
        const tab = new URLSearchParams(window.location.search).get('tab');
        if (tab && document.getElementById(tab + '-tab')) switchTab(tab);
    };

    function showAddUserModal() {
        document.getElementById('add-user-modal').style.display = 'flex';
        document.getElementById('modal-overlay').style.display = 'block';
    }

    function showEditUser(u) {
        document.getElementById('edit_user_id').value = u.id;
        document.getElementById('edit_full_name').value = u.full_name || '';
        document.getElementById('edit_email').value = u.email || '';
        document.getElementById('edit_role').value = u.role || 'staff';
        document.getElementById('edit_username_field').value = u.username || '';
        document.getElementById('edit_username_display').textContent = '@' + (u.username || '');
        document.getElementById('edit_created_display').textContent = 'Joined ' + (u.created_at ? u.created_at.substring(0,10) : 'N/A');
        // Avatar initials
        const parts = (u.full_name || '').split(' ');
        const initials = parts.length >= 2 ? parts[0][0] + parts[1][0] : (u.full_name || '?').substring(0,2);
        document.getElementById('edit_avatar').textContent = initials.toUpperCase();
        document.getElementById('edit_avatar').style.background = u.role === 'admin' ? '#2f6df6' : '#64748b';
        // Store password for reveal
        document.getElementById('edit_pw_display').value = u.password_hash || '';
        // Delete button — hide for own account
        const wrap = document.getElementById('delete_btn_wrap');
        if (u.id != currentUserId) {
            wrap.innerHTML = `<button type="button" class="modern-btn btn-danger-modern" onclick="confirmDeleteUser(${u.id}, '${(u.full_name||'').replace(/'/g,"\\'")}')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                Delete Account
            </button>`;
        } else {
            wrap.innerHTML = '';
        }
        // Reset password field
        document.getElementById('password_field_wrap').style.display = 'none';
        document.getElementById('toggle_pw_btn').textContent = 'Change Password';
        document.getElementById('edit-user-modal').style.display = 'flex';
        document.getElementById('modal-overlay').style.display = 'block';
    }

    function togglePasswordField() {
        const wrap = document.getElementById('password_field_wrap');
        const btn = document.getElementById('toggle_pw_btn');
        const visible = wrap.style.display !== 'none';
        wrap.style.display = visible ? 'none' : 'block';
        btn.textContent = visible ? 'Change Password' : 'Hide';
    }

    let pwVisible = false;
    function toggleShowPassword() {
        pwVisible = !pwVisible;
        const input = document.getElementById('edit_pw_display');
        input.type = pwVisible ? 'text' : 'password';
        document.getElementById('eye_icon').innerHTML = pwVisible
            ? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>'
            : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    }

    function confirmDeleteUser(id, name) {
        document.getElementById('delete-user-id').value = id;
        document.getElementById('delete-user-name').textContent = name;
        closeModal('edit-user-modal');
        document.getElementById('delete-dialog').style.display = 'block';
        document.getElementById('modal-overlay').style.display = 'block';
    }

    function showApproveReset(reqId, userId, name) {
        document.getElementById('approve_req_id').value = reqId;
        document.getElementById('approve_user_id').value = userId;
        document.getElementById('approve_user_name').textContent = name;
        document.getElementById('approve-reset-modal').style.display = 'flex';
        document.getElementById('modal-overlay').style.display = 'block';
    }

    function closeDialog(id) {
        document.getElementById(id).style.display = 'none';
        document.getElementById('modal-overlay').style.display = 'none';
    }

    function closeModal(id) {
        document.getElementById(id).style.display = 'none';
        document.getElementById('modal-overlay').style.display = 'none';
    }

    function closeAllModals() {
        ['add-user-modal','edit-user-modal','delete-dialog','approve-reset-modal'].forEach(id => {
            document.getElementById(id).style.display = 'none';
        });
        document.getElementById('modal-overlay').style.display = 'none';
    }
  </script>
</body>
</html>
