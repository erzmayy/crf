-- =====================================================================
-- Database: crf_ppu
-- Modul Change Request Form (CRF) - PT Persona Prima Utama
-- Dibuat modular agar mudah diintegrasikan ke SIAP PPU
-- =====================================================================

CREATE DATABASE IF NOT EXISTS crf_ppu
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE crf_ppu;

-- ---------------------------------------------------------------------
-- 1. users
-- Tabel development untuk login sementara (staff & admin).
-- Nantinya akan digantikan oleh tabel user SIAP yang sesungguhnya.
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('staff','admin') NOT NULL DEFAULT 'staff',
    departemen VARCHAR(100) DEFAULT NULL,
    jabatan VARCHAR(100) DEFAULT NULL,
    nomor_hp VARCHAR(20) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. crf_requests
-- Tabel utama pengajuan Change Request Form.
-- ---------------------------------------------------------------------
CREATE TABLE crf_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nomor_crf VARCHAR(20) DEFAULT NULL UNIQUE,
    user_id INT UNSIGNED NOT NULL,
    helpdesk_ticket_id INT UNSIGNED DEFAULT NULL,

    -- Detail Change Request
    judul VARCHAR(200) NOT NULL,
    sistem_aplikasi VARCHAR(150) DEFAULT NULL,
    jenis_perubahan VARCHAR(100) DEFAULT NULL,
    deskripsi_perubahan TEXT,
    alasan_perubahan TEXT COMMENT 'Alasan Permohonan Perubahan (justifikasi bisnis)',
    kondisi_saat_ini TEXT,
    kondisi_diharapkan TEXT,
    benefit_perubahan TEXT COMMENT 'Benefit dari perubahan yang diharapkan (manfaat jika perubahan disetujui/dijalankan)',
    dampak_perubahan TEXT COMMENT 'Dampak jika perubahan TIDAK dilakukan (bukan level severity, diisi bebas oleh pemohon)',
    prioritas ENUM('rendah','sedang','tinggi','kritis') DEFAULT 'sedang',
    target_waktu DATE DEFAULT NULL,

    status ENUM(
        'draft',
        'diajukan',
        'dalam_pemeriksaan',
        'perlu_revisi',
        'disetujui',
        'ditolak',
        'dalam_proses',
        'selesai',
        'dibatalkan'
    ) NOT NULL DEFAULT 'draft',

    revision_count INT UNSIGNED NOT NULL DEFAULT 0,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    submitted_at TIMESTAMP NULL DEFAULT NULL,

    CONSTRAINT fk_crf_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    INDEX idx_crf_status (status),
    INDEX idx_crf_user (user_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. helpdesk_tickets_dummy
-- Simulasi form Helpdesk (sementara, sampai source SIAP tersedia).
-- ---------------------------------------------------------------------
CREATE TABLE helpdesk_tickets_dummy (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    nama_pemohon VARCHAR(100) NOT NULL,
    no_hp VARCHAR(20) NOT NULL,
    email VARCHAR(150) NOT NULL,

    jenis_permasalahan ENUM('IT','Non IT') NOT NULL,
    problem_masalah VARCHAR(150) NOT NULL,
    jenis_permintaan ENUM('indikasi_sebelum_permasalahan','change_request_form') NOT NULL,
    kategori_dampak ENUM('maintenance','request','komplain') DEFAULT NULL,

    jam_mulai_laporan TIME DEFAULT NULL,
    isi_pesan TEXT,
    dokumen_pendukung VARCHAR(255) DEFAULT NULL,

    crf_id INT UNSIGNED DEFAULT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_helpdesk_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_helpdesk_crf
        FOREIGN KEY (crf_id) REFERENCES crf_requests(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. crf_attachments
-- Lampiran pendukung CRF (bisa lebih dari satu, termasuk saat revisi).
-- ---------------------------------------------------------------------
CREATE TABLE crf_attachments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    crf_id INT UNSIGNED NOT NULL,
    uploaded_by INT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) DEFAULT NULL,
    size INT UNSIGNED DEFAULT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_attachment_crf
        FOREIGN KEY (crf_id) REFERENCES crf_requests(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_attachment_user
        FOREIGN KEY (uploaded_by) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    INDEX idx_attachment_crf (crf_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. crf_revisions
-- Riwayat permintaan & pengiriman ulang revisi.
-- ---------------------------------------------------------------------
CREATE TABLE crf_revisions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    crf_id INT UNSIGNED NOT NULL,
    revision_number INT UNSIGNED NOT NULL,
    catatan_revisi TEXT NOT NULL,
    bagian_perlu_direvisi VARCHAR(255) DEFAULT NULL,
    requested_by INT UNSIGNED NOT NULL,
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_snapshot_json JSON DEFAULT NULL,
    resubmitted_at TIMESTAMP NULL DEFAULT NULL,

    CONSTRAINT fk_revision_crf
        FOREIGN KEY (crf_id) REFERENCES crf_requests(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_revision_admin
        FOREIGN KEY (requested_by) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    INDEX idx_revision_crf (crf_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. crf_activity_logs
-- Gabungan Timeline + Activity Log + Riwayat Update Status.
-- ---------------------------------------------------------------------
CREATE TABLE crf_activity_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    crf_id INT UNSIGNED NOT NULL,
    actor_id INT UNSIGNED NOT NULL,
    actor_role ENUM('staff','admin') NOT NULL,
    activity_type VARCHAR(50) NOT NULL,
    status_before VARCHAR(30) DEFAULT NULL,
    status_after VARCHAR(30) DEFAULT NULL,
    catatan TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_log_crf
        FOREIGN KEY (crf_id) REFERENCES crf_requests(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_log_actor
        FOREIGN KEY (actor_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,

    INDEX idx_log_crf (crf_id),
    INDEX idx_log_created (created_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. notifications
-- Notifikasi untuk user & admin.
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    crf_id INT UNSIGNED DEFAULT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_notif_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_notif_crf
        FOREIGN KEY (crf_id) REFERENCES crf_requests(id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    INDEX idx_notif_user (user_id, is_read)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Tambahkan foreign key crf_requests -> helpdesk_tickets_dummy
-- (dibuat belakangan karena kedua tabel saling merujuk)
-- ---------------------------------------------------------------------
ALTER TABLE crf_requests
    ADD CONSTRAINT fk_crf_helpdesk
    FOREIGN KEY (helpdesk_ticket_id) REFERENCES helpdesk_tickets_dummy(id)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- ---------------------------------------------------------------------
-- Data dummy untuk testing (2 akun development)
-- Password untuk keduanya: "password123" (akan di-hash saat seeding via PHP)
-- ---------------------------------------------------------------------
-- INSERT INTO users (nama, email, password, role, departemen, jabatan, nomor_hp) VALUES
-- ('Fikri Raihan', 'staff@ppu.co.id', '$2y$10$PLACEHOLDER_HASH', 'staff', 'Information Technology', 'Staff Developer & Security', '083882126458'),
-- ('Admin CAB', 'admin@ppu.co.id', '$2y$10$PLACEHOLDER_HASH', 'admin', 'IT Change Advisory Board', 'Admin CAB', '081200000000');
