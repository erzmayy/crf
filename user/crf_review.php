<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('staff');

$user = currentUser();
$csrfToken = generate_csrf_token();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM crf_requests WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->execute([$id, $user['id']]);
$crf = $stmt->fetch();

if (!$crf) {
    set_flash('error', 'Change Request tidak ditemukan atau bukan milik Anda.');
    redirect(BASE_URL . '/user/pengajuan_saya.php');
}

if ($crf['status'] !== 'draft') {
    set_flash('error', 'Pengajuan ini sudah tidak berstatus draft.');
    redirect(BASE_URL . '/user/crf_detail.php?id=' . $id);
}

$stmt = $pdo->prepare('SELECT * FROM crf_attachments WHERE crf_id = ? ORDER BY uploaded_at DESC');
$stmt->execute([$id]);
$attachments = $stmt->fetchAll();

$pageTitle  = 'Review Pengajuan CRF - SIAP PPU';
$breadcrumb = 'Help Desk > Change Request > Review';
$activeMenu = 'crf_form';
require __DIR__ . '/../includes/header.php';

$prioritasLabel = ['rendah' => 'Rendah', 'sedang' => 'Sedang', 'tinggi' => 'Tinggi'];
?>

<div style="display:flex; align-items:center; justify-content:space-between;">
    <h1 class="page-title">Review Pengajuan Change Request</h1>
    <span class="badge badge-draft">DRAFT PENGAJUAN</span>
</div>
<p class="page-subtitle">Periksa kembali data di bawah sebelum mengajukan. Anda masih bisa kembali untuk mengedit.</p>

<div class="card">
    <h2 class="section-title">1. Summary Data Pemohon</h2>
    <div class="form-grid-2">
        <div><span class="text-muted">Nama Pemohon</span><br><strong><?= e($user['nama']) ?></strong></div>
        <div><span class="text-muted">Divisi/Departemen</span><br><strong><?= e($user['departemen'] ?? '-') ?></strong></div>
    </div>
    <div class="form-grid-2">
        <div><span class="text-muted">No Handphone/WA</span><br><strong><?= e($user['nomor_hp'] ?? '-') ?></strong></div>
        <div><span class="text-muted">Email</span><br><strong><?= e($user['email']) ?></strong></div>
    </div>
</div>

<div class="card">
    <h2 class="section-title">2. Detail Rencana Perubahan (CRF)</h2>

    <div class="form-grid-2">
        <div><span class="text-muted">Judul Change Request</span><br><strong><?= e($crf['judul']) ?></strong></div>
        <div><span class="text-muted">Kategori Perubahan</span><br><strong><?= e($crf['sistem_aplikasi']) ?></strong></div>    </div>
    <div class="form-grid-2">
        <div><span class="text-muted">Jenis Perubahan</span><br><strong><?= e($crf['jenis_perubahan']) ?></strong></div>
        <div><span class="text-muted">Tingkat Urgensi &amp; Target Waktu</span><br><strong><?= e($prioritasLabel[$crf['prioritas']] ?? $crf['prioritas']) ?> | <?= format_tanggal($crf['target_waktu']) ?></strong></div>
    </div>

    <p><span class="text-muted">Deskripsi Perubahan</span><br><?= nl2br(e($crf['deskripsi_perubahan'])) ?></p>
    <p><span class="text-muted">Alasan Permohonan Perubahan</span><br><?= nl2br(e($crf['alasan_perubahan'])) ?></p>

    <p><span class="text-muted">Benefit dari Perubahan yang Diharapkan</span><br><?= nl2br(e($crf['benefit_perubahan'])) ?></p>
    <p><span class="text-muted">Dampak Jika Perubahan Tidak Dilakukan</span><br><?= nl2br(e($crf['dampak_perubahan'])) ?></p>

    <?php if (!empty($crf['aset_sumber_pendukung'])): ?>
    <p><span class="text-muted">Aset / Sumber Pendukung</span><br><?= nl2br(e($crf['aset_sumber_pendukung'])) ?></p>
    <?php endif; ?>

    <p><span class="text-muted">Lampiran Pendukung</span><br>
        <?php if (empty($attachments)): ?>
            <span class="text-muted">Tidak ada lampiran</span>
        <?php else: ?>
            <?php foreach ($attachments as $att): ?>
                <span class="attachment-chip">&#128206; <?= e($att['original_name']) ?> (<?= round($att['size']/1024,1) ?> KB)</span><br>
            <?php endforeach; ?>
        <?php endif; ?>
    </p>
</div>

<div class="form-actions">
    <a href="<?= e(BASE_URL) ?>/user/crf_form.php?id=<?= (int)$crf['id'] ?>" class="btn btn-outline">Kembali &amp; Edit</a>
    <button type="button" class="btn btn-primary" onclick="document.getElementById('confirmModal').classList.add('show')">Ajukan CRF Sekarang</button>
</div>

<!-- Modal Konfirmasi -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal-box">
        <h3>Konfirmasi Pengajuan CRF</h3>
        <p>Apakah Anda yakin ingin mengajukan Change Request ini? Data yang dikirimkan akan langsung masuk ke tahap peninjauan oleh Admin (Change Advisory Board).</p>
        <form method="POST" action="<?= e(BASE_URL) ?>/actions/crf_submit.php">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="crf_id" value="<?= (int)$crf['id'] ?>">
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('confirmModal').classList.remove('show')">Batal</button>
                <button type="submit" class="btn btn-success">Ya, Ajukan</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>