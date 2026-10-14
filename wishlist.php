<?php
/**
 * HANÉ - Saved Wishlist Page
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Handle removal
if (isset($_GET['remove'])) {
    $removeId = (int)$_GET['remove'];
    if (!empty($_SESSION['user_id'])) {
        $del = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
        $del->execute([$_SESSION['user_id'], $removeId]);
    } else {
        unset($_SESSION['wishlist'][$removeId]);
    }
    set_flash('info', 'Item removed from your wishlist.');
    header('Location: ' . BASE_URL . 'wishlist.php');
    exit;
}

$pageTitle = 'Saved Wishlist';
$wishlistIds = get_wishlist_ids($pdo);

$wishlistProducts = [];
if (!empty($wishlistIds)) {
    $placeholders = implode(',', array_fill(0, count($wishlistIds), '?'));
    $wStmt = $pdo->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id IN ($placeholders) AND p.status = 1");
    $wStmt->execute($wishlistIds);
    $wishlistProducts = $wStmt->fetchAll(PDO::FETCH_ASSOC);
}

include __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div style="margin: 2.5rem 0 1rem;">
        <h1 class="section-title" style="margin: 0 0 0.4rem;">Saved Wishlist</h1>
        <p class="muted text-center">Keep track of your dream styles and transfer them to your bag anytime.</p>
    </div>

    <?php if (!empty($wishlistProducts)): ?>
        <div class="products-grid">
            <?php foreach ($wishlistProducts as $p): ?>
                <?php include __DIR__ . '/includes/product-card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon-wrap">
                <?= hane_icon('heart') ?>
            </div>
            <h2 class="empty-title">Your Wishlist is Empty</h2>
            <p class="empty-desc">Found something you adore? Click the heart icon on any product to save it here for later.</p>
            <a class="btn btn-primary btn-lg" href="<?= BASE_URL ?>shop.php">
                Explore Collections <?= hane_icon('arrow-right') ?>
            </a>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
