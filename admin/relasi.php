<?php
// admin/relasi.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/sk.php';
requireRole('admin');

$pageTitle  = 'Relasi Pembimbingan';
$activePage = 'relasi';

// Handle create/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    
    // Logika ini menangani baik alur 'Ajuan' maupun 'Manual'.
    // Nomor SK dan file PDF SK dibuat otomatis (config/sk.php).
    if ($aksi === 'buat') {
        $id_mhs   = (int)($_POST['id_mahasiswa'] ?? 0);
        $id_dosen = (int)($_POST['id_dosen'] ?? 0);
        $id_judul = (int)($_POST['id_judul'] ?? 0);
        $semester = trim($_POST['semester'] ?? '');

        // Validasi: Pastikan mahasiswa tidak memiliki bimbingan aktif ganda
        $existing = countRows('SELECT COUNT(*) FROM bimbingan WHERE id_mahasiswa=? AND status="aktif"', [$id_mhs]);
        
        $judulOk = countRows('SELECT COUNT(*) FROM judul_skripsi WHERE id_judul=? AND id_mahasiswa=? AND status="disetujui"', [$id_judul, $id_mhs]);
        $dosenOk = fetchOne('SELECT d.kuota_bimbingan, d.terisi FROM dosen d JOIN user u ON u.id_user=d.id_user WHERE d.id_dosen=? AND u.status="aktif"', [$id_dosen]);
        $mhsOk   = countRows('SELECT COUNT(*) FROM mahasiswa m JOIN user u ON u.id_user=m.id_user WHERE m.id_mahasiswa=? AND u.status="aktif"', [$id_mhs]);

        if (!skSiap()) {
            setFlash('danger', 'Gagal: database masih versi lama. Impor ulang database/db_bimbingan_skripsi.sql di phpMyAdmin agar SK bisa terbit otomatis.');
        } elseif ($existing > 0) {
            setFlash('danger', 'Gagal: Mahasiswa tersebut sudah memiliki relasi bimbingan yang masih aktif.');
        } elseif (!$mhsOk) {
            setFlash('danger', 'Gagal: mahasiswa tidak ditemukan atau akunnya nonaktif.');
        } elseif (!$judulOk) {
            setFlash('danger', 'Gagal: judul yang dipilih bukan judul disetujui milik mahasiswa tersebut.');
        } elseif (!$dosenOk) {
            setFlash('danger', 'Gagal: dosen tidak ditemukan atau akunnya nonaktif.');
        } elseif ((int)$dosenOk['terisi'] >= (int)$dosenOk['kuota_bimbingan']) {
            setFlash('danger', 'Gagal: kuota bimbingan dosen tersebut sudah penuh.');
        } elseif ($semester === '' || mb_strlen($semester) > 20) {
            setFlash('danger', 'Gagal: semester wajib diisi (maksimal 20 karakter).');
        } else {
            $db = getDB();
            $db->beginTransaction();
            $sk = null;
            try {
                execute('INSERT INTO bimbingan (id_mahasiswa, id_dosen, id_judul, semester, tgl_mulai, status)
                         VALUES (?, ?, ?, ?, CURDATE(), "aktif")',
                         [$id_mhs, $id_dosen, $id_judul, $semester]);
                $idBaru = (int)$db->lastInsertId();
                // Nomor SK terbit otomatis + file PDF SK dibuat
                $sk = terbitkanSk($idBaru);
                // SK terbit: skripsi mahasiswa mulai berjalan
                execute('UPDATE mahasiswa SET status_skripsi="proses" WHERE id_mahasiswa=?', [$id_mhs]);

                $dosen = fetchOne('SELECT id_user FROM dosen WHERE id_dosen=?', [$id_dosen]);
                $mhs   = fetchOne('SELECT id_user FROM mahasiswa WHERE id_mahasiswa=?', [$id_mhs]);
                kirimNotif((int)($dosen['id_user'] ?? 0), 'Penugasan Bimbingan Baru',
                    'Anda ditunjuk sebagai dosen pembimbing berdasarkan SK ' . $sk['no_sk'] . '. SK dapat diunduh di menu Mahasiswa Bimbingan.',
                    'info', 'relasi', 'dosen/mahasiswa.php');
                kirimNotif((int)($mhs['id_user'] ?? 0), 'SK Bimbingan Terbit!',
                    'SK ' . $sk['no_sk'] . ' telah terbit dan dosen pembimbing Anda sudah ditetapkan. Unduh SK di menu Usulan Pembimbing. Menu Dokumen dan Jadwal kini terbuka.',
                    'sukses', 'relasi', 'mahasiswa/pengajuan_dosen.php');

                $db->commit();
                setFlash('success', 'Relasi berhasil dibuat. SK ' . $sk['no_sk'] . ' terbit otomatis dan notifikasi dikirim.');
            } catch (\Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                hapusUpload($sk['path'] ?? null);
                setFlash('danger', pesanGalat($e, 'Gagal memproses plotting'));
            }
        }
    } elseif ($aksi === 'buat_ulang_sk') {
        $idB = (int)($_POST['id_bimbingan'] ?? 0);
        if (!skSiap()) {
            setFlash('danger', 'Gagal: database masih versi lama. Impor ulang database/db_bimbingan_skripsi.sql di phpMyAdmin.');
        } elseif (!countRows('SELECT COUNT(*) FROM bimbingan WHERE id_bimbingan=?', [$idB])) {
            setFlash('danger', 'Relasi bimbingan tidak ditemukan.');
        } else {
            $db = getDB();
            $db->beginTransaction();
            $sk = null;
            try {
                $sk = terbitkanSk($idB);
                $db->commit();
                if ($sk['lama'] && $sk['lama'] !== $sk['path']) hapusUpload($sk['lama']);
                setFlash('success', 'File SK ' . $sk['no_sk'] . ' berhasil dibuat ulang dengan data terbaru.');
            } catch (\Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                hapusUpload($sk['path'] ?? null);
                setFlash('danger', pesanGalat($e, 'Gagal membuat ulang SK'));
            }
        }
    } elseif ($aksi === 'hentikan' || $aksi === 'selesai') {
        $idB  = (int)($_POST['id_bimbingan'] ?? 0);
        $bimb = fetchOne('SELECT b.id_mahasiswa, m.id_user FROM bimbingan b
                          JOIN mahasiswa m ON m.id_mahasiswa=b.id_mahasiswa
                          WHERE b.id_bimbingan=? AND b.status="aktif"', [$idB]);
        if (!$bimb) {
            setFlash('danger', 'Relasi bimbingan aktif tidak ditemukan.');
        } elseif ($aksi === 'selesai') {
            execute('UPDATE bimbingan SET status="selesai",tgl_selesai=CURDATE() WHERE id_bimbingan=?', [$idB]);
            execute('UPDATE mahasiswa SET status_skripsi="selesai" WHERE id_mahasiswa=?', [$bimb['id_mahasiswa']]);
            execute('INSERT INTO notifikasi (id_user,judul,pesan,tipe,modul,tautan) VALUES (?,?,?,?,?,"dashboard.php")',
                [$bimb['id_user'], 'Bimbingan Skripsi Selesai',
                 'Selamat! Proses bimbingan skripsi Anda telah dinyatakan selesai.', 'sukses', 'bimbingan']);
            setFlash('success', 'Bimbingan ditandai selesai. Status skripsi mahasiswa menjadi "Selesai".');
        } else {
            execute('UPDATE bimbingan SET status="dihentikan",tgl_selesai=CURDATE() WHERE id_bimbingan=?', [$idB]);
            // Tanpa pembimbing aktif, skripsi kembali ke tahap awal
            execute('UPDATE mahasiswa SET status_skripsi="belum" WHERE id_mahasiswa=? AND status_skripsi="proses"', [$bimb['id_mahasiswa']]);
            setFlash('warning', 'Relasi bimbingan berhasil dihentikan.');
        }
    }
    header('Location: ' . BASE_URL . '/admin/relasi.php');
    exit;
}

