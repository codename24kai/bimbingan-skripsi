<?php
// mahasiswa/dokumen.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('mahasiswa');

$pageTitle  = 'Dokumen & Revisi';
$activePage = 'dokumen';
$eid        = entityId();

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'upload') {
        // Validasi Sesi
        if (!$eid) {
            setFlash('danger', 'Sesi mahasiswa tidak valid atau data profil belum lengkap. Harap hubungi admin.');
            header('Location: ' . BASE_URL . '/mahasiswa/dokumen.php');
            exit;
        }

        $nama  = trim($_POST['nama_dokumen'] ?? '');
        $jenis = trim($_POST['jenis_bab'] ?? '');

        if (!$nama || !$jenis || empty($_FILES['file']['name'])) {
            setFlash('danger', 'Harap isi semua field dan pilih file.');
        } else {
            $db = getDB();
            $db->beginTransaction();
            try {
                // Upload Error Check
                if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Terjadi kesalahan saat mengunggah file (Kode: ' . $_FILES['file']['error'] . ').');
                }

                // Extension Check
                $ext     = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
                $allowed = ['pdf', 'docx'];
                if (!in_array($ext, $allowed)) {
                    throw new Exception('Format file tidak didukung. Harap unggah PDF atau DOCX.');
                }

                // Size Check (max 10MB)
                $maxSize = 10 * 1024 * 1024;
                if ($_FILES['file']['size'] > $maxSize) {
                    throw new Exception('Ukuran file terlalu besar. Maksimal 10 MB.');
                }

                // Perbaikan 1: Menggunakan uniqid() untuk keamanan nama file
                $fname = 'dok_' . $eid . '_' . uniqid() . '.' . $ext;
                $size  = (int)ceil($_FILES['file']['size'] / 1024);

                if (!is_dir(UPLOAD_DIR)) {
                    if (!mkdir(UPLOAD_DIR, 0755, true)) {
                        throw new Exception('Gagal membuat direktori upload.');
                    }
                }

                if (!move_uploaded_file($_FILES['file']['tmp_name'], UPLOAD_DIR . $fname)) {
                    throw new Exception('Gagal menyimpan file ke server.');
                }

                // Cek versi sebelumnya
                $prev = fetchOne('SELECT MAX(versi) AS v FROM dokumen WHERE id_mahasiswa=? AND jenis_bab=?', [$eid, $jenis]);
                $versi = ($prev['v'] ?? 0) + 1;

                // Perbaikan 2: Ubah status dokumen versi lama menjadi "direvisi" secara otomatis
                if ($versi > 1) {
                    execute('UPDATE dokumen SET status="direvisi" WHERE id_mahasiswa=? AND jenis_bab=? AND versi < ?', [$eid, $jenis, $versi]);
                }

                // Insert dokumen baru
                execute('INSERT INTO dokumen (id_mahasiswa,nama_dokumen,jenis_bab,versi,file_path,ukuran_kb,format_file,status)
                         VALUES (?,?,?,?,?,?,"pdf","menunggu")',
                    [$eid, $nama, $jenis, $versi, 'uploads/'.$fname, $size]);

                $db->commit();
                setFlash('success', 'Dokumen berhasil diunggah. Versi telah diperbarui.');
                header('Location: ' . BASE_URL . '/mahasiswa/dokumen.php');
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                setFlash('danger', $e->getMessage());
            }
        }
    }
}

