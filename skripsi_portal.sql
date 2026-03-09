-- ============================================================
--  PORTAL BIMBINGAN SKRIPSI — Database Schema
--  MySQL 8.0+
--  Dibuat  : 2025
--  Versi   : 1.0.0
-- ============================================================

-- Buat & gunakan database
CREATE DATABASE IF NOT EXISTS db_bimbingan_skripsi
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE db_bimbingan_skripsi;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. PROGRAM_STUDI
-- ============================================================
CREATE TABLE IF NOT EXISTS program_studi (
    id_prodi        INT             NOT NULL AUTO_INCREMENT,
    kode_prodi      VARCHAR(10)     NOT NULL,
    nama_prodi      VARCHAR(100)    NOT NULL,
    jenjang         ENUM('D3','S1','S2','S3') NOT NULL DEFAULT 'S1',
    fakultas        VARCHAR(100)    NOT NULL,
    akreditasi      CHAR(1)         NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id_prodi),
    UNIQUE KEY uq_kode_prodi (kode_prodi)
) ENGINE=InnoDB COMMENT='Referensi program studi yang tersedia';


-- ============================================================
-- 2. USER  (tabel autentikasi induk)
-- ============================================================
CREATE TABLE IF NOT EXISTS user (
    id_user         INT             NOT NULL AUTO_INCREMENT,
    nama_lengkap    VARCHAR(100)    NOT NULL,
    email           VARCHAR(100)    NOT NULL,
    password_hash   VARCHAR(255)    NOT NULL,
    role            ENUM('mahasiswa','dosen','admin') NOT NULL,
    status          ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
    foto_profil     VARCHAR(255)    NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,
    last_login      DATETIME        NULL,

    PRIMARY KEY (id_user),
    UNIQUE KEY uq_email (email),
    INDEX idx_role  (role),
    INDEX idx_status (status)
) ENGINE=InnoDB COMMENT='Tabel induk autentikasi semua pengguna';