// 1. QUERY ANTREAN AJUAN (Ditambahkan JOIN ke usulan_dosen)
$antreanPlotting = fetchAll(
    'SELECT j.id_judul, j.judul_indonesia, j.bidang_penelitian, j.usulan_dosen, m.id_mahasiswa, m.nim, u.nama_lengkap, j.tgl_pengajuan, ud.nama_lengkap AS nama_dosen_usulan
     FROM judul_skripsi j
     JOIN mahasiswa m ON m.id_mahasiswa = j.id_mahasiswa
     JOIN user u ON u.id_user = m.id_user
     LEFT JOIN dosen d_usul ON d_usul.id_dosen = j.usulan_dosen
     LEFT JOIN user ud ON ud.id_user = d_usul.id_user
     WHERE j.status = "disetujui"
     AND NOT EXISTS (
         SELECT 1 FROM bimbingan b WHERE b.id_mahasiswa = m.id_mahasiswa AND b.status = "aktif"
     )
     ORDER BY j.tgl_pengajuan ASC'
);

// 2. QUERY MASTER DATA UNTUK FORM MANUAL
$mhsTanpaBimb = fetchAll(
    'SELECT m.id_mahasiswa,m.nim,u.nama_lengkap FROM mahasiswa m JOIN user u ON u.id_user=m.id_user
     WHERE NOT EXISTS (SELECT 1 FROM bimbingan b WHERE b.id_mahasiswa=m.id_mahasiswa AND b.status="aktif")
     ORDER BY u.nama_lengkap'
);
// Pastikan d.keahlian ditarik dari database
$dosenList = fetchAll('SELECT d.id_dosen, u.nama_lengkap, (d.kuota_bimbingan-d.terisi) AS sisa, d.keahlian 
                       FROM dosen d JOIN user u ON u.id_user=d.id_user 
                       WHERE d.kuota_bimbingan > d.terisi AND u.status = "aktif"
                       ORDER BY u.nama_lengkap');
$judulList = fetchAll('SELECT j.id_judul,j.judul_indonesia,m.nim FROM judul_skripsi j JOIN mahasiswa m ON m.id_mahasiswa=j.id_mahasiswa WHERE j.status="disetujui"');

// 3. QUERY TABEL SEMUA RELASI
$relasi = fetchAll(
    'SELECT b.*,um.nama_lengkap AS nama_mhs,m.nim,ud.nama_lengkap AS nama_dosen,j.judul_indonesia
     FROM bimbingan b
     JOIN mahasiswa m ON m.id_mahasiswa=b.id_mahasiswa JOIN user um ON um.id_user=m.id_user
     JOIN dosen d ON d.id_dosen=b.id_dosen JOIN user ud ON ud.id_user=d.id_user
     JOIN judul_skripsi j ON j.id_judul=b.id_judul
     ORDER BY b.status,b.tgl_mulai DESC'
);

$semesterDefault = 'Genap ' . date('Y') . '/' . (date('Y')+1);
$skSiap = skSiap();
$nomorBerikut = $skSiap ? nomorSkBerikutnya() : null;

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<style>
  .tab-container { margin-bottom: 24px; }
  .tab-header { display: flex; gap: 10px; border-bottom: 2px solid var(--cream-dark); padding-bottom: 10px; margin-bottom: 20px; }
  .tab-btn { background: none; border: none; font-size: 15px; font-weight: 600; color: var(--slate); cursor: pointer; padding: 8px 16px; border-radius: 6px; transition: 0.2s; }
  .tab-btn.active { background: var(--navy); color: var(--white); }
  .tab-btn:hover:not(.active) { background: var(--cream); color: var(--navy); }
  .tab-content { display: none; }
  .tab-content.active { display: block; animation: fadeIn 0.3s ease; }
  @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">
  <?= tampilFlash() ?>
  <?php if (!$skSiap): ?>
    <div class="alert alert-static danger">Database belum mendukung SK otomatis. Buka phpMyAdmin, lalu impor ulang file <code>database/db_bimbingan_skripsi.sql</code> ke database <b><?= e(DB_NAME) ?></b>.</div>
  <?php endif; ?>
  
  <div class="page-header" style="display:flex; justify-content:space-between; align-items:flex-end;">
      <div>
          <h1>Relasi Pembimbingan & SK</h1>
          <p>Plotting dosen pembimbing dan penerbitan Surat Keputusan (SK)</p>
      </div>
      <div style="display:flex; gap:12px;">
          <?php foreach ([
            ['Aktif',  countRows('SELECT COUNT(*) FROM bimbingan WHERE status="aktif"'),    'success'],
            ['Selesai',countRows('SELECT COUNT(*) FROM bimbingan WHERE status="selesai"'), 'info']
          ] as [$l,$n,$b]): ?>
            <div style="background:var(--white); border:1px solid var(--cream-dark); padding:10px 20px; border-radius:8px; text-align:center;">
                <div style="font-size:12px; color:var(--slate); margin-bottom:4px;"><?= $l ?></div>
                <div style="font-size:20px; font-weight:700; color:var(--<?= $b ?>);"><?= $n ?></div>
            </div>
          <?php endforeach; ?>
      </div>
  </div>

  <?php if ($skSiap): ?>
  <div class="status-card status-card--blok" style="margin-bottom:16px;">
    <div><strong>SK terbit otomatis.</strong> Saat Anda menekan Terbitkan, sistem memberi nomor SK berurutan per tahun dengan format <span class="mono">UOB/SCDT/SK-BIMBINGAN/tahun/nomor</span>, lalu membuat file PDF SK berisi data mahasiswa, judul, dosen pembimbing, dan kode verifikasi.
      Nomor berikutnya: <strong class="mono"><?= e($nomorBerikut) ?></strong>. Kop surat & penandatangan bisa diubah di <a href="<?= BASE_URL ?>/admin/pengaturan.php" class="link">Pengaturan</a>.</div>
  </div>
  <?php endif; ?>

  <div class="card tab-container">
      <div class="card-body">
          <div class="tab-header">
              <button class="tab-btn active" onclick="switchTab('ajuan')">📋 Alur 1: Antrean Plotting (Judul Disetujui)</button>
              <button class="tab-btn" onclick="switchTab('manual')">⚙️ Alur 2: Plotting Manual (Bypass)</button>
          </div>

          <div id="tab_ajuan" class="tab-content active">
              <?php if(empty($antreanPlotting)): ?>
                  <div class="empty-state" style="padding: 30px;">
                      <div class="empty-icon">✅</div>
                      <div class="empty-title">Semua Ajuan Telah Diplot</div>
                      <div class="empty-sub">Tidak ada judul disetujui yang menunggu penetapan pembimbing & SK.</div>
                  </div>
              <?php else: ?>
                  <div class="table-wrap">
                      <table>
                          <thead>
                              <tr>
                                  <th>Mahasiswa</th>
                                  <th>Judul Disetujui</th>
                                  <th style="width:340px;">Pilih Dosen Pembimbing</th>
                                  <th style="width:100px;">Aksi</th>
                              </tr>
                          </thead>
                          <tbody>
                              <?php foreach($antreanPlotting as $ap): ?>
                              <tr>
                                  <td>
                                      <div style="font-weight:600; font-size:13px;"><?= e($ap['nama_lengkap']) ?></div>
                                      <div class="mono meta-sm"><?= e($ap['nim']) ?></div>
                                  </td>
                                  
                                  <td class="fs-13">
                                      <?= e($ap['judul_indonesia']) ?>
                                      <div style="display:flex; gap:8px; align-items:center; margin-top:6px;">
                                          <div style="font-size:11px; color:var(--info-text);">ACC: <?= tgl($ap['tgl_pengajuan']) ?></div>
                                          <span class="badge info" style="font-size:10px;"><?= e($ap['bidang_penelitian']) ?></span>
                                      </div>
                                      <?php if ($ap['usulan_dosen']): ?>
                                          <div style="font-size:11px; font-weight:600; color:var(--gold-text); margin-top:8px;">
                                              ⭐ Usulan Mhs: <?= e($ap['nama_dosen_usulan']) ?>
                                          </div>
                                      <?php endif; ?>
                                  </td>
                                  <form method="POST"><?= csrfField() ?>
                                      <input type="hidden" name="aksi" value="buat">
                                      <input type="hidden" name="id_mahasiswa" value="<?= $ap['id_mahasiswa'] ?>">
                                      <input type="hidden" name="id_judul" value="<?= $ap['id_judul'] ?>">
                                      <input type="hidden" name="semester" value="<?= $semesterDefault ?>">
                                      
                                      <td style="padding: 10px;">
                                          <div class="meta-sm mb-4">Plotting Dosen Pembimbing:</div>
                                          <select class="form-input" name="id_dosen" style="padding:6px; font-size:13px; margin-bottom:12px;" required>
                                              <option value="" disabled selected>-- Pilih Dosen --</option>
                                              
                                              <optgroup label="🌟 Rekomendasi (<?= e($ap['bidang_penelitian']) ?>)">
                                                  <?php 
                                                  $adaRekom = false;
                                                  foreach ($dosenList as $d) {
                                                      if ($d['keahlian'] === $ap['bidang_penelitian']) {
                                                          // AUTO-SELECT JIKA INI DOSEN YANG DIUSULKAN
                                                          $isSelected = ($d['id_dosen'] == $ap['usulan_dosen']) ? 'selected' : '';
                                                          echo '<option value="' . $d['id_dosen'] . '" '.$isSelected.'>✅ ' . e($d['nama_lengkap']) . ' (Sisa: ' . $d['sisa'] . ')</option>';
                                                          $adaRekom = true;
                                                      }
                                                  }
                                                  if (!$adaRekom) echo '<option disabled>Tidak ada dosen dengan bidang ini</option>';
                                                  ?>
                                              </optgroup>
                                  
                                              <optgroup label="👨‍🏫 Dosen Bidang Lainnya">
                                                  <?php 
                                                  foreach ($dosenList as $d) {
                                                      if ($d['keahlian'] !== $ap['bidang_penelitian']) {
                                                          // AUTO-SELECT JIKA INI DOSEN YANG DIUSULKAN
                                                          $isSelected = ($d['id_dosen'] == $ap['usulan_dosen']) ? 'selected' : '';
                                                          echo '<option value="' . $d['id_dosen'] . '" '.$isSelected.'>' . e($d['nama_lengkap']) . ' (Sisa: ' . $d['sisa'] . ' | Bidang: ' . e($d['keahlian']) . ')</option>';
                                                      }
                                                  }
                                                  ?>
                                              </optgroup>
                                          </select>
                                          
                                          <div class="meta-sm">Nomor & file SK dibuat otomatis saat diterbitkan.</div>
                                      </td>
                                      
                                      <td>
                                          <button type="submit" class="btn btn-primary" style="padding:8px 12px; font-size:12px; width: 100%; justify-content: center;" <?= $skSiap ? '' : 'disabled' ?>>Terbitkan SK</button>
                                      </td>
                                  </form>
                              </tr>
                              <?php endforeach; ?>
                          </tbody>
                      </table>
                  </div>
              <?php endif; ?>
          </div>

          <div id="tab_manual" class="tab-content">
              <div style="max-width:600px;">
                  <p style="font-size:13px; color:var(--slate); margin-bottom:16px;">Gunakan form bypass ini jika mahasiswa belum melakukan pengajuan dosen lewat sistem (misal: pindahan). Nomor & file SK tetap terbit otomatis.</p>
                  
                  <form method="POST"><?= csrfField() ?>
                    <input type="hidden" name="aksi" value="buat">
                    <div class="form-group">
                      <label for="f_id_mahasiswa" class="form-label">1. Pilih Mahasiswa (Tanpa Bimbingan Aktif)</label>
                      <select id="f_id_mahasiswa" class="form-input" name="id_mahasiswa" required>
                        <option value="" disabled selected>Pilih mahasiswa...</option>
                        <?php foreach ($mhsTanpaBimb as $m): ?><option value="<?= $m['id_mahasiswa'] ?>">[<?= $m['nim'] ?>] <?= e($m['nama_lengkap']) ?></option><?php endforeach; ?>
                      </select>
                    </div>
                    <div class="form-group">
                      <label for="f_id_dosen" class="form-label">2. Pilih Dosen Pembimbing</label>
                      <select id="f_id_dosen" class="form-input" name="id_dosen" required>
                        <option value="" disabled selected>Pilih dosen...</option>
                        <?php foreach ($dosenList as $d): ?><option value="<?= $d['id_dosen'] ?>"><?= e($d['nama_lengkap']) ?> (Kuota Sisa: <?= $d['sisa'] ?>)</option><?php endforeach; ?>
                      </select>
                    </div>
                    <div class="form-group">
                      <label for="f_id_judul" class="form-label">3. Tautkan Judul Skripsi (Yang Disetujui)</label>
                      <select id="f_id_judul" class="form-input" name="id_judul" required>
                        <option value="" disabled selected>Pilih judul disetujui...</option>
                        <?php foreach ($judulList as $j): ?><option value="<?= $j['id_judul'] ?>">NIM: <?= $j['nim'] ?> - <?= e(mb_strimwidth($j['judul_indonesia'],0,60,'...')) ?></option><?php endforeach; ?>
                      </select>
                    </div>
                    
                    <div class="form-group">
                      <label for="f_semester" class="form-label">Semester Eksekusi</label>
                      <input id="f_semester" class="form-input" name="semester" value="<?= $semesterDefault ?>" required>
                    </div>
                    
                    <button type="submit" class="btn btn-gold" <?= $skSiap ? '' : 'disabled' ?>>🔗 Terbitkan SK & Buat Relasi</button>
                  </form>
              </div>
          </div>
      </div>
  </div>

  <div class="card">
    <div class="card-header"><div class="card-title">Riwayat & Daftar Relasi Berjalan</div></div>
    <div class="card-body">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Mahasiswa</th><th>Dosen</th><th>Judul</th><th>SK Bimbingan</th><th>Mulai</th><th>Status</th><th>Aksi</th></tr></thead>
          <tbody>
            <?php foreach ($relasi as $r): ?>
              <tr>
                <td>
                    <div style="font-weight:600; font-size:13px;"><?= e($r['nama_mhs']) ?></div>
                    <div class="mono" style="font-size:11px;color:var(--slate);"><?= $r['nim'] ?></div>
                </td>
                <td class="fs-13"><?= e($r['nama_dosen']) ?></td>
                <td style="max-width:200px;font-size:12px;"><?= e(mb_strimwidth($r['judul_indonesia'],0,40,'...')) ?></td>
                
                <td>
                    <?php if(!empty($r['no_sk'])): ?>
                        <div style="font-size:11px; font-weight:600; color:var(--navy);"><?= e($r['no_sk']) ?></div>
                        <?php if(!empty($r['tgl_sk'])): ?><div class="meta-sm"><?= tgl($r['tgl_sk']) ?></div><?php endif; ?>
                    <?php else: ?>
                        <div class="meta-sm">Belum ada SK</div>
                    <?php endif; ?>
                    <div style="display:flex; gap:10px; align-items:center; margin-top:4px; flex-wrap:wrap;">
                      <?php if(!empty($r['file_sk'])): ?>
                        <a href="<?= e(fileUrl($r['file_sk'])) ?>" target="_blank" style="font-size:11px; color:var(--info-text); text-decoration:underline;">Unduh PDF</a>
                      <?php endif; ?>
                      <?php if ($skSiap): ?>
                        <form method="POST" data-confirm="<?= !empty($r['sk_urut']) ? e('Buat ulang file SK ' . $r['no_sk'] . ' dengan data terbaru? Nomor SK, tanggal, dan kode verifikasi tetap sama.') : e('Terbitkan SK otomatis untuk ' . $r['nama_mhs'] . '? Relasi ini akan mendapat nomor SK baru (' . $nomorBerikut . ') dan file SK lama diganti.') ?>" data-confirm-ok="<?= !empty($r['sk_urut']) ? 'Buat ulang' : 'Terbitkan' ?>" data-confirm-tone="primary"><?= csrfField() ?>
                          <input type="hidden" name="aksi" value="buat_ulang_sk">
                          <input type="hidden" name="id_bimbingan" value="<?= $r['id_bimbingan'] ?>">
                          <button class="btn btn-secondary" style="padding:2px 8px; font-size:11px;"><?= !empty($r['sk_urut']) ? '↻ Buat ulang' : '⚙️ Terbitkan otomatis' ?></button>
                        </form>
                      <?php endif; ?>
                    </div>
                </td>

                <td class="nowrap fs-12"><?= tgl($r['tgl_mulai']) ?></td>
                <td><span class="badge <?= badge($r['status']) ?>"><span class="badge-dot"></span><?= label($r['status']) ?></span></td>
                <td>
                  <?php if ($r['status'] === 'aktif'): ?>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    <form method="POST" data-confirm="Tandai bimbingan <?= e($r['nama_mhs']) ?> sebagai selesai? Status skripsinya akan menjadi Selesai." data-confirm-ok="Tandai selesai" data-confirm-tone="primary"><?= csrfField() ?>
                      <input type="hidden" name="aksi" value="selesai">
                      <input type="hidden" name="id_bimbingan" value="<?= $r['id_bimbingan'] ?>">
                      <button class="btn btn-success" style="padding:4px 8px; font-size:11px;">🎓 Selesai</button>
                    </form>
                    <form method="POST" data-confirm="Hentikan relasi ini? Dosen dan mahasiswa tidak akan bisa melakukan proses bimbingan lagi." data-confirm-ok="Hentikan"><?= csrfField() ?>
                      <input type="hidden" name="aksi" value="hentikan">
                      <input type="hidden" name="id_bimbingan" value="<?= $r['id_bimbingan'] ?>">
                      <button class="btn btn-danger" style="padding:4px 8px; font-size:11px;">🛑 Hentikan</button>
                    </form>
                    </div>
                  <?php else: ?><span style="color:var(--slate);font-size:12px;">—</span><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
function switchTab(tabId) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    if(tabId === 'ajuan') {
        document.querySelector('.tab-btn:nth-child(1)').classList.add('active');
        document.getElementById('tab_ajuan').classList.add('active');
    } else {
        document.querySelector('.tab-btn:nth-child(2)').classList.add('active');
        document.getElementById('tab_manual').classList.add('active');
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>