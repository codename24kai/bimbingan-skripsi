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

    if (!$tgl || !$waktu || !$topik) {
        setFlash('danger', 'Harap isi semua field wajib.');
    } else {
        execute('INSERT INTO sesi_bimbingan (id_bimbingan,tgl_sesi,waktu_mulai,durasi_menit,topik,metode,status)
                 VALUES (?,?,?,?,?,?,"dijadwalkan")',
            [$bimbingan['id_bimbingan'], $tgl, $waktu, $durasi, $topik, $metode]);

        // Notif dosen
        $dosenUser = fetchOne('SELECT id_user FROM dosen WHERE id_dosen=?', [$bimbingan['id_dosen']]);
        if ($dosenUser) {
            execute('INSERT INTO notifikasi (id_user,judul,pesan,tipe,modul) VALUES (?,?,?,?,?)',
                [$dosenUser['id_user'], 'Permintaan Jadwal Bimbingan',
                 'Mahasiswa mengajukan sesi bimbingan pada ' . date('d M Y', strtotime($tgl)) . ' pukul ' . $waktu,
                 'info', 'jadwal']);
        }
        setFlash('success', 'Permintaan jadwal bimbingan berhasil dikirim!');
        header('Location: ' . BASE_URL . '/mahasiswa/bimbingan.php');
        exit;
    }
}

$jadwal = fetchAll('SELECT * FROM sesi_bimbingan WHERE id_bimbingan=? ORDER BY tgl_sesi DESC LIMIT 10',
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
    <div class="alert info">ℹ️ Anda belum memiliki bimbingan aktif. Ajukan judul skripsi terlebih dahulu.</div>
  <?php else: ?>
  <div class="grid-2">
    <div class="form-card">
      <h2>Ajukan Jadwal</h2>
      <p>Pilih waktu yang tersedia sesuai slot dosen.</p>
      <form method="POST"><?= csrfField() ?>
        <div class="form-group">
          <label class="form-label">Tanggal <span style="color:red">*</span></label>
          <input class="form-input" type="date" name="tgl_sesi" required min="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Waktu Mulai</label>
            <input class="form-input" type="time" name="waktu_mulai" required value="09:00">
          </div>
          <div class="form-group">
            <label class="form-label">Durasi (menit)</label>
            <select class="form-input" name="durasi_menit">
              <option value="30">30 menit</option>
              <option value="45" selected>45 menit</option>
              <option value="60">60 menit</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Topik Bimbingan <span style="color:red">*</span></label>
          <textarea class="form-input" name="topik" style="min-height:80px;" required placeholder="Topik yang akan dibahas..."></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Metode</label>
          <select class="form-input" name="metode">
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
          <div class="empty-state"><div class="empty-sub">Belum ada sesi bimbingan.</div></div>
        <?php else: ?>
          <div class="timeline">
            <?php foreach ($jadwal as $j):
              $dot = $j['status']==='selesai' ? 'green' : ($j['status']==='batal' ? 'red' : 'blue');
              $icon = $j['status']==='selesai' ? '✅' : ($j['status']==='batal' ? '❌' : '🗓️');
            ?>
              <div class="timeline-item">
                <div class="timeline-dot <?= $dot ?>"><?= $icon ?></div>
                <div class="timeline-body">
                  <div class="timeline-title"><?= e($j['topik']) ?></div>
                  <div class="timeline-sub"><?= $j['durasi_menit'] ?> menit · <?= e($j['metode']) ?></div>
                  <div class="timeline-time"><?= date('d M Y H:i', strtotime($j['tgl_sesi'].' '.$j['waktu_mulai'])) ?></div>
                </div>
                <span class="badge <?= badge($j['status']) ?>" style="flex-shrink:0;"><?= $j['status'] ?></span>
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
