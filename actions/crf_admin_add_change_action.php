<?php
/**
 * Menyimpan entry baru untuk fitur "Change Request Action":
 * Saran Alternatif / Post Implementation Review / Implementasi.
 * Bisa ditambah berkali-kali (riwayat), tidak menimpa entry sebelumnya.
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

$crfId      = (int)($_POST['crf_id'] ?? 0);
$actionType = $_POST['action_type'] ?? '';
$items      = trim($_POST['items'] ?? '');
$tanggal    = $_POST['tanggal'] ?? '';

$allowedTypes = ['saran_alternatif', 'post_implementation_review', 'implementasi'];
$labels = [
    'saran_alternatif'           => 'Saran Alternatif',
    'post_implementation_review' => 'Post Implementation Review',
    'implementasi'                => 'Implementasi',
];

if (!in_array($actionType, $allowedTypes, true)) {
    set_flash('error', 'Jenis Change Request Action tidak valid.');
    redirect(BASE_URL . '/admin/crf_detail.php?id=' . $crfId);
}
if ($items === '') {
    set_flash('error', 'Isi minimal satu poin sebelum menyimpan.');
    redirect(BASE_URL . '/admin/crf_detail.php?id=' . $crfId);
}

$stmt = $pdo->prepare('SELECT id FROM crf_requests WHERE id = ? LIMIT 1');
$stmt->execute([$crfId]);
if (!$stmt->fetch()) {
    set_flash('error', 'Change Request tidak ditemukan.');
    redirect(BASE_URL . '/admin/crf_list.php');
}

$stmt = $pdo->prepare("
    INSERT INTO crf_change_actions (crf_id, action_type, items, tanggal, created_by)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->execute([$crfId, $actionType, $items, $tanggal ?: null, $admin['id']]);

$stmt = $pdo->prepare("
    INSERT INTO crf_activity_logs (crf_id, actor_id, actor_role, activity_type, status_before, status_after, catatan)
    VALUES (?, ?, 'admin', 'status_updated', NULL, NULL, ?)
");
$stmt->execute([$crfId, $admin['id'], $labels[$actionType] . ' ditambahkan oleh ' . $admin['nama']]);

set_flash('success', $labels[$actionType] . ' berhasil disimpan.');
redirect(BASE_URL . '/admin/crf_detail.php?id=' . $crfId);