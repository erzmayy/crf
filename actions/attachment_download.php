<?php
/**
 * Serve file lampiran CRF dengan validasi kepemilikan.
 * Staff hanya boleh download lampiran dari CRF miliknya sendiri.
 * Admin boleh download lampiran dari CRF siapa pun.
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user = currentUser();
$attachmentId = (int)($_GET['id'] ?? 0);

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

// Validasi hak akses di backend
if ($user['role'] === 'staff' && (int)$attachment['crf_owner_id'] !== (int)$user['id']) {
    http_response_code(403);
    die('Anda tidak memiliki akses ke file ini.');
}

$filePath = __DIR__ . '/../' . $attachment['file_path'];

if (!file_exists($filePath)) {
    http_response_code(404);
    die('File fisik tidak ditemukan di server.');
}

header('Content-Type: ' . ($attachment['mime_type'] ?: 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . basename($attachment['original_name']) . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;