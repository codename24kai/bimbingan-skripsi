<?php
// info/panduan.php — Panduan alur skripsi (S1) dan cara memakai website per peran (S4)
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireLogin();

$pageTitle  = 'Panduan';
$activePage = 'panduan';
$r          = role();

// Alur skripsi: [judul, siapa, keterangan, halaman]
$alur = [
    ['Ajukan judul', 'Mahasiswa', 'Isi judul utama dan dua judul alternatif, bidang penelitian, lalu unggah transkrip nilai. Sistem memberi tahu jika ada judul lain yang mirip.', 'mahasiswa/pengajuan.php'],
    ['Validasi judul', 'Admin / Dosen', 'Judul diperiksa. Hasilnya disetujui, perlu revisi, atau ditolak. Jika perlu revisi, perbaiki lalu ajukan ulang.', null],
    ['Usulkan dosen pembimbing', 'Mahasiswa', 'Setelah judul disetujui, pilih dosen yang bidang keahliannya sesuai dan kuotanya masih tersedia.', 'mahasiswa/pengajuan_dosen.php'],
    ['SK pembimbing terbit', 'Admin', 'Admin menetapkan pembimbing dan mengunggah SK. Menu Dokumen, Jadwal, Riwayat, serta Seminar & Sidang terbuka.', null],
    ['Bimbingan rutin', 'Mahasiswa & Dosen', 'Pilih slot konsultasi dosen atau ajukan jadwal. Setelah sesi, dosen menulis catatan bimbingan.', 'mahasiswa/bimbingan.php'],
    ['Unggah dan revisi bab', 'Mahasiswa & Dosen', 'Unggah Bab I sampai V. Dosen memberi catatan revisi per halaman; diskusikan di kolom diskusi dokumen. Unggah ulang untuk menaikkan versi.', 'mahasiswa/dokumen.php'],
    ['Seminar proposal', 'Mahasiswa, Dosen, Admin', 'Daftar seminar dengan berkas persyaratan. Pembimbing menyetujui, admin menjadwalkan dan menunjuk penguji.', 'mahasiswa/ujian.php'],
    ['Sidang akhir', 'Mahasiswa, Dosen, Admin', 'Setelah Bab V disetujui, daftar sidang akhir. Penguji dan pembimbing mengisi nilai, admin menetapkan hasil.', 'mahasiswa/ujian.php'],
    ['Revisi akhir dan selesai', 'Mahasiswa & Admin', 'Kerjakan revisi sidang, cetak kartu bimbingan dan lembar pengesahan, lalu admin menutup bimbingan.', 'info/template.php'],
];

