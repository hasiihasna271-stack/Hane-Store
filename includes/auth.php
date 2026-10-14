<?php
/**
 * HANÉ Authentication Helper
 */

require_once __DIR__ . '/functions.php';

function is_user_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function get_current_user_data(PDO $pdo): ?array {
    if (!is_user_logged_in()) {
        return null;
    }
    static $cachedUser = null;
    if ($cachedUser === null) {
        $stmt = $pdo->prepare("SELECT id, name, email, phone, created_at FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $cachedUser = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$cachedUser) {
            unset($_SESSION['user_id']);
        }
    }
    return $cachedUser;
}

function require_user_login(string $redirectUrl = ''): void {
    if (!is_user_logged_in()) {
        $currentUri = $_SERVER['REQUEST_URI'] ?? '';
        $redirect = $redirectUrl ?: $currentUri;
        set_flash('info', 'Please sign in to access your account.');
        header('Location: ' . BASE_URL . 'login.php?redirect=' . urlencode($redirect));
        exit;
    }
}

function is_admin_logged_in(): bool {
    return !empty($_SESSION['admin_id']);
}

function get_current_admin(PDO $pdo): ?array {
    if (!is_admin_logged_in()) {
        return null;
    }
    static $cachedAdmin = null;
    if ($cachedAdmin === null) {
        $stmt = $pdo->prepare("SELECT id, name, email, role, created_at FROM admins WHERE id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $cachedAdmin = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$cachedAdmin) {
            unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_email']);
        }
    }
    return $cachedAdmin;
}

function require_admin_login(): void {
    if (!is_admin_logged_in()) {
        header('Location: ' . BASE_URL . 'admin/login.php');
        exit;
    }
}

// Backwards compatibility alias
function adminOnly(): void {
    require_admin_login();
}
