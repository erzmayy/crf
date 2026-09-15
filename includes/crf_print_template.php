<?php
/**
 * Template cetak/export CRF (2 halaman, sesuai referensi desain).
 * Variabel yang HARUS sudah tersedia sebelum include file ini:
 *   $crf            - array data crf_requests + nama_pemohon, departemen, jabatan, nomor_hp, email_pemohon
 *   $changeActions  - array grouped by type: saran_alternatif, post_implementation_review, implementasi
 *
 * Ini halaman MANDIRI (tidak pakai header/sidebar aplikasi) supaya bersih saat di-print.
 */

$kategoriList = ['Aplikasi', 'Infrastruktur', 'Proses', 'Security', 'Lainnya'];
$kategoriTerpilih = '';
$kategoriDetail = '';
if (!empty($crf['sistem_aplikasi']) && strpos($crf['sistem_aplikasi'], ':') !== false) {
    [$kategoriTerpilih, $kategoriDetail] = array_map('trim', explode(':', $crf['sistem_aplikasi'], 2));
}

$prioritasLabel = ['rendah' => 'Rendah', 'sedang' => 'Sedang', 'tinggi' => 'Tinggi', 'kritis' => 'Kritis'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Change Request Form - <?= e($crf['nomor_crf'] ?? 'Draft') ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #111;
            margin: 0;
            padding: 20px;
            background: #e5e7eb;
        }
        .no-print { margin-bottom: 16px; }
        .no-print button {
            padding: 8px 16px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
        }
        .sheet {
            background: #fff;
            max-width: 800px;
            margin: 0 auto 24px;
            padding: 24px 28px;
            border: 1px solid #000;
        }
        .doc-title {
            text-align: center;
            font-weight: bold;
            font-size: 16px;
            text-decoration: underline;
            margin-bottom: 12px;
        }
        table.headbox {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 11.5px;
        }
        table.headbox td {
            border: 1px solid #000;
            padding: 4px 6px;
        }
        table.headbox td.label { width: 32%; font-weight: bold; background: #f3f4f6; }

        .section-heading {
            text-align: center;
            font-style: italic;
            font-weight: bold;
            font-size: 13px;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 4px 0;
            margin: 14px 0 10px;
        }

        table.fields {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        table.fields td {
            border: 1px solid #000;
            padding: 6px 8px;
            vertical-align: top;
        }
        table.fields td.num { width: 26px; font-weight: bold; text-align: center; }
        table.fields td.label { width: 130px; font-weight: bold; }
        table.fields td.desc { font-weight: normal; font-style: italic; font-size: 9.5px; color: #444; }
        table.fields ol, table.fields ul { margin: 0; padding-left: 16px; }
        table.fields li { margin-bottom: 2px; }
        .kategori-list { list-style: none; padding-left: 0; margin: 0; }
        .kategori-list li { margin-bottom: 2px; }
        .kategori-list li.checked { font-weight: bold; }

        .action-row { display: flex; border: 1px solid #000; margin-bottom: -1px; }
        .action-label { width: 30%; padding: 6px 8px; border-right: 1px solid #000; }
        .action-label strong { display: block; }
        .action-label span { font-size: 9.5px; font-style: italic; color: #444; }
        .action-body { flex: 1; padding: 6px 8px; }
        .action-body ol { margin: 0 0 4px; padding-left: 18px; }
        .action-date { font-size: 10px; color: #333; border-top: 1px dashed #999; margin-top: 4px; padding-top: 3px; }

        .sign-table { width: 100%; border-collapse: collapse; margin-top: 30px; font-size: 11px; }
        .sign-table td { width: 50%; text-align: center; padding: 4px; vertical-align: top; }
        .sign-box { height: 60px; }
        .sign-line { border-top: 1px solid #000; margin-top: 60px; padding-top: 4px; }

        @media print {
            body { background: #fff; padding: 0; }
            .no-print { display: none; }
            .sheet { border: none; margin: 0 auto; box-shadow: none; page-break-after: always; }
            .sheet:last-child { page-break-after: auto; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()">&#128438; Print / Save as PDF</button>
</div>

<!-- ============ HALAMAN 1 ============ -->
<div class="sheet">
    <div class="doc-title">CHANGE REQUEST FORM</div>

    <table class="headbox">
        <tr>
            <td class="label">Nomor CRF</td><td><?= e($crf['nomor_crf'] ?? '(Draft)') ?></td>
            <td class="label">Hari / Tanggal</td><td><?= format_tanggal($crf['submitted_at'] ?? $crf['created_at']) ?></td>
        </tr>
        <tr>
            <td class="label">Departemen / Unit Bisnis</td><td><?= e($crf['departemen'] ?? '-') ?></td>
            <td class="label">Nama Pemohon</td><td><?= e($crf['nama_pemohon'] ?? '-') ?></td>
        </tr>
        <tr>
            <td class="label">Jabatan Pemohon</td><td colspan="3"><?= e($crf['jabatan'] ?? '-') ?></td>
        </tr>
    </table>

    <div class="section-heading">Change Request Description</div>

    <table class="fields">
        <tr>
            <td class="num">1</td>
            <td class="label">Kategori Perubahan:<div class="desc">(silahkan sampaikan Kategori perubahan yang Saudara sampaikan)</div></td>
            <td>
                <ul class="kategori-list">
                    <?php foreach ($kategoriList as $k): ?>
                        <li class="<?= $kategoriTerpilih === $k ? 'checked' : '' ?>">
                            [<?= $kategoriTerpilih === $k ? 'X' : ' ' ?>] <?= e($k) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($kategoriDetail): ?>
                    <div style="margin-top:4px;"><em>Detail:</em> <?= e($kategoriDetail) ?></div>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td class="num">2</td>
            <td class="label">Deskripsi Perubahan:<div class="desc">(uraikan perubahan yang diminta)</div></td>
            <td>
                <ol>
                    <?php foreach (numbered_lines($crf['deskripsi_perubahan']) as $line): ?>
                        <li><?= e($line) ?></li>
                    <?php endforeach; ?>
                </ol>
            </td>
        </tr>
        <tr>
            <td class="num">3</td>
            <td class="label">Benefit dari perubahan yang diharapkan:</td>
            <td>
                <ol>
                    <?php foreach (numbered_lines($crf['benefit_perubahan']) as $line): ?>
                        <li><?= e($line) ?></li>
                    <?php endforeach; ?>
                </ol>
            </td>
        </tr>
        <tr>
            <td class="num">4</td>
            <td class="label">Dampak dari Tidak Dilakukan:</td>
            <td>
                <ol>
                    <?php foreach (numbered_lines($crf['dampak_perubahan']) as $line): ?>
                        <li><?= e($line) ?></li>
                    <?php endforeach; ?>
                </ol>
            </td>
        </tr>
        <tr>
            <td class="num">5</td>
            <td class="label">Alasan Permohonan Perubahan:</td>
            <td>
                <ol>
                    <?php foreach (numbered_lines($crf['alasan_perubahan']) as $line): ?>
                        <li><?= e($line) ?></li>
                    <?php endforeach; ?>
                </ol>
            </td>
        </tr>
        <tr>
            <td class="num">6</td>
            <td class="label">Aset / Sumber Pendukung:<div class="desc">(anggaran/pendanaan pendukung)</div></td>
            <td>
                <?php $asetLines = numbered_lines($crf['aset_sumber_pendukung'] ?? ''); ?>
                <?php if (empty($asetLines)): ?>
                    <span style="color:#999;">-</span>
                <?php else: ?>
                    <ol>
                        <?php foreach ($asetLines as $line): ?>
                            <li><?= e($line) ?></li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </td>
        </tr>
    </table>
</div>

<!-- ============ HALAMAN 2 ============ -->
<div class="sheet">
    <div class="section-heading">Change Request Action</div>
    <p style="text-align:center; font-size:10px; font-style:italic; margin-top:-6px;">
        (silahkan sampaikan saran alternatif dan tindak lanjut atas permintaan perubahan pada kolom tersebut di bawah)
    </p>

    <?php
        $actionSections = [
            'saran_alternatif' => ['title' => 'Saran Alternatif:', 'desc' => '(saran alternatif yang akan dilakukan atas perubahan yang telah disampaikan)'],
            'post_implementation_review' => ['title' => 'Post Implementation Review:', 'desc' => '(proses evaluasi yang dilakukan setelah perubahan diterapkan)'],
            'implementasi' => ['title' => 'Implementasi:', 'desc' => '(pelaksanaan yang telah dilakukan atas perubahan yang telah disampaikan)'],
        ];
    ?>

    <?php foreach ($actionSections as $type => $info): ?>
        <div class="action-row">
            <div class="action-label">
                <strong><?= e($info['title']) ?></strong>
                <span><?= e($info['desc']) ?></span>
            </div>
            <div class="action-body">
                <?php if (empty($changeActions[$type])): ?>
                    <span style="color:#999;">Belum ada catatan.</span>
                <?php else: ?>
                    <?php foreach ($changeActions[$type] as $entry): ?>
                        <ol>
                            <?php foreach (numbered_lines($entry['items']) as $line): ?>
                                <li><?= e($line) ?></li>
                            <?php endforeach; ?>
                        </ol>
                        <div class="action-date">Tanggal: <?= $entry['tanggal'] ? format_tanggal($entry['tanggal']) : '-' ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <table class="sign-table">
        <tr>
            <td>
                <div class="sign-box"></div>
                <div class="sign-line">
                    <strong><?= e($crf['nama_pemohon'] ?? '-') ?></strong><br>
                    Yang Mengajukan<br>
                    <?= e($crf['departemen'] ?? '-') ?>
                </div>
            </td>
            <td>
                <div class="sign-box"></div>
                <div class="sign-line">
                    <strong>&nbsp;</strong><br>
                    Diketahui oleh (Admin / CAB)<br>
                    &nbsp;
                </div>
            </td>
        </tr>
    </table>
</div>

</body>
</html>