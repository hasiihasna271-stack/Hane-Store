<?php
/**
 * HANÉ - Checkout Page
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Ensure cart is not empty
if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    set_flash('info', 'Your shopping bag is empty. Please add items before checking out.');
    header('Location: ' . BASE_URL . 'shop.php');
    exit;
}

$currentUser = get_current_user_data($pdo);
$userId = $currentUser['id'] ?? null;

// Fetch saved addresses if logged in
$savedAddresses = [];
if ($userId) {
    $addrStmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
    $addrStmt->execute([$userId]);
    $savedAddresses = $addrStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calculate totals server-side
$appliedCoupon = $_SESSION['coupon_code'] ?? null;
$totals = calculate_totals($pdo, $appliedCoupon);

if (empty($totals['items'])) {
    set_flash('error', 'There are no valid items in your bag.');
    header('Location: ' . BASE_URL . 'cart.php');
    exit;
}

$errors = [];

// Handle Place Order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    if (!verify_csrf()) {
        $errors[] = 'Security token invalid or expired. Please resubmit the form.';
    }

    $customerName = trim($_POST['customer_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $paymentMethod = trim($_POST['payment_method'] ?? 'Cash on Delivery');
    $orderNotes = trim($_POST['order_notes'] ?? '');

    // Address fields
    $selectedAddressId = (int)($_POST['saved_address_id'] ?? 0);
    $saveAddress = isset($_POST['save_address']) && $userId;

    $address1 = '';
    $address2 = '';
    $city = '';
    $district = '';
    $postal = '';

    if ($selectedAddressId > 0 && $userId) {
        // Use saved address
        $sAddrStmt = $pdo->prepare("SELECT * FROM addresses WHERE id = ? AND user_id = ?");
        $sAddrStmt->execute([$selectedAddressId, $userId]);
        $sAddr = $sAddrStmt->fetch(PDO::FETCH_ASSOC);
        if ($sAddr) {
            $customerName = $customerName ?: $sAddr['full_name'];
            $phone = $phone ?: $sAddr['phone'];
            $address1 = $sAddr['address_line1'];
            $address2 = $sAddr['address_line2'];
            $city = $sAddr['city'];
            $district = $sAddr['district'];
            $postal = $sAddr['postal_code'];
        } else {
            $errors[] = 'Selected address was not found.';
        }
    } else {
        // Use custom address form fields
        $address1 = trim($_POST['address_line1'] ?? '');
        $address2 = trim($_POST['address_line2'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $district = trim($_POST['district'] ?? '');
        $postal = trim($_POST['postal_code'] ?? '');
    }

    // Server-side validation
    if (empty($customerName)) $errors[] = 'Full name is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
    if (empty($phone)) $errors[] = 'Phone number is required.';
    if (empty($address1)) $errors[] = 'Delivery address is required.';
    if (empty($city)) $errors[] = 'City is required.';
    if (empty($district)) $errors[] = 'District / Province is required.';

    if (!in_array($paymentMethod, ['Cash on Delivery', 'Bank Transfer'])) {
        $paymentMethod = 'Cash on Delivery';
    }

    if (empty($errors)) {
        // Re-calculate totals strictly on server side using database values
        $checkoutTotals = calculate_totals($pdo, $appliedCoupon);

        try {
            // DATABASE TRANSACTION (Requirement 37)
            $pdo->beginTransaction();

            // 1. Validate real-time stock for all variants
            foreach ($checkoutTotals['items'] as $item) {
                $stockCheck = $pdo->prepare("SELECT stock, p.name FROM product_variants pv JOIN products p ON p.id = pv.product_id WHERE pv.id = ? FOR UPDATE");
                $stockCheck->execute([$item['variant_id']]);
                $currStockRow = $stockCheck->fetch(PDO::FETCH_ASSOC);

                if (!$currStockRow || (int)$currStockRow['stock'] < $item['quantity']) {
                    throw new Exception("Stock validation failed: '{$item['name']}' ({$item['color']} / {$item['size']}) only has " . ($currStockRow['stock'] ?? 0) . " available. Please adjust your bag.");
                }
            }

            // 2. Generate unique order number (e.g. HANE-2026-000125)
            $orderNumber = generate_order_number($pdo);

            // 3. Insert Order
            $orderSql = "INSERT INTO orders (
                order_number, user_id, customer_name, email, phone,
                address_line1, address_line2, city, district, postal_code,
                subtotal, discount, delivery_charge, total, coupon_code,
                payment_method, payment_status, status, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Unpaid', 'Pending', ?)";

            $couponCodeVal = $checkoutTotals['coupon'] ? $checkoutTotals['coupon']['code'] : null;

            $orderStmt = $pdo->prepare($orderSql);
            $orderStmt->execute([
                $orderNumber,
                $userId,
                $customerName,
                $email,
                $phone,
                $address1,
                $address2,
                $city,
                $district,
                $postal,
                $checkoutTotals['subtotal'],
                $checkoutTotals['discount'],
                $checkoutTotals['delivery_charge'],
                $checkoutTotals['grand_total'],
                $couponCodeVal,
                $paymentMethod,
                $orderNotes
            ]);

            $orderId = (int)$pdo->lastInsertId();

            // 4. Insert Order Items & Deduct Variant Stock (Prevents Overselling!)
            $itemStmt = $pdo->prepare("INSERT INTO order_items (
                order_id, product_id, variant_id, product_name, color, size, price, quantity, subtotal
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $deductStockStmt = $pdo->prepare("UPDATE product_variants SET stock = stock - ? WHERE id = ?");

            foreach ($checkoutTotals['items'] as $item) {
                $itemStmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['variant_id'],
                    $item['name'],
                    $item['color'],
                    $item['size'],
                    $item['unit_price'],
                    $item['quantity'],
                    $item['subtotal']
                ]);

                // Reduce stock per variant
                $deductStockStmt->execute([$item['quantity'], $item['variant_id']]);
            }

            // 5. Record Coupon Usage if coupon applied
            if ($checkoutTotals['coupon'] && $checkoutTotals['discount'] > 0) {
                $cUseStmt = $pdo->prepare("INSERT INTO coupon_usage (coupon_id, order_id, user_id, discount_amount) VALUES (?, ?, ?, ?)");
                $cUseStmt->execute([
                    $checkoutTotals['coupon']['id'],
                    $orderId,
                    $userId,
                    $checkoutTotals['discount']
                ]);

                $cIncStmt = $pdo->prepare("UPDATE coupons SET times_used = times_used + 1 WHERE id = ?");
                $cIncStmt->execute([$checkoutTotals['coupon']['id']]);
            }

            // 6. Save new address to address book if requested
            if ($saveAddress && $selectedAddressId <= 0 && $userId) {
                $insAddr = $pdo->prepare("INSERT INTO addresses (user_id, full_name, phone, address_line1, address_line2, city, district, postal_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $insAddr->execute([$userId, $customerName, $phone, $address1, $address2, $city, $district, $postal]);
            }

            // Commit Transaction!
            $pdo->commit();

            // Clear session cart & coupon
            $_SESSION['cart'] = [];
            unset($_SESSION['coupon_code']);
            $_SESSION['last_order_number'] = $orderNumber;

            // Redirect to confirmation page (Prevents duplicate order on refresh)
            header('Location: ' . BASE_URL . 'order-success.php?order=' . urlencode($orderNumber));
            exit;

        } catch (Exception $ex) {
            $pdo->rollBack();
            $errors[] = $ex->getMessage();
        }
    }
}

$pageTitle = 'Secure Checkout';
include __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div style="margin: 2.5rem 0 1rem;">
        <h1 class="section-title" style="margin: 0 0 0.4rem;">Checkout</h1>
        <p class="muted text-center">Complete your order details and delivery information.</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div style="max-width: 900px; margin: 0 auto 1.5rem; background: var(--color-danger-bg); border-left: 4px solid var(--color-danger); padding: 1.2rem; border-radius: var(--radius-sm);">
            <strong style="color: var(--color-danger);">Please resolve the following issues:</strong>
            <ul style="margin: 0.5rem 0 0 1.2rem; color: #842029; font-size: 0.92rem; list-style: disc;">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= BASE_URL ?>checkout.php" id="checkoutForm">
        <?= csrf_field() ?>

        <div class="cart-checkout-layout">
            <!-- Left Form Area -->
            <div style="display: flex; flex-direction: column; gap: 2rem;">
                
                <!-- Contact Information -->
                <div class="form-card">
                    <h2 style="font-family: var(--font-serif); font-size: 1.5rem; color: var(--color-green); margin-bottom: 1.2rem;">
                        1. Contact Information
                    </h2>
                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">Full Name *</label>
                            <input class="form-control" type="text" name="customer_name" value="<?= e($_POST['customer_name'] ?? $currentUser['name'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone Number *</label>
                            <input class="form-control" type="tel" name="phone" placeholder="e.g. +94 77 123 4567" value="<?= e($_POST['phone'] ?? $currentUser['phone'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Email Address *</label>
                        <input class="form-control" type="email" name="email" value="<?= e($_POST['email'] ?? $currentUser['email'] ?? '') ?>" required>
                        <small class="muted">Your order receipt and delivery updates will be sent here.</small>
                    </div>
                </div>

                <!-- Delivery Address -->
                <div class="form-card">
                    <h2 style="font-family: var(--font-serif); font-size: 1.5rem; color: var(--color-green); margin-bottom: 1.2rem;">
                        2. Delivery Address
                    </h2>

                    <!-- Saved Addresses Selector (for logged-in users) -->
                    <?php if (!empty($savedAddresses)): ?>
                        <div class="form-group" style="margin-bottom: 1.5rem;">
                            <label class="form-label">Choose from Saved Addresses</label>
                            <select class="form-control" name="saved_address_id" id="savedAddressSelector" onchange="toggleAddressFields(this.value)">
                                <option value="0">-- Enter a New Delivery Address --</option>
                                <?php foreach ($savedAddresses as $addr): ?>
                                    <option value="<?= $addr['id'] ?>" <?= $addr['is_default'] ? 'selected' : '' ?>>
                                        <?= e($addr['full_name']) ?> &ndash; <?= e($addr['address_line1']) ?>, <?= e($addr['city']) ?> (<?= e($addr['district']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div id="newAddressFields" style="<?= !empty($savedAddresses) ? 'display: none;' : '' ?>">
                        <div class="form-group">
                            <label class="form-label">Address Line 1 *</label>
                            <input class="form-control" type="text" name="address_line1" placeholder="House / Building No., Street Name" value="<?= e($_POST['address_line1'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Address Line 2 (Optional)</label>
                            <input class="form-control" type="text" name="address_line2" placeholder="Apartment, Suite, Landmark" value="<?= e($_POST['address_line2'] ?? '') ?>">
                        </div>

                        <div class="form-row-2">
                            <div class="form-group">
                                <label class="form-label">City *</label>
                                <input class="form-control" type="text" name="city" placeholder="e.g. Colombo" value="<?= e($_POST['city'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">District / Province *</label>
                                <input class="form-control" type="text" name="district" placeholder="e.g. Western Province" value="<?= e($_POST['district'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-row-2">
                            <div class="form-group">
                                <label class="form-label">Postal Code</label>
                                <input class="form-control" type="text" name="postal_code" placeholder="e.g. 00700" value="<?= e($_POST['postal_code'] ?? '') ?>">
                            </div>
                            <?php if ($userId): ?>
                                <div class="form-group" style="display: flex; align-items: center; margin-top: 1.8rem;">
                                    <label class="filter-checkbox-label">
                                        <input type="checkbox" name="save_address" value="1" checked>
                                        <span>Save address to my address book</span>
                                    </label>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 1rem; margin-bottom: 0;">
                        <label class="form-label">Order Notes / Delivery Instructions (Optional)</label>
                        <textarea class="form-control" name="order_notes" rows="2" placeholder="Special notes for delivery courier..."><?= e($_POST['order_notes'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Payment Method -->
                <div class="form-card">
                    <h2 style="font-family: var(--font-serif); font-size: 1.5rem; color: var(--color-green); margin-bottom: 1.2rem;">
                        3. Payment Method
                    </h2>

                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <label style="border: 2px solid var(--color-green); padding: 1.2rem; border-radius: var(--radius-md); display: flex; align-items: flex-start; gap: 1rem; background: var(--color-beige-light); cursor: pointer;">
                            <input type="radio" name="payment_method" value="Cash on Delivery" checked style="accent-color: var(--color-green); margin-top: 4px;">
                            <div>
                                <strong style="font-size: 1rem; color: var(--color-green);">Cash on Delivery (COD)</strong>
                                <p class="muted" style="margin-top: 0.2rem; font-size: 0.88rem;">Pay cash upon delivery at your doorstep anywhere in Sri Lanka.</p>
                            </div>
                        </label>

                        <label style="border: 1px solid var(--color-line); padding: 1.2rem; border-radius: var(--radius-md); display: flex; align-items: flex-start; gap: 1rem; background: white; cursor: pointer;">
                            <input type="radio" name="payment_method" value="Bank Transfer" style="accent-color: var(--color-green); margin-top: 4px;">
                            <div>
                                <strong style="font-size: 1rem; color: var(--color-green);">Direct Bank Transfer</strong>
                                <p class="muted" style="margin-top: 0.2rem; font-size: 0.88rem;">Transfer directly to our Commercial Bank account. Payment verification instructions will be provided upon order confirmation.</p>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Order Summary Sidebar -->
            <div>
                <div class="order-summary-card">
                    <h2 class="summary-title">Order Review</h2>

                    <div style="max-height: 260px; overflow-y: auto; margin-bottom: 1.2rem; padding-right: 0.4rem;">
                        <?php foreach ($totals['items'] as $it): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.6rem 0; border-bottom: 1px solid var(--color-line); font-size: 0.9rem;">
                                <div style="display: flex; gap: 0.8rem; align-items: center;">
                                    <img src="<?= get_image_url($it['featured_image']) ?>" alt="<?= e($it['name']) ?>" style="width: 45px; height: 55px; object-fit: cover; border-radius: 6px;">
                                    <div>
                                        <strong><?= e($it['name']) ?></strong>
                                        <div class="muted" style="font-size: 0.78rem;"><?= e($it['color']) ?> &bull; <?= e($it['size']) ?> &times; <?= $it['quantity'] ?></div>
                                    </div>
                                </div>
                                <span style="font-weight: 600;"><?= money($it['subtotal']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="summary-row">
                        <span class="muted">Subtotal</span>
                        <span style="font-weight: 600;"><?= money($totals['subtotal']) ?></span>
                    </div>

                    <?php if ($totals['discount'] > 0): ?>
                        <div class="summary-row" style="color: var(--color-success);">
                            <span>Discount (<?= e($totals['coupon']['code'] ?? 'Coupon') ?>)</span>
                            <span>&minus;<?= money($totals['discount']) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="summary-row">
                        <span class="muted">Delivery Fee</span>
                        <span><?= ($totals['delivery_charge'] == 0) ? '<span style="color: var(--color-success); font-weight: 600;">FREE</span>' : money($totals['delivery_charge']) ?></span>
                    </div>

                    <div class="summary-row total-row">
                        <span>Total Due</span>
                        <span><?= money($totals['grand_total']) ?></span>
                    </div>

                    <div style="margin-top: 1.8rem;">
                        <button class="btn btn-primary btn-full btn-lg" type="submit" name="place_order">
                            Confirm &amp; Place Order <?= hane_icon('arrow-right') ?>
                        </button>
                    </div>

                    <div style="margin-top: 1.2rem; text-align: center; font-size: 0.8rem; color: var(--color-muted); display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
                        <?= hane_icon('shield') ?> Guaranteed 100% Safe &amp; Secure Order
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function toggleAddressFields(val) {
    const fields = document.getElementById('newAddressFields');
    if (fields) {
        fields.style.display = (val === '0') ? 'block' : 'none';
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
