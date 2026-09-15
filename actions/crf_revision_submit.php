<?php
/**
 * Memproses pengiriman ulang revisi CRF oleh user.
 * Status: perlu_revisi -> diajukan (kembali masuk antrean pemeriksaan Admin).
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/wasabi.php';
requireRole('staff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/user/pengajuan_saya.php');
}

$user = currentUser();

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('error', 'Sesi tidak valid, silakan coba lagi.');
    redirect(BASE_URL . '/user/pengajuan_saya.php');
}

$crfId = (int)($_POST['crf_id'] ?? 0);

$kategoriPerubahan = $_POST['kategori_perubahan'] ?? '';
$kategoriDetail     = trim($_POST['kategori_perubahan_detail'] ?? '');
$sistemAplikasi     = $kategoriPerubahan !== '' ? "$kategoriPerubahan: $kategoriDetail" : '';

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
    if (!in_array($data['prioritas'], ['rendah', 'sedang', 'tinggi'], true)) {
        $errors[] = 'Tingkat Urgensi wajib dipilih.';
    }
}
if (!empty($errors)) {
    $_SESSION['old_input'] = $_POST;
    set_flash('error', implode(' ', $errors));
    redirect(BASE_URL . '/user/crf_revisi.php?id=' . $crfId);
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT * FROM crf_requests WHERE id = ? AND user_id = ? AND status = "perlu_revisi" FOR UPDATE');
    $stmt->execute([$crfId, $user['id']]);
    $crf = $stmt->fetch();

    if (!$crf) {
        $pdo->rollBack();
        set_flash('error', 'Change Request tidak ditemukan, bukan milik Anda, atau tidak sedang perlu revisi.');
        redirect(BASE_URL . '/user/pengajuan_saya.php');
    }

    $stmt = $pdo->prepare("
        UPDATE crf_requests SET
            judul = ?, sistem_aplikasi = ?, jenis_perubahan = ?, deskripsi_perubahan = ?,
            alasan_perubahan = ?, kondisi_saat_ini = '', kondisi_diharapkan = '',
            benefit_perubahan = ?, dampak_perubahan = ?, prioritas = ?, target_waktu = ?,
            status = 'diajukan', revision_count = revision_count + 1
        WHERE id = ?
    ");
    $stmt->execute([
        $data['judul'], $data['sistem_aplikasi'], $data['jenis_perubahan'], $data['deskripsi_perubahan'],
        $data['alasan_perubahan'], $data['benefit_perubahan'], $data['dampak_perubahan'],
        $data['prioritas'], $data['target_waktu'], $crfId,
    ]);

    // Tandai revisi terakhir sebagai sudah dikirim ulang
    $stmt = $pdo->prepare("
        UPDATE crf_revisions
        SET resubmitted_at = NOW()
        WHERE crf_id = ? AND resubmitted_at IS NULL
        ORDER BY requested_at DESC LIMIT 1
    ");
    $stmt->execute([$crfId]);

    $stmt = $pdo->prepare("
        INSERT INTO crf_activity_logs (crf_id, actor_id, actor_role, activity_type, status_before, status_after, catatan)
        VALUES (?, ?, 'staff', 'revision_resubmitted', 'perlu_revisi', 'diajukan', NULL)
    ");
    $stmt->execute([$crfId, $user['id']]);

    // Upload lampiran tambahan (opsional, append)
    if (!empty($_FILES['lampiran']['name'][0])) {
        $fileCount = count($_FILES['lampiran']['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['lampiran']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
            if ($_FILES['lampiran']['error'][$i] !== UPLOAD_ERR_OK) continue;

            $originalName = $_FILES['lampiran']['name'][$i];
            $tmpName      = $_FILES['lampiran']['tmp_name'][$i];
            $size         = $_FILES['lampiran']['size'][$i];
            $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $tmpName);
            finfo_close($finfo);

            if (!in_array($ext, ALLOWED_EXTENSIONS, true) || !in_array($mime, ALLOWED_MIME_TYPES, true)) continue;
            if ($size > MAX_UPLOAD_SIZE) continue;

            $storedName = 'crf_' . $crfId . '_rev_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $objectKey  = 'crf/' . $crfId . '/' . $storedName;

            if (wasabi_upload_file($tmpName, $objectKey, $mime)) {
                $stmt = $pdo->prepare("
                    INSERT INTO crf_attachments (crf_id, uploaded_by, original_name, stored_name, file_path, mime_type, size)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$crfId, $user['id'], $originalName, $storedName, $objectKey, $mime, $size]);
            }
        }
    }

    // Notifikasi ke semua Admin
    $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
    $notifStmt = $pdo->prepare("
        INSERT INTO notifications (user_id, crf_id, type, title, message)
        VALUES (?, ?, 'revisi_dikirim', ?, ?)
    ");
    foreach ($admins as $admin) {
        $notifStmt->execute([
            $admin['id'],
            $crfId,
            "User Mengirimkan Berkas Revisi [{$crf['nomor_crf']}]",
            "Pemohon: {$user['nama']} ({$user['departemen']}) telah mengirim ulang revisi untuk {$crf['judul']}.",
        ]);
    }

    $pdo->commit();

    set_flash('success', 'Revisi berhasil dikirim ulang. Pengajuan Anda kembali masuk ke antrean pemeriksaan Admin.');
    redirect(BASE_URL . '/user/crf_detail.php?id=' . $crfId);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', 'Terjadi kesalahan saat mengirim revisi. Silakan coba lagi.');
    redirect(BASE_URL . '/user/crf_revisi.php?id=' . $crfId);
}