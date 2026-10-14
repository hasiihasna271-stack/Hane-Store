<?php
/**
 * HANÉ Admin - Delete Product Handler
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_admin_login();

$productId = (int)($_GET['id'] ?? 0);
if ($productId > 0) {
    // Delete product (FK cascading deletes variants, images, wishlist items)
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    set_flash('success', 'Product and its variants have been deleted.');
}

header('Location: ' . BASE_URL . 'admin/products/index.php');
exit;
