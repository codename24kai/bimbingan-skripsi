-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 01 Jun 2026 pada 20.30
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_bimbingan_skripsi`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `admin`
--

CREATE TABLE `admin` (
  `id_admin` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `level_akses` enum('super','regular') NOT NULL DEFAULT 'regular',
  `departemen` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Data administrator sistem';

--
-- Dumping data untuk tabel `admin`
--

INSERT INTO `admin` (`id_admin`, `id_user`, `level_akses`, `departemen`) VALUES
(1, 1, 'super', 'Bagian Akademik');

-- --------------------------------------------------------

--
-- Struktur dari tabel `bimbingan`
--

CREATE TABLE `bimbingan` (
  `id_bimbingan` int(11) NOT NULL,
  `id_mahasiswa` int(11) NOT NULL,
  `id_dosen` int(11) NOT NULL,
  `id_judul` int(11) NOT NULL,
  `no_sk` varchar(100) DEFAULT NULL,
  `file_sk` varchar(255) DEFAULT NULL,
  `semester` varchar(20) NOT NULL,
  `tgl_mulai` date NOT NULL,
  `tgl_selesai` date DEFAULT NULL,
  `total_sesi` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `status` enum('aktif','selesai','dihentikan') NOT NULL DEFAULT 'aktif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Relasi aktif bimbingan mahasiswa–dosen';

--
-- Trigger `bimbingan`
--
DELIMITER $$
CREATE TRIGGER `trg_bimbingan_insert` AFTER INSERT ON `bimbingan` FOR EACH ROW BEGIN
    IF NEW.status = 'aktif' THEN
        UPDATE dosen SET terisi = terisi + 1
        WHERE id_dosen = NEW.id_dosen;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_bimbingan_update` AFTER UPDATE ON `bimbingan` FOR EACH ROW BEGIN
    IF OLD.status = 'aktif'
       AND NEW.status IN ('selesai','dihentikan') THEN
        UPDATE dosen SET terisi = GREATEST(terisi - 1, 0)
        WHERE id_dosen = NEW.id_dosen;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `catatan_bimbingan`
--

