-- ============================================================
--  SkripsiKu — Database lengkap (struktur + data contoh)
--
--  Cara impor:
--    phpMyAdmin → tab Import → pilih file ini → Kirim
--    atau: mysql -u root < database/db_bimbingan_skripsi.sql
--
--  File ini otomatis membuat database `db_bimbingan_skripsi`
--  dan sudah mencakup semua pembaruan (alur bimbingan, fitur
--  tambahan, SK otomatis). Tidak perlu menjalankan migrasi lain.
--
--  PERHATIAN: tabel yang sudah ada di database tersebut akan
--  dihapus dan dibuat ulang (DROP TABLE IF EXISTS).
--  Diuji di MariaDB 10.4 (XAMPP) s.d. 10.11.
-- ============================================================

CREATE DATABASE IF NOT EXISTS `db_bimbingan_skripsi` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `db_bimbingan_skripsi`;

-- Server version	10.11.14-MariaDB-0ubuntu0.24.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admin`
--

DROP TABLE IF EXISTS `admin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin` (
  `id_admin` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `level_akses` enum('super','regular') NOT NULL DEFAULT 'regular',
  `departemen` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_admin`),
  UNIQUE KEY `uq_user_admin` (`id_user`),
  CONSTRAINT `fk_admin_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Data administrator sistem';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin`
--

