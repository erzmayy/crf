<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

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