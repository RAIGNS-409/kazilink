<?php
$host = 'localhost';
$db   = 'kazilink_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Don't output anything here - let api.php handle errors
    error_log('DB Error: ' . $e->getMessage());
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
