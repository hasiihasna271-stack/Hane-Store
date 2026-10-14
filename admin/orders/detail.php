<?php
/**
 * HANÉ Admin - Order Details & Fulfillment Manager
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_admin_login();

$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) {
    header('Location: ' . BASE_URL . 'admin/orders/index.php');
    exit;
}

// Handle Status & Payment Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_order_status'])) {
    $newStatus = trim($_POST['status'] ?? '');
    $newPaymentStatus = trim($_POST['payment_status'] ?? '');
    $allowedStatuses = ['Pending', 'Confirmed', 'Packed', 'Shipped', 'Delivered', 'Cancelled'];

    if (in_array($newStatus, $allowedStatuses)) {
        $uStmt = $pdo->prepare("UPDATE orders SET status = ?, payment_status = ? WHERE id = ?");
        $uStmt->execute([$newStatus, $newPaymentStatus, $orderId]);
        set_flash('success', "Order #{$orderId} status successfully updated to {$newStatus}.");
    }
    header('Location: ' . BASE_URL . 'admin/orders/detail.php?id=' . $orderId);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    set_flash('error', 'Order not found.');
    header('Location: ' . BASE_URL . 'admin/orders/index.php');
    exit;
}

$adminTitle = 'Order ' . $order['order_number'];

// Fetch items
$itStmt = $pdo->prepare("SELECT oi.*, p.featured_image FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?");
$itStmt->execute([$orderId]);
$items = $itStmt->fetchAll(PDO::FETCH_ASSOC);

$allStatuses = ['Pending', 'Confirmed', 'Packed', 'Shipped', 'Delivered', 'Cancelled'];
require_once __DIR__ . '/../_header.php';
?>

<div class="admin-page-header">
    <div class="admin-page-title">
        <h1>Order Details: <?= e($order['order_number']) ?></h1>
        <p>Placed on <?= date('F d, Y \a\t h:i A', strtotime($order['created_at'])) ?></p>
    </div>
    <div style="display: flex; gap: 0.8rem;">
        <button class="btn btn-outline" type="button" onclick="window.print()">
            Print Invoice
        </button>
        <a class="btn btn-outline" href="<?= BASE_URL ?>admin/orders/index.php">
            &larr; Back to Orders
        </a>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
    <!-- Left Column: Items Table & Customer Summary -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        
        <!-- Ordered Items -->
        <div class="admin-card">
            <h2 class="admin-card-title" style="margin-bottom: 1.2rem;">Ordered Items</h2>
            <div style="overflow-x: auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Variant</th>
                            <th>Unit Price</th>
                            <th>Quantity</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $it): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; gap: 0.8rem; align-items: center;">
                                        <img src="<?= get_image_url($it['featured_image']) ?>" alt="" style="width: 44px; height: 58px; object-fit: cover; border-radius: 6px; border: 1px solid var(--admin-border);">
                                        <strong><?= e($it['product_name']) ?></strong>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge" style="background: #FAF7F1; border: 1px solid var(--admin-border);">
                                        <?= e($it['color']) ?> &bull; <?= e($it['size']) ?>
                                    </span>
                                </td>
                                <td><?= money($it['price']) ?></td>
                                <td><strong><?= (int)$it['quantity'] ?></strong></td>
                                <td><strong><?= money($it['subtotal']) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Financial Totals Summary -->
            <div style="max-width: 320px; margin-left: auto; margin-top: 1.5rem; font-size: 0.95rem; line-height: 2;">
                <div style="display: flex; justify-content: space-between;">
                    <span class="muted">Subtotal:</span>
                    <span><?= money($order['subtotal']) ?></span>
                </div>
                <?php if ($order['discount'] > 0): ?>
                    <div style="display: flex; justify-content: space-between; color: var(--admin-success);">
                        <span>Coupon Discount:</span>
                        <span>&minus;<?= money($order['discount']) ?></span>
                    </div>
                <?php endif; ?>
                <div style="display: flex; justify-content: space-between;">
                    <span class="muted">Delivery Fee:</span>
                    <span><?= ($order['delivery_charge'] == 0) ? 'FREE' : money($order['delivery_charge']) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; font-weight: 700; font-size: 1.25rem; color: var(--admin-accent); border-top: 2px solid var(--admin-border); margin-top: 0.5rem; padding-top: 0.5rem;">
                    <span>Grand Total:</span>
                    <span><?= money($order['total']) ?></span>
                </div>
            </div>
        </div>

        <!-- Delivery Address Snapshot -->
        <div class="admin-card">
            <h2 class="admin-card-title" style="margin-bottom: 1rem;">Customer &amp; Delivery Snapshot</h2>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div>
                    <strong style="color: var(--admin-accent); font-size: 1.05rem;"><?= e($order['customer_name']) ?></strong><br>
                    <span class="muted"><?= e($order['email']) ?></span><br>
                    <span class="muted">Phone: <?= e($order['phone']) ?></span>
                    <?php if ($order['user_id']): ?>
                        <div style="margin-top: 0.6rem;">
                            <a href="<?= BASE_URL ?>admin/customers/detail.php?id=<?= $order['user_id'] ?>" style="font-size: 0.82rem; text-decoration: underline; color: var(--admin-accent);">
                                View Customer Profile &rarr;
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="font-size: 0.92rem; line-height: 1.6; background: #FAF7F1; padding: 1.2rem; border-radius: 10px; border: 1px solid var(--admin-border);">
                    <strong>Delivery Location:</strong><br>
                    <?= e($order['address_line1']) ?><br>
                    <?php if ($order['address_line2']): ?><?= e($order['address_line2']) ?><br><?php endif; ?>
                    <?= e($order['city']) ?>, <?= e($order['district']) ?><br>
                    <?php if ($order['postal_code']): ?>Postal Code: <?= e($order['postal_code']) ?><br><?php endif; ?>
                </div>
            </div>

            <?php if ($order['notes']): ?>
                <div style="margin-top: 1.2rem; padding: 1rem; background: #fdfae5; border-radius: 8px; border: 1px solid #fae8a4;">
                    <strong>Customer Special Instructions:</strong>
                    <p style="margin-top: 0.2rem; font-size: 0.9rem;"><?= nl2br(e($order['notes'])) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Status & Fulfillment -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        
        <div class="admin-card">
            <h2 class="admin-card-title" style="margin-bottom: 1.2rem;">Order Fulfillment</h2>
            <form method="post" action="<?= BASE_URL ?>admin/orders/detail.php?id=<?= $orderId ?>">
                <div class="form-group">
                    <label class="form-label">Order Status</label>
                    <select name="status" class="form-control" style="background: white; font-weight: 600;">
                        <?php foreach ($allStatuses as $stOption): ?>
                            <option value="<?= $stOption ?>" <?= ($order['status'] === $stOption) ? 'selected' : '' ?>><?= $stOption ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Payment Status</label>
                    <select name="payment_status" class="form-control" style="background: white;">
                        <option value="Unpaid" <?= ($order['payment_status'] === 'Unpaid') ? 'selected' : '' ?>>Unpaid</option>
                        <option value="Paid" <?= ($order['payment_status'] === 'Paid') ? 'selected' : '' ?>>Paid</option>
                        <option value="Refunded" <?= ($order['payment_status'] === 'Refunded') ? 'selected' : '' ?>>Refunded</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Payment Method</label>
                    <input class="form-control" type="text" value="<?= e($order['payment_method']) ?>" disabled style="background: #FAF7F1;">
                </div>

                <button class="btn btn-primary btn-full" type="submit" name="save_order_status">
                    Update Order Status
                </button>
            </form>
        </div>

        <div class="admin-card" style="background: #FAF7F1;">
            <h3 style="font-size: 0.95rem; font-weight: 700; color: var(--admin-accent); margin-bottom: 0.6rem;">Fulfillment Note</h3>
            <p style="font-size: 0.82rem; color: var(--admin-text-muted); line-height: 1.6;">
                Updating the status here immediately updates the customer's live Order Tracker in their account portal.
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../_footer.php'; ?>
