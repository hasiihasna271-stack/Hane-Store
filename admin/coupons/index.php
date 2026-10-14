<?php
/**
 * HANÉ Admin - Coupons Management
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_admin_login();

$adminTitle = 'Promotional Coupons';
$error = '';

// Handle Add Coupon
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_coupon'])) {
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
        $chk = $pdo->prepare("SELECT id FROM coupons WHERE code = ?");
        $chk->execute([$code]);
        if ($chk->fetch()) {
            $error = "A coupon with code '{$code}' already exists.";
        } else {
            $ins = $pdo->prepare("INSERT INTO coupons (code, discount_type, discount_value, min_order, start_date, expiry_date, usage_limit, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$code, $type, $value, $minOrder, $startDate, $expiryDate, $usageLimit, $status]);
            set_flash('success', "Coupon '{$code}' created successfully.");
            header('Location: ' . BASE_URL . 'admin/coupons/index.php');
            exit;
        }
    }
}

// Handle Status Toggle (Activate/Deactivate)
if (isset($_GET['toggle'])) {
    $cId = (int)$_GET['toggle'];
    $pdo->prepare("UPDATE coupons SET status = IF(status=1, 0, 1) WHERE id = ?")->execute([$cId]);
    set_flash('success', 'Coupon status updated.');
    header('Location: ' . BASE_URL . 'admin/coupons/index.php');
    exit;
}

// Fetch all coupons
$coupons = $pdo->query("SELECT * FROM coupons ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
require_once __DIR__ . '/../_header.php';
?>

<div class="admin-page-header">
    <div class="admin-page-title">
        <h1>Coupons &amp; Discounts</h1>
        <p>Create promotional campaigns, percentage discounts, and fixed vouchers.</p>
    </div>
</div>

<?php if ($error): ?>
    <div style="background: #fdf2f2; border-left: 4px solid #c2413a; color: #c2413a; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 1.8fr; gap: 2rem;">
    <!-- Add Coupon Form -->
    <div class="admin-card">
        <h2 class="admin-card-title" style="margin-bottom: 1.2rem;">Create Coupon</h2>
        <form method="post" action="<?= BASE_URL ?>admin/coupons/index.php">
            <div class="form-group">
                <label class="form-label">Coupon Code *</label>
                <input class="form-control" type="text" name="code" placeholder="e.g. SUMMER20" style="text-transform: uppercase;" required>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">Discount Type *</label>
                    <select class="form-control" name="discount_type" required>
                        <option value="percentage">Percentage (%)</option>
                        <option value="fixed">Fixed Amount (Rs.)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Discount Value *</label>
                    <input class="form-control" type="number" step="0.01" name="discount_value" placeholder="e.g. 15 or 500" required>
                </div>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">Min. Order (Rs.)</label>
                    <input class="form-control" type="number" step="0.01" name="min_order" value="0.00">
                </div>
                <div class="form-group">
                    <label class="form-label">Usage Limit (Max Uses)</label>
                    <input class="form-control" type="number" name="usage_limit" placeholder="Leave empty for unlimited">
                </div>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label class="form-label">Start Date</label>
                    <input class="form-control" type="date" name="start_date">
                </div>
                <div class="form-group">
                    <label class="form-label">Expiry Date</label>
                    <input class="form-control" type="date" name="expiry_date">
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label class="filter-checkbox-label">
                    <input type="checkbox" name="status" value="1" checked>
                    <span>Activate immediately</span>
                </label>
            </div>

            <button class="btn btn-primary btn-full" type="submit" name="add_coupon">
                Save Coupon
            </button>
        </form>
    </div>

    <!-- Coupons List -->
    <div class="admin-card">
        <h2 class="admin-card-title" style="margin-bottom: 1.2rem;">All Coupons</h2>
        <div style="overflow-x: auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Discount</th>
                        <th>Min Order</th>
                        <th>Validity</th>
                        <th>Usage</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($coupons)): ?>
                        <?php foreach ($coupons as $cp): 
                            $isExpired = ($cp['expiry_date'] && $cp['expiry_date'] < date('Y-m-d'));
                        ?>
                            <tr>
                                <td>
                                    <strong style="font-family: monospace; font-size: 1rem; color: var(--admin-accent);"><?= e($cp['code']) ?></strong>
                                </td>
                                <td>
                                    <strong>
                                        <?= ($cp['discount_type'] === 'percentage') ? $cp['discount_value'] . '%' : money($cp['discount_value']) ?>
                                    </strong>
                                </td>
                                <td><?= money($cp['min_order']) ?></td>
                                <td style="font-size: 0.8rem;" class="muted">
                                    <?= $cp['start_date'] ?: 'Any' ?> &rarr; <?= $cp['expiry_date'] ?: 'Never' ?><br>
                                    <?php if ($isExpired): ?><span class="badge" style="background: #fee2e2; color: #991b1b;">Expired</span><?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-size: 0.88rem;"><?= (int)$cp['times_used'] ?> / <?= $cp['usage_limit'] ?: '&infin;' ?></span>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>admin/coupons/index.php?toggle=<?= $cp['id'] ?>" title="Click to toggle status">
                                        <span class="status-badge <?= $cp['status'] ? 'status-delivered' : 'status-cancelled' ?>">
                                            <?= $cp['status'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </a>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <a class="btn-icon" href="<?= BASE_URL ?>admin/coupons/edit.php?id=<?= $cp['id'] ?>" title="Edit">
                                            <?= hane_icon('dashboard') ?>
                                        </a>
                                        <a class="btn-icon btn-danger" href="<?= BASE_URL ?>admin/coupons/delete.php?id=<?= $cp['id'] ?>" onclick="return confirm('Delete coupon <?= $cp['code'] ?>?');" title="Delete">
                                            <?= hane_icon('trash') ?>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 3rem; color: var(--admin-text-muted);">
                                No coupons created yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../_footer.php'; ?>
