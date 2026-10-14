<?php
/**
 * HANÉ Admin - Store Settings Configuration
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_admin_login();

$adminTitle = 'Store Configuration';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $settingsToSave = [
        'store_name'          => trim($_POST['store_name'] ?? 'HANÉ'),
        'store_tagline'       => trim($_POST['store_tagline'] ?? 'Wear Your Elegance.'),
        'store_email'         => trim($_POST['store_email'] ?? 'hello@hane.lk'),
        'store_phone'         => trim($_POST['store_phone'] ?? '+94 11 234 5678'),
        'store_address'       => trim($_POST['store_address'] ?? '42 Elegance Avenue, Colombo 07, Sri Lanka'),
        'free_shipping_min'   => (string)(float)($_POST['free_shipping_min'] ?? 5000),
        'delivery_charge'     => (string)(float)($_POST['delivery_charge'] ?? 350),
        'currency'            => trim($_POST['currency'] ?? 'Rs.'),
        'low_stock_threshold' => (string)(int)($_POST['low_stock_threshold'] ?? 5),
    ];

    $upd = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    foreach ($settingsToSave as $k => $v) {
        $upd->execute([$k, $v]);
    }

    set_flash('success', 'Store settings have been successfully updated.');
    header('Location: ' . BASE_URL . 'admin/settings/index.php');
    exit;
}

// Fetch all current settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$currentSettings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $currentSettings[$row['setting_key']] = $row['setting_value'];
}
require_once __DIR__ . '/../_header.php';
?>

<div class="admin-page-header">
    <div class="admin-page-title">
        <h1>Store Configuration</h1>
        <p>Manage brand details, fulfillment thresholds, and notification parameters.</p>
    </div>
</div>

<div class="admin-card" style="max-width: 800px;">
    <form method="post" action="<?= BASE_URL ?>admin/settings/index.php">
        
        <h2 class="admin-card-title" style="margin-bottom: 1.2rem;">Brand &amp; Contact Details</h2>
        
        <div class="form-row-2">
            <div class="form-group">
                <label class="form-label">Store Brand Name</label>
                <input class="form-control" type="text" name="store_name" value="<?= e($currentSettings['store_name'] ?? 'HANÉ') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Brand Tagline</label>
                <input class="form-control" type="text" name="store_tagline" value="<?= e($currentSettings['store_tagline'] ?? 'Wear Your Elegance.') ?>" required>
            </div>
        </div>

        <div class="form-row-2">
            <div class="form-group">
                <label class="form-label">Support Email Address</label>
                <input class="form-control" type="email" name="store_email" value="<?= e($currentSettings['store_email'] ?? 'hello@hane.lk') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Support Contact Number</label>
                <input class="form-control" type="text" name="store_phone" value="<?= e($currentSettings['store_phone'] ?? '+94 11 234 5678') ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Flagship Store / Headquarters Address</label>
            <input class="form-control" type="text" name="store_address" value="<?= e($currentSettings['store_address'] ?? '42 Elegance Avenue, Colombo 07, Sri Lanka') ?>" required>
        </div>

        <hr style="border: 0; border-top: 1px solid var(--admin-border); margin: 2rem 0;">

        <h2 class="admin-card-title" style="margin-bottom: 1.2rem;">Shipping &amp; Inventory Rules</h2>

        <div class="form-row-2">
            <div class="form-group">
                <label class="form-label">Free Shipping Minimum (Rs.)</label>
                <input class="form-control" type="number" step="0.01" name="free_shipping_min" value="<?= e($currentSettings['free_shipping_min'] ?? '5000') ?>" required>
                <small class="muted">Orders above this subtotal qualify for free delivery automatically.</small>
            </div>
            <div class="form-group">
                <label class="form-label">Standard Delivery Charge (Rs.)</label>
                <input class="form-control" type="number" step="0.01" name="delivery_charge" value="<?= e($currentSettings['delivery_charge'] ?? '350') ?>" required>
                <small class="muted">Applied to orders below the free shipping threshold.</small>
            </div>
        </div>

        <div class="form-row-2">
            <div class="form-group">
                <label class="form-label">Store Currency Symbol</label>
                <input class="form-control" type="text" name="currency" value="<?= e($currentSettings['currency'] ?? 'Rs.') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Low Stock Alert Threshold</label>
                <input class="form-control" type="number" name="low_stock_threshold" value="<?= e($currentSettings['low_stock_threshold'] ?? '5') ?>" required>
                <small class="muted">Variants with stock at or below this value appear in low stock alerts.</small>
            </div>
        </div>

        <div style="margin-top: 1.5rem;">
            <button class="btn btn-primary btn-lg" type="submit" name="save_settings">
                Save Store Settings
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../_footer.php'; ?>
