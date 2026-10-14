<?php
/**
 * HANÉ - Account Root Router
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (!is_user_logged_in()) {
    header('Location: ' . BASE_URL . 'login.php?redirect=' . urlencode(BASE_URL . 'account/profile.php'));
    exit;
}

header('Location: ' . BASE_URL . 'account/profile.php');
exit;
