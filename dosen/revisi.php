<?php
// dosen/revisi.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('dosen');

$pageTitle  = 'Review & Revisi Dokumen';
$activePage = 'revisi_dokumen';

// Ambil ID Dosen dari User yang login
$id_user_dosen = userId();
$dosenData = fetchOne('SELECT id_dosen FROM dosen WHERE id_user=?', [$id_user_dosen]);
$id_dosen = $dosenData['id_dosen'];

// Handle Aksi Beri Revisi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi'])) {
    if ($_POST['aksi'] === 'beri_revisi') {
        $id_dokumen = (int)$_POST['id_dokumen'];
        $status_dok = trim((string)($_POST['status_dokumen'] ?? '')); // 'direvisi' atau 'disetujui'
        $prioritas  = trim((string)($_POST['prioritas'] ?? ''));
        $halaman    = mb_substr(trim((string)($_POST['halaman_revisi'] ?? '')), 0, 100);
        $catatan    = trim((string)($_POST['catatan_revisi'] ?? ''));

        // Dokumen harus milik mahasiswa bimbingan dosen yang sedang login
        $dokMilik = fetchOne(
            'SELECT d.id_dokumen, d.status FROM dokumen d
             JOIN bimbingan b ON b.id_mahasiswa = d.id_mahasiswa
             WHERE d.id_dokumen = ? AND b.id_dosen = ? AND b.status = "aktif"', [$id_dokumen, $id_dosen]
        );

        if (!$dokMilik) {
            setFlash('danger', 'Dokumen tidak ditemukan atau bukan milik mahasiswa bimbingan Anda.');
        } elseif ($dokMilik['status'] !== 'menunggu') {
            setFlash('danger', 'Dokumen ini sudah direview (' . label($dokMilik['status']) . '). Tunggu mahasiswa mengunggah versi baru, atau lanjutkan lewat Diskusi.');
        } elseif (!in_array($status_dok, ['direvisi', 'disetujui'], true)
                  || !in_array($prioritas, ['minor', 'mayor', 'kritis'], true)) {
            setFlash('danger', 'Status dokumen atau prioritas tidak valid.');
        } elseif (empty($catatan)) {
            setFlash('danger', 'Catatan revisi tidak boleh kosong.');
        } else {
            $db = getDB();
            $db->beginTransaction();
            try {
                // 1. Masukkan ke tabel revisi
                execute('INSERT INTO revisi (id_dokumen, id_dosen, tgl_revisi, prioritas, halaman_revisi, catatan_revisi, status) 
                         VALUES (?, ?, NOW(), ?, ?, ?, "belum")', 
                         [$id_dokumen, $id_dosen, $prioritas, $halaman, $catatan]);
                
                // 2. Update status di tabel dokumen
                execute('UPDATE dokumen SET status=? WHERE id_dokumen=?', [$status_dok, $id_dokumen]);

                // 3. Ambil info mahasiswa untuk notifikasi
                $dokInfo = fetchOne('SELECT d.nama_dokumen, m.id_user FROM dokumen d JOIN mahasiswa m ON d.id_mahasiswa = m.id_mahasiswa WHERE d.id_dokumen=?', [$id_dokumen]);
                
                if ($dokInfo) {
                    $pesanNotif = ($status_dok === 'disetujui') 
                        ? 'Dokumen '.$dokInfo['nama_dokumen'].' telah disetujui dengan catatan.' 
                        : 'Dosen pembimbing memberikan catatan revisi pada dokumen '.$dokInfo['nama_dokumen'].'.';
                    $tipeNotif = ($status_dok === 'disetujui') ? 'sukses' : 'peringatan';
                    
                    kirimNotif((int)$dokInfo['id_user'], 'Update Revisi Dokumen', $pesanNotif, $tipeNotif, 'dokumen', 'mahasiswa/dokumen.php');
                }

                $db->commit();
                setFlash('success', 'Catatan revisi berhasil dikirim dan status dokumen telah diperbarui.');
            } catch (Throwable $e) {
                $db->rollBack();
                setFlash('danger', pesanGalat($e, 'Gagal menyimpan revisi'));
            }
        }
        header('Location: ' . BASE_URL . '/dosen/revisi.php');
        exit;
    }
}

