<?php
/**
 * HANÉ - Shop Page
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Shop All Collections';
$pageDesc = 'Explore the full collection of timeless dresses, linens, tops, bottoms and co-ords at HANÉ.';

// Filter parameters
$catSlug = trim($_GET['cat'] ?? '');
$searchQuery = trim($_GET['q'] ?? '');
$isSaleOnly = isset($_GET['sale']) && $_GET['sale'] == '1';
$isNewOnly = isset($_GET['new']) && $_GET['new'] == '1';
$selectedSize = trim($_GET['size'] ?? '');
$selectedColor = trim($_GET['color'] ?? '');
$inStockOnly = isset($_GET['in_stock']) && $_GET['in_stock'] == '1';
$minPrice = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? (float)$_GET['min_price'] : null;
$maxPrice = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? (float)$_GET['max_price'] : null;
$sortBy = trim($_GET['sort'] ?? 'newest');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Base query construction
$where = ["p.status = 1"];
$params = [];

if ($catSlug !== '') {
    $where[] = "c.slug = ?";
    $params[] = $catSlug;
}

if ($searchQuery !== '') {
    $where[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%{$searchQuery}%";
    $params[] = "%{$searchQuery}%";
}

if ($isSaleOnly) {
    $where[] = "(p.is_sale = 1 OR (p.sale_price IS NOT NULL AND p.sale_price < p.regular_price))";
}

if ($isNewOnly) {
    $where[] = "p.is_new = 1";
}

if ($minPrice !== null) {
    $where[] = "COALESCE(p.sale_price, p.regular_price) >= ?";
    $params[] = $minPrice;
}

if ($maxPrice !== null) {
    $where[] = "COALESCE(p.sale_price, p.regular_price) <= ?";
    $params[] = $maxPrice;
}

// Variant-specific filters (Size, Color, In-Stock)
if ($selectedSize !== '' || $selectedColor !== '' || $inStockOnly) {
    $vSubWhere = ["pv.product_id = p.id"];
    if ($selectedSize !== '') {
        $vSubWhere[] = "pv.size = ?";
        $params[] = $selectedSize;
    }
    if ($selectedColor !== '') {
        $vSubWhere[] = "pv.color = ?";
        $params[] = $selectedColor;
    }
    if ($inStockOnly) {
        $vSubWhere[] = "pv.stock > 0";
    }
    $where[] = "EXISTS (SELECT 1 FROM product_variants pv WHERE " . implode(" AND ", $vSubWhere) . ")";
}

// Sorting logic
$orderBy = "p.id DESC"; // default newest
switch ($sortBy) {
    case 'price_asc':
        $orderBy = "COALESCE(p.sale_price, p.regular_price) ASC";
        break;
    case 'price_desc':
        $orderBy = "COALESCE(p.sale_price, p.regular_price) DESC";
        break;
    case 'name_asc':
        $orderBy = "p.name ASC";
        break;
    case 'best_selling':
        $orderBy = "(SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE product_id = p.id) DESC";
        break;
    default:
        $orderBy = "p.id DESC";
        break;
}

$whereClause = implode(" AND ", $where);

// Count total matching products for pagination
$countSql = "SELECT COUNT(DISTINCT p.id) FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalProducts = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalProducts / $perPage) ?: 1;

// Fetch products for current page
$sql = "
    SELECT p.*, c.name AS category_name, c.slug AS category_slug 
    FROM products p 
    LEFT JOIN categories c ON c.id = p.category_id 
    WHERE $whereClause 
    ORDER BY $orderBy 
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch categories for sidebar filter
$allCategories = $pdo->query("SELECT * FROM categories WHERE status = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch distinct colors and sizes for sidebar filters
$availableSizes = ['XS', 'S', 'M', 'L', 'XL'];
$availableColors = $pdo->query("SELECT DISTINCT color, color_code FROM product_variants ORDER BY color ASC")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div style="margin: 2.5rem 0 1.5rem; text-align: center;">
        <h1 class="section-title" style="margin: 0 0 0.5rem;">
            <?php 
                if ($isSaleOnly) echo 'Season Sale';
                elseif ($isNewOnly) echo 'New Arrivals';
                elseif ($catSlug) {
                    $cName = 'Shop';
                    foreach ($allCategories as $c) {
                        if ($c['slug'] === $catSlug) { $cName = $c['name']; break; }
                    }
                    echo e($cName);
                } else {
                    echo 'The Collection';
                }
            ?>
        </h1>
        <p class="muted">
            Showing <?= count($products) ?> of <?= $totalProducts ?> curated styles
            <?php if ($searchQuery): ?>
                matching &ldquo;<strong><?= e($searchQuery) ?></strong>&rdquo;
            <?php endif; ?>
        </p>
    </div>

    <div class="shop-layout">
        <!-- Sidebar Filters -->
        <aside class="filters-sidebar">
            <form id="filterForm" method="get" action="<?= BASE_URL ?>shop.php">
                <?php if ($searchQuery): ?>
                    <input type="hidden" name="q" value="<?= e($searchQuery) ?>">
                <?php endif; ?>

                <div class="filter-group">
                    <div class="filter-title">
                        Categories
                        <?php if ($catSlug): ?>
                            <a href="<?= BASE_URL ?>shop.php" style="font-size: 0.75rem; text-decoration: underline; color: var(--color-muted);">Clear</a>
                        <?php endif; ?>
                    </div>
                    <div class="filter-options">
                        <label class="filter-checkbox-label">
                            <input type="radio" name="cat" value="" <?= empty($catSlug) ? 'checked' : '' ?> onchange="this.form.submit()">
                            <span>All Categories</span>
                        </label>
                        <?php foreach ($allCategories as $cat): ?>
                            <label class="filter-checkbox-label">
                                <input type="radio" name="cat" value="<?= e($cat['slug']) ?>" <?= ($catSlug === $cat['slug']) ? 'checked' : '' ?> onchange="this.form.submit()">
                                <span><?= e($cat['name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Size Filter -->
                <div class="filter-group">
                    <div class="filter-title">Size</div>
                    <div class="size-pill-selector">
                        <a href="<?= BASE_URL ?>shop.php?<?= http_build_query(array_merge($_GET, ['size' => '', 'page' => 1])) ?>" 
                           class="size-pill-btn <?= empty($selectedSize) ? 'active' : '' ?>">All</a>
                        <?php foreach ($availableSizes as $sz): ?>
                            <a href="<?= BASE_URL ?>shop.php?<?= http_build_query(array_merge($_GET, ['size' => $sz, 'page' => 1])) ?>" 
                               class="size-pill-btn <?= ($selectedSize === $sz) ? 'active' : '' ?>"><?= e($sz) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Color Filter -->
                <div class="filter-group">
                    <div class="filter-title">Color</div>
                    <div class="filter-options" style="max-height: 180px; overflow-y: auto;">
                        <label class="filter-checkbox-label">
                            <input type="radio" name="color" value="" <?= empty($selectedColor) ? 'checked' : '' ?> onchange="this.form.submit()">
                            <span>All Colors</span>
                        </label>
                        <?php foreach ($availableColors as $col): ?>
                            <label class="filter-checkbox-label">
                                <input type="radio" name="color" value="<?= e($col['color']) ?>" <?= ($selectedColor === $col['color']) ? 'checked' : '' ?> onchange="this.form.submit()">
                                <span class="color-dot" style="background-color: <?= e($col['color_code']) ?>; display: inline-block;"></span>
                                <span><?= e($col['color']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Price Range Filter -->
                <div class="filter-group">
                    <div class="filter-title">Price Range (Rs.)</div>
                    <div style="display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.8rem;">
                        <input type="number" name="min_price" placeholder="Min" class="form-control" style="padding: 0.5rem; font-size: 0.85rem;" value="<?= $minPrice !== null ? e($minPrice) : '' ?>">
                        <span>&ndash;</span>
                        <input type="number" name="max_price" placeholder="Max" class="form-control" style="padding: 0.5rem; font-size: 0.85rem;" value="<?= $maxPrice !== null ? e($maxPrice) : '' ?>">
                    </div>
                    <button class="btn btn-outline btn-sm btn-full" type="submit">Apply Price</button>
                </div>

                <!-- Availability Filter -->
                <div class="filter-group">
                    <div class="filter-title">Availability</div>
                    <div class="filter-options">
                        <label class="filter-checkbox-label">
                            <input type="checkbox" name="in_stock" value="1" <?= $inStockOnly ? 'checked' : '' ?> onchange="this.form.submit()">
                            <span>In Stock Only</span>
                        </label>
                    </div>
                </div>

                <div style="margin-top: 1.5rem;">
                    <a class="btn btn-outline btn-sm btn-full" href="<?= BASE_URL ?>shop.php">
                        Reset All Filters
                    </a>
                </div>
            </form>
        </aside>

        <!-- Product Grid Area -->
        <main>
            <!-- Sort and Total Toolbar -->
            <div class="shop-toolbar">
                <div style="font-size: 0.9rem; color: var(--color-muted);">
                    Showing <strong><?= count($products) ?></strong> of <strong><?= $totalProducts ?></strong> items
                </div>

                <div class="shop-sort-wrap">
                    <label for="sortSelect" style="font-size: 0.88rem; font-weight: 500;">Sort By:</label>
                    <select id="sortSelect" class="custom-select" onchange="location = this.value;">
                        <option value="<?= BASE_URL ?>shop.php?<?= http_build_query(array_merge($_GET, ['sort' => 'newest', 'page' => 1])) ?>" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest Arrivals</option>
                        <option value="<?= BASE_URL ?>shop.php?<?= http_build_query(array_merge($_GET, ['sort' => 'price_asc', 'page' => 1])) ?>" <?= $sortBy === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="<?= BASE_URL ?>shop.php?<?= http_build_query(array_merge($_GET, ['sort' => 'price_desc', 'page' => 1])) ?>" <?= $sortBy === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="<?= BASE_URL ?>shop.php?<?= http_build_query(array_merge($_GET, ['sort' => 'name_asc', 'page' => 1])) ?>" <?= $sortBy === 'name_asc' ? 'selected' : '' ?>>Name: A to Z</option>
                        <option value="<?= BASE_URL ?>shop.php?<?= http_build_query(array_merge($_GET, ['sort' => 'best_selling', 'page' => 1])) ?>" <?= $sortBy === 'best_selling' ? 'selected' : '' ?>>Best Selling</option>
                    </select>
                </div>
            </div>

            <!-- Products List or Empty State -->
            <?php if (!empty($products)): ?>
                <div class="products-grid" style="grid-template-columns: repeat(3, 1fr); margin-top: 0;">
                    <?php foreach ($products as $p): ?>
                        <?php include __DIR__ . '/includes/product-card.php'; ?>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav class="pagination" aria-label="Product list pagination">
                        <?php if ($page > 1): ?>
                            <a class="page-btn" href="<?= BASE_URL ?>shop.php?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" aria-label="Previous Page">
                                <?= hane_icon('chevron-left') ?>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a class="page-btn <?= ($i === $page) ? 'active' : '' ?>" href="<?= BASE_URL ?>shop.php?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a class="page-btn" href="<?= BASE_URL ?>shop.php?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" aria-label="Next Page">
                                <?= hane_icon('chevron-right') ?>
                            </a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>

            <?php else: ?>
                <!-- Empty Results State -->
                <div class="empty-state">
                    <div class="empty-icon-wrap">
                        <?= hane_icon('search') ?>
                    </div>
                    <h2 class="empty-title">No Products Found</h2>
                    <p class="empty-desc">We couldn't find any items matching your selected criteria. Try adjusting your filters or search terms.</p>
                    <a class="btn btn-primary" href="<?= BASE_URL ?>shop.php">
                        Clear All Filters
                    </a>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