-- ============================================================
-- 3. ADMIN
-- ============================================================
CREATE TABLE IF NOT EXISTS admin (
    id_admin        INT             NOT NULL AUTO_INCREMENT,
    id_user         INT             NOT NULL,
    level_akses     ENUM('super','regular') NOT NULL DEFAULT 'regular',
    departemen      VARCHAR(100)    NULL,

    PRIMARY KEY (id_admin),
    UNIQUE KEY uq_user_admin (id_user),
    CONSTRAINT fk_admin_user
        FOREIGN KEY (id_user) REFERENCES user (id_user)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='Data administrator sistem';


-- ============================================================
-- 4. MAHASISWA
-- ============================================================
CREATE TABLE IF NOT EXISTS mahasiswa (
    id_mahasiswa    INT             NOT NULL AUTO_INCREMENT,
    id_user         INT             NOT NULL,
    id_prodi        INT             NOT NULL,
    nim             VARCHAR(20)     NOT NULL,
    angkatan        YEAR            NOT NULL,
    ipk             DECIMAL(3,2)    NULL,
    status_skripsi  ENUM('belum','proses','selesai') NOT NULL DEFAULT 'belum',
    no_telepon      VARCHAR(15)     NULL,

    PRIMARY KEY (id_mahasiswa),
    UNIQUE KEY uq_nim (nim),
    UNIQUE KEY uq_user_mahasiswa (id_user),
    INDEX idx_prodi_mhs (id_prodi),
    INDEX idx_angkatan  (angkatan),
    CONSTRAINT fk_mhs_user
        FOREIGN KEY (id_user)   REFERENCES user (id_user)
        ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT fk_mhs_prodi
        FOREIGN KEY (id_prodi)  REFERENCES program_studi (id_prodi)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='Data profil mahasiswa';


-- ============================================================
-- 5. DOSEN
-- ============================================================
CREATE TABLE IF NOT EXISTS dosen (
    id_dosen            INT             NOT NULL AUTO_INCREMENT,
    id_user             INT             NOT NULL,
    id_prodi            INT             NOT NULL,
    nip                 VARCHAR(20)     NOT NULL,
    jabatan_fungsional  VARCHAR(50)     NULL,
    keahlian            TEXT            NULL,
    kuota_bimbingan     TINYINT UNSIGNED NOT NULL DEFAULT 10,
    terisi              TINYINT UNSIGNED NOT NULL DEFAULT 0,

    PRIMARY KEY (id_dosen),
    UNIQUE KEY uq_nip       (nip),
    UNIQUE KEY uq_user_dosen (id_user),
    INDEX idx_prodi_dsn (id_prodi),
    CONSTRAINT fk_dsn_user
        FOREIGN KEY (id_user)  REFERENCES user (id_user)
        ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT fk_dsn_prodi
        FOREIGN KEY (id_prodi) REFERENCES program_studi (id_prodi)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_kuota
        CHECK (terisi <= kuota_bimbingan)
) ENGINE=InnoDB COMMENT='Data dosen pembimbing';


-- ============================================================
-- 6. JUDUL_SKRIPSI
-- ============================================================
CREATE TABLE IF NOT EXISTS judul_skripsi (
    id_judul            INT             NOT NULL AUTO_INCREMENT,
    id_mahasiswa        INT             NOT NULL,
    judul_indonesia     TEXT            NOT NULL,
    judul_inggris       TEXT            NULL,
    bidang_penelitian   VARCHAR(100)    NOT NULL,
    latar_belakang      TEXT            NULL,
    metode_penelitian   VARCHAR(100)    NULL,
    iterasi             TINYINT UNSIGNED NOT NULL DEFAULT 1,
    status              ENUM('draft','diajukan','revisi','disetujui','ditolak')
                                        NOT NULL DEFAULT 'draft',
    tgl_pengajuan       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    file_proposal       VARCHAR(255)    NULL,

    PRIMARY KEY (id_judul),
    INDEX idx_judul_mhs    (id_mahasiswa),
    INDEX idx_judul_status (status),
    CONSTRAINT fk_judul_mhs
        FOREIGN KEY (id_mahasiswa) REFERENCES mahasiswa (id_mahasiswa)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='Riwayat pengajuan judul skripsi';


-- ============================================================
-- 7. BIMBINGAN  (relasi aktif mahasiswa–dosen)
-- ============================================================
CREATE TABLE IF NOT EXISTS bimbingan (
    id_bimbingan    INT             NOT NULL AUTO_INCREMENT,
    id_mahasiswa    INT             NOT NULL,
    id_dosen        INT             NOT NULL,
    id_judul        INT             NOT NULL,
    semester        VARCHAR(20)     NOT NULL,
    tgl_mulai       DATE            NOT NULL,
    tgl_selesai     DATE            NULL,
    total_sesi      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status          ENUM('aktif','selesai','dihentikan') NOT NULL DEFAULT 'aktif',

    PRIMARY KEY (id_bimbingan),
    INDEX idx_bimb_mhs   (id_mahasiswa),
    INDEX idx_bimb_dosen (id_dosen),
    INDEX idx_bimb_judul (id_judul),
    INDEX idx_bimb_status (status),
    CONSTRAINT fk_bimb_mhs
        FOREIGN KEY (id_mahasiswa) REFERENCES mahasiswa (id_mahasiswa)
        ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT fk_bimb_dosen
        FOREIGN KEY (id_dosen)     REFERENCES dosen (id_dosen)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_bimb_judul
        FOREIGN KEY (id_judul)     REFERENCES judul_skripsi (id_judul)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='Relasi aktif bimbingan mahasiswa–dosen';


-- ============================================================
-- 8. SESI_BIMBINGAN
-- ============================================================
CREATE TABLE IF NOT EXISTS sesi_bimbingan (
    id_sesi         INT             NOT NULL AUTO_INCREMENT,
    id_bimbingan    INT             NOT NULL,
    tgl_sesi        DATE            NOT NULL,
    waktu_mulai     TIME            NOT NULL,
    durasi_menit    SMALLINT UNSIGNED NULL,
    topik           TEXT            NOT NULL,
    metode          ENUM('tatap_muka','zoom','gmeet','lainnya')
                                    NOT NULL DEFAULT 'tatap_muka',
    lokasi_link     VARCHAR(255)    NULL,
    status          ENUM('dijadwalkan','selesai','batal')
                                    NOT NULL DEFAULT 'dijadwalkan',

    PRIMARY KEY (id_sesi),
    INDEX idx_sesi_bimb   (id_bimbingan),
    INDEX idx_sesi_tgl    (tgl_sesi),
    INDEX idx_sesi_status (status),
    CONSTRAINT fk_sesi_bimb
        FOREIGN KEY (id_bimbingan) REFERENCES bimbingan (id_bimbingan)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='Setiap pertemuan sesi bimbingan';


-- ============================================================
-- 9. CATATAN_BIMBINGAN
-- ============================================================
CREATE TABLE IF NOT EXISTS catatan_bimbingan (
    id_catatan      INT             NOT NULL AUTO_INCREMENT,
    id_sesi         INT             NOT NULL,
    isi_catatan     TEXT            NOT NULL,
    keputusan       ENUM('disetujui','revisi','ditolak')
                                    NOT NULL,
    file_lampiran   VARCHAR(255)    NULL,
    bab_terkait     VARCHAR(50)     NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id_catatan),
    INDEX idx_cat_sesi    (id_sesi),
    INDEX idx_cat_keputusan (keputusan),
    CONSTRAINT fk_cat_sesi
        FOREIGN KEY (id_sesi) REFERENCES sesi_bimbingan (id_sesi)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='Catatan dosen setelah sesi bimbingan';


-- ============================================================
-- 10. DOKUMEN
-- ============================================================
CREATE TABLE IF NOT EXISTS dokumen (
    id_dokumen      INT             NOT NULL AUTO_INCREMENT,
    id_mahasiswa    INT             NOT NULL,
    nama_dokumen    VARCHAR(150)    NOT NULL,
    jenis_bab       VARCHAR(30)     NOT NULL,
    versi           TINYINT UNSIGNED NOT NULL DEFAULT 1,
    file_path       VARCHAR(255)    NOT NULL,
    ukuran_kb       INT UNSIGNED    NOT NULL,
    format_file     VARCHAR(10)     NOT NULL DEFAULT 'pdf',
    status          ENUM('menunggu','direvisi','disetujui')
                                    NOT NULL DEFAULT 'menunggu',
    tgl_upload      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id_dokumen),
    INDEX idx_dok_mhs    (id_mahasiswa),
    INDEX idx_dok_status (status),
    CONSTRAINT fk_dok_mhs
        FOREIGN KEY (id_mahasiswa) REFERENCES mahasiswa (id_mahasiswa)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='Dokumen skripsi yang diunggah mahasiswa';


-- ============================================================
-- 11. REVISI
-- ============================================================
CREATE TABLE IF NOT EXISTS revisi (
    id_revisi       INT             NOT NULL AUTO_INCREMENT,
    id_dokumen      INT             NOT NULL,
    id_dosen        INT             NOT NULL,
    catatan_revisi  TEXT            NOT NULL,
    halaman_revisi  VARCHAR(100)    NULL,
    prioritas       ENUM('minor','mayor','kritis')
                                    NOT NULL DEFAULT 'minor',
    status          ENUM('belum','sudah') NOT NULL DEFAULT 'belum',
    tgl_revisi      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id_revisi),
    INDEX idx_rev_dok   (id_dokumen),
    INDEX idx_rev_dosen (id_dosen),
    INDEX idx_rev_status (status),
    CONSTRAINT fk_rev_dok
        FOREIGN KEY (id_dokumen) REFERENCES dokumen (id_dokumen)
        ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT fk_rev_dosen
        FOREIGN KEY (id_dosen)   REFERENCES dosen (id_dosen)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='Catatan revisi dosen untuk dokumen mahasiswa';


-- ============================================================
-- 12. NOTIFIKASI
-- ============================================================
CREATE TABLE IF NOT EXISTS notifikasi (
    id_notif        INT             NOT NULL AUTO_INCREMENT,
    id_user         INT             NOT NULL,
    judul           VARCHAR(150)    NOT NULL,
    pesan           TEXT            NOT NULL,
    tipe            ENUM('info','sukses','peringatan','error')
                                    NOT NULL DEFAULT 'info',
    modul           VARCHAR(50)     NULL,
    is_read         BOOLEAN         NOT NULL DEFAULT FALSE,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id_notif),
    INDEX idx_notif_user   (id_user),
    INDEX idx_notif_read   (is_read),
    INDEX idx_notif_tipe   (tipe),
    CONSTRAINT fk_notif_user
        FOREIGN KEY (id_user) REFERENCES user (id_user)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB COMMENT='Log notifikasi untuk semua pengguna';


SET FOREIGN_KEY_CHECKS = 1;


-- ============================================================
--  TRIGGER — otomatis update total_sesi & kuota dosen
-- ============================================================

DELIMITER $$

-- Update total_sesi saat sesi selesai
CREATE TRIGGER trg_sesi_selesai
AFTER UPDATE ON sesi_bimbingan
FOR EACH ROW
BEGIN
    IF NEW.status = 'selesai' AND OLD.status != 'selesai' THEN
        UPDATE bimbingan
        SET total_sesi = total_sesi + 1
        WHERE id_bimbingan = NEW.id_bimbingan;
    END IF;
END$$

-- Tambah slot terisi dosen saat bimbingan aktif dibuat
CREATE TRIGGER trg_bimbingan_insert
AFTER INSERT ON bimbingan
FOR EACH ROW
BEGIN
    IF NEW.status = 'aktif' THEN
        UPDATE dosen SET terisi = terisi + 1
        WHERE id_dosen = NEW.id_dosen;
    END IF;
END$$

-- Kurangi slot terisi dosen saat bimbingan selesai/dihentikan
CREATE TRIGGER trg_bimbingan_update
AFTER UPDATE ON bimbingan
FOR EACH ROW
BEGIN
    IF OLD.status = 'aktif'
       AND NEW.status IN ('selesai','dihentikan') THEN
        UPDATE dosen SET terisi = GREATEST(terisi - 1, 0)
        WHERE id_dosen = NEW.id_dosen;
    END IF;
END$$

DELIMITER ;


-- ============================================================
--  DATA AWAL (Seed)
-- ============================================================

-- Program Studi
INSERT INTO program_studi (kode_prodi, nama_prodi, jenjang, fakultas, akreditasi) VALUES
('TIF',  'Teknik Informatika',       'S1', 'Fakultas Ilmu Komputer', 'A'),
('SIF',  'Sistem Informasi',         'S1', 'Fakultas Ilmu Komputer', 'A'),
('MNI',  'Manajemen Informatika',    'D3', 'Fakultas Ilmu Komputer', 'B'),
('TKM',  'Teknik Komputer',          'S1', 'Fakultas Teknik',        'B');

-- User
INSERT INTO user (nama_lengkap, email, password_hash, role, status) VALUES
-- Admin
('Admin Akademik',          'admin@univ.ac.id',              SHA2('admin123',256),  'admin',     'aktif'),
-- Dosen
('Dr. Sari Indah, M.Kom',   'sari.indah@univ.ac.id',         SHA2('dosen123',256),  'dosen',     'aktif'),
('Prof. Budi Raharjo, Ph.D','budi.raharjo@univ.ac.id',       SHA2('dosen123',256),  'dosen',     'aktif'),
('Dr. Anto Wijaya, M.T',    'anto.wijaya@univ.ac.id',        SHA2('dosen123',256),  'dosen',     'aktif'),
('Dr. Maya Lestari, M.Kom', 'maya.lestari@univ.ac.id',       SHA2('dosen123',256),  'dosen',     'aktif'),
-- Mahasiswa
('Ahmad Fauzi',             'ahmad.fauzi@mhs.univ.ac.id',    SHA2('mhs123',256),    'mahasiswa', 'aktif'),
('Rina Marlina',            'rina.marlina@mhs.univ.ac.id',   SHA2('mhs123',256),    'mahasiswa', 'aktif'),
('Budi Santoso',            'budi.santoso@mhs.univ.ac.id',   SHA2('mhs123',256),    'mahasiswa', 'aktif'),
('Citra Dewi',              'citra.dewi@mhs.univ.ac.id',     SHA2('mhs123',256),    'mahasiswa', 'aktif'),
('Deni Kurniawan',          'deni.kurniawan@mhs.univ.ac.id', SHA2('mhs123',256),    'mahasiswa', 'aktif');

-- Admin
INSERT INTO admin (id_user, level_akses, departemen) VALUES
(1, 'super', 'Bagian Akademik');

-- Dosen
INSERT INTO dosen (id_user, id_prodi, nip, jabatan_fungsional, keahlian, kuota_bimbingan) VALUES
(2, 1, '198512001', 'Lektor Kepala',   'AI, Machine Learning, Data Science', 10),
(3, 1, '196712002', 'Guru Besar',      'Jaringan Komputer, Keamanan Siber',   8),
(4, 2, '197812003', 'Lektor',          'Rekayasa Perangkat Lunak, UML',      10),
(5, 2, '198912004', 'Asisten Ahli',    'Sistem Informasi, ERP, SAP',          8);

-- Mahasiswa
INSERT INTO mahasiswa (id_user, id_prodi, nim, angkatan, ipk, status_skripsi, no_telepon) VALUES
(6,  1, '21TIF001', 2021, 3.75, 'proses',  '081234567890'),
(7,  1, '21TIF002', 2021, 3.82, 'proses',  '081234567891'),
(8,  2, '21SIF001', 2021, 3.50, 'proses',  '081234567892'),
(9,  3, '21MNI001', 2021, 3.20, 'proses',  '081234567893'),
(10, 4, '21TKM001', 2021, 3.00, 'belum',   '081234567894');

-- Judul Skripsi
INSERT INTO judul_skripsi
    (id_mahasiswa, judul_indonesia, judul_inggris, bidang_penelitian, metode_penelitian, iterasi, status, file_proposal)
VALUES
(1, 'Implementasi Machine Learning untuk Deteksi Spam Email Berbasis Naive Bayes',
    'Machine Learning Implementation for Email Spam Detection Based on Naive Bayes',
    'Kecerdasan Buatan', 'Eksperimen', 3, 'disetujui', 'proposal/mhs1_v3.pdf'),
(2, 'Sistem Rekomendasi Film Menggunakan Collaborative Filtering',
    'Movie Recommendation System Using Collaborative Filtering',
    'Sistem Cerdas', 'Penelitian Terapan', 2, 'disetujui', 'proposal/mhs2_v2.pdf'),
(3, 'Analisis Sentimen Media Sosial Terhadap Produk UMKM Menggunakan SVM',
    'Social Media Sentiment Analysis on MSME Products Using SVM',
    'Kecerdasan Buatan', 'Eksperimen', 1, 'diajukan', 'proposal/mhs3_v1.pdf'),
(4, 'Perancangan Sistem Informasi Manajemen Klinik Berbasis Web',
    'Design of Web-based Clinic Management Information System',
    'Sistem Informasi', 'Prototype', 1, 'revisi', 'proposal/mhs4_v1.pdf');

-- Bimbingan
INSERT INTO bimbingan (id_mahasiswa, id_dosen, id_judul, semester, tgl_mulai, total_sesi, status) VALUES
(1, 1, 1, 'Genap 2024/2025', '2025-01-06', 8, 'aktif'),
(2, 1, 2, 'Genap 2024/2025', '2025-01-06', 6, 'aktif'),
(3, 3, 3, 'Genap 2024/2025', '2025-01-13', 3, 'aktif'),
(4, 4, 4, 'Genap 2024/2025', '2025-01-13', 2, 'aktif');

-- Sesi Bimbingan
INSERT INTO sesi_bimbingan (id_bimbingan, tgl_sesi, waktu_mulai, durasi_menit, topik, metode, status) VALUES
(1, '2025-02-28', '10:00:00', 45, 'Review Bab II dan Metodologi', 'tatap_muka', 'selesai'),
(1, '2025-03-12', '10:00:00', 60, 'Pembahasan Bab III',           'tatap_muka', 'dijadwalkan'),
(2, '2025-02-26', '13:00:00', 60, 'Review Bab II',                'zoom',       'selesai'),
(3, '2025-03-05', '09:00:00', 45, 'Konsultasi Metodologi SVM',    'gmeet',      'selesai');

-- Catatan Bimbingan
INSERT INTO catatan_bimbingan (id_sesi, isi_catatan, keputusan, bab_terkait) VALUES
(1, 'Tinjauan pustaka sudah cukup komprehensif. Perbaiki format sitasi menjadi APA style dan tambahkan minimal 3 referensi jurnal internasional terbaru.', 'revisi',    'BAB II'),
(3, 'Tinjauan pustaka perlu diperkuat dengan referensi jurnal internasional 5 tahun terakhir. Kerangka teoritis masih kurang kohesif.',                     'revisi',    'BAB II'),
(4, 'Metode SVM yang dipilih sudah tepat untuk analisis sentimen. Lanjutkan ke tahap pengumpulan dataset dari Twitter API.',                               'disetujui', 'BAB III');

-- Dokumen
INSERT INTO dokumen (id_mahasiswa, nama_dokumen, jenis_bab, versi, file_path, ukuran_kb, format_file, status) VALUES
(1, 'BAB I - Pendahuluan.pdf',          'BAB I',    3, 'docs/mhs1/bab1_v3.pdf', 512,  'pdf', 'disetujui'),
(1, 'BAB II - Tinjauan Pustaka.pdf',    'BAB II',   2, 'docs/mhs1/bab2_v2.pdf', 1024, 'pdf', 'direvisi'),
(1, 'BAB III - Metodologi.pdf',         'BAB III',  1, 'docs/mhs1/bab3_v1.pdf', 768,  'pdf', 'menunggu'),
(2, 'BAB I - Pendahuluan.pdf',          'BAB I',    2, 'docs/mhs2/bab1_v2.pdf', 480,  'pdf', 'disetujui'),
(2, 'BAB IV - Hasil Penelitian.pdf',    'BAB IV',   1, 'docs/mhs2/bab4_v1.pdf', 2048, 'pdf', 'menunggu');

-- Revisi
INSERT INTO revisi (id_dokumen, id_dosen, catatan_revisi, halaman_revisi, prioritas, status) VALUES
(2, 1, 'Sitasi pada halaman 12-15 tidak konsisten. Gunakan format APA 7th edition secara menyeluruh.', 'Hal. 12-15', 'mayor', 'belum'),
(2, 1, 'Tambahkan 3 referensi jurnal Scopus atau WoS yang terbit setelah 2020.',                       'Hal. 18',    'minor', 'belum');

-- Notifikasi
INSERT INTO notifikasi (id_user, judul, pesan, tipe, modul, is_read) VALUES
(6, 'Judul Skripsi Disetujui',    'Selamat! Judul skripsi Anda telah disetujui oleh Dr. Sari Indah, M.Kom.',                               'sukses',    'judul',    FALSE),
(6, 'Catatan Revisi Baru',        'Ada catatan revisi baru pada dokumen BAB II dari dosen pembimbing. Silakan tinjau segera.',              'peringatan','dokumen',  FALSE),
(7, 'Dokumen BAB IV Diterima',    'Dokumen BAB IV Anda telah diterima dan sedang ditinjau oleh dosen pembimbing.',                          'info',      'dokumen',  TRUE),
(2, 'Permintaan Bimbingan Baru',  'Ahmad Fauzi mengajukan jadwal bimbingan untuk Rabu, 12 Maret 2025 pukul 10.00.',                         'info',      'jadwal',   FALSE),
(1, 'Verifikasi Akun Pending',    '5 akun mahasiswa baru memerlukan verifikasi dari administrator.',                                        'peringatan','manajemen',FALSE);


-- ============================================================
--  QUERY OPERASIONAL — CRUD & REPORTING
-- ============================================================

-- ── A. MAHASISWA ────────────────────────────────────────────

-- A1. Daftar semua mahasiswa beserta nama dosen dan progres bimbingan
SELECT
    m.nim,
    u.nama_lengkap                          AS nama_mahasiswa,
    ps.nama_prodi,
    ud.nama_lengkap                         AS nama_dosen,
    j.judul_indonesia                       AS judul_skripsi,
    j.status                                AS status_judul,
    b.total_sesi,
    m.status_skripsi
FROM mahasiswa m
JOIN user        u   ON u.id_user      = m.id_user
JOIN program_studi ps ON ps.id_prodi   = m.id_prodi
LEFT JOIN bimbingan b ON b.id_mahasiswa = m.id_mahasiswa AND b.status = 'aktif'
LEFT JOIN dosen     d ON d.id_dosen     = b.id_dosen
LEFT JOIN user      ud ON ud.id_user    = d.id_user
LEFT JOIN judul_skripsi j ON j.id_judul = b.id_judul
ORDER BY m.nim;

-- A2. Riwayat bimbingan lengkap satu mahasiswa
SELECT
    sb.tgl_sesi,
    sb.waktu_mulai,
    sb.durasi_menit,
    sb.topik,
    sb.metode,
    sb.status                               AS status_sesi,
    cb.isi_catatan,
    cb.keputusan,
    cb.bab_terkait
FROM sesi_bimbingan sb
JOIN bimbingan     b  ON b.id_bimbingan  = sb.id_bimbingan
LEFT JOIN catatan_bimbingan cb ON cb.id_sesi = sb.id_sesi
WHERE b.id_mahasiswa = 1
ORDER BY sb.tgl_sesi DESC;

-- A3. Dokumen & status revisi milik mahasiswa tertentu
SELECT
    d.nama_dokumen,
    d.jenis_bab,
    d.versi,
    d.status                                AS status_dokumen,
    d.tgl_upload,
    COUNT(r.id_revisi)                      AS jumlah_revisi,
    SUM(r.status = 'belum')                 AS revisi_pending
FROM dokumen d
LEFT JOIN revisi r ON r.id_dokumen = d.id_dokumen
WHERE d.id_mahasiswa = 1
GROUP BY d.id_dokumen
ORDER BY d.jenis_bab, d.versi DESC;

-- A4. Notifikasi belum dibaca milik user
SELECT judul, pesan, tipe, modul, created_at
FROM notifikasi
WHERE id_user = 6 AND is_read = FALSE
ORDER BY created_at DESC;

-- A5. Update status notifikasi menjadi sudah dibaca
UPDATE notifikasi
SET is_read = TRUE
WHERE id_user = 6;

-- ── B. DOSEN ────────────────────────────────────────────────

-- B1. Daftar mahasiswa bimbingan dosen beserta progres
SELECT
    m.nim,
    um.nama_lengkap                         AS nama_mahasiswa,
    j.judul_indonesia,
    b.total_sesi,
    b.tgl_mulai,
    b.status                                AS status_bimbingan,
    m.status_skripsi
FROM bimbingan b
JOIN mahasiswa   m  ON m.id_mahasiswa  = b.id_mahasiswa
JOIN user        um ON um.id_user      = m.id_user
JOIN judul_skripsi j ON j.id_judul    = b.id_judul
WHERE b.id_dosen = 1
ORDER BY b.status, m.nim;

-- B2. Judul skripsi yang menunggu validasi dosen
SELECT
    j.id_judul,
    m.nim,
    um.nama_lengkap                         AS nama_mahasiswa,
    j.judul_indonesia,
    j.bidang_penelitian,
    j.iterasi,
    j.tgl_pengajuan
FROM judul_skripsi j
JOIN mahasiswa   m  ON m.id_mahasiswa  = j.id_mahasiswa
JOIN user        um ON um.id_user      = m.id_user
JOIN bimbingan   b  ON b.id_mahasiswa  = m.id_mahasiswa AND b.id_dosen = 1
WHERE j.status = 'diajukan'
ORDER BY j.tgl_pengajuan ASC;

-- B3. Setujui judul skripsi
UPDATE judul_skripsi
SET status = 'disetujui'
WHERE id_judul = 3;

-- B4. Minta revisi judul
UPDATE judul_skripsi
SET status   = 'revisi',
    iterasi  = iterasi + 1
WHERE id_judul = 4;

-- B5. Tambah catatan bimbingan setelah sesi selesai
INSERT INTO catatan_bimbingan (id_sesi, isi_catatan, keputusan, bab_terkait)
VALUES (2, 'Metodologi sudah baik, lanjutkan ke pengumpulan data primer.', 'disetujui', 'BAB III');

-- B6. Sesi terjadwal minggu ini untuk dosen
SELECT
    sb.tgl_sesi,
    sb.waktu_mulai,
    sb.durasi_menit,
    sb.topik,
    sb.metode,
    um.nama_lengkap                         AS nama_mahasiswa,
    m.nim
FROM sesi_bimbingan sb
JOIN bimbingan   b  ON b.id_bimbingan  = sb.id_bimbingan
JOIN mahasiswa   m  ON m.id_mahasiswa  = b.id_mahasiswa
JOIN user        um ON um.id_user      = m.id_user
WHERE b.id_dosen    = 1
  AND sb.status     = 'dijadwalkan'
  AND sb.tgl_sesi BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
ORDER BY sb.tgl_sesi, sb.waktu_mulai;

-- B7. Tambah revisi pada dokumen
INSERT INTO revisi (id_dokumen, id_dosen, catatan_revisi, halaman_revisi, prioritas)
VALUES (3, 1, 'Tambahkan flowchart sistem pada bagian 3.2.', 'Hal. 24', 'minor');

-- ── C. ADMIN ────────────────────────────────────────────────

-- C1. Ringkasan statistik per program studi
SELECT
    ps.nama_prodi,
    ps.jenjang,
    COUNT(DISTINCT m.id_mahasiswa)          AS total_mahasiswa,
    COUNT(DISTINCT d.id_dosen)              AS total_dosen,
    SUM(m.status_skripsi = 'proses')        AS skripsi_aktif,
    SUM(m.status_skripsi = 'selesai')       AS skripsi_selesai,
    SUM(m.status_skripsi = 'belum')         AS belum_mulai
FROM program_studi ps
LEFT JOIN mahasiswa m  ON m.id_prodi  = ps.id_prodi
LEFT JOIN dosen     d  ON d.id_prodi  = ps.id_prodi
GROUP BY ps.id_prodi
ORDER BY ps.nama_prodi;

-- C2. Semua pengguna dengan info role
SELECT
    u.id_user,
    u.nama_lengkap,
    u.email,
    u.role,
    u.status,
    u.created_at,
    u.last_login,
    CASE u.role
        WHEN 'mahasiswa' THEN m.nim
        WHEN 'dosen'     THEN d.nip
        ELSE             'N/A'
    END                                     AS nomor_identitas
FROM user u
LEFT JOIN mahasiswa m ON m.id_user = u.id_user
LEFT JOIN dosen     d ON d.id_user = u.id_user
ORDER BY u.role, u.nama_lengkap;

-- C3. Buat relasi bimbingan baru
INSERT INTO bimbingan (id_mahasiswa, id_dosen, id_judul, semester, tgl_mulai, status)
VALUES (5, 2, 1, 'Genap 2024/2025', CURDATE(), 'aktif');

-- C4. Hentikan relasi bimbingan
UPDATE bimbingan
SET status      = 'dihentikan',
    tgl_selesai = CURDATE()
WHERE id_bimbingan = 4;

-- C5. Mahasiswa tanpa pembimbing aktif
SELECT m.nim, u.nama_lengkap, ps.nama_prodi
FROM mahasiswa m
JOIN user          u  ON u.id_user   = m.id_user
JOIN program_studi ps ON ps.id_prodi = m.id_prodi
WHERE NOT EXISTS (
    SELECT 1 FROM bimbingan b
    WHERE b.id_mahasiswa = m.id_mahasiswa
      AND b.status = 'aktif'
);

-- C6. Dosen dengan kuota tersisa
SELECT
    u.nama_lengkap                          AS nama_dosen,
    d.nip,
    ps.nama_prodi,
    d.kuota_bimbingan,
    d.terisi,
    (d.kuota_bimbingan - d.terisi)          AS sisa_kuota
FROM dosen d
JOIN user          u  ON u.id_user   = d.id_user
JOIN program_studi ps ON ps.id_prodi = d.id_prodi
ORDER BY sisa_kuota DESC;

-- C7. Log aktivitas (notifikasi semua user) hari ini
SELECT
    n.created_at,
    u.nama_lengkap,
    u.role,
    n.judul,
    n.tipe,
    n.modul
FROM notifikasi n
JOIN user u ON u.id_user = n.id_user
WHERE DATE(n.created_at) = CURDATE()
ORDER BY n.created_at DESC;

-- C8. Kirim notifikasi ke semua mahasiswa aktif
INSERT INTO notifikasi (id_user, judul, pesan, tipe, modul)
SELECT
    u.id_user,
    'Pengingat Progres Skripsi',
    'Harap segera perbarui dokumen skripsi Anda dan jadwalkan bimbingan berikutnya.',
    'peringatan',
    'sistem'
FROM mahasiswa m
JOIN user u ON u.id_user = m.id_user
WHERE m.status_skripsi = 'proses';


-- ── D. REPORTING ────────────────────────────────────────────

-- D1. Laporan rekapitulasi bimbingan per dosen (bulan ini)
SELECT
    ud.nama_lengkap                         AS nama_dosen,
    COUNT(DISTINCT b.id_mahasiswa)          AS jml_mahasiswa,
    COUNT(sb.id_sesi)                       AS total_sesi,
    SUM(sb.status = 'selesai')              AS sesi_selesai,
    SUM(sb.status = 'dijadwalkan')          AS sesi_terjadwal,
    ROUND(AVG(sb.durasi_menit), 0)          AS avg_durasi_menit
FROM dosen d
JOIN user             ud ON ud.id_user       = d.id_user
LEFT JOIN bimbingan   b  ON b.id_dosen       = d.id_dosen
LEFT JOIN sesi_bimbingan sb ON sb.id_bimbingan = b.id_bimbingan
                           AND MONTH(sb.tgl_sesi) = MONTH(CURDATE())
                           AND YEAR(sb.tgl_sesi)  = YEAR(CURDATE())
GROUP BY d.id_dosen
ORDER BY total_sesi DESC;

-- D2. Distribusi status judul skripsi
SELECT
    status,
    COUNT(*) AS jumlah,
    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM judul_skripsi), 2) AS persen
FROM judul_skripsi
GROUP BY status;

-- D3. Mahasiswa dengan revisi pending terbanyak
SELECT
    m.nim,
    u.nama_lengkap,
    COUNT(r.id_revisi)                      AS total_revisi_pending
FROM revisi r
JOIN dokumen   d ON d.id_dokumen   = r.id_dokumen
JOIN mahasiswa m ON m.id_mahasiswa = d.id_mahasiswa
JOIN user      u ON u.id_user      = m.id_user
WHERE r.status = 'belum'
GROUP BY m.id_mahasiswa
ORDER BY total_revisi_pending DESC;

-- D4. Rata-rata sesi bimbingan sampai selesai per prodi
SELECT
    ps.nama_prodi,
    ROUND(AVG(b.total_sesi), 1)             AS avg_sesi
FROM bimbingan b
JOIN mahasiswa     m  ON m.id_mahasiswa = b.id_mahasiswa
JOIN program_studi ps ON ps.id_prodi    = m.id_prodi
WHERE b.status = 'selesai'
GROUP BY ps.id_prodi;

-- D5. Full-text search judul skripsi
SELECT
    m.nim,
    u.nama_lengkap,
    j.judul_indonesia,
    j.bidang_penelitian,
    j.status
FROM judul_skripsi j
JOIN mahasiswa m ON m.id_mahasiswa = j.id_mahasiswa
JOIN user      u ON u.id_user      = m.id_user
WHERE j.judul_indonesia LIKE '%Machine Learning%'
   OR j.judul_indonesia LIKE '%Sentiment%'
ORDER BY j.tgl_pengajuan DESC;
