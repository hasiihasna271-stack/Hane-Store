<?php
/**
 * Reusable Product Card Component
 * Expected variable: $p (array of product fields)
 */

if (!isset($p)) return;

$pId = (int)$p['id'];
$pName = $p['name'];
$pSlug = $p['slug'];
$pPrice = (float)$p['regular_price'];
$pSalePrice = ($p['sale_price'] !== null && $p['sale_price'] > 0) ? (float)$p['sale_price'] : null;
$isOnSale = ($pSalePrice !== null && $pSalePrice < $pPrice);
$effectivePrice = $isOnSale ? $pSalePrice : $pPrice;
$isNew = (bool)($p['is_new'] ?? 0);

// Get main image and secondary image
$mainImg = get_image_url($p['featured_image'] ?? '');

// Check secondary image
$secImg = null;
if (!empty($p['secondary_image'])) {
    $secImg = get_image_url($p['secondary_image']);
} else {
    // Lookup secondary image if not already joined
    static $secondaryImagesCache = [];
    if (!isset($secondaryImagesCache[$pId]) && isset($pdo)) {
        $imgStmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ? AND is_secondary = 1 LIMIT 1");
        $imgStmt->execute([$pId]);
        $secondaryImagesCache[$pId] = $imgStmt->fetchColumn() ?: null;
    }
    if (!empty($secondaryImagesCache[$pId])) {
        $secImg = get_image_url($secondaryImagesCache[$pId]);
    }
}

// Colors and total stock for this product
static $productVariantsCache = [];
if (!isset($productVariantsCache[$pId]) && isset($pdo)) {
    $vStmt = $pdo->prepare("SELECT DISTINCT color, color_code, stock FROM product_variants WHERE product_id = ?");
    $vStmt->execute([$pId]);
    $productVariantsCache[$pId] = $vStmt->fetchAll(PDO::FETCH_ASSOC);
}
$variants = $productVariantsCache[$pId] ?? [];

$distinctColors = [];
$totalStock = 0;
foreach ($variants as $v) {
    $totalStock += (int)$v['stock'];
    if (!isset($distinctColors[$v['color']])) {
        $distinctColors[$v['color']] = $v['color_code'] ?: '#CCCCCC';
    }
}

$isOutOfStock = ($totalStock <= 0);
$inWishlist = is_in_wishlist($pdo, $pId);
?>
<div class="product-card">
    <div class="product-media">
        <!-- Badges -->
        <div class="product-badges">
            <?php if ($isOutOfStock): ?>
                <span class="badge badge-out">Out of Stock</span>
            <?php elseif ($isOnSale): ?>
                <span class="badge badge-sale">Sale</span>
            <?php elseif ($isNew): ?>
                <span class="badge badge-new">New</span>
            <?php endif; ?>
        </div>

        <!-- Wishlist Button -->
        <button class="wishlist-toggle-btn <?= $inWishlist ? 'active' : '' ?>" 
                type="button" 
                data-product-id="<?= $pId ?>" 
                title="<?= $inWishlist ? 'Remove from wishlist' : 'Add to wishlist' ?>" 
                aria-label="Wishlist">
            <?= hane_icon('heart') ?>
        </button>

        <!-- Product Images (Primary + Secondary Hover) -->
        <a href="<?= BASE_URL ?>product.php?id=<?= $pId ?>">
            <img src="<?= $mainImg ?>" alt="<?= e($pName) ?>" loading="lazy">
            <?php if ($secImg): ?>
                <img class="img-secondary" src="<?= $secImg ?>" alt="<?= e($pName) ?> Alternate View" loading="lazy">
            <?php endif; ?>
        </a>
    </div>

    <div class="product-info">
        <?php if (!empty($p['category_name'])): ?>
            <div class="product-category-label"><?= e($p['category_name']) ?></div>
        <?php endif; ?>

        <h3 class="product-title">
            <a href="<?= BASE_URL ?>product.php?id=<?= $pId ?>"><?= e($pName) ?></a>
        </h3>

        <!-- Color Swatch Dots -->
        <?php if (!empty($distinctColors)): ?>
            <div class="product-color-dots" title="<?= count($distinctColors) ?> color options">
                <?php foreach (array_slice($distinctColors, 0, 4) as $colName => $colCode): ?>
                    <span class="color-dot" style="background-color: <?= e($colCode) ?>;" title="<?= e($colName) ?>"></span>
                <?php endforeach; ?>
                <?php if (count($distinctColors) > 4): ?>
                    <span style="font-size: 0.72rem; color: var(--color-muted);">+<?= count($distinctColors) - 4 ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Price Display -->
        <div class="product-price-row">
            <span class="current-price"><?= money($effectivePrice) ?></span>
            <?php if ($isOnSale): ?>
                <span class="original-price"><?= money($pPrice) ?></span>
            <?php endif; ?>
        </div>

        <div class="product-actions">
            <a class="btn btn-outline btn-sm btn-full" href="<?= BASE_URL ?>product.php?id=<?= $pId ?>">
                View Product
            </a>
        </div>
    </div>
</div>
