<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('staff');

$user = currentUser();
$id = (int)($_GET['id'] ?? 0);

// Validasi kepemilikan di backend - user tidak boleh membuka CRF milik user lain
$stmt = $pdo->prepare('SELECT * FROM crf_requests WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->execute([$id, $user['id']]);
$crf = $stmt->fetch();

if (!$crf) {
    set_flash('error', 'Change Request tidak ditemukan atau bukan milik Anda.');
    redirect(BASE_URL . '/user/pengajuan_saya.php');
}

$stmt = $pdo->prepare('SELECT * FROM crf_attachments WHERE crf_id = ? ORDER BY uploaded_at DESC');
$stmt->execute([$id]);
$attachments = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM crf_activity_logs WHERE crf_id = ? ORDER BY created_at ASC');
$stmt->execute([$id]);
$timeline = $stmt->fetchAll();

// Catatan revisi terakhir (jika status perlu_revisi)
$latestRevision = null;
if ($crf['status'] === 'perlu_revisi') {
    $stmt = $pdo->prepare('SELECT * FROM crf_revisions WHERE crf_id = ? ORDER BY requested_at DESC LIMIT 1');
    $stmt->execute([$id]);
    $latestRevision = $stmt->fetch();
}

$prioritasLabel = ['rendah' => 'Rendah', 'sedang' => 'Sedang', 'tinggi' => 'Tinggi'];

$pageTitle  = 'Detail CRF - SIAP PPU';
$breadcrumb = 'Help Desk > Detail CRF';
$activeMenu = 'pengajuan_saya';
require __DIR__ . '/../includes/header.php';
?>

<div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.5rem;">
    <h1 class="page-title">Detail Change Request - <?= e($crf['nomor_crf'] ?? '(Draft, belum diajukan)') ?></h1>
    <span class="badge <?= status_badge_class($crf['status']) ?>"><?= status_label($crf['status']) ?></span>
</div>

<?php if ($crf['status'] === 'perlu_revisi' && $latestRevision): ?>
    <div class="alert alert-error" style="margin-bottom:1.5rem;">
        <strong>Catatan Revisi dari Admin:</strong><br>
        <?= nl2br(e($latestRevision['catatan_revisi'])) ?>
    </div>
    <div class="form-actions" style="border:none; justify-content:flex-start; margin-top:0;">
        <a href="<?= e(BASE_URL) ?>/user/crf_revisi.php?id=<?= (int)$crf['id'] ?>" class="btn btn-warning">Revisi Pengajuan</a>
    </div>
<?php endif; ?>

<div class="card">
    <h2 class="section-title">Data Pemohon</h2>
    <div class="form-grid-2">
        <div><span class="text-muted">Nama Pemohon</span><br><strong><?= e($user['nama']) ?></strong></div>
        <div><span class="text-muted">No HP / WA</span><br><strong><?= e($user['nomor_hp'] ?? '-') ?></strong></div>
    </div>
    <div class="form-grid-2">
        <div><span class="text-muted">Email</span><br><strong><?= e($user['email']) ?></strong></div>
        <div><span class="text-muted">Departemen / Divisi</span><br><strong><?= e($user['departemen'] ?? '-') ?></strong></div>
    </div>
</div>

<div class="card">
    <h2 class="section-title">Rencana Perubahan (CRF)</h2>
    <div class="form-grid-2">
        <div><span class="text-muted">Judul Change Request</span><br><strong><?= e($crf['judul']) ?></strong></div>
        <div><span class="text-muted">Kategori Perubahan</span><br><strong><?= e($crf['sistem_aplikasi']) ?></strong></div>    </div>
    <div class="form-grid-2">
        <div><span class="text-muted">Jenis Perubahan</span><br><strong><?= e($crf['jenis_perubahan']) ?></strong></div>
        <div><span class="text-muted">Tingkat Urgensi &amp; Target Waktu</span><br><strong><?= e($prioritasLabel[$crf['prioritas']] ?? $crf['prioritas']) ?> | <?= format_tanggal($crf['target_waktu']) ?></strong></div>
    </div>

    <p><span class="text-muted">Deskripsi Perubahan</span><br><?= nl2br(e($crf['deskripsi_perubahan'])) ?></p>
    <p><span class="text-muted">Alasan Permohonan Perubahan</span><br><?= nl2br(e($crf['alasan_perubahan'])) ?></p>

    <div class="form-grid-2">
        <p><span class="text-muted">Kondisi Saat Ini</span><br><?= nl2br(e($crf['kondisi_saat_ini'])) ?></p>
        <p><span class="text-muted">Kondisi Yang Diharapkan</span><br><?= nl2br(e($crf['kondisi_diharapkan'])) ?></p>
    </div>

    <p><span class="text-muted">Benefit dari Perubahan yang Diharapkan</span><br><?= nl2br(e($crf['benefit_perubahan'])) ?></p>
    <p><span class="text-muted">Dampak Jika Perubahan Tidak Dilakukan</span><br><?= nl2br(e($crf['dampak_perubahan'])) ?></p>

    <p><span class="text-muted">Lampiran Pendukung</span><br>
        <?php if (empty($attachments)): ?>
            <span class="text-muted">Tidak ada lampiran</span>
        <?php else: ?>
            <?php foreach ($attachments as $att): ?>
                <a href="<?= e(BASE_URL) ?>/actions/attachment_download.php?id=<?= (int)$att['id'] ?>" class="attachment-chip">
                    &#128206; <?= e($att['original_name']) ?> (<?= round($att['size']/1024,1) ?> KB)
                </a><br>
            <?php endforeach; ?>
        <?php endif; ?>
    </p>
</div>

<div class="card">
    <h2 class="section-title">Timeline Proses Pengajuan</h2>
    <?php if (empty($timeline)): ?>
        <p class="text-muted">Belum ada riwayat aktivitas.</p>
    <?php else: ?>
        <ul class="timeline">
            <?php foreach ($timeline as $t): ?>
                <li>
                    <strong><?= e(activity_label($t['activity_type'])) ?></strong><br>
                    <span class="text-muted"><?= format_tanggal($t['created_at'], true) ?></span>
                    <?php if (!empty($t['catatan'])): ?>
                        <p style="margin:0.25rem 0 0;"><?= nl2br(e($t['catatan'])) ?></p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<div class="form-actions" style="justify-content:flex-start; border:none;">
    <a href="<?= e(BASE_URL) ?>/user/pengajuan_saya.php" class="btn btn-outline">&lt; Kembali Ke List</a>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>