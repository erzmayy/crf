<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$csrfToken = generate_csrf_token();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT c.*, u.nama AS nama_pemohon, u.departemen
    FROM crf_requests c
    JOIN users u ON u.id = c.user_id
    WHERE c.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$crf = $stmt->fetch();

if (!$crf) {
    set_flash('error', 'Change Request tidak ditemukan.');
    redirect(BASE_URL . '/admin/crf_list.php');
}

if ($crf['status'] !== 'diajukan') {
    set_flash('error', 'Permintaan revisi hanya bisa dilakukan saat status Diajukan.');
    redirect(BASE_URL . '/admin/crf_detail.php?id=' . $id);
}

$bagianOptions = [
    'deskripsi_perubahan' => 'Deskripsi Perubahan',
    'alasan_perubahan'    => 'Alasan / Justifikasi Bisnis',
    'dampak_perubahan'    => 'Dampak Terhadap Sistem',
    'prioritas'           => 'Skala Prioritas',
    'lampiran'            => 'Lampiran / Dokumen Pendukung',
    'lainnya'             => 'Lainnya',
];

$pageTitle  = 'Permintaan Revisi - SIAP PPU';
$breadcrumb = 'Change Request Form > Form Permintaan Revisi';
$activeMenu = 'crf_list';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">Permintaan Revisi - <?= e($crf['nomor_crf']) ?></h1>
<p class="page-subtitle">Change Request Form &gt; Form Permintaan Revisi</p>

<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem;">
        <div class="form-grid-2" style="flex:1;">
            <div><span class="text-muted">Judul Change Request</span><br><strong><?= e($crf['judul']) ?></strong></div>
            <div><span class="text-muted">Pemohon</span><br><strong><?= e($crf['nama_pemohon']) ?></strong></div>
        </div>
        <span class="badge <?= status_badge_class($crf['status']) ?>">STATUS: <?= status_label($crf['status']) ?></span>
    </div>
</div>

<form method="POST" action="<?= e(BASE_URL) ?>/actions/crf_admin_request_revision.php">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="crf_id" value="<?= (int)$crf['id'] ?>">

    <div class="card">
        <h2 class="section-title">Formulir Instruksi Revisi</h2>

        <div class="form-group">
            <label>Catatan Revisi *</label>
            <textarea name="catatan_revisi" rows="4" required
                      placeholder="Tuliskan instruksi revisi secara spesifik dan jelas agar pemohon dapat melakukan perbaikan dengan tepat."></textarea>
        </div>

        <div class="form-group">
            <label>Bagian yang Perlu Direvisi *</label>
            <div class="radio-group" style="flex-direction:column; align-items:flex-start; gap:0.5rem;">
                <?php foreach ($bagianOptions as $val => $label): ?>
                    <label>
                        <input type="checkbox" name="bagian[]" value="<?= e($val) ?>">
                        <?= e($label) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <a href="<?= e(BASE_URL) ?>/admin/crf_detail.php?id=<?= (int)$crf['id'] ?>" class="btn btn-outline">Batal</a>
        <button type="submit" class="btn btn-warning">Kirim Permintaan Revisi</button>
    </div>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>