// Ambil daftar dokumen milik mahasiswa yang dibimbing oleh dosen ini
// Diurutkan berdasarkan status "menunggu" agar berada di paling atas
$dokumenList = fetchAll(
    'SELECT d.*, m.nim, u.nama_lengkap AS nama_mhs,
            COUNT(r.id_revisi) AS total_revisi,
            (SELECT COUNT(*) FROM dokumen d2 WHERE d2.id_mahasiswa = d.id_mahasiswa AND d2.jenis_bab = d.jenis_bab) AS jml_versi
     FROM dokumen d
     JOIN mahasiswa m ON d.id_mahasiswa = m.id_mahasiswa
     JOIN user u ON m.id_user = u.id_user
     JOIN bimbingan b ON b.id_mahasiswa = m.id_mahasiswa
     LEFT JOIN revisi r ON r.id_dokumen = d.id_dokumen
     WHERE b.id_dosen = ? AND b.status = "aktif"
     GROUP BY d.id_dokumen
     ORDER BY CASE WHEN d.status = "menunggu" THEN 0 ELSE 1 END, d.tgl_upload DESC', 
    [$id_dosen]
);

$belumDibaca = pesanBelumDibaca();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">
  <?= tampilFlash() ?>

  <div class="page-header" style="display:flex; justify-content:space-between; align-items:flex-end;">
      <div>
          <h1>Review & Revisi Dokumen</h1>
          <p>Tinjau dokumen yang diunggah mahasiswa bimbingan Anda dan berikan catatan perbaikan.</p>
      </div>
      <div style="background:var(--white); border:1px solid var(--cream-dark); padding:10px 20px; border-radius:8px; text-align:center;">
          <div style="font-size:12px; color:var(--slate); margin-bottom:4px;">Menunggu Direview</div>
          <?php $pendingCount = count(array_filter($dokumenList, fn($d) => $d['status'] === 'menunggu')); ?>
          <div style="font-size:20px; font-weight:700; color:var(--warning);"><?= $pendingCount ?> Dokumen</div>
      </div>
  </div>

  <div class="card">
    <div class="card-body">
      <?php if (empty($dokumenList)): ?>
        <div class="empty-state">
            <div class="empty-icon">📁</div>
            <div class="empty-title">Belum Ada Dokumen</div>
            <div class="empty-sub">Mahasiswa bimbingan Anda belum mengunggah dokumen apapun.</div>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Mahasiswa</th>
                <th>Dokumen & Bab</th>
                <th>Versi</th>
                <th>Tanggal</th>
                <th>Status</th>
                <th class="text-right">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($dokumenList as $d): 
                  $jsonData = htmlspecialchars(json_encode($d), ENT_QUOTES, 'UTF-8');
              ?>
                <tr style="<?= $d['status'] === 'menunggu' ? 'background:rgba(245, 166, 35, 0.05);' : '' ?>">
                  <td>
                    <div style="font-weight:600; font-size:13px;"><?= e($d['nama_mhs']) ?></div>
                    <div class="mono meta-sm"><?= e($d['nim']) ?></div>
                  </td>
                  <td>
                    <div style="font-weight:500; font-size:13px;"><?= e($d['nama_dokumen']) ?></div>
                    <div class="meta-sm"><?= e(label($d['jenis_bab'])) ?> · <?= $d['ukuran_kb'] ?> KB</div>
                  </td>
                  <td><span class="mono" style="font-size:12px;background:var(--cream);padding:3px 8px;border-radius:4px;">v<?= $d['versi'] ?></span></td>
                  <td style="font-size:12px; color:var(--slate);"><?= tgl($d['tgl_upload']) ?></td>
                  <td><span class="badge <?= badge($d['status']) ?>"><span class="badge-dot"></span><?= e(strtoupper($d['status'])) ?></span></td>
                  <td class="text-right">
                    <div style="display:flex;gap:6px; justify-content:flex-end;">
                      <a href="<?= e(fileUrl($d['file_path'])) ?>" target="_blank" class="btn btn-secondary" style="padding:4px 8px; font-size:11px;" title="Unduh File">⬇️ Unduh</a>
                      <a href="<?= BASE_URL ?>/diskusi.php?dok=<?= (int)$d['id_dokumen'] ?>" class="btn btn-secondary" style="padding:4px 8px; font-size:11px;" title="Diskusi dengan mahasiswa"><?= icon('chat', 14) ?> Diskusi<?php $baru = $belumDibaca[$d['id_mahasiswa'] . '|' . $d['jenis_bab']] ?? 0; if ($baru): ?> <span class="badge info" title="<?= $baru ?> pesan belum dibaca"><?= $baru ?> baru</span><?php endif; ?></a>
                      <?php if ($d['jml_versi'] > 1): ?>
                        <a href="<?= BASE_URL ?>/bandingkan.php?dok=<?= (int)$d['id_dokumen'] ?>" class="btn btn-secondary" style="padding:4px 8px; font-size:11px;" title="Bandingkan dengan versi sebelumnya"><?= icon('columns', 14) ?> Bandingkan</a>
                      <?php endif; ?>
                      <?php if ($d['status'] === 'menunggu'): ?>
                        <button type="button" class="btn btn-primary" style="padding:4px 8px; font-size:11px;" onclick="openReviewModal(<?= $jsonData ?>)">✍️ Beri Revisi</button>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
                
                <?php
                // Tampilkan catatan revisi (mirip dengan sisi mahasiswa)
                $revisi = fetchAll('SELECT * FROM revisi WHERE id_dokumen=? ORDER BY tgl_revisi DESC', [$d['id_dokumen']]);
                foreach ($revisi as $rv): ?>
                  <tr style="background:rgba(11, 31, 58, 0.02);">
                    <td colspan="6" style="padding:10px 16px 10px 56px;">
                      <div style="font-size:12px;display:flex;gap:10px;align-items:flex-start;">
                        <span>📌</span>
                        <div>
                          <strong style="color:var(--navy);">Catatan Dosen</strong> ·
                          <span class="badge <?= badge($rv['prioritas']) ?>" style="font-size:10px;"><?= e(label($rv['prioritas'])) ?></span>
                          <?php if ($rv['halaman_revisi']): ?> · <span class="text-slate">Hal: <?= e($rv['halaman_revisi']) ?></span><?php endif; ?>
                          <div style="color:#4a5568;margin-top:3px; line-height:1.4;"><?= nl2br(e($rv['catatan_revisi'])) ?></div>
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

