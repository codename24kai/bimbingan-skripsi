<?php
// admin/laporan.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('admin');

$pageTitle  = 'Laporan & Aktivitas';
$activePage = 'laporan';

$totalSesi    = countRows('SELECT COUNT(*) FROM sesi_bimbingan WHERE status="selesai"');
$totalSelesai = countRows('SELECT COUNT(*) FROM mahasiswa WHERE status_skripsi="selesai"');
$avgSesi      = fetchOne('SELECT ROUND(AVG(total_sesi),1) AS avg FROM bimbingan WHERE status="selesai"')['avg'] ?? 0;
$terlambat    = countRows('SELECT COUNT(*) FROM mahasiswa m WHERE status_skripsi="proses"
                           AND NOT EXISTS (SELECT 1 FROM sesi_bimbingan sb JOIN bimbingan b ON b.id_bimbingan=sb.id_bimbingan
                                          WHERE b.id_mahasiswa=m.id_mahasiswa AND sb.tgl_sesi >= DATE_SUB(CURDATE(),INTERVAL 30 DAY))');

$rekapDosen = fetchAll(
    'SELECT ud.nama_lengkap,
            COUNT(DISTINCT b.id_mahasiswa) AS jml_mhs,
            COUNT(sb.id_sesi) AS total_sesi,
            SUM(sb.status="selesai") AS selesai,
            ROUND(AVG(sb.durasi_menit),0) AS avg_durasi
     FROM dosen d JOIN user ud ON ud.id_user=d.id_user
     LEFT JOIN bimbingan b ON b.id_dosen=d.id_dosen
     LEFT JOIN sesi_bimbingan sb ON sb.id_bimbingan=b.id_bimbingan
     GROUP BY d.id_dosen ORDER BY total_sesi DESC'
);

$distribusiJudul = fetchAll('SELECT status, COUNT(*) AS n FROM judul_skripsi GROUP BY status');

$logAktivitas = fetchAll(
    'SELECT n.created_at,u.nama_lengkap,u.role,n.judul,n.tipe,n.modul
     FROM notifikasi n JOIN user u ON u.id_user=n.id_user
     ORDER BY n.created_at DESC LIMIT 20'
);

$mhsPendingRevisi = fetchAll(
    'SELECT m.nim,u.nama_lengkap,COUNT(r.id_revisi) AS total_pending
     FROM revisi r JOIN dokumen d ON d.id_dokumen=r.id_dokumen
     JOIN mahasiswa m ON m.id_mahasiswa=d.id_mahasiswa JOIN user u ON u.id_user=m.id_user
     WHERE r.status="belum" GROUP BY m.id_mahasiswa ORDER BY total_pending DESC LIMIT 5'
);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">
  <div class="page-header">
    <h1>Laporan & Aktivitas</h1>
    <p>Pantau statistik dan aktivitas sistem secara keseluruhan — <?= date('d F Y') ?></p>
  </div>

  <div class="stats-grid">
    <div class="stat-card blue"><div class="stat-icon">📊</div><div class="stat-num"><?= $totalSesi ?></div><div class="stat-label">Total Sesi Selesai</div></div>
    <div class="stat-card green"><div class="stat-icon">🎓</div><div class="stat-num"><?= $totalSelesai ?></div><div class="stat-label">Skripsi Selesai</div></div>
    <div class="stat-card gold"><div class="stat-icon">⏱️</div><div class="stat-num"><?= $avgSesi ?></div><div class="stat-label">Rata-rata Sesi/Skripsi</div></div>
    <div class="stat-card red"><div class="stat-icon">⚠️</div><div class="stat-num"><?= $terlambat ?></div><div class="stat-label">Mahasiswa Tidak Aktif 30hr</div></div>
  </div>

  <div class="grid-2" style="margin-bottom:20px;">
    <div class="card">
      <div class="card-header"><div class="card-title">Rekap Bimbingan per Dosen</div></div>
      <div class="card-body">
        <div class="table-wrap">
          <table>
            <thead><tr><th>Dosen</th><th>Mhs</th><th>Total Sesi</th><th>Selesai</th><th>Avg (menit)</th></tr></thead>
            <tbody>
              <?php foreach ($rekapDosen as $r): ?>
                <tr>
                  <td style="font-weight:500;"><?= e($r['nama_lengkap']) ?></td>
                  <td><?= $r['jml_mhs'] ?></td>
                  <td><?= $r['total_sesi'] ?></td>
                  <td><?= $r['selesai'] ?></td>
                  <td><?= $r['avg_durasi'] ?? '—' ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div>
      <div class="card" style="margin-bottom:16px;">
        <div class="card-header"><div class="card-title">Status Judul Skripsi</div></div>
        <div class="card-body">
          <?php $total = array_sum(array_column($distribusiJudul,'n')); ?>
          <?php foreach ($distribusiJudul as $d): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid var(--cream-dark);">
              <span class="badge <?= badge($d['status']) ?>"><span class="badge-dot"></span><?= $d['status'] ?></span>
              <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:80px;"><div class="progress-bar"><div class="progress-fill blue" style="width:<?= $total > 0 ? round($d['n']/$total*100) : 0 ?>%"></div></div></div>
                <strong><?= $d['n'] ?></strong>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><div class="card-title">Revisi Pending Terbanyak</div></div>
        <div class="card-body">
          <?php foreach ($mhsPendingRevisi as $m): ?>
            <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--cream-dark);">
              <div>
                <div style="font-size:14px;font-weight:500;"><?= e($m['nama_lengkap']) ?></div>
                <div style="font-size:12px;color:var(--slate);"><?= e($m['nim']) ?></div>
              </div>
              <span class="badge danger" style="height:fit-content;"><span class="badge-dot"></span><?= $m['total_pending'] ?> pending</span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><div class="card-title">Log Aktivitas Sistem</div></div>
    <div class="card-body">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Waktu</th><th>Pengguna</th><th>Role</th><th>Judul</th><th>Tipe</th><th>Modul</th></tr></thead>
          <tbody>
            <?php foreach ($logAktivitas as $log): ?>
              <tr>
                <td class="mono" style="font-size:12px;"><?= date('d M H:i', strtotime($log['created_at'])) ?></td>
                <td style="font-weight:500;"><?= e($log['nama_lengkap']) ?></td>
                <td><span class="badge neutral"><?= $log['role'] ?></span></td>
                <td style="font-size:13px;"><?= e($log['judul']) ?></td>
                <td><span class="badge <?= badge($log['tipe']) ?>"><span class="badge-dot"></span><?= $log['tipe'] ?></span></td>
                <td style="font-size:12px;color:var(--slate);"><?= e($log['modul'] ?? '—') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
