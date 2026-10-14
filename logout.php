<?php
/**
 * HANÉ - Customer Logout
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email']);
set_flash('info', 'You have been signed out safely. Come back soon!');
header('Location: ' . BASE_URL . 'login.php');
exit;
