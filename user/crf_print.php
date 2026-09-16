<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('staff');

$user = currentUser();
$id = (int)($_GET['id'] ?? 0);

// Validasi kepemilikan - user hanya boleh cetak CRF miliknya sendiri
$stmt = $pdo->prepare('SELECT * FROM crf_requests WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->execute([$id, $user['id']]);
$crf = $stmt->fetch();

if (!$crf) {
    set_flash('error', 'Change Request tidak ditemukan atau bukan milik Anda.');
    redirect(BASE_URL . '/user/pengajuan_saya.php');
}

// Sisipkan data pemohon dari akun login (dipakai template cetak)
$crf['nama_pemohon']  = $user['nama'];
$crf['email_pemohon'] = $user['email'];
$crf['nomor_hp']      = $user['nomor_hp'];
$crf['departemen']    = $user['departemen'];
$crf['jabatan']       = $user['jabatan'];

$stmt = $pdo->prepare("
    SELECT ca.*, u.nama AS nama_pembuat
    FROM crf_change_actions ca
    JOIN users u ON u.id = ca.created_by
    WHERE ca.crf_id = ?
    ORDER BY ca.created_at ASC
");
$stmt->execute([$id]);
$changeActionRows = $stmt->fetchAll();

$changeActions = ['saran_alternatif' => [], 'post_implementation_review' => [], 'implementasi' => []];
foreach ($changeActionRows as $row) {
    $changeActions[$row['action_type']][] = $row;
}

// Data siapa & kapan CRF ini disetujui (dari activity log)
$stmt = $pdo->prepare("
    SELECT l.created_at, u.nama AS approver_nama
    FROM crf_activity_logs l
    JOIN users u ON u.id = l.actor_id
    WHERE l.crf_id = ? AND l.activity_type = 'approved'
    ORDER BY l.created_at DESC LIMIT 1
");
$stmt->execute([$id]);
$approvalInfo = $stmt->fetch();

require __DIR__ . '/../includes/crf_print_template.php';