$caraPakai = [
    'mahasiswa' => ['Mahasiswa', [
        ['Mengganti kata sandi', 'Buka Profil Saya, isi kata sandi lama dan baru (minimal 8 karakter). Akun baru memakai kata sandi awal uab# + 6 digit terakhir NIM/NIP dan wajib menggantinya saat login pertama.'],
        ['Mengajukan jadwal bimbingan', 'Buka Jadwal Bimbingan. Pilih slot yang dibuka dosen (langsung terkonfirmasi), atau ajukan waktu sendiri dan tunggu konfirmasi dosen.'],
        ['Mengunggah dokumen', 'Buka Dokumen & Revisi, pilih bab, lalu seret file PDF atau DOCX (maks 10 MB). Mengunggah bab yang sama lagi akan membuat versi baru (v2, v3, dan seterusnya).'],
        ['Membalas catatan revisi', 'Di Dokumen & Revisi, klik ikon diskusi pada dokumen. Tulis pertanyaan atau tanggapan; dosen mendapat notifikasi.'],
        ['Mencetak kartu bimbingan', 'Buka Riwayat Bimbingan lalu klik Cetak Kartu Bimbingan. Pilih "Simpan sebagai PDF" di jendela cetak browser.'],
        ['Mendaftar seminar atau sidang', 'Buka Seminar & Sidang, pilih jenis ujian, unggah berkas persyaratan (PDF atau ZIP), lalu tunggu persetujuan pembimbing.'],
    ]],
    'dosen' => ['Dosen', [
        ['Membuka jam konsultasi', 'Buka Slot Konsultasi, isi tanggal dan jam, dan bisa diulang setiap minggu. Mahasiswa bimbingan dapat langsung memesan slot yang kosong.'],
        ['Mengonfirmasi jadwal', 'Jadwal yang diajukan mahasiswa muncul di Jadwal Konsultasi bagian Menunggu Konfirmasi. Terima atau tolak, lalu tandai selesai setelah sesi.'],
        ['Memberi revisi dokumen', 'Buka Review Dokumen, pilih dokumen, tulis catatan beserta halaman dan prioritas. Gunakan Bandingkan Versi untuk melihat perubahan dari versi sebelumnya.'],
        ['Menulis catatan bimbingan', 'Buka Catatan Bimbingan, pilih sesi, lalu tulis catatan dan keputusan. Catatan ini masuk ke kartu bimbingan mahasiswa.'],
        ['Menyetujui dan menilai ujian', 'Buka Seminar & Sidang. Setujui pendaftaran mahasiswa bimbingan Anda, dan isi rubrik nilai untuk ujian yang Anda bimbing atau uji.'],
    ]],
    'admin' => ['Admin', [
        ['Menambah banyak akun sekaligus', 'Buka Impor Data, unduh contoh CSV, isi di Excel, simpan sebagai CSV, lalu unggah. Kata sandi awal tiap akun adalah uab# + 6 digit terakhir NIM/NIP dan wajib diganti saat login pertama.'],
        ['Menerbitkan SK pembimbing', 'Buka Relasi & SK, pilih mahasiswa dari antrean, tetapkan dosen, lalu unggah file SK.'],
        ['Membuat pengumuman', 'Buka Pengumuman, isi judul dan isi, pilih target (semua, mahasiswa, atau dosen) dan prodi. Centang Sematkan untuk menaruhnya paling atas.'],
        ['Menjadwalkan seminar dan sidang', 'Buka Seminar & Sidang. Pendaftaran yang sudah disetujui pembimbing bisa dijadwalkan dan diberi penguji, lalu hasilnya ditetapkan setelah ujian.'],
        ['Menangani lupa kata sandi', 'Permintaan dari halaman Lupa Kata Sandi muncul di Reset Kata Sandi. Klik Reset (kata sandi kembali ke uab# + 6 digit terakhir NIM/NIP), lalu sampaikan langsung ke pemilik akun.'],
        ['Mengisi kalender akademik', 'Buka Kalender, lalu tambahkan agenda seperti batas pendaftaran sidang. Agenda tampil di kalender semua pengguna.'],
    ]],
];

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">
  <div class="page-header">
    <h1>Panduan</h1>
    <p>Alur skripsi dari pengajuan judul sampai selesai, dan cara memakai website</p>
  </div>

  <div class="card mb-20">
    <div class="card-header"><div class="card-title">Alur skripsi</div><span class="badge neutral"><?= count($alur) ?> langkah</span></div>
    <div class="card-body">
      <ol class="langkah alur-panduan">
        <?php foreach ($alur as $i => [$judul, $siapa, $ket, $href]): ?>
          <li class="langkah-item todo">
            <span class="langkah-no"><?= $i + 1 ?></span>
            <div class="min-w-0">
              <div class="langkah-title"><?= e($judul) ?> <span class="badge neutral" style="margin-left:6px;"><?= e($siapa) ?></span></div>
              <div class="langkah-sub"><?= e($ket) ?></div>
              <?php if ($href && $r === 'mahasiswa'): ?>
                <a class="link fs-13" href="<?= BASE_URL . '/' . $href ?>">Buka halaman</a>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ol>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><div class="card-title">Cara memakai website</div></div>
    <div class="card-body">
      <?php foreach ($caraPakai as $kunci => [$judulPeran, $item]): ?>
        <details class="lipat" <?= $kunci === $r ? 'open' : '' ?>>
          <summary>Untuk <?= e($judulPeran) ?><?= $kunci === $r ? ' (Anda)' : '' ?></summary>
          <div class="lipat-isi">
            <?php foreach ($item as [$t, $isi]): ?>
              <div class="tanya-jawab"><div class="fw-500"><?= e($t) ?></div><p><?= e($isi) ?></p></div>
            <?php endforeach; ?>
          </div>
        </details>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
