<?php
// admin/laporan.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('admin');

$pageTitle  = 'Laporan & Aktivitas';
$activePage = 'laporan';

// 1. AMBIL DATA STATISTIK UTAMA
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

// 2. HANDLE EXPORT LAPORAN (PDF & EXCEL)
if (isset($_GET['export'])) {
    $format = $_GET['export'];
    $filename = "Rekap_Bimbingan_" . date('Ymd_Hi');

    if ($format === 'excel') {
        // Output sebagai CSV untuk Excel
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputs($output, "\xEF\xBB\xBF"); // Tambahkan BOM agar Excel membaca UTF-8 dengan benar
        
        // Header Kolom
        fputcsv($output, ['Nama Dosen', 'Jumlah Mahasiswa', 'Total Sesi', 'Sesi Selesai', 'Rata-rata Durasi (Menit)']);
        
        // Data Rows
        foreach ($rekapDosen as $r) {
            fputcsv($output, [
                $r['nama_lengkap'], 
                $r['jml_mhs'], 
                $r['total_sesi'], 
                $r['selesai'] ?? 0, 
                $r['avg_durasi'] ?? 0
            ]);
        }
        fclose($output);
        exit;
        
    } elseif ($format === 'pdf') {
        // Output sebagai halaman cetak PDF
        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <title>Laporan Bimbingan Skripsi</title>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; color: #333; margin: 0; padding: 40px; }
                .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 20px; margin-bottom: 30px; }
                h1 { margin: 0 0 10px 0; font-size: 24px; }
                p { margin: 0; font-size: 14px; color: #555; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
                th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
                th { background-color: #f4f4f4; font-weight: 600; }
                @media print {
                    @page { margin: 1.5cm; }
                    body { padding: 0; }
                }
            </style>
        </head>
        <body onload="window.print()">
            <div class="header">
                <h1>Laporan Rekapitulasi Bimbingan Dosen</h1>
                <p>Dicetak pada: <?= date('d F Y H:i') ?></p>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Nama Dosen Pembimbing</th>
                        <th>Jumlah Mahasiswa</th>
                        <th>Total Sesi</th>
                        <th>Sesi Selesai</th>
                        <th>Rata-rata Durasi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rekapDosen as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['nama_lengkap']) ?></td>
                        <td><?= $r['jml_mhs'] ?> Mahasiswa</td>
                        <td><?= $r['total_sesi'] ?> Sesi</td>
                        <td><?= $r['selesai'] ?? 0 ?> Sesi</td>
                        <td><?= $r['avg_durasi'] ?? 0 ?> Menit</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </body>
        </html>
        <?php
        exit;
    }
}

// 3. AMBIL DATA LAINNYA UNTUK VIEW
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
  <div class="page-header" style="display:flex; justify-content:space-between; align-items:flex-end;">
    <div>
        <h1>Laporan & Aktivitas</h1>
        <p>Pantau statistik dan aktivitas sistem secara keseluruhan — <?= date('d F Y') ?></p>
    </div>
    
    <div style="position: relative; display: inline-block;">
        <button class="btn btn-primary" onclick="toggleExportMenu()" style="display:flex; gap:8px; align-items:center;">
            📥 Buat Laporan ▼
        </button>
        <div id="exportMenu" style="display:none; position:absolute; right:0; top:100%; margin-top:8px; background:var(--white); min-width:180px; box-shadow:var(--shadow-lg); border-radius:var(--radius-sm); border:1px solid var(--cream-dark); overflow:hidden; z-index:100;">
            <a href="?export=pdf" target="_blank" style="display:block; padding:12px 16px; color:var(--navy); text-decoration:none; font-size:13px; border-bottom:1px solid var(--cream-dark); transition:background 0.2s;">
                📄 Cetak Laporan (PDF)
            </a>
            <a href="?export=excel" style="display:block; padding:12px 16px; color:var(--navy); text-decoration:none; font-size:13px; transition:background 0.2s;">
                📊 Unduh Data (Excel/CSV)
            </a>
        </div>
    </div>
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

<script>
// Fungsi toggle dropdown export
function toggleExportMenu() {
    const menu = document.getElementById('exportMenu');
    menu.style.display = (menu.style.display === 'none' || menu.style.display === '') ? 'block' : 'none';
}

// Menutup menu jika diklik di luar kotak
window.onclick = function(event) {
    if (!event.target.matches('.btn-primary') && !event.target.closest('.btn-primary')) {
        const menu = document.getElementById('exportMenu');
        if (menu && menu.style.display === 'block') {
            menu.style.display = 'none';
        }
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>