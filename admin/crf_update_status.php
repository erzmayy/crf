<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$csrfToken = generate_csrf_token();
$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM crf_requests WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$crf = $stmt->fetch();

if (!$crf) {
    set_flash('error', 'Change Request tidak ditemukan.');
    redirect(BASE_URL . '/admin/crf_list.php');
}

$nextStatuses = crf_allowed_next_statuses($crf['status']);

if (empty($nextStatuses)) {
    set_flash('error', 'Tidak ada transisi status yang tersedia untuk status saat ini.');
    redirect(BASE_URL . '/admin/crf_detail.php?id=' . $id);
}

// Riwayat perubahan status (dari activity log, hanya yang benar-benar ganti status)
$stmt = $pdo->prepare("
    SELECT l.*, u.nama AS actor_nama
    FROM crf_activity_logs l
    JOIN users u ON u.id = l.actor_id
    WHERE l.crf_id = ?
      AND l.status_before IS NOT NULL
      AND l.status_before != l.status_after
    ORDER BY l.created_at DESC
");
$stmt->execute([$id]);
$statusHistory = $stmt->fetchAll();

$pageTitle  = 'Update Status - SIAP PPU';
$breadcrumb = 'Change Request Form > Update Status';
$activeMenu = 'crf_list';
require __DIR__ . '/../includes/header.php';
?>

<div style="display:flex; align-items:center; justify-content:space-between;">
    <h1 class="page-title">Update Status - <?= e($crf['nomor_crf']) ?></h1>
    <span class="badge <?= status_badge_class($crf['status']) ?>"><?= status_label($crf['status']) ?></span>
</div>
<p class="page-subtitle">Change Request Form &gt; Update Status</p>

<form method="POST" action="<?= e(BASE_URL) ?>/actions/crf_admin_update_status.php">
    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
    <input type="hidden" name="crf_id" value="<?= (int)$crf['id'] ?>">

    <div class="card">
        <h2 class="section-title">Formulir Update Progres / Status</h2>

        <div class="form-grid-2">
            <div class="form-group">
                <label>Ubah Status Ke *</label>
                <select name="new_status" required>
                    <option value="">-- Pilih Status --</option>
                    <?php foreach ($nextStatuses as $val => $label): ?>
                        <option value="<?= e($val) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Catatan Perkembangan / Progress Notes</label>
            <textarea name="catatan" rows="3" placeholder="Tuliskan progres terbaru mengenai Change Request ini..."></textarea>
        </div>
    </div>

    <div class="form-actions">
        <a href="<?= e(BASE_URL) ?>/admin/crf_detail.php?id=<?= (int)$crf['id'] ?>" class="btn btn-outline">Batal</a>
        <button type="submit" class="btn btn-primary">Update Status</button>
    </div>
</form>

<div class="card">
    <h2 class="section-title">Riwayat Perubahan Status</h2>
    <?php if (empty($statusHistory)): ?>
        <p class="text-muted">Belum ada riwayat perubahan status.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Waktu Perubahan</th>
                    <th>Status Lama</th>
                    <th>Status Baru</th>
                    <th>Diubah Oleh</th>
                    <th>Catatan / Progress</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($statusHistory as $h): ?>
                <tr>
                    <td><?= format_tanggal($h['created_at'], true) ?></td>
                    <td><?= e(status_label($h['status_before'])) ?></td>
                    <td><strong><?= e(status_label($h['status_after'])) ?></strong></td>
                    <td><?= e($h['actor_nama']) ?></td>
                    <td><?= e($h['catatan'] ?? '-') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>