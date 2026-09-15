<?php
/**
 * Kumpulan fungsi bantu umum yang dipakai di seluruh modul CRF.
 * Ini setara dengan helper Blade / global helper di Laravel.
 */

// Escape output untuk mencegah XSS (pengganti Blade auto-escaping {{ }})
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Redirect helper lalu hentikan eksekusi script
function redirect($path) {
    header('Location: ' . $path);
    exit;
}

// Flash message sederhana (pesan sekali tampil, disimpan di session)
function set_flash($type, $message) {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes() {
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

// --- CSRF Protection sederhana ---
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}

// Format tanggal ala Indonesia, tanpa library eksternal
function format_tanggal($datetime, $withTime = false) {
    if (!$datetime) return '-';
    $bulan = ['', 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $ts = is_numeric($datetime) ? (int)$datetime : strtotime($datetime);
    $hasil = date('d', $ts) . ' ' . $bulan[(int)date('n', $ts)] . ' ' . date('Y', $ts);
    if ($withTime) {
        $hasil .= ', ' . date('H:i', $ts);
    }
    return $hasil;
}

// "X menit/jam/hari yang lalu" - dipakai di halaman notifikasi (mirip diffForHumans Laravel)
function waktu_relatif($datetime) {
    $ts = is_numeric($datetime) ? (int)$datetime : strtotime($datetime);
    $diff = time() - $ts;

    if ($diff < 60)        return 'Baru saja';
    if ($diff < 3600)      return floor($diff / 60) . ' Menit yang lalu';
    if ($diff < 86400)     return floor($diff / 3600) . ' Jam yang lalu';
    if ($diff < 604800)    return floor($diff / 86400) . ' Hari yang lalu';
    if ($diff < 2592000)   return floor($diff / 604800) . ' Minggu yang lalu';
    return format_tanggal($ts);
}

// Pemetaan role -> nama folder halaman.
// Role 'staff' disimpan di folder 'user/', role 'admin' di folder 'admin/'.
// Dipakai supaya redirect setelah login selalu menuju folder yang benar.
function role_folder($role) {
    $map = [
        'staff' => 'user',
        'admin' => 'admin',
    ];
    return $map[$role] ?? $role;
}

// Label status CRF yang mudah dibaca (untuk badge di UI)
function status_label($status) {
    $labels = [
        'draft'              => 'Draft',
        'diajukan'           => 'Diajukan',
        'perlu_revisi'       => 'Perlu Revisi',
        'disetujui'          => 'Disetujui',
        'ditolak'            => 'Ditolak',
        'selesai'            => 'Selesai',
    ];
    return $labels[$status] ?? ucfirst($status);
}

// Kelas CSS badge sesuai status (didefinisikan di assets/css/style.css)
function status_badge_class($status) {
    $classes = [
        'draft'              => 'badge-draft',
        'diajukan'           => 'badge-diajukan',
        'perlu_revisi'       => 'badge-revisi',
        'disetujui'          => 'badge-disetujui',
        'ditolak'            => 'badge-ditolak',
        'selesai'            => 'badge-selesai',
    ];
    return $classes[$status] ?? 'badge-default';
}

// Ambil inisial nama untuk avatar bulat di topbar (mis. "Fikri Raihan" -> "FR")
function initials($name) {
    $parts = preg_split('/\s+/', trim($name ?? ''));
    if (count($parts) === 1) {
        return strtoupper(substr($parts[0], 0, 2));
    }
    return strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
}

// Dummy mapping departemen ke kode divisi CRF.
function crf_division_code($department) {
    $codes = [
        'Information Technology'      => '02.4',
        'IT Change Advisory Board'    => '02.4',
        'Human Resources'             => '01.1',
        'Finance'                     => '03.1',
        'Operations'                  => '04.1',
    ];

    return $codes[$department] ?? '99.9';
}

// Generate nomor CRF format PPU-[kode divisi].[nomor urut].[bulan].[tahun].
// HARUS dipanggil di dalam transaction ($pdo->beginTransaction()) supaya
// row-lock (FOR UPDATE) efektif mencegah duplikat saat diakses bersamaan.
function generate_nomor_crf(PDO $pdo, $department) {
    $divisionCode = crf_division_code($department);
    $month = date('m');
    $year = date('y');
    $prefix = "PPU-{$divisionCode}.";
    $periodSuffix = ".{$month}.{$year}";

    $stmt = $pdo->prepare("
        SELECT nomor_crf
        FROM crf_requests
        WHERE nomor_crf LIKE ?
        ORDER BY CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(nomor_crf, '.', 3), '.', -1) AS UNSIGNED) DESC
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([$prefix . '%' . $periodSuffix]);
    $last = $stmt->fetchColumn();

    $nextNumber = 1;
    if ($last) {
        $parts = explode('.', $last);
        $nextNumber = (int)($parts[2] ?? 0) + 1;
    }

    return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT) . $periodSuffix;
}

// Label human-readable untuk activity_type di crf_activity_logs (dipakai di Timeline)
function activity_label($type) {
    $labels = [
        'draft_created'        => 'Draft dibuat oleh pemohon',
        'draft_updated'        => 'Draft diperbarui oleh pemohon',
        'submitted'            => 'Pengajuan CRF diajukan oleh pemohon',
        'review_started'       => 'Admin mulai memeriksa pengajuan',
        'revision_requested'   => 'Admin meminta revisi',
        'revision_resubmitted' => 'Pemohon mengirim ulang revisi',
        'approved'             => 'Pengajuan disetujui oleh Admin',
        'rejected'             => 'Pengajuan ditolak oleh Admin',
        'status_updated'       => 'Status diperbarui oleh Admin',
        'completed'            => 'Change Request selesai diimplementasikan',
    ];
    return $labels[$type] ?? ucfirst(str_replace('_', ' ', $type));
}

// Daftar status berikutnya yang valid dari status saat ini, dipakai di
// halaman Update Status (Admin) untuk dropdown DAN validasi backend
// (satu sumber kebenaran, supaya keduanya selalu sinkron).
function crf_allowed_next_statuses($current) {
    $map = [
        'disetujui'    => ['selesai' => 'Selesai'],
    ];
    $options = $map[$current] ?? [];

    return $options;
}

function numbered_lines($text) {
    $lines = explode("\n", (string) $text);
    $lines = array_map('trim', $lines);
    $lines = array_filter($lines, function ($l) { return $l !== ''; });
    return array_values($lines);
}