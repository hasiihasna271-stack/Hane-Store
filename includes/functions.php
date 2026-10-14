<?php
/**
 * HANÉ E-Commerce Core Functions & Helpers
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/icons.php';

/**
 * Clean HTML output
 */
function e($string): string {
    return htmlspecialchars((string)($string ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Format currency
 */
function money($amount): string {
    return CURRENCY_SYMBOL . number_format((float)$amount, 2);
}

/**
 * Generate or get CSRF token
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output hidden CSRF form field
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Verify CSRF token
 */
function verify_csrf(?string $token = null): bool {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? '';
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Flash notification messaging
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type'    => $type, // 'success', 'error', 'info', 'warning'
        'message' => $message,
    ];
}

function get_flash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Safe Image Path Resolver
 */
function get_image_url(?string $path, string $fallback = 'assets/images/hero-model.jpg'): string {
    if (!empty($path)) {
        $normalized = ltrim(str_replace('\\', '/', $path), '/');
        $fullPath = __DIR__ . '/../' . $normalized;
        if (file_exists($fullPath)) {
            return BASE_URL . $normalized;
        }
    }
    return BASE_URL . $fallback;
}

/**
 * Cart Item Count in Navbar
 */
function cartCount(): int {
    if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        return 0;
    }
    return (int)array_sum($_SESSION['cart']);
}

/**
 * Get detailed Cart Items from Database
 */
function get_cart_items(PDO $pdo): array {
    if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        return [];
    }

    $variantIds = array_keys($_SESSION['cart']);
    if (empty($variantIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
    $sql = "SELECT pv.id AS variant_id, pv.product_id, pv.color, pv.color_code, pv.size, pv.stock AS available_stock,
                   p.name, p.slug, p.regular_price, p.sale_price, p.featured_image, p.status,
                   c.name AS category_name
            FROM product_variants pv
            JOIN products p ON p.id = pv.product_id
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE pv.id IN ($placeholders)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($variantIds);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    foreach ($rows as $row) {
        $vid = $row['variant_id'];
        $qty = (int)($_SESSION['cart'][$vid] ?? 0);
        if ($qty <= 0) continue;

        // Effective price (use sale price if valid)
        $unitPrice = ($row['sale_price'] !== null && $row['sale_price'] > 0) ? (float)$row['sale_price'] : (float)$row['regular_price'];
        
        $row['quantity'] = $qty;
        $row['unit_price'] = $unitPrice;
        $row['subtotal'] = $unitPrice * $qty;
        $row['in_stock'] = ($row['available_stock'] >= $qty && $row['status'] == 1);
        
        $items[] = $row;
    }

    return $items;
}

/**
 * Server-side Cart & Order Totals Calculation
 */
function calculate_totals(PDO $pdo, ?string $couponCode = null): array {
    $items = get_cart_items($pdo);
    $subtotal = 0.0;
    $hasOutOfStock = false;

    foreach ($items as $item) {
        $subtotal += $item['subtotal'];
        if (!$item['in_stock']) {
            $hasOutOfStock = true;
        }
    }

    // Delivery charge calculation
    $freeShippingMin = (float)get_setting('free_shipping_min', 5000);
    $defaultDelivery = (float)get_setting('delivery_charge', 350);
    $deliveryCharge = ($subtotal >= $freeShippingMin || $subtotal == 0) ? 0.0 : $defaultDelivery;

    // Coupon calculation
    $discount = 0.0;
    $couponDetails = null;
    $couponError = null;

    if (!empty($couponCode)) {
        $cStmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND status = 1 LIMIT 1");
        $cStmt->execute([strtoupper(trim($couponCode))]);
        $coupon = $cStmt->fetch(PDO::FETCH_ASSOC);

        $today = date('Y-m-d');
        if (!$coupon) {
            $couponError = "Coupon '{$couponCode}' is not valid.";
        } elseif ($coupon['start_date'] && $coupon['start_date'] > $today) {
            $couponError = "Coupon '{$couponCode}' is not active yet.";
        } elseif ($coupon['expiry_date'] && $coupon['expiry_date'] < $today) {
            $couponError = "Coupon '{$couponCode}' has expired.";
        } elseif ($coupon['usage_limit'] !== null && $coupon['times_used'] >= $coupon['usage_limit']) {
            $couponError = "Coupon '{$couponCode}' usage limit has been reached.";
        } elseif ($subtotal < (float)$coupon['min_order']) {
            $couponError = "Minimum order of " . money($coupon['min_order']) . " required for this coupon.";
        } else {
            if ($coupon['discount_type'] === 'percentage') {
                $discount = ($subtotal * (float)$coupon['discount_value']) / 100.0;
            } else {
                $discount = (float)$coupon['discount_value'];
            }
            // Discount cannot exceed subtotal
            if ($discount > $subtotal) {
                $discount = $subtotal;
            }
            $couponDetails = $coupon;
        }
    }

    $grandTotal = max(0, $subtotal - $discount) + $deliveryCharge;

    return [
        'items'             => $items,
        'item_count'        => count($items),
        'subtotal'          => $subtotal,
        'discount'          => $discount,
        'delivery_charge'   => $deliveryCharge,
        'grand_total'       => $grandTotal,
        'free_shipping_min' => $freeShippingMin,
        'coupon'            => $couponDetails,
        'coupon_error'      => $couponError,
        'has_out_of_stock'  => $hasOutOfStock,
    ];
}

/**
 * Wishlist helpers
 */
function get_wishlist_ids(PDO $pdo): array {
    if (!empty($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    return array_keys($_SESSION['wishlist'] ?? []);
}

function wishlistCount(PDO $pdo): int {
    return count(get_wishlist_ids($pdo));
}

function is_in_wishlist(PDO $pdo, int $productId): bool {
    $ids = get_wishlist_ids($pdo);
    return in_array($productId, $ids);
}

/**
 * Handle secure file upload for products and categories
 */
function upload_image_file(array $file, string $subfolder = 'products'): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'File upload error code: ' . $file['error']];
    }

    if ($file['size'] > 5 * 1024 * 1024) { // 5MB max
        return ['success' => false, 'error' => 'File size exceeds 5MB limit.'];
    }

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!array_key_exists($mimeType, $allowedMimes)) {
        return ['success' => false, 'error' => 'Only JPG, PNG, and WebP images are allowed.'];
    }

    $ext = $allowedMimes[$mimeType];
    $filename = 'hane_' . bin2hex(random_bytes(10)) . '.' . $ext;
    
    $targetDir = __DIR__ . '/../uploads/' . trim($subfolder, '/') . '/';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $destination = $targetDir . $filename;
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        $relativePath = 'uploads/' . trim($subfolder, '/') . '/' . $filename;
        return ['success' => true, 'path' => $relativePath];
    }

    return ['success' => false, 'error' => 'Failed to save uploaded file.'];
}

/**
 * Generate Unique Order Number
 * e.g. HANE-2026-000125
 */
function generate_order_number(PDO $pdo): string {
    $year = date('Y');
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders");
    $count = (int)$stmt->fetchColumn() + 1;
    return sprintf("HANE-%s-%06d", $year, $count);
}

/**
 * URL Slug generator
 */
function slugify(string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'n-a' : $text;
}
