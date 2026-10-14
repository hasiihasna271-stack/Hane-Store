<?php
/**
 * HANÉ AJAX Endpoint
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

if ($action === 'toggle_wishlist') {
    $productId = (int)($_POST['product_id'] ?? 0);
    if ($productId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product.']);
        exit;
    }

    // Verify product exists and is active
    $pStmt = $pdo->prepare("SELECT id, name FROM products WHERE id = ? AND status = 1");
    $pStmt->execute([$productId]);
    $product = $pStmt->fetch();
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found.']);
        exit;
    }

    $inWishlist = false;
    if (!empty($_SESSION['user_id'])) {
        // Logged-in user: DB persistence
        $userId = $_SESSION['user_id'];
        $checkStmt = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
        $checkStmt->execute([$userId, $productId]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            $del = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
            $del->execute([$userId, $productId]);
            $inWishlist = false;
            $msg = "'{$product['name']}' removed from your wishlist.";
        } else {
            $ins = $pdo->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
            $ins->execute([$userId, $productId]);
            $inWishlist = true;
            $msg = "'{$product['name']}' added to your wishlist.";
        }
    } else {
        // Guest user: session persistence
        if (!isset($_SESSION['wishlist'])) {
            $_SESSION['wishlist'] = [];
        }
        if (isset($_SESSION['wishlist'][$productId])) {
            unset($_SESSION['wishlist'][$productId]);
            $inWishlist = false;
            $msg = "'{$product['name']}' removed from your wishlist.";
        } else {
            $_SESSION['wishlist'][$productId] = true;
            $inWishlist = true;
            $msg = "'{$product['name']}' added to your wishlist.";
        }
    }

    echo json_encode([
        'success'     => true,
        'in_wishlist' => $inWishlist,
        'count'       => wishlistCount($pdo),
        'message'     => $msg
    ]);
    exit;
}

if ($action === 'newsletter_subscribe') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO newsletter_subscribers (email) VALUES (?)");
        $stmt->execute([$email]);
        echo json_encode(['success' => true, 'message' => 'Welcome to the HANÉ Circle! Check your inbox soon for exclusive perks.']);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            echo json_encode(['success' => true, 'message' => 'You are already a member of the HANÉ Circle!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
        }
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
exit;