CREATE TABLE `catatan_bimbingan` (
  `id_catatan` int(11) NOT NULL,
  `id_sesi` int(11) NOT NULL,
  `isi_catatan` text NOT NULL,
  `keputusan` enum('disetujui','revisi','ditolak') NOT NULL,
  `file_lampiran` varchar(255) DEFAULT NULL,
  `bab_terkait` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catatan dosen setelah sesi bimbingan';

-- --------------------------------------------------------

--
-- Struktur dari tabel `dokumen`
--

CREATE TABLE `dokumen` (
  `id_dokumen` int(11) NOT NULL,
  `id_mahasiswa` int(11) NOT NULL,
  `nama_dokumen` varchar(150) NOT NULL,
  `jenis_bab` varchar(30) NOT NULL,
  `versi` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `file_path` varchar(255) NOT NULL,
  `ukuran_kb` int(10) UNSIGNED NOT NULL,
  `format_file` varchar(10) NOT NULL DEFAULT 'pdf',
  `status` enum('menunggu','direvisi','disetujui') NOT NULL DEFAULT 'menunggu',
  `tgl_upload` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dokumen skripsi yang diunggah mahasiswa';

-- --------------------------------------------------------

--
-- Struktur dari tabel `dosen`
--

CREATE TABLE `dosen` (
  `id_dosen` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `id_prodi` int(11) NOT NULL,
  `nip` varchar(20) NOT NULL,
  `jabatan_fungsional` enum('Tenaga Pengajar','Asisten Ahli','Lektor','Lektor Kepala','Guru Besar') DEFAULT NULL COMMENT 'Jabatan Fungsional Akademik Dosen',
  `keahlian` enum('Rekayasa Perangkat Lunak & Web','Pemrograman Mobile','Kecerdasan Buatan & Machine Learning','Data Science & Analitik','Internet of Things (IoT)','Keamanan Siber & Jaringan','Manajemen Sistem Informasi') DEFAULT NULL COMMENT 'Bidang Keahlian Dosen Ilkom',
  `kuota_bimbingan` tinyint(3) UNSIGNED NOT NULL DEFAULT 10,
  `terisi` tinyint(3) UNSIGNED NOT NULL DEFAULT 0
) ;

--
-- Dumping data untuk tabel `dosen`
--

INSERT INTO `dosen` (`id_dosen`, `id_user`, `id_prodi`, `nip`, `jabatan_fungsional`, `keahlian`, `kuota_bimbingan`, `terisi`) VALUES
(1, 2, 1, '198512001', 'Lektor Kepala', '', 10, 2),
(2, 3, 1, '196712002', 'Guru Besar', '', 8, 1),
(3, 4, 2, '197812003', 'Lektor', '', 10, 3);

-- --------------------------------------------------------

--
-- Struktur dari tabel `judul_skripsi`
--

CREATE TABLE `judul_skripsi` (
  `id_judul` int(11) NOT NULL,
  `id_mahasiswa` int(11) NOT NULL,
  `judul_indonesia` text NOT NULL,
  `judul_alternatif_1` text DEFAULT NULL,
  `judul_alternatif_2` text DEFAULT NULL,
  `bidang_penelitian` varchar(100) NOT NULL,
  `latar_belakang` text DEFAULT NULL,
  `metode_penelitian` enum('Agile Software Development','Waterfall Model','RAD (Rapid Application Development)','Prototyping','CRISP-DM (Data Science)','Riset Eksperimental','Kuantitatif','Kualitatif','Lainnya') DEFAULT NULL COMMENT 'Metode pengembangan aplikasi atau penelitian',
  `iterasi` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `status` enum('draft','diajukan','revisi','disetujui','ditolak') NOT NULL DEFAULT 'draft',
  `tgl_pengajuan` datetime NOT NULL DEFAULT current_timestamp(),
  `file_proposal` varchar(255) DEFAULT NULL,
  `file_transkrip` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Riwayat pengajuan judul skripsi';

-- --------------------------------------------------------

--
-- Struktur dari tabel `mahasiswa`
--

CREATE TABLE `mahasiswa` (
  `id_mahasiswa` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `id_prodi` int(11) NOT NULL,
  `nim` varchar(20) NOT NULL,
  `angkatan` year(4) NOT NULL,
  `ipk` decimal(3,2) DEFAULT NULL,
  `status_skripsi` enum('belum','proses','selesai') NOT NULL DEFAULT 'belum',
  `no_telepon` varchar(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Data profil mahasiswa';

--
-- Dumping data untuk tabel `mahasiswa`
--

INSERT INTO `mahasiswa` (`id_mahasiswa`, `id_user`, `id_prodi`, `nim`, `angkatan`, `ipk`, `status_skripsi`, `no_telepon`) VALUES
(8, 15, 1, '231011403449', '2023', NULL, 'belum', NULL),
(9, 16, 1, '231011403460', '2023', NULL, 'belum', NULL),
(10, 17, 1, '231011403426', '2023', NULL, 'belum', NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `notifikasi`
--

CREATE TABLE `notifikasi` (
  `id_notif` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `judul` varchar(150) NOT NULL,
  `pesan` text NOT NULL,
  `tipe` enum('info','sukses','peringatan','error') NOT NULL DEFAULT 'info',
  `modul` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log notifikasi untuk semua pengguna';

--
-- Dumping data untuk tabel `notifikasi`
--

INSERT INTO `notifikasi` (`id_notif`, `id_user`, `judul`, `pesan`, `tipe`, `modul`, `is_read`, `created_at`) VALUES
(4, 2, 'Permintaan Bimbingan Baru', 'Ahmad Fauzi mengajukan jadwal bimbingan untuk Rabu, 12 Maret 2025 pukul 10.00.', 'info', 'jadwal', 0, '2026-03-10 05:41:38'),
(5, 1, 'Verifikasi Akun Pending', '5 akun mahasiswa baru memerlukan verifikasi dari administrator.', 'peringatan', 'manajemen', 1, '2026-03-10 05:41:38'),
(13, 2, 'Permintaan Jadwal Bimbingan', 'Mahasiswa mengajukan sesi bimbingan pada 12 Mar 2026 pukul 10:00', 'info', 'jadwal', 0, '2026-03-10 06:49:56'),
(14, 4, 'Pengajuan Judul Baru', 'Mahasiswa mengajukan judul baru untuk ditinjau: Skibidi Fortnite', 'info', 'judul', 0, '2026-04-21 00:07:09'),
(16, 4, 'Pengajuan Judul Baru', 'Mahasiswa mengajukan judul baru untuk ditinjau: Skibidi Fortnite', 'info', 'judul', 0, '2026-04-21 00:43:35'),
(18, 4, 'Permintaan Jadwal Bimbingan', 'Mahasiswa mengajukan sesi bimbingan pada 27 Apr 2026 pukul 11:00', 'info', 'jadwal', 0, '2026-04-21 00:46:11'),
(23, 4, 'Pengajuan Judul Baru', 'Mahasiswa mengajukan judul baru untuk ditinjau: Penelitian Eceng Gondok Pada hutan hujan di daerah kali ciliwung', 'info', 'judul', 0, '2026-04-29 13:44:00');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengaturan`
--

CREATE TABLE `pengaturan` (
  `nama_key` varchar(50) NOT NULL,
  `nilai_value` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel konfigurasi umum sistem';

--
-- Dumping data untuk tabel `pengaturan`
--

INSERT INTO `pengaturan` (`nama_key`, `nilai_value`) VALUES
('default_kuota_dosen', '10'),
('nama_institusi', 'University of Birmingham'),
('semester_aktif', 'Genap'),
('tahun_akademik', '2025/2026');

-- --------------------------------------------------------

--
-- Struktur dari tabel `program_studi`
--

CREATE TABLE `program_studi` (
  `id_prodi` int(11) NOT NULL,
  `kode_prodi` varchar(10) NOT NULL,
  `nama_prodi` varchar(100) NOT NULL,
  `jenjang` enum('D3','S1','S2','S3') NOT NULL DEFAULT 'S1',
  `fakultas` varchar(100) NOT NULL,
  `akreditasi` char(1) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Referensi program studi yang tersedia';

--
-- Dumping data untuk tabel `program_studi`
--

INSERT INTO `program_studi` (`id_prodi`, `kode_prodi`, `nama_prodi`, `jenjang`, `fakultas`, `akreditasi`, `created_at`, `updated_at`) VALUES
(1, 'TIF', 'Teknik Informatika', 'S1', 'Fakultas Ilmu Komputer', 'A', '2026-03-09 22:41:38', '2026-03-09 22:41:38'),
(2, 'SIF', 'Sistem Informasi', 'S1', 'Fakultas Ilmu Komputer', 'A', '2026-03-09 22:41:38', '2026-03-09 22:41:38');

-- --------------------------------------------------------

--
-- Struktur dari tabel `revisi`
--

CREATE TABLE `revisi` (
  `id_revisi` int(11) NOT NULL,
  `id_dokumen` int(11) NOT NULL,
  `id_dosen` int(11) NOT NULL,
  `catatan_revisi` text NOT NULL,
  `halaman_revisi` varchar(100) DEFAULT NULL,
  `prioritas` enum('minor','mayor','kritis') NOT NULL DEFAULT 'minor',
  `status` enum('belum','sudah') NOT NULL DEFAULT 'belum',
  `tgl_revisi` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catatan revisi dosen untuk dokumen mahasiswa';

-- --------------------------------------------------------

--
-- Struktur dari tabel `sesi_bimbingan`
--

CREATE TABLE `sesi_bimbingan` (
  `id_sesi` int(11) NOT NULL,
  `id_bimbingan` int(11) NOT NULL,
  `tgl_sesi` date NOT NULL,
  `waktu_mulai` time NOT NULL,
  `durasi_menit` smallint(5) UNSIGNED DEFAULT NULL,
  `topik` text NOT NULL,
  `metode` enum('tatap_muka','zoom','gmeet','lainnya') NOT NULL DEFAULT 'tatap_muka',
  `lokasi_link` varchar(255) DEFAULT NULL,
  `status` enum('dijadwalkan','selesai','batal') NOT NULL DEFAULT 'dijadwalkan'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Setiap pertemuan sesi bimbingan';

--
-- Trigger `sesi_bimbingan`
--
DELIMITER $$
CREATE TRIGGER `trg_sesi_selesai` AFTER UPDATE ON `sesi_bimbingan` FOR EACH ROW BEGIN
    IF NEW.status = 'selesai' AND OLD.status != 'selesai' THEN
        UPDATE bimbingan
        SET total_sesi = total_sesi + 1
        WHERE id_bimbingan = NEW.id_bimbingan;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `user`
--

CREATE TABLE `user` (
  `id_user` int(11) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('mahasiswa','dosen','admin') NOT NULL,
  `status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel induk autentikasi semua pengguna';

--
-- Dumping data untuk tabel `user`
--

INSERT INTO `user` (`id_user`, `nama_lengkap`, `email`, `password_hash`, `role`, `status`, `created_at`, `updated_at`, `last_login`) VALUES
(1, 'Admin Akademik', 'admin@univ.ac.id', '240be518fabd2724ddb6f04eeb1da5967448d7e831c08c8fa822809f74c720a9', 'admin', 'aktif', '2026-03-09 22:41:38', '2026-06-01 18:18:12', '2026-06-02 01:18:12'),
(2, 'Dr. Sari Indah, M.Kom', 'sari.indah@univ.ac.id', '6a43336baf50915c0042ba1ccecc7c75072763569bf8ad735bd7f6b4419ceb67', 'dosen', 'aktif', '2026-03-09 22:41:38', '2026-06-01 15:50:09', '2026-06-01 22:50:09'),
(3, 'Prof. Budi Raharjo, Ph.D', 'budi.raharjo@univ.ac.id', '6a43336baf50915c0042ba1ccecc7c75072763569bf8ad735bd7f6b4419ceb67', 'dosen', 'aktif', '2026-03-09 22:41:38', '2026-03-10 00:21:58', '2026-03-10 07:21:58'),
(4, 'Dr. Anto Wijaya, M.T', 'anto.wijaya@univ.ac.id', '6a43336baf50915c0042ba1ccecc7c75072763569bf8ad735bd7f6b4419ceb67', 'dosen', 'aktif', '2026-03-09 22:41:38', '2026-06-01 17:51:32', '2026-06-02 00:51:32'),
(5, 'Dr. Maya Lestari, M.Kom', 'maya.lestari@univ.ac.id', '6a43336baf50915c0042ba1ccecc7c75072763569bf8ad735bd7f6b4419ceb67', 'dosen', 'aktif', '2026-03-09 22:41:38', '2026-03-09 22:41:38', NULL),
(15, 'Muhammad Keisa Nabhan', 'kaisanabhan@univ.ac.id', 'eb67db6839be4d7309b3a070101dc9756e443291a945df9d0b8afa433678cb44', 'mahasiswa', 'aktif', '2026-06-01 16:13:01', '2026-06-01 17:25:32', '2026-06-02 00:25:32'),
(16, 'Danang Wijanarko', 'danangw@univ.ac.id', 'eb67db6839be4d7309b3a070101dc9756e443291a945df9d0b8afa433678cb44', 'mahasiswa', 'aktif', '2026-06-01 16:15:08', '2026-06-01 16:15:08', NULL),
(17, 'Marfin Nurhidayat', 'marfinn@univ.ac.id', 'eb67db6839be4d7309b3a070101dc9756e443291a945df9d0b8afa433678cb44', 'mahasiswa', 'aktif', '2026-06-01 16:15:48', '2026-06-01 16:15:48', NULL);

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id_admin`),
  ADD UNIQUE KEY `uq_user_admin` (`id_user`);

--
-- Indeks untuk tabel `bimbingan`
--
ALTER TABLE `bimbingan`
  ADD PRIMARY KEY (`id_bimbingan`),
  ADD KEY `idx_bimb_mhs` (`id_mahasiswa`),
  ADD KEY `idx_bimb_dosen` (`id_dosen`),
  ADD KEY `idx_bimb_judul` (`id_judul`),
  ADD KEY `idx_bimb_status` (`status`);

--
-- Indeks untuk tabel `catatan_bimbingan`
--
ALTER TABLE `catatan_bimbingan`
  ADD PRIMARY KEY (`id_catatan`),
  ADD KEY `idx_cat_sesi` (`id_sesi`),
  ADD KEY `idx_cat_keputusan` (`keputusan`);

--
-- Indeks untuk tabel `dokumen`
--
ALTER TABLE `dokumen`
  ADD PRIMARY KEY (`id_dokumen`),
  ADD KEY `idx_dok_mhs` (`id_mahasiswa`),
  ADD KEY `idx_dok_status` (`status`);

--
-- Indeks untuk tabel `dosen`
--
ALTER TABLE `dosen`
  ADD PRIMARY KEY (`id_dosen`),
  ADD UNIQUE KEY `uq_nip` (`nip`),
  ADD UNIQUE KEY `uq_user_dosen` (`id_user`),
  ADD KEY `idx_prodi_dsn` (`id_prodi`);

--
-- Indeks untuk tabel `judul_skripsi`
--
ALTER TABLE `judul_skripsi`
  ADD PRIMARY KEY (`id_judul`),
  ADD KEY `idx_judul_mhs` (`id_mahasiswa`),
  ADD KEY `idx_judul_status` (`status`);

--
-- Indeks untuk tabel `mahasiswa`
--
ALTER TABLE `mahasiswa`
  ADD PRIMARY KEY (`id_mahasiswa`),
  ADD UNIQUE KEY `uq_nim` (`nim`),
  ADD UNIQUE KEY `uq_user_mahasiswa` (`id_user`),
  ADD KEY `idx_prodi_mhs` (`id_prodi`),
  ADD KEY `idx_angkatan` (`angkatan`);

--
-- Indeks untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD PRIMARY KEY (`id_notif`),
  ADD KEY `idx_notif_user` (`id_user`),
  ADD KEY `idx_notif_read` (`is_read`),
  ADD KEY `idx_notif_tipe` (`tipe`);

--
-- Indeks untuk tabel `pengaturan`
--
ALTER TABLE `pengaturan`
  ADD PRIMARY KEY (`nama_key`);

--
-- Indeks untuk tabel `program_studi`
--
ALTER TABLE `program_studi`
  ADD PRIMARY KEY (`id_prodi`),
  ADD UNIQUE KEY `uq_kode_prodi` (`kode_prodi`);

--
-- Indeks untuk tabel `revisi`
--
ALTER TABLE `revisi`
  ADD PRIMARY KEY (`id_revisi`),
  ADD KEY `idx_rev_dok` (`id_dokumen`),
  ADD KEY `idx_rev_dosen` (`id_dosen`),
  ADD KEY `idx_rev_status` (`status`);

--
-- Indeks untuk tabel `sesi_bimbingan`
--
ALTER TABLE `sesi_bimbingan`
  ADD PRIMARY KEY (`id_sesi`),
  ADD KEY `idx_sesi_bimb` (`id_bimbingan`),
  ADD KEY `idx_sesi_tgl` (`tgl_sesi`),
  ADD KEY `idx_sesi_status` (`status`);

--
-- Indeks untuk tabel `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `uq_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_status` (`status`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `admin`
--
ALTER TABLE `admin`
  MODIFY `id_admin` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT untuk tabel `bimbingan`
--
ALTER TABLE `bimbingan`
  MODIFY `id_bimbingan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `catatan_bimbingan`
--
ALTER TABLE `catatan_bimbingan`
  MODIFY `id_catatan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `dokumen`
--
ALTER TABLE `dokumen`
  MODIFY `id_dokumen` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `dosen`
--
ALTER TABLE `dosen`
  MODIFY `id_dosen` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `judul_skripsi`
--
ALTER TABLE `judul_skripsi`
  MODIFY `id_judul` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `mahasiswa`
--
ALTER TABLE `mahasiswa`
  MODIFY `id_mahasiswa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  MODIFY `id_notif` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT untuk tabel `program_studi`
--
ALTER TABLE `program_studi`
  MODIFY `id_prodi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `revisi`
--
ALTER TABLE `revisi`
  MODIFY `id_revisi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `sesi_bimbingan`
--
ALTER TABLE `sesi_bimbingan`
  MODIFY `id_sesi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `user`
--
ALTER TABLE `user`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `admin`
--
ALTER TABLE `admin`
  ADD CONSTRAINT `fk_admin_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `bimbingan`
--
ALTER TABLE `bimbingan`
  ADD CONSTRAINT `fk_bimb_dosen` FOREIGN KEY (`id_dosen`) REFERENCES `dosen` (`id_dosen`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bimb_judul` FOREIGN KEY (`id_judul`) REFERENCES `judul_skripsi` (`id_judul`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bimb_mhs` FOREIGN KEY (`id_mahasiswa`) REFERENCES `mahasiswa` (`id_mahasiswa`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `catatan_bimbingan`
--
ALTER TABLE `catatan_bimbingan`
  ADD CONSTRAINT `fk_cat_sesi` FOREIGN KEY (`id_sesi`) REFERENCES `sesi_bimbingan` (`id_sesi`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `dokumen`
--
ALTER TABLE `dokumen`
  ADD CONSTRAINT `fk_dok_mhs` FOREIGN KEY (`id_mahasiswa`) REFERENCES `mahasiswa` (`id_mahasiswa`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `dosen`
--
ALTER TABLE `dosen`
  ADD CONSTRAINT `fk_dsn_prodi` FOREIGN KEY (`id_prodi`) REFERENCES `program_studi` (`id_prodi`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dsn_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `judul_skripsi`
--
ALTER TABLE `judul_skripsi`
  ADD CONSTRAINT `fk_judul_mhs` FOREIGN KEY (`id_mahasiswa`) REFERENCES `mahasiswa` (`id_mahasiswa`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mahasiswa`
--
ALTER TABLE `mahasiswa`
  ADD CONSTRAINT `fk_mhs_prodi` FOREIGN KEY (`id_prodi`) REFERENCES `program_studi` (`id_prodi`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mhs_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `revisi`
--
ALTER TABLE `revisi`
  ADD CONSTRAINT `fk_rev_dok` FOREIGN KEY (`id_dokumen`) REFERENCES `dokumen` (`id_dokumen`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rev_dosen` FOREIGN KEY (`id_dosen`) REFERENCES `dosen` (`id_dosen`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `sesi_bimbingan`
--
ALTER TABLE `sesi_bimbingan`
  ADD CONSTRAINT `fk_sesi_bimb` FOREIGN KEY (`id_bimbingan`) REFERENCES `bimbingan` (`id_bimbingan`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
