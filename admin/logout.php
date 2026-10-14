<?php
/**
 * HANÉ Admin - Logout
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_email']);
header('Location: ' . BASE_URL . 'admin/login.php');
exit;
