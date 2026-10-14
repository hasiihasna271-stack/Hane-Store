<?php
/**
 * HANÉ - Order Confirmation / Success Page
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$orderNumber = trim($_GET['order'] ?? '');
if (empty($orderNumber)) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$oStmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ? LIMIT 1");
$oStmt->execute([$orderNumber]);
$order = $oStmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

// Fetch order items
$itStmt = $pdo->prepare("SELECT oi.*, p.featured_image FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?");
$itStmt->execute([$order['id']]);
$items = $itStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Order Confirmed #' . $order['order_number'];
include __DIR__ . '/includes/header.php';
?>

<div class="container" style="max-width: 860px; margin: 3rem auto 5rem;">
    <!-- Thank You Hero Card -->
    <div style="background: white; border-radius: var(--radius-xl); border: 1px solid var(--color-line); padding: 3rem; box-shadow: var(--shadow-md); text-align: center;">
        <div style="width: 72px; height: 72px; border-radius: 50%; background: var(--color-success-bg); color: var(--color-success); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1.5rem;">
            <?= hane_icon('check') ?>
        </div>

        <span style="font-size: 0.85rem; font-weight: 700; letter-spacing: 0.15em; text-transform: uppercase; color: var(--color-green); display: block; margin-bottom: 0.4rem;">
            Order Received
        </span>
        <h1 style="font-size: 2.8rem; margin-bottom: 0.8rem;">Thank You for Your Order</h1>
        <p class="muted" style="max-width: 520px; margin: 0 auto 1.5rem; font-size: 1.05rem;">
            Your order has been recorded in our system. We have sent an email receipt to <strong><?= e($order['email']) ?></strong>.
        </p>

        <div style="display: inline-flex; align-items: center; gap: 1rem; background: var(--color-cream); padding: 0.8rem 1.8rem; border-radius: var(--radius-full); border: 1px solid var(--color-line); margin-bottom: 2.5rem;">
            <span>Order Reference: <strong style="color: var(--color-green); font-size: 1.1rem;"><?= e($order['order_number']) ?></strong></span>
            <span>&bull;</span>
            <span class="badge badge-sale" style="background: var(--color-beige); color: var(--color-green);">Status: <?= e($order['status']) ?></span>
        </div>

        <!-- Order Items Breakdown -->
        <div class="table-wrap" style="text-align: left; margin: 0 0 2rem;">
            <table class="hane-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Variant</th>
                        <th>Qty</th>
                        <th>Price</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td>
                                <div style="display: flex; gap: 0.8rem; align-items: center;">
                                    <img src="<?= get_image_url($it['featured_image']) ?>" alt="<?= e($it['product_name']) ?>" style="width: 50px; height: 65px; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid var(--color-line);">
                                    <strong><?= e($it['product_name']) ?></strong>
                                </div>
                            </td>
                            <td><?= e($it['color']) ?> &bull; <?= e($it['size']) ?></td>
                            <td><?= (int)$it['quantity'] ?></td>
                            <td><?= money($it['price']) ?></td>
                            <td style="font-weight: 600; color: var(--color-green);"><?= money($it['subtotal']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Delivery & Payment Summary Grid -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; text-align: left; background: var(--color-cream); padding: 1.8rem; border-radius: var(--radius-lg); border: 1px solid var(--color-line); margin-bottom: 2.5rem;">
            <div>
                <h4 style="font-family: var(--font-serif); font-size: 1.25rem; color: var(--color-green); margin-bottom: 0.6rem;">Delivery Details</h4>
                <div style="font-size: 0.92rem; line-height: 1.6; color: var(--color-charcoal);">
                    <strong><?= e($order['customer_name']) ?></strong><br>
                    <?= e($order['address_line1']) ?><br>
                    <?php if ($order['address_line2']): ?><?= e($order['address_line2']) ?><br><?php endif; ?>
                    <?= e($order['city']) ?>, <?= e($order['district']) ?><br>
                    <?php if ($order['postal_code']): ?>Postal Code: <?= e($order['postal_code']) ?><br><?php endif; ?>
                    Phone: <?= e($order['phone']) ?>
                </div>
            </div>

            <div>
                <h4 style="font-family: var(--font-serif); font-size: 1.25rem; color: var(--color-green); margin-bottom: 0.6rem;">Payment &amp; Totals</h4>
                <div style="font-size: 0.92rem; line-height: 1.8;">
                    <div style="display: flex; justify-content: space-between;">
                        <span class="muted">Subtotal:</span>
                        <span><?= money($order['subtotal']) ?></span>
                    </div>
                    <?php if ($order['discount'] > 0): ?>
                        <div style="display: flex; justify-content: space-between; color: var(--color-success);">
                            <span>Discount (<?= e($order['coupon_code'] ?? 'Coupon') ?>):</span>
                            <span>&minus;<?= money($order['discount']) ?></span>
                        </div>
                    <?php endif; ?>
                    <div style="display: flex; justify-content: space-between;">
                        <span class="muted">Delivery Fee:</span>
                        <span><?= ($order['delivery_charge'] == 0) ? 'FREE' : money($order['delivery_charge']) ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-weight: 700; font-size: 1.15rem; color: var(--color-green); border-top: 1px solid var(--color-line); margin-top: 0.4rem; padding-top: 0.4rem;">
                        <span>Total:</span>
                        <span><?= money($order['total']) ?></span>
                    </div>
                    <div style="margin-top: 0.6rem; font-size: 0.85rem; color: var(--color-muted);">
                        Method: <strong><?= e($order['payment_method']) ?></strong> (<?= e($order['payment_status']) ?>)
                    </div>
                </div>
            </div>
        </div>

        <?php if ($order['payment_method'] === 'Bank Transfer'): ?>
            <div style="background: #eef5ed; border: 1px solid #c3e6cb; border-radius: var(--radius-md); padding: 1.2rem; text-align: left; margin-bottom: 2rem; font-size: 0.9rem;">
                <strong style="color: var(--color-green);">Bank Transfer Instructions:</strong>
                <p style="margin-top: 0.3rem;">Please transfer the total amount of <strong><?= money($order['total']) ?></strong> to:</p>
                <div style="margin-top: 0.5rem; font-family: monospace;">
                    Bank: Commercial Bank of Ceylon<br>
                    Account Name: HANÉ Elegance (Pvt) Ltd<br>
                    Account No: 1000 8945 2311<br>
                    Branch: Colombo 07 Branch<br>
                    Reference: <strong><?= e($order['order_number']) ?></strong>
                </div>
                <p style="margin-top: 0.5rem; color: var(--color-muted); font-size: 0.82rem;">Once transfer is complete, please email a screenshot to hello@hane.lk with your order reference.</p>
            </div>
        <?php endif; ?>

        <!-- Action CTAs -->
        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <?php if (is_user_logged_in()): ?>
                <a class="btn btn-outline" href="<?= BASE_URL ?>account/orders.php">
                    View My Orders
                </a>
            <?php endif; ?>
            <a class="btn btn-primary btn-lg" href="<?= BASE_URL ?>shop.php">
                Continue Shopping <?= hane_icon('arrow-right') ?>
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
