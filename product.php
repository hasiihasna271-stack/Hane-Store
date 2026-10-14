<?php
/**
 * HANÉ - Product Details Page
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$productId = (int)($_GET['id'] ?? 0);
if ($productId <= 0) {
    header('Location: ' . BASE_URL . 'shop.php');
    exit;
}

// Fetch product details
$pStmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name, c.slug AS category_slug 
    FROM products p 
    LEFT JOIN categories c ON c.id = p.category_id 
    WHERE p.id = ? AND p.status = 1 
    LIMIT 1
");
$pStmt->execute([$productId]);
$product = $pStmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: ' . BASE_URL . 'shop.php');
    exit;
}

// Handle Customer Review Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!verify_csrf()) {
        set_flash('error', 'Security session expired. Please try again.');
        header('Location: ' . BASE_URL . 'product.php?id=' . $productId);
        exit;
    }

    $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
    $comment = trim($_POST['comment'] ?? '');
    $customerName = trim($_POST['customer_name'] ?? '');
    $userId = $_SESSION['user_id'] ?? null;

    if (empty($customerName) && $userId) {
        $uStmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $uStmt->execute([$userId]);
        $customerName = $uStmt->fetchColumn() ?: 'Verified Customer';
    }

    if (!empty($comment)) {
        $revStmt = $pdo->prepare("INSERT INTO reviews (product_id, user_id, customer_name, rating, comment, status) VALUES (?, ?, ?, ?, ?, 'Approved')");
        $revStmt->execute([$productId, $userId, $customerName ?: 'Customer', $rating, $comment]);
        set_flash('success', 'Thank you! Your review has been published.');
    } else {
        set_flash('error', 'Please provide review comments before submitting.');
    }
    header('Location: ' . BASE_URL . 'product.php?id=' . $productId . '#reviews');
    exit;
}

// Fetch Gallery Images
$imgStmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ? ORDER BY sort_order ASC");
$imgStmt->execute([$productId]);
$extraImages = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

$gallery = [];
if (!empty($product['featured_image'])) {
    $gallery[] = get_image_url($product['featured_image']);
}
foreach ($extraImages as $img) {
    $url = get_image_url($img);
    if (!in_array($url, $gallery)) {
        $gallery[] = $url;
    }
}
if (empty($gallery)) {
    $gallery[] = BASE_URL . 'assets/images/hero-model.jpg';
}

// Fetch Variants with Color, Size and Stock
$vStmt = $pdo->prepare("SELECT id, color, color_code, size, stock, sku FROM product_variants WHERE product_id = ? ORDER BY color ASC, size ASC");
$vStmt->execute([$productId]);
$variants = $vStmt->fetchAll(PDO::FETCH_ASSOC);

// Extract distinct colors and sizes
$colors = [];
$sizes = [];
foreach ($variants as $v) {
    if (!isset($colors[$v['color']])) {
        $colors[$v['color']] = $v['color_code'] ?: '#E7B1AE';
    }
    if (!in_array($v['size'], $sizes)) {
        $sizes[] = $v['size'];
    }
}

// Price calculations
$regularPrice = (float)$product['regular_price'];
$salePrice = ($product['sale_price'] !== null && $product['sale_price'] > 0) ? (float)$product['sale_price'] : null;
$isOnSale = ($salePrice !== null && $salePrice < $regularPrice);
$effectivePrice = $isOnSale ? $salePrice : $regularPrice;
$discountPercent = $isOnSale ? round((($regularPrice - $salePrice) / $regularPrice) * 100) : 0;

// Fetch Approved Reviews
$revStmt = $pdo->prepare("SELECT * FROM reviews WHERE product_id = ? AND status = 'Approved' ORDER BY created_at DESC");
$revStmt->execute([$productId]);
$reviews = $revStmt->fetchAll(PDO::FETCH_ASSOC);
$totalReviews = count($reviews);
$avgRating = $totalReviews > 0 ? round(array_sum(array_column($reviews, 'rating')) / $totalReviews, 1) : 5.0;

// Related Products ("You May Also Like")
$relStmt = $pdo->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.category_id = ? AND p.id != ? AND p.status = 1 ORDER BY RAND() LIMIT 4");
$relStmt->execute([$product['category_id'], $productId]);
$relatedProducts = $relStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = $product['name'];
$pageDesc = substr(strip_tags($product['description']), 0, 160);

include __DIR__ . '/includes/header.php';
?>

<!-- Pass variant JSON safely for client interactivity -->
<script id="productVariantsData" type="application/json">
<?= json_encode($variants, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>
</script>

<div class="container">
    <div class="product-detail-layout">
        <!-- Gallery Section -->
        <div class="product-gallery-wrap">
            <div class="gallery-thumbs">
                <?php foreach ($gallery as $index => $imgUrl): ?>
                    <div class="thumb-item <?= ($index === 0) ? 'active' : '' ?>" data-full-img="<?= $imgUrl ?>">
                        <img src="<?= $imgUrl ?>" alt="<?= e($product['name']) ?> thumbnail <?= $index + 1 ?>">
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="gallery-main">
                <img id="mainGalleryImg" src="<?= $gallery[0] ?>" alt="<?= e($product['name']) ?>">
            </div>
        </div>

        <!-- Product Information & Variant Selector -->
        <div class="product-detail-info">
            <nav class="detail-breadcrumbs" aria-label="Breadcrumb">
                <a href="<?= BASE_URL ?>index.php">Home</a> &rsaquo; 
                <a href="<?= BASE_URL ?>shop.php">Shop</a> &rsaquo; 
                <a href="<?= BASE_URL ?>shop.php?cat=<?= urlencode($product['category_slug'] ?? '') ?>"><?= e($product['category_name'] ?? 'Collection') ?></a>
            </nav>

            <h1 class="detail-title"><?= e($product['name']) ?></h1>

            <div class="detail-rating-row">
                <div class="stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <?= ($i <= round($avgRating)) ? hane_icon('star') : hane_icon('star-empty') ?>
                    <?php endfor; ?>
                </div>
                <a href="#reviews" style="font-size: 0.85rem; color: var(--color-muted); text-decoration: underline;">
                    <?= $avgRating ?> (<?= $totalReviews ?> <?= $totalReviews === 1 ? 'Review' : 'Reviews' ?>)
                </a>
            </div>

            <div class="detail-price-row">
                <span class="detail-price"><?= money($effectivePrice) ?></span>
                <?php if ($isOnSale): ?>
                    <span class="detail-original-price"><?= money($regularPrice) ?></span>
                    <span class="detail-discount-pill">-<?= $discountPercent ?>% OFF</span>
                <?php endif; ?>
            </div>

            <div class="detail-description">
                <?= nl2br(e($product['description'])) ?>
            </div>

            <!-- Add to Cart / Variant Form -->
            <form class="add-to-cart-form" method="post" action="<?= BASE_URL ?>cart.php">
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?= $productId ?>">
                <input type="hidden" name="variant_id" id="selectedVariantId" value="">

                <!-- Color Selection -->
                <div class="variant-block">
                    <div class="variant-label-row">
                        <span class="variant-label">Color: <strong id="selectedColorName" style="color: var(--color-charcoal);"></strong></span>
                    </div>
                    <div class="color-swatch-list">
                        <?php foreach ($colors as $colName => $colCode): ?>
                            <button class="color-swatch-btn" type="button" data-color="<?= e($colName) ?>">
                                <span class="circle-color" style="background-color: <?= e($colCode) ?>;"></span>
                                <span><?= e($colName) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Size Selection -->
                <div class="variant-block">
                    <div class="variant-label-row">
                        <span class="variant-label">Size: <strong id="selectedSizeName" style="color: var(--color-charcoal);"></strong></span>
                        <button type="button" class="size-guide-link" id="openSizeGuideBtn">Size Guide</button>
                    </div>
                    <div class="size-pill-list">
                        <?php foreach ($sizes as $sz): ?>
                            <button class="size-select-btn" type="button" data-size="<?= e($sz) ?>">
                                <?= e($sz) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <!-- Dynamic Stock Status Notice -->
                    <div id="stockStatusNotice" class="stock-status-notice in-stock">
                        <!-- Filled by JavaScript -->
                    </div>
                </div>

                <!-- Quantity & Actions -->
                <div class="qty-actions-row">
                    <div class="qty-stepper">
                        <button type="button" class="qty-btn qty-minus" aria-label="Decrease Quantity">-</button>
                        <input type="number" name="quantity" id="productQtyInput" class="qty-input" value="1" min="1" max="10">
                        <button type="button" class="qty-btn qty-plus" aria-label="Increase Quantity">+</button>
                    </div>

                    <div style="flex: 1;">
                        <button type="submit" name="add_to_cart" id="addToCartBtn" class="btn btn-primary btn-full btn-lg">
                            Add to Cart
                        </button>
                    </div>

                    <button type="button" 
                            class="wishlist-toggle-btn <?= is_in_wishlist($pdo, $productId) ? 'active' : '' ?>" 
                            data-product-id="<?= $productId ?>" 
                            style="position: static; width: 50px; height: 50px; border-radius: var(--radius-md);" 
                            title="Add to Wishlist">
                        <?= hane_icon('heart') ?>
                    </button>
                </div>

                <div style="margin-top: 0.8rem;">
                    <button type="submit" name="buy_now" value="1" id="buyNowBtn" class="btn btn-blush btn-full btn-lg">
                        Buy Now
                    </button>
                </div>
            </form>

            <!-- Product Accordions -->
            <div class="detail-accordions">
                <div class="accordion-item">
                    <button class="accordion-header" type="button">
                        <span>Delivery &amp; Shipping</span>
                        <?= hane_icon('chevron-down') ?>
                    </button>
                    <div class="accordion-body" style="display: none;">
                        We deliver island-wide across Sri Lanka within 2–4 business days. Free shipping is automatically applied on orders over <?= money(get_setting('free_shipping_min', 5000)) ?>. Cash on Delivery and Bank Transfer are supported.
                    </div>
                </div>

                <div class="accordion-item">
                    <button class="accordion-header" type="button">
                        <span>Easy 7-Day Returns</span>
                        <?= hane_icon('chevron-down') ?>
                    </button>
                    <div class="accordion-body" style="display: none;">
                        We want you to feel completely confident in your purchase. If you need a different size or style, initiate an exchange within 7 days of receiving your parcel. Items must be unworn with original tags attached.
                    </div>
                </div>

                <div class="accordion-item">
                    <button class="accordion-header" type="button">
                        <span>Fabric Care Instructions</span>
                        <?= hane_icon('chevron-down') ?>
                    </button>
                    <div class="accordion-body" style="display: none;">
                        To preserve the delicate drape and natural textures of our garments: hand wash cold or machine wash gentle cycle in a protective laundry bag. Line dry in shade and warm iron on reverse.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Size Guide Modal -->
    <div class="modal-overlay" id="sizeGuideModal" role="dialog" aria-modal="true" aria-labelledby="sizeGuideTitle">
        <div class="modal-container">
            <button class="modal-close-btn" id="closeSizeGuideBtn" type="button" aria-label="Close Size Guide">
                <?= hane_icon('close') ?>
            </button>
            <h2 id="sizeGuideTitle" class="section-title" style="margin: 0 0 1rem; text-align: left;">HANÉ Size Guide</h2>
            <p class="muted" style="margin-bottom: 1.5rem;">Measurements are provided in inches for standard garment fitting.</p>
            <div class="table-wrap">
                <table class="hane-table">
                    <thead>
                        <tr>
                            <th>Size</th>
                            <th>Bust (in)</th>
                            <th>Waist (in)</th>
                            <th>Hips (in)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>XS</strong></td><td>32 &ndash; 33</td><td>24 &ndash; 25</td><td>34 &ndash; 35</td></tr>
                        <tr><td><strong>S</strong></td><td>34 &ndash; 35</td><td>26 &ndash; 27</td><td>36 &ndash; 37</td></tr>
                        <tr><td><strong>M</strong></td><td>36 &ndash; 37</td><td>28 &ndash; 29</td><td>38 &ndash; 39</td></tr>
                        <tr><td><strong>L</strong></td><td>38 &ndash; 40</td><td>30 &ndash; 32</td><td>40 &ndash; 42</td></tr>
                        <tr><td><strong>XL</strong></td><td>41 &ndash; 43</td><td>33 &ndash; 35</td><td>43 &ndash; 45</td></tr>
                    </tbody>
                </table>
            </div>
            <p style="font-size: 0.85rem; color: var(--color-muted);">If your measurements fall between two sizes, we recommend sizing up for a relaxed fit or sizing down for a tailored silhouette.</p>
        </div>
    </div>

    <!-- Customer Reviews Section -->
    <section id="reviews" style="margin: 4rem 0 3rem; padding-top: 2rem; border-top: 1px solid var(--color-line);">
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h2 class="section-title" style="text-align: left; margin: 0 0 0.4rem;">Customer Reviews</h2>
                <div style="display: flex; align-items: center; gap: 0.8rem;">
                    <div class="stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?= ($i <= round($avgRating)) ? hane_icon('star') : hane_icon('star-empty') ?>
                        <?php endfor; ?>
                    </div>
                    <span style="font-weight: 700; color: var(--color-green);"><?= $avgRating ?> out of 5</span>
                    <span class="muted">(Based on <?= $totalReviews ?> reviews)</span>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1.4fr 1fr; gap: 3rem;">
            <!-- Reviews List -->
            <div>
                <?php if (!empty($reviews)): ?>
                    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                        <?php foreach ($reviews as $rev): ?>
                            <div style="background: white; border: 1px solid var(--color-line); border-radius: var(--radius-md); padding: 1.5rem;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.6rem;">
                                    <strong><?= e($rev['customer_name']) ?></strong>
                                    <span class="muted" style="font-size: 0.8rem;"><?= date('M d, Y', strtotime($rev['created_at'])) ?></span>
                                </div>
                                <div class="stars" style="margin-bottom: 0.8rem;">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?= ($i <= (int)$rev['rating']) ? hane_icon('star') : hane_icon('star-empty') ?>
                                    <?php endfor; ?>
                                </div>
                                <p style="color: #444; font-size: 0.95rem; line-height: 1.6;"><?= nl2br(e($rev['comment'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state" style="padding: 3rem 1.5rem;">
                        <p class="muted">No reviews yet for this piece. Be the first to share your experience!</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Submit Review Form -->
            <div>
                <div class="form-card" style="padding: 2rem;">
                    <h3 style="font-family: var(--font-serif); font-size: 1.4rem; color: var(--color-green); margin-bottom: 1.2rem;">Write a Review</h3>
                    <form method="post" action="<?= BASE_URL ?>product.php?id=<?= $productId ?>">
                        <?= csrf_field() ?>

                        <div class="form-group">
                            <label class="form-label">Your Name</label>
                            <input class="form-control" type="text" name="customer_name" value="<?= e($currentUser['name'] ?? '') ?>" placeholder="e.g. Ruwani S." required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Rating</label>
                            <select class="form-control" name="rating" required>
                                <option value="5">&#9733;&#9733;&#9733;&#9733;&#9733; (5 Stars - Excellent)</option>
                                <option value="4">&#9733;&#9733;&#9733;&#9733;&#9734; (4 Stars - Very Good)</option>
                                <option value="3">&#9733;&#9733;&#9733;&#9734;&#9734; (3 Stars - Average)</option>
                                <option value="2">&#9733;&#9733;&#9734;&#9734;&#9734; (2 Stars - Poor)</option>
                                <option value="1">&#9733;&#9734;&#9734;&#9734;&#9734; (1 Star - Terrible)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Your Review</label>
                            <textarea class="form-control" name="comment" rows="4" placeholder="How did this garment fit? How does the fabric feel?" required></textarea>
                        </div>

                        <button class="btn btn-primary btn-full" type="submit" name="submit_review">
                            Submit Review
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Related Products -->
    <?php if (!empty($relatedProducts)): ?>
        <section style="margin: 4rem 0;">
            <h2 class="section-title">You May Also Like</h2>
            <p class="section-subtitle">Complementary pieces designed with matching aesthetics.</p>
            <div class="products-grid">
                <?php foreach ($relatedProducts as $p): ?>
                    <?php include __DIR__ . '/includes/product-card.php'; ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
