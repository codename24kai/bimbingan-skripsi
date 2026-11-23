<?php
// beranda.php — Halaman publik sebelum login (S6): tentang SkripsiKu, alur singkat, pengumuman umum
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

$institusi = pengaturan('nama_institusi', '');
$statistik = [
    ['Mahasiswa terdaftar', countRows('SELECT COUNT(*) FROM mahasiswa')],
    ['Dosen pembimbing', countRows('SELECT COUNT(*) FROM dosen')],
    ['Skripsi selesai', countRows('SELECT COUNT(*) FROM mahasiswa WHERE status_skripsi="selesai"')],
];
// Hanya pengumuman umum (untuk semua, semua prodi) yang tampil sebelum login
$pengumuman = tabelAda('pengumuman') ? fetchAll(
    'SELECT judul, isi, created_at FROM pengumuman WHERE target_role="semua" AND id_prodi IS NULL
     AND (berlaku_sampai IS NULL OR berlaku_sampai >= CURDATE()) ORDER BY disematkan DESC, created_at DESC LIMIT 3'
) : [];
$agenda = tabelAda('agenda_akademik') ? fetchAll(
    'SELECT * FROM agenda_akademik WHERE COALESCE(tgl_selesai, tgl_mulai) >= CURDATE() ORDER BY tgl_mulai LIMIT 4'
) : [];
$alur = [
    ['Ajukan judul', 'Satu judul utama dan dua alternatif, sistem mengecek kemiripan.'],
    ['Dapat pembimbing', 'Pilih dosen sesuai keahlian, admin menerbitkan SK.'],
    ['Bimbingan & revisi', 'Pesan slot konsultasi, unggah bab, diskusikan revisi.'],
    ['Seminar & sidang', 'Daftar online, lihat jadwal, penguji, dan hasil.'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SkripsiKu — Portal Bimbingan Skripsi</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=<?= @filemtime(__DIR__ . '/assets/style.css') ?>">
</head>
<body class="beranda">
<header class="beranda-nav">
  <div class="sidebar-logo">Skripsi<span>Ku</span></div>
  <nav class="row-10">
    <a class="btn btn-secondary btn-sm" href="login-staff.php">Masuk Dosen / Admin</a>
    <a class="btn btn-gold btn-sm" href="index.php">Masuk Mahasiswa</a>
  </nav>
</header>

<section class="beranda-hero">
  <div class="beranda-hero__isi">
    <h1>Bimbingan skripsi yang <em>terstruktur</em> dan transparan</h1>
    <p>Dari pengajuan judul sampai sidang akhir, semua tercatat di satu tempat<?= $institusi ? ' untuk ' . e($institusi) : '' ?>.</p>
    <div class="row-10" style="flex-wrap:wrap;margin-top:22px;">
      <a class="btn btn-gold" href="index.php">Masuk sebagai mahasiswa</a>
      <a class="btn btn-secondary" href="#alur">Lihat alurnya</a>
    </div>
  </div>
  <dl class="beranda-angka">
    <?php foreach ($statistik as [$k, $v]): ?><div><dt><?= e($k) ?></dt><dd><?= (int)$v ?></dd></div><?php endforeach; ?>
  </dl>
</section>

<main class="beranda-isi">
  <section id="alur">
    <h2>Alur singkat</h2>
    <ol class="beranda-alur">
      <?php foreach ($alur as $i => [$t, $k]): ?>
        <li><span class="langkah-no"><?= $i + 1 ?></span><div><b><?= e($t) ?></b><p><?= e($k) ?></p></div></li>
      <?php endforeach; ?>
    </ol>
  </section>

  <div class="grid-2">
    <section class="card">
      <div class="card-header"><h2 class="card-title">Pengumuman</h2></div>
      <div class="card-body">
        <?php if (!$pengumuman): ?><div class="empty-state"><div class="empty-sub">Belum ada pengumuman umum.</div></div><?php endif; ?>
        <?php foreach ($pengumuman as $p): ?>
          <article class="agenda-baris"><div class="min-w-0"><div class="fw-500"><?= e($p['judul']) ?></div>
            <div class="meta"><?= tgl($p['created_at']) ?> · <?= e(mb_strimwidth($p['isi'], 0, 140, '…')) ?></div></div></article>
        <?php endforeach; ?>
      </div>
    </section>
    <section class="card">
      <div class="card-header"><h2 class="card-title">Tanggal penting</h2></div>
      <div class="card-body">
        <?php if (!$agenda): ?><div class="empty-state"><div class="empty-sub">Belum ada agenda akademik.</div></div><?php endif; ?>
        <?php foreach ($agenda as $a): ?>
          <div class="agenda-baris"><i class="titik titik--agenda"></i><div class="min-w-0"><div class="fw-500"><?= e($a['nama']) ?></div>
            <div class="meta"><?= tgl($a['tgl_mulai']) ?><?= $a['tgl_selesai'] && $a['tgl_selesai'] !== $a['tgl_mulai'] ? ' – ' . tgl($a['tgl_selesai']) : '' ?></div></div></div>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
</main>
<footer class="beranda-kaki">© <?= date('Y') ?> SkripsiKu<?= $institusi ? ' · ' . e($institusi) : '' ?> · <a class="link" href="lupa-sandi.php">Lupa kata sandi</a></footer>
</body>
</html>
