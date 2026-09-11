<?php
/**
 * Header bersama - include SETELAH requireLogin()/requireRole() dipanggil.
 * Variabel yang sebaiknya di-set sebelum include file ini:
 *   $pageTitle   (string) judul tab browser
 *   $breadcrumb  (string) teks breadcrumb di topbar
 *   $activeMenu  (string) key menu aktif di sidebar
 *
 * CATATAN INTEGRASI: file ini (beserta sidebar.php & footer.php) adalah
 * satu-satunya bagian yang perlu DIBUANG saat modul CRF ditempel ke SIAP,
 * karena SIAP sudah punya layout/topbar/sidebar sendiri. Konten tiap
 * halaman (antara header dan footer) bisa langsung ditempel ke layout SIAP.
 */
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'CRF SIAP PPU') ?></title>
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/style.css">
</head>
<body class="app-body">
<div class="app-wrapper">
    <?php include __DIR__ . '/sidebar.php'; ?>

    <div class="app-main">
        <div class="app-topbar">
            <div class="app-topbar-left">
                <span class="app-topbar-brand">SIAP PPU</span>
                <span class="app-topbar-breadcrumb"><?= e($breadcrumb ?? '') ?></span>
            </div>
            <div class="app-topbar-right">
                <!-- <span class="app-topbar-quote">&quot;Tidak peduli cepat atau lambat prosesnya, yang penting selamat sampai tujuan.&quot;</span> -->
                <div class="app-topbar-user">
                    <div class="app-topbar-user-info">
                        <strong><?= e($user['nama']) ?></strong>
                        <span><?= e($user['jabatan'] ?? '-') ?></span>
                    </div>
                    <div class="app-topbar-avatar"><?= e(initials($user['nama'])) ?></div>
                </div>
            </div>
        </div>

        <div class="app-content">
            <?php foreach (get_flashes() as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endforeach; ?>
