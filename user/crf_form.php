<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('staff');

$user = currentUser();
$csrfToken = generate_csrf_token();

$crf = null;
$existingAttachments = [];
$helpdeskId = isset($_GET['helpdesk_id']) ? (int)$_GET['helpdesk_id'] : null;

// Mode edit: hanya boleh mengedit CRF milik sendiri yang masih berstatus draft.
// Validasi kepemilikan & status dilakukan di backend (bukan hanya sembunyi tombol di UI).
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare('SELECT * FROM crf_requests WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$id, $user['id']]);
    $crf = $stmt->fetch();

    if (!$crf) {
        set_flash('error', 'Change Request tidak ditemukan atau bukan milik Anda.');
        redirect(BASE_URL . '/user/pengajuan_saya.php');
    }

    if ($crf['status'] !== 'draft') {
        set_flash('error', 'Hanya pengajuan berstatus Draft yang dapat diedit melalui halaman ini. Gunakan menu Revisi jika diminta Admin.');
        redirect(BASE_URL . '/user/crf_detail.php?id=' . $id);
    }

    $stmt = $pdo->prepare('SELECT * FROM crf_attachments WHERE crf_id = ? ORDER BY uploaded_at DESC');
    $stmt->execute([$id]);
    $existingAttachments = $stmt->fetchAll();

    $helpdeskId = $crf['helpdesk_ticket_id'];
}

// Jika ada input lama tersimpan (setelah validasi gagal), pakai itu untuk mengisi ulang form
$old = $_SESSION['old_input'] ?? null;
unset($_SESSION['old_input']);

function field_value($key, $old, $crf, $default = '') {
    if ($old !== null && isset($old[$key])) return $old[$key];
    if ($crf !== null && isset($crf[$key])) return $crf[$key];
    return $default;
}

$pageTitle  = 'Change Request Form - SIAP PPU';
$breadcrumb = 'Help Desk > Change Request Form';
$activeMenu = 'crf_form';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">Pengajuan Change Request Form (CRF)</h1>
<p class="page-subtitle">Lengkapi detail perubahan yang Anda ajukan. Anda bisa menyimpan sebagai draft dan melanjutkan mengisi nanti.</p>

