<?php
require_once __DIR__ . '/../includes/auth.php';

// Jika sudah login, langsung arahkan ke dashboard sesuai role
if (!empty($_SESSION['user_id'])) {
    redirect(BASE_URL . '/' . role_folder($_SESSION['user_role']) . '/dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Sesi tidak valid, silakan muat ulang halaman dan coba lagi.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $errors[] = 'Email dan password wajib diisi.';
        } elseif (attemptLogin($pdo, $email, $password)) {
            redirect(BASE_URL . '/' . role_folder($_SESSION['user_role']) . '/dashboard.php');
        } else {
            $errors[] = 'Email atau password salah.';
        }
    }
}

$csrfToken = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Change Request Form SIAP PPU</title>
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-box">
        <h1>Change Request Form</h1>
        <p class="login-subtitle">Login sementara untuk development &mdash; akan digantikan autentikasi SIAP saat integrasi</p>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endforeach; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <label for="email">Email</label>
            <input type="email" id="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>">

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Login</button>
        </form>

        <div class="login-hint">
            <p><strong>Akun contoh (development):</strong></p>
            <p>Staff &nbsp;: staff@ppu.co.id / password123</p>
            <p>Admin : admin@ppu.co.id / password123</p>
        </div>
    </div>
</body>
</html>
