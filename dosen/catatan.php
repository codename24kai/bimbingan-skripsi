<?php
// dosen/catatan.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('dosen');

$pageTitle  = 'Catatan Bimbingan';
$activePage = 'catatan';
$eid        = entityId();
$id_bimb    = (int)($_GET['id_bimbingan'] ?? 0);

// Get all bimbingan for selector
$listBimb = fetchAll(
    'SELECT b.id_bimbingan,um.nama_lengkap,m.nim
     FROM bimbingan b
     JOIN mahasiswa m ON m.id_mahasiswa=b.id_mahasiswa
     JOIN user um ON um.id_user=m.id_user
     WHERE b.id_dosen=? AND b.status="aktif" ORDER BY um.nama_lengkap', [$eid]
);

// Handle POST: add catatan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_sesi   = (int)($_POST['id_sesi'] ?? 0);
    $isi       = trim($_POST['isi_catatan'] ?? '');
    $keputusan = $_POST['keputusan'] ?? 'disetujui';
    $bab       = trim($_POST['bab_terkait'] ?? '');
    $backUrl   = BASE_URL . '/dosen/catatan.php' . ($id_bimb ? '?id_bimbingan='.$id_bimb : '');

    // Pastikan sesi ini milik mahasiswa bimbingan dosen yang sedang login
    $sesiMilik = fetchOne(
        'SELECT sb.id_sesi FROM sesi_bimbingan sb
         JOIN bimbingan b ON b.id_bimbingan = sb.id_bimbingan
         WHERE sb.id_sesi = ? AND b.id_dosen = ?', [$id_sesi, $eid]
    );
    if (!$sesiMilik) {
        setFlash('danger', 'Sesi bimbingan tidak ditemukan atau bukan milik mahasiswa bimbingan Anda.');
        header('Location: ' . $backUrl);
        exit;
    }
    if ($isi === '' || !in_array($keputusan, ['disetujui', 'revisi', 'ditolak'], true)) {
        setFlash('danger', 'Isi catatan dan keputusan wajib diisi dengan benar.');
        header('Location: ' . $backUrl);
        exit;
    }

    // Also update sesi status to selesai
    execute('UPDATE sesi_bimbingan SET status="selesai" WHERE id_sesi=?', [$id_sesi]);
    execute('INSERT INTO catatan_bimbingan (id_sesi,isi_catatan,keputusan,bab_terkait) VALUES (?,?,?,?)',
        [$id_sesi, $isi, $keputusan, $bab]);

    // Notify mahasiswa
    $sesi = fetchOne('SELECT sb.id_bimbingan FROM sesi_bimbingan sb WHERE sb.id_sesi=?', [$id_sesi]);
    if ($sesi) {
        $mhsUser = fetchOne('SELECT m.id_mahasiswa,u.id_user FROM bimbingan b
                             JOIN mahasiswa m ON m.id_mahasiswa=b.id_mahasiswa
                             JOIN user u ON u.id_user=m.id_user
                             WHERE b.id_bimbingan=?', [$sesi['id_bimbingan']]);
        if ($mhsUser) {
            execute('INSERT INTO notifikasi (id_user,judul,pesan,tipe,modul) VALUES (?,?,?,?,?)',
                [$mhsUser['id_user'], 'Catatan Bimbingan Baru',
                 'Dosen telah menambahkan catatan bimbingan. Keputusan: ' . $keputusan,
                 $keputusan==='disetujui' ? 'sukses' : 'peringatan', 'bimbingan']);
        }
    }
    setFlash('success', 'Catatan bimbingan berhasil disimpan.');
    header('Location: ' . $backUrl);
    exit;
}

// Sesi belum ada catatan (tersedia untuk diberi catatan)
$sesiAvail = [];
if ($id_bimb) {
    $sesiAvail = fetchAll(
        'SELECT sb.* FROM sesi_bimbingan sb
         LEFT JOIN catatan_bimbingan cb ON cb.id_sesi=sb.id_sesi
         WHERE sb.id_bimbingan=? AND cb.id_catatan IS NULL AND sb.status != "batal"
         ORDER BY sb.tgl_sesi DESC', [$id_bimb]
    );
}

