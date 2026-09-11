<?php
/**
 * Menandai notifikasi sebagai sudah dibaca.
 * GET  ?id=X        -> tandai 1 notifikasi dibaca, lalu redirect ke CRF terkait (jika ada)
 * POST mark_all=1   -> tandai semua notifikasi milik user ini sebagai dibaca
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user = currentUser();
$notifPage = BASE_URL . '/' . role_folder($user['role']) . '/notifikasi.php';

// Tandai semua
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['mark_all'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Sesi tidak valid.');
        redirect($notifPage);
    }
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    set_flash('success', 'Semua notifikasi ditandai telah dibaca.');
    redirect($notifPage);
}

// Tandai satu notifikasi + redirect ke CRF terkait
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM notifications WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->execute([$id, $user['id']]);
$notif = $stmt->fetch();

if (!$notif) {
    redirect($notifPage);
}

$stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ?');
$stmt->execute([$id]);

if ($notif['crf_id']) {
    redirect(BASE_URL . '/' . role_folder($user['role']) . '/crf_detail.php?id=' . $notif['crf_id']);
}

redirect($notifPage);