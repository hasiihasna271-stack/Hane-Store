<?php
/**
 * HANÉ E-Commerce Configuration
 */

// Define application base paths and URLs
if (!defined('BASE_URL')) {
    // Dynamically detect base URL or default to /hane_store/
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    if (strpos($scriptDir, '/admin') !== false) {
        $baseUrl = substr($scriptDir, 0, strpos($scriptDir, '/admin') + 1);
    } else {
        $baseUrl = rtrim($scriptDir, '/') . '/';
    }
    // Normalize to /hane_store/ if inside hane_store
    if (strpos($baseUrl, 'hane_store') !== false) {
        define('BASE_URL', '/hane_store/');
    } else {
        define('BASE_URL', $baseUrl ?: '/');
    }
}

define('SITE_NAME', 'HANÉ');
define('SITE_TAGLINE', 'Wear Your Elegance.');
define('CURRENCY_SYMBOL', 'Rs. ');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('ASSET_PATH', BASE_URL . 'assets/');

// Helper to fetch setting from database with caching
function get_setting(string $key, $default = '') {
    static $settingsCache = null;
    global $pdo;

    if ($settingsCache === null && isset($pdo)) {
        try {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
            $settingsCache = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settingsCache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            $settingsCache = [];
        }
    }

    return $settingsCache[$key] ?? $default;
}
