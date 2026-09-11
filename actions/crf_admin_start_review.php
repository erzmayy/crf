<?php
/**
 * Admin mulai memeriksa CRF: status diajukan -> dalam_pemeriksaan.
 */
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/admin/crf_list.php');
}

$admin = currentUser();

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('error', 'Sesi tidak valid, silakan coba lagi.');
    redirect(BASE_URL . '/admin/crf_list.php');
}

$crfId = (int)($_POST['crf_id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM crf_requests WHERE id = ? AND status = "diajukan" LIMIT 1');
$stmt->execute([$crfId]);
$crf = $stmt->fetch();

if (!$crf) {
    set_flash('error', 'Change Request tidak ditemukan atau statusnya sudah berubah.');
    redirect(BASE_URL . '/admin/crf_list.php');
}

$stmt = $pdo->prepare("UPDATE crf_requests SET status = 'dalam_pemeriksaan' WHERE id = ?");
$stmt->execute([$crfId]);

$stmt = $pdo->prepare("
    INSERT INTO crf_activity_logs (crf_id, actor_id, actor_role, activity_type, status_before, status_after, catatan)
    VALUES (?, ?, 'admin', 'review_started', 'diajukan', 'dalam_pemeriksaan', NULL)
");
$stmt->execute([$crfId, $admin['id']]);

$notifStmt = $pdo->prepare("
    INSERT INTO notifications (user_id, crf_id, type, title, message)
    VALUES (?, ?, 'status_berubah', ?, ?)
");
$notifStmt->execute([
    $crf['user_id'],
    $crfId,
    "CRF Sedang Diperiksa [{$crf['nomor_crf']}]",
    "Permohonan Anda saat ini masuk ke dalam antrean evaluasi teknis oleh Admin.",
]);

set_flash('success', 'Status diubah menjadi Dalam Pemeriksaan.');
redirect(BASE_URL . '/admin/crf_detail.php?id=' . $crfId);