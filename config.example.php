<?php
declare(strict_types=1);

// Example configuration for the Academic Archiving System (v1.1).
// Copy this file to config/database.php and fill in your own values.
// Do NOT commit config/database.php with real credentials.
//
// You can also set these values with environment variables instead:
//   DB_HOST, DB_NAME, DB_USER, DB_PASS
// Environment variables take priority over the values below.

$host = getenv('DB_HOST') ?: '127.0.0.1';
$db   = getenv('DB_NAME') ?: 'academic_archiving';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';  // put your MySQL password here, or leave empty for a local install with no password

$dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed. Check config/database.php and import database/schema.sql.');
}