<form method="POST" action="<?= e(BASE_URL) ?>/actions/crf_save_draft.php" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
    <?php if ($crf): ?>
        <input type="hidden" name="crf_id" value="<?= (int)$crf['id'] ?>">
    <?php endif; ?>
    <?php if ($helpdeskId): ?>
        <input type="hidden" name="helpdesk_id" value="<?= (int)$helpdeskId ?>">
    <?php endif; ?>

    <div class="card">
        <h2 class="section-title">Data Pemohon (Read-Only)</h2>
        <div class="form-grid-2">
            <div class="form-group">
                <label>Nama Pemohon</label>
                <input type="text" value="<?= e($user['nama']) ?>" readonly class="input-readonly">
            </div>
            <div class="form-group">
                <label>No HP / WA</label>
                <input type="text" value="<?= e($user['nomor_hp'] ?? '-') ?>" readonly class="input-readonly">
            </div>
        </div>
        <div class="form-grid-2">
            <div class="form-group">
                <label>Email</label>
                <input type="text" value="<?= e($user['email']) ?>" readonly class="input-readonly">
            </div>
            <div class="form-group">
                <label>Departemen / Divisi</label>
                <input type="text" value="<?= e($user['departemen'] ?? '-') ?>" readonly class="input-readonly">
            </div>
        </div>
        <div class="form-group">
            <label>Jabatan</label>
            <input type="text" value="<?= e($user['jabatan'] ?? '-') ?>" readonly class="input-readonly">
        </div>
    </div>

    <div class="card">
        <h2 class="section-title">Informasi Change Request (CRF)</h2>

        <div class="form-grid-2">
            <div class="form-group">
                <label>Judul Change Request *</label>
                <input type="text" name="judul" required placeholder="Contoh: Penambahan Fitur Approval Multi-Level"
                       value="<?= e(field_value('judul', $old, $crf)) ?>">
            </div>
                        <div class="form-group">
                <label>Kategori Perubahan *</label>
                <?php
                    // Kolom database tetap 'sistem_aplikasi', hanya makna & tampilannya
                    // diubah jadi "Kategori Perubahan" sesuai referensi form Anda.
                    // Nilai tersimpan sebagai "Kategori: Detail", contoh: "Aplikasi: Modul CL".
                    $kategoriHints = [
                        'Aplikasi'       => 'Modul CL / PKS / PKWT / Absensi dsb.',
                        'Infrastruktur'  => 'Jaringan Lokal - LAN, Jaringan Internet Public - WAN',
                        'Proses'         => 'Modul Payroll - perubahan proses pembuatan Payroll',
                        'Security'       => 'Permintaan Perubahan Kewenangan menu / User ID / Password',
                        'Lainnya'        => 'Permintaan Sarana Otomasi',
                    ];

                    $currentSistem = field_value('sistem_aplikasi', $old, $crf);
                    $currentKategori = '';
                    $currentDetail = '';
                    if ($currentSistem && strpos($currentSistem, ':') !== false) {
                        [$currentKategori, $currentDetail] = array_map('trim', explode(':', $currentSistem, 2));
                    }
                ?>
                <div class="radio-group" id="kategoriPerubahanGroup">
                    <?php foreach ($kategoriHints as $kat => $hint): ?>
                        <label>
                            <input type="radio" name="kategori_perubahan" value="<?= e($kat) ?>"
                                   data-hint="<?= e('Misalnya ' . $hint) ?>"
                                   <?= $currentKategori === $kat ? 'checked' : '' ?> required>
                            <?= e($kat) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <input type="text" name="kategori_perubahan_detail" id="kategoriPerubahanDetail"
                       placeholder="Pilih kategori terlebih dahulu"
                       style="margin-top:0.5rem;" required
                       value="<?= e($currentDetail) ?>">
            </div>
        </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label>Jenis Perubahan *</label>
                <?php
                    $jenisOptions = ['Fitur Baru (Enhancement)', 'Perbaikan Bug (Bug Fix)', 'Infrastruktur', 'Integrasi Sistem', 'Lainnya'];
                    $currentJenis = field_value('jenis_perubahan', $old, $crf);
                ?>
                <select name="jenis_perubahan" required>
                    <option value="">-- Pilih Jenis Perubahan --</option>
                    <?php foreach ($jenisOptions as $opt): ?>
                        <option value="<?= e($opt) ?>" <?= $currentJenis === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Target Waktu Implementasi *</label>
                <input type="date" name="target_waktu" required value="<?= e(field_value('target_waktu', $old, $crf)) ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Deskripsi Perubahan *</label>
            <textarea name="deskripsi_perubahan" rows="3" required
                      placeholder="Jelaskan detail teknis perubahan yang diajukan..."><?= e(field_value('deskripsi_perubahan', $old, $crf)) ?></textarea>
        </div>

        <div class="form-group">
            <label>Alasan Permohonan Perubahan (Justifikasi Bisnis) *</label>
            <textarea name="alasan_perubahan" rows="3" required
                      placeholder="Mengapa perubahan ini diperlukan oleh divisi Anda?"><?= e(field_value('alasan_perubahan', $old, $crf)) ?></textarea>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label>Kondisi Saat Ini *</label>
                <textarea name="kondisi_saat_ini" rows="3" required
                          placeholder="Deskripsikan cara kerja/sistem saat ini..."><?= e(field_value('kondisi_saat_ini', $old, $crf)) ?></textarea>
            </div>
            <div class="form-group">
                <label>Kondisi Yang Diharapkan *</label>
                <textarea name="kondisi_diharapkan" rows="3" required
                          placeholder="Bagaimana sistem seharusnya bekerja setelah perubahan?"><?= e(field_value('kondisi_diharapkan', $old, $crf)) ?></textarea>
            </div>
        </div>

        <div class="form-group">
            <label>Benefit dari Perubahan yang Diharapkan *</label>
            <textarea name="benefit_perubahan" rows="3" required
                      placeholder="Manfaat apa yang didapat jika perubahan ini dijalankan?"><?= e(field_value('benefit_perubahan', $old, $crf)) ?></textarea>
        </div>

        <div class="form-group">
            <label>Dampak Jika Perubahan Tidak Dilakukan *</label>
            <textarea name="dampak_perubahan" rows="3" required
                      placeholder="Apa risiko/dampaknya jika perubahan ini tidak dijalankan?"><?= e(field_value('dampak_perubahan', $old, $crf)) ?></textarea>
        </div>

        <div class="form-group">
            <label>Tingkat Prioritas *</label>
            <?php
                $prioritasOptions = ['rendah' => 'Rendah', 'sedang' => 'Sedang', 'tinggi' => 'Tinggi', 'kritis' => 'Kritis'];
                $currentPrioritas = field_value('prioritas', $old, $crf, 'sedang');
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
            <label>Lampiran Pendukung (Mockup/Dokumen Alur)</label>
            <input type="file" name="lampiran[]" multiple accept=".pdf,.zip">
            <p class="field-hint">Opsional. Maksimal ukuran per file: 5MB (PDF/ZIP).</p>

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
        <button type="submit" name="intent" value="draft" class="btn btn-outline">Simpan Draft</button>
        <button type="submit" name="intent" value="review" class="btn btn-primary">Ajukan CRF</button>
    </div>
</form>

<script>
    // Placeholder detail berubah mengikuti Kategori Perubahan yang dipilih
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