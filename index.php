<?php
/**
 * HANÉ - Homepage
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Wear Your Elegance';
$pageDesc = 'Discover timeless women’s fashion, effortless silhouettes, and premium linens at HANÉ.';

// Fetch categories with dynamic product count
$catStmt = $pdo->query("
    SELECT c.*, COUNT(p.id) AS product_count 
    FROM categories c 
    LEFT JOIN products p ON p.category_id = c.id AND p.status = 1 
    WHERE c.status = 1 
    GROUP BY c.id 
    ORDER BY c.id ASC 
    LIMIT 6
");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch New Arrivals dynamically from MySQL
$newProductsStmt = $pdo->query("
    SELECT p.*, c.name AS category_name 
    FROM products p 
    LEFT JOIN categories c ON c.id = p.category_id 
    WHERE p.status = 1 
    ORDER BY p.is_new DESC, p.id DESC 
    LIMIT 8
");
$newProducts = $newProductsStmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section" aria-label="Hero Showcase">
    <div class="hero-grid">
        <div class="hero-content">
            <div class="hero-eyebrow-wrap">
                <span class="hero-eyebrow"><span aria-hidden="true">✦</span> NEW SEASON COLLECTION</span>
                <span class="hero-eyebrow-line" aria-hidden="true"></span>
            </div>
            <h1 class="hero-title">
                Elegance<br>in <i>Every Look</i>
            </h1>
            <p class="hero-description">
                Trendy outfits, timeless styles, made for the modern you.
            </p>
            <div class="hero-cta-row">
                <a class="btn btn-primary btn-lg hero-shop-button" href="<?= BASE_URL ?>shop.php">
                    Shop Now <?= hane_icon('arrow-right') ?>
                </a>
                <div class="hero-trust-features" aria-label="Shopping benefits">
                    <div class="hero-trust-item">
                        <svg class="hero-trust-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M19.5 4.5C11 4.5 5.5 8 5.5 14c0 3.2 2.1 5.5 5.1 5.5 6.2 0 8.9-7.4 8.9-15Z" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M3.5 21c2.2-4.2 5.6-7.4 10.2-9.8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                        </svg>
                        <span>Premium<br>Quality</span>
                    </div>
                    <div class="hero-trust-item">
                        <?= hane_icon('truck', 'hero-trust-icon') ?>
                        <span>Fast<br>Shipping</span>
                    </div>
                    <div class="hero-trust-item">
                        <?= hane_icon('return', 'hero-trust-icon') ?>
                        <span>Easy<br>Returns</span>
                    </div>
                </div>
            </div>
            <div class="hero-slider-dots" aria-hidden="true">
                <span class="active"></span>
                <span></span>
                <span></span>
                <i></i>
            </div>
        </div>

        <div class="hero-visual-wrap" aria-label="HANÉ New Season Collection">
            <div class="hero-visual">
                <div class="hero-halo" aria-hidden="true"></div>
                <div class="hero-orbit" aria-hidden="true"></div>

                <div class="hero-floral-decoration" aria-hidden="true">
                    <svg viewBox="0 0 260 350" fill="none">
                        <path d="M34 320C75 253 79 190 119 132C145 94 178 66 219 34" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                        <path d="M77 245C43 234 31 209 35 183C61 190 79 211 77 245Z" stroke="currentColor" stroke-width="1.2"/>
                        <path d="M96 211C129 201 147 178 149 149C120 156 100 178 96 211Z" stroke="currentColor" stroke-width="1.2"/>
                        <path d="M126 157C95 143 84 119 91 94C117 105 132 128 126 157Z" stroke="currentColor" stroke-width="1.2"/>
                        <path d="M153 121C181 112 195 91 194 67C169 73 153 91 153 121Z" stroke="currentColor" stroke-width="1.2"/>
                        <path d="M54 282C27 270 18 251 24 230C46 240 58 258 54 282Z" stroke="currentColor" stroke-width="1.2"/>
                        <g stroke="currentColor" stroke-width="1.1">
                            <path d="M163 211c0-8 6-14 14-14s14 6 14 14-6 14-14 14-14-6-14-14Z"/>
                            <path d="M177 190v42M156 211h42M162 196l30 30M192 196l-30 30"/>
                            <path d="M210 140c0-6 5-11 11-11s11 5 11 11-5 11-11 11-11-5-11-11Z"/>
                            <path d="M221 123v34M204 140h34M209 128l24 24M233 128l-24 24"/>
                        </g>
                        <circle cx="177" cy="211" r="3" fill="currentColor"/>
                        <circle cx="221" cy="140" r="2.5" fill="currentColor"/>
                    </svg>
                </div>

                <div class="hero-leaves" aria-hidden="true">
                    <span class="leaf leaf-one"></span>
                    <span class="leaf leaf-two"></span>
                    <span class="leaf leaf-three"></span>
                </div>

                <span class="hero-sparkle hero-sparkle-one" aria-hidden="true">✦</span>
                <span class="hero-sparkle hero-sparkle-two" aria-hidden="true">✦</span>
                <span class="hero-petal hero-petal-one" aria-hidden="true"></span>
                <span class="hero-petal hero-petal-two" aria-hidden="true"></span>

                <img
                    src="<?= BASE_URL ?>assets/images/hero-model.png"
                    class="hero-model"
                    alt="HANÉ new season collection"
                >
            </div>
        </div>
    </div>
</section>

<div class="container">
    <!-- Shop by Category Section -->
    <section id="collections" style="margin-top: 2rem;">
        <h2 class="section-title">Shop by Category</h2>
        <p class="section-subtitle">Curated essentials tailored to elevate every moment of your day.</p>
        
        <div class="categories-grid">
            <?php foreach ($categories as $cat): ?>
                <a class="category-card" href="<?= BASE_URL ?>shop.php?cat=<?= urlencode($cat['slug']) ?>">
                    <div class="category-img-wrap">
                        <img src="<?= get_image_url($cat['image']) ?>" alt="<?= e($cat['name']) ?>" loading="lazy">
                    </div>
                    <div class="category-name"><?= e($cat['name']) ?></div>
                    <div class="category-count"><?= (int)$cat['product_count'] ?> Products</div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Sale Banner -->
    <section class="sale-banner">
        <div class="sale-banner-grid">
            <div class="sale-banner-content">
                <span class="sale-tag">LIMITED TIME OFFER</span>
                <h2>Season Sale is Live!</h2>
                <p>Enjoy up to 40% off on selected collections. Indulge in premium dresses, breathable linens, and luxury co-ords before stock runs out.</p>
                <div class="sale-discount-badge">
                    UP TO 40% OFF
                </div>
                <div>
                    <a class="btn btn-blush btn-lg" href="<?= BASE_URL ?>shop.php?sale=1">
                        Explore Deals <?= hane_icon('arrow-right') ?>
                    </a>
                </div>
            </div>
            <div class="sale-banner-image">
                <img src="<?= BASE_URL ?>assets/images/sale-model.jpg" alt="Season Fashion Sale Promotion">
            </div>
        </div>
    </section>

    <!-- New Arrivals / Products Section -->
    <section style="margin-bottom: 4rem;">
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h2 class="section-title" style="text-align: left; margin: 0 0 0.4rem;">New Arrivals</h2>
                <p style="color: var(--color-muted); margin: 0;">Fresh drops, timeless cuts, and effortless styling.</p>
            </div>
            <a class="btn btn-outline" href="<?= BASE_URL ?>shop.php">
                View All Products <?= hane_icon('arrow-right') ?>
            </a>
        </div>

        <div class="products-grid">
            <?php foreach ($newProducts as $p): ?>
                <?php include __DIR__ . '/includes/product-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>