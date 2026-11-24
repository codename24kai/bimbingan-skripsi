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

// Pengingat otomatis (H-1 jadwal, mahasiswa tidak aktif). Maksimal sekali per jam.
jalankanPengingat();
$pengumuman = pengumumanUntukSaya(3);

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
                          WHERE b.id_mahasiswa=? AND sb.status IN ("dijadwalkan","dikonfirmasi")
                          AND sb.tgl_sesi >= CURDATE()
                          ORDER BY sb.tgl_sesi,sb.waktu_mulai LIMIT 1', [$eid]);

    $timeline = fetchAll('SELECT sb.tgl_sesi,sb.topik,sb.status AS status_sesi,
                           cb.keputusan,cb.bab_terkait
                           FROM sesi_bimbingan sb
                           JOIN bimbingan b ON b.id_bimbingan=sb.id_bimbingan
                           LEFT JOIN catatan_bimbingan cb ON cb.id_sesi=sb.id_sesi
                           WHERE b.id_mahasiswa=?
                           ORDER BY sb.tgl_sesi DESC LIMIT 5', [$eid]);

    // Langkah skripsi untuk mahasiswa yang belum punya pembimbing aktif
    $judulTerakhir = fetchOne('SELECT status, usulan_dosen FROM judul_skripsi
                               WHERE id_mahasiswa=? ORDER BY id_judul DESC LIMIT 1', [$eid]);
    $stJudul  = $judulTerakhir['status'] ?? null;
    $langkah = [
        ['Ajukan judul skripsi', in_array($stJudul, ['diajukan','revisi','disetujui'], true),
         'mahasiswa/pengajuan.php', $stJudul === 'revisi' ? 'Judul Anda perlu direvisi. Perbaiki lalu ajukan ulang.' : ($stJudul === 'ditolak' ? 'Judul sebelumnya ditolak. Ajukan judul baru.' : 'Isi form pengajuan dengan 3 opsi judul.')],
        ['Judul disetujui', $stJudul === 'disetujui', null, 'Dosen dan admin akan memeriksa judul Anda.'],
        ['Usulkan dosen pembimbing', $stJudul === 'disetujui' && !empty($judulTerakhir['usulan_dosen']),
         'mahasiswa/pengajuan_dosen.php', 'Pilih dosen yang sesuai dengan bidang judul Anda.'],
        ['SK bimbingan terbit', (bool)$bimbingan, null, 'Admin menerbitkan SK dan menu Dokumen, Jadwal, Riwayat akan terbuka.'],
    ];
    $langkahAktif = 0;
    foreach ($langkah as $i => $l) { if (!$l[1]) { $langkahAktif = $i; break; } $langkahAktif = $i + 1; }

    $progres = progresSkripsi($eid);

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
                                WHERE b.id_dosen=? AND sb.status IN ("dijadwalkan","dikonfirmasi")
                                AND sb.tgl_sesi BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY)',[$eid]);
    $revisiBlm     = countRows('SELECT COUNT(*) FROM revisi WHERE id_dosen=? AND status="belum"',[$eid]);

    $listMhs = fetchAll('SELECT m.nim,um.nama_lengkap,j.judul_indonesia,b.total_sesi,m.status_skripsi
                         FROM bimbingan b
                         JOIN mahasiswa m ON m.id_mahasiswa=b.id_mahasiswa
                         JOIN user um ON um.id_user=m.id_user
                         JOIN judul_skripsi j ON j.id_judul=b.id_judul
                         WHERE b.id_dosen=? AND b.status="aktif"
                         ORDER BY um.nama_lengkap', [$eid]);

    $jadwalMendatang = fetchAll('SELECT sb.tgl_sesi,sb.waktu_mulai,sb.topik,sb.metode,sb.status,
                                  um.nama_lengkap AS nama_mhs
                                  FROM sesi_bimbingan sb
                                  JOIN bimbingan b ON b.id_bimbingan=sb.id_bimbingan
                                  JOIN mahasiswa m ON m.id_mahasiswa=b.id_mahasiswa
                                  JOIN user um ON um.id_user=m.id_user
                                  WHERE b.id_dosen=? AND sb.status IN ("dijadwalkan","dikonfirmasi")
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

    // Grafik: pengajuan judul 6 bulan terakhir & beban bimbingan per dosen (D14)
    $perBulan = [];
    for ($i = 5; $i >= 0; $i--) $perBulan[date('Y-m', strtotime("first day of -$i month"))] = 0;
    foreach (fetchAll('SELECT DATE_FORMAT(tgl_pengajuan, "%Y-%m") AS bln, COUNT(*) AS n FROM judul_skripsi
                       WHERE tgl_pengajuan >= ? GROUP BY bln', [array_key_first($perBulan) . '-01']) as $r) {
        if (isset($perBulan[$r['bln']])) $perBulan[$r['bln']] = (int)$r['n'];
    }
    $bebanDosen = fetchAll('SELECT u.nama_lengkap, d.kuota_bimbingan,
                              (SELECT COUNT(*) FROM bimbingan b WHERE b.id_dosen=d.id_dosen AND b.status="aktif") AS aktif
                            FROM dosen d JOIN user u ON u.id_user=d.id_user ORDER BY aktif DESC, u.nama_lengkap LIMIT 8');
    $ujianMenunggu = countRows('SELECT COUNT(*) FROM ujian WHERE status="disetujui_pembimbing"');
    $resetMenunggu = countRows('SELECT COUNT(*) FROM permintaan_reset WHERE status="menunggu"');

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

      <?= tampilFlash() ?>

      <?php if (!empty($pengumuman)): ?>
        <section class="card pengumuman-ringkas mb-20" aria-label="Pengumuman">
          <div class="card-header">
            <div class="card-title"><?= icon('megaphone', 18) ?> Pengumuman</div>
            <a class="link fs-13" href="<?= BASE_URL ?>/info/pengumuman.php">Lihat semua</a>
          </div>
          <div class="card-body">
            <?php foreach ($pengumuman as $p): ?>
              <a class="pengumuman-baris" href="<?= BASE_URL ?>/info/pengumuman.php#p<?= (int)$p['id_pengumuman'] ?>">
                <?php if ($p['disematkan']): ?><span class="badge warning"><span class="badge-dot"></span>Penting</span><?php endif; ?>
                <span class="fw-500"><?= e($p['judul']) ?></span>
                <span class="meta"><?= tgl($p['created_at'], 'tglbln') ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>

      <!-- ════════════ MAHASISWA DASHBOARD ════════════ -->
      <?php if ($role === 'mahasiswa'): ?>

        <div class="page-header">
          <h1>Selamat datang, <?= e(explode(' ', currentUser()['nama_lengkap'])[0]) ?>! 👋</h1>
          <p><?= tgl(date('Y-m-d'), 'lengkap') ?> · Pantau progres skripsi Anda hari ini</p>
        </div>

        <?php if (!$bimbingan): ?>
        <div class="card langkah-card mb-20">
          <div class="card-header">
            <div class="card-title">Langkah Skripsi Anda</div>
            <span class="badge info"><span class="badge-dot"></span><?= min($langkahAktif, count($langkah)) ?> dari <?= count($langkah) ?> selesai</span>
          </div>
          <div class="card-body">
            <ol class="langkah">
              <?php foreach ($langkah as $i => [$judulL, $done, $href, $ket]):
                $state = $done ? 'done' : ($i === $langkahAktif ? 'now' : 'todo'); ?>
                <li class="langkah-item <?= $state ?>">
                  <span class="langkah-no"><?= $done ? '✓' : $i + 1 ?></span>
                  <div class="min-w-0">
                    <div class="langkah-title"><?= e($judulL) ?></div>
                    <?php if (!$done): ?><div class="langkah-sub"><?= e($ket) ?></div><?php endif; ?>
                    <?php if ($state === 'now' && $href): ?>
                      <a href="<?= BASE_URL . '/' . $href ?>" class="btn btn-gold btn-sm" style="margin-top:8px;"><?= e($judulL) ?></a>
                    <?php endif; ?>
                  </div>
                </li>
              <?php endforeach; ?>
            </ol>
          </div>
        </div>
        <?php else: ?>

        <div class="stats-grid">
          <div class="stat-card blue">
            <div class="stat-icon" aria-hidden="true"><?= icon('pencil', 22) ?></div>
            <div class="stat-num"><?= $totalSesi ?></div>
            <div class="stat-label">Total Sesi Bimbingan</div>
          </div>
          <div class="stat-card green">
            <div class="stat-icon" aria-hidden="true"><?= icon('check-circle', 22) ?></div>
            <div class="stat-num"><?= countRows('SELECT COUNT(*) FROM dokumen WHERE id_mahasiswa=? AND status="disetujui"',[$eid]) ?></div>
            <div class="stat-label">Dokumen Disetujui</div>
          </div>
          <div class="stat-card gold">
            <div class="stat-icon" aria-hidden="true"><?= icon('file', 22) ?></div>
            <div class="stat-num"><?= $totalRevisi ?></div>
            <div class="stat-label">Revisi Pending</div>
            <?php if ($totalRevisi > 0): ?>
              <div class="stat-change warn">⏳ Perlu ditindaklanjuti</div>
            <?php endif; ?>
          </div>
          <div class="stat-card red">
            <div class="stat-icon" aria-hidden="true"><?= icon('calendar', 22) ?></div>
            <div class="stat-num stat-num--text"><?= $nextSesi ? tglRelatif($nextSesi['tgl_sesi']) : '—' ?></div>
            <div class="stat-label">Bimbingan Berikutnya</div>
            <?php if ($nextSesi): ?>
              <div class="stat-change <?= $nextSesi['status'] === 'dikonfirmasi' ? 'up' : 'warn' ?>"><?= tgl($nextSesi['tgl_sesi'].' '.$nextSesi['waktu_mulai'], 'harijam') ?> · <?= e(label($nextSesi['status'])) ?></div>
            <?php elseif ($bimbingan): ?>
              <a class="stat-change" href="<?= BASE_URL ?>/mahasiswa/bimbingan.php" style="color:var(--info-text);text-decoration:underline;">Ajukan jadwal bimbingan</a>
            <?php endif; ?>
          </div>
        </div>

        <div class="card mb-20">
          <div class="card-header">
            <div class="card-title">Progres Skripsi</div>
            <span class="badge info"><span class="badge-dot"></span><?= $progres['persen'] ?>% · Sekarang: <?= e($progres['sekarang']) ?></span>
          </div>
          <div class="card-body">
            <div class="progress-bar" style="height:10px;"><div class="progress-fill" style="width:<?= $progres['persen'] ?>%"></div></div>
            <ol class="tahap-strip">
              <?php foreach ($progres['tahap'] as [$nm, $ok, $ket]): ?>
                <li class="<?= $ok ? 'done' : ($nm === $progres['sekarang'] ? 'now' : '') ?>" title="<?= e($ket) ?>">
                  <span class="tahap-dot" aria-hidden="true"><?= $ok ? '✓' : '' ?></span>
                  <span class="tahap-nama"><?= e($nm) ?></span>
                  <span class="tahap-ket"><?= e($ket) ?></span>
                </li>
              <?php endforeach; ?>
            </ol>
          </div>
        </div>

        <div class="grid-3 mb-20">
          <div class="card">
            <div class="card-header">
              <div class="card-title">Dokumen per Bab</div>
            </div>
            <div class="card-body">
              <?php if (empty($dokumen)): ?>
                <div class="empty-state">
                  <div class="empty-sub">Belum ada dokumen diunggah.</div>
                  <?php if ($bimbingan): ?><a href="<?= BASE_URL ?>/mahasiswa/dokumen.php" class="btn btn-secondary btn-sm" style="margin-top:10px;">Unggah dokumen pertama</a><?php else: ?><div class="empty-sub" style="margin-top:4px;">Unggah dokumen bisa dilakukan setelah SK bimbingan terbit.</div><?php endif; ?>
                </div>
              <?php else: ?>
                <?php foreach ($dokumen as $d): ?>
                  <div class="progress-item">
                    <div class="progress-top">
                      <div class="progress-name"><?= e(label($d['jenis_bab'])) ?></div>
                      <span class="badge <?= badge($d['status']) ?>">
                        <span class="badge-dot"></span><?= e(label($d['status'])) ?>
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
                        <div class="timeline-sub"><?= e(label($t['bab_terkait'])) ?></div>
                      <?php endif; ?>
                      <div class="timeline-time"><?= tgl($t['tgl_sesi'], 'hari') ?> · <?= e(label($t['keputusan'] ?: $t['status_sesi'])) ?></div>
                    </div>
                  </div>
                <?php endforeach; ?>
                <?php if (empty($timeline)): ?>
                  <div class="empty-state"><div class="empty-sub">Belum ada aktivitas bimbingan. Sesi dan catatan dari dosen akan muncul di sini.</div></div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <div class="card-title">Info Skripsi Aktif</div>
            <span class="badge <?= badge($bimbingan['status_judul']) ?>">
              <span class="badge-dot"></span>Judul <?= e(mb_strtolower(label($bimbingan['status_judul']))) ?>
            </span>
          </div>
          <div class="card-body">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(200px,100%),1fr));gap:20px;padding-top:8px;">
              <?php foreach ([
                ['Judul', $bimbingan['judul_indonesia']],
                ['Dosen Pembimbing', $bimbingan['nama_dosen']],
                ['Program Studi', $mhs['nama_prodi']],
                ['Status Skripsi', label($mhs['status_skripsi'])],
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
          <p>Pantau seluruh mahasiswa bimbingan Anda · <?= tgl(date('Y-m-d'), 'lengkap') ?></p>
        </div>

        <div class="stats-grid">
          <div class="stat-card blue"><div class="stat-icon" aria-hidden="true"><?= icon('users', 22) ?></div><div class="stat-num"><?= $jumlahMhs ?></div><div class="stat-label">Mahasiswa Bimbingan</div></div>
          <div class="stat-card gold"><div class="stat-icon" aria-hidden="true"><?= icon('clock', 22) ?></div><div class="stat-num"><?= $judulPending ?></div><div class="stat-label">Judul Menunggu Validasi</div></div>
          <div class="stat-card green"><div class="stat-icon" aria-hidden="true"><?= icon('check-circle', 22) ?></div><div class="stat-num"><?= $sesiMingguIni ?></div><div class="stat-label">Sesi Minggu Ini</div></div>
          <div class="stat-card red"><div class="stat-icon" aria-hidden="true"><?= icon('file', 22) ?></div><div class="stat-num"><?= $revisiBlm ?></div><div class="stat-label">Revisi Belum Dibalas</div></div>
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
                        <td class="fw-500"><?= e($m['nama_lengkap']) ?></td>
                        <td class="mono fs-12"><?= e($m['nim']) ?></td>
                        <td><?= $m['total_sesi'] ?></td>
                        <td><span class="badge <?= badge($m['status_skripsi']) ?>"><span class="badge-dot"></span><?= e(label($m['status_skripsi'])) ?></span></td>
                      </tr>
                    <?php endforeach; ?>
                    <?php if (empty($listMhs)): ?>
                      <tr><td colspan="4"><div class="empty-state"><div class="empty-sub">Belum ada mahasiswa bimbingan aktif.</div></div></td></tr>
                    <?php endif; ?>
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
                    <div class="timeline-dot <?= $j['status']==='dikonfirmasi' ? 'blue' : 'gold' ?>">🗓️</div>
                    <div class="timeline-body">
                      <div class="timeline-title"><?= e($j['nama_mhs']) ?></div>
                      <div class="timeline-sub"><?= e($j['topik']) ?></div>
                      <div class="timeline-time"><?= tgl($j['tgl_sesi'].' '.$j['waktu_mulai'], 'harijam') ?> · <?= e(label($j['metode'])) ?></div>
                      <?php if ($j['status'] === 'dijadwalkan'): ?><span class="badge warning" style="margin-top:4px;"><span class="badge-dot"></span>Menunggu konfirmasi Anda</span><?php endif; ?>
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
          <div class="stat-card blue"><div class="stat-icon" aria-hidden="true"><?= icon('users', 22) ?></div><div class="stat-num"><?= $totalMhs ?></div><div class="stat-label">Total Mahasiswa</div></div>
          <div class="stat-card green"><div class="stat-icon" aria-hidden="true"><?= icon('user', 22) ?></div><div class="stat-num"><?= $totalDosen ?></div><div class="stat-label">Dosen Pembimbing</div></div>
          <div class="stat-card gold"><div class="stat-icon" aria-hidden="true"><?= icon('clipboard', 22) ?></div><div class="stat-num"><?= $aktifSkrpsi ?></div><div class="stat-label">Skripsi Aktif</div></div>
          <div class="stat-card red"><div class="stat-icon" aria-hidden="true"><?= icon('clock', 22) ?></div><div class="stat-num"><?= $pending ?></div><div class="stat-label">Akun Non-aktif</div></div>
        </div>

        <?php if ($ujianMenunggu || $resetMenunggu): ?>
          <div class="tugas-admin mb-20">
            <?php if ($ujianMenunggu): ?><a class="status-card" href="<?= BASE_URL ?>/admin/ujian.php"><?= icon('award', 18) ?> <b><?= $ujianMenunggu ?></b> seminar/sidang perlu dijadwalkan</a><?php endif; ?>
            <?php if ($resetMenunggu): ?><a class="status-card" href="<?= BASE_URL ?>/admin/reset_sandi.php"><?= icon('key', 18) ?> <b><?= $resetMenunggu ?></b> permintaan reset kata sandi</a><?php endif; ?>
          </div>
        <?php endif; ?>

        <div class="grid-2 mb-20">
          <div class="card">
            <div class="card-header"><div class="card-title">Pengajuan judul per bulan</div><span class="meta">6 bulan terakhir</span></div>
            <div class="card-body">
              <?php $maxB = max(1, max($perBulan)); ?>
              <div class="grafik-kolom" role="img" aria-label="Pengajuan judul per bulan: <?= e(implode(', ', array_map(fn($k, $v) => NAMA_BULAN[(int)substr($k, 5)] . ' ' . $v, array_keys($perBulan), $perBulan))) ?>">
                <?php foreach ($perBulan as $bln => $n): ?>
                  <div class="grafik-kolom__item">
                    <span class="grafik-kolom__nilai"><?= $n ?></span>
                    <div class="grafik-kolom__batang" style="height:<?= round($n / $maxB * 100) ?>%"></div>
                    <span class="grafik-kolom__label"><?= BULAN_PENDEK[(int)substr($bln, 5)] ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <div class="card">
            <div class="card-header"><div class="card-title">Beban bimbingan dosen</div><span class="meta">aktif / kuota</span></div>
            <div class="card-body">
              <?php foreach ($bebanDosen as $d): $pct = $d['kuota_bimbingan'] ? min(100, round($d['aktif'] / $d['kuota_bimbingan'] * 100)) : 0; ?>
                <div class="grafik-baris">
                  <div class="grafik-baris__label"><?= e($d['nama_lengkap']) ?></div>
                  <div class="progress-bar"><div class="progress-fill <?= $pct >= 100 ? 'penuh' : '' ?>" style="width:<?= $pct ?>%"></div></div>
                  <div class="grafik-baris__nilai mono"><?= (int)$d['aktif'] ?>/<?= (int)$d['kuota_bimbingan'] ?></div>
                </div>
              <?php endforeach; ?>
              <?php if (!$bebanDosen): ?><div class="empty-state"><div class="empty-sub">Belum ada data dosen.</div></div><?php endif; ?>
            </div>
          </div>
        </div>

        <div class="grid-2 mb-20">
          <div class="card">
            <div class="card-header"><div class="card-title">Statistik per Program Studi</div></div>
            <div class="card-body">
              <div class="table-wrap">
                <table>
                  <thead><tr><th>Program Studi</th><th>Mahasiswa</th><th>Aktif</th><th>Selesai</th></tr></thead>
                  <tbody>
                    <?php foreach ($statsProdi as $p): ?>
                      <tr>
                        <td class="fw-500"><?= e($p['nama_prodi']) ?></td>
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
                      <div class="timeline-sub"><?= e($log['nama_lengkap']) ?> · <?= e(label($log['role'])) ?></div>
                      <div class="timeline-time"><?= tgl($log['created_at'], 'tglblnjam') ?></div>
                    </div>
                  </div>
                <?php endforeach; ?>
                <?php if (empty($logAktivitas)): ?>
                  <div class="empty-state"><div class="empty-sub">Belum ada aktivitas.</div></div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

      <?php endif; ?>

    </div><!-- /.content -->
  </main>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
