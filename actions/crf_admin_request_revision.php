<?php
/**
 * Admin meminta revisi: status (diajukan/dalam_pemeriksaan) -> perlu_revisi.
 * Membuat baris baru di crf_revisions yang nanti akan "ditutup" (resubmitted_at)
 * saat user mengirim ulang lewat user/crf_revisi.php.
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

$crfId         = (int)($_POST['crf_id'] ?? 0);
$catatanRevisi = trim($_POST['catatan_revisi'] ?? '');
$bagian        = $_POST['bagian'] ?? [];

if ($catatanRevisi === '') {
    set_flash('error', 'Catatan Revisi wajib diisi.');
    redirect(BASE_URL . '/admin/crf_revisi_request.php?id=' . $crfId);
}
if (empty($bagian)) {
    set_flash('error', 'Pilih minimal satu Bagian yang Perlu Direvisi.');
    redirect(BASE_URL . '/admin/crf_revisi_request.php?id=' . $crfId);
}

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

    $stmt = $pdo->prepare("UPDATE crf_requests SET status = 'perlu_revisi' WHERE id = ?");
    $stmt->execute([$crfId]);

    $stmt = $pdo->prepare("
        INSERT INTO crf_revisions (crf_id, revision_number, catatan_revisi, bagian_perlu_direvisi, requested_by, requested_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $crfId,
        $crf['revision_count'] + 1,
        $catatanRevisi,
        implode(',', $bagian),
        $admin['id'],
    ]);

    $stmt = $pdo->prepare("
        INSERT INTO crf_activity_logs (crf_id, actor_id, actor_role, activity_type, status_before, status_after, catatan)
        VALUES (?, ?, 'admin', 'revision_requested', ?, 'perlu_revisi', ?)
    ");
    $stmt->execute([$crfId, $admin['id'], $crf['status'], $catatanRevisi]);

    $notifStmt = $pdo->prepare("
        INSERT INTO notifications (user_id, crf_id, type, title, message)
        VALUES (?, ?, 'revisi_diminta', ?, ?)
    ");
    $notifStmt->execute([
        $crf['user_id'],
        $crfId,
        "CRF Membutuhkan Revisi [{$crf['nomor_crf']}]",
        "Catatan Admin: $catatanRevisi",
    ]);

    $pdo->commit();

    set_flash('success', 'Permintaan revisi berhasil dikirim ke pemohon.');
    redirect(BASE_URL . '/admin/crf_detail.php?id=' . $crfId);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    set_flash('error', 'Terjadi kesalahan saat mengirim permintaan revisi.');
    redirect(BASE_URL . '/admin/crf_revisi_request.php?id=' . $crfId);
}