<?php
/**
 * Admin menyelesaikan CRF (Disetujui -> Selesai).
 * Validasi transisi status dilakukan ulang di backend via crf_allowed_next_statuses(),
 * supaya tidak bisa dimanipulasi lewat request langsung.
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

$crfId          = (int)($_POST['crf_id'] ?? 0);
$newStatus      = $_POST['new_status'] ?? '';
$catatan        = trim($_POST['catatan'] ?? '');
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT * FROM crf_requests WHERE id = ? FOR UPDATE');
    $stmt->execute([$crfId]);
    $crf = $stmt->fetch();

    if (!$crf) {
        $pdo->rollBack();
        set_flash('error', 'Change Request tidak ditemukan.');
        redirect(BASE_URL . '/admin/crf_list.php');
    }

    $allowedNext = crf_allowed_next_statuses($crf['status']);
    if (!array_key_exists($newStatus, $allowedNext)) {
        $pdo->rollBack();
        set_flash('error', 'Transisi status tidak valid dari status saat ini.');
        redirect(BASE_URL . '/admin/crf_update_status.php?id=' . $crfId);
    }

    $stmt = $pdo->prepare('UPDATE crf_requests SET status = ? WHERE id = ?');
    $stmt->execute([$newStatus, $crfId]);

    $activityType = $newStatus === 'selesai' ? 'completed' : 'status_updated';

    $stmt = $pdo->prepare("
        INSERT INTO crf_activity_logs (crf_id, actor_id, actor_role, activity_type, status_before, status_after, catatan)
        VALUES (?, ?, 'admin', ?, ?, ?, ?)
    ");
    $stmt->execute([$crfId, $admin['id'], $activityType, $crf['status'], $newStatus, $catatan ?: null]);

    $notifTitles = [
        'selesai' => "CRF Selesai Diimplementasikan [{$crf['nomor_crf']}]",
    ];
    $notifMessages = [
        'selesai'      => "Seluruh tahapan implementasi \"{$crf['judul']}\" telah berhasil diselesaikan.",
    ];

    $notifStmt = $pdo->prepare("
        INSERT INTO notifications (user_id, crf_id, type, title, message)
        VALUES (?, ?, 'status_berubah', ?, ?)
    ");
    $notifStmt->execute([
        $crf['user_id'],
        $crfId,
        $notifTitles[$newStatus] ?? "Status CRF diperbarui [{$crf['nomor_crf']}]",
        $notifMessages[$newStatus] ?? "Status berubah menjadi " . status_label($newStatus),
    ]);

    $pdo->commit();

    set_flash('success', 'Status berhasil diperbarui menjadi ' . status_label($newStatus) . '.');
    redirect(BASE_URL . '/admin/crf_detail.php?id=' . $crfId);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    set_flash('error', 'Terjadi kesalahan saat memperbarui status.');
    redirect(BASE_URL . '/admin/crf_update_status.php?id=' . $crfId);
}