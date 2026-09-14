-- Jalankan hanya jika ingin membatalkan migrasi status/urgensi.
-- Pastikan tabel crf_requests_backup_status_urgensi masih ada.

ALTER TABLE crf_requests
    MODIFY COLUMN nomor_crf VARCHAR(20) DEFAULT NULL UNIQUE,
    MODIFY COLUMN prioritas ENUM('rendah', 'sedang', 'tinggi', 'kritis') NOT NULL DEFAULT 'sedang',
    MODIFY COLUMN status ENUM(
        'draft',
        'diajukan',
        'dalam_pemeriksaan',
        'perlu_revisi',
        'disetujui',
        'ditolak',
        'dalam_proses',
        'selesai',
        'dibatalkan'
    ) NOT NULL DEFAULT 'draft';

UPDATE crf_requests AS current_crf
JOIN crf_requests_backup_status_urgensi AS backup_crf
  ON backup_crf.id = current_crf.id
SET
    current_crf.status = backup_crf.status,
    current_crf.prioritas = backup_crf.prioritas;

-- Hapus tabel backup hanya setelah Anda memastikan data sudah kembali benar.
-- DROP TABLE crf_requests_backup_status_urgensi;
