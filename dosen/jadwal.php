<?php
// dosen/jadwal.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('dosen');

$pageTitle  = 'Jadwal Konsultasi';
$activePage = 'jadwal_dosen';
$eid        = entityId();

// Handle konfirmasi/penolakan sesi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_sesi = (int)($_POST['id_sesi'] ?? 0);
    $aksi    = $_POST['aksi'] ?? '';

    $sesi = fetchOne('SELECT sb.*,b.id_mahasiswa,
                       u.id_user AS id_user_mhs
                      FROM sesi_bimbingan sb
                      JOIN bimbingan b ON b.id_bimbingan=sb.id_bimbingan
                      JOIN mahasiswa m ON m.id_mahasiswa=b.id_mahasiswa
                      JOIN user u ON u.id_user=m.id_user
                      WHERE sb.id_sesi=? AND b.id_dosen=?', [$id_sesi, $eid]);

    if (!$sesi) {
        setFlash('danger', 'Jadwal tidak ditemukan atau bukan milik mahasiswa bimbingan Anda.');
        header('Location: ' . BASE_URL . '/dosen/jadwal.php');
        exit;
    }

    if ($aksi === 'terima' && $sesi['status'] === 'dijadwalkan') {
        execute('UPDATE sesi_bimbingan SET status="dikonfirmasi" WHERE id_sesi=?', [$id_sesi]);
        execute('INSERT INTO notifikasi (id_user,judul,pesan,tipe,modul,tautan) VALUES (?,?,?,?,?,"mahasiswa/bimbingan.php")',
            [$sesi['id_user_mhs'], 'Jadwal Bimbingan Dikonfirmasi',
             'Dosen mengonfirmasi jadwal bimbingan Anda pada ' . tgl($sesi['tgl_sesi'].' '.$sesi['waktu_mulai'], 'harijam') . '.',
             'sukses', 'jadwal']);
        setFlash('success', 'Jadwal dikonfirmasi. Sesi dipindahkan ke daftar Jadwal Terkonfirmasi.');
    } elseif ($aksi === 'tolak' && in_array($sesi['status'], ['dijadwalkan', 'dikonfirmasi'], true)) {
        execute('UPDATE sesi_bimbingan SET status="batal" WHERE id_sesi=?', [$id_sesi]);
        execute('INSERT INTO notifikasi (id_user,judul,pesan,tipe,modul,tautan) VALUES (?,?,?,?,?,"mahasiswa/bimbingan.php")',
            [$sesi['id_user_mhs'], 'Jadwal Bimbingan Dibatalkan',
             'Dosen membatalkan jadwal bimbingan pada ' . tgl($sesi['tgl_sesi'], 'hari') . '. Silakan ajukan jadwal lain.',
             'peringatan', 'jadwal']);
        setFlash('warning', 'Jadwal dibatalkan dan mahasiswa sudah diberi tahu.');
    } else {
        setFlash('danger', 'Status jadwal sudah berubah. Muat ulang halaman lalu coba lagi.');
    }
    header('Location: ' . BASE_URL . '/dosen/jadwal.php');
    exit;
}

$sesiAkanDatang = fetchAll(
    'SELECT sb.*,um.nama_lengkap AS nama_mhs,m.nim
     FROM sesi_bimbingan sb
     JOIN bimbingan b ON b.id_bimbingan=sb.id_bimbingan
     JOIN mahasiswa m ON m.id_mahasiswa=b.id_mahasiswa
     JOIN user um ON um.id_user=m.id_user
     WHERE b.id_dosen=? AND sb.status IN ("dijadwalkan","dikonfirmasi")
     AND sb.tgl_sesi >= CURDATE()
     ORDER BY sb.tgl_sesi,sb.waktu_mulai', [$eid]
);
$pending    = array_values(array_filter($sesiAkanDatang, fn($x) => $x['status'] === 'dijadwalkan'));
$terkonfirm = array_values(array_filter($sesiAkanDatang, fn($x) => $x['status'] === 'dikonfirmasi'));

// Sesi terkonfirmasi yang tanggalnya sudah lewat tapi belum diberi catatan
$perluCatatan = fetchAll(
    'SELECT sb.*,um.nama_lengkap AS nama_mhs
     FROM sesi_bimbingan sb
     JOIN bimbingan b ON b.id_bimbingan=sb.id_bimbingan
     JOIN mahasiswa m ON m.id_mahasiswa=b.id_mahasiswa
     JOIN user um ON um.id_user=m.id_user
     WHERE b.id_dosen=? AND sb.status IN ("dijadwalkan","dikonfirmasi") AND sb.tgl_sesi < CURDATE()
     ORDER BY sb.tgl_sesi DESC', [$eid]
);

