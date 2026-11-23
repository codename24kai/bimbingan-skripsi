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

// Pesan slot konsultasi yang dibuka dosen (langsung terkonfirmasi)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $bimbingan && ($_POST['aksi'] ?? '') === 'pesan_slot') {
    $idSlot = (int)($_POST['id_slot'] ?? 0);
    $topik  = trim($_POST['topik'] ?? '');
    $db = getDB();
    $db->beginTransaction();
    try {
        // Kunci baris slot agar dua mahasiswa tidak memesan kursi terakhir bersamaan
        $slot = fetchOne('SELECT * FROM slot_dosen WHERE id_slot=? AND id_dosen=? AND tgl >= CURDATE() FOR UPDATE', [$idSlot, $bimbingan['id_dosen']]);
        if (!$slot) throw new Exception('Slot tidak ditemukan atau sudah lewat.');
        if ($topik === '') throw new Exception('Isi topik yang akan dibahas.');
        $terisi = countRows('SELECT COUNT(*) FROM sesi_bimbingan WHERE id_slot=? AND status IN ("dijadwalkan","dikonfirmasi","selesai")', [$idSlot]);
        if ($terisi >= $slot['kuota']) throw new Exception('Slot sudah penuh. Pilih slot lain.');
        if (countRows('SELECT COUNT(*) FROM sesi_bimbingan WHERE id_slot=? AND id_bimbingan=? AND status<>"batal"', [$idSlot, $bimbingan['id_bimbingan']])) {
            throw new Exception('Anda sudah memesan slot ini.');
        }
        $durasi = (int)round((strtotime($slot['jam_selesai']) - strtotime($slot['jam_mulai'])) / 60);
        execute('INSERT INTO sesi_bimbingan (id_bimbingan,id_slot,tgl_sesi,waktu_mulai,durasi_menit,topik,metode,lokasi_link,status)
                 VALUES (?,?,?,?,?,?,?,?,"dikonfirmasi")',
            [$bimbingan['id_bimbingan'], $idSlot, $slot['tgl'], $slot['jam_mulai'], $durasi, mb_substr($topik, 0, 1000), $slot['metode'], $slot['lokasi_link']]);
        $db->commit();
        $dosenUser = fetchOne('SELECT id_user FROM dosen WHERE id_dosen=?', [$bimbingan['id_dosen']]);
        kirimNotif((int)($dosenUser['id_user'] ?? 0), 'Slot konsultasi dipesan',
            (currentUser()['nama_lengkap'] ?? 'Mahasiswa') . ' memesan slot ' . tgl($slot['tgl'] . ' ' . $slot['jam_mulai'], 'harijam') . '. Topik: ' . mb_strimwidth($topik, 0, 100, '…'),
            'info', 'jadwal', 'dosen/jadwal.php');
        setFlash('success', 'Slot berhasil dipesan dan langsung terkonfirmasi.');
    } catch (Exception $ex) {
        $db->rollBack();
        setFlash('danger', $ex->getMessage());
    }
    header('Location: ' . BASE_URL . '/mahasiswa/bimbingan.php');
    exit;
}

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

// Slot konsultasi dosen pembimbing yang masih bisa dipesan
$slotTersedia = $bimbingan ? fetchAll(
    'SELECT s.*, (SELECT COUNT(*) FROM sesi_bimbingan sb WHERE sb.id_slot=s.id_slot AND sb.status IN ("dijadwalkan","dikonfirmasi","selesai")) AS terisi,
            (SELECT COUNT(*) FROM sesi_bimbingan sb WHERE sb.id_slot=s.id_slot AND sb.id_bimbingan=? AND sb.status<>"batal") AS milik_saya
     FROM slot_dosen s
     WHERE s.id_dosen=? AND (s.tgl > CURDATE() OR (s.tgl = CURDATE() AND s.jam_mulai > CURTIME()))
     ORDER BY s.tgl, s.jam_mulai LIMIT 20', [$bimbingan['id_bimbingan'], $bimbingan['id_dosen']]
) : [];

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
  <div class="card mb-20">
    <div class="card-header"><div class="card-title">Slot konsultasi dosen</div><span class="meta">Pesan slot kosong, jadwal langsung terkonfirmasi</span></div>
    <div class="card-body">
      <?php if (!$slotTersedia): ?>
        <div class="empty-state"><div class="empty-sub">Dosen belum membuka slot konsultasi. Anda tetap bisa mengajukan jadwal sendiri lewat form di bawah.</div></div>
      <?php else: ?>
        <div class="slot-grid">
          <?php foreach ($slotTersedia as $s): $sisa = $s['kuota'] - $s['terisi']; ?>
            <div class="slot-item <?= $s['milik_saya'] ? 'milik' : ($sisa <= 0 ? 'penuh' : '') ?>">
              <div class="fw-500"><?= tgl($s['tgl'], 'hari') ?></div>
              <div class="meta"><?= substr($s['jam_mulai'], 0, 5) ?>–<?= substr($s['jam_selesai'], 0, 5) ?> · <?= e(label($s['metode'])) ?></div>
              <?php if ($s['lokasi_link']): ?><div class="meta"><?= e(mb_strimwidth($s['lokasi_link'], 0, 40, '…')) ?></div><?php endif; ?>
              <?php if ($s['milik_saya']): ?>
                <span class="badge success" style="margin-top:8px;"><span class="badge-dot"></span>Sudah Anda pesan</span>
              <?php elseif ($sisa <= 0): ?>
                <span class="badge neutral" style="margin-top:8px;">Penuh</span>
              <?php else: ?>
                <details class="slot-pesan">
                  <summary class="btn btn-secondary btn-sm">Pesan (sisa <?= $sisa ?>)</summary>
                  <form method="POST"><?= csrfField() ?>
                    <input type="hidden" name="aksi" value="pesan_slot"><input type="hidden" name="id_slot" value="<?= (int)$s['id_slot'] ?>">
                    <label class="form-label" for="topik<?= (int)$s['id_slot'] ?>">Topik</label>
                    <textarea class="form-input" id="topik<?= (int)$s['id_slot'] ?>" name="topik" rows="2" required placeholder="cth: Revisi Bab II"></textarea>
                    <button class="btn btn-gold btn-sm btn-full" style="margin-top:6px;">Pesan slot</button>
                  </form>
                </details>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="grid-2">
    <div class="form-card">
      <h2>Ajukan Jadwal Sendiri</h2>
      <p>Dosen akan menerima atau menolak permintaan Anda. Anda akan mendapat notifikasi.</p>
      <form method="POST"><?= csrfField() ?>
        <div class="form-group">
          <label for="f_tgl_sesi" class="form-label">Tanggal <span class="req" aria-hidden="true">*</span></label>
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
          <label for="f_topik" class="form-label">Topik Bimbingan <span class="req" aria-hidden="true">*</span></label>
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
