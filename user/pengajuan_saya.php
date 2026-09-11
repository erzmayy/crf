<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('staff');

$user = currentUser();

// --- Filter & Search ---
$q          = trim($_GET['q'] ?? '');
$statusFil  = $_GET['status'] ?? '';
$dateStart  = $_GET['date_start'] ?? '';
$dateEnd    = $_GET['date_end'] ?? '';
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 10;
$offset     = ($page - 1) * $perPage;

$where  = ['user_id = ?'];
$params = [$user['id']];

if ($q !== '') {
    $where[] = '(judul LIKE ? OR nomor_crf LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($statusFil !== '') {
    $where[] = 'status = ?';
    $params[] = $statusFil;
}
if ($dateStart !== '') {
    $where[] = 'DATE(created_at) >= ?';
    $params[] = $dateStart;
}
if ($dateEnd !== '') {
    $where[] = 'DATE(created_at) <= ?';
    $params[] = $dateEnd;
}

$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM crf_requests WHERE $whereSql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$listStmt = $pdo->prepare("
    SELECT id, nomor_crf, judul, status, prioritas, created_at
    FROM crf_requests
    WHERE $whereSql
    ORDER BY created_at DESC
    LIMIT $perPage OFFSET $offset
");
$listStmt->execute($params);
$rows = $listStmt->fetchAll();

$pageTitle  = 'Pengajuan Saya - CRF SIAP PPU';
$breadcrumb = 'Help Desk > Pengajuan Saya';
$activeMenu = 'pengajuan_saya';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">Pengajuan Saya - Change Request Form</h1>
<p class="page-subtitle">Daftar seluruh Change Request yang pernah Anda ajukan.</p>

<div class="card">
    <form method="GET" class="filter-bar">
        <input type="text" name="q" placeholder="Cari berdasarkan Judul / Nomor CRF..." value="<?= e($q) ?>">
        <select name="status">
            <option value="">Semua Status</option>
            <?php foreach (['draft','diajukan','dalam_pemeriksaan','perlu_revisi','disetujui','ditolak','dalam_proses','selesai','dibatalkan'] as $s): ?>
                <option value="<?= $s ?>" <?= $statusFil === $s ? 'selected' : '' ?>><?= status_label($s) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="date_start" value="<?= e($dateStart) ?>" title="Dari tanggal">
        <input type="date" name="date_end" value="<?= e($dateEnd) ?>" title="Sampai tanggal">
        <button type="submit" class="btn btn-outline btn-sm">Filter</button>
        <a href="<?= e(BASE_URL) ?>/user/crf_form.php" class="btn btn-primary btn-sm">+ Buat CRF Baru</a>
    </form>
</div>

<div class="card">
    <?php if (empty($rows)): ?>
        <p class="text-muted">Tidak ada pengajuan yang cocok dengan filter Anda.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nomor CRF</th>
                    <th>Judul Change Request</th>
                    <th>Tanggal Pengajuan</th>
                    <th>Prioritas</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $i => $r): ?>
                <tr>
                    <td><?= $offset + $i + 1 ?></td>
                    <td><?= e($r['nomor_crf'] ?? '(Draft)') ?></td>
                    <td><?= e($r['judul']) ?></td>
                    <td><?= format_tanggal($r['created_at']) ?></td>
                    <td><?= e(ucfirst($r['prioritas'])) ?></td>
                    <td><span class="badge <?= status_badge_class($r['status']) ?>"><?= status_label($r['status']) ?></span></td>
                    <td>
                        <a href="<?= e(BASE_URL) ?>/user/crf_detail.php?id=<?= (int)$r['id'] ?>">Detail</a>
                        <?php if ($r['status'] === 'draft'): ?>
                            &nbsp;|&nbsp;<a href="<?= e(BASE_URL) ?>/user/crf_form.php?id=<?= (int)$r['id'] ?>">Edit</a>
                        <?php elseif ($r['status'] === 'perlu_revisi'): ?>
                            &nbsp;|&nbsp;<a href="<?= e(BASE_URL) ?>/user/crf_revisi.php?id=<?= (int)$r['id'] ?>">Revisi</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php
                    $qs = $_GET;
                    for ($p = 1; $p <= $totalPages; $p++):
                        $qs['page'] = $p;
                ?>
                    <a href="?<?= http_build_query($qs) ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

        <p class="text-muted" style="margin-top:0.75rem; font-size:0.8rem;">
            Menampilkan <?= count($rows) ?> dari <?= $totalRows ?> entries
        </p>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>