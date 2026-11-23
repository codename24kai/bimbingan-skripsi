<?php
// mahasiswa/dokumen.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('mahasiswa');

$pageTitle  = 'Dokumen & Revisi';
$activePage = 'dokumen';
$eid        = entityId();

// Handle upload, edit, hapus
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Validasi Sesi
    if (!$eid) {
        setFlash('danger', 'Sesi mahasiswa tidak valid atau data profil belum lengkap. Harap hubungi admin.');
        header('Location: ' . BASE_URL . '/mahasiswa/dokumen.php');
        exit;
    }

    if ($_POST['action'] === 'upload') {
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

                // Ubah status dokumen versi lama menjadi "direvisi" secara otomatis
                if ($versi > 1) {
                    execute('UPDATE dokumen SET status="direvisi" WHERE id_mahasiswa=? AND jenis_bab=? AND versi < ?', [$eid, $jenis, $versi]);
                }

                // Insert dokumen baru
                execute('INSERT INTO dokumen (id_mahasiswa,nama_dokumen,jenis_bab,versi,file_path,ukuran_kb,format_file,status)
                         VALUES (?,?,?,?,?,?,"pdf","menunggu")',
                    [$eid, $nama, $jenis, $versi, 'uploads/'.$fname, $size]);

                $db->commit();
                setFlash('success', 'Dokumen berhasil diunggah. Versi telah diperbarui.');
            } catch (Exception $e) {
                $db->rollBack();
                setFlash('danger', $e->getMessage());
            }
        }
        
    } elseif ($_POST['action'] === 'edit') {
        $id_dok = (int)$_POST['id_dokumen'];
        $nama   = trim($_POST['nama_dokumen'] ?? '');
        $jenis  = trim($_POST['jenis_bab'] ?? '');
        
        if ($nama && $jenis) {
            execute('UPDATE dokumen SET nama_dokumen=?, jenis_bab=? WHERE id_dokumen=? AND id_mahasiswa=?', [$nama, $jenis, $id_dok, $eid]);
            setFlash('success', 'Metadata dokumen berhasil diperbarui.');
        }
        
    } elseif ($_POST['action'] === 'hapus') {
        $id_dok = (int)$_POST['id_dokumen'];
        
        // Ambil path file untuk menghapus file fisik di server
        $dok = fetchOne('SELECT file_path FROM dokumen WHERE id_dokumen=? AND id_mahasiswa=?', [$id_dok, $eid]);
        if ($dok) {
            $filePath = __DIR__ . '/../' . $dok['file_path'];
            if (file_exists($filePath)) {
                @unlink($filePath); // Hapus file fisik
            }
            
            // Hapus data dari database
            execute('DELETE FROM dokumen WHERE id_dokumen=? AND id_mahasiswa=?', [$id_dok, $eid]);
            setFlash('success', 'Dokumen beserta filenya berhasil dihapus secara permanen.');
        }
    }
    
    header('Location: ' . BASE_URL . '/mahasiswa/dokumen.php');
    exit;
}

