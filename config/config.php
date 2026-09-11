<?php
/**
 * Konfigurasi umum aplikasi CRF.
 *
 * CATATAN INTEGRASI: saat modul ini digabung ke SIAP, file ini
 * kemungkinan akan digantikan konfigurasi SIAP yang sudah ada.
 * Sesuaikan BASE_URL dan path lain sesuai struktur folder SIAP.
 */

// Sesuaikan dengan nama folder project di htdocs XAMPP Anda
define('BASE_URL', 'http://localhost/crf');

// Path fisik & URL penyimpanan upload
define('UPLOAD_PATH', __DIR__ . '/../uploads/crf/');
define('UPLOAD_URL', BASE_URL . '/uploads/crf/');

// Batas ukuran upload (5MB sesuai desain PDF)
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024);

// Validasi lampiran (sesuai keterangan "Maksimal ukuran file: 5MB (PDF/ZIP)")
define('ALLOWED_EXTENSIONS', ['pdf', 'zip']);
define('ALLOWED_MIME_TYPES', ['application/pdf', 'application/zip', 'application/x-zip-compressed']);

// Error reporting - MATIKAN display_errors saat sudah production
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Mulai session (harus dipanggil sebelum ada output apa pun)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Jakarta');