$riwayatSesi = fetchAll(
    'SELECT sb.*,um.nama_lengkap AS nama_mhs
     FROM sesi_bimbingan sb
     JOIN bimbingan b ON b.id_bimbingan=sb.id_bimbingan
     JOIN mahasiswa m ON m.id_mahasiswa=b.id_mahasiswa
     JOIN user um ON um.id_user=m.id_user
     WHERE b.id_dosen=? AND (sb.status="selesai" OR sb.status="batal")
     ORDER BY sb.tgl_sesi DESC LIMIT 10', [$eid]
);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">
  <?= tampilFlash() ?>
  <div class="page-header"><h1>Jadwal Konsultasi</h1><p>Kelola permintaan dan konfirmasi jadwal bimbingan mahasiswa</p></div>

  <?php if (!empty($perluCatatan)): ?>
    <div class="alert alert-static warning">
      <?= count($perluCatatan) ?> sesi sudah lewat tanggalnya tapi belum diberi catatan.
      Tulis catatan agar sesi tercatat selesai:
      <?php foreach ($perluCatatan as $i => $pc): ?>
        <a href="<?= BASE_URL ?>/dosen/catatan.php?id_bimbingan=<?= (int)$pc['id_bimbingan'] ?>" style="font-weight:600;text-decoration:underline;"><?= e($pc['nama_mhs']) ?> (<?= tgl($pc['tgl_sesi'], 'tglbln') ?>)</a><?= $i < count($perluCatatan) - 1 ? ',' : '' ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="grid-2">
    <div class="card">
      <div class="card-header">
        <div class="card-title">Menunggu Konfirmasi</div>
        <span class="badge <?= $pending ? 'warning' : 'neutral' ?>"><span class="badge-dot"></span><?= count($pending) ?> permintaan</span>
      </div>
      <div class="card-body">
        <?php if (empty($pending)): ?>
          <div class="empty-state"><div class="empty-sub">Tidak ada permintaan jadwal baru. Permintaan dari mahasiswa akan muncul di sini.</div></div>
        <?php else: ?>
          <?php foreach ($pending as $p): ?>
            <div class="sesi-item">
              <div class="min-w-0">
                <div style="font-size:14px;font-weight:600;"><?= e($p['nama_mhs']) ?> <span class="meta">(<?= e($p['nim']) ?>)</span></div>
                <div style="font-size:13px;margin-top:3px;"><strong><?= tgl($p['tgl_sesi'].' '.$p['waktu_mulai'], 'harijam') ?></strong> <span class="text-slate">· <?= tglRelatif($p['tgl_sesi']) ?></span></div>
                <div style="font-size:13px;margin-top:3px;"><?= e($p['topik']) ?></div>
                <div style="font-size:12px;color:var(--slate);margin-top:3px;"><?= (int)$p['durasi_menit'] ?> menit · <?= e(label($p['metode'])) ?><?= $p['lokasi_link'] ? ' · ' . e($p['lokasi_link']) : '' ?></div>
              </div>
              <form method="POST" class="sesi-aksi"><?= csrfField() ?>
                <input type="hidden" name="id_sesi" value="<?= $p['id_sesi'] ?>">
                <button name="aksi" value="terima" class="btn btn-success btn-sm">Terima</button>
                <button name="aksi" value="tolak" class="btn btn-danger btn-sm" data-confirm="Tolak jadwal ini? Mahasiswa akan diminta mengajukan jadwal lain." data-confirm-ok="Tolak jadwal">Tolak</button>
              </form>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <div class="card-title">Jadwal Terkonfirmasi</div>
        <span class="badge <?= $terkonfirm ? 'info' : 'neutral' ?>"><span class="badge-dot"></span><?= count($terkonfirm) ?> sesi</span>
      </div>
      <div class="card-body">
        <?php if (empty($terkonfirm)): ?>
          <div class="empty-state"><div class="empty-sub">Belum ada jadwal yang dikonfirmasi. Jadwal yang Anda terima akan muncul di sini.</div></div>
        <?php else: ?>
          <?php foreach ($terkonfirm as $p): ?>
            <div class="sesi-item">
              <div class="min-w-0">
                <div style="font-size:14px;font-weight:600;"><?= e($p['nama_mhs']) ?></div>
                <div style="font-size:13px;margin-top:3px;"><strong><?= tgl($p['tgl_sesi'].' '.$p['waktu_mulai'], 'harijam') ?></strong> <span class="text-slate">· <?= tglRelatif($p['tgl_sesi']) ?></span></div>
                <div style="font-size:13px;margin-top:3px;"><?= e($p['topik']) ?></div>
                <div style="font-size:12px;color:var(--slate);margin-top:3px;"><?= (int)$p['durasi_menit'] ?> menit · <?= e(label($p['metode'])) ?><?= $p['lokasi_link'] ? ' · ' . e($p['lokasi_link']) : '' ?></div>
              </div>
              <form method="POST" class="sesi-aksi"><?= csrfField() ?>
                <input type="hidden" name="id_sesi" value="<?= $p['id_sesi'] ?>">
                <a href="<?= BASE_URL ?>/dosen/catatan.php?id_bimbingan=<?= (int)$p['id_bimbingan'] ?>" class="btn btn-secondary btn-sm">Tulis catatan</a>
                <button name="aksi" value="tolak" class="btn btn-danger btn-sm" data-confirm="Batalkan jadwal yang sudah dikonfirmasi? Mahasiswa akan diberi tahu." data-confirm-ok="Batalkan jadwal">Batalkan</button>
              </form>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><div class="card-title">Riwayat Sesi</div></div>
    <div class="card-body">
      <div class="timeline">
        <?php foreach ($riwayatSesi as $r):
          $dot = $r['status']==='selesai' ? 'green' : 'red';
          $icon = $r['status']==='selesai' ? '✅' : '❌';
        ?>
          <div class="timeline-item">
            <div class="timeline-dot <?= $dot ?>"><?= $icon ?></div>
            <div class="timeline-body">
              <div class="timeline-title"><?= e($r['nama_mhs']) ?></div>
              <div class="timeline-sub"><?= e(mb_strimwidth($r['topik'],0,60,'...')) ?></div>
              <div class="timeline-time"><?= tgl($r['tgl_sesi'], 'hari') ?> · <?= e(label($r['status'])) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (empty($riwayatSesi)): ?>
          <div class="empty-state"><div class="empty-sub">Belum ada sesi yang selesai atau dibatalkan.</div></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
