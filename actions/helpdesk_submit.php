<?php
/**
 * Endpoint pemrosesan Formulir Help Desk (simulasi).
 * Menyimpan tiket ke helpdesk_tickets_dummy, lalu:
 * - Jika Jenis Permintaan = Change Request Form -> redirect ke user/crf_form.php
 * - Jika Jenis Permintaan = Indikasi Sebelum Permasalahan -> selesai (di luar cakupan modul CRF)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/wasabi.php';
requireRole('staff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/helpdesk/form.php');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('error', 'Sesi tidak valid, silakan coba lagi.');
    redirect(BASE_URL . '/helpdesk/form.php');
}

$user = currentUser();

$jenis_permintaan   = $_POST['jenis_permintaan'] ?? '';
$jenis_permasalahan = $_POST['jenis_permasalahan'] ?? '';
$problem_masalah    = trim($_POST['problem_masalah'] ?? '');
$kategori_dampak    = $_POST['kategori_dampak'] ?? '';
$jam_mulai_laporan  = $_POST['jam_mulai_laporan'] ?? '';
$isi_pesan          = trim($_POST['isi_pesan'] ?? '');

$errors = [];
if (!in_array($jenis_permintaan, ['indikasi_sebelum_permasalahan', 'change_request_form'], true)) {
    $errors[] = 'Jenis Permintaan wajib dipilih.';
}
if (!in_array($jenis_permasalahan, ['IT', 'Non IT'], true)) {
    $errors[] = 'Jenis Permasalahan wajib dipilih.';
}
if ($problem_masalah === '') {
    $errors[] = 'Problem/Masalah wajib dipilih.';
}
if (!in_array($kategori_dampak, ['maintenance', 'request', 'komplain'], true)) {
    $errors[] = 'Kategori/Dampak wajib dipilih.';
}
if ($jam_mulai_laporan === '') {
    $errors[] = 'Jam Mulai Laporan wajib diisi.';
}
if ($isi_pesan === '') {
    $errors[] = 'Isi Pesan wajib diisi.';
}

// Validasi & simpan dokumen pendukung (opsional)
$dokumenPath = null;
if (!empty($_FILES['dokumen_pendukung']['name'])) {
    $file = $_FILES['dokumen_pendukung'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Gagal mengunggah dokumen pendukung.';
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($ext, ALLOWED_EXTENSIONS, true) || !in_array($mime, ALLOWED_MIME_TYPES, true)) {
            $errors[] = 'Format dokumen tidak valid. Hanya PDF/ZIP yang diizinkan.';
        } elseif ($file['size'] > MAX_UPLOAD_SIZE) {
            $errors[] = 'Ukuran dokumen melebihi 5MB.';
        } else {
            $storedName = 'helpdesk_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $objectKey  = 'helpdesk/' . $storedName;

            if (wasabi_upload_file($file['tmp_name'], $objectKey, $mime)) {
                $dokumenPath = $objectKey;
            } else {
                $errors[] = 'Gagal mengunggah dokumen pendukung ke storage.';
            }
        }
    }
}

if (!empty($errors)) {
    set_flash('error', implode(' ', $errors));
    redirect(BASE_URL . '/helpdesk/form.php');
}

$stmt = $pdo->prepare("
    INSERT INTO helpdesk_tickets_dummy
        (user_id, nama_pemohon, no_hp, email, jenis_permasalahan, problem_masalah, jenis_permintaan, kategori_dampak, jam_mulai_laporan, isi_pesan, dokumen_pendukung)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->execute([
    $user['id'],
    $user['nama'],
    $user['nomor_hp'],
    $user['email'],
    $jenis_permasalahan,
    $problem_masalah,
    $jenis_permintaan,
    $kategori_dampak,
    $jam_mulai_laporan,
    $isi_pesan,
    $dokumenPath,
]);

$ticketId = (int)$pdo->lastInsertId();

if ($jenis_permintaan === 'change_request_form') {
    set_flash('success', 'Tiket Help Desk berhasil dibuat. Silakan lanjutkan mengisi Change Request Form di bawah ini.');
    redirect(BASE_URL . '/user/crf_form.php?helpdesk_id=' . $ticketId);
} else {
    set_flash('success', 'Tiket Help Desk berhasil dibuat (simulasi). Alur penanganan "Indikasi Sebelum Permasalahan" berada di luar cakupan modul CRF ini.');
    redirect(BASE_URL . '/helpdesk/form.php');
}
