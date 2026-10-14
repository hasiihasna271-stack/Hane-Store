<?php
/**
 * HANÉ Admin - Header & Sidebar Component
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin_login();
$currentAdmin = get_current_admin($pdo);
$adminName = $currentAdmin['name'] ?? 'Administrator';
$adminEmail = $currentAdmin['email'] ?? 'admin@hane.lk';
$flashMsg = get_flash();

// Determine active admin menu section
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$adminSection = 'dashboard';
if (strpos($scriptPath, '/products') !== false) $adminSection = 'products';
elseif (strpos($scriptPath, '/categories') !== false) $adminSection = 'categories';
elseif (strpos($scriptPath, '/inventory') !== false) $adminSection = 'inventory';
elseif (strpos($scriptPath, '/orders') !== false) $adminSection = 'orders';
elseif (strpos($scriptPath, '/customers') !== false) $adminSection = 'customers';
elseif (strpos($scriptPath, '/coupons') !== false) $adminSection = 'coupons';
elseif (strpos($scriptPath, '/reviews') !== false) $adminSection = 'reviews';
elseif (strpos($scriptPath, '/reports') !== false) $adminSection = 'reports';
elseif (strpos($scriptPath, '/settings') !== false) $adminSection = 'settings';
?>
<!doctype html>
<html lang="en" data-theme-storage-key="hane-admin-theme">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($adminTitle) ? e($adminTitle) . ' | HANÉ Admin' : 'HANÉ Management Console' ?></title>
    <script src="<?= ASSET_PATH ?>js/theme.js?v=<?= filemtime(__DIR__ . '/../assets/js/theme.js') ?>"></script>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Admin & Main CSS -->
    <link rel="stylesheet" href="<?= ASSET_PATH ?>css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">
</head>
<body class="admin-body">

<div class="admin-wrapper">
    <!-- Admin Sidebar Navigation -->
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="admin-sidebar-brand">
            <div class="brand-name">HANÉ</div>
            <span class="brand-badge">Admin</span>
        </div>

        <nav class="admin-nav" aria-label="Admin Navigation">
            <div class="admin-nav-category">Main</div>
            <a href="<?= BASE_URL ?>admin/index.php" class="admin-nav-item <?= ($adminSection === 'dashboard') ? 'active' : '' ?>">
                <?= hane_icon('dashboard') ?> <span>Dashboard</span>
            </a>

            <div class="admin-nav-category">Catalog &amp; Stock</div>
            <a href="<?= BASE_URL ?>admin/products/index.php" class="admin-nav-item <?= ($adminSection === 'products') ? 'active' : '' ?>">
                <?= hane_icon('box') ?> <span>Products</span>
            </a>
            <a href="<?= BASE_URL ?>admin/categories/index.php" class="admin-nav-item <?= ($adminSection === 'categories') ? 'active' : '' ?>">
                <?= hane_icon('grid') ?> <span>Categories</span>
            </a>
            <a href="<?= BASE_URL ?>admin/inventory/index.php" class="admin-nav-item <?= ($adminSection === 'inventory') ? 'active' : '' ?>">
                <?= hane_icon('truck') ?> <span>Inventory</span>
            </a>

            <div class="admin-nav-category">Sales &amp; Users</div>
            <a href="<?= BASE_URL ?>admin/orders/index.php" class="admin-nav-item <?= ($adminSection === 'orders') ? 'active' : '' ?>">
                <?= hane_icon('bag') ?> <span>Orders</span>
            </a>
            <a href="<?= BASE_URL ?>admin/customers/index.php" class="admin-nav-item <?= ($adminSection === 'customers') ? 'active' : '' ?>">
                <?= hane_icon('users') ?> <span>Customers</span>
            </a>
            <a href="<?= BASE_URL ?>admin/coupons/index.php" class="admin-nav-item <?= ($adminSection === 'coupons') ? 'active' : '' ?>">
                <?= hane_icon('tag') ?> <span>Coupons</span>
            </a>
            <a href="<?= BASE_URL ?>admin/reviews/index.php" class="admin-nav-item <?= ($adminSection === 'reviews') ? 'active' : '' ?>">
                <?= hane_icon('star') ?> <span>Reviews</span>
            </a>

            <div class="admin-nav-category">Insights &amp; Setup</div>
            <a href="<?= BASE_URL ?>admin/reports/index.php" class="admin-nav-item <?= ($adminSection === 'reports') ? 'active' : '' ?>">
                <?= hane_icon('dashboard') ?> <span>Reports</span>
            </a>
            <a href="<?= BASE_URL ?>admin/settings/index.php" class="admin-nav-item <?= ($adminSection === 'settings') ? 'active' : '' ?>">
                <?= hane_icon('settings') ?> <span>Settings</span>
            </a>
        </nav>

        <div class="admin-sidebar-footer">
            <div class="admin-user-pill">
                <div class="admin-user-avatar">
                    <?= strtoupper(substr($adminName, 0, 1)) ?>
                </div>
                <div class="admin-user-info">
                    <div class="name"><?= e($adminName) ?></div>
                    <div class="role"><?= e($adminEmail) ?></div>
                </div>
                <a href="<?= BASE_URL ?>admin/logout.php" style="color: rgba(250,247,241,0.7); display: flex;" title="Sign Out">
                    <?= hane_icon('logout') ?>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="admin-main">
        <!-- Topbar -->
        <header class="admin-topbar">
            <div class="topbar-left">
                <button class="admin-hamburger" id="adminHamburger" type="button" aria-label="Toggle Navigation">
                    <?= hane_icon('menu') ?>
                </button>
                <div class="admin-breadcrumbs">
                    HANÉ Admin &rsaquo; <span><?= isset($adminTitle) ? e($adminTitle) : 'Dashboard' ?></span>
                </div>
            </div>

            <div class="topbar-right">
                <button class="admin-theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark mode" title="Switch to dark mode" aria-pressed="false">
                    <span class="theme-toggle-icon" data-theme-icon="moon"><?= hane_icon('moon') ?></span>
                    <span class="theme-toggle-icon" data-theme-icon="sun" hidden><?= hane_icon('sun') ?></span>
                </button>
                <a class="storefront-link" href="<?= BASE_URL ?>index.php" target="_blank" title="View Public Website">
                    <?= hane_icon('eye') ?> Live Storefront &rarr;
                </a>
            </div>
        </header>

        <!-- Flash Alert Toasts -->
        <?php if ($flashMsg): ?>
            <div style="padding: 1.2rem 2rem 0;">
                <div style="background: <?= ($flashMsg['type'] === 'success') ? 'var(--admin-success-bg)' : 'var(--admin-error-bg)' ?>; border-left: 4px solid <?= ($flashMsg['type'] === 'success') ? 'var(--admin-alert-success)' : 'var(--admin-alert-error)' ?>; color: <?= ($flashMsg['type'] === 'success') ? 'var(--admin-alert-success)' : 'var(--admin-alert-error)' ?>; padding: 0.9rem 1.4rem; border-radius: 8px; font-size: 0.92rem;">
                    <?= e($flashMsg['message']) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Dynamic Page Content Container -->
        <main class="admin-content">
