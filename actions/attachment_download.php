<?php
/**
 * Redirect ke presigned URL Wasabi setelah validasi kepemilikan.
 * ?id=X&mode=view     -> buka file langsung di tab baru (inline)
 * ?id=X&mode=download -> paksa download (default)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/wasabi.php';
requireLogin();

$user = currentUser();
$attachmentId = (int)($_GET['id'] ?? 0);
$mode = ($_GET['mode'] ?? 'download') === 'view' ? 'view' : 'download';
$disposition = $mode === 'view' ? 'inline' : 'attachment';

$stmt = $pdo->prepare("
    SELECT a.*, c.user_id AS crf_owner_id
    FROM crf_attachments a
    JOIN crf_requests c ON c.id = a.crf_id
    WHERE a.id = ?
    LIMIT 1
");
$stmt->execute([$attachmentId]);
$attachment = $stmt->fetch();

if (!$attachment) {
    http_response_code(404);
    die('File tidak ditemukan.');
}

// Validasi hak akses di backend - staff hanya boleh akses lampiran miliknya sendiri
if ($user['role'] === 'staff' && (int)$attachment['crf_owner_id'] !== (int)$user['id']) {
    http_response_code(403);
    die('Anda tidak memiliki akses ke file ini.');
}

// file_path berisi object key di Wasabi
$presignedUrl = wasabi_get_presigned_url($attachment['file_path'], $attachment['original_name'], 5, $disposition);

if (!$presignedUrl) {
    http_response_code(500);
    die('Gagal membuat link. Silakan coba lagi.');
}

redirect($presignedUrl);