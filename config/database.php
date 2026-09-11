<?php
/**
 * Koneksi database menggunakan PDO + prepared statements.
 * Sesuaikan DB_USER / DB_PASS jika XAMPP Anda memakai kredensial berbeda.
 */

require_once __DIR__ . '/config.php';

define('DB_HOST', 'localhost');
define('DB_NAME', 'crf_ppu');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // Jangan tampilkan detail error database ke user
    die('Koneksi database gagal. Silakan hubungi administrator. (' . $e->getMessage() . ')');
}
