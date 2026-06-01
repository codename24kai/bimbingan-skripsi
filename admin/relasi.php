<?php
// admin/relasi.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('admin');

$pageTitle  = 'Relasi Pembimbingan';
$activePage = 'relasi';

// Handle create/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    
    // Logika ini menangani baik alur 'Ajuan' maupun 'Manual'
    if ($aksi === 'buat') {
        $id_mhs   = (int)$_POST['id_mahasiswa'];
        $id_dosen = (int)$_POST['id_dosen'];
        $id_judul = (int)$_POST['id_judul'];
        $semester = trim($_POST['semester'] ?? '');
        $no_sk    = trim($_POST['no_sk'] ?? ''); // Tangkap No SK

        // Validasi: Pastikan mahasiswa tidak memiliki bimbingan aktif ganda
        $existing = countRows('SELECT COUNT(*) FROM bimbingan WHERE id_mahasiswa=? AND status="aktif"', [$id_mhs]);
        
        if ($existing > 0) {
            setFlash('danger', 'Gagal: Mahasiswa tersebut sudah memiliki relasi bimbingan yang masih aktif.');
        } elseif (empty($no_sk) || empty($_FILES['file_sk']['name'])) {
            setFlash('danger', 'Gagal: Nomor SK dan Dokumen SK Bimbingan wajib diisi!');
        } else {
            $db = getDB();
            $db->beginTransaction();
            
            try {
                // Handle Upload File SK Bimbingan
                $ext = strtolower(pathinfo($_FILES['file_sk']['name'], PATHINFO_EXTENSION));
                if ($ext !== 'pdf') {
                    throw new \Exception('Format SK Bimbingan harus PDF.');
                }
                if ($_FILES['file_sk']['size'] > (5 * 1024 * 1024)) {
                    throw new \Exception('Ukuran file SK maksimal 5MB.');
                }

                $fname = 'SK_' . $id_mhs . '_' . uniqid() . '.pdf';
                if (!is_dir(UPLOAD_DIR)) {
                    mkdir(UPLOAD_DIR, 0755, true);
                }
                if (!move_uploaded_file($_FILES['file_sk']['tmp_name'], UPLOAD_DIR . $fname)) {
                    throw new \Exception('Gagal menyimpan dokumen SK ke server.');
                }
                
                $filePath = 'uploads/' . $fname;

                // Insert relasi bimbingan beserta SK
                execute('INSERT INTO bimbingan (id_mahasiswa, id_dosen, id_judul, semester, tgl_mulai, status, no_sk, file_sk)
                         VALUES (?, ?, ?, ?, CURDATE(), "aktif", ?, ?)', 
                         [$id_mhs, $id_dosen, $id_judul, $semester, $no_sk, $filePath]);
                
                // Tambahkan notifikasi otomatis
                $dosen = fetchOne('SELECT id_user FROM dosen WHERE id_dosen=?', [$id_dosen]);
                $mhs = fetchOne('SELECT id_user FROM mahasiswa WHERE id_mahasiswa=?', [$id_mhs]);
                
                if($dosen) execute('INSERT INTO notifikasi (id_user, judul, pesan, tipe) VALUES (?, ?, ?, ?)', [$dosen['id_user'], 'Bimbingan Baru', 'Anda telah diplot sebagai pembimbing mahasiswa baru. SK telah diterbitkan.', 'info']);
                if($mhs) execute('INSERT INTO notifikasi (id_user, judul, pesan, tipe) VALUES (?, ?, ?, ?)', [$mhs['id_user'], 'SK Bimbingan Terbit!', 'Dosen pembimbing telah ditetapkan dan SK Bimbingan Anda sudah bisa diunduh. Semua akses fitur kini terbuka.', 'sukses']);

                $db->commit();
                setFlash('success', 'Relasi berhasil dibuat. SK telah diterbitkan dan notifikasi dikirim.');
            } catch (\Exception $e) {
                $db->rollBack();
                setFlash('danger', 'Gagal memproses plotting: ' . $e->getMessage());
            }
        }
    } elseif ($aksi === 'hentikan') {
        execute('UPDATE bimbingan SET status="dihentikan",tgl_selesai=CURDATE() WHERE id_bimbingan=?', [(int)$_POST['id_bimbingan']]);
        setFlash('warning', 'Relasi bimbingan berhasil dihentikan.');
    }
    header('Location: ' . BASE_URL . '/admin/relasi.php');
    exit;
}

