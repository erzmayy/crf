<?php
/**
 * Admin menolak CRF: status diajukan -> ditolak.
 * Catatan/alasan penolakan wajib diisi.
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
    set_flash('error', 'Alasan penolakan wajib diisi.');
    redirect(BASE_URL . '/admin/crf_detail.php?id=' . $crfId);
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT * FROM crf_requests
        WHERE id = ? AND status = 'diajukan'
        FOR UPDATE
    ");
    $stmt->execute([$crfId]);
    $crf = $stmt->fetch();

    if (!$crf) {
        $pdo->rollBack();
        set_flash('error', 'Change Request tidak ditemukan atau statusnya sudah berubah.');
        redirect(BASE_URL . '/admin/crf_list.php');
    }

    $stmt = $pdo->prepare("UPDATE crf_requests SET status = 'ditolak' WHERE id = ?");
    $stmt->execute([$crfId]);

    $stmt = $pdo->prepare("
        INSERT INTO crf_activity_logs (crf_id, actor_id, actor_role, activity_type, status_before, status_after, catatan)
        VALUES (?, ?, 'admin', 'rejected', ?, 'ditolak', ?)
    ");
    $stmt->execute([$crfId, $admin['id'], $crf['status'], $catatan]);

    $notifStmt = $pdo->prepare("
        INSERT INTO notifications (user_id, crf_id, type, title, message)
        VALUES (?, ?, 'status_berubah', ?, ?)
    ");
    $notifStmt->execute([
        $crf['user_id'],
        $crfId,
        "CRF Ditolak [{$crf['nomor_crf']}]",
        "Change Request Form Anda untuk \"{$crf['judul']}\" ditolak. Alasan: $catatan",
    ]);

    $pdo->commit();

    set_flash('success', 'Change Request telah ditolak.');
    redirect(BASE_URL . '/admin/crf_detail.php?id=' . $crfId);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    set_flash('error', 'Terjadi kesalahan saat menolak Change Request.');
    redirect(BASE_URL . '/admin/crf_detail.php?id=' . $crfId);
}