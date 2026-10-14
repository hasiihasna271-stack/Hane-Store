<?php
/**
 * HANÉ Database Connection
 */
$host = 'localhost';
$db   = 'hane_store';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die('Database connection failed. Please ensure MySQL is running in XAMPP and database.sql has been imported.');
}

require_once __DIR__ . '/config.php';
