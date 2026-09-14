<?php
/**
 * Submit final CRF: generate nomor CRF, ubah status draft -> diajukan,
 * catat activity log, dan kirim notifikasi ke semua Admin (CAB).
 */
require_once __DIR__ . '/../includes/auth.php';
requireRole('staff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/user/pengajuan_saya.php');
}

$user = currentUser();

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('error', 'Sesi tidak valid, silakan coba lagi.');
    redirect(BASE_URL . '/user/pengajuan_saya.php');
}

$crfId = (int)($_POST['crf_id'] ?? 0);

try {
    $pdo->beginTransaction();

    // Kunci baris CRF ini untuk mencegah submit ganda bersamaan
    $stmt = $pdo->prepare('SELECT * FROM crf_requests WHERE id = ? AND user_id = ? AND status = "draft" FOR UPDATE');
    $stmt->execute([$crfId, $user['id']]);
    $crf = $stmt->fetch();

    if (!$crf) {
        $pdo->rollBack();
        set_flash('error', 'Change Request tidak ditemukan, bukan milik Anda, atau sudah diajukan sebelumnya.');
        redirect(BASE_URL . '/user/pengajuan_saya.php');
    }

    $nomorCrf = generate_nomor_crf($pdo, $user['departemen'] ?? '');

    $stmt = $pdo->prepare("
        UPDATE crf_requests
        SET nomor_crf = ?, status = 'diajukan', submitted_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$nomorCrf, $crfId]);

    $stmt = $pdo->prepare("
        INSERT INTO crf_activity_logs (crf_id, actor_id, actor_role, activity_type, status_before, status_after, catatan)
        VALUES (?, ?, 'staff', 'submitted', 'draft', 'diajukan', ?)
    ");
    $stmt->execute([$crfId, $user['id'], "Pengajuan CRF dibuat oleh {$user['nama']}"]);

    // Notifikasi ke semua Admin (CAB)
    $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
    $notifStmt = $pdo->prepare("
        INSERT INTO notifications (user_id, crf_id, type, title, message)
        VALUES (?, ?, 'crf_baru', ?, ?)
    ");
    foreach ($admins as $admin) {
        $notifStmt->execute([
            $admin['id'],
            $crfId,
            "CRF Baru Masuk untuk Evaluasi [$nomorCrf]",
            "Pemohon: {$user['nama']} ({$user['departemen']}). Judul: {$crf['judul']}. Mohon segera dijadwalkan untuk review.",
        ]);
    }

    $pdo->commit();

    set_flash('success', "Change Request berhasil diajukan dengan nomor $nomorCrf.");
    redirect(BASE_URL . '/user/crf_detail.php?id=' . $crfId);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', 'Terjadi kesalahan saat mengajukan Change Request. Silakan coba lagi.');
    redirect(BASE_URL . '/user/crf_review.php?id=' . $crfId);
}