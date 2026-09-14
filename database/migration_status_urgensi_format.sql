-- Backup database sebelum menjalankan migrasi ini.
CREATE TABLE crf_requests_backup_status_urgensi AS
SELECT * FROM crf_requests;

UPDATE crf_requests
SET status = CASE status
    WHEN 'dalam_pemeriksaan' THEN 'diajukan'
    WHEN 'dalam_proses' THEN 'disetujui'
    WHEN 'dibatalkan' THEN 'ditolak'
    ELSE status
END;

UPDATE crf_requests
SET prioritas = 'tinggi'
WHERE prioritas = 'kritis';

ALTER TABLE crf_requests
    MODIFY COLUMN nomor_crf VARCHAR(24) DEFAULT NULL UNIQUE,
    MODIFY COLUMN prioritas ENUM('rendah', 'sedang', 'tinggi') NOT NULL DEFAULT 'sedang',
    MODIFY COLUMN status ENUM(
        'draft',
        'diajukan',
        'perlu_revisi',
        'disetujui',
        'ditolak',
        'selesai'
    ) NOT NULL DEFAULT 'draft';
