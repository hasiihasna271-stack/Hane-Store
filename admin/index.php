<?php
/**
 * HANÉ Admin - Dashboard
 */
require_once __DIR__ . '/_header.php';

$adminTitle = 'Dashboard Overview';

// 1. KPI Metrics
$totalOrders = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalSales = (float)$pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status != 'Cancelled'")->fetchColumn();
$totalCustomers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status = 1")->fetchColumn();

// Low stock threshold from settings
$lowStockThreshold = (int)get_setting('low_stock_threshold', 5);

// 2. Sales Overview (Past 6 Months from real database orders)
$salesByMonth = [];
for ($i = 5; $i >= 0; $i--) {
    $monthKey = date('Y-m', strtotime("-$i months"));
    $monthLabel = date('M Y', strtotime("-$i months"));
    $salesByMonth[$monthKey] = [
        'label' => $monthLabel,
        'total' => 0.0
    ];
}

$salesQuery = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COALESCE(SUM(total), 0) AS monthly_total 
    FROM orders 
    WHERE status != 'Cancelled' AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY ym
");
while ($row = $salesQuery->fetch(PDO::FETCH_ASSOC)) {
    if (isset($salesByMonth[$row['ym']])) {
        $salesByMonth[$row['ym']]['total'] = (float)$row['monthly_total'];
    }
}

// Find max for bar chart scaling
$maxMonthSales = 1.0;
foreach ($salesByMonth as $sm) {
    if ($sm['total'] > $maxMonthSales) {
        $maxMonthSales = $sm['total'];
    }
}

// 3. Order Status Breakdown
$allStatuses = ['Pending', 'Confirmed', 'Packed', 'Shipped', 'Delivered', 'Cancelled'];
$statusCounts = [];
foreach ($allStatuses as $st) {
    $q = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status = ?");
    $q->execute([$st]);
    $statusCounts[$st] = (int)$q->fetchColumn();
}

