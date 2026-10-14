<?php
/**
 * HANÉ Admin - Edit Coupon
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_admin_login();

$couponId = (int)($_GET['id'] ?? 0);
if ($couponId <= 0) {
    header('Location: ' . BASE_URL . 'admin/coupons/index.php');
    exit;
}

$cStmt = $pdo->prepare("SELECT * FROM coupons WHERE id = ?");
$cStmt->execute([$couponId]);
$coupon = $cStmt->fetch(PDO::FETCH_ASSOC);

if (!$coupon) {
    set_flash('error', 'Coupon not found.');
    header('Location: ' . BASE_URL . 'admin/coupons/index.php');
    exit;
}

$adminTitle = 'Edit Coupon: ' . $coupon['code'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $type = trim($_POST['discount_type'] ?? 'percentage');
    $value = (float)($_POST['discount_value'] ?? 0);
    $minOrder = (float)($_POST['min_order'] ?? 0);
    $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $expiryDate = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;
    $usageLimit = (!empty($_POST['usage_limit']) && is_numeric($_POST['usage_limit'])) ? (int)$_POST['usage_limit'] : null;
    $status = isset($_POST['status']) ? 1 : 0;

    if (empty($code) || $value <= 0) {
        $error = 'Valid coupon code and discount value are required.';
    } else {
        $chk = $pdo->prepare("SELECT id FROM coupons WHERE code = ? AND id != ?");
        $chk->execute([$code, $couponId]);
        if ($chk->fetch()) {
            $error = "Another coupon with code '{$code}' already exists.";
        } else {
            $uStmt = $pdo->prepare("UPDATE coupons SET code = ?, discount_type = ?, discount_value = ?, min_order = ?, start_date = ?, expiry_date = ?, usage_limit = ?, status = ? WHERE id = ?");
            $uStmt->execute([$code, $type, $value, $minOrder, $startDate, $expiryDate, $usageLimit, $status, $couponId]);
            set_flash('success', "Coupon '{$code}' updated successfully.");
            header('Location: ' . BASE_URL . 'admin/coupons/index.php');
            exit;
        }
    }
}
require_once __DIR__ . '/../_header.php';
?>

<div class="admin-page-header">
    <div class="admin-page-title">
        <h1>Edit Coupon: <?= e($coupon['code']) ?></h1>
    </div>
    <div>
        <a class="btn btn-outline" href="<?= BASE_URL ?>admin/coupons/index.php">
            &larr; Back to Coupons
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div style="background: #fdf2f2; border-left: 4px solid #c2413a; color: #c2413a; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<div class="admin-card" style="max-width: 650px;">
    <form method="post" action="<?= BASE_URL ?>admin/coupons/edit.php?id=<?= $couponId ?>">
        <div class="form-group">
            <label class="form-label">Coupon Code *</label>
            <input class="form-control" type="text" name="code" value="<?= e($coupon['code']) ?>" style="text-transform: uppercase;" required>
        </div>

        <div class="form-row-2">
            <div class="form-group">
                <label class="form-label">Discount Type *</label>
                <select class="form-control" name="discount_type" required>
                    <option value="percentage" <?= ($coupon['discount_type'] === 'percentage') ? 'selected' : '' ?>>Percentage (%)</option>
                    <option value="fixed" <?= ($coupon['discount_type'] === 'fixed') ? 'selected' : '' ?>>Fixed Amount (Rs.)</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Discount Value *</label>
                <input class="form-control" type="number" step="0.01" name="discount_value" value="<?= e($coupon['discount_value']) ?>" required>
            </div>
        </div>

        <div class="form-row-2">
            <div class="form-group">
                <label class="form-label">Min. Order (Rs.)</label>
                <input class="form-control" type="number" step="0.01" name="min_order" value="<?= e($coupon['min_order']) ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Usage Limit</label>
                <input class="form-control" type="number" name="usage_limit" value="<?= e($coupon['usage_limit'] ?? '') ?>" placeholder="Unlimited">
            </div>
        </div>

        <div class="form-row-2">
            <div class="form-group">
                <label class="form-label">Start Date</label>
                <input class="form-control" type="date" name="start_date" value="<?= e($coupon['start_date'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Expiry Date</label>
                <input class="form-control" type="date" name="expiry_date" value="<?= e($coupon['expiry_date'] ?? '') ?>">
            </div>
        </div>

        <div class="form-group" style="margin-bottom: 1.5rem;">
            <label class="filter-checkbox-label">
                <input type="checkbox" name="status" value="1" <?= $coupon['status'] ? 'checked' : '' ?>>
                <span>Active coupon</span>
            </label>
        </div>

        <button class="btn btn-primary" type="submit">
            Save Changes
        </button>
    </form>
</div>

<?php require_once __DIR__ . '/../_footer.php'; ?>
