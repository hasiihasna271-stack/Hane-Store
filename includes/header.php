<?php
/**
 * HANÉ Customer Storefront Header
 */
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$cartCountVal = cartCount();
$wishlistCountVal = wishlistCount($pdo);
$currentUser = get_current_user_data($pdo);
$flashMsg = get_flash();
$currentScript = basename($_SERVER['PHP_SELF'] ?? '');
$currentCat = $_GET['cat'] ?? '';
$isSalePage = isset($_GET['sale']);
$isNewPage = isset($_GET['new']);
?>
<!doctype html>
<html lang="en" data-theme-storage-key="hane-theme">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' | ' . SITE_NAME : SITE_NAME . ' | ' . SITE_TAGLINE ?></title>
    <meta name="description" content="<?= isset($pageDesc) ? e($pageDesc) : 'HANÉ - Timeless elegance and modern women’s fashion.' ?>">
    <script src="<?= ASSET_PATH ?>js/theme.js?v=<?= filemtime(__DIR__ . '/../assets/js/theme.js') ?>"></script>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Main Storefront CSS -->
    <link rel="stylesheet" href="<?= ASSET_PATH ?>css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>

<!-- Announcement Topbar -->
<div class="top-announcement">
    Free Shipping on Orders over <?= money(get_setting('free_shipping_min', 5000)) ?> &nbsp;|&nbsp; Easy 7-Day Returns &nbsp;|&nbsp; Cash on Delivery Available
</div>

<!-- Header -->
<header class="site-header">
    <div class="container">
        <div class="navbar">
            <!-- Mobile Menu Toggle Button -->
            <button class="hamburger-btn" id="hamburgerBtn" type="button" aria-label="Open Navigation Menu">
                <?= hane_icon('menu') ?>
            </button>

            <!-- Brand Logo -->
            <a class="logo" href="<?= BASE_URL ?>index.php">
                HANÉ
                <span>Elegance</span>
            </a>

            <!-- Desktop Navigation Links -->
            <nav class="nav-menu" aria-label="Main Navigation">
                <a class="nav-link <?= ($currentScript === 'index.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>">Home</a>
                <a class="nav-link <?= ($currentScript === 'shop.php' && !$currentCat && !$isSalePage && !$isNewPage) ? 'active' : '' ?>" href="<?= BASE_URL ?>shop.php">Shop</a>
                <a class="nav-link" href="<?= BASE_URL ?>shop.php#collections">Collections</a>
                <a class="nav-link <?= $isNewPage ? 'active' : '' ?>" href="<?= BASE_URL ?>shop.php?new=1">New Arrivals</a>
                <a class="nav-link <?= ($currentCat === 'dresses') ? 'active' : '' ?>" href="<?= BASE_URL ?>shop.php?cat=dresses">Dresses</a>
                <a class="nav-link <?= ($currentCat === 'tops') ? 'active' : '' ?>" href="<?= BASE_URL ?>shop.php?cat=tops">Tops</a>
                <a class="nav-link <?= ($currentCat === 'bottoms') ? 'active' : '' ?>" href="<?= BASE_URL ?>shop.php?cat=bottoms">Bottoms</a>
                <a class="nav-link sale-link <?= $isSalePage ? 'active' : '' ?>" href="<?= BASE_URL ?>shop.php?sale=1">Sale</a>
            </nav>

            <!-- Action Buttons: Search, Account, Wishlist, Cart -->
            <div class="nav-actions">
                <button class="nav-btn" id="searchToggleBtn" type="button" title="Search Products" aria-label="Search">
                    <?= hane_icon('search') ?>
                </button>

                <button class="nav-btn theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark mode" title="Switch to dark mode" aria-pressed="false">
                    <span class="theme-toggle-icon" data-theme-icon="moon"><?= hane_icon('moon') ?></span>
                    <span class="theme-toggle-icon" data-theme-icon="sun" hidden><?= hane_icon('sun') ?></span>
                </button>

                <a class="nav-btn" href="<?= is_user_logged_in() ? BASE_URL . 'account/profile.php' : BASE_URL . 'login.php' ?>" title="<?= is_user_logged_in() ? 'My Account (' . e($currentUser['name'] ?? 'User') . ')' : 'Sign In' ?>" aria-label="Account">
                    <?= hane_icon('user') ?>
                </a>

                <a class="nav-btn" href="<?= BASE_URL ?>wishlist.php" title="Wishlist" aria-label="Wishlist">
                    <?= hane_icon('heart') ?>
                    <span class="badge-count" id="wishlistCountBadge"><?= $wishlistCountVal ?></span>
                </a>

                <a class="nav-btn" href="<?= BASE_URL ?>cart.php" title="Shopping Bag" aria-label="Cart">
                    <?= hane_icon('bag') ?>
                    <span class="badge-count" id="cartCountBadge"><?= $cartCountVal ?></span>
                </a>
            </div>
        </div>
    </div>

    <!-- Expandable Search Form Drawer -->
    <div class="search-bar-drawer" id="searchBarDrawer">
        <div class="container">
            <form class="search-form-header" action="<?= BASE_URL ?>shop.php" method="get">
                <input class="search-input-header" type="search" name="q" placeholder="Search dresses, linen tops, co-ords, trousers..." value="<?= e($_GET['q'] ?? '') ?>">
                <button class="btn btn-primary btn-sm" type="submit">
                    <?= hane_icon('search') ?> Search
                </button>
            </form>
        </div>
    </div>
