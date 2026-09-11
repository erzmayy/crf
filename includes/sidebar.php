<?php
$role = $_SESSION['user_role'] ?? null;
$activeMenu = $activeMenu ?? '';
?>
<aside class="app-sidebar">
    <div class="app-sidebar-logo">
        <span class="app-sidebar-logo-icon">&#128421;</span>
        <span>SIAP PPU</span>
    </div>

    <nav class="app-sidebar-nav">
        <span class="app-sidebar-item disabled">Menu Saya</span>
        <span class="app-sidebar-item disabled">Laporan</span>
        <span class="app-sidebar-item disabled">Keuangan</span>
        <?php if ($role === 'admin'): ?>
            <span class="app-sidebar-item disabled">User &amp; Menu</span>
        <?php endif; ?>
        <span class="app-sidebar-item disabled">Personalia</span>
        <span class="app-sidebar-item disabled">Upload Manual</span>
        <span class="app-sidebar-item disabled">Aplikasi PPU</span>

        <div class="app-sidebar-group-title">Help Desk</div>

        <?php if ($role === 'staff'): ?>
            <a href="<?= e(BASE_URL) ?>/helpdesk/form.php"
               class="app-sidebar-subitem <?= $activeMenu === 'helpdesk_form' ? 'active' : '' ?>">Formulir Help Desk</a>
            <a href="<?= e(BASE_URL) ?>/user/crf_form.php"
               class="app-sidebar-subitem <?= $activeMenu === 'crf_form' ? 'active' : '' ?>">Change Request Form</a>
            <a href="<?= e(BASE_URL) ?>/user/pengajuan_saya.php"
               class="app-sidebar-subitem <?= $activeMenu === 'pengajuan_saya' ? 'active' : '' ?>">Pengajuan Saya</a>
            <a href="<?= e(BASE_URL) ?>/user/notifikasi.php"
               class="app-sidebar-subitem <?= $activeMenu === 'notifikasi' ? 'active' : '' ?>">Notifikasi CRF</a>
        <?php elseif ($role === 'admin'): ?>
            <a href="<?= e(BASE_URL) ?>/admin/crf_list.php"
               class="app-sidebar-subitem <?= $activeMenu === 'crf_list' ? 'active' : '' ?>">Daftar Change Request</a>
            <a href="<?= e(BASE_URL) ?>/admin/laporan.php"
               class="app-sidebar-subitem <?= $activeMenu === 'laporan' ? 'active' : '' ?>">Laporan &amp; Statistik</a>
            <a href="<?= e(BASE_URL) ?>/admin/notifikasi.php"
               class="app-sidebar-subitem <?= $activeMenu === 'notifikasi' ? 'active' : '' ?>">Notifikasi Admin</a>
        <?php endif; ?>
    </nav>

    <div class="app-sidebar-footer">
        <a href="<?= e(BASE_URL) ?>/auth/logout.php" class="app-sidebar-logout">Logout</a>
    </div>
</aside>
