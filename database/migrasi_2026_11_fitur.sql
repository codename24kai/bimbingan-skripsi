-- ============================================================
--  Migrasi fitur baru (pengumuman, template, diskusi, reset sandi,
--  slot dosen, agenda akademik, pengingat, seminar & sidang, nilai).
--  Jalankan SEKALI di phpMyAdmin (tab SQL) pada database
--  db_bimbingan_skripsi, setelah migrasi_2026_11_alur.sql.
--  Aman dijalankan ulang (memakai IF NOT EXISTS).
-- ============================================================

-- ── D6 Pengumuman ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `pengumuman` (
  `id_pengumuman` int(11) NOT NULL AUTO_INCREMENT,
  `judul` varchar(150) NOT NULL,
  `isi` text NOT NULL,
  `target_role` enum('semua','mahasiswa','dosen') NOT NULL DEFAULT 'semua',
  `id_prodi` int(11) DEFAULT NULL COMMENT 'NULL = semua prodi',
  `disematkan` tinyint(1) NOT NULL DEFAULT 0,
  `berlaku_sampai` date DEFAULT NULL COMMENT 'NULL = tidak kedaluwarsa',
  `dibuat_oleh` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_pengumuman`),
  KEY `idx_peng_aktif` (`berlaku_sampai`),
  CONSTRAINT `fk_peng_user` FOREIGN KEY (`dibuat_oleh`) REFERENCES `user` (`id_user`) ON DELETE CASCADE,
  CONSTRAINT `fk_peng_prodi` FOREIGN KEY (`id_prodi`) REFERENCES `program_studi` (`id_prodi`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pengumuman dari admin';

-- ── S2 Template dokumen (diunggah admin, diunduh semua) ─────
CREATE TABLE IF NOT EXISTS `template_dokumen` (
  `id_template` int(11) NOT NULL AUTO_INCREMENT,
  `nama` varchar(150) NOT NULL,
  `kategori` enum('proposal','bab','pengesahan','formulir','lainnya') NOT NULL DEFAULT 'lainnya',
  `keterangan` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `ukuran_kb` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_template`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Template dokumen skripsi';

-- ── D2 Diskusi per dokumen ──────────────────────────────────
CREATE TABLE IF NOT EXISTS `komentar_dokumen` (
  `id_komentar` int(11) NOT NULL AUTO_INCREMENT,
  `id_dokumen` int(11) NOT NULL,
  `id_revisi` int(11) DEFAULT NULL COMMENT 'Diisi jika menanggapi catatan revisi tertentu',
  `id_user` int(11) NOT NULL,
  `isi` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_komentar`),
  KEY `idx_kom_dok` (`id_dokumen`, `created_at`),
  CONSTRAINT `fk_kom_dok` FOREIGN KEY (`id_dokumen`) REFERENCES `dokumen` (`id_dokumen`) ON DELETE CASCADE,
  CONSTRAINT `fk_kom_rev` FOREIGN KEY (`id_revisi`) REFERENCES `revisi` (`id_revisi`) ON DELETE SET NULL,
  CONSTRAINT `fk_kom_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Diskusi mahasiswa-dosen per dokumen';

-- ── D3 Permintaan reset kata sandi (tanpa email) ────────────
CREATE TABLE IF NOT EXISTS `permintaan_reset` (
  `id_permintaan` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `status` enum('menunggu','selesai','ditolak') NOT NULL DEFAULT 'menunggu',
  `catatan` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `diproses_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id_permintaan`),
  KEY `idx_reset_status` (`status`),
  CONSTRAINT `fk_reset_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Permintaan reset kata sandi ke admin';

-- ── D4 Slot ketersediaan dosen ──────────────────────────────
CREATE TABLE IF NOT EXISTS `slot_dosen` (
  `id_slot` int(11) NOT NULL AUTO_INCREMENT,
  `id_dosen` int(11) NOT NULL,
  `tgl` date NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `kuota` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `metode` enum('tatap_muka','zoom','gmeet','lainnya') NOT NULL DEFAULT 'tatap_muka',
  `lokasi_link` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_slot`),
  KEY `idx_slot_dosen_tgl` (`id_dosen`, `tgl`),
  CONSTRAINT `fk_slot_dosen` FOREIGN KEY (`id_dosen`) REFERENCES `dosen` (`id_dosen`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Jam konsultasi yang dibuka dosen';

-- Sesi yang dipesan dari slot (kolom ditambahkan hanya jika belum ada)
SET @ada := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sesi_bimbingan' AND COLUMN_NAME = 'id_slot');
SET @sql := IF(@ada = 0,
  'ALTER TABLE `sesi_bimbingan` ADD `id_slot` int(11) DEFAULT NULL AFTER `id_bimbingan`, ADD KEY `idx_sesi_slot` (`id_slot`)',
  'SELECT 1');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ── S5 Agenda / kalender akademik ───────────────────────────
CREATE TABLE IF NOT EXISTS `agenda_akademik` (
  `id_agenda` int(11) NOT NULL AUTO_INCREMENT,
  `nama` varchar(150) NOT NULL,
  `tgl_mulai` date NOT NULL,
  `tgl_selesai` date DEFAULT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_agenda`),
  KEY `idx_agenda_tgl` (`tgl_mulai`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tanggal penting semester';

-- ── D10 Catatan pengingat yang sudah dikirim (anti dobel) ───
CREATE TABLE IF NOT EXISTS `pengingat_log` (
  `jenis` varchar(30) NOT NULL,
  `ref_id` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `periode` varchar(10) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`jenis`, `ref_id`, `id_user`, `periode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pengingat otomatis yang sudah terkirim';

-- ── D9 Seminar proposal & sidang akhir ──────────────────────
CREATE TABLE IF NOT EXISTS `ujian` (
  `id_ujian` int(11) NOT NULL AUTO_INCREMENT,
  `id_bimbingan` int(11) NOT NULL,
  `jenis` enum('seminar_proposal','sidang_akhir') NOT NULL,
  `status` enum('diajukan','disetujui_pembimbing','terjadwal','selesai','ditolak') NOT NULL DEFAULT 'diajukan',
  `file_berkas` varchar(255) DEFAULT NULL,
  `catatan_mhs` text DEFAULT NULL,
  `catatan_pembimbing` text DEFAULT NULL,
  `tgl_ujian` date DEFAULT NULL,
  `waktu_mulai` time DEFAULT NULL,
  `ruang` varchar(150) DEFAULT NULL,
  `hasil` enum('lulus','lulus_revisi','tidak_lulus') DEFAULT NULL,
  `nilai_akhir` decimal(5,2) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_ujian`),
  KEY `idx_ujian_bimb` (`id_bimbingan`),
  CONSTRAINT `fk_ujian_bimb` FOREIGN KEY (`id_bimbingan`) REFERENCES `bimbingan` (`id_bimbingan`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pendaftaran seminar proposal dan sidang';

CREATE TABLE IF NOT EXISTS `penguji_ujian` (
  `id_ujian` int(11) NOT NULL,
  `id_dosen` int(11) NOT NULL,
  `peran` enum('ketua','anggota') NOT NULL DEFAULT 'anggota',
  PRIMARY KEY (`id_ujian`, `id_dosen`),
  CONSTRAINT `fk_pu_ujian` FOREIGN KEY (`id_ujian`) REFERENCES `ujian` (`id_ujian`) ON DELETE CASCADE,
  CONSTRAINT `fk_pu_dosen` FOREIGN KEY (`id_dosen`) REFERENCES `dosen` (`id_dosen`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dosen penguji per ujian';

-- ── D16 Nilai rubrik (pembimbing & penguji) ─────────────────
CREATE TABLE IF NOT EXISTS `nilai_ujian` (
  `id_ujian` int(11) NOT NULL,
  `id_dosen` int(11) NOT NULL,
  `peran` enum('pembimbing','penguji') NOT NULL,
  `n_penulisan` tinyint(3) UNSIGNED NOT NULL,
  `n_presentasi` tinyint(3) UNSIGNED NOT NULL,
  `n_penguasaan` tinyint(3) UNSIGNED NOT NULL,
  `n_metodologi` tinyint(3) UNSIGNED NOT NULL,
  `catatan` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_ujian`, `id_dosen`),
  CONSTRAINT `fk_nu_ujian` FOREIGN KEY (`id_ujian`) REFERENCES `ujian` (`id_ujian`) ON DELETE CASCADE,
  CONSTRAINT `fk_nu_dosen` FOREIGN KEY (`id_dosen`) REFERENCES `dosen` (`id_dosen`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Nilai rubrik per dosen per ujian';

-- ── Pengaturan tambahan ─────────────────────────────────────
INSERT IGNORE INTO `pengaturan` (`nama_key`, `nilai_value`) VALUES
  ('kontak_prodi', 'Bagian Akademik Fakultas Ilmu Komputer, Gedung A Lt. 1 (Senin–Jumat, 08.00–15.00)'),
  ('batas_tidak_aktif_hari', '30'),
  ('pengingat_terakhir', '');
