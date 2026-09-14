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

if ($crf['status'] !== 'perlu_revisi') {
    set_flash('error', 'Pengajuan ini tidak sedang dalam status Perlu Revisi.');
    redirect(BASE_URL . '/user/crf_detail.php?id=' . $id);
}

$stmt = $pdo->prepare('SELECT * FROM crf_revisions WHERE crf_id = ? ORDER BY requested_at DESC LIMIT 1');
$stmt->execute([$id]);
$latestRevision = $stmt->fetch();

$bagianPerluDirevisi = $latestRevision && $latestRevision['bagian_perlu_direvisi']
    ? explode(',', $latestRevision['bagian_perlu_direvisi'])
    : [];

$stmt = $pdo->prepare('SELECT * FROM crf_attachments WHERE crf_id = ? ORDER BY uploaded_at DESC');
$stmt->execute([$id]);
$existingAttachments = $stmt->fetchAll();

$old = $_SESSION['old_input'] ?? null;
unset($_SESSION['old_input']);

function rv_field($key, $old, $crf) {
    if ($old !== null && isset($old[$key])) return $old[$key];
    return $crf[$key] ?? '';
}

// Parsing kembali Kategori Perubahan dari kolom sistem_aplikasi ("Kategori: Detail")
$currentKategori = '';
$currentDetail = '';
$sistemVal = rv_field('sistem_aplikasi', $old, $crf);
if ($sistemVal && strpos($sistemVal, ':') !== false) {
    [$currentKategori, $currentDetail] = array_map('trim', explode(':', $sistemVal, 2));
}

$kategoriHints = [
    'Aplikasi'       => 'Modul CL / PKS / PKWT / Absensi dsb.',
    'Infrastruktur'  => 'Jaringan Lokal - LAN, Jaringan Internet Public - WAN',
    'Proses'         => 'Modul Payroll - perubahan proses pembuatan Payroll',
    'Security'       => 'Permintaan Perubahan Kewenangan menu / User ID / Password',
    'Lainnya'        => 'Permintaan Sarana Otomasi',
];

$bagianLabels = [
    'deskripsi_perubahan' => 'Deskripsi Perubahan',
    'alasan_perubahan'    => 'Alasan / Justifikasi Bisnis',
    'dampak_perubahan'    => 'Dampak Terhadap Sistem',
    'prioritas'           => 'Skala Prioritas',
    'lampiran'            => 'Lampiran / Dokumen Pendukung',
    'lainnya'             => 'Lainnya',
];

$pageTitle  = 'Revisi Pengajuan CRF - SIAP PPU';
$breadcrumb = 'Help Desk > Revisi Pengajuan';
$activeMenu = 'pengajuan_saya';
require __DIR__ . '/../includes/header.php';
?>

<div style="display:flex; align-items:center; justify-content:space-between;">
    <h1 class="page-title">Revisi Pengajuan - <?= e($crf['nomor_crf']) ?></h1>
    <span class="badge badge-revisi">PERLU REVISI</span>
</div>