<div id="modalRevisi" style="display:none;position:fixed;inset:0;background:rgba(11,31,58,.6);z-index:1000;align-items:center;justify-content:center; padding:20px;">
  <div style="background:var(--white);border-radius:var(--radius);width:100%;max-width:500px;box-shadow:var(--shadow-lg);">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:24px 32px;border-bottom:1px solid var(--cream-dark);">
      <h2 style="font-family:'Playfair Display',serif;font-size:20px;margin:0;">Kirim Catatan Revisi</h2>
      <button type="button" onclick="document.getElementById('modalRevisi').style.display='none'" style="background:none;border:none;font-size:24px;cursor:pointer;">×</button>
    </div>
    
    <div style="padding:24px 32px;">
      <div style="margin-bottom:20px; background:var(--cream); padding:12px; border-radius:8px;">
          <div class="meta-sm mb-4">Mengulas Dokumen:</div>
          <div id="rev_nama_dokumen" style="font-weight:600; font-size:14px; color:var(--navy);">-</div>
          <div id="rev_nama_mhs" style="font-size:12px; color:var(--slate); margin-top:4px;">-</div>
      </div>

      <form method="POST"><?= csrfField() ?>
        <input type="hidden" name="aksi" value="beri_revisi">
        <input type="hidden" name="id_dokumen" id="rev_id_dokumen">
        
        <div class="form-row">
            <div class="form-group">
                <label for="f_status_dokumen" class="form-label">Keputusan Dokumen</label>
                <select id="f_status_dokumen" class="form-input" name="status_dokumen" required>
                    <option value="direvisi">⚠️ Perlu Revisi / Perbaikan</option>
                    <option value="disetujui">✅ Dokumen Disetujui</option>
                </select>
            </div>
            <div class="form-group">
                <label for="f_prioritas" class="form-label">Tingkat Prioritas</label>
                <select id="f_prioritas" class="form-input" name="prioritas" required>
                    <option value="kritis">Kritis</option>
                    <option value="mayor">Mayor</option>
                    <option value="minor" selected>Minor (saran)</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="f_halaman_revisi" class="form-label">Halaman / Bab (Opsional)</label>
            <input id="f_halaman_revisi" class="form-input" name="halaman_revisi" placeholder="Cth: Hal 12-14 atau Bab 2.3">
        </div>

        <div class="form-group">
            <label for="f_catatan_revisi" class="form-label">Catatan Revisi / Masukan</label>
            <textarea id="f_catatan_revisi" class="form-input" name="catatan_revisi" rows="4" required placeholder="Tuliskan detail perbaikan yang harus dilakukan mahasiswa..."></textarea>
        </div>

        <div class="form-actions" style="margin-top:24px;">
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalRevisi').style.display='none'">Batal</button>
            <button type="submit" class="btn btn-primary">Kirim Catatan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openReviewModal(data) {
    document.getElementById('rev_id_dokumen').value = data.id_dokumen;
    document.getElementById('rev_nama_dokumen').innerText = data.nama_dokumen + ' (v' + data.versi + ')';
    document.getElementById('rev_nama_mhs').innerText = 'Oleh: ' + data.nama_mhs + ' (' + data.nim + ')';
    
    document.getElementById('modalRevisi').style.display = 'flex';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>