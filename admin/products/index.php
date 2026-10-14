<?php
/**
 * HANÉ Admin - Products List
 */
require_once __DIR__ . '/../_header.php';

$adminTitle = 'Product Catalog';

// Filters & Search
$q = trim($_GET['q'] ?? '');
$catId = (int)($_GET['category_id'] ?? 0);

$where = ["1=1"];
$params = [];

if ($q !== '') {
    $where[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

if ($catId > 0) {
    $where[] = "p.category_id = ?";
    $params[] = $catId;
}

$whereClause = implode(" AND ", $where);

$sql = "
    SELECT p.*, c.name AS category_name,
           (SELECT COALESCE(SUM(stock), 0) FROM product_variants WHERE product_id = p.id) AS total_stock,
           (SELECT COUNT(*) FROM product_variants WHERE product_id = p.id) AS variant_count
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE $whereClause
    ORDER BY p.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch categories for filter dropdown
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="admin-page-header">
    <div class="admin-page-title">
        <h1>Product Catalog</h1>
        <p>Manage styles, prices, visual assets, and inventory variants.</p>
    </div>
    <div>
        <a class="btn btn-primary" href="<?= BASE_URL ?>admin/products/add.php">
            + Add New Product
        </a>
    </div>
</div>

<div class="admin-card">
    <!-- Filter Toolbar -->
    <form method="get" action="<?= BASE_URL ?>admin/products/index.php" style="display: flex; gap: 1rem; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 200px;">
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search product name or description..." class="form-control" style="background: white;">
        </div>
        <div style="min-width: 180px;">
            <select name="category_id" class="form-control" style="background: white;" onchange="this.form.submit()">
                <option value="0">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= ($catId === (int)$cat['id']) ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-outline btn-sm" type="submit">Filter</button>
        <?php if ($q || $catId): ?>
            <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>admin/products/index.php">Reset</a>
        <?php endif; ?>
    </form>

    <div style="overflow-x: auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Regular Price</th>
                    <th>Sale Price</th>
                    <th>Variants</th>
                    <th>Total Stock</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td>
                                <div style="display: flex; gap: 0.9rem; align-items: center;">
                                    <img src="<?= get_image_url($p['featured_image']) ?>" alt="" style="width: 44px; height: 58px; object-fit: cover; border-radius: 6px; border: 1px solid var(--admin-border);">
                                    <div>
                                        <a href="<?= BASE_URL ?>admin/products/edit.php?id=<?= $p['id'] ?>" style="font-weight: 600; color: var(--admin-accent); font-size: 0.95rem;">
                                            <?= e($p['name']) ?>
                                        </a>
                                        <div style="font-size: 0.75rem; color: var(--admin-text-muted); margin-top: 0.2rem;">
                                            Slug: <?= e($p['slug']) ?>
                                            <?php if ($p['is_featured']): ?><span class="badge" style="background: #e0f2fe; color: #0369a1; padding: 2px 6px;">Featured</span><?php endif; ?>
                                            <?php if ($p['is_new']): ?><span class="badge" style="background: #dcfce7; color: #15803d; padding: 2px 6px;">New</span><?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td><?= e($p['category_name'] ?? 'Uncategorized') ?></td>
                            <td><?= money($p['regular_price']) ?></td>
                            <td>
                                <?= ($p['sale_price'] !== null && $p['sale_price'] > 0) ? '<span style="color: var(--admin-danger); font-weight: 600;">' . money($p['sale_price']) . '</span>' : '&mdash;' ?>
                            </td>
                            <td>
                                <span class="badge" style="background: #FAF7F1; border: 1px solid var(--admin-border); color: var(--admin-text-main);">
                                    <?= (int)$p['variant_count'] ?> combinations
                                </span>
                            </td>
                            <td>
                                <?php if ($p['total_stock'] <= 0): ?>
                                    <span class="status-badge status-cancelled">Out of Stock</span>
                                <?php elseif ($p['total_stock'] <= 10): ?>
                                    <span class="status-badge status-pending"><?= $p['total_stock'] ?> units (Low)</span>
                                <?php else: ?>
                                    <span class="status-badge status-delivered"><?= $p['total_stock'] ?> units</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge <?= $p['status'] ? 'status-delivered' : 'status-cancelled' ?>">
                                    <?= $p['status'] ? 'Active' : 'Draft' ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <a class="btn-icon" href="<?= BASE_URL ?>product.php?id=<?= $p['id'] ?>" target="_blank" title="View on storefront">
                                        <?= hane_icon('eye') ?>
                                    </a>
                                    <a class="btn-icon" href="<?= BASE_URL ?>admin/products/edit.php?id=<?= $p['id'] ?>" title="Edit product">
                                        <?= hane_icon('dashboard') ?>
                                    </a>
                                    <a class="btn-icon btn-danger" href="<?= BASE_URL ?>admin/products/delete.php?id=<?= $p['id'] ?>" onclick="return confirm('Are you sure you want to delete this product and all its variants?');" title="Delete product">
                                        <?= hane_icon('trash') ?>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 3rem; color: var(--admin-text-muted);">
                            No products match your search criteria.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../_footer.php'; ?>