$dokumen = fetchAll(
    'SELECT d.*, COUNT(r.id_revisi) AS total_revisi,
            SUM(CASE WHEN r.status="belum" THEN 1 ELSE 0 END) AS revisi_pending,
            (SELECT COUNT(*) FROM komentar_dokumen k WHERE k.id_dokumen = d.id_dokumen) AS jml_pesan,
            (SELECT COUNT(*) FROM dokumen d2 WHERE d2.id_mahasiswa = d.id_mahasiswa AND d2.jenis_bab = d.jenis_bab) AS jml_versi
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

  <div class="split" style="--side:320px;">
    <div>
      <div class="card mb-20">
        <div class="card-header"><div class="card-title">Daftar Dokumen</div></div>
        <div class="card-body">
          <?php if (empty($dokumen)): ?>
            <div class="empty-state"><div class="empty-icon">📁</div><div class="empty-title">Belum Ada Dokumen</div><div class="empty-sub">Upload dokumen skripsi Anda di samping.</div></div>
          <?php else: ?>
            <div class="table-wrap">
              <table>
                <thead><tr><th>Dokumen</th><th>Versi</th><th>Diunggah</th><th>Status</th><th>Revisi</th><th class="text-right">Aksi</th></tr></thead>
                <tbody>
                  <?php foreach ($dokumen as $d): 
                      $jsonData = htmlspecialchars(json_encode($d), ENT_QUOTES, 'UTF-8');
                  ?>
                    <tr>
                      <td>
                        <div class="row-10">
                          <span style="font-size:20px;">📄</span>
                          <div>
                            <div class="fw-500"><?= e($d['nama_dokumen']) ?></div>
                            <div class="meta"><?= e(label($d['jenis_bab'])) ?> · <?= $d['ukuran_kb'] ?> KB</div>
                          </div>
                        </div>
                      </td>
                      <td><span class="mono" style="font-size:12px;background:var(--cream);padding:3px 8px;border-radius:4px;">v<?= $d['versi'] ?></span></td>
                      <td style="font-size:13px;color:var(--slate);"><?= tgl($d['tgl_upload']) ?></td>
                      <td><span class="badge <?= badge($d['status']) ?>"><span class="badge-dot"></span><?= e(strtoupper($d['status'])) ?></span></td>
                      <td>
                        <?php if ($d['revisi_pending'] > 0): ?>
                          <span class="badge warning"><span class="badge-dot"></span><?= $d['revisi_pending'] ?> pending</span>
                        <?php else: ?>
                          <span style="font-size:13px;color:var(--slate);">—</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-right">
                        <div style="display:flex;gap:6px; justify-content:flex-end;">
                          <a href="<?= e(fileUrl($d['file_path'])) ?>" target="_blank" class="btn-icon" title="Unduh File" aria-label="Unduh <?= e($d['nama_dokumen']) ?>"><?= icon('download') ?></a>
                          <a href="<?= BASE_URL ?>/diskusi.php?dok=<?= (int)$d['id_dokumen'] ?>" class="btn-icon btn-icon--hitung" title="Diskusi dengan dosen" aria-label="Diskusi <?= e($d['nama_dokumen']) ?>"><?= icon('chat') ?><?php if ($d['jml_pesan']): ?><span class="hitung"><?= (int)$d['jml_pesan'] ?></span><?php endif; ?></a>
                          <?php if ($d['jml_versi'] > 1): ?>
                            <a href="<?= BASE_URL ?>/bandingkan.php?dok=<?= (int)$d['id_dokumen'] ?>" class="btn-icon" title="Bandingkan versi" aria-label="Bandingkan versi <?= e($d['nama_dokumen']) ?>"><?= icon('columns') ?></a>
                          <?php endif; ?>
                          <button type="button" class="btn-icon" title="Edit Dokumen" aria-label="Edit Dokumen <?= e($d['nama_dokumen']) ?>" onclick="openEditModal(<?= $jsonData ?>)"><?= icon('pencil') ?></button>
                          <form method="POST" style="display:inline;" data-confirm="Hapus dokumen <?= e($d['nama_dokumen']) ?> (v<?= (int)$d['versi'] ?>)? File akan dihapus permanen." data-confirm-ok="Hapus"><?= csrfField() ?>
                              <input type="hidden" name="action" value="hapus">
                              <input type="hidden" name="id_dokumen" value="<?= $d['id_dokumen'] ?>">
                              <button type="submit" class="btn-icon btn-icon--danger" title="Hapus Dokumen" aria-label="Hapus Dokumen <?= e($d['nama_dokumen']) ?>"><?= icon('trash') ?></button>
                          </form>
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
                              <span class="badge <?= badge($rv['prioritas']) ?>" style="font-size:10px;"><?= e(label($rv['prioritas'])) ?></span>
                              <?php if ($rv['halaman_revisi']): ?> · <span class="text-slate">Hal: <?= e($rv['halaman_revisi']) ?></span><?php endif; ?>
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
      <form method="POST" enctype="multipart/form-data"><?= csrfField() ?>
        <input type="hidden" name="action" value="upload">
        <div class="form-group">
          <label for="f_nama_dokumen" class="form-label">Nama Dokumen</label>
          <input id="f_nama_dokumen" class="form-input" name="nama_dokumen" required placeholder="cth: Revisi BAB I - Pendahuluan">
        </div>
        <div class="form-group">
          <label for="f_jenis_bab" class="form-label">Jenis / Bab</label>
          <select id="f_jenis_bab" class="form-input" name="jenis_bab" required>
            <option value="" disabled selected>Pilih bab...</option>
            <?php foreach (['BAB I','BAB II','BAB III','BAB IV','BAB V','Proposal','Lampiran', 'Lainnya'] as $b): ?>
              <option value="<?= $b ?>"><?= $b ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="f_file" class="form-label">File dokumen</label>
          <?= uploadZone('file', 'Klik atau seret file PDF/DOCX', '.pdf,.docx,.doc', 'Ukuran maksimal 10 MB', true) ?>
        </div>
        <button type="submit" class="btn btn-gold btn-full">📤 Upload Dokumen</button>
      </form>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modalEdit" style="display:none;">
  <div style="background:var(--white);border-radius:var(--radius);padding:32px;width:100%;max-width:400px;box-shadow:var(--shadow-lg);">
    <div class="modal-head">
      <h2 class="modal-title">Edit Data Dokumen</h2>
      <button type="button" aria-label="Tutup" class="modal-close" type="button" onclick="document.getElementById('modalEdit').style.display='none'">×</button>
    </div>
    <form method="POST"><?= csrfField() ?>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id_dokumen" id="edit_id_dok">
      
      <div class="form-group">
        <label for="edit_nama" class="form-label">Nama Dokumen</label>
        <input class="form-input" name="nama_dokumen" id="edit_nama" required>
      </div>
      
      <div class="form-group">
        <label for="edit_jenis" class="form-label">Jenis / Bab</label>
        <select class="form-input" name="jenis_bab" id="edit_jenis" required>
          <?php foreach (['BAB I','BAB II','BAB III','BAB IV','BAB V','Proposal','Lampiran', 'Lainnya'] as $b): ?>
            <option value="<?= $b ?>"><?= $b ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <div class="alert alert-static info" style="font-size:12px; padding:10px;">
          Fitur ini hanya untuk mengubah metadata (Nama dan Jenis Bab). Jika Anda ingin memperbarui isi file, silakan <b>Unggah Dokumen</b> baru di panel sebelah kanan agar sistem dapat menaikkan versinya (v2, v3, dst).
      </div>
      
      <div class="form-actions" style="margin-top:20px;">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalEdit').style.display='none'">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(data) {
    document.getElementById('edit_id_dok').value = data.id_dokumen;
    document.getElementById('edit_nama').value = data.nama_dokumen;
    document.getElementById('edit_jenis').value = data.jenis_bab;
    document.getElementById('modalEdit').style.display = 'flex';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>