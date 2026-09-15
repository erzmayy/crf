<?php
/**
 * Menyimpan CRF sebagai draft (create atau update).
 * Dipanggil dari 2 tombol di form: "Simpan Draft" dan "Ajukan CRF".
 * - intent=draft  -> simpan lalu kembali ke form (tetap draft)
 * - intent=review -> simpan lalu lanjut ke halaman review (masih draft, belum submit final)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/wasabi.php';
requireRole('staff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/user/crf_form.php');
}

$user = currentUser();

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('error', 'Sesi tidak valid, silakan coba lagi.');
    redirect(BASE_URL . '/user/crf_form.php');
}

$crfId       = isset($_POST['crf_id']) ? (int)$_POST['crf_id'] : null;
$helpdeskId  = isset($_POST['helpdesk_id']) ? (int)$_POST['helpdesk_id'] : null;
$intent      = $_POST['intent'] ?? 'draft';

// Sistem/Aplikasi: kalau pilih "Lainnya", pakai isian teks bebas
// Kategori Perubahan + Detail -> digabung jadi satu nilai "Kategori: Detail"
// disimpan di kolom sistem_aplikasi (kolom lama, makna baru).
$kategoriPerubahan = $_POST['kategori_perubahan'] ?? '';
$kategoriDetail    = trim($_POST['kategori_perubahan_detail'] ?? '');
$sistemAplikasi    = $kategoriPerubahan !== '' ? "$kategoriPerubahan: $kategoriDetail" : '';

$data = [
    'judul'               => trim($_POST['judul'] ?? ''),
    'sistem_aplikasi'     => $sistemAplikasi,
    'jenis_perubahan'     => $_POST['jenis_perubahan'] ?? '',
    'deskripsi_perubahan' => trim($_POST['deskripsi_perubahan'] ?? ''),
    'alasan_perubahan'    => trim($_POST['alasan_perubahan'] ?? ''),
    'benefit_perubahan'   => trim($_POST['benefit_perubahan'] ?? ''),
    'dampak_perubahan'    => trim($_POST['dampak_perubahan'] ?? ''),
    'aset_sumber_pendukung' => trim($_POST['aset_sumber_pendukung'] ?? ''),
    'prioritas'           => $_POST['prioritas'] ?? 'sedang',
    'target_waktu'        => $_POST['target_waktu'] ?? '',
];

// Validasi dasar: semua field wajib tidak boleh kosong
$requiredLabels = [
    'judul' => 'Judul Change Request',
    'sistem_aplikasi' => 'Kategori Perubahan (beserta detail keterangan)',
    'jenis_perubahan' => 'Jenis Perubahan',
    'deskripsi_perubahan' => 'Deskripsi Perubahan',
    'alasan_perubahan' => 'Alasan Permohonan Perubahan',
    'benefit_perubahan' => 'Benefit dari Perubahan',
    'dampak_perubahan' => 'Dampak Jika Tidak Dilakukan',
    'target_waktu' => 'Target Waktu Implementasi',
];

$errors = [];
foreach ($requiredLabels as $key => $label) {
    if ($data[$key] === '') {
        $errors[] = "$label wajib diisi.";
    }
}
if (!in_array($data['prioritas'], ['rendah', 'sedang', 'tinggi'], true)) {
    $errors[] = 'Tingkat Urgensi wajib dipilih.';
}
if ($data['target_waktu'] !== '' && !DateTime::createFromFormat('Y-m-d', $data['target_waktu'])) {
    $errors[] = 'Format Target Waktu Implementasi tidak valid.';
}

if (!empty($errors)) {
    $_SESSION['old_input'] = $_POST;
    set_flash('error', implode(' ', $errors));
    $redirectUrl = BASE_URL . '/user/crf_form.php';
    if ($crfId) $redirectUrl .= '?id=' . $crfId;
    elseif ($helpdeskId) $redirectUrl .= '?helpdesk_id=' . $helpdeskId;
    redirect($redirectUrl);
}

// --- Simpan ke database (create atau update, hanya jika masih draft & milik sendiri) ---
if ($crfId) {
    $stmt = $pdo->prepare('SELECT id FROM crf_requests WHERE id = ? AND user_id = ? AND status = "draft" LIMIT 1');
    $stmt->execute([$crfId, $user['id']]);
    if (!$stmt->fetch()) {
        set_flash('error', 'Change Request tidak ditemukan, bukan milik Anda, atau sudah tidak berstatus draft.');
        redirect(BASE_URL . '/user/pengajuan_saya.php');
    }

    $stmt = $pdo->prepare("
        UPDATE crf_requests SET
            judul = ?, sistem_aplikasi = ?, jenis_perubahan = ?, deskripsi_perubahan = ?,
            alasan_perubahan = ?, kondisi_saat_ini = '', kondisi_diharapkan = '',
            benefit_perubahan = ?, dampak_perubahan = ?, prioritas = ?, target_waktu = ?,
            helpdesk_ticket_id = COALESCE(helpdesk_ticket_id, ?)
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([
        $data['judul'], $data['sistem_aplikasi'], $data['jenis_perubahan'], $data['deskripsi_perubahan'],
        $data['alasan_perubahan'], $data['benefit_perubahan'], $data['dampak_perubahan'],
        $data['prioritas'], $data['target_waktu'], $helpdeskId, $crfId, $user['id'],
    ]);

    $activityType = 'draft_updated';
} else {
    $stmt = $pdo->prepare("
        INSERT INTO crf_requests
            (user_id, helpdesk_ticket_id, judul, sistem_aplikasi, jenis_perubahan, deskripsi_perubahan,
             alasan_perubahan, kondisi_saat_ini, kondisi_diharapkan, benefit_perubahan, dampak_perubahan,
             prioritas, target_waktu, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, '', '', ?, ?, ?, ?, 'draft')
    ");
    $stmt->execute([
        $user['id'], $helpdeskId, $data['judul'], $data['sistem_aplikasi'], $data['jenis_perubahan'],
        $data['deskripsi_perubahan'], $data['alasan_perubahan'], $data['benefit_perubahan'],
        $data['dampak_perubahan'], $data['prioritas'], $data['target_waktu'],
    ]);
    $crfId = (int)$pdo->lastInsertId();

    // Tautkan balik ke tiket Helpdesk asal, jika ada
    if ($helpdeskId) {
        $stmt = $pdo->prepare('UPDATE helpdesk_tickets_dummy SET crf_id = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$crfId, $helpdeskId, $user['id']]);
    }

    $activityType = 'draft_created';
}

// Catat activity log
$stmt = $pdo->prepare("
    INSERT INTO crf_activity_logs (crf_id, actor_id, actor_role, activity_type, status_before, status_after, catatan)
    VALUES (?, ?, 'staff', ?, 'draft', 'draft', NULL)
");
$stmt->execute([$crfId, $user['id'], $activityType]);

// --- Proses upload lampiran (opsional, bisa lebih dari satu file) ---
if (!empty($_FILES['lampiran']['name'][0])) {
    $fileCount = count($_FILES['lampiran']['name']);
    $uploadErrors = [];

    for ($i = 0; $i < $fileCount; $i++) {
        if ($_FILES['lampiran']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;

        if ($_FILES['lampiran']['error'][$i] !== UPLOAD_ERR_OK) {
            $uploadErrors[] = "Gagal mengunggah file: " . e($_FILES['lampiran']['name'][$i]);
            continue;
        }

        $originalName = $_FILES['lampiran']['name'][$i];
        $tmpName      = $_FILES['lampiran']['tmp_name'][$i];
        $size         = $_FILES['lampiran']['size'][$i];
        $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $tmpName);
        finfo_close($finfo);

        if (!in_array($ext, ALLOWED_EXTENSIONS, true) || !in_array($mime, ALLOWED_MIME_TYPES, true)) {
            $uploadErrors[] = "$originalName: format tidak valid (hanya PDF/ZIP).";
            continue;
        }
        if ($size > MAX_UPLOAD_SIZE) {
            $uploadErrors[] = "$originalName: ukuran melebihi 5MB.";
            continue;
        }

        $storedName = 'crf_' . $crfId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $objectKey  = 'crf/' . $crfId . '/' . $storedName;

        if (wasabi_upload_file($tmpName, $objectKey, $mime)) {
            $stmt = $pdo->prepare("
                INSERT INTO crf_attachments (crf_id, uploaded_by, original_name, stored_name, file_path, mime_type, size)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$crfId, $user['id'], $originalName, $storedName, $objectKey, $mime, $size]);
        } else {
            $uploadErrors[] = "$originalName: gagal diunggah ke storage. DETAIL: " . ($GLOBALS['wasabi_last_error'] ?? 'tidak diketahui');        }
    }

    if (!empty($uploadErrors)) {
        set_flash('error', implode(' ', $uploadErrors));
    }
}

if ($intent === 'review') {
    redirect(BASE_URL . '/user/crf_review.php?id=' . $crfId);
}

set_flash('success', 'Draft berhasil disimpan.');
redirect(BASE_URL . '/user/crf_form.php?id=' . $crfId);