$dokumen = fetchAll(
    'SELECT d.*, COUNT(r.id_revisi) AS total_revisi,
            SUM(CASE WHEN r.status="belum" THEN 1 ELSE 0 END) AS revisi_pending
     FROM dokumen d
     LEFT JOIN revisi r ON r.id_dokumen=d.id_dokumen
     WHERE d.id_mahasiswa=?
     GROUP BY d.id_dokumen
     ORDER BY d.jenis_bab, d.versi DESC', [$eid]
);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">
  <?php $flash = getFlash(); if ($flash): ?><div class="alert <?= $flash['type'] ?>"><?= e($flash['msg']) ?></div><?php endif; ?>

  <div class="page-header">
    <h1>Dokumen & Revisi</h1>
    <p>Kelola semua dokumen skripsi dan riwayat revisi Anda</p>
  </div>

  <div style="display:grid;grid-template-columns:1fr 320px;gap:20px;">
    <div>
      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><div class="card-title">Daftar Dokumen</div></div>
        <div class="card-body">
          <?php if (empty($dokumen)): ?>
            <div class="empty-state"><div class="empty-icon">📁</div><div class="empty-title">Belum Ada Dokumen</div><div class="empty-sub">Upload dokumen skripsi Anda di samping.</div></div>
          <?php else: ?>
            <div class="table-wrap">
              <table>
                <thead><tr><th>Dokumen</th><th>Versi</th><th>Diunggah</th><th>Status</th><th>Revisi</th><th>Aksi</th></tr></thead>
                <tbody>
                  <?php foreach ($dokumen as $d): ?>
                    <tr>
                      <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                          <span style="font-size:20px;">📄</span>
                          <div>
                            <div style="font-weight:500;"><?= e($d['nama_dokumen']) ?></div>
                            <div style="font-size:12px;color:var(--slate);"><?= e($d['jenis_bab']) ?> · <?= $d['ukuran_kb'] ?> KB</div>
                          </div>
                        </div>
                      </td>
                      <td><span class="mono" style="font-size:12px;background:var(--cream);padding:3px 8px;border-radius:4px;">v<?= $d['versi'] ?></span></td>
                      <td style="font-size:13px;color:var(--slate);"><?= date('d M Y', strtotime($d['tgl_upload'])) ?></td>
                      <td><span class="badge <?= badge($d['status']) ?>"><span class="badge-dot"></span><?= e(strtoupper($d['status'])) ?></span></td>
                      <td>
                        <?php if ($d['revisi_pending'] > 0): ?>
                          <span class="badge warning"><span class="badge-dot"></span><?= $d['revisi_pending'] ?> pending</span>
                        <?php else: ?>
                          <span style="font-size:13px;color:var(--slate);">—</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div style="display:flex;gap:6px;">
                          <a href="<?= BASE_URL . '/' . $d['file_path'] ?>" target="_blank" class="btn-icon" title="Unduh File">⬇️</a>
                        </div>
                      </td>
                    </tr>
                    <?php
                    // Menampilkan catatan revisi jika ada
                    $revisi = fetchAll('SELECT r.*,ud.nama_lengkap AS nama_dosen FROM revisi r
                                       JOIN dosen d ON d.id_dosen=r.id_dosen
                                       JOIN user ud ON ud.id_user=d.id_user
                                       WHERE r.id_dokumen=?
                                       ORDER BY r.tgl_revisi DESC', [$d['id_dokumen']]);
                    foreach ($revisi as $rv): ?>
                      <tr style="background:rgba(245,166,35,0.04);">
                        <td colspan="6" style="padding:10px 16px 10px 56px;">
                          <div style="font-size:12px;display:flex;gap:10px;align-items:flex-start;">
                            <span>📌</span>
                            <div>
                              <strong><?= e($rv['nama_dosen']) ?></strong> ·
                              <span class="badge <?= badge($rv['prioritas']) ?>" style="font-size:10px;"><?= $rv['prioritas'] ?></span>
                              <?php if ($rv['halaman_revisi']): ?> · <span style="color:var(--slate);">Hal: <?= e($rv['halaman_revisi']) ?></span><?php endif; ?>
                              <div style="color:#4a5568;margin-top:3px; line-height:1.4;"><?= e($rv['catatan_revisi']) ?></div>
                            </div>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="form-card" style="height:fit-content;">
      <h2>Unggah Dokumen</h2>
      <p>Upload dokumen skripsi terbaru Anda</p>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="upload">
        <div class="form-group">
          <label class="form-label">Nama Dokumen</label>
          <input class="form-input" name="nama_dokumen" required placeholder="cth: Revisi BAB I - Pendahuluan">
        </div>
        <div class="form-group">
          <label class="form-label">Jenis / Bab</label>
          <select class="form-input" name="jenis_bab" required>
            <option value="" disabled selected>Pilih bab...</option>
            <?php foreach (['BAB I','BAB II','BAB III','BAB IV','BAB V','Proposal','Lampiran', 'Lainnya'] as $b): ?>
              <option value="<?= $b ?>"><?= $b ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <div class="upload-zone" id="uploadZone">
            <div class="upload-icon">📤</div>
            <div class="upload-label" id="fileLabelDok">Klik atau seret file PDF/DOCX</div>
            <div class="upload-sub">Ukuran maksimal 10 MB</div>
            <input type="file" name="file" required accept=".pdf,.docx,.doc" style="position:absolute;inset:0;opacity:0;cursor:pointer;" onchange="document.getElementById('fileLabelDok').innerText = this.files[0] ? this.files[0].name : 'Klik atau seret file PDF/DOCX'">
          </div>
        </div>
        <button type="submit" class="btn btn-gold btn-full">📤 Upload Dokumen</button>
      </form>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>