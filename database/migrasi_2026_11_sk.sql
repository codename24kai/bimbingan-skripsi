-- ============================================================
--  Migrasi SK Bimbingan otomatis (nomor SK, kode verifikasi, PDF).
--  Jalankan SEKALI di phpMyAdmin (tab SQL) pada database
--  db_bimbingan_skripsi, setelah migrasi_2026_11_fitur.sql.
--  Aman dijalankan ulang (memakai IF NOT EXISTS / INSERT IGNORE).
--  Butuh MariaDB 10.0+ (bawaan XAMPP).
-- ============================================================

-- Penghitung nomor urut SK per tahun. Nomor diambil dengan
-- INSERT ... ON DUPLICATE KEY UPDATE di dalam transaksi, sehingga
-- dua penerbitan bersamaan tidak pernah mendapat nomor yang sama.
CREATE TABLE IF NOT EXISTS `sk_nomor` (
  `tahun` smallint(5) UNSIGNED NOT NULL,
  `terakhir` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Nomor urut terakhir yang terbit',
  PRIMARY KEY (`tahun`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Penghitung nomor SK bimbingan per tahun';

ALTER TABLE `bimbingan`
  ADD COLUMN IF NOT EXISTS `sk_tahun` smallint(5) UNSIGNED DEFAULT NULL COMMENT 'Tahun pada nomor SK' AFTER `file_sk`,
  ADD COLUMN IF NOT EXISTS `sk_urut` int(10) UNSIGNED DEFAULT NULL COMMENT 'Nomor urut SK dalam tahun tsb' AFTER `sk_tahun`,
  ADD COLUMN IF NOT EXISTS `tgl_sk` date DEFAULT NULL COMMENT 'Tanggal SK ditetapkan' AFTER `sk_urut`,
  ADD COLUMN IF NOT EXISTS `kode_verifikasi` varchar(20) DEFAULT NULL COMMENT 'Kode verifikasi keaslian SK' AFTER `tgl_sk`,
  ADD UNIQUE KEY IF NOT EXISTS `uq_bimbingan_sk_nomor` (`sk_tahun`, `sk_urut`),
  ADD UNIQUE KEY IF NOT EXISTS `uq_bimbingan_sk_kode` (`kode_verifikasi`);

-- Nomor SK lama yang sudah berformat UOB/SCDT/SK-BIMBINGAN/<tahun>/<urut>
-- ikut dicatat, supaya nomor otomatis melanjutkan dari angka terbesar.
UPDATE IGNORE `bimbingan`
   SET `sk_tahun` = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(`no_sk`, '/', -2), '/', 1) AS UNSIGNED),
       `sk_urut`  = CAST(SUBSTRING_INDEX(`no_sk`, '/', -1) AS UNSIGNED),
       `tgl_sk`   = COALESCE(`tgl_sk`, `tgl_mulai`)
 WHERE `sk_urut` IS NULL
   AND `no_sk` REGEXP '^UOB/SCDT/SK-BIMBINGAN/[0-9]{4}/[0-9]{1,9}$';

INSERT INTO `sk_nomor` (`tahun`, `terakhir`)
  SELECT `sk_tahun`, MAX(`sk_urut`) FROM `bimbingan` WHERE `sk_urut` IS NOT NULL GROUP BY `sk_tahun`
  ON DUPLICATE KEY UPDATE `terakhir` = GREATEST(`terakhir`, VALUES(`terakhir`));

-- Kop surat & penandatangan SK (bisa diubah di menu Pengaturan)
INSERT IGNORE INTO `pengaturan` (`nama_key`, `nilai_value`) VALUES
  ('sk_institusi', 'University of Birmingham'),
  ('sk_unit', 'School of Computing and Digital Technology'),
  ('sk_alamat', 'Edgbaston, Birmingham B15 2TT, United Kingdom'),
  ('sk_tempat', 'Birmingham, United Kingdom'),
  ('sk_penandatangan', 'Dr. Sarah J. Williams'),
  ('sk_jabatan_ttd', 'Dean of School of Computing and Digital Technology');
