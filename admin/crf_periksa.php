<?php
/**
 * Fungsionalitas "Pemeriksaan CRF" (approve/reject/catatan) sudah digabung
 * langsung ke admin/crf_detail.php supaya tidak ada duplikasi tampilan detail.
 * File ini disisakan sebagai alias supaya link lama/skeleton folder tetap jalan.
 */
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$id = (int)($_GET['id'] ?? 0);
redirect(BASE_URL . '/admin/crf_detail.php?id=' . $id);