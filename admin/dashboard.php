<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$user = currentUser();

// Ringkasan seluruh CRF (semua user), draft tidak dihitung karena belum diajukan
$stmt = $pdo->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'diajukan') AS diajukan,
        SUM(status = 'perlu_revisi') AS perlu_revisi,
        SUM(status = 'selesai') AS selesai
    FROM crf_requests
    WHERE status != 'draft'
");
$summary = $stmt->fetch();

// 5 pengajuan terbaru (semua user)
$stmt = $pdo->query("
    SELECT c.id, c.nomor_crf, c.judul, c.status, c.prioritas, c.created_at, u.nama AS nama_pemohon, u.departemen
    FROM crf_requests c
    JOIN users u ON u.id = c.user_id
    WHERE c.status != 'draft'
    ORDER BY c.created_at DESC
    LIMIT 5
");
$recentCrf = $stmt->fetchAll();

$pageTitle  = 'Dashboard Admin - CRF SIAP PPU';
$breadcrumb = 'Menu Saya > Dashboard';
$activeMenu = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">Dashboard Change Request Form</h1>
<p class="page-subtitle">Ringkasan seluruh pengajuan Change Request dari semua departemen</p>

<div class="stat-grid">
    <div class="stat-card">
        <span class="stat-label">Total CRF</span>
        <span class="stat-value"><?= (int)$summary['total'] ?></span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Pengajuan Baru</span>
        <span class="stat-value"><?= (int)$summary['diajukan'] ?></span>
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
        <a href="<?= e(BASE_URL) ?>/admin/crf_list.php" class="btn btn-sm btn-outline">Lihat Semua</a>
    </div>

    <?php if (empty($recentCrf)): ?>
        <p class="text-muted">Belum ada pengajuan CRF yang masuk.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Nomor CRF</th>
                    <th>Pemohon</th>
                    <th>Judul</th>
                    <th>Tingkat Urgensi</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentCrf as $crf): ?>
                <tr>
                    <td><?= e($crf['nomor_crf']) ?></td>
                    <td><?= e($crf['nama_pemohon']) ?> <span class="text-muted">(<?= e($crf['departemen']) ?>)</span></td>
                    <td><?= e($crf['judul']) ?></td>
                    <td><?= e(ucfirst($crf['prioritas'])) ?></td>
                    <td><span class="badge <?= status_badge_class($crf['status']) ?>"><?= status_label($crf['status']) ?></span></td>
                    <td><a href="<?= e(BASE_URL) ?>/admin/crf_detail.php?id=<?= (int)$crf['id'] ?>">Detail</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>