// 4. Recent Orders
$recentOrders = $pdo->query("SELECT * FROM orders ORDER BY id DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);

// 5. Low Stock Variants Alert
$lowStockStmt = $pdo->prepare("
    SELECT pv.*, p.name AS product_name, p.featured_image 
    FROM product_variants pv 
    JOIN products p ON p.id = pv.product_id 
    WHERE pv.stock <= ? AND p.status = 1 
    ORDER BY pv.stock ASC 
    LIMIT 6
");
$lowStockStmt->execute([$lowStockThreshold]);
$lowStockVariants = $lowStockStmt->fetchAll(PDO::FETCH_ASSOC);

// 6. Top Selling Products
$topProducts = $pdo->query("
    SELECT p.id, p.name, p.featured_image, p.regular_price, p.sale_price,
           SUM(oi.quantity) AS total_sold, SUM(oi.subtotal) AS total_revenue
    FROM order_items oi
    JOIN products p ON p.id = oi.product_id
    JOIN orders o ON o.id = oi.order_id
    WHERE o.status != 'Cancelled'
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// 7. Recent Customer Registrations
$recentUsers = $pdo->query("SELECT * FROM users ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="admin-page-header">
    <div class="admin-page-title">
        <h1>Store Performance Overview</h1>
        <p>Real-time analytics, inventory monitoring, and fulfillment status.</p>
    </div>
    <div style="display: flex; gap: 0.8rem;">
        <a class="storefront-link" href="<?= BASE_URL ?>admin/orders/index.php">
            <?= hane_icon('bag') ?> Process Orders
        </a>
        <a class="storefront-link" href="<?= BASE_URL ?>admin/products/add.php" style="background: var(--admin-accent); color: white;">
            + Add New Product
        </a>
    </div>
</div>

<!-- KPI Cards -->
<div class="admin-stats-grid">
    <div class="admin-stat-card">
        <div class="admin-stat-icon">
            <?= hane_icon('bag') ?>
        </div>
        <div class="admin-stat-info">
            <h3>Total Orders</h3>
            <div class="value"><?= number_format($totalOrders) ?></div>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-icon">
            <?= hane_icon('tag') ?>
        </div>
        <div class="admin-stat-info">
            <h3>Net Revenue</h3>
            <div class="value"><?= money($totalSales) ?></div>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-icon">
            <?= hane_icon('users') ?>
        </div>
        <div class="admin-stat-info">
            <h3>Customers</h3>
            <div class="value"><?= number_format($totalCustomers) ?></div>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-icon">
            <?= hane_icon('box') ?>
        </div>
        <div class="admin-stat-info">
            <h3>Live Products</h3>
            <div class="value"><?= number_format($totalProducts) ?></div>
        </div>
    </div>
</div>

<!-- Charts & Status Row -->
<div class="admin-dashboard-row">
    <!-- Real Bar Chart: Monthly Sales -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2 class="admin-card-title">Monthly Revenue Trend</h2>
            <span class="muted" style="font-size: 0.8rem;">Past 6 Months</span>
        </div>
        <div class="sales-chart-wrap">
            <?php foreach ($salesByMonth as $sm): 
                $pct = max(6, round(($sm['total'] / $maxMonthSales) * 100));
            ?>
                <div class="chart-bar-col">
                    <span class="chart-bar-amount"><?= ($sm['total'] > 0) ? 'Rs.' . round($sm['total'] / 1000) . 'k' : '0' ?></span>
                    <div class="chart-bar-fill" style="height: <?= $pct ?>%;" title="<?= $sm['label'] ?>: <?= money($sm['total']) ?>"></div>
                    <span class="chart-bar-label"><?= e($sm['label']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Order Status Distribution -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2 class="admin-card-title">Order Status Breakdown</h2>
            <a href="<?= BASE_URL ?>admin/orders/index.php" style="font-size: 0.8rem; text-decoration: underline; color: var(--admin-accent);">View All</a>
        </div>
        <div class="status-list">
            <?php foreach ($statusCounts as $stName => $stCount): ?>
                <div class="status-item">
                    <span class="status-badge status-<?= strtolower($stName) ?>"><?= e($stName) ?></span>
                    <strong style="font-size: 1.1rem; color: var(--admin-accent);"><?= $stCount ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Two-Column Section: Recent Orders & Low Stock Alerts -->
<div class="admin-dashboard-row">
    <!-- Recent Orders Table -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2 class="admin-card-title">Recent Orders</h2>
            <a href="<?= BASE_URL ?>admin/orders/index.php" style="font-size: 0.82rem; font-weight: 600; color: var(--admin-accent); text-decoration: underline;">
                Manage Orders &rarr;
            </a>
        </div>
        <?php if (!empty($recentOrders)): ?>
            <div style="overflow-x: auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $ro): ?>
                            <tr>
                                <td>
                                    <strong style="color: var(--admin-accent);"><?= e($ro['order_number']) ?></strong>
                                    <div class="muted" style="font-size: 0.75rem;"><?= date('M d, H:i', strtotime($ro['created_at'])) ?></div>
                                </td>
                                <td><?= e($ro['customer_name']) ?></td>
                                <td><strong><?= money($ro['total']) ?></strong></td>
                                <td>
                                    <span class="status-badge status-<?= strtolower($ro['status']) ?>"><?= e($ro['status']) ?></span>
                                </td>
                                <td>
                                    <a class="btn-icon" href="<?= BASE_URL ?>admin/orders/detail.php?id=<?= $ro['id'] ?>" title="View Order">
                                        <?= hane_icon('eye') ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="muted">No orders placed yet.</p>
        <?php endif; ?>
    </div>

    <!-- Low Stock Inventory Alerts -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2 class="admin-card-title">Low Stock Alerts (&le; <?= $lowStockThreshold ?>)</h2>
            <a href="<?= BASE_URL ?>admin/inventory/index.php" style="font-size: 0.82rem; font-weight: 600; color: var(--admin-accent); text-decoration: underline;">
                Manage Stock &rarr;
            </a>
        </div>
        <?php if (!empty($lowStockVariants)): ?>
            <div style="display: flex; flex-direction: column; gap: 0.8rem;">
                <?php foreach ($lowStockVariants as $ls): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: #FAF7F1; border-radius: 10px; border: 1px solid var(--admin-border);">
                        <div style="display: flex; gap: 0.6rem; align-items: center;">
                            <img src="<?= get_image_url($ls['featured_image']) ?>" alt="" style="width: 36px; height: 46px; object-fit: cover; border-radius: 4px;">
                            <div>
                                <strong style="font-size: 0.88rem;"><?= e($ls['product_name']) ?></strong>
                                <div class="muted" style="font-size: 0.78rem;">
                                    <?= e($ls['color']) ?> &bull; Size: <?= e($ls['size']) ?>
                                </div>
                            </div>
                        </div>
                        <span class="badge" style="background: <?= ($ls['stock'] <= 0) ? '#fee2e2' : '#fef3c7' ?>; color: <?= ($ls['stock'] <= 0) ? '#991b1b' : '#92400e' ?>; font-weight: 700;">
                            <?= ($ls['stock'] <= 0) ? 'Out of Stock' : $ls['stock'] . ' left' ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="padding: 1.5rem; text-align: center; color: var(--admin-success);">
                &check; All inventory variants are adequately stocked!
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Two-Column Section: Top Selling & New Customers -->
<div class="admin-dashboard-row">
    <!-- Top Selling Products -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2 class="admin-card-title">Top Selling Products</h2>
        </div>
        <?php if (!empty($topProducts)): ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Units Sold</th>
                        <th>Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topProducts as $tp): ?>
                        <tr>
                            <td>
                                <div style="display: flex; gap: 0.8rem; align-items: center;">
                                    <img src="<?= get_image_url($tp['featured_image']) ?>" alt="" style="width: 38px; height: 50px; object-fit: cover; border-radius: 6px;">
                                    <a href="<?= BASE_URL ?>admin/products/edit.php?id=<?= $tp['id'] ?>" style="font-weight: 600; color: var(--admin-accent);">
                                        <?= e($tp['name']) ?>
                                    </a>
                                </div>
                            </td>
                            <td><strong><?= (int)$tp['total_sold'] ?></strong> units</td>
                            <td><?= money($tp['total_revenue']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="muted">No sales data recorded yet.</p>
        <?php endif; ?>
    </div>

    <!-- New Registered Customers -->
    <div class="admin-card">
        <div class="admin-card-header">
            <h2 class="admin-card-title">New Customers</h2>
            <a href="<?= BASE_URL ?>admin/customers/index.php" style="font-size: 0.82rem; font-weight: 600; color: var(--admin-accent); text-decoration: underline;">
                View All &rarr;
            </a>
        </div>
        <?php if (!empty($recentUsers)): ?>
            <div style="display: flex; flex-direction: column; gap: 0.8rem;">
                <?php foreach ($recentUsers as $ru): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0.9rem; background: #FAF7F1; border-radius: 10px;">
                        <div>
                            <strong><?= e($ru['name']) ?></strong>
                            <div class="muted" style="font-size: 0.78rem;"><?= e($ru['email']) ?></div>
                        </div>
                        <span class="muted" style="font-size: 0.75rem;"><?= date('M d, Y', strtotime($ru['created_at'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="muted">No customer accounts registered yet.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
