<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('staff');

$user = currentUser();

$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();

$pageTitle  = 'Notifikasi CRF - SIAP PPU';
$breadcrumb = 'Help Desk > Notifikasi CRF';
$activeMenu = 'notifikasi';
require __DIR__ . '/../includes/header.php';
?>

<div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.5rem;">
    <div>
        <h1 class="page-title">Notifikasi - Change Request Form</h1>
        <p class="page-subtitle">Pantau langsung status dan feedback atas pengajuan Anda</p>
    </div>
    <?php if (!empty($notifications)): ?>
        <form method="POST" action="<?= e(BASE_URL) ?>/actions/notification_mark_read.php">
            <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
            <input type="hidden" name="mark_all" value="1">
            <button type="submit" class="btn btn-outline btn-sm">Tandai Semua Telah Dibaca</button>
        </form>
    <?php endif; ?>
</div>

<?php if (empty($notifications)): ?>
    <div class="card">
        <p class="text-muted">Belum ada notifikasi.</p>
    </div>
<?php else: ?>
    <?php foreach ($notifications as $n): ?>
        <div class="card notif-card <?= $n['is_read'] ? '' : 'notif-unread' ?>">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:1rem;">
                <div style="flex:1;">
                    <a href="<?= e(BASE_URL) ?>/actions/notification_mark_read.php?id=<?= (int)$n['id'] ?>" class="notif-title">
                        <?= e($n['title']) ?>
                    </a>
                    <p style="margin:0.4rem 0 0; font-size:0.85rem; color:var(--color-muted);"><?= e($n['message']) ?></p>
                </div>
                <span class="text-muted" style="font-size:0.78rem; white-space:nowrap;"><?= waktu_relatif($n['created_at']) ?></span>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>