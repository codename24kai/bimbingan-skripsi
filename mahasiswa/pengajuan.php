<?php
// mahasiswa/pengajuan.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('mahasiswa');

$pageTitle  = 'Pengajuan Judul';
$activePage = 'pengajuan';
$eid        = entityId();

// Get active bimbingan to know id_dosen
$bimbingan = fetchOne('SELECT * FROM bimbingan WHERE id_mahasiswa=? AND status="aktif" LIMIT 1', [$eid]);

// List dosen for selector
$listDosen = fetchAll('SELECT d.id_dosen,u.nama_lengkap,(d.kuota_bimbingan-d.terisi) AS sisa
                       FROM dosen d JOIN user u ON u.id_user=d.id_user
                       WHERE d.kuota_bimbingan > d.terisi ORDER BY u.nama_lengkap');

// Riwayat pengajuan
$riwayat = fetchAll('SELECT * FROM judul_skripsi WHERE id_mahasiswa=? ORDER BY tgl_pengajuan DESC', [$eid]);

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $judul_id  = trim($_POST['judul_indonesia'] ?? '');
    $judul_en  = trim($_POST['judul_inggris'] ?? '');
    $bidang    = trim($_POST['bidang_penelitian'] ?? '');
    $latar     = trim($_POST['latar_belakang'] ?? '');
    $metode    = trim($_POST['metode_penelitian'] ?? '');
    $id_dosen  = (int)($_POST['id_dosen'] ?? 0);

    if (!$judul_id || !$bidang || !$id_dosen) {
        setFlash('danger', 'Harap isi semua field yang wajib.');
    } else {
        $db = getDB();
        $db->beginTransaction();
        try {
            // Check iterasi
            $prev = fetchOne('SELECT COUNT(*) AS n FROM judul_skripsi WHERE id_mahasiswa=?', [$eid]);
            $iterasi = ($prev['n'] ?? 0) + 1;

            // Handle file upload
            $filePath = null;
            if (!empty($_FILES['file_proposal']['name'])) {
                $ext  = strtolower(pathinfo($_FILES['file_proposal']['name'], PATHINFO_EXTENSION));
                $name = 'proposal_' . $eid . '_' . time() . '.' . $ext;
                if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
                move_uploaded_file($_FILES['file_proposal']['tmp_name'], UPLOAD_DIR . $name);
                $filePath = 'uploads/' . $name;
            }

            // Insert judul
            $idJudul = execute(
                'INSERT INTO judul_skripsi (id_mahasiswa,judul_indonesia,judul_inggris,
                  bidang_penelitian,latar_belakang,metode_penelitian,iterasi,status,file_proposal)
                 VALUES (?,?,?,?,?,?,?,"diajukan",?)',
                [$eid,$judul_id,$judul_en,$bidang,$latar,$metode,$iterasi,$filePath]
            );

            // Create or update bimbingan
            if (!$bimbingan) {
                execute('INSERT INTO bimbingan (id_mahasiswa,id_dosen,id_judul,semester,tgl_mulai,status)
                         VALUES (?,?,?,?,CURDATE(),"aktif")',
                    [$eid,$id_dosen,$idJudul,'Genap ' . date('Y') . '/' . (date('Y')+1)]);
            }

            // Notifikasi dosen
            $dosenUser = fetchOne('SELECT d.id_user FROM dosen d WHERE d.id_dosen=?', [$id_dosen]);
            if ($dosenUser) {
                execute('INSERT INTO notifikasi (id_user,judul,pesan,tipe,modul) VALUES (?,?,?,?,?)',
                    [$dosenUser['id_user'],'Pengajuan Judul Baru',
                     'Mahasiswa mengajukan judul baru untuk ditinjau: ' . $judul_id,
                     'info','judul']);
            }

            $db->commit();
            setFlash('success', 'Pengajuan judul berhasil dikirim! Tunggu konfirmasi dari dosen pembimbing.');
            header('Location: ' . BASE_URL . '/mahasiswa/pengajuan.php');
            exit;
        } catch (\Exception $e) {
            $db->rollBack();
            setFlash('danger', 'Gagal menyimpan pengajuan: ' . $e->getMessage());
        }
    }
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">

  <?php $flash = getFlash(); if ($flash): ?>
    <div class="alert <?= $flash['type'] ?>"><?= e($flash['msg']) ?></div>
  <?php endif; ?>

  <div class="page-header">
    <h1>Pengajuan Judul Skripsi</h1>
    <p>Ajukan judul penelitian Anda untuk ditinjau oleh dosen pembimbing</p>
  </div>

  <div style="display:grid;grid-template-columns:1fr 320px;gap:20px;">
    <!-- FORM -->
    <div class="form-card">
      <h2>Form Pengajuan</h2>
      <p>Isi formulir dengan lengkap dan teliti sebelum mengajukan.</p>
      <form method="POST" enctype="multipart/form-data">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Judul Skripsi (Indonesia) <span style="color:red">*</span></label>
            <input class="form-input" name="judul_indonesia" required placeholder="Judul dalam Bahasa Indonesia" value="<?= e($_POST['judul_indonesia'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Judul Skripsi (Inggris)</label>
            <input class="form-input" name="judul_inggris" placeholder="Title in English" value="<?= e($_POST['judul_inggris'] ?? '') ?>">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Bidang Penelitian <span style="color:red">*</span></label>
            <select class="form-input" name="bidang_penelitian" required>
              <option value="">Pilih bidang...</option>
              <?php foreach (['Kecerdasan Buatan / AI','Jaringan Komputer','Rekayasa Perangkat Lunak','Sistem Informasi','Keamanan Siber','Data Science','IoT','Lainnya'] as $b): ?>
                <option <?= ($_POST['bidang_penelitian']??'')===$b?'selected':'' ?>><?= $b ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Dosen Pembimbing <span style="color:red">*</span></label>
            <select class="form-input" name="id_dosen" required <?= $bimbingan ? 'disabled' : '' ?>>
              <option value="">Pilih dosen...</option>
              <?php foreach ($listDosen as $d): ?>
                <option value="<?= $d['id_dosen'] ?>" <?= ($bimbingan['id_dosen']??'')==$d['id_dosen']?'selected':'' ?>>
                  <?= e($d['nama_lengkap']) ?> (Sisa: <?= $d['sisa'] ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <?php if ($bimbingan): ?>
              <input type="hidden" name="id_dosen" value="<?= $bimbingan['id_dosen'] ?>">
              <div style="font-size:12px;color:var(--slate);margin-top:4px;">Dosen sudah terhubung dari bimbingan aktif.</div>
            <?php endif; ?>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Latar Belakang & Rumusan Masalah</label>
          <textarea class="form-input" name="latar_belakang" placeholder="Deskripsikan latar belakang dan rumusan masalah penelitian Anda..."><?= e($_POST['latar_belakang'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Metode Penelitian</label>
          <input class="form-input" name="metode_penelitian" placeholder="cth: Eksperimen, Prototype, Survey..." value="<?= e($_POST['metode_penelitian'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Lampiran Proposal (PDF/DOCX, maks 10MB)</label>
          <div class="upload-zone">
            <div class="upload-icon">📄</div>
            <div class="upload-label">Klik atau seret file ke sini</div>
            <div class="upload-sub">PDF, DOCX maks. 10 MB</div>
            <input type="file" name="file_proposal" accept=".pdf,.docx" style="position:absolute;inset:0;opacity:0;cursor:pointer;">
          </div>
        </div>
        <div class="form-actions">
          <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-secondary">Batal</a>
          <button type="submit" class="btn btn-gold">✈️ Kirim Pengajuan</button>
        </div>
      </form>
    </div>

    <!-- RIWAYAT -->
    <div>
      <div class="card">
        <div class="card-header"><div class="card-title">Riwayat Pengajuan</div></div>
        <div class="card-body">
          <?php if (empty($riwayat)): ?>
            <div class="empty-state"><div class="empty-sub">Belum ada pengajuan.</div></div>
          <?php else: ?>
            <?php foreach ($riwayat as $r): ?>
              <div style="border:1px solid var(--cream-dark);border-radius:8px;padding:14px;margin-bottom:10px;">
                <div style="font-size:12px;font-weight:600;color:var(--navy);margin-bottom:6px;">
                  Iterasi <?= $r['iterasi'] ?> — <?= date('d M Y', strtotime($r['tgl_pengajuan'])) ?>
                </div>
                <div style="font-size:13px;color:#4a5568;margin-bottom:8px;"><?= e(mb_strimwidth($r['judul_indonesia'],0,80,'...')) ?></div>
                <span class="badge <?= badge($r['status']) ?>"><span class="badge-dot"></span><?= e($r['status']) ?></span>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
