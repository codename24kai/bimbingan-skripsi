-- ============================================================
--  Migrasi PR B (alur & bahasa) — jalankan SEKALI di phpMyAdmin
--  pada database yang sudah berisi data (db_bimbingan_skripsi3.sql).
--  Aman dijalankan ulang.
-- ============================================================

-- 1. Status baru "dikonfirmasi" untuk jadwal yang sudah diterima dosen
ALTER TABLE `sesi_bimbingan`
  MODIFY `status` enum('dijadwalkan','dikonfirmasi','selesai','batal') NOT NULL DEFAULT 'dijadwalkan';

-- 2. Isi status skripsi yang selama ini tidak pernah berubah
UPDATE `mahasiswa` m
  JOIN `bimbingan` b ON b.id_mahasiswa = m.id_mahasiswa AND b.status = 'aktif'
   SET m.status_skripsi = 'proses'
 WHERE m.status_skripsi = 'belum';

UPDATE `mahasiswa` m
  JOIN `bimbingan` b ON b.id_mahasiswa = m.id_mahasiswa AND b.status = 'selesai'
   SET m.status_skripsi = 'selesai';

-- 3. Hitung ulang jumlah sesi selesai (data contoh dimasukkan tanpa trigger)
UPDATE `bimbingan` b
   SET b.total_sesi = (SELECT COUNT(*) FROM `sesi_bimbingan` sb
                        WHERE sb.id_bimbingan = b.id_bimbingan AND sb.status = 'selesai');