// Get catatan list
$catatan = [];
if ($id_bimb) {
    $catatan = fetchAll(
        'SELECT cb.*,sb.tgl_sesi,sb.topik,um.nama_lengkap AS nama_mhs
         FROM catatan_bimbingan cb
         JOIN sesi_bimbingan sb ON sb.id_sesi=cb.id_sesi
         JOIN bimbingan b ON b.id_bimbingan=sb.id_bimbingan
         JOIN mahasiswa m ON m.id_mahasiswa=b.id_mahasiswa
         JOIN user um ON um.id_user=m.id_user
         WHERE sb.id_bimbingan=?
         ORDER BY sb.tgl_sesi DESC', [$id_bimb]
    );
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">
  <?php $flash = getFlash(); if ($flash): ?><div class="alert <?= $flash['type'] ?>"><?= e($flash['msg']) ?></div><?php endif; ?>
  <div class="page-header"><h1>Catatan Bimbingan</h1><p>Tambah dan kelola catatan untuk mahasiswa bimbingan</p></div>

  <!-- Pilih mahasiswa -->
  <div class="card" style="margin-bottom:20px;">
    <div class="card-body" style="padding:16px 24px;">
      <form method="GET" style="display:flex;gap:12px;align-items:center;">
        <label class="form-label" style="margin:0;white-space:nowrap;">Pilih Mahasiswa:</label>
        <select class="form-input" name="id_bimbingan" style="max-width:280px;" onchange="this.form.submit()">
          <option value="">-- Pilih --</option>
          <?php foreach ($listBimb as $lb): ?>
            <option value="<?= $lb['id_bimbingan'] ?>" <?= $id_bimb===$lb['id_bimbingan']?'selected':'' ?>>
              <?= e($lb['nama_lengkap']) ?> (<?= e($lb['nim']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
  </div>

  <?php if ($id_bimb): ?>
  <div style="display:grid;grid-template-columns:1fr 360px;gap:20px;">
    <div>
      <?php if (empty($catatan)): ?>
        <div class="card"><div class="card-body"><div class="empty-state"><div class="empty-sub">Belum ada catatan untuk mahasiswa ini.</div></div></div></div>
      <?php else: ?>
        <?php foreach ($catatan as $c):
          $cls = $c['keputusan']==='disetujui' ? 'approved' : ($c['keputusan']==='revisi' ? 'revision' : 'rejected');
        ?>
          <div class="catatan-card <?= $cls ?>">
            <div class="catatan-header">
              <div>
                <div class="catatan-dosen"><?= e($c['topik']) ?></div>
                <div style="margin-top:6px;">
                  <span class="badge <?= badge($c['keputusan']) ?>"><span class="badge-dot"></span><?= $c['keputusan'] ?></span>
                  <?php if ($c['bab_terkait']): ?><span class="badge neutral" style="margin-left:6px;"><?= e($c['bab_terkait']) ?></span><?php endif; ?>
                </div>
              </div>
              <div class="catatan-date"><?= date('d M Y', strtotime($c['tgl_sesi'])) ?></div>
            </div>
            <div class="catatan-text"><?= e($c['isi_catatan']) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Form tambah catatan -->
    <div class="form-card" style="height:fit-content;">
      <h2>Tambah Catatan</h2>
      <p>Buat catatan setelah sesi selesai</p>
      <?php if (empty($sesiAvail)): ?>
        <div class="alert info">Tidak ada sesi yang perlu dicatat saat ini.</div>
      <?php else: ?>
        <form method="POST"><?= csrfField() ?>
          <div class="form-group">
            <label class="form-label">Pilih Sesi</label>
            <select class="form-input" name="id_sesi" required>
              <?php foreach ($sesiAvail as $s): ?>
                <option value="<?= $s['id_sesi'] ?>"><?= date('d M Y', strtotime($s['tgl_sesi'])) ?> — <?= e(mb_strimwidth($s['topik'],0,40,'...')) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Keputusan</label>
            <select class="form-input" name="keputusan" required>
              <option value="disetujui">✅ Disetujui</option>
              <option value="revisi">✏️ Perlu Revisi</option>
              <option value="ditolak">❌ Ditolak</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Bab Terkait</label>
            <input class="form-input" name="bab_terkait" placeholder="cth: BAB II">
          </div>
          <div class="form-group">
            <label class="form-label">Isi Catatan</label>
            <textarea class="form-input" name="isi_catatan" required placeholder="Tuliskan catatan bimbingan..."></textarea>
          </div>
          <button type="submit" class="btn btn-gold btn-full">💾 Simpan Catatan</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
