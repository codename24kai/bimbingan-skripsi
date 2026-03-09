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
    $id_sesi = (int)$_POST['id_sesi'];
    $aksi    = $_POST['aksi'] ?? '';

    $sesi = fetchOne('SELECT sb.*,b.id_mahasiswa,
                       u.id_user AS id_user_mhs
                      FROM sesi_bimbingan sb
                      JOIN bimbingan b ON b.id_bimbingan=sb.id_bimbingan
                      JOIN mahasiswa m ON m.id_mahasiswa=b.id_mahasiswa
                      JOIN user u ON u.id_user=m.id_user
                      WHERE sb.id_sesi=?', [$id_sesi]);

    if ($sesi) {
        if ($aksi === 'terima') {
            // Sesi tetap dijadwalkan (sudah terjadwal)
            execute('INSERT INTO notifikasi (id_user,judul,pesan,tipe,modul) VALUES (?,?,?,?,?)',
                [$sesi['id_user_mhs'], 'Jadwal Bimbingan Dikonfirmasi',
                 'Dosen mengkonfirmasi jadwal bimbingan Anda pada ' . date('d M Y', strtotime($sesi['tgl_sesi'])),
                 'sukses', 'jadwal']);
            setFlash('success', 'Jadwal dikonfirmasi.');
        } elseif ($aksi === 'tolak') {
            execute('UPDATE sesi_bimbingan SET status="batal" WHERE id_sesi=?', [$id_sesi]);
            execute('INSERT INTO notifikasi (id_user,judul,pesan,tipe,modul) VALUES (?,?,?,?,?)',
                [$sesi['id_user_mhs'], 'Jadwal Bimbingan Dibatalkan',
                 'Dosen membatalkan jadwal bimbingan pada ' . date('d M Y', strtotime($sesi['tgl_sesi'])) . '. Silakan ajukan ulang.',
                 'peringatan', 'jadwal']);
            setFlash('warning', 'Jadwal dibatalkan.');
        }
        header('Location: ' . BASE_URL . '/dosen/jadwal.php');
        exit;
    }
}

$pending = fetchAll(
    'SELECT sb.*,um.nama_lengkap AS nama_mhs,m.nim
     FROM sesi_bimbingan sb
     JOIN bimbingan b ON b.id_bimbingan=sb.id_bimbingan
     JOIN mahasiswa m ON m.id_mahasiswa=b.id_mahasiswa
     JOIN user um ON um.id_user=m.id_user
     WHERE b.id_dosen=? AND sb.status="dijadwalkan"
     AND sb.tgl_sesi >= CURDATE()
     ORDER BY sb.tgl_sesi,sb.waktu_mulai', [$eid]
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
  <?php $flash = getFlash(); if ($flash): ?><div class="alert <?= $flash['type'] ?>"><?= e($flash['msg']) ?></div><?php endif; ?>
  <div class="page-header"><h1>Jadwal Konsultasi</h1><p>Kelola permintaan dan konfirmasi jadwal bimbingan mahasiswa</p></div>

  <div class="grid-2">
    <div class="card">
      <div class="card-header">
        <div class="card-title">Permintaan Masuk</div>
        <span class="badge warning"><span class="badge-dot"></span><?= count($pending) ?> Pending</span>
      </div>
      <div class="card-body">
        <?php if (empty($pending)): ?>
          <div class="empty-state"><div class="empty-sub">Tidak ada permintaan bimbingan.</div></div>
        <?php else: ?>
          <?php foreach ($pending as $p): ?>
            <div style="padding:16px 0;border-bottom:1px solid var(--cream-dark);">
              <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                <div>
                  <div style="font-size:14px;font-weight:600;"><?= e($p['nama_mhs']) ?> <span style="font-size:12px;color:var(--slate);">(<?= e($p['nim']) ?>)</span></div>
                  <div style="font-size:13px;color:var(--slate);margin-top:3px;">📅 <?= date('d M Y H:i', strtotime($p['tgl_sesi'].' '.$p['waktu_mulai'])) ?></div>
                  <div style="font-size:13px;margin-top:3px;">💬 <?= e($p['topik']) ?></div>
                  <div style="font-size:12px;color:var(--slate);margin-top:3px;">⏱ <?= $p['durasi_menit'] ?> menit · 📡 <?= e($p['metode']) ?></div>
                </div>
                <form method="POST" style="display:flex;flex-direction:column;gap:6px;">
                  <input type="hidden" name="id_sesi" value="<?= $p['id_sesi'] ?>">
                  <button name="aksi" value="terima" class="btn btn-success btn-sm">✅ Terima</button>
                  <button name="aksi" value="tolak"  class="btn btn-danger btn-sm">❌ Tolak</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
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
                <div class="timeline-sub"><?= e(mb_strimwidth($r['topik'],0,50,'...')) ?></div>
                <div class="timeline-time"><?= date('d M Y', strtotime($r['tgl_sesi'])) ?> · <?= e($r['status']) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
          <?php if (empty($riwayatSesi)): ?>
            <div class="empty-state"><div class="empty-sub">Belum ada riwayat.</div></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
