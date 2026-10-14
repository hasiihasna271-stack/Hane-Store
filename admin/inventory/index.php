<?php
/**
 * HANÉ Admin - Inventory & Stock Management
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_admin_login();

$adminTitle = 'Inventory Management';
$lowStockThreshold = (int)get_setting('low_stock_threshold', 5);

// Handle Single Variant Stock Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stock'])) {
    $variantId = (int)($_POST['variant_id'] ?? 0);
    $newStock = max(0, (int)($_POST['stock'] ?? 0));

    if ($variantId > 0) {
        $uStmt = $pdo->prepare("UPDATE product_variants SET stock = ? WHERE id = ?");
        $uStmt->execute([$newStock, $variantId]);
        set_flash('success', "Stock updated to {$newStock} units.");
    }
    header('Location: ' . BASE_URL . 'admin/inventory/index.php');
    exit;
}

// Filters
$stockFilter = $_GET['filter'] ?? 'all';
$q = trim($_GET['q'] ?? '');

$where = ["p.status = 1"];
$params = [];

if ($q !== '') {
    $where[] = "(p.name LIKE ? OR pv.color LIKE ? OR pv.sku LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

if ($stockFilter === 'low') {
    $where[] = "pv.stock <= ? AND pv.stock > 0";
    $params[] = $lowStockThreshold;
} elseif ($stockFilter === 'out') {
    $where[] = "pv.stock <= 0";
}

$whereClause = implode(" AND ", $where);

$sql = "
    SELECT pv.*, p.name AS product_name, p.featured_image, p.regular_price, p.sale_price, c.name AS category_name
    FROM product_variants pv
    JOIN products p ON p.id = pv.product_id
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE $whereClause
    ORDER BY pv.stock ASC, p.name ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$variants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Inventory Summary Metrics
$totalUnits = (int)$pdo->query("SELECT COALESCE(SUM(pv.stock), 0) FROM product_variants pv JOIN products p ON p.id = pv.product_id WHERE p.status = 1")->fetchColumn();
$lowStockCount = (int)$pdo->prepare("SELECT COUNT(*) FROM product_variants pv JOIN products p ON p.id = pv.product_id WHERE p.status = 1 AND pv.stock <= ? AND pv.stock > 0");
$lowStockCount->execute([$lowStockThreshold]);
$lowStockTotal = (int)$lowStockCount->fetchColumn();

$outOfStockCount = (int)$pdo->query("SELECT COUNT(*) FROM product_variants pv JOIN products p ON p.id = pv.product_id WHERE p.status = 1 AND pv.stock <= 0")->fetchColumn();
require_once __DIR__ . '/../_header.php';
?>

<div class="admin-page-header">
    <div class="admin-page-title">
        <h1>Inventory &amp; Stock Levels</h1>
        <p>Monitor and adjust stock quantities per specific Color and Size combination.</p>
    </div>
</div>

<!-- Stock KPI Summary Cards -->
<div class="admin-stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 2rem;">
    <div class="admin-stat-card">
        <div class="admin-stat-icon">
            <?= hane_icon('box') ?>
        </div>
        <div class="admin-stat-info">
            <h3>Total Inventory Units</h3>
            <div class="value"><?= number_format($totalUnits) ?></div>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-icon" style="background: #fef3c7; color: #92400e;">
            <?= hane_icon('shield') ?>
        </div>
        <div class="admin-stat-info">
            <h3>Low Stock Variants (&le; <?= $lowStockThreshold ?>)</h3>
            <div class="value" style="color: #92400e;"><?= $lowStockTotal ?></div>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-icon" style="background: #fee2e2; color: #991b1b;">
            <?= hane_icon('trash') ?>
        </div>
        <div class="admin-stat-info">
            <h3>Out of Stock Combinations</h3>
            <div class="value" style="color: #991b1b;"><?= $outOfStockCount ?></div>
        </div>
    </div>
</div>

<div class="admin-card">
    <!-- Toolbar Filters -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; gap: 0.5rem;">
            <a href="<?= BASE_URL ?>admin/inventory/index.php" class="btn btn-sm <?= ($stockFilter === 'all') ? 'btn-primary' : 'btn-outline' ?>">All Variants</a>
            <a href="<?= BASE_URL ?>admin/inventory/index.php?filter=low" class="btn btn-sm <?= ($stockFilter === 'low') ? 'btn-primary' : 'btn-outline' ?>">Low Stock (&le; <?= $lowStockThreshold ?>)</a>
            <a href="<?= BASE_URL ?>admin/inventory/index.php?filter=out" class="btn btn-sm <?= ($stockFilter === 'out') ? 'btn-primary' : 'btn-outline' ?>">Out of Stock (0)</a>
        </div>

        <form method="get" action="<?= BASE_URL ?>admin/inventory/index.php" style="display: flex; gap: 0.5rem;">
            <input type="hidden" name="filter" value="<?= e($stockFilter) ?>">
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search product, color, or SKU..." class="form-control" style="background: white; padding: 0.5rem 1rem; font-size: 0.88rem;">
            <button class="btn btn-outline btn-sm" type="submit">Search</button>
        </form>
    </div>

    <div style="overflow-x: auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Color</th>
                    <th>Size</th>
                    <th>SKU</th>
                    <th>Stock Status</th>
                    <th>Quick Stock Adjustment</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($variants)): ?>
                    <?php foreach ($variants as $v): ?>
                        <tr>
                            <td>
                                <div style="display: flex; gap: 0.8rem; align-items: center;">
                                    <img src="<?= get_image_url($v['featured_image']) ?>" alt="" style="width: 38px; height: 50px; object-fit: cover; border-radius: 6px; border: 1px solid var(--admin-border);">
                                    <div>
                                        <a href="<?= BASE_URL ?>admin/products/edit.php?id=<?= $v['product_id'] ?>" style="font-weight: 600; color: var(--admin-accent);">
                                            <?= e($v['product_name']) ?>
                                        </a>
                                        <div class="muted" style="font-size: 0.75rem;"><?= e($v['category_name'] ?? '') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span class="color-dot" style="background-color: <?= e($v['color_code']) ?>; display: inline-block;"></span>
                                    <span><?= e($v['color']) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge" style="background: #FAF7F1; border: 1px solid var(--admin-border); font-weight: 700;">
                                    <?= e($v['size']) ?>
                                </span>
                            </td>
                            <td><code><?= e($v['sku'] ?: '&mdash;') ?></code></td>
                            <td>
                                <?php if ($v['stock'] <= 0): ?>
                                    <span class="status-badge status-cancelled">Out of Stock</span>
                                <?php elseif ($v['stock'] <= $lowStockThreshold): ?>
                                    <span class="status-badge status-pending">Low: <?= $v['stock'] ?> units</span>
                                <?php else: ?>
                                    <span class="status-badge status-delivered"><?= $v['stock'] ?> in stock</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" action="<?= BASE_URL ?>admin/inventory/index.php" style="display: flex; gap: 0.4rem; align-items: center;">
                                    <input type="hidden" name="variant_id" value="<?= $v['id'] ?>">
                                    <input type="number" name="stock" value="<?= (int)$v['stock'] ?>" min="0" class="form-control" style="width: 80px; padding: 0.35rem 0.6rem; font-size: 0.88rem; background: white;" required>
                                    <button class="btn btn-outline btn-sm" type="submit" name="update_stock" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;">
                                        Update
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 3rem; color: var(--admin-text-muted);">
                            No variants found matching the criteria.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../_footer.php'; ?>
