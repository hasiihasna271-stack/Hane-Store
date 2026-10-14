<?php
/**
 * HANÉ Admin - Orders List & Status Updates
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_admin_login();

$adminTitle = 'Customer Orders';

// Handle Inline Status Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $allowed = ['Pending', 'Confirmed', 'Packed', 'Shipped', 'Delivered', 'Cancelled'];
    $rawOrderId = $_POST['order_id'] ?? null;
    $orderId = is_string($rawOrderId) ? filter_var($rawOrderId, FILTER_VALIDATE_INT) : false;
    $newStatus = $_POST['status'] ?? null;

    if (!verify_csrf()) {
        set_flash('error', 'Your session has expired. Please try again.');
    } elseif ($orderId !== false && $orderId > 0 && is_string($newStatus) && in_array($newStatus, $allowed, true)) {
        $uStmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $uStmt->execute([$newStatus, $orderId]);
        set_flash('success', "Order #{$orderId} status changed to {$newStatus}.");
    } else {
        set_flash('error', 'Invalid order or status. Please try again.');
    }
    header('Location: ' . BASE_URL . 'admin/orders/index.php');
    exit;
}

require_once __DIR__ . '/../_header.php';

// Filters & Search
$q = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$where = ["1=1"];
$params = [];

if ($q !== '') {
    $where[] = "(order_number LIKE ? OR customer_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

if ($statusFilter !== '') {
    $where[] = "status = ?";
    $params[] = $statusFilter;
}

if ($dateFrom !== '') {
    $where[] = "DATE(created_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo !== '') {
    $where[] = "DATE(created_at) <= ?";
    $params[] = $dateTo;
}

$whereClause = implode(" AND ", $where);

$sql = "
    SELECT o.*,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count
    FROM orders o
    WHERE $whereClause
    ORDER BY o.id DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$allStatuses = ['Pending', 'Confirmed', 'Packed', 'Shipped', 'Delivered', 'Cancelled'];
?>

<div class="admin-page-header">
    <div class="admin-page-title">
        <h1>Orders Management</h1>
        <p>Review customer purchases, inspect address details, and update dispatch status.</p>
    </div>
</div>

<div class="admin-card">
    <!-- Filter Toolbar -->
    <form method="get" action="<?= BASE_URL ?>admin/orders/index.php" style="display: flex; gap: 0.8rem; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 200px;">
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search Order #, customer, email, phone..." class="form-control" style="background: white;">
        </div>

        <div style="min-width: 150px;">
            <select name="status" class="form-control" style="background: white;" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <?php foreach ($allStatuses as $st): ?>
                    <option value="<?= $st ?>" <?= ($statusFilter === $st) ? 'selected' : '' ?>><?= $st ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: flex; align-items: center; gap: 0.4rem;">
            <input type="date" name="date_from" value="<?= e($dateFrom) ?>" class="form-control" style="background: white; padding: 0.5rem;" title="From Date">
            <span>to</span>
            <input type="date" name="date_to" value="<?= e($dateTo) ?>" class="form-control" style="background: white; padding: 0.5rem;" title="To Date">
        </div>

        <button class="btn btn-outline btn-sm" type="submit">Filter</button>
        <?php if ($q || $statusFilter || $dateFrom || $dateTo): ?>
            <a class="btn btn-outline btn-sm" href="<?= BASE_URL ?>admin/orders/index.php">Reset</a>
        <?php endif; ?>
    </form>

    <div style="overflow-x: auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Items</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Fulfillment Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($orders)): ?>
                    <?php foreach ($orders as $ord): ?>
                        <tr>
                            <td>
                                <a href="<?= BASE_URL ?>admin/orders/detail.php?id=<?= $ord['id'] ?>" style="font-weight: 700; color: var(--admin-accent);">
                                    <?= e($ord['order_number']) ?>
                                </a>
                            </td>
                            <td class="muted" style="font-size: 0.82rem;">
                                <?= date('M d, Y', strtotime($ord['created_at'])) ?><br>
                                <?= date('h:i A', strtotime($ord['created_at'])) ?>
                            </td>
                            <td>
                                <strong><?= e($ord['customer_name']) ?></strong>
                                <div class="muted" style="font-size: 0.78rem;"><?= e($ord['phone']) ?></div>
                                <div class="muted" style="font-size: 0.78rem;"><?= e($ord['city']) ?>, <?= e($ord['district']) ?></div>
                            </td>
                            <td><?= (int)$ord['item_count'] ?> items</td>
                            <td><strong><?= money($ord['total']) ?></strong></td>
                            <td>
                                <span style="font-size: 0.85rem; font-weight: 500;"><?= e($ord['payment_method']) ?></span><br>
                                <span class="badge" style="background: #FAF7F1; border: 1px solid var(--admin-border); font-size: 0.72rem;"><?= e($ord['payment_status']) ?></span>
                            </td>
                            <td>
                                <form method="post" action="<?= BASE_URL ?>admin/orders/index.php" style="margin: 0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                                    <input type="hidden" name="update_status" value="1">
                                    <select name="status" class="form-control auto-submit-status" data-original="<?= e($ord['status']) ?>" style="background: white; padding: 0.35rem 0.6rem; font-size: 0.85rem; width: 130px; font-weight: 600;">
                                        <?php foreach ($allStatuses as $stOption): ?>
                                            <option value="<?= $stOption ?>" <?= ($ord['status'] === $stOption) ? 'selected' : '' ?>><?= $stOption ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <a class="btn-icon" href="<?= BASE_URL ?>admin/orders/detail.php?id=<?= $ord['id'] ?>" title="Open order details">
                                        <?= hane_icon('eye') ?>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 3rem; color: var(--admin-text-muted);">
                            No orders found matching the filter criteria.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../_footer.php'; ?>
