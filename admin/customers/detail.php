<?php
/**
 * HANÉ Admin - Customer Profile & History
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_admin_login();

$userId = (int)($_GET['id'] ?? 0);
if ($userId <= 0) {
    header('Location: ' . BASE_URL . 'admin/customers/index.php');
    exit;
}

$uStmt = $pdo->prepare("SELECT id, name, email, phone, created_at FROM users WHERE id = ?");
$uStmt->execute([$userId]);
$customer = $uStmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    set_flash('error', 'Customer not found.');
    header('Location: ' . BASE_URL . 'admin/customers/index.php');
    exit;
}

$adminTitle = 'Customer: ' . $customer['name'];

// Fetch customer orders
$oStmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
$oStmt->execute([$userId]);
$customerOrders = $oStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch saved addresses
$aStmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
$aStmt->execute([$userId]);
$customerAddresses = $aStmt->fetchAll(PDO::FETCH_ASSOC);
require_once __DIR__ . '/../_header.php';
?>

<div class="admin-page-header">
    <div class="admin-page-title">
        <h1>Client Profile: <?= e($customer['name']) ?></h1>
        <p>Member since <?= date('F d, Y', strtotime($customer['created_at'])) ?></p>
    </div>
    <div>
        <a class="btn btn-outline" href="<?= BASE_URL ?>admin/customers/index.php">
            &larr; Back to Customers
        </a>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem;">
    <!-- Customer Profile & Addresses -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <div class="admin-card">
            <h2 class="admin-card-title" style="margin-bottom: 1rem;">Contact Info</h2>
            <div style="font-size: 0.95rem; line-height: 1.8;">
                <div>Name: <strong><?= e($customer['name']) ?></strong></div>
                <div>Email: <a href="mailto:<?= e($customer['email']) ?>" style="color: var(--admin-accent); text-decoration: underline;"><?= e($customer['email']) ?></a></div>
                <div>Phone: <?= e($customer['phone'] ?: '&mdash;') ?></div>
            </div>
        </div>

        <div class="admin-card">
            <h2 class="admin-card-title" style="margin-bottom: 1rem;">Saved Addresses (<?= count($customerAddresses) ?>)</h2>
            <?php if (!empty($customerAddresses)): ?>
                <div style="display: flex; flex-direction: column; gap: 0.8rem;">
                    <?php foreach ($customerAddresses as $addr): ?>
                        <div style="background: #FAF7F1; padding: 0.9rem; border-radius: 8px; border: 1px solid var(--admin-border); font-size: 0.88rem; line-height: 1.5;">
                            <?php if ($addr['is_default']): ?>
                                <span class="badge badge-new" style="font-size: 0.65rem; margin-bottom: 0.3rem; display: inline-block;">Default</span><br>
                            <?php endif; ?>
                            <?= e($addr['full_name']) ?><br>
                            <?= e($addr['address_line1']) ?><br>
                            <?= e($addr['city']) ?>, <?= e($addr['district']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="muted" style="font-size: 0.85rem;">No saved addresses found.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Order History -->
    <div class="admin-card">
        <h2 class="admin-card-title" style="margin-bottom: 1.2rem;">Order History (<?= count($customerOrders) ?>)</h2>
        <?php if (!empty($customerOrders)): ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customerOrders as $co): ?>
                        <tr>
                            <td><strong><?= e($co['order_number']) ?></strong></td>
                            <td class="muted" style="font-size: 0.82rem;"><?= date('M d, Y', strtotime($co['created_at'])) ?></td>
                            <td><?= money($co['total']) ?></td>
                            <td><?= e($co['payment_method']) ?></td>
                            <td>
                                <span class="status-badge status-<?= strtolower($co['status']) ?>"><?= e($co['status']) ?></span>
                            </td>
                            <td>
                                <a class="btn-icon" href="<?= BASE_URL ?>admin/orders/detail.php?id=<?= $co['id'] ?>" title="View Order">
                                    <?= hane_icon('eye') ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="muted">This customer has not placed any orders yet.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../_footer.php'; ?>