// 1. QUERY ANTREAN AJUAN
$antreanPlotting = fetchAll(
    'SELECT j.id_judul, j.judul_indonesia, m.id_mahasiswa, m.nim, u.nama_lengkap, j.tgl_pengajuan
     FROM judul_skripsi j
     JOIN mahasiswa m ON m.id_mahasiswa = j.id_mahasiswa
     JOIN user u ON u.id_user = m.id_user
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
$dosenList = fetchAll('SELECT d.id_dosen,u.nama_lengkap,(d.kuota_bimbingan-d.terisi) AS sisa, d.keahlian FROM dosen d JOIN user u ON u.id_user=d.id_user WHERE d.kuota_bimbingan>d.terisi ORDER BY u.nama_lengkap');
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
  <?php $flash = getFlash(); if ($flash): ?><div class="alert <?= $flash['type'] ?>"><?= e($flash['msg']) ?></div><?php endif; ?>
  
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
                                  <th style="width:340px;">Pilih Dosen & Upload SK</th>
                                  <th style="width:100px;">Aksi</th>
                              </tr>
                          </thead>
                          <tbody>
                              <?php foreach($antreanPlotting as $ap): ?>
                              <tr>
                                  <td>
                                      <div style="font-weight:600; font-size:13px;"><?= e($ap['nama_lengkap']) ?></div>
                                      <div class="mono" style="font-size:11px; color:var(--slate);"><?= e($ap['nim']) ?></div>
                                  </td>
                                  <td style="font-size:13px;">
                                      <?= e($ap['judul_indonesia']) ?>
                                      <div style="font-size:11px; color:var(--info); margin-top:4px;">ACC: <?= date('d M Y', strtotime($ap['tgl_pengajuan'])) ?></div>
                                  </td>
                                  <form method="POST" enctype="multipart/form-data">
                                      <input type="hidden" name="aksi" value="buat">
                                      <input type="hidden" name="id_mahasiswa" value="<?= $ap['id_mahasiswa'] ?>">
                                      <input type="hidden" name="id_judul" value="<?= $ap['id_judul'] ?>">
                                      <input type="hidden" name="semester" value="<?= $semesterDefault ?>">
                                      
                                      <td style="padding: 10px;">
                                          <select class="form-input" name="id_dosen" style="padding:6px; font-size:13px; margin-bottom:8px;" required>
                                              <option value="" disabled selected>-- Pilih Dosen Tersedia --</option>
                                              <?php foreach ($dosenList as $d): ?>
                                                  <option value="<?= $d['id_dosen'] ?>">
                                                      <?= e($d['nama_lengkap']) ?> (Sisa: <?= $d['sisa'] ?>)
                                                  </option>
                                              <?php endforeach; ?>
                                          </select>
                                          
                                          <input type="text" class="form-input" name="no_sk" placeholder="Nomor SK (Cth: 123/SK/UNPAM/2026)" style="padding:6px; font-size:12px; margin-bottom:8px;" required>
                                          
                                          <div style="font-size:11px; color:var(--slate); margin-bottom:4px;">Unggah PDF SK Bimbingan:</div>
                                          <input type="file" name="file_sk" accept=".pdf" class="form-input" style="padding:4px; font-size:12px;" required>
                                      </td>
                                      <td>
                                          <button type="submit" class="btn btn-primary" style="padding:8px 12px; font-size:12px; width: 100%; justify-content: center;">Terbitkan</button>
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
                  <p style="font-size:13px; color:var(--slate); margin-bottom:16px;">Gunakan form bypass ini jika mahasiswa belum melakukan pengajuan lewat sistem namun SK sudah dicetak (misal: Pindahan).</p>
                  
                  <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="aksi" value="buat">
                    <div class="form-group">
                      <label class="form-label">1. Pilih Mahasiswa (Tanpa Bimbingan Aktif)</label>
                      <select class="form-input" name="id_mahasiswa" required>
                        <option value="" disabled selected>Pilih mahasiswa...</option>
                        <?php foreach ($mhsTanpaBimb as $m): ?><option value="<?= $m['id_mahasiswa'] ?>">[<?= $m['nim'] ?>] <?= e($m['nama_lengkap']) ?></option><?php endforeach; ?>
                      </select>
                    </div>
                    <div class="form-group">
                      <label class="form-label">2. Pilih Dosen Pembimbing</label>
                      <select class="form-input" name="id_dosen" required>
                        <option value="" disabled selected>Pilih dosen...</option>
                        <?php foreach ($dosenList as $d): ?><option value="<?= $d['id_dosen'] ?>"><?= e($d['nama_lengkap']) ?> (Kuota Sisa: <?= $d['sisa'] ?>)</option><?php endforeach; ?>
                      </select>
                    </div>
                    <div class="form-group">
                      <label class="form-label">3. Tautkan Judul Skripsi (Yang Disetujui)</label>
                      <select class="form-input" name="id_judul" required>
                        <option value="" disabled selected>Pilih judul disetujui...</option>
                        <?php foreach ($judulList as $j): ?><option value="<?= $j['id_judul'] ?>">NIM: <?= $j['nim'] ?> - <?= e(mb_strimwidth($j['judul_indonesia'],0,60,'...')) ?></option><?php endforeach; ?>
                      </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                          <label class="form-label">Nomor Surat Keputusan (SK)</label>
                          <input class="form-input" name="no_sk" placeholder="Cth: 001/SK/ILKOM/2026" required>
                        </div>
                        <div class="form-group">
                          <label class="form-label">Upload File SK (PDF)</label>
                          <input class="form-input" type="file" name="file_sk" accept=".pdf" required style="padding: 7px;">
                        </div>
                    </div>
                    
                    <div class="form-group">
                      <label class="form-label">Semester Eksekusi</label>
                      <input class="form-input" name="semester" value="<?= $semesterDefault ?>" required>
                    </div>
                    
                    <button type="submit" class="btn btn-gold">🔗 Terbitkan & Buat Relasi</button>
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
                <td style="font-size:13px;"><?= e($r['nama_dosen']) ?></td>
                <td style="max-width:200px;font-size:12px;"><?= e(mb_strimwidth($r['judul_indonesia'],0,40,'...')) ?></td>
                
                <td>
                    <?php if(!empty($r['no_sk'])): ?>
                        <div style="font-size:11px; font-weight:600; color:var(--navy);"><?= e($r['no_sk']) ?></div>
                        <?php if(!empty($r['file_sk'])): ?>
                            <a href="<?= BASE_URL ?>/<?= e($r['file_sk']) ?>" target="_blank" style="font-size:11px; color:var(--info); text-decoration:underline;">Lihat PDF</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="font-size:11px; color:var(--slate);">-</span>
                    <?php endif; ?>
                </td>

                <td style="font-size:12px;"><?= date('d/m/y', strtotime($r['tgl_mulai'])) ?></td>
                <td><span class="badge <?= badge($r['status']) ?>"><span class="badge-dot"></span><?= $r['status'] ?></span></td>
                <td>
                  <?php if ($r['status'] === 'aktif'): ?>
                    <form method="POST" onsubmit="return confirm('Hentikan relasi ini? Dosen dan mahasiswa tidak akan bisa melakukan proses bimbingan lagi.')">
                      <input type="hidden" name="aksi" value="hentikan">
                      <input type="hidden" name="id_bimbingan" value="<?= $r['id_bimbingan'] ?>">
                      <button class="btn btn-danger" style="padding:4px 8px; font-size:11px;">🛑 Hentikan</button>
                    </form>
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