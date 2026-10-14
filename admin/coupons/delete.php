<?php
/**
 * HANÉ Admin - Delete Coupon
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_admin_login();

$couponId = (int)($_GET['id'] ?? 0);
if ($couponId > 0) {
    $stmt = $pdo->prepare("DELETE FROM coupons WHERE id = ?");
    $stmt->execute([$couponId]);
    set_flash('success', 'Coupon has been deleted.');
}

header('Location: ' . BASE_URL . 'admin/coupons/index.php');
exit;
