<?php
/**
 * HANÉ Admin - Financial & Sales Reports
 */
require_once __DIR__ . '/../_header.php';

$adminTitle = 'Business Analytics & Reports';

$dateFrom = trim($_GET['date_from'] ?? date('Y-m-01'));
$dateTo = trim($_GET['date_to'] ?? date('Y-m-d'));

$params = [$dateFrom, $dateTo];

// Financial Summary within Date Range
$finSql = "
    SELECT 
        COUNT(*) AS order_count,
        COALESCE(SUM(total), 0) AS total_revenue,
        COALESCE(SUM(subtotal), 0) AS gross_subtotal,
        COALESCE(SUM(discount), 0) AS total_discounts,
        COALESCE(SUM(delivery_charge), 0) AS total_delivery,
        COALESCE(AVG(total), 0) AS avg_order_value
    FROM orders
    WHERE status != 'Cancelled' AND DATE(created_at) BETWEEN ? AND ?
";
$finStmt = $pdo->prepare($finSql);
$finStmt->execute($params);
$finMetrics = $finStmt->fetch(PDO::FETCH_ASSOC);

// Orders by Status in Range
$stSql = "
    SELECT status, COUNT(*) AS count, COALESCE(SUM(total), 0) AS status_total
    FROM orders
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY status
";
$stStmt = $pdo->prepare($stSql);
$stStmt->execute($params);
$statusBreakdown = $stStmt->fetchAll(PDO::FETCH_ASSOC);

// Top Selling Products in Range
$topSql = "
    SELECT p.name, p.featured_image, c.name AS category_name,
           SUM(oi.quantity) AS total_units,
           SUM(oi.subtotal) AS revenue
    FROM order_items oi
    JOIN products p ON p.id = oi.product_id
    LEFT JOIN categories c ON c.id = p.category_id
    JOIN orders o ON o.id = oi.order_id
    WHERE o.status != 'Cancelled' AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY p.id
    ORDER BY total_units DESC
    LIMIT 6
";
$topStmt = $pdo->prepare($topSql);
$topStmt->execute($params);
$topProducts = $topStmt->fetchAll(PDO::FETCH_ASSOC);

// Daily Sales within Range
$daySql = "
    SELECT DATE(created_at) AS sale_date, COUNT(*) AS orders_count, COALESCE(SUM(total), 0) AS day_total
    FROM orders
    WHERE status != 'Cancelled' AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY sale_date
    ORDER BY sale_date DESC
";
$dayStmt = $pdo->prepare($daySql);
$dayStmt->execute($params);
$dailySales = $dayStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="admin-page-header">
    <div class="admin-page-title">
        <h1>Financial &amp; Sales Reports</h1>
        <p>Analyze performance metrics, revenue drivers, and volume trends.</p>
    </div>
    <div>
        <button class="btn btn-outline" type="button" onclick="window.print()">
            Print Report
        </button>
    </div>
</div>

<!-- Date Filter Card -->
<div class="admin-card" style="margin-bottom: 2rem;">
    <form method="get" action="<?= BASE_URL ?>admin/reports/index.php" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
        <div>
            <label class="form-label">Start Date</label>
            <input class="form-control" type="date" name="date_from" value="<?= e($dateFrom) ?>" style="background: white;" required>
        </div>
        <div>
            <label class="form-label">End Date</label>
            <input class="form-control" type="date" name="date_to" value="<?= e($dateTo) ?>" style="background: white;" required>
        </div>
        <button class="btn btn-primary" type="submit">Generate Report</button>
        <a class="btn btn-outline" href="<?= BASE_URL ?>admin/reports/index.php">Current Month</a>
    </form>
</div>

<!-- KPI Cards -->
<div class="admin-stats-grid">
    <div class="admin-stat-card">
        <div class="admin-stat-icon"><?= hane_icon('tag') ?></div>
        <div class="admin-stat-info">
            <h3>Net Revenue</h3>
            <div class="value"><?= money($finMetrics['total_revenue']) ?></div>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-icon"><?= hane_icon('bag') ?></div>
        <div class="admin-stat-info">
            <h3>Orders Count</h3>
            <div class="value"><?= number_format($finMetrics['order_count']) ?></div>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-icon"><?= hane_icon('dashboard') ?></div>
        <div class="admin-stat-info">
            <h3>Avg. Order Value (AOV)</h3>
            <div class="value"><?= money($finMetrics['avg_order_value']) ?></div>
        </div>
    </div>

    <div class="admin-stat-card">
        <div class="admin-stat-icon"><?= hane_icon('shield') ?></div>
        <div class="admin-stat-info">
            <h3>Discounts Granted</h3>
            <div class="value"><?= money($finMetrics['total_discounts']) ?></div>
        </div>
    </div>
</div>

<!-- Two-Column Breakdown Row -->
<div class="admin-dashboard-row">
    <!-- Top Selling in Period -->
    <div class="admin-card">
        <h2 class="admin-card-title" style="margin-bottom: 1.2rem;">Best Performing Products</h2>
        <?php if (!empty($topProducts)): ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Sold Qty</th>
                        <th>Total Sales</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topProducts as $tp): ?>
                        <tr>
                            <td>
                                <div style="display: flex; gap: 0.8rem; align-items: center;">
                                    <img src="<?= get_image_url($tp['featured_image']) ?>" alt="" style="width: 36px; height: 48px; object-fit: cover; border-radius: 4px;">
                                    <strong><?= e($tp['name']) ?></strong>
                                </div>
                            </td>
                            <td><?= e($tp['category_name'] ?? '&mdash;') ?></td>
                            <td><strong><?= (int)$tp['total_units'] ?></strong> units</td>
                            <td><?= money($tp['revenue']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="muted">No sales found in this period.</p>
        <?php endif; ?>
    </div>

    <!-- Orders by Status in Period -->
    <div class="admin-card">
        <h2 class="admin-card-title" style="margin-bottom: 1.2rem;">Orders by Status</h2>
        <div class="status-list">
            <?php foreach ($statusBreakdown as $sb): ?>
                <div class="status-item">
                    <div>
                        <span class="status-badge status-<?= strtolower($sb['status']) ?>"><?= e($sb['status']) ?></span>
                        <span class="muted" style="font-size: 0.8rem; margin-left: 0.5rem;"><?= (int)$sb['count'] ?> orders</span>
                    </div>
                    <strong><?= money($sb['status_total']) ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Daily Sales Breakdown Table -->
<div class="admin-card">
    <h2 class="admin-card-title" style="margin-bottom: 1.2rem;">Daily Sales Activity</h2>
    <?php if (!empty($dailySales)): ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Orders Completed</th>
                    <th>Total Revenue</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dailySales as $ds): ?>
                    <tr>
                        <td><strong><?= date('l, M d, Y', strtotime($ds['sale_date'])) ?></strong></td>
                        <td><?= (int)$ds['orders_count'] ?> orders</td>
                        <td><strong style="color: var(--admin-accent);"><?= money($ds['day_total']) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="muted">No sales recorded on the selected dates.</p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../_footer.php'; ?>
