<?php
/**
 * HANÉ - Shopping Bag / Cart Page
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Handle Add to Cart / Buy Now
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_to_cart']) || isset($_POST['buy_now']))) {
    $variantId = (int)($_POST['variant_id'] ?? 0);
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));

    if ($variantId <= 0) {
        set_flash('error', 'Please select both an available color and size before adding to bag.');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? BASE_URL . 'shop.php'));
        exit;
    }

    // Verify variant and check stock
    $vStmt = $pdo->prepare("SELECT pv.*, p.name, p.status FROM product_variants pv JOIN products p ON p.id = pv.product_id WHERE pv.id = ?");
    $vStmt->execute([$variantId]);
    $variant = $vStmt->fetch(PDO::FETCH_ASSOC);

    if (!$variant || $variant['status'] != 1) {
        set_flash('error', 'This item is no longer available.');
        header('Location: ' . BASE_URL . 'shop.php');
        exit;
    }

    if ($variant['stock'] <= 0) {
        set_flash('error', "'{$variant['name']}' ({$variant['color']} / {$variant['size']}) is currently out of stock.");
        header('Location: ' . BASE_URL . 'product.php?id=' . $variant['product_id']);
        exit;
    }

    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $existingQty = (int)($_SESSION['cart'][$variantId] ?? 0);
    $newQty = $existingQty + $quantity;

    if ($newQty > $variant['stock']) {
        $_SESSION['cart'][$variantId] = (int)$variant['stock'];
        set_flash('warning', "We added the maximum available stock ({$variant['stock']} items) to your bag.");
    } else {
        $_SESSION['cart'][$variantId] = $newQty;
        set_flash('success', "'{$variant['name']}' ({$variant['color']} / {$variant['size']}) added to your bag.");
    }

    if (!empty($_POST['buy_now'])) {
        header('Location: ' . BASE_URL . 'checkout.php');
        exit;
    }

    header('Location: ' . BASE_URL . 'cart.php');
    exit;
}

// Handle Quantity Stepper Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    if (isset($_POST['quantities']) && is_array($_POST['quantities'])) {
        foreach ($_POST['quantities'] as $vId => $qty) {
            $vId = (int)$vId;
            $qty = (int)$qty;
            if ($qty <= 0) {
                unset($_SESSION['cart'][$vId]);
            } else {
                // Check stock bound
                $stStmt = $pdo->prepare("SELECT stock FROM product_variants WHERE id = ?");
                $stStmt->execute([$vId]);
                $avail = (int)$stStmt->fetchColumn();
                $_SESSION['cart'][$vId] = min($qty, $avail);
            }
        }
        set_flash('success', 'Your shopping bag was updated.');
    }
    header('Location: ' . BASE_URL . 'cart.php');
    exit;
}

// Handle Remove Single Item
if (isset($_GET['remove'])) {
    $removeId = (int)$_GET['remove'];
    unset($_SESSION['cart'][$removeId]);
    set_flash('info', 'Item removed from your bag.');
    header('Location: ' . BASE_URL . 'cart.php');
    exit;
}

// Handle Clear All Cart
if (isset($_GET['clear']) && $_GET['clear'] == '1') {
    $_SESSION['cart'] = [];
    unset($_SESSION['coupon_code']);
    set_flash('info', 'Your shopping bag has been cleared.');
    header('Location: ' . BASE_URL . 'cart.php');
    exit;
}

// Handle Coupon Application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_coupon'])) {
    $cCode = strtoupper(trim($_POST['coupon_code'] ?? ''));
    if (!empty($cCode)) {
        $_SESSION['coupon_code'] = $cCode;
        $tempTotals = calculate_totals($pdo, $cCode);
        if ($tempTotals['coupon_error']) {
            unset($_SESSION['coupon_code']);
            set_flash('error', $tempTotals['coupon_error']);
        } else {
            set_flash('success', "Coupon '{$cCode}' applied successfully!");
        }
    }
    header('Location: ' . BASE_URL . 'cart.php');
    exit;
}

// Handle Remove Coupon
if (isset($_GET['remove_coupon'])) {
    unset($_SESSION['coupon_code']);
    set_flash('info', 'Coupon removed.');
    header('Location: ' . BASE_URL . 'cart.php');
    exit;
}

$pageTitle = 'Shopping Bag';
$appliedCoupon = $_SESSION['coupon_code'] ?? null;
$totals = calculate_totals($pdo, $appliedCoupon);

include __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div style="margin: 2.5rem 0 1rem;">
        <h1 class="section-title" style="margin: 0 0 0.4rem;">Your Shopping Bag</h1>
        <p class="muted text-center">Review your selected pieces and proceed to checkout.</p>
    </div>

    <?php if (!empty($totals['items'])): ?>
        <div class="cart-checkout-layout">
            <!-- Items Table Area -->
            <div>
                <form method="post" action="<?= BASE_URL ?>cart.php" id="cartUpdateForm">
                    <div class="table-wrap" style="background: white; border-radius: var(--radius-lg); border: 1px solid var(--color-line); box-shadow: var(--shadow-sm); overflow: hidden;">
                        <table class="hane-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Unit Price</th>
                                    <th>Quantity</th>
                                    <th>Subtotal</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($totals['items'] as $item): ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; gap: 1rem; align-items: center;">
                                                <img src="<?= get_image_url($item['featured_image']) ?>" 
                                                     alt="<?= e($item['name']) ?>" 
                                                     style="width: 70px; height: 90px; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid var(--color-line);">
                                                <div>
                                                    <a href="<?= BASE_URL ?>product.php?id=<?= $item['product_id'] ?>" style="font-weight: 600; font-family: var(--font-serif); font-size: 1.15rem; color: var(--color-green);">
                                                        <?= e($item['name']) ?>
                                                    </a>
                                                    <div style="font-size: 0.85rem; color: var(--color-muted); margin-top: 0.2rem; display: flex; align-items: center; gap: 0.6rem;">
                                                        <span>Color: <strong><?= e($item['color']) ?></strong></span>
                                                        <span>&bull;</span>
                                                        <span>Size: <strong><?= e($item['size']) ?></strong></span>
                                                    </div>
                                                    <?php if (!$item['in_stock']): ?>
                                                        <span class="badge badge-out" style="margin-top: 0.4rem; display: inline-block;">Only <?= (int)$item['available_stock'] ?> Left</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="font-weight: 600;">
                                            <?= money($item['unit_price']) ?>
                                        </td>
                                        <td>
                                            <div class="qty-stepper" style="width: fit-content;">
                                                <button type="button" class="qty-btn qty-minus">-</button>
                                                <input type="number" 
                                                       name="quantities[<?= $item['variant_id'] ?>]" 
                                                       class="qty-input" 
                                                       value="<?= $item['quantity'] ?>" 
                                                       min="1" 
                                                       max="<?= (int)$item['available_stock'] ?>" 
                                                       onchange="document.getElementById('cartUpdateForm').submit()">
                                                <button type="button" class="qty-btn qty-plus">+</button>
                                            </div>
                                        </td>
                                        <td style="font-weight: 700; color: var(--color-green); font-size: 1.05rem;">
                                            <?= money($item['subtotal']) ?>
                                        </td>
                                        <td>
                                            <a href="<?= BASE_URL ?>cart.php?remove=<?= $item['variant_id'] ?>" 
                                               style="color: var(--color-danger); display: flex; align-items: center; justify-content: center;" 
                                               title="Remove item">
                                                <?= hane_icon('trash') ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                        <a class="btn btn-outline" href="<?= BASE_URL ?>shop.php">
                            <?= hane_icon('chevron-left') ?> Continue Shopping
                        </a>
                        <div style="display: flex; gap: 0.8rem;">
                            <a class="btn btn-outline" href="<?= BASE_URL ?>cart.php?clear=1" onclick="return confirm('Clear entire shopping bag?');" style="color: var(--color-danger); border-color: var(--color-line);">
                                Clear Bag
                            </a>
                            <button class="btn btn-primary" type="submit" name="update_cart">
                                Update Bag
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Summary & Coupon Sidebar -->
            <div>
                <div class="order-summary-card">
                    <h2 class="summary-title">Bag Summary</h2>

                    <!-- Free shipping progress indicator -->
                    <?php 
                        $freeThreshold = $totals['free_shipping_min'];
                        $percentProgress = min(100, round(($totals['subtotal'] / $freeThreshold) * 100));
                        $neededForFree = max(0, $freeThreshold - $totals['subtotal']);
                    ?>
                    <div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.82rem; margin-bottom: 0.3rem;">
                            <?php if ($neededForFree > 0): ?>
                                <span>Add <strong><?= money($neededForFree) ?></strong> for <strong>FREE Delivery</strong></span>
                            <?php else: ?>
                                <span style="color: var(--color-success); font-weight: 700;">&check; You've unlocked FREE Delivery!</span>
                            <?php endif; ?>
                            <span><?= $percentProgress ?>%</span>
                        </div>
                        <div class="free-shipping-progress">
                            <div class="free-shipping-progress-bar" style="width: <?= $percentProgress ?>%;"></div>
                        </div>
                    </div>

                    <div class="summary-row">
                        <span class="muted">Subtotal (<?= $totals['item_count'] ?> items)</span>
                        <span style="font-weight: 600;"><?= money($totals['subtotal']) ?></span>
                    </div>

                    <?php if ($totals['discount'] > 0): ?>
                        <div class="summary-row" style="color: var(--color-success);">
                            <span>Discount (<?= e($totals['coupon']['code'] ?? 'Coupon') ?>)</span>
                            <span>&minus;<?= money($totals['discount']) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="summary-row">
                        <span class="muted">Estimated Delivery</span>
                        <span><?= ($totals['delivery_charge'] == 0) ? '<span style="color: var(--color-success); font-weight: 600;">FREE</span>' : money($totals['delivery_charge']) ?></span>
                    </div>

                    <div class="summary-row total-row">
                        <span>Grand Total</span>
                        <span><?= money($totals['grand_total']) ?></span>
                    </div>

                    <!-- Coupon Code Input Box -->
                    <div style="margin-top: 1.8rem; padding-top: 1.5rem; border-top: 1px solid var(--color-line);">
                        <?php if ($totals['coupon']): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; background: var(--color-beige-light); padding: 0.7rem 1rem; border-radius: var(--radius-sm); font-size: 0.88rem;">
                                <span>Coupon <strong><?= e($totals['coupon']['code']) ?></strong> applied</span>
                                <a href="<?= BASE_URL ?>cart.php?remove_coupon=1" style="color: var(--color-danger); text-decoration: underline; font-size: 0.8rem;">Remove</a>
                            </div>
                        <?php else: ?>
                            <form method="post" action="<?= BASE_URL ?>cart.php" style="display: flex; gap: 0.5rem;">
                                <input type="text" name="coupon_code" class="form-control" placeholder="Promo code (e.g. ELEGANCE10)" style="text-transform: uppercase; font-size: 0.85rem;" required>
                                <button class="btn btn-outline btn-sm" type="submit" name="apply_coupon">Apply</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div style="margin-top: 1.8rem;">
                        <a class="btn btn-primary btn-full btn-lg" href="<?= BASE_URL ?>checkout.php">
                            Proceed to Checkout <?= hane_icon('arrow-right') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- Empty Cart State -->
        <div class="empty-state">
            <div class="empty-icon-wrap">
                <?= hane_icon('bag') ?>
            </div>
            <h2 class="empty-title">Your Bag is Empty</h2>
            <p class="empty-desc">You haven’t added any items to your shopping bag yet. Explore our latest arrivals and timeless wardrobe staples.</p>
            <a class="btn btn-primary btn-lg" href="<?= BASE_URL ?>shop.php">
                Discover The Collection <?= hane_icon('arrow-right') ?>
            </a>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
