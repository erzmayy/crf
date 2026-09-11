<?php
/**
 * Menyimpan catatan bebas dari Admin (tidak mengubah status),
 * tampil di kotak "Catatan Komite CAB / Feedback Admin" pada halaman detail.
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

$crfId   = (int)($_POST['crf_id'] ?? 0);
$catatan = trim($_POST['catatan'] ?? '');

if ($catatan === '') {
    redirect(BASE_URL . '/admin/crf_detail.php?id=' . $crfId);
}

$stmt = $pdo->prepare('SELECT id, status FROM crf_requests WHERE id = ? LIMIT 1');
$stmt->execute([$crfId]);
$crf = $stmt->fetch();

if (!$crf) {
    set_flash('error', 'Change Request tidak ditemukan.');
    redirect(BASE_URL . '/admin/crf_list.php');
}

$stmt = $pdo->prepare("
    INSERT INTO crf_activity_logs (crf_id, actor_id, actor_role, activity_type, status_before, status_after, catatan)
    VALUES (?, ?, 'admin', 'status_updated', ?, ?, ?)
");
$stmt->execute([$crfId, $admin['id'], $crf['status'], $crf['status'], $catatan]);

set_flash('success', 'Catatan berhasil disimpan.');
redirect(BASE_URL . '/admin/crf_detail.php?id=' . $crfId);