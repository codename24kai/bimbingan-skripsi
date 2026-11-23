<?php
// mahasiswa/bimbingan.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('mahasiswa');

$pageTitle  = 'Jadwal Bimbingan';
$activePage = 'bimbingan';
$eid        = entityId();

$bimbingan = fetchOne('SELECT b.*,d.id_dosen FROM bimbingan b JOIN dosen d ON d.id_dosen=b.id_dosen WHERE b.id_mahasiswa=? AND b.status="aktif" LIMIT 1', [$eid]);

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $bimbingan) {
    $tgl    = $_POST['tgl_sesi'] ?? '';
    $waktu  = $_POST['waktu_mulai'] ?? '';
    $durasi = (int)($_POST['durasi_menit'] ?? 45);
    $topik  = trim($_POST['topik'] ?? '');
    $metode = $_POST['metode'] ?? 'tatap_muka';

    if (!in_array($metode, ['tatap_muka','zoom','gmeet','lainnya'], true)) $metode = 'tatap_muka';
    if (!in_array($durasi, [30,45,60], true)) $durasi = 45;
    if (!$tgl || !$waktu || !$topik) {
        setFlash('danger', 'Tanggal, waktu, dan topik bimbingan wajib diisi.');
    } elseif ($tgl < date('Y-m-d')) {
        setFlash('danger', 'Tanggal bimbingan tidak boleh di masa lalu.');
    } else {
        execute('INSERT INTO sesi_bimbingan (id_bimbingan,tgl_sesi,waktu_mulai,durasi_menit,topik,metode,status)
                 VALUES (?,?,?,?,?,?,"dijadwalkan")',
            [$bimbingan['id_bimbingan'], $tgl, $waktu, $durasi, $topik, $metode]);

        // Notif dosen
        $dosenUser = fetchOne('SELECT id_user FROM dosen WHERE id_dosen=?', [$bimbingan['id_dosen']]);
        if ($dosenUser) {
            execute('INSERT INTO notifikasi (id_user,judul,pesan,tipe,modul) VALUES (?,?,?,?,?)',
                [$dosenUser['id_user'], 'Permintaan Jadwal Bimbingan',
                 'Mahasiswa mengajukan sesi bimbingan pada ' . tgl($tgl . ' ' . $waktu, 'harijam') . '. Buka Jadwal Konsultasi untuk menerima atau menolak.',
                 'info', 'jadwal']);
        }
        setFlash('success', 'Permintaan jadwal terkirim. Status akan berubah menjadi "Dikonfirmasi" setelah dosen menerimanya.');
        header('Location: ' . BASE_URL . '/mahasiswa/bimbingan.php');
        exit;
    }
}

// Jadwal mendatang lebih dulu (terdekat di atas), lalu sesi yang sudah lewat
$jadwal = fetchAll('SELECT * FROM sesi_bimbingan WHERE id_bimbingan=?
                    ORDER BY (tgl_sesi >= CURDATE() AND status IN ("dijadwalkan","dikonfirmasi")) DESC,
                             CASE WHEN tgl_sesi >= CURDATE() AND status IN ("dijadwalkan","dikonfirmasi") THEN tgl_sesi END ASC,
                             tgl_sesi DESC, waktu_mulai DESC
                    LIMIT 10',
    [$bimbingan['id_bimbingan'] ?? 0]);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">
  <?php $flash = getFlash(); if ($flash): ?><div class="alert <?= $flash['type'] ?>"><?= e($flash['msg']) ?></div><?php endif; ?>
  <div class="page-header"><h1>Jadwal Bimbingan</h1><p>Ajukan jadwal konsultasi dengan dosen pembimbing</p></div>

  <?php if (!$bimbingan): ?>
    <div class="alert alert-static info">ℹ️ Jadwal bimbingan bisa diajukan setelah SK bimbingan terbit. Lihat langkah yang tersisa di <a href="<?= BASE_URL ?>/dashboard.php" style="text-decoration:underline;font-weight:600;">Dashboard</a>.</div>
  <?php else: ?>
  <div class="grid-2">
    <div class="form-card">
      <h2>Ajukan Jadwal</h2>
      <p>Dosen akan menerima atau menolak permintaan Anda. Anda akan mendapat notifikasi.</p>
      <form method="POST"><?= csrfField() ?>
        <div class="form-group">
          <label for="f_tgl_sesi" class="form-label">Tanggal <span style="color:red">*</span></label>
          <input id="f_tgl_sesi" class="form-input" type="date" name="tgl_sesi" required min="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="f_waktu_mulai" class="form-label">Waktu Mulai</label>
            <input id="f_waktu_mulai" class="form-input" type="time" name="waktu_mulai" required value="09:00">
          </div>
          <div class="form-group">
            <label for="f_durasi_menit" class="form-label">Durasi (menit)</label>
            <select id="f_durasi_menit" class="form-input" name="durasi_menit">
              <option value="30">30 menit</option>
              <option value="45" selected>45 menit</option>
              <option value="60">60 menit</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label for="f_topik" class="form-label">Topik Bimbingan <span style="color:red">*</span></label>
          <textarea id="f_topik" class="form-input" name="topik" style="min-height:80px;" required placeholder="Topik yang akan dibahas..."></textarea>
        </div>
        <div class="form-group">
          <label for="f_metode" class="form-label">Metode</label>
          <select id="f_metode" class="form-input" name="metode">
            <option value="tatap_muka">Tatap Muka</option>
            <option value="zoom">Online (Zoom)</option>
            <option value="gmeet">Online (Google Meet)</option>
            <option value="lainnya">Lainnya</option>
          </select>
        </div>
        <button type="submit" class="btn btn-gold btn-full">📅 Ajukan Jadwal</button>
      </form>
    </div>

    <div class="card">
      <div class="card-header"><div class="card-title">Sesi Bimbingan</div></div>
      <div class="card-body">
        <?php if (empty($jadwal)): ?>
          <div class="empty-state"><div class="empty-sub">Belum ada sesi bimbingan. Ajukan jadwal pertama Anda lewat form di samping.</div></div>
        <?php else: ?>
          <div class="timeline">
            <?php foreach ($jadwal as $j):
              $dot = ['selesai'=>'green','batal'=>'red','dikonfirmasi'=>'blue'][$j['status']] ?? 'gold';
              $icon = $j['status']==='selesai' ? '✅' : ($j['status']==='batal' ? '❌' : '🗓️');
            ?>
              <div class="timeline-item">
                <div class="timeline-dot <?= $dot ?>"><?= $icon ?></div>
                <div class="timeline-body">
                  <div class="timeline-title"><?= e($j['topik']) ?></div>
                  <div class="timeline-sub"><?= (int)$j['durasi_menit'] ?> menit · <?= e(label($j['metode'])) ?></div>
                  <div class="timeline-time"><?= tgl($j['tgl_sesi'].' '.$j['waktu_mulai'], 'harijam') ?></div>
                </div>
                <span class="badge <?= badge($j['status']) ?>"><span class="badge-dot"></span><?= e(label($j['status'])) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
