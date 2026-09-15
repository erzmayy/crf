<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

// --- Filter, Search, Sort, Pagination ---
$q          = trim($_GET['q'] ?? '');
$statusFil  = $_GET['status'] ?? '';
$deptFil    = $_GET['departemen'] ?? '';
$prioFil    = $_GET['prioritas'] ?? '';
$dateStart  = $_GET['date_start'] ?? '';
$dateEnd    = $_GET['date_end'] ?? '';
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 10;
$offset     = ($page - 1) * $perPage;

$sortableColumns = ['created_at', 'nomor_crf', 'prioritas', 'status'];
$sort = in_array($_GET['sort'] ?? '', $sortableColumns, true) ? $_GET['sort'] : 'created_at';
$dir  = ($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

$where  = ["c.status != 'draft'"];
$params = [];

if ($q !== '') {
    $where[] = '(c.judul LIKE ? OR c.nomor_crf LIKE ? OR u.nama LIKE ?)';
    $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%";
}
if ($statusFil !== '') {
    $where[] = 'c.status = ?';
    $params[] = $statusFil;
}
if ($deptFil !== '') {
    $where[] = 'u.departemen = ?';
    $params[] = $deptFil;
}
if ($prioFil !== '') {
    $where[] = 'c.prioritas = ?';
    $params[] = $prioFil;
}
if ($dateStart !== '') {
    $where[] = 'DATE(c.created_at) >= ?';
    $params[] = $dateStart;
}
if ($dateEnd !== '') {
    $where[] = 'DATE(c.created_at) <= ?';
    $params[] = $dateEnd;
}

$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("
    SELECT COUNT(*) FROM crf_requests c JOIN users u ON u.id = c.user_id WHERE $whereSql
");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$listStmt = $pdo->prepare("
    SELECT c.id, c.nomor_crf, c.judul, c.status, c.prioritas, c.created_at, u.nama AS nama_pemohon, u.departemen
    FROM crf_requests c
    JOIN users u ON u.id = c.user_id
    WHERE $whereSql
    ORDER BY c.$sort $dir
    LIMIT $perPage OFFSET $offset
");
$listStmt->execute($params);
$rows = $listStmt->fetchAll();

// Daftar departemen untuk filter dropdown
$departments = $pdo->query("SELECT DISTINCT departemen FROM users WHERE departemen IS NOT NULL AND departemen != '' ORDER BY departemen")->fetchAll(PDO::FETCH_COLUMN);

function sort_link($col, $label, $sort, $dir) {
    $newDir = ($sort === $col && $dir === 'asc') ? 'desc' : 'asc';
    $qs = $_GET;
    $qs['sort'] = $col;
    $qs['dir'] = $newDir;
    $arrow = $sort === $col ? ($dir === 'asc' ? ' &#9650;' : ' &#9660;') : '';
    return '<a href="?' . e(http_build_query($qs)) . '" style="color:inherit; text-decoration:none;">' . $label . $arrow . '</a>';
}

$pageTitle  = 'Daftar Change Request - SIAP PPU';
$breadcrumb = 'Help Desk > Semua Pengajuan';
$activeMenu = 'crf_list';
require __DIR__ . '/../includes/header.php';
?>

<h1 class="page-title">Daftar Pengajuan Change Request</h1>
<p class="page-subtitle">Evaluasi, verifikasi, dan tindak lanjuti usulan perubahan sistem SIAP PPU dari seluruh departemen.</p>

<div class="card">
    <form method="GET" class="filter-bar">
        <input type="text" name="q" placeholder="Cari berdasarkan Nama, No CRF, atau Judul..." value="<?= e($q) ?>">
        <select name="status">
            <option value="">Semua Status</option>
            <?php foreach (['diajukan','perlu_revisi','disetujui','ditolak','selesai'] as $s): ?>
                <option value="<?= $s ?>" <?= $statusFil === $s ? 'selected' : '' ?>><?= status_label($s) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="departemen">
            <option value="">Semua Departemen</option>
            <?php foreach ($departments as $d): ?>
                <option value="<?= e($d) ?>" <?= $deptFil === $d ? 'selected' : '' ?>><?= e($d) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="prioritas">
            <option value="">Tingkat Urgensi</option>
            <?php foreach (['rendah','sedang','tinggi'] as $p): ?>
                <option value="<?= $p ?>" <?= $prioFil === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="date_start" value="<?= e($dateStart) ?>" title="Dari tanggal">
        <input type="date" name="date_end" value="<?= e($dateEnd) ?>" title="Sampai tanggal">
        <button type="submit" class="btn btn-outline btn-sm">Filter</button>
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
                    <th><?= sort_link('nomor_crf', 'Nomor CRF', $sort, $dir) ?></th>
                    <th>Nama Pemohon</th>
                    <th>Departemen</th>
                    <th>Judul Change Request</th>
                    <th><?= sort_link('created_at', 'Tgl Pengajuan', $sort, $dir) ?></th>
                    <th><?= sort_link('prioritas', 'Prioritas', $sort, $dir) ?></th>
                    <th><?= sort_link('status', 'Status', $sort, $dir) ?></th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $i => $r): ?>
                <tr>
                    <td><?= $offset + $i + 1 ?></td>
                    <td><?= e($r['nomor_crf']) ?></td>
                    <td><?= e($r['nama_pemohon']) ?></td>
                    <td><?= e($r['departemen']) ?></td>
                    <td><?= e($r['judul']) ?></td>
                    <td><?= format_tanggal($r['created_at']) ?></td>
                    <td><?= e(ucfirst($r['prioritas'])) ?></td>
                    <td><span class="badge <?= status_badge_class($r['status']) ?>"><?= status_label($r['status']) ?></span></td>
                    <td>
                        <a href="<?= e(BASE_URL) ?>/admin/crf_detail.php?id=<?= (int)$r['id'] ?>">Detail</a>
                        &nbsp;|&nbsp;
                        <a href="<?= e(BASE_URL) ?>/admin/crf_print.php?id=<?= (int)$r['id'] ?>" target="_blank" title="Cetak / Export PDF">&#128438; Cetak</a>
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