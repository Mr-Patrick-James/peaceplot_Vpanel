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
$stats = [
    'total' => 0,
    'vacant' => 0,
    'occupied' => 0
];

if ($conn) {
    try {
        // Fetch stats
        $stats['total'] = $conn->query("SELECT COUNT(*) FROM cemetery_lots")->fetchColumn();
        $stats['vacant'] = $conn->query("SELECT COUNT(*) FROM cemetery_lots WHERE status = 'Vacant'")->fetchColumn();
        $stats['occupied'] = $conn->query("SELECT COUNT(*) FROM cemetery_lots WHERE status = 'Occupied'")->fetchColumn();

        // Fetch sections with their block names for filtering and dropdowns
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

        $stmt = $conn->query("
            SELECT cl.*, s.name as section_name, b.name as block_name,
                   (SELECT GROUP_CONCAT(full_name, ', ') 
                    FROM (SELECT full_name FROM deceased_records WHERE lot_id = cl.id AND is_archived = 0 ORDER BY created_at DESC, id DESC)
                   ) as deceased_name 
            FROM cemetery_lots cl 
            LEFT JOIN sections s ON cl.section_id = s.id
            LEFT JOIN blocks b ON s.block_id = b.id
            ORDER BY cl.lot_number
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
  <title>PeacePlot Admin - Cemetery Lot Management</title>
  <link rel="stylesheet" href="../assets/css/styles.css" />
  <style>
    /* Specific styles for the new UI based on the image */
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
    .breadcrumbs a {
      color: #94a3b8;
      text-decoration: none;
    }
    .breadcrumbs .current {
      color: #1e293b;
      font-weight: 600;
    }
    .header-actions {
      display: flex;
      gap: 12px;
    }
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
    }
    .btn-yellow {
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
    .btn-yellow:hover {
      background: #2563eb;
      transform: translateY(-1px);
    }
    .btn-assign {
      background: #eff6ff;
      color: #3b82f6;
      border: 1px solid #dbeafe;
    }
    .btn-assign:hover {
      background: #dbeafe;
      border-color: #bfdbfe;
    }

    .stats-row {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 24px;
      margin-bottom: 32px;
    }
    .stat-box {
      background: #fff;
      padding: 24px;
      border-radius: 16px;
      border-left: 5px solid #0e1f35; /* Sidebar background color */
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
    .stat-info .stat-label {
      font-size: 13px;
      font-weight: 600;
      color: #94a3b8;
      margin-bottom: 4px;
    }
    .stat-info .stat-number {
      font-size: 28px;
      font-weight: 700;
      color: #1e293b;
      line-height: 1;
    }
    .stat-info .stat-sub {
      font-size: 12px;
      margin-top: 8px;
    }
    .stat-sub.growth { color: #10b981; }
    .stat-sub.percent { color: #64748b; }

    .content-section {
      background: #fff;
      border-radius: 16px;
      padding: 0;
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
    .content-title-wrap .title {
      font-size: 18px;
      font-weight: 700;
      color: #1e293b;
      margin: 0 0 4px 0;
    }
    .content-title-wrap .subtitle {
      font-size: 13px;
      color: #94a3b8;
      margin: 0;
    }
    .filter-controls {
      display: flex;
      gap: 12px;
      align-items: center;
    }
    .search-wrapper {
      position: relative;
    }
    .search-wrapper svg {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #94a3b8;
    }
    .search-wrapper input {
      padding: 10px 16px 10px 40px;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      font-size: 14px;
      width: 280px;
      outline: none;
      transition: all 0.2s;
    }
    .search-wrapper input:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    .select-styled {
      padding: 10px 16px;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      font-size: 14px;
      background: #fff;
      color: #1e293b;
      outline: none;
      cursor: pointer;
    }
    .icon-btn-outline {
      width: 40px;
      height: 40px;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #64748b;
      cursor: pointer;
      background: #fff;
    }

    /* Advanced Filter Control Styles */
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
    .btn-save-view { font-size: 13px; color: #3b82f6; text-decoration: none; font-weight: 600; }
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
    .category-toggle svg { 
      width: 16px; height: 16px; color: #94a3b8; 
      transition: transform 0.2s;
    }
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
    .filter-option input[type="checkbox"] {
      width: 16px;
      height: 16px;
      border-radius: 4px;
      border: 2px solid #cbd5e1;
      cursor: pointer;
    }
    
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
    .filter-chip .remove {
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0.7;
    }
    .filter-chip .remove:hover { opacity: 1; }

    .table thead th {
      background: #f8fafc;
      color: #94a3b8;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      padding: 16px 32px;
    }
    .table tbody td {
      padding: 16px 32px;
      font-size: 14px;
      color: #475569;
      vertical-align: middle;
    }
    .lot-name-cell {
      display: flex;
      align-items: center;
      gap: 16px;
    }
    .lot-icon {
      width: 36px;
      height: 36px;
      background: #3b82f6;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
    }
    .lot-name-info .name {
      font-weight: 600;
      color: #1e293b;
      display: block;
    }
    .lot-name-info .sub {
      font-size: 12px;
      color: #94a3b8;
    }
    .status-badge {
      padding: 4px 12px;
      border-radius: 6px;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
    }
    .status-badge.active { background: #dcfce7; color: #10b981; }
    .status-badge.vacant { background: #eff6ff; color: #3b82f6; }
    .status-badge.maintenance { background: #f1f5f9; color: #64748b; }

    /* Map Button Unassigned Styling */
    .btn-action.btn-map.unassigned {
      background: #f8fafc;
      color: #94a3b8;
      border-color: #e2e8f0;
      opacity: 0.7;
    }
    .btn-action.btn-map.unassigned:hover {
      background: #f1f5f9;
      color: #64748b;
      opacity: 1;
    }

    .pagination-footer {
      padding: 20px 32px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-top: 1px solid #f1f5f9;
    }
    .pagination-text {
      font-size: 13px;
      color: #94a3b8;
    }
    .pagination-controls {
      display: flex;
      gap: 8px;
    }
    .page-num {
      width: 32px;
      height: 32px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
    }
    .page-num.active {
      background: #3b82f6;
      color: #fff;
    }
    .page-arrow {
      width: 32px;
      height: 32px;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      color: #94a3b8;
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

    /* Modern Modal Styles */
    .modal {
      display: none;
      position: fixed;
      z-index: 5000;
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

    .modal-content {
      background: white;
      border-radius: 20px;
      width: 100%;
      max-width: 650px;
      max-height: 90vh;
      overflow-y: auto;
      box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
      animation: modalSlideUp 0.3s ease-out;
    }

    @keyframes modalSlideUp {
      from { transform: translateY(20px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }

    .modal-header {
      padding: 24px 32px;
      border-bottom: 1px solid #f1f5f9;
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: sticky;
      top: 0;
      background: white;
      z-index: 10;
    }

    .modal-header h2 {
      font-size: 20px;
      font-weight: 700;
      color: #1e293b;
      margin: 0;
    }

    .modal-close {
      background: #f1f5f9;
      border: none;
      width: 32px;
      height: 32px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #64748b;
      cursor: pointer;
      transition: all 0.2s;
    }

    .modal-close:hover {
      background: #e2e8f0;
      color: #1e293b;
    }

    .modal-body {
      padding: 32px;
    }

    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      margin-bottom: 32px;
    }

    .form-group {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .form-group.full-width {
      grid-column: 1 / -1;
    }

    .form-group label {
      font-size: 13px;
      font-weight: 600;
      color: #64748b;
    }

    .form-group input, .form-group select, .form-group textarea {
      padding: 12px 16px;
      border: 1.5px solid #e2e8f0;
      border-radius: 10px;
      font-size: 14px;
      color: #1e293b;
      transition: all 0.2s;
    }

    .form-group input:focus, .form-group select:focus {
      border-color: #3b82f6;
      box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
      outline: none;
    }

    /* Burial Info Section */
    .burial-info-section {
      margin-top: 24px;
      padding-top: 24px;
      border-top: 1px solid #f1f5f9;
    }

    .section-title {
      font-size: 16px;
      font-weight: 700;
      color: #1e293b;
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .layer-card {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 16px;
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .layer-number {
      width: 32px;
      height: 32px;
      background: #3b82f6;
      color: white;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 14px;
      flex-shrink: 0;
    }

    .layer-details {
      flex-grow: 1;
    }

    .layer-name {
      font-weight: 600;
      color: #1e293b;
      font-size: 14px;
      margin-bottom: 2px;
    }

    .layer-sub {
      font-size: 12px;
      color: #64748b;
    }

    .layer-status {
      padding: 4px 10px;
      border-radius: 6px;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
    }

    .layer-status.occupied { background: #dcfce7; color: #10b981; }
    .layer-status.vacant { background: #f1f5f9; color: #64748b; }

    .modal-footer {
      padding: 24px 32px;
      border-top: 1px solid #f1f5f9;
      display: flex;
      justify-content: flex-end;
      gap: 12px;
      position: sticky;
      bottom: 0;
      background: white;
      z-index: 10;
    }

    .btn-secondary {
      background: #f1f5f9;
      color: #475569;
      border: none;
      padding: 12px 24px;
      border-radius: 10px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
    }

    .btn-primary {
      background: #3b82f6;
      color: white;
      border: none;
      padding: 12px 24px;
      border-radius: 10px;
      font-weight: 600;
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
      transition: all 0.2s;
    }

    .btn-primary:hover { background: #2563eb; transform: translateY(-1px); }

    /* Burial Detail Modal Specific Styles */
    .burial-detail-card {
      background: #f8fafc;
      border-radius: 16px;
      overflow: hidden;
      border: 1px solid #e2e8f0;
    }

    .burial-detail-header {
      padding: 24px;
      background: white;
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      border-bottom: 1px solid #f1f5f9;
    }

    .burial-name-info h3 {
      font-size: 22px;
      font-weight: 700;
      color: #1e293b;
      margin: 0 0 4px 0;
    }

    .burial-location {
      display: flex;
      align-items: center;
      gap: 6px;
      color: #64748b;
      font-size: 14px;
    }

    .status-pill {
      padding: 6px 14px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      background: #fff7ed;
      color: #f97316;
      border: 1px solid #ffedd5;
    }

    .burial-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 24px;
      padding: 24px;
      background: white;
    }

    .info-item {
      display: flex;
      align-items: flex-start;
      gap: 12px;
    }

    .info-icon {
      width: 36px;
      height: 36px;
      background: #f8fafc;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #64748b;
      flex-shrink: 0;
    }

    .info-content label {
      display: block;
      font-size: 12px;
      color: #94a3b8;
      margin-bottom: 2px;
      font-weight: 500;
    }

    .info-content span {
      font-weight: 600;
      color: #1e293b;
      font-size: 15px;
    }

    .image-section {
      padding: 24px;
      border-top: 1px solid #f1f5f9;
      background: white;
    }

    .image-section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 16px;
    }

    .image-title {
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: 600;
      color: #475569;
      font-size: 14px;
    }

    .image-grid-placeholder {
      background: #f8fafc;
      border: 2px dashed #e2e8f0;
      border-radius: 12px;
      padding: 40px;
      text-align: center;
      color: #94a3b8;
    }
  </style>
</head>
<body>
  <div class="app">
    <!-- Sidebar included as usual -->
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
            <a href="index.php" class="active"><span>Manage Lots</span></a>
            <a href="blocks.php"><span>Manage Blocks</span></a>
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
            <span class="current">Manage Lots</span>
          </div>
          <h1 class="title">Manage Lots</h1>
          <p class="subtitle">Manage all cemetery lots in the institution</p>
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
          <button class="btn-outline" style="display: none;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 8v13H3V8M1 3h22v5H1zM10 12h4"/></svg>
            Archived
          </button>
          <button class="btn-outline">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
            Export
          </button>
          <button class="btn-yellow" data-action="add">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            Add Lot
          </button>
        </div>
      </header>

      <div class="stats-row">
        <div class="stat-box">
          <div class="stat-icon-wrap">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
          </div>
          <div class="stat-info">
            <div class="stat-label">Total Cemetery Lots</div>
            <div class="stat-number"><?php echo $stats['total']; ?></div>
            <div class="stat-sub growth">+0 this month</div>
          </div>
        </div>
        <div class="stat-box">
          <div class="stat-icon-wrap">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          </div>
          <div class="stat-info">
            <div class="stat-label">Vacant</div>
            <div class="stat-number"><?php echo $stats['vacant']; ?></div>
            <div class="stat-sub percent"><?php echo $stats['total'] > 0 ? round(($stats['vacant']/$stats['total'])*100) : 0; ?>%</div>
          </div>
        </div>
        <div class="stat-box">
          <div class="stat-icon-wrap">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </div>
          <div class="stat-info">
            <div class="stat-label">Occupied</div>
            <div class="stat-number"><?php echo $stats['occupied']; ?></div>
            <div class="stat-sub percent"><?php echo $stats['total'] > 0 ? round(($stats['occupied']/$stats['total'])*100) : 0; ?>%</div>
          </div>
        </div>
      </div>

      <section class="content-section">
        <div class="content-header">
          <div class="content-title-wrap">
            <h2 class="title">Cemetery Lot List</h2>
            <p class="subtitle">All cemetery lots and their details</p>
          </div>
          <div class="filter-controls">
            <div class="search-wrapper">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
              <input id="lotSearch" type="text" placeholder="Search lots...">
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
                  <div style="display: flex; gap: 12px; align-items: center;">
                    <a href="#" class="btn-save-view" onclick="clearAllFilters(); return false;" style="color: #ef4444;">Clear all</a>
                    <a href="#" class="btn-save-view">Save view</a>
                  </div>
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
                            <?php echo htmlspecialchars($section['name']); ?>
                          </label>
                        <?php endforeach; ?>
                      </div>
                    </div>
                    
                    <!-- Status Category -->
                    <div class="filter-category">
                      <button class="category-toggle" onclick="toggleCategory(this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        Status
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
                  </div>

                  <div class="popover-column">
                    <!-- Occupancy Category -->
                    <div class="filter-category active">
                      <button class="category-toggle" onclick="toggleCategory(this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                        Occupancy
                      </button>
                      <div class="category-content">
                        <label class="filter-option">
                          <input type="checkbox" name="occupancy" value="Assigned" onchange="updateFilters()"> Assigned
                        </label>
                        <label class="filter-option">
                          <input type="checkbox" name="occupancy" value="Unassigned" onchange="updateFilters()"> Unassigned
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
                        <label class="filter-option">
                          <input type="radio" name="sort_order" value="ASC" onchange="updateFilters()" checked> First to Last (ASC)
                        </label>
                        <label class="filter-option">
                          <input type="radio" name="sort_order" value="DESC" onchange="updateFilters()"> Last to First (DESC)
                        </label>
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

        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th align="left">ID</th>
                <th align="left">Lot Details</th>
                <th align="left">Position</th>
                <th align="left">Occupant</th>
                <th align="left">Status</th>
                <th align="left">Layer Occupancy</th>
                <th align="right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <!-- Data will be loaded via JS -->
              <tr>
                <td colspan="7" style="text-align:center; padding: 60px; color:#94a3b8;">
                  <div class="loading-spinner" style="display: inline-block; width: 24px; height: 24px; border: 2px solid rgba(0,0,0,0.1); border-radius: 50%; border-top-color: #fbbf24; animation: spin 1s ease-in-out infinite;"></div>
                  <div style="margin-top: 12px; font-size: 13px;">Loading data...</div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="pagination-footer">
          <div class="pagination-text">
            Showing <span id="paginationRange">-</span> of <span id="paginationTotal">-</span> lots
          </div>
          <div class="pagination-controls">
            <!-- Pagination buttons will be rendered here -->
          </div>
        </div>
      </section>
    </main>
  </div>

  <style>
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
    /* Simple overrides for existing JS-generated elements */
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
        white-space: nowrap;
      }
     .pagination-btn:disabled {
       opacity: 0.5;
       cursor: not-allowed;
     }
     .pagination-btn.active {
          background: #3b82f6;
          color: #fff;
          border-color: #3b82f6;
        }
    .pagination-btn:hover:not(:disabled) {
      background: #f8fafc;
    }
    .pagination-ellipsis {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 32px;
      height: 32px;
      color: #94a3b8;
      font-size: 13px;
    }
  </style>

  <!-- Confirmation Modal -->
  <div id="confirmModal" class="confirm-modal">
    <div class="confirm-modal-content">
      <div class="confirm-icon">⚠</div>
      <h3 class="confirm-title">Delete Lot?</h3>
      <p id="confirmMessage" class="confirm-message">Are you sure you want to delete this lot? This action cannot be undone.</p>
      <div class="confirm-actions">
        <button class="btn-confirm-cancel" onclick="closeConfirmModal()">Cancel</button>
        <button id="confirmDeleteBtn" class="btn-confirm-delete">Delete</button>
      </div>
    </div>
  </div>

  <script src="../assets/js/app.js"></script>
  <script src="../assets/js/api.js"></script>
  <script src="../assets/js/cemetery-lots.js"></script>
</body>
</html>