</header>

<!-- Mobile Navigation Drawer -->
<div class="mobile-nav-overlay" id="mobileOverlay"></div>
<div class="mobile-drawer" id="mobileDrawer">
    <div class="mobile-drawer-header">
        <div class="logo">HANÉ</div>
        <button class="modal-close-btn" id="closeDrawerBtn" type="button" aria-label="Close Navigation">
            <?= hane_icon('close') ?>
        </button>
    </div>
    <div class="mobile-menu-links">
        <a href="<?= BASE_URL ?>index.php">Home</a>
        <a href="<?= BASE_URL ?>shop.php">All Products</a>
        <a href="<?= BASE_URL ?>shop.php?new=1">New Arrivals</a>
        <a href="<?= BASE_URL ?>shop.php?cat=dresses">Dresses</a>
        <a href="<?= BASE_URL ?>shop.php?cat=tops">Tops</a>
        <a href="<?= BASE_URL ?>shop.php?cat=bottoms">Bottoms</a>
        <a href="<?= BASE_URL ?>shop.php?cat=co-ords">Co-ords</a>
        <a href="<?= BASE_URL ?>shop.php?sale=1" style="color: #b73e3e; font-weight: 700;">Season Sale (Up to 40% Off)</a>
        <hr style="border: 0; border-top: 1px solid var(--color-line); margin: 0.5rem 0;">
        <?php if (is_user_logged_in()): ?>
            <a href="<?= BASE_URL ?>account/profile.php">My Account (<?= e($currentUser['name'] ?? 'User') ?>)</a>
            <a href="<?= BASE_URL ?>account/orders.php">My Orders</a>
            <a href="<?= BASE_URL ?>logout.php">Logout</a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>login.php">Sign In</a>
            <a href="<?= BASE_URL ?>register.php">Create Account</a>
        <?php endif; ?>
    </div>
</div>

<!-- Flash Message Toasts Container -->
<div class="toast-container" id="toastContainer">
    <?php if ($flashMsg): ?>
        <div class="toast toast-<?= e($flashMsg['type']) ?>">
            <div class="toast-msg"><?= e($flashMsg['message']) ?></div>
            <button class="toast-close" type="button" aria-label="Close">&times;</button>
        </div>
    <?php endif; ?>
</div>

<main>
