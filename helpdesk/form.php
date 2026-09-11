<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('staff');

$user = currentUser();
$csrfToken = generate_csrf_token();

$pageTitle  = 'Formulir Help Desk - SIAP PPU';
$breadcrumb = 'Help Desk > Formulir Help Desk PPU';
$activeMenu = 'helpdesk_form';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">Formulir Help Desk PPU</h1>
<p class="page-subtitle">
    Ajukan laporan atau permintaan melalui Help Desk. Jika permintaan Anda berupa perubahan sistem/fitur,
    pilih <strong>Change Request Form</strong> di bawah untuk melanjutkan ke formulir CRF.
</p>

<div class="card">
<form method="POST" action="<?= e(BASE_URL) ?>/actions/helpdesk_submit.php" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

    <div class="form-grid-2">
        <div class="form-group">
            <label>Nama Lengkap *</label>
            <input type="text" value="<?= e($user['nama']) ?>" readonly class="input-readonly">
        </div>
        <div class="form-group">
            <label>No Handphone / WA *</label>
            <input type="text" value="<?= e($user['nomor_hp'] ?? '-') ?>" readonly class="input-readonly">
        </div>
    </div>

    <div class="form-group">
        <label>Email *</label>
        <input type="text" value="<?= e($user['email']) ?>" readonly class="input-readonly">
    </div>

    <div class="form-group">
        <label>Jenis Permintaan *</label>
        <div class="radio-group">
            <label><input type="radio" name="jenis_permintaan" value="indikasi_sebelum_permasalahan" required> Indikasi Sebelum Permasalahan</label>
            <label><input type="radio" name="jenis_permintaan" value="change_request_form" required> Change Request Form</label>
        </div>
    </div>

    <div class="form-grid-2">
        <div class="form-group">
            <label>Jenis Permasalahan *</label>
            <select name="jenis_permasalahan" id="jenisPermasalahan" required>
                <option value="">-- Pilih Jenis Permasalahan --</option>
                <option value="IT">IT</option>
                <option value="Non IT">Non IT</option>
            </select>
        </div>
        <div class="form-group">
            <label>Problem / Masalah *</label>
            <select name="problem_masalah" id="problemMasalah" required>
                <option value="">-- Pilih Problem / Masalah --</option>
            </select>
        </div>
    </div>

    <div class="form-group">
        <label>Kategori / Dampak *</label>
        <div class="radio-group">
            <label><input type="radio" name="kategori_dampak" value="maintenance" required> Maintenance / Kegiatan Rutin</label>
            <label><input type="radio" name="kategori_dampak" value="request" required> Request / Permintaan</label>
            <label><input type="radio" name="kategori_dampak" value="komplain" required> Komplain</label>
        </div>
    </div>

    <div class="form-group">
        <label>Jam Mulai Laporan *</label>
        <input type="time" name="jam_mulai_laporan" required style="max-width:200px;">
    </div>

    <div class="form-group">
        <label>Isi Pesan / Detail Permasalahan *</label>
        <textarea name="isi_pesan" rows="4" required placeholder="Silahkan isi pesan Anda"></textarea>
    </div>

    <div class="form-group">
        <label>Dokumen Pendukung</label>
        <input type="file" name="dokumen_pendukung" accept=".pdf,.zip,.jpg,.jpeg">
        <p class="field-hint">Opsional. Maksimal ukuran file: 5MB (PDF/ZIP/JPG).</p>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Kirim Formulir Help Desk</button>
    </div>
</form>
</div>

<script>
    // Opsi dropdown Problem/Masalah tergantung Jenis Permasalahan (IT / Non IT)
    // sesuai poin 8-9 di brief.
    const problemOptions = {
        'IT': [
            'Jaringan / Internet Bermasalah',
            'Aplikasi / Sistem Error',
            'Perangkat Keras (Hardware)',
            'Email / Akun Bermasalah',
            'Permintaan Instalasi Software',
            'Lainnya (IT)'
        ],
        'Non IT': [
            'Fasilitas Kantor',
            'Kendaraan Operasional',
            'Administrasi / Dokumen',
            'Kebersihan & Maintenance Gedung',
            'Lainnya (Non IT)'
        ]
    };

    const jenisSelect = document.getElementById('jenisPermasalahan');
    const problemSelect = document.getElementById('problemMasalah');

    jenisSelect.addEventListener('change', function () {
        const options = problemOptions[this.value] || [];
        problemSelect.innerHTML = '<option value="">-- Pilih Problem / Masalah --</option>';
        options.forEach(function (opt) {
            const el = document.createElement('option');
            el.value = opt;
            el.textContent = opt;
            problemSelect.appendChild(el);
        });
    });
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