<?php if ($latestRevision): ?>
<div class="alert alert-error" style="margin-bottom:1.5rem;">
    <strong>Catatan Revisi dari Admin:</strong><br>
    <?= nl2br(e($latestRevision['catatan_revisi'])) ?>

    <?php if (!empty($bagianPerluDirevisi)): ?>
        <div style="margin-top:0.6rem;">
            <strong style="font-size:0.82rem;">Bagian yang perlu direvisi:</strong>
            <ul style="margin:0.3rem 0 0; padding-left:1.2rem; font-size:0.85rem;">
                <?php foreach ($bagianPerluDirevisi as $b): ?>
                    <li><?= e($bagianLabels[trim($b)] ?? trim($b)) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<form method="POST" action="<?= e(BASE_URL) ?>/actions/crf_revision_submit.php" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="crf_id" value="<?= (int)$crf['id'] ?>">

    <div class="card">
        <h2 class="section-title">Formulir Revisi Change Request (CRF)</h2>

        <div class="form-grid-2">
            <div class="form-group">
                <label>Judul Change Request *</label>
                <input type="text" name="judul" required value="<?= e(rv_field('judul', $old, $crf)) ?>">
            </div>
            <div class="form-group">
                <label>Target Waktu Implementasi *</label>
                <input type="date" name="target_waktu" required value="<?= e(rv_field('target_waktu', $old, $crf)) ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Kategori Perubahan *</label>
            <div class="radio-group" id="kategoriPerubahanGroup">
                <?php foreach ($kategoriHints as $kat => $hint): ?>
                    <label>
                        <input type="radio" name="kategori_perubahan" value="<?= e($kat) ?>"
                               data-hint="<?= e('sebutkan misal ' . $hint) ?>"
                               <?= $currentKategori === $kat ? 'checked' : '' ?> required>
                        <?= e($kat) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <input type="text" name="kategori_perubahan_detail" id="kategoriPerubahanDetail"
                   style="margin-top:0.5rem;" required value="<?= e($currentDetail) ?>">
        </div>

        <div class="form-group">
            <label>Jenis Perubahan *</label>
            <?php
                $jenisOptions = ['Fitur Baru (Enhancement)', 'Perbaikan Bug (Bug Fix)', 'Infrastruktur', 'Integrasi Sistem', 'Lainnya'];
                $currentJenis = rv_field('jenis_perubahan', $old, $crf);
            ?>
            <select name="jenis_perubahan" required>
                <option value="">-- Pilih Jenis Perubahan --</option>
                <?php foreach ($jenisOptions as $opt): ?>
                    <option value="<?= e($opt) ?>" <?= $currentJenis === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Deskripsi Perubahan *</label>
            <textarea name="deskripsi_perubahan" rows="3" required><?= e(rv_field('deskripsi_perubahan', $old, $crf)) ?></textarea>
        </div>

        <div class="form-group">
            <label>Alasan Permohonan Perubahan (Justifikasi Bisnis) *</label>
            <textarea name="alasan_perubahan" rows="3" required><?= e(rv_field('alasan_perubahan', $old, $crf)) ?></textarea>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label>Kondisi Saat Ini *</label>
                <textarea name="kondisi_saat_ini" rows="3" required><?= e(rv_field('kondisi_saat_ini', $old, $crf)) ?></textarea>
            </div>
            <div class="form-group">
                <label>Kondisi Yang Diharapkan *</label>
                <textarea name="kondisi_diharapkan" rows="3" required><?= e(rv_field('kondisi_diharapkan', $old, $crf)) ?></textarea>
            </div>
        </div>

        <div class="form-group">
            <label>Benefit dari Perubahan yang Diharapkan *</label>
            <textarea name="benefit_perubahan" rows="3" required><?= e(rv_field('benefit_perubahan', $old, $crf)) ?></textarea>
        </div>

        <div class="form-group">
            <label>Dampak Jika Perubahan Tidak Dilakukan *</label>
            <textarea name="dampak_perubahan" rows="3" required><?= e(rv_field('dampak_perubahan', $old, $crf)) ?></textarea>
        </div>

        <div class="form-group">
            <label>Tingkat Urgensi *</label>
            <?php
                $prioritasOptions = ['rendah' => 'Rendah', 'sedang' => 'Sedang', 'tinggi' => 'Tinggi'];
                $currentPrioritas = rv_field('prioritas', $old, $crf) ?: 'sedang';
            ?>
            <div class="radio-group">
                <?php foreach ($prioritasOptions as $val => $label): ?>
                    <label>
                        <input type="radio" name="prioritas" value="<?= $val ?>" <?= $currentPrioritas === $val ? 'checked' : '' ?> required>
                        <?= $label ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="form-group">
            <label>Lampiran Pendukung Baru (Mockup/Dokumen Alur)</label>
            <input type="file" name="lampiran[]" multiple accept=".pdf,.zip,.jpg,.jpeg">
            <p class="field-hint">Opsional. Maksimal ukuran per file: 5MB (PDF/ZIP/JPG). File lama tetap tersimpan.</p>

            <?php if (!empty($existingAttachments)): ?>
                <ul class="attachment-list">
                    <?php foreach ($existingAttachments as $att): ?>
                        <li>&#128206; <?= e($att['original_name']) ?> (<?= round($att['size'] / 1024, 1) ?> KB)</li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-actions">
        <a href="<?= e(BASE_URL) ?>/user/crf_detail.php?id=<?= (int)$crf['id'] ?>" class="btn btn-outline">Batal</a>
        <button type="submit" class="btn btn-success">Kirim Revisi Pengajuan</button>
    </div>
</form>

<script>
    const kategoriRadios = document.querySelectorAll('#kategoriPerubahanGroup input[name="kategori_perubahan"]');
    const kategoriDetail = document.getElementById('kategoriPerubahanDetail');
    kategoriRadios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            kategoriDetail.placeholder = this.dataset.hint;
        });
        if (radio.checked) {
            kategoriDetail.placeholder = radio.dataset.hint;
        }
    });
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>