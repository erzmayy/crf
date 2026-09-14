<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$admin = currentUser();
$csrfToken = generate_csrf_token();

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT c.*, u.nama AS nama_pemohon, u.email AS email_pemohon, u.nomor_hp, u.departemen, u.jabatan
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

$stmt = $pdo->prepare('SELECT * FROM crf_attachments WHERE crf_id = ? ORDER BY uploaded_at DESC');
$stmt->execute([$id]);
$attachments = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT * FROM crf_activity_logs WHERE crf_id = ? ORDER BY created_at ASC');
$stmt->execute([$id]);
$timeline = $stmt->fetchAll();

$prioritasLabel = ['rendah' => 'Rendah', 'sedang' => 'Sedang', 'tinggi' => 'Tinggi'];

$pageTitle  = 'Detail CRF - Admin SIAP PPU';
$breadcrumb = 'Help Desk > Detail Pengajuan CRF';
$activeMenu = 'crf_list';
require __DIR__ . '/../includes/header.php';
?>

<div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.75rem;">
    <div>
        <h1 class="page-title">Detail Change Request - <?= e($crf['nomor_crf']) ?></h1>
        <p class="page-subtitle">Evaluasi Change Request Form dari <?= e($crf['nama_pemohon']) ?> (<?= e($crf['departemen']) ?>)</p>
    </div>
    <span class="badge <?= status_badge_class($crf['status']) ?>" style="font-size:0.8rem;"><?= status_label($crf['status']) ?></span>
</div>

<!-- ================= TOMBOL AKSI KONTEKSTUAL ================= -->
<div class="form-actions" style="border:none; justify-content:flex-start; margin-top:0.5rem;">
    <?php if ($crf['status'] === 'diajukan'): ?>
        <a href="<?= e(BASE_URL) ?>/admin/crf_revisi_request.php?id=<?= (int)$crf['id'] ?>" class="btn btn-warning">Minta Revisi</a>
        <button type="button" class="btn btn-danger" onclick="document.getElementById('rejectModal').classList.add('show')">Tolak</button>
        <button type="button" class="btn btn-success" onclick="document.getElementById('approveModal').classList.add('show')">Setujui CRF</button>

    <?php elseif ($crf['status'] === 'disetujui'): ?>
        <a href="<?= e(BASE_URL) ?>/admin/crf_update_status.php?id=<?= (int)$crf['id'] ?>" class="btn btn-primary">Tandai Selesai</a>

    <?php elseif ($crf['status'] === 'perlu_revisi'): ?>
        <p class="text-muted" style="margin:0;">Menunggu pemohon mengirim ulang revisi.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h2 class="section-title">Informasi Pemohon</h2>
    <div class="form-grid-2">
        <div><span class="text-muted">Nama Lengkap</span><br><strong><?= e($crf['nama_pemohon']) ?></strong></div>
        <div><span class="text-muted">No Handphone / WA</span><br><strong><?= e($crf['nomor_hp'] ?? '-') ?></strong></div>
    </div>
    <div class="form-grid-2">
        <div><span class="text-muted">Email Pemohon</span><br><strong><?= e($crf['email_pemohon']) ?></strong></div>
        <div><span class="text-muted">Departemen</span><br><strong><?= e($crf['departemen'] ?? '-') ?></strong></div>
    </div>
    <div class="form-group">
        <span class="text-muted">Jabatan</span><br><strong><?= e($crf['jabatan'] ?? '-') ?></strong>
    </div>
</div>

<div class="card">
    <h2 class="section-title">Informasi Change Request</h2>
    <div class="form-grid-2">
        <div><span class="text-muted">Judul Change Request</span><br><strong><?= e($crf['judul']) ?></strong></div>
        <div><span class="text-muted">Kategori Perubahan</span><br><strong><?= e($crf['sistem_aplikasi']) ?></strong></div>
    </div>
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

    <p><span class="text-muted">Dokumen Pendukung / Lampiran</span><br>
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
    <h2 class="section-title">Catatan Komite CAB / Feedback Admin</h2>
    <form method="POST" action="<?= e(BASE_URL) ?>/actions/crf_admin_add_note.php">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="crf_id" value="<?= (int)$crf['id'] ?>">
        <div class="form-group">
            <textarea name="catatan" rows="3" placeholder="Tuliskan catatan tambahan evaluasi teknis, instruksi, atau progres di sini..."></textarea>
        </div>
        <div class="form-actions" style="border:none; margin-top:0;">
            <button type="submit" class="btn btn-primary btn-sm">Simpan Catatan</button>
        </div>
    </form>
</div>

<div class="card">
    <h2 class="section-title">Riwayat Alur Proses (Timeline)</h2>
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
    <a href="<?= e(BASE_URL) ?>/admin/crf_list.php" class="btn btn-outline">&lt; Kembali Ke List</a>
</div>

<!-- Modal Setujui -->
<div class="modal-overlay" id="approveModal">
    <div class="modal-box">
        <h3>Konfirmasi Persetujuan</h3>
        <p>Apakah Anda yakin ingin menyetujui Change Request ini? Status akan berubah menjadi Disetujui.</p>
        <form method="POST" action="<?= e(BASE_URL) ?>/actions/crf_admin_approve.php">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="crf_id" value="<?= (int)$crf['id'] ?>">
            <div class="form-group">
                <label>Catatan Persetujuan (opsional)</label>
                <textarea name="catatan" rows="2"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('approveModal').classList.remove('show')">Batal</button>
                <button type="submit" class="btn btn-success">Ya, Setujui</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Tolak -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal-box">
        <h3>Konfirmasi Penolakan</h3>
        <p>Mohon isi alasan penolakan. Pemohon akan melihat catatan ini.</p>
        <form method="POST" action="<?= e(BASE_URL) ?>/actions/crf_admin_reject.php">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="crf_id" value="<?= (int)$crf['id'] ?>">
            <div class="form-group">
                <label>Alasan Penolakan *</label>
                <textarea name="catatan" rows="3" required></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('rejectModal').classList.remove('show')">Batal</button>
                <button type="submit" class="btn btn-danger">Ya, Tolak</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>