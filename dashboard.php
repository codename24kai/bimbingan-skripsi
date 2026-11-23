<?php
// dashboard.php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
requireLogin();

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
$role       = role();
$uid        = userId();
$eid        = entityId();

/* ── DATA PER ROLE ─────────────────────────── */
if ($role === 'mahasiswa') {
    $mhs = fetchOne('SELECT m.*,ps.nama_prodi FROM mahasiswa m
                     JOIN program_studi ps ON ps.id_prodi=m.id_prodi
                     WHERE m.id_mahasiswa=?', [$eid]);

    $bimbingan = fetchOne('SELECT b.*,ud.nama_lengkap AS nama_dosen,
                            j.judul_indonesia, j.status AS status_judul
                            FROM bimbingan b
                            JOIN dosen d ON d.id_dosen=b.id_dosen
                            JOIN user ud ON ud.id_user=d.id_user
                            JOIN judul_skripsi j ON j.id_judul=b.id_judul
                            WHERE b.id_mahasiswa=? AND b.status="aktif"
                            ORDER BY b.id_bimbingan DESC LIMIT 1', [$eid]);

    $totalSesi   = $bimbingan['total_sesi'] ?? 0;
    $totalRevisi = countRows('SELECT COUNT(*) FROM revisi r
                              JOIN dokumen d ON d.id_dokumen=r.id_dokumen
                              WHERE d.id_mahasiswa=? AND r.status="belum"', [$eid]);

    $nextSesi = fetchOne('SELECT sb.*,b.id_bimbingan FROM sesi_bimbingan sb
                          JOIN bimbingan b ON b.id_bimbingan=sb.id_bimbingan
                          WHERE b.id_mahasiswa=? AND sb.status="dijadwalkan"
                          AND sb.tgl_sesi >= CURDATE()
                          ORDER BY sb.tgl_sesi,sb.waktu_mulai LIMIT 1', [$eid]);

    $timeline = fetchAll('SELECT sb.tgl_sesi,sb.topik,sb.status AS status_sesi,
                           cb.keputusan,cb.bab_terkait
                           FROM sesi_bimbingan sb
                           JOIN bimbingan b ON b.id_bimbingan=sb.id_bimbingan
                           LEFT JOIN catatan_bimbingan cb ON cb.id_sesi=sb.id_sesi
                           WHERE b.id_mahasiswa=?
                           ORDER BY sb.tgl_sesi DESC LIMIT 5', [$eid]);

    $dokumen = fetchAll('SELECT d.jenis_bab,d.status,COUNT(r.id_revisi) AS jml_revisi
                         FROM dokumen d
                         LEFT JOIN revisi r ON r.id_dokumen=d.id_dokumen AND r.status="belum"
                         WHERE d.id_mahasiswa=?
                         GROUP BY d.id_dokumen ORDER BY d.jenis_bab', [$eid]);

} elseif ($role === 'dosen') {
    $dosen = fetchOne('SELECT d.*,ps.nama_prodi FROM dosen d
                       JOIN program_studi ps ON ps.id_prodi=d.id_prodi
                       WHERE d.id_dosen=?', [$eid]);

    $jumlahMhs     = countRows('SELECT COUNT(*) FROM bimbingan WHERE id_dosen=? AND status="aktif"',[$eid]);
    $judulPending  = countRows('SELECT COUNT(*) FROM judul_skripsi j
                                JOIN bimbingan b ON b.id_mahasiswa=j.id_mahasiswa
                                WHERE b.id_dosen=? AND j.status="diajukan"',[$eid]);
    $sesiMingguIni = countRows('SELECT COUNT(*) FROM sesi_bimbingan sb
                                JOIN bimbingan b ON b.id_bimbingan=sb.id_bimbingan
                                WHERE b.id_dosen=? AND sb.status="dijadwalkan"
                                AND sb.tgl_sesi BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY)',[$eid]);
    $revisiBlm     = countRows('SELECT COUNT(*) FROM revisi WHERE id_dosen=? AND status="belum"',[$eid]);

    $listMhs = fetchAll('SELECT m.nim,um.nama_lengkap,j.judul_indonesia,b.total_sesi,m.status_skripsi
                         FROM bimbingan b
                         JOIN mahasiswa m ON m.id_mahasiswa=b.id_mahasiswa
                         JOIN user um ON um.id_user=m.id_user
                         JOIN judul_skripsi j ON j.id_judul=b.id_judul
                         WHERE b.id_dosen=? AND b.status="aktif"
                         ORDER BY um.nama_lengkap', [$eid]);

    $jadwalMendatang = fetchAll('SELECT sb.tgl_sesi,sb.waktu_mulai,sb.topik,sb.metode,
                                  um.nama_lengkap AS nama_mhs
                                  FROM sesi_bimbingan sb
                                  JOIN bimbingan b ON b.id_bimbingan=sb.id_bimbingan
                                  JOIN mahasiswa m ON m.id_mahasiswa=b.id_mahasiswa
                                  JOIN user um ON um.id_user=m.id_user
                                  WHERE b.id_dosen=? AND sb.status="dijadwalkan"
                                  AND sb.tgl_sesi >= CURDATE()
                                  ORDER BY sb.tgl_sesi,sb.waktu_mulai LIMIT 5', [$eid]);

} elseif ($role === 'admin') {
    $totalMhs   = countRows('SELECT COUNT(*) FROM mahasiswa');
    $totalDosen = countRows('SELECT COUNT(*) FROM dosen');
    $aktifSkrpsi = countRows('SELECT COUNT(*) FROM mahasiswa WHERE status_skripsi="proses"');
    $pending    = countRows('SELECT COUNT(*) FROM user WHERE status="nonaktif"');

    $statsProdi = fetchAll('SELECT ps.nama_prodi,
                             COUNT(DISTINCT m.id_mahasiswa) AS total_mhs,
                             SUM(m.status_skripsi="proses") AS aktif,
                             SUM(m.status_skripsi="selesai") AS selesai
                             FROM program_studi ps
                             LEFT JOIN mahasiswa m ON m.id_prodi=ps.id_prodi
                             GROUP BY ps.id_prodi ORDER BY ps.nama_prodi');

    $logAktivitas = fetchAll('SELECT n.created_at,u.nama_lengkap,u.role,n.judul,n.tipe
                              FROM notifikasi n JOIN user u ON u.id_user=n.id_user
                              ORDER BY n.created_at DESC LIMIT 8');
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="layout">
  <main class="main">
    <?php include __DIR__ . '/includes/topbar.php'; ?>
    <div class="content">

      <?php $flash = getFlash(); if ($flash): ?>
        <div class="alert <?= $flash['type'] ?>"><?= e($flash['msg']) ?></div>
      <?php endif; ?>

      <!-- ════════════ MAHASISWA DASHBOARD ════════════ -->
      <?php if ($role === 'mahasiswa'): ?>

        <div class="page-header">
          <h1>Selamat datang, <?= e(explode(' ', currentUser()['nama_lengkap'])[0]) ?>! 👋</h1>
          <p><?= date('l, d F Y') ?> — Pantau progres skripsi Anda hari ini</p>
        </div>

        <div class="stats-grid">
          <div class="stat-card blue">
            <div class="stat-icon">📝</div>
            <div class="stat-num"><?= $totalSesi ?></div>
            <div class="stat-label">Total Sesi Bimbingan</div>
          </div>
          <div class="stat-card green">
            <div class="stat-icon">✅</div>
            <div class="stat-num"><?= countRows('SELECT COUNT(*) FROM dokumen WHERE id_mahasiswa=? AND status="disetujui"',[$eid]) ?></div>
            <div class="stat-label">Dokumen Disetujui</div>
          </div>
          <div class="stat-card gold">
            <div class="stat-icon">📄</div>
            <div class="stat-num"><?= $totalRevisi ?></div>
            <div class="stat-label">Revisi Pending</div>
            <?php if ($totalRevisi > 0): ?>
              <div class="stat-change warn">⏳ Perlu ditindaklanjuti</div>
            <?php endif; ?>
          </div>
          <div class="stat-card red">
            <div class="stat-icon">🗓️</div>
            <div class="stat-num"><?= $nextSesi ? date('d', strtotime($nextSesi['tgl_sesi'])) : '—' ?></div>
            <div class="stat-label">Hari Bimbingan Berikutnya</div>
            <?php if ($nextSesi): ?>
              <div class="stat-change warn">📅 <?= date('d M', strtotime($nextSesi['tgl_sesi'])) ?></div>
            <?php endif; ?>
          </div>
        </div>

        <div class="grid-3" style="margin-bottom:20px;">
          <div class="card">
            <div class="card-header">
              <div class="card-title">Dokumen per Bab</div>
            </div>
            <div class="card-body">
              <?php if (empty($dokumen)): ?>
                <div class="empty-state"><div class="empty-sub">Belum ada dokumen diunggah.</div></div>
              <?php else: ?>
                <?php foreach ($dokumen as $d): ?>
                  <div class="progress-item">
                    <div class="progress-top">
                      <div class="progress-name"><?= e($d['jenis_bab']) ?></div>
                      <span class="badge <?= badge($d['status']) ?>">
                        <span class="badge-dot"></span><?= e($d['status']) ?>
                      </span>
                    </div>
                    <?php if ($d['jml_revisi'] > 0): ?>
                      <div class="progress-sub" style="color:var(--warn-text);">⚠ <?= $d['jml_revisi'] ?> revisi belum diselesaikan</div>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

          <div class="card">
            <div class="card-header"><div class="card-title">Aktivitas Terkini</div></div>
            <div class="card-body">
              <div class="timeline">
                <?php foreach ($timeline as $t):
                  $dot = $t['keputusan']==='disetujui' ? 'green' : ($t['keputusan']==='revisi' ? 'gold' : 'blue');
                  $icon = $t['keputusan']==='disetujui' ? '✅' : ($t['keputusan']==='revisi' ? '📝' : '🗓️');
                ?>
                  <div class="timeline-item">
                    <div class="timeline-dot <?= $dot ?>"><?= $icon ?></div>
                    <div class="timeline-body">
                      <div class="timeline-title"><?= e($t['topik']) ?></div>
                      <?php if ($t['bab_terkait']): ?>
                        <div class="timeline-sub"><?= e($t['bab_terkait']) ?></div>
                      <?php endif; ?>
                      <div class="timeline-time"><?= date('d M Y', strtotime($t['tgl_sesi'])) ?></div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>

        <?php if ($bimbingan): ?>
        <div class="card">
          <div class="card-header">
            <div class="card-title">Info Skripsi Aktif</div>
            <span class="badge <?= badge($bimbingan['status_judul']) ?>">
              <span class="badge-dot"></span><?= e($bimbingan['status_judul']) ?>
            </span>
          </div>
          <div class="card-body">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(200px,100%),1fr));gap:20px;padding-top:8px;">
              <?php foreach ([
                ['Judul', $bimbingan['judul_indonesia']],
                ['Dosen Pembimbing', $bimbingan['nama_dosen']],
                ['Program Studi', $mhs['nama_prodi']],
                ['Status Skripsi', ucfirst($mhs['status_skripsi'])],
              ] as [$k,$v]): ?>
                <div>
                  <div style="font-size:11px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--slate);margin-bottom:6px;"><?= $k ?></div>
                  <div style="font-size:14px;color:var(--navy);font-weight:500;"><?= e($v) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <?php endif; ?>

      <!-- ════════════ DOSEN DASHBOARD ════════════ -->
      <?php elseif ($role === 'dosen'): ?>

        <div class="page-header">
          <h1>Dashboard Dosen 📚</h1>
          <p>Pantau seluruh mahasiswa bimbingan Anda — <?= date('d F Y') ?></p>
        </div>

        <div class="stats-grid">
          <div class="stat-card blue"><div class="stat-icon">🎒</div><div class="stat-num"><?= $jumlahMhs ?></div><div class="stat-label">Mahasiswa Bimbingan</div></div>
          <div class="stat-card gold"><div class="stat-icon">⏳</div><div class="stat-num"><?= $judulPending ?></div><div class="stat-label">Judul Menunggu Validasi</div></div>
          <div class="stat-card green"><div class="stat-icon">✅</div><div class="stat-num"><?= $sesiMingguIni ?></div><div class="stat-label">Sesi Minggu Ini</div></div>
          <div class="stat-card red"><div class="stat-icon">📄</div><div class="stat-num"><?= $revisiBlm ?></div><div class="stat-label">Revisi Belum Dibalas</div></div>
        </div>

        <div class="grid-2">
          <div class="card">
            <div class="card-header"><div class="card-title">Mahasiswa Aktif</div></div>
            <div class="card-body">
              <div class="table-wrap">
                <table>
                  <thead><tr><th>Mahasiswa</th><th>NIM</th><th>Sesi</th><th>Status</th></tr></thead>
                  <tbody>
                    <?php foreach ($listMhs as $m): ?>
                      <tr>
                        <td style="font-weight:500;"><?= e($m['nama_lengkap']) ?></td>
                        <td class="mono" style="font-size:12px;"><?= e($m['nim']) ?></td>
                        <td><?= $m['total_sesi'] ?></td>
                        <td><span class="badge <?= badge($m['status_skripsi']) ?>"><span class="badge-dot"></span><?= $m['status_skripsi'] ?></span></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card-header"><div class="card-title">Jadwal Mendatang</div></div>
            <div class="card-body">
              <div class="timeline">
                <?php foreach ($jadwalMendatang as $j): ?>
                  <div class="timeline-item">
                    <div class="timeline-dot blue">🗓️</div>
                    <div class="timeline-body">
                      <div class="timeline-title"><?= e($j['nama_mhs']) ?></div>
                      <div class="timeline-sub"><?= e($j['topik']) ?></div>
                      <div class="timeline-time"><?= date('d M Y H:i', strtotime($j['tgl_sesi'].' '.$j['waktu_mulai'])) ?> — <?= e($j['metode']) ?></div>
                    </div>
                  </div>
                <?php endforeach; ?>
                <?php if (empty($jadwalMendatang)): ?>
                  <div class="empty-state"><div class="empty-sub">Tidak ada jadwal mendatang.</div></div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

      <!-- ════════════ ADMIN DASHBOARD ════════════ -->
      <?php elseif ($role === 'admin'): ?>

        <div class="page-header">
          <h1>Dashboard Administrator ⚙️</h1>
          <p>Ringkasan aktivitas sistem portal bimbingan skripsi</p>
        </div>

        <div class="stats-grid">
          <div class="stat-card blue"><div class="stat-icon">🎒</div><div class="stat-num"><?= $totalMhs ?></div><div class="stat-label">Total Mahasiswa</div></div>
          <div class="stat-card green"><div class="stat-icon">👨‍🏫</div><div class="stat-num"><?= $totalDosen ?></div><div class="stat-label">Dosen Pembimbing</div></div>
          <div class="stat-card gold"><div class="stat-icon">📋</div><div class="stat-num"><?= $aktifSkrpsi ?></div><div class="stat-label">Skripsi Aktif</div></div>
          <div class="stat-card red"><div class="stat-icon">⏳</div><div class="stat-num"><?= $pending ?></div><div class="stat-label">Akun Non-aktif</div></div>
        </div>

        <div class="grid-2" style="margin-bottom:20px;">
          <div class="card">
            <div class="card-header"><div class="card-title">Statistik per Program Studi</div></div>
            <div class="card-body">
              <div class="table-wrap">
                <table>
                  <thead><tr><th>Program Studi</th><th>Mahasiswa</th><th>Aktif</th><th>Selesai</th></tr></thead>
                  <tbody>
                    <?php foreach ($statsProdi as $p): ?>
                      <tr>
                        <td style="font-weight:500;"><?= e($p['nama_prodi']) ?></td>
                        <td><?= $p['total_mhs'] ?></td>
                        <td><?= $p['aktif'] ?></td>
                        <td><?= $p['selesai'] ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card-header"><div class="card-title">Log Aktivitas Terbaru</div></div>
            <div class="card-body">
              <div class="timeline">
                <?php foreach ($logAktivitas as $log): ?>
                  <div class="timeline-item">
                    <div class="timeline-dot <?= $log['tipe']==='sukses'?'green':($log['tipe']==='peringatan'?'gold':'blue') ?>">
                      <?= $log['tipe']==='sukses'?'✅':($log['tipe']==='peringatan'?'⚠️':'ℹ️') ?>
                    </div>
                    <div class="timeline-body">
                      <div class="timeline-title"><?= e($log['judul']) ?></div>
                      <div class="timeline-sub"><?= e($log['nama_lengkap']) ?> · <?= e($log['role']) ?></div>
                      <div class="timeline-time"><?= date('d M H:i', strtotime($log['created_at'])) ?></div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>

      <?php endif; ?>

    </div><!-- /.content -->
  </main>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
