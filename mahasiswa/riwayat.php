<?php
// mahasiswa/riwayat.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('mahasiswa');

$pageTitle  = 'Riwayat Bimbingan';
$activePage = 'riwayat';
$eid        = entityId();

$riwayat = fetchAll(
    'SELECT sb.id_sesi,sb.tgl_sesi,sb.waktu_mulai,sb.durasi_menit,sb.topik,sb.metode,sb.status,
            cb.isi_catatan,cb.keputusan,cb.bab_terkait,cb.file_lampiran
     FROM sesi_bimbingan sb
     JOIN bimbingan b ON b.id_bimbingan=sb.id_bimbingan
     LEFT JOIN catatan_bimbingan cb ON cb.id_sesi=sb.id_sesi
     WHERE b.id_mahasiswa=?
     ORDER BY sb.tgl_sesi DESC', [$eid]
);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">
  <div class="page-header">
    <h1>Riwayat Bimbingan</h1>
    <p>Catatan lengkap seluruh sesi bimbingan skripsi Anda</p>
  </div>

  <?php if (empty($riwayat)): ?>
    <div class="empty-state card" style="padding:48px;">
      <div class="empty-icon">📋</div>
      <div class="empty-title">Belum Ada Riwayat</div>
      <div class="empty-sub">Riwayat bimbingan akan muncul setelah sesi pertama selesai.</div>
    </div>
  <?php else: ?>
    <?php $sesi = 1; foreach ($riwayat as $r):
      $cls = $r['keputusan']==='disetujui' ? 'approved' : ($r['keputusan']==='revisi' ? 'revision' : '');
    ?>
      <div class="catatan-card <?= $cls ?>">
        <div class="catatan-header">
          <div>
            <div class="catatan-dosen">
              Bimbingan ke-<?= $sesi++ ?> — <?= e($r['topik']) ?>
            </div>
            <div style="display:flex;gap:10px;align-items:center;margin-top:6px;">
              <span class="badge <?= badge($r['status']) ?>"><span class="badge-dot"></span><?= e($r['status']) ?></span>
              <?php if ($r['keputusan']): ?>
                <span class="badge <?= badge($r['keputusan']) ?>"><span class="badge-dot"></span><?= e($r['keputusan']) ?></span>
              <?php endif; ?>
              <?php if ($r['durasi_menit']): ?>
                <span style="font-size:12px;color:var(--slate);">⏱ <?= $r['durasi_menit'] ?> menit</span>
              <?php endif; ?>
              <span style="font-size:12px;color:var(--slate);">📡 <?= e($r['metode']) ?></span>
              <?php if ($r['bab_terkait']): ?>
                <span class="badge neutral"><?= e($r['bab_terkait']) ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div class="catatan-date"><?= date('d M Y H:i', strtotime($r['tgl_sesi'].' '.$r['waktu_mulai'])) ?></div>
        </div>
        <?php if ($r['isi_catatan']): ?>
          <div class="catatan-text"><strong>Catatan Dosen:</strong> <?= e($r['isi_catatan']) ?></div>
        <?php else: ?>
          <div class="catatan-text" style="color:var(--slate);">Belum ada catatan untuk sesi ini.</div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
