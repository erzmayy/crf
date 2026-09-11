<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('staff');

$user = currentUser();

// Ringkasan jumlah CRF milik user ini, per status
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'draft') AS draft,
        SUM(status = 'dalam_pemeriksaan') AS dalam_pemeriksaan,
        SUM(status = 'perlu_revisi') AS perlu_revisi,
        SUM(status = 'selesai') AS selesai
    FROM crf_requests
    WHERE user_id = ?
");
$stmt->execute([$user['id']]);
$summary = $stmt->fetch();

// 5 pengajuan terbaru milik user (draft & yang sudah diajukan)
$stmt = $pdo->prepare("
    SELECT id, nomor_crf, judul, status, created_at
    FROM crf_requests
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([$user['id']]);
$recentCrf = $stmt->fetchAll();

$pageTitle  = 'Dashboard - Change Request Form';
$breadcrumb = 'Menu Saya > Dashboard';
$activeMenu = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">Dashboard Change Request Form</h1>
<p class="page-subtitle">Ringkasan pengajuan Change Request milik Anda</p>

<div class="stat-grid">
    <div class="stat-card">
        <span class="stat-label">Total Pengajuan</span>
        <span class="stat-value"><?= (int)$summary['total'] ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Draft</span>
        <span class="stat-value"><?= (int)$summary['draft'] ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Dalam Pemeriksaan</span>
        <span class="stat-value"><?= (int)$summary['dalam_pemeriksaan'] ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Perlu Revisi</span>
        <span class="stat-value"><?= (int)$summary['perlu_revisi'] ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Selesai</span>
        <span class="stat-value"><?= (int)$summary['selesai'] ?></span>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Pengajuan Terbaru</h2>
        <a href="<?= e(BASE_URL) ?>/user/pengajuan_saya.php" class="btn btn-sm btn-outline">Lihat Semua</a>
    </div>

    <?php if (empty($recentCrf)): ?>
        <p class="text-muted">
            Belum ada pengajuan Change Request.
            <a href="<?= e(BASE_URL) ?>/helpdesk/form.php">Buat pengajuan baru</a>.
        </p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Nomor CRF</th>
                    <th>Judul</th>
                    <th>Tanggal</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentCrf as $crf): ?>
                <tr>
                    <td><?= e($crf['nomor_crf'] ?? '(Draft)') ?></td>
                    <td><?= e($crf['judul']) ?></td>
                    <td><?= format_tanggal($crf['created_at']) ?></td>
                    <td><span class="badge <?= status_badge_class($crf['status']) ?>"><?= status_label($crf['status']) ?></span></td>
                    <td><a href="<?= e(BASE_URL) ?>/user/crf_detail.php?id=<?= (int)$crf['id'] ?>">Detail</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
