<?php
/**
 * Modul otentikasi & otorisasi sederhana berbasis PHP Session.
 * Ini adalah pengganti sementara middleware Laravel / auth SIAP.
 *
 * CATATAN INTEGRASI: saat SIAP sudah tersedia, isi fungsi requireLogin(),
 * requireRole(), dan currentUser() bisa diganti untuk membaca session/auth
 * milik SIAP, TANPA perlu mengubah pemanggilan fungsi di halaman-halaman lain.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

// Pastikan user sudah login. Jika belum, redirect ke halaman login.
function requireLogin() {
    if (empty($_SESSION['user_id'])) {
        redirect(BASE_URL . '/auth/login.php');
    }
}

/**
 * Pastikan role user sesuai yang dibutuhkan halaman.
 * $allowedRoles bisa string tunggal ('admin') atau array (['staff','admin']).
 *
 * PENTING (poin keamanan #24 di brief): fungsi ini WAJIB dipanggil di setiap
 * halaman/action, bukan hanya menyembunyikan tombol di tampilan.
 */
function requireRole($allowedRoles) {
    requireLogin();

    $allowedRoles = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];

    if (!in_array($_SESSION['user_role'], $allowedRoles, true)) {
        http_response_code(403);
        die('Akses ditolak: Anda tidak memiliki hak akses ke halaman ini.');
    }
}

// Ambil data lengkap user yang sedang login (di-cache statis per request)
function currentUser() {
    static $user = null;

    if ($user !== null) {
        return $user;
    }

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    global $pdo;
    $stmt = $pdo->prepare('SELECT id, nama, email, role, departemen, jabatan, nomor_hp FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    return $user;
}

// Proses login: verifikasi email + password, set session jika berhasil
function attemptLogin($pdo, $email, $password) {
    $stmt = $pdo->prepare('SELECT id, nama, email, password, role FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }

    // Regenerasi session ID untuk mencegah session fixation attack
    session_regenerate_id(true);

    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_nama'] = $user['nama'];
    $_SESSION['user_role'] = $user['role'];

    return true;
}

// Hancurkan session (logout)
function doLogout() {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']
        );
    }

    session_destroy();
}
