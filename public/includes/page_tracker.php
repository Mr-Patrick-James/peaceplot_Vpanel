<?php
/**
 * Page view tracker — include at the top of any page to log navigation.
 * Requires $conn (PDO) to be available before including.
 * 
 * Usage: require_once __DIR__ . '/includes/page_tracker.php';
 */
if (!isset($conn) || !$conn) return;

require_once __DIR__ . '/../../config/logger.php';

$pageMap = [
    'dashboard.php'      => 'Dashboard',
    'burial-records.php' => 'Burial Records',
    'cemetery-map.php'   => 'Cemetery Map',
    'map-editor.php'     => 'Map Editor',
    'blocks.php'         => 'Manage Blocks',
    'sections.php'       => 'Manage Sections',
    'index.php'          => 'Manage Lots',
    'lot-availability.php' => 'Lot Availability',
    'reports.php'        => 'Reports',
    'history.php'        => 'System History',
    'settings.php'       => 'Settings',
    'search-results.php' => 'Search Results',
];

$currentFile = basename($_SERVER['PHP_SELF']);
$pageName = $pageMap[$currentFile] ?? $currentFile;

logActivity($conn, 'PAGE_VIEW', 'navigation', null, 'Visited page: ' . $pageName);
