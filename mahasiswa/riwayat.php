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
     ORDER BY sb.tgl_sesi DESC, sb.waktu_mulai DESC', [$eid]
);

// Pisahkan sesi yang akan datang dari riwayat
$akanDatang = array_values(array_filter($riwayat, fn($r) =>
    in_array($r['status'], ['dijadwalkan','dikonfirmasi'], true) && $r['tgl_sesi'] >= date('Y-m-d')));
$riwayat = array_values(array_filter($riwayat, fn($r) =>
    !(in_array($r['status'], ['dijadwalkan','dikonfirmasi'], true) && $r['tgl_sesi'] >= date('Y-m-d'))));
$akanDatang = array_reverse($akanDatang); // terdekat dulu

// Nomor "Bimbingan ke-N" dihitung dari sesi pertama yang tidak dibatalkan
$nomor = [];
$n = 0;
foreach (array_reverse($riwayat) as $r) {
    if ($r['status'] !== 'batal') $nomor[$r['id_sesi']] = ++$n;
}

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

  <?php if (!empty($akanDatang)): ?>
    <div class="card" style="margin-bottom:20px;">
      <div class="card-header">
        <div class="card-title">Akan Datang</div>
        <a href="<?= BASE_URL ?>/mahasiswa/bimbingan.php" style="font-size:13px;color:var(--info-text);text-decoration:underline;">Kelola jadwal</a>
      </div>
      <div class="card-body">
        <?php foreach ($akanDatang as $a): ?>
          <div class="sesi-item">
            <div style="min-width:0;">
              <div style="font-size:14px;font-weight:600;"><?= e($a['topik']) ?></div>
              <div style="font-size:13px;color:var(--slate);margin-top:3px;"><?= tgl($a['tgl_sesi'].' '.$a['waktu_mulai'], 'harijam') ?> · <?= tglRelatif($a['tgl_sesi']) ?> · <?= e(label($a['metode'])) ?></div>
            </div>
            <span class="badge <?= badge($a['status']) ?>"><span class="badge-dot"></span><?= e(label($a['status'])) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if (empty($riwayat)): ?>
    <div class="empty-state card" style="padding:48px;">
      <div class="empty-icon">📋</div>
      <div class="empty-title">Belum Ada Riwayat</div>
      <div class="empty-sub">Riwayat bimbingan akan muncul setelah sesi pertama selesai.</div>
      <a href="<?= BASE_URL ?>/mahasiswa/bimbingan.php" class="btn btn-secondary btn-sm" style="margin-top:12px;">Ajukan jadwal bimbingan</a>
    </div>
  <?php else: ?>
    <?php foreach ($riwayat as $r):
      $cls = $r['keputusan']==='disetujui' ? 'approved' : ($r['keputusan']==='revisi' ? 'revision' : '');
    ?>
      <div class="catatan-card <?= $cls ?>">
        <div class="catatan-header">
          <div>
            <div class="catatan-dosen">
              <?= isset($nomor[$r['id_sesi']]) ? 'Bimbingan ke-' . $nomor[$r['id_sesi']] : 'Sesi dibatalkan' ?> · <?= e($r['topik']) ?>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:8px 10px;align-items:center;margin-top:6px;">
              <span class="badge <?= badge($r['status']) ?>"><span class="badge-dot"></span><?= e(label($r['status'])) ?></span>
              <?php if ($r['keputusan']): ?>
                <span class="badge <?= badge($r['keputusan']) ?>"><span class="badge-dot"></span>Keputusan: <?= e(mb_strtolower(label($r['keputusan']))) ?></span>
              <?php endif; ?>
              <?php if ($r['durasi_menit']): ?>
                <span style="font-size:12px;color:var(--slate);"><?= (int)$r['durasi_menit'] ?> menit</span>
              <?php endif; ?>
              <span style="font-size:12px;color:var(--slate);"><?= e(label($r['metode'])) ?></span>
              <?php if ($r['bab_terkait']): ?>
                <span class="badge neutral"><?= e(label($r['bab_terkait'])) ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div class="catatan-date"><?= tgl($r['tgl_sesi'].' '.$r['waktu_mulai'], 'harijam') ?></div>
        </div>
        <?php if ($r['isi_catatan']): ?>
          <div class="catatan-text"><strong>Catatan Dosen:</strong> <?= e($r['isi_catatan']) ?></div>
        <?php else: ?>
          <div class="catatan-text" style="color:var(--slate);"><?= $r['status']==='batal' ? 'Sesi ini dibatalkan.' : 'Dosen belum menulis catatan untuk sesi ini.' ?></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