LOCK TABLES `admin` WRITE;
/*!40000 ALTER TABLE `admin` DISABLE KEYS */;
INSERT INTO `admin` VALUES
(1,1,'super','Bagian Akademik');
/*!40000 ALTER TABLE `admin` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `agenda_akademik`
--

DROP TABLE IF EXISTS `agenda_akademik`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `agenda_akademik` (
  `id_agenda` int(11) NOT NULL AUTO_INCREMENT,
  `nama` varchar(150) NOT NULL,
  `tgl_mulai` date NOT NULL,
  `tgl_selesai` date DEFAULT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_agenda`),
  KEY `idx_agenda_tgl` (`tgl_mulai`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tanggal penting semester';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `agenda_akademik`
--

LOCK TABLES `agenda_akademik` WRITE;
/*!40000 ALTER TABLE `agenda_akademik` DISABLE KEYS */;
/*!40000 ALTER TABLE `agenda_akademik` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bimbingan`
--

DROP TABLE IF EXISTS `bimbingan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bimbingan` (
  `id_bimbingan` int(11) NOT NULL AUTO_INCREMENT,
  `id_mahasiswa` int(11) NOT NULL,
  `id_dosen` int(11) NOT NULL,
  `id_judul` int(11) NOT NULL,
  `no_sk` varchar(100) DEFAULT NULL,
  `file_sk` varchar(255) DEFAULT NULL,
  `sk_tahun` smallint(5) unsigned DEFAULT NULL COMMENT 'Tahun pada nomor SK',
  `sk_urut` int(10) unsigned DEFAULT NULL COMMENT 'Nomor urut SK dalam tahun tsb',
  `tgl_sk` date DEFAULT NULL COMMENT 'Tanggal SK ditetapkan',
  `kode_verifikasi` varchar(20) DEFAULT NULL COMMENT 'Kode verifikasi keaslian SK',
  `semester` varchar(20) NOT NULL,
  `tgl_mulai` date NOT NULL,
  `tgl_selesai` date DEFAULT NULL,
  `total_sesi` smallint(5) unsigned NOT NULL DEFAULT 0,
  `status` enum('aktif','selesai','dihentikan') NOT NULL DEFAULT 'aktif',
  PRIMARY KEY (`id_bimbingan`),
  UNIQUE KEY `uq_bimbingan_sk_nomor` (`sk_tahun`,`sk_urut`),
  UNIQUE KEY `uq_bimbingan_sk_kode` (`kode_verifikasi`),
  KEY `idx_bimb_mhs` (`id_mahasiswa`),
  KEY `idx_bimb_dosen` (`id_dosen`),
  KEY `idx_bimb_judul` (`id_judul`),
  KEY `idx_bimb_status` (`status`),
  CONSTRAINT `fk_bimb_dosen` FOREIGN KEY (`id_dosen`) REFERENCES `dosen` (`id_dosen`) ON UPDATE CASCADE,
  CONSTRAINT `fk_bimb_judul` FOREIGN KEY (`id_judul`) REFERENCES `judul_skripsi` (`id_judul`) ON UPDATE CASCADE,
  CONSTRAINT `fk_bimb_mhs` FOREIGN KEY (`id_mahasiswa`) REFERENCES `mahasiswa` (`id_mahasiswa`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Relasi aktif bimbingan mahasiswa–dosen';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bimbingan`
--

LOCK TABLES `bimbingan` WRITE;
/*!40000 ALTER TABLE `bimbingan` DISABLE KEYS */;
/*!40000 ALTER TABLE `bimbingan` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_bimbingan_insert` AFTER INSERT ON `bimbingan` FOR EACH ROW BEGIN
    IF NEW.status = 'aktif' THEN
        UPDATE dosen SET terisi = terisi + 1
        WHERE id_dosen = NEW.id_dosen;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_bimbingan_update` AFTER UPDATE ON `bimbingan` FOR EACH ROW BEGIN
    IF OLD.status = 'aktif'
       AND NEW.status IN ('selesai','dihentikan') THEN
        UPDATE dosen SET terisi = GREATEST(terisi - 1, 0)
        WHERE id_dosen = NEW.id_dosen;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `catatan_bimbingan`
--

DROP TABLE IF EXISTS `catatan_bimbingan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `catatan_bimbingan` (
  `id_catatan` int(11) NOT NULL AUTO_INCREMENT,
  `id_sesi` int(11) NOT NULL,
  `isi_catatan` text NOT NULL,
  `keputusan` enum('disetujui','revisi','ditolak') NOT NULL,
  `file_lampiran` varchar(255) DEFAULT NULL,
  `bab_terkait` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_catatan`),
  KEY `idx_cat_sesi` (`id_sesi`),
  KEY `idx_cat_keputusan` (`keputusan`),
  CONSTRAINT `fk_cat_sesi` FOREIGN KEY (`id_sesi`) REFERENCES `sesi_bimbingan` (`id_sesi`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catatan dosen setelah sesi bimbingan';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `catatan_bimbingan`
--

LOCK TABLES `catatan_bimbingan` WRITE;
/*!40000 ALTER TABLE `catatan_bimbingan` DISABLE KEYS */;
/*!40000 ALTER TABLE `catatan_bimbingan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dokumen`
--

DROP TABLE IF EXISTS `dokumen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dokumen` (
  `id_dokumen` int(11) NOT NULL AUTO_INCREMENT,
  `id_mahasiswa` int(11) NOT NULL,
  `nama_dokumen` varchar(150) NOT NULL,
  `jenis_bab` varchar(30) NOT NULL,
  `versi` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `file_path` varchar(255) NOT NULL,
  `ukuran_kb` int(10) unsigned NOT NULL,
  `format_file` varchar(10) NOT NULL DEFAULT 'pdf',
  `status` enum('menunggu','direvisi','disetujui') NOT NULL DEFAULT 'menunggu',
  `tgl_upload` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_dokumen`),
  KEY `idx_dok_mhs` (`id_mahasiswa`),
  KEY `idx_dok_status` (`status`),
  CONSTRAINT `fk_dok_mhs` FOREIGN KEY (`id_mahasiswa`) REFERENCES `mahasiswa` (`id_mahasiswa`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dokumen skripsi yang diunggah mahasiswa';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dokumen`
--

LOCK TABLES `dokumen` WRITE;
/*!40000 ALTER TABLE `dokumen` DISABLE KEYS */;
/*!40000 ALTER TABLE `dokumen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dosen`
--

DROP TABLE IF EXISTS `dosen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dosen` (
  `id_dosen` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `id_prodi` int(11) NOT NULL,
  `nip` varchar(20) NOT NULL,
  `jabatan_fungsional` enum('Tenaga Pengajar','Asisten Ahli','Lektor','Lektor Kepala','Guru Besar') DEFAULT NULL COMMENT 'Jabatan Fungsional Akademik Dosen',
  `keahlian` enum('Rekayasa Perangkat Lunak & Web','Pemrograman Mobile','Kecerdasan Buatan & Machine Learning','Data Science & Analitik','Internet of Things (IoT)','Keamanan Siber & Jaringan','Manajemen Sistem Informasi') DEFAULT NULL COMMENT 'Bidang Keahlian Dosen Ilkom',
  `kuota_bimbingan` tinyint(3) unsigned NOT NULL DEFAULT 10,
  `terisi` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_dosen`),
  UNIQUE KEY `uq_nip` (`nip`),
  UNIQUE KEY `uq_user_dosen` (`id_user`),
  KEY `idx_prodi_dsn` (`id_prodi`),
  CONSTRAINT `fk_dsn_prodi` FOREIGN KEY (`id_prodi`) REFERENCES `program_studi` (`id_prodi`) ON UPDATE CASCADE,
  CONSTRAINT `fk_dsn_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dosen`
--

LOCK TABLES `dosen` WRITE;
/*!40000 ALTER TABLE `dosen` DISABLE KEYS */;
INSERT INTO `dosen` VALUES
(5,18,1,'1000010326','Tenaga Pengajar','Internet of Things (IoT)',10,0),
(6,20,2,'1980020221','Tenaga Pengajar','Rekayasa Perangkat Lunak & Web',3,0);
/*!40000 ALTER TABLE `dosen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `judul_skripsi`
--

DROP TABLE IF EXISTS `judul_skripsi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `judul_skripsi` (
  `id_judul` int(11) NOT NULL AUTO_INCREMENT,
  `id_mahasiswa` int(11) NOT NULL,
  `judul_indonesia` text NOT NULL,
  `judul_alternatif_1` text DEFAULT NULL,
  `judul_alternatif_2` text DEFAULT NULL,
  `bidang_penelitian` varchar(100) NOT NULL,
  `latar_belakang` text DEFAULT NULL,
  `metode_penelitian` enum('Agile Software Development','Waterfall Model','RAD (Rapid Application Development)','Prototyping','CRISP-DM (Data Science)','Riset Eksperimental','Kuantitatif','Kualitatif','Lainnya') DEFAULT NULL COMMENT 'Metode pengembangan aplikasi atau penelitian',
  `iterasi` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `usulan_dosen` int(11) DEFAULT NULL COMMENT 'ID Dosen yang diusulkan oleh mahasiswa',
  `status` enum('draft','diajukan','revisi','disetujui','ditolak') NOT NULL DEFAULT 'draft',
  `tgl_pengajuan` datetime NOT NULL DEFAULT current_timestamp(),
  `file_proposal` varchar(255) DEFAULT NULL,
  `file_transkrip` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_judul`),
  KEY `idx_judul_mhs` (`id_mahasiswa`),
  KEY `idx_judul_status` (`status`),
  CONSTRAINT `fk_judul_mhs` FOREIGN KEY (`id_mahasiswa`) REFERENCES `mahasiswa` (`id_mahasiswa`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Riwayat pengajuan judul skripsi';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `judul_skripsi`
--

LOCK TABLES `judul_skripsi` WRITE;
/*!40000 ALTER TABLE `judul_skripsi` DISABLE KEYS */;
INSERT INTO `judul_skripsi` VALUES
(9,8,'Rancang Bangun Prototipe Sistem Keamanan Rumah Pintar Berbasis IoT Menggunakan Pengenalan Wajah dan Notifikasi Telegram.','Pengembangan Purwarupa Sistem Otomatisasi Pencahayaan dan Pendingin Ruangan Berbasis IoT untuk Efisiensi Energi Rumah Pintar.','Prototipe Alat Deteksi Kebocoran Gas LPG dan Kebakaran pada Smart Home Berbasis IoT Menggunakan Sensor MQ-2.','Internet of Things (IoT)','Rumah merupakan aset berharga yang berfungsi sebagai tempat berlindung sekaligus ruang privasi utama bagi setiap keluarga. Seiring dengan peningkatan mobilitas masyarakat, rumah sering kali ditinggalkan dalam keadaan kosong dalam waktu yang cukup lama. Kondisi ini memicu berbagai risiko domestik yang mengancam rumah tinggal, yang secara garis besar dikelompokkan ke dalam tiga masalah utama: ancaman kriminalitas (keamanan), pemborosan konsumsi listrik (efisiensi energi), dan risiko bencana internal seperti kebakaran (keselamatan).Dari sisi keamanan, metode konvensional seperti kunci manual dan kamera CCTV statis dinilai kurang optimal karena tidak mampu memberikan peringatan dini (real-time) saat terjadi pembobolan. Dari sisi efisiensi energi, kelalaian penghuni dalam mematikan perangkat elektronik seperti lampu dan pendingin ruangan (AC) saat bepergian memicu pembengkakan tagihan listrik secara signifikan. Sementara dari sisi keselamatan, kebocoran gas LPG dan hubungan arus pendek listrik yang terlambat disadari sering kali menjadi pemicu utama kebakaran hebat yang menimbulkan kerugian materiil hingga korban jiwa. Selama ini, solusi untuk ketiga masalah tersebut masih bersifat parsial atau berdiri sendiri-sendiri, sehingga kurang praktis dan mahal dalam implementasinya.\r\n\r\nPerkembangan teknologi Internet of Things (IoT) membuka peluang besar untuk mengintegrasikan solusi-solusi tersebut ke dalam satu kesatuan sistem rumah pintar (smart home). Dengan memanfaatkan mikrokontroler sebagai pusat kendali yang terhubung ke internet, berbagai parameter rumah dapat dipantau dari jarak jauh. Keamanan dapat ditingkatkan melalui teknologi pemindaian wajah (face recognition) yang mengirimkan notifikasi instan ke ponsel pemilik rumah. Efisiensi energi dapat dicapai melalui otomatisasi lampu dan AC menggunakan sensor kehadiran manusia dan sensor suhu. Sedangkan aspek keselamatan dapat dijamin dengan menempatkan sensor pendeteksi gas dan asap yang mampu memicu alarm serta mengirim peringatan darurat secara otomatis saat potensi kebakaran terdeteksi.Oleh karena itu, penelitian ini bertujuan untuk mengembangkan sebuah prototipe ekosistem smart home yang terintegrasi secara menyeluruh. Pendekatan metode prototyping dipilih dalam penelitian ini agar setiap sub-sistem—baik fitur keamanan, efisiensi, maupun keselamatan—dapat dirancang, diuji, dan dievaluasi secara bertahap. Melalui metode ini, interaksi antar-sensor dan ketepatan respons sistem dapat divalidasi dengan optimal sebelum diimplementasikan pada skala rumah tinggal yang sebenarnya.','Prototyping',1,NULL,'disetujui','2026-06-02 07:46:42',NULL,'uploads/syarat_8_6a1e27f2a97d3.pdf');
/*!40000 ALTER TABLE `judul_skripsi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `komentar_dokumen`
--

DROP TABLE IF EXISTS `komentar_dokumen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `komentar_dokumen` (
  `id_komentar` int(11) NOT NULL AUTO_INCREMENT,
  `id_dokumen` int(11) NOT NULL,
  `id_revisi` int(11) DEFAULT NULL COMMENT 'Diisi jika menanggapi catatan revisi tertentu',
  `id_user` int(11) NOT NULL,
  `isi` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_komentar`),
  KEY `idx_kom_dok` (`id_dokumen`,`created_at`),
  KEY `fk_kom_rev` (`id_revisi`),
  KEY `fk_kom_user` (`id_user`),
  CONSTRAINT `fk_kom_dok` FOREIGN KEY (`id_dokumen`) REFERENCES `dokumen` (`id_dokumen`) ON DELETE CASCADE,
  CONSTRAINT `fk_kom_rev` FOREIGN KEY (`id_revisi`) REFERENCES `revisi` (`id_revisi`) ON DELETE SET NULL,
  CONSTRAINT `fk_kom_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Diskusi mahasiswa-dosen per dokumen';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `komentar_dokumen`
--

LOCK TABLES `komentar_dokumen` WRITE;
/*!40000 ALTER TABLE `komentar_dokumen` DISABLE KEYS */;
/*!40000 ALTER TABLE `komentar_dokumen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mahasiswa`
--

DROP TABLE IF EXISTS `mahasiswa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mahasiswa` (
  `id_mahasiswa` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `id_prodi` int(11) NOT NULL,
  `nim` varchar(20) NOT NULL,
  `angkatan` year(4) NOT NULL,
  `status_skripsi` enum('belum','proses','selesai') NOT NULL DEFAULT 'belum',
  `no_telepon` varchar(15) DEFAULT NULL,
  PRIMARY KEY (`id_mahasiswa`),
  UNIQUE KEY `uq_nim` (`nim`),
  UNIQUE KEY `uq_user_mahasiswa` (`id_user`),
  KEY `idx_prodi_mhs` (`id_prodi`),
  KEY `idx_angkatan` (`angkatan`),
  CONSTRAINT `fk_mhs_prodi` FOREIGN KEY (`id_prodi`) REFERENCES `program_studi` (`id_prodi`) ON UPDATE CASCADE,
  CONSTRAINT `fk_mhs_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Data profil mahasiswa';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mahasiswa`
--

LOCK TABLES `mahasiswa` WRITE;
/*!40000 ALTER TABLE `mahasiswa` DISABLE KEYS */;
INSERT INTO `mahasiswa` VALUES
(8,15,1,'231011403449',2023,'belum',NULL),
(9,16,1,'231011403460',2023,'belum',NULL),
(10,17,1,'231011403426',2023,'belum',NULL),
(11,19,1,'231011401421',2023,'belum',NULL),
(12,21,1,'231011402222',2023,'belum',NULL);
/*!40000 ALTER TABLE `mahasiswa` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `nilai_ujian`
--

DROP TABLE IF EXISTS `nilai_ujian`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nilai_ujian` (
  `id_ujian` int(11) NOT NULL,
  `id_dosen` int(11) NOT NULL,
  `peran` enum('pembimbing','penguji') NOT NULL,
  `n_penulisan` tinyint(3) unsigned NOT NULL,
  `n_presentasi` tinyint(3) unsigned NOT NULL,
  `n_penguasaan` tinyint(3) unsigned NOT NULL,
  `n_metodologi` tinyint(3) unsigned NOT NULL,
  `catatan` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_ujian`,`id_dosen`),
  KEY `fk_nu_dosen` (`id_dosen`),
  CONSTRAINT `fk_nu_dosen` FOREIGN KEY (`id_dosen`) REFERENCES `dosen` (`id_dosen`) ON DELETE CASCADE,
  CONSTRAINT `fk_nu_ujian` FOREIGN KEY (`id_ujian`) REFERENCES `ujian` (`id_ujian`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Nilai rubrik per dosen per ujian';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `nilai_ujian`
--

LOCK TABLES `nilai_ujian` WRITE;
/*!40000 ALTER TABLE `nilai_ujian` DISABLE KEYS */;
/*!40000 ALTER TABLE `nilai_ujian` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifikasi`
--

DROP TABLE IF EXISTS `notifikasi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifikasi` (
  `id_notif` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `judul` varchar(150) NOT NULL,
  `pesan` text NOT NULL,
  `tipe` enum('info','sukses','peringatan','error') NOT NULL DEFAULT 'info',
  `tautan` varchar(255) DEFAULT NULL,
  `modul` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_notif`),
  KEY `idx_notif_user` (`id_user`),
  KEY `idx_notif_read` (`is_read`),
  KEY `idx_notif_tipe` (`tipe`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log notifikasi untuk semua pengguna';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifikasi`
--

LOCK TABLES `notifikasi` WRITE;
/*!40000 ALTER TABLE `notifikasi` DISABLE KEYS */;
INSERT INTO `notifikasi` VALUES
(25,15,'Judul Skripsi Disetujui!','Selamat! Judul Anda telah disetujui. Silakan tunggu penetapan Dosen Pembimbing.','sukses',NULL,'judul',0,'2026-06-02 08:39:46');
/*!40000 ALTER TABLE `notifikasi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pengaturan`
--

DROP TABLE IF EXISTS `pengaturan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pengaturan` (
  `nama_key` varchar(50) NOT NULL,
  `nilai_value` text NOT NULL,
  PRIMARY KEY (`nama_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel konfigurasi umum sistem';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pengaturan`
--

LOCK TABLES `pengaturan` WRITE;
/*!40000 ALTER TABLE `pengaturan` DISABLE KEYS */;
INSERT INTO `pengaturan` VALUES
('batas_tidak_aktif_hari','30'),
('default_kuota_dosen','10'),
('kontak_prodi','Bagian Akademik Fakultas Ilmu Komputer, Gedung A Lt. 1 (Seninâ€“Jumat, 08.00â€“15.00)'),
('maksimal_kuota_dosen','20'),
('nama_institusi','University of Birmingham'),
('pengingat_terakhir',''),
('semester_aktif','Genap'),
('sk_alamat','Edgbaston, Birmingham B15 2TT, United Kingdom'),
('sk_institusi','University of Birmingham'),
('sk_jabatan_ttd','Dean of School of Computing and Digital Technology'),
('sk_penandatangan','Dr. Sarah J. Williams'),
('sk_tempat','Birmingham, United Kingdom'),
('sk_unit','School of Computing and Digital Technology'),
('tahun_akademik','2025/2026');
/*!40000 ALTER TABLE `pengaturan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pengingat_log`
--

DROP TABLE IF EXISTS `pengingat_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pengingat_log` (
  `jenis` varchar(30) NOT NULL,
  `ref_id` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `periode` varchar(10) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`jenis`,`ref_id`,`id_user`,`periode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pengingat otomatis yang sudah terkirim';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pengingat_log`
--

LOCK TABLES `pengingat_log` WRITE;
/*!40000 ALTER TABLE `pengingat_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `pengingat_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `penguji_ujian`
--

DROP TABLE IF EXISTS `penguji_ujian`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `penguji_ujian` (
  `id_ujian` int(11) NOT NULL,
  `id_dosen` int(11) NOT NULL,
  `peran` enum('ketua','anggota') NOT NULL DEFAULT 'anggota',
  PRIMARY KEY (`id_ujian`,`id_dosen`),
  KEY `fk_pu_dosen` (`id_dosen`),
  CONSTRAINT `fk_pu_dosen` FOREIGN KEY (`id_dosen`) REFERENCES `dosen` (`id_dosen`) ON DELETE CASCADE,
  CONSTRAINT `fk_pu_ujian` FOREIGN KEY (`id_ujian`) REFERENCES `ujian` (`id_ujian`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dosen penguji per ujian';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `penguji_ujian`
--

LOCK TABLES `penguji_ujian` WRITE;
/*!40000 ALTER TABLE `penguji_ujian` DISABLE KEYS */;
/*!40000 ALTER TABLE `penguji_ujian` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pengumuman`
--

DROP TABLE IF EXISTS `pengumuman`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `pengumuman` (
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
  KEY `fk_peng_user` (`dibuat_oleh`),
  KEY `fk_peng_prodi` (`id_prodi`),
  CONSTRAINT `fk_peng_prodi` FOREIGN KEY (`id_prodi`) REFERENCES `program_studi` (`id_prodi`) ON DELETE SET NULL,
  CONSTRAINT `fk_peng_user` FOREIGN KEY (`dibuat_oleh`) REFERENCES `user` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pengumuman dari admin';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pengumuman`
--

LOCK TABLES `pengumuman` WRITE;
/*!40000 ALTER TABLE `pengumuman` DISABLE KEYS */;
/*!40000 ALTER TABLE `pengumuman` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permintaan_reset`
--

DROP TABLE IF EXISTS `permintaan_reset`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `permintaan_reset` (
  `id_permintaan` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `status` enum('menunggu','selesai','ditolak') NOT NULL DEFAULT 'menunggu',
  `catatan` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `diproses_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id_permintaan`),
  KEY `idx_reset_status` (`status`),
  KEY `fk_reset_user` (`id_user`),
  CONSTRAINT `fk_reset_user` FOREIGN KEY (`id_user`) REFERENCES `user` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Permintaan reset kata sandi ke admin';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permintaan_reset`
--

LOCK TABLES `permintaan_reset` WRITE;
/*!40000 ALTER TABLE `permintaan_reset` DISABLE KEYS */;
/*!40000 ALTER TABLE `permintaan_reset` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `program_studi`
--

DROP TABLE IF EXISTS `program_studi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `program_studi` (
  `id_prodi` int(11) NOT NULL AUTO_INCREMENT,
  `kode_prodi` varchar(10) NOT NULL,
  `nama_prodi` varchar(100) NOT NULL,
  `jenjang` enum('D3','S1','S2','S3') NOT NULL DEFAULT 'S1',
  `fakultas` varchar(100) NOT NULL,
  `akreditasi` char(1) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_prodi`),
  UNIQUE KEY `uq_kode_prodi` (`kode_prodi`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Referensi program studi yang tersedia';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `program_studi`
--

LOCK TABLES `program_studi` WRITE;
/*!40000 ALTER TABLE `program_studi` DISABLE KEYS */;
INSERT INTO `program_studi` VALUES
(1,'TIF','Teknik Informatika','S1','Fakultas Ilmu Komputer','A','2026-03-09 22:41:38','2026-03-09 22:41:38'),
(2,'SIF','Sistem Informasi','S1','Fakultas Ilmu Komputer','A','2026-03-09 22:41:38','2026-03-09 22:41:38');
/*!40000 ALTER TABLE `program_studi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `revisi`
--

DROP TABLE IF EXISTS `revisi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `revisi` (
  `id_revisi` int(11) NOT NULL AUTO_INCREMENT,
  `id_dokumen` int(11) NOT NULL,
  `id_dosen` int(11) NOT NULL,
  `catatan_revisi` text NOT NULL,
  `halaman_revisi` varchar(100) DEFAULT NULL,
  `prioritas` enum('minor','mayor','kritis') NOT NULL DEFAULT 'minor',
  `status` enum('belum','sudah') NOT NULL DEFAULT 'belum',
  `tgl_revisi` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_revisi`),
  KEY `idx_rev_dok` (`id_dokumen`),
  KEY `idx_rev_dosen` (`id_dosen`),
  KEY `idx_rev_status` (`status`),
  CONSTRAINT `fk_rev_dok` FOREIGN KEY (`id_dokumen`) REFERENCES `dokumen` (`id_dokumen`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rev_dosen` FOREIGN KEY (`id_dosen`) REFERENCES `dosen` (`id_dosen`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catatan revisi dosen untuk dokumen mahasiswa';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `revisi`
--

LOCK TABLES `revisi` WRITE;
/*!40000 ALTER TABLE `revisi` DISABLE KEYS */;
/*!40000 ALTER TABLE `revisi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sesi_bimbingan`
--

DROP TABLE IF EXISTS `sesi_bimbingan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sesi_bimbingan` (
  `id_sesi` int(11) NOT NULL AUTO_INCREMENT,
  `id_bimbingan` int(11) NOT NULL,
  `id_slot` int(11) DEFAULT NULL,
  `tgl_sesi` date NOT NULL,
  `waktu_mulai` time NOT NULL,
  `durasi_menit` smallint(5) unsigned DEFAULT NULL,
  `topik` text NOT NULL,
  `metode` enum('tatap_muka','zoom','gmeet','lainnya') NOT NULL DEFAULT 'tatap_muka',
  `lokasi_link` varchar(255) DEFAULT NULL,
  `status` enum('dijadwalkan','dikonfirmasi','selesai','batal') NOT NULL DEFAULT 'dijadwalkan',
  PRIMARY KEY (`id_sesi`),
  KEY `idx_sesi_bimb` (`id_bimbingan`),
  KEY `idx_sesi_tgl` (`tgl_sesi`),
  KEY `idx_sesi_status` (`status`),
  KEY `idx_sesi_slot` (`id_slot`),
  CONSTRAINT `fk_sesi_bimb` FOREIGN KEY (`id_bimbingan`) REFERENCES `bimbingan` (`id_bimbingan`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Setiap pertemuan sesi bimbingan';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sesi_bimbingan`
--

LOCK TABLES `sesi_bimbingan` WRITE;
/*!40000 ALTER TABLE `sesi_bimbingan` DISABLE KEYS */;
/*!40000 ALTER TABLE `sesi_bimbingan` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50003 TRIGGER `trg_sesi_selesai` AFTER UPDATE ON `sesi_bimbingan` FOR EACH ROW BEGIN
    IF NEW.status = 'selesai' AND OLD.status != 'selesai' THEN
        UPDATE bimbingan
        SET total_sesi = total_sesi + 1
        WHERE id_bimbingan = NEW.id_bimbingan;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `sk_nomor`
--

DROP TABLE IF EXISTS `sk_nomor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sk_nomor` (
  `tahun` smallint(5) unsigned NOT NULL,
  `terakhir` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Nomor urut terakhir yang terbit',
  PRIMARY KEY (`tahun`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Penghitung nomor SK bimbingan per tahun';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sk_nomor`
--

LOCK TABLES `sk_nomor` WRITE;
/*!40000 ALTER TABLE `sk_nomor` DISABLE KEYS */;
/*!40000 ALTER TABLE `sk_nomor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `slot_dosen`
--

DROP TABLE IF EXISTS `slot_dosen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `slot_dosen` (
  `id_slot` int(11) NOT NULL AUTO_INCREMENT,
  `id_dosen` int(11) NOT NULL,
  `tgl` date NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `kuota` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `metode` enum('tatap_muka','zoom','gmeet','lainnya') NOT NULL DEFAULT 'tatap_muka',
  `lokasi_link` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_slot`),
  KEY `idx_slot_dosen_tgl` (`id_dosen`,`tgl`),
  CONSTRAINT `fk_slot_dosen` FOREIGN KEY (`id_dosen`) REFERENCES `dosen` (`id_dosen`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Jam konsultasi yang dibuka dosen';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `slot_dosen`
--

LOCK TABLES `slot_dosen` WRITE;
/*!40000 ALTER TABLE `slot_dosen` DISABLE KEYS */;
/*!40000 ALTER TABLE `slot_dosen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `template_dokumen`
--

DROP TABLE IF EXISTS `template_dokumen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `template_dokumen` (
  `id_template` int(11) NOT NULL AUTO_INCREMENT,
  `nama` varchar(150) NOT NULL,
  `kategori` enum('proposal','bab','pengesahan','formulir','lainnya') NOT NULL DEFAULT 'lainnya',
  `keterangan` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `ukuran_kb` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_template`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Template dokumen skripsi';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `template_dokumen`
--

LOCK TABLES `template_dokumen` WRITE;
/*!40000 ALTER TABLE `template_dokumen` DISABLE KEYS */;
/*!40000 ALTER TABLE `template_dokumen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ujian`
--

DROP TABLE IF EXISTS `ujian`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ujian` (
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ujian`
--

LOCK TABLES `ujian` WRITE;
/*!40000 ALTER TABLE `ujian` DISABLE KEYS */;
/*!40000 ALTER TABLE `ujian` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user` (
  `id_user` int(11) NOT NULL AUTO_INCREMENT,
  `nama_lengkap` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('mahasiswa','dosen','admin') NOT NULL,
  `status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `uq_email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel induk autentikasi semua pengguna';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user`
--

LOCK TABLES `user` WRITE;
/*!40000 ALTER TABLE `user` DISABLE KEYS */;
INSERT INTO `user` VALUES
(1,'Admin Akademik','admin@univ.ac.id','240be518fabd2724ddb6f04eeb1da5967448d7e831c08c8fa822809f74c720a9','admin','aktif','2026-03-09 22:41:38','2026-06-02 02:27:05','2026-06-02 09:27:05'),
(15,'Muhammad Keisa Nabhan','kaisanabhan@univ.ac.id','eb67db6839be4d7309b3a070101dc9756e443291a945df9d0b8afa433678cb44','mahasiswa','aktif','2026-06-01 16:13:01','2026-06-02 02:36:06','2026-06-02 09:36:06'),
(16,'Danang Wijanarko','danangw@univ.ac.id','eb67db6839be4d7309b3a070101dc9756e443291a945df9d0b8afa433678cb44','mahasiswa','aktif','2026-06-01 16:15:08','2026-06-01 16:15:08',NULL),
(17,'Marfin Nurhidayat','marfinn@univ.ac.id','eb67db6839be4d7309b3a070101dc9756e443291a945df9d0b8afa433678cb44','mahasiswa','aktif','2026-06-01 16:15:48','2026-06-02 02:41:53','2026-06-02 09:41:53'),
(18,'Mahabarata','maha@univ.ac.id','6a43336baf50915c0042ba1ccecc7c75072763569bf8ad735bd7f6b4419ceb67','dosen','aktif','2026-06-02 00:54:58','2026-06-02 01:18:32','2026-06-02 08:18:32'),
(19,'Fajar Firdaus','fajarf@univ.ac.id','eb67db6839be4d7309b3a070101dc9756e443291a945df9d0b8afa433678cb44','mahasiswa','aktif','2026-06-02 01:13:33','2026-06-02 01:14:03','2026-06-02 08:14:03'),
(20,'Carrick Michael','michaelc@univ.ac.id','6a43336baf50915c0042ba1ccecc7c75072763569bf8ad735bd7f6b4419ceb67','dosen','aktif','2026-06-02 02:29:01','2026-06-02 02:40:41','2026-06-02 09:40:41'),
(21,'Rizki Indra Jaya','rizkyjaya@univ.ac.id','eb67db6839be4d7309b3a070101dc9756e443291a945df9d0b8afa433678cb44','mahasiswa','aktif','2026-06-02 02:36:42','2026-06-02 02:36:42',NULL);
/*!40000 ALTER TABLE `user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Selesai
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

