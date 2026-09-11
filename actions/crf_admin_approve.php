<?php
/**
 * Admin menyetujui CRF: status (diajukan/dalam_pemeriksaan) -> disetujui.
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

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT * FROM crf_requests
        WHERE id = ? AND status IN ('diajukan', 'dalam_pemeriksaan')
        FOR UPDATE
    ");
    $stmt->execute([$crfId]);
    $crf = $stmt->fetch();

    if (!$crf) {
        $pdo->rollBack();
        set_flash('error', 'Change Request tidak ditemukan atau statusnya sudah berubah.');
        redirect(BASE_URL . '/admin/crf_list.php');
    }

    $stmt = $pdo->prepare("UPDATE crf_requests SET status = 'disetujui' WHERE id = ?");
    $stmt->execute([$crfId]);

    $stmt = $pdo->prepare("
        INSERT INTO crf_activity_logs (crf_id, actor_id, actor_role, activity_type, status_before, status_after, catatan)
        VALUES (?, ?, 'admin', 'approved', ?, 'disetujui', ?)
    ");
    $stmt->execute([$crfId, $admin['id'], $crf['status'], $catatan ?: null]);

    $notifStmt = $pdo->prepare("
        INSERT INTO notifications (user_id, crf_id, type, title, message)
        VALUES (?, ?, 'status_berubah', ?, ?)
    ");
    $notifStmt->execute([
        $crf['user_id'],
        $crfId,
        "CRF Berhasil Disetujui [{$crf['nomor_crf']}]",
        "Change Request Form Anda untuk \"{$crf['judul']}\" telah disetujui oleh Admin.",
    ]);

    $pdo->commit();

    set_flash('success', 'Change Request berhasil disetujui.');
    redirect(BASE_URL . '/admin/crf_detail.php?id=' . $crfId);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    set_flash('error', 'Terjadi kesalahan saat menyetujui Change Request.');
    redirect(BASE_URL . '/admin/crf_detail.php?id=' . $crfId);
}