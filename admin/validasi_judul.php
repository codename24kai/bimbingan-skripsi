<?php
// admin/validasi_judul.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('admin'); // Bisa diakses Admin / Kaprodi

$pageTitle  = 'Validasi Judul';
$activePage = 'validasi_judul';

// Handle aksi validasi (Setujui / Tolak)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi        = $_POST['aksi'] ?? '';
    $id_judul    = (int)$_POST['id_judul'];
    $id_user_mhs = (int)$_POST['id_user_mhs']; // Untuk kirim notifikasi

    $db = getDB();
    $db->beginTransaction();

    try {
        if ($aksi === 'setujui') {
            // Tangkap judul mana yang dipilih Kaprodi
            $judul_terpilih = trim($_POST['judul_terpilih'] ?? '');
            
            if (empty($judul_terpilih)) {
                throw new \Exception('Anda harus memilih salah satu judul untuk disetujui.');
            }

            // Update status menjadi disetujui, dan timpa judul_indonesia dengan judul yang dipilih
            execute('UPDATE judul_skripsi SET status="disetujui", judul_indonesia=? WHERE id_judul=?', [$judul_terpilih, $id_judul]);
            
            // PERBAIKAN ERROR HY093: Sinkronisasi 5 tanda tanya (?) dengan 5 parameter array
            execute('INSERT INTO notifikasi (id_user, judul, pesan, tipe, modul) VALUES (?, ?, ?, ?, ?)', 
                    [$id_user_mhs, 'Judul Skripsi Disetujui!', 'Selamat! Judul Anda telah disetujui. Silakan tunggu penetapan Dosen Pembimbing.', 'sukses', 'judul']);
            
            setFlash('success', 'Judul berhasil disetujui dan diteruskan ke antrean plotting pembimbing.');

        } elseif ($aksi === 'tolak') {
            $catatan = trim($_POST['catatan_penolakan'] ?? 'Judul tidak memenuhi kriteria kelayakan prodi.');
            
            // Update status
            execute('UPDATE judul_skripsi SET status="ditolak" WHERE id_judul=?', [$id_judul]);
            
            // PERBAIKAN ERROR HY093: Sinkronisasi 5 tanda tanya (?) dengan 5 parameter array
            execute('INSERT INTO notifikasi (id_user, judul, pesan, tipe, modul) VALUES (?, ?, ?, ?, ?)', 
                    [$id_user_mhs, 'Pengajuan Judul Ditolak', 'Pengajuan Anda ditolak. Catatan: ' . $catatan, 'danger', 'judul']);
            
            setFlash('warning', 'Pengajuan judul telah ditolak.');
        }

        $db->commit();
    } catch (\Exception $e) {
        $db->rollBack();
        setFlash('danger', 'Gagal memproses validasi: ' . $e->getMessage());
    }
    
    header('Location: ' . BASE_URL . '/admin/validasi_judul.php');
    exit;
}

// QUERY 1: Antrean Menunggu Validasi (status = diajukan)
$pendingList = fetchAll(
    'SELECT j.*, m.nim, u.nama_lengkap, u.id_user as id_user_mhs 
     FROM judul_skripsi j 
     JOIN mahasiswa m ON m.id_mahasiswa = j.id_mahasiswa 
     JOIN user u ON u.id_user = m.id_user 
     WHERE j.status = "diajukan" 
     ORDER BY j.tgl_pengajuan ASC'
);

// QUERY 2: Riwayat Validasi (status = disetujui / ditolak)
$riwayatList = fetchAll(
    'SELECT j.*, m.nim, u.nama_lengkap 
     FROM judul_skripsi j 
     JOIN mahasiswa m ON m.id_mahasiswa = j.id_mahasiswa 
     JOIN user u ON u.id_user = m.id_user 
     WHERE j.status IN ("disetujui", "ditolak") 
     ORDER BY j.tgl_pengajuan DESC LIMIT 50'
);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">
  <?php $flash = getFlash(); if ($flash): ?><div class="alert <?= $flash['type'] ?>"><?= e($flash['msg']) ?></div><?php endif; ?>

  <div class="page-header" style="display:flex; justify-content:space-between; align-items:flex-end;">
      <div>
          <h1>Validasi Judul & Kelayakan</h1>
          <p>Tinjau latar belakang, periksa syarat, dan setujui judul mahasiswa.</p>
      </div>
      <div style="background:var(--white); border:1px solid var(--cream-dark); padding:10px 20px; border-radius:8px; text-align:center;">
          <div style="font-size:12px; color:var(--slate); margin-bottom:4px;">Menunggu Validasi</div>
          <div style="font-size:20px; font-weight:700; color:var(--warning);"><?= count($pendingList) ?> Ajuan</div>
      </div>
  </div>

  <div class="card" style="margin-bottom: 24px;">
    <div class="card-header"><div class="card-title">⏳ Antrean Validasi Judul Baru</div></div>
    <div class="card-body">
      <?php if (empty($pendingList)): ?>
          <div class="empty-state">
              <div class="empty-icon">🎉</div>
              <div class="empty-title">Tidak Ada Antrean</div>
              <div class="empty-sub">Semua pengajuan judul mahasiswa telah divalidasi.</div>
          </div>
      <?php else: ?>
          <div class="table-wrap">
              <table>
                  <thead>
                      <tr>
                          <th>Mahasiswa</th>
                          <th>Bidang & Metode</th>
                          <th>Berkas Syarat</th>
                          <th>Tanggal Ajuan</th>
                          <th style="text-align:right;">Aksi</th>
                      </tr>
                  </thead>
                  <tbody>
                      <?php foreach ($pendingList as $p): 
                          $jsonItem = htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8');
                      ?>
                      <tr>
                          <td>
                              <div style="font-weight:600; font-size:13px;"><?= e($p['nama_lengkap']) ?></div>
                              <div class="mono" style="font-size:11px; color:var(--slate);">NIM: <?= e($p['nim']) ?></div>
                              <div style="font-size:11px; color:var(--info); margin-top:4px;">Iterasi ke-<?= $p['iterasi'] ?></div>
                          </td>
                          <td>
                              <div style="font-size:12px; font-weight:500; color:var(--navy);"><?= e($p['bidang_penelitian']) ?></div>
                              <div style="font-size:11px; color:var(--slate);"><?= e($p['metode_penelitian']) ?></div>
                          </td>
                          <td>
                              <div style="display:flex; gap:8px; flex-direction:column;">
                                  <?php if($p['file_transkrip']): ?>
                                      <a href="<?= BASE_URL ?>/<?= e($p['file_transkrip']) ?>" target="_blank" style="font-size:11px; color:var(--info); text-decoration:none;">📄 Cek Transkrip/KRS</a>
                                  <?php else: ?>
                                      <span style="font-size:11px; color:var(--danger);">❌ Syarat Kurang</span>
                                  <?php endif; ?>
                                  
                                  <?php if($p['file_proposal']): ?>
                                      <a href="<?= BASE_URL ?>/<?= e($p['file_proposal']) ?>" target="_blank" style="font-size:11px; color:var(--slate); text-decoration:none;">📄 Draft Proposal</a>
                                  <?php endif; ?>
                              </div>
                          </td>
                          <td style="font-size:12px; color:var(--slate);"><?= date('d M Y', strtotime($p['tgl_pengajuan'])) ?></td>
                          <td style="text-align:right;">
                              <button type="button" class="btn btn-primary" style="padding:6px 12px; font-size:12px;" onclick="openReviewModal(<?= $jsonItem ?>)">🔍 Tinjau & Validasi</button>
                          </td>
                      </tr>
                      <?php endforeach; ?>
                  </tbody>
              </table>
          </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><div class="card-title">Riwayat Validasi Terakhir</div></div>
    <div class="card-body">
      <div class="table-wrap">
          <table>
              <thead>
                  <tr>
                      <th>Mahasiswa</th>
                      <th>Judul Akhir (Di-ACC/Ditolak)</th>
                      <th>Bidang</th>
                      <th>Status</th>
                  </tr>
              </thead>
              <tbody>
                  <?php foreach ($riwayatList as $r): ?>
                  <tr>
                      <td>
                          <div style="font-weight:600; font-size:13px;"><?= e($r['nama_lengkap']) ?></div>
                          <div class="mono" style="font-size:11px; color:var(--slate);"><?= e($r['nim']) ?></div>
                      </td>
                      <td style="font-size:12px; max-width:300px; line-height:1.4;"><?= e($r['judul_indonesia']) ?></td>
                      <td style="font-size:12px; color:var(--slate);"><?= e($r['bidang_penelitian']) ?></td>
                      <td><span class="badge <?= badge($r['status']) ?>"><span class="badge-dot"></span><?= e(strtoupper($r['status'])) ?></span></td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if(empty($riwayatList)): ?>
                      <tr><td colspan="4" class="empty-state" style="padding:20px;">Belum ada riwayat validasi.</td></tr>
                  <?php endif; ?>
              </tbody>
          </table>
      </div>
    </div>
  </div>

</div>

<div id="modalReview" style="display:none;position:fixed;inset:0;background:rgba(11,31,58,.6);z-index:1000;align-items:center;justify-content:center; padding:20px;">
  <div style="background:var(--white);border-radius:var(--radius);width:100%;max-width:800px;box-shadow:var(--shadow-lg); display:flex; flex-direction:column; max-height:90vh;">
    
    <div style="display:flex;justify-content:space-between;align-items:center;padding:24px 32px;border-bottom:1px solid var(--cream-dark);">
      <div>
          <h2 style="font-family:'Playfair Display',serif;font-size:20px;margin:0;">Tinjau Pengajuan Judul</h2>
          <div id="modal_mhs_info" style="font-size:13px; color:var(--slate); margin-top:4px;">-</div>
      </div>
      <button type="button" onclick="document.getElementById('modalReview').style.display='none'" style="background:none;border:none;font-size:24px;cursor:pointer;">×</button>
    </div>
    
    <div style="padding:24px 32px; overflow-y:auto; flex:1;">
        <div style="background:#f8f9fa; padding:16px; border-radius:8px; margin-bottom:24px;">
            <div style="font-size:11px; font-weight:700; color:var(--slate); text-transform:uppercase; margin-bottom:8px;">Latar Belakang & Rumusan Masalah</div>
            <div id="modal_latar_belakang" style="font-size:13px; line-height:1.6; color:var(--navy);"></div>
        </div>

        <form id="formValidasi" method="POST">
            <input type="hidden" name="id_judul" id="val_id_judul">
            <input type="hidden" name="id_user_mhs" id="val_id_user_mhs">
            <input type="hidden" name="aksi" id="val_aksi" value="setujui">

            <div style="font-size:14px; font-weight:600; margin-bottom:12px; color:var(--navy);">Pilih Judul Yang Disetujui:</div>
            <div style="display:flex; flex-direction:column; gap:12px; margin-bottom:24px;">
                <label style="display:flex; gap:12px; align-items:flex-start; cursor:pointer; padding:12px; border:1px solid var(--cream-dark); border-radius:8px; transition:0.2s;" onmouseover="this.style.borderColor='var(--info)'" onmouseout="this.style.borderColor='var(--cream-dark)'">
                    <input type="radio" name="judul_terpilih" id="radio_j1" value="" style="margin-top:4px;" required>
                    <div>
                        <div style="font-size:11px; font-weight:600; color:var(--slate); margin-bottom:4px;">Prioritas 1</div>
                        <div id="text_j1" style="font-size:13px; font-weight:500; line-height:1.4;">-</div>
                    </div>
                </label>
                <label style="display:flex; gap:12px; align-items:flex-start; cursor:pointer; padding:12px; border:1px solid var(--cream-dark); border-radius:8px; transition:0.2s;" onmouseover="this.style.borderColor='var(--info)'" onmouseout="this.style.borderColor='var(--cream-dark)'">
                    <input type="radio" name="judul_terpilih" id="radio_j2" value="" style="margin-top:4px;">
                    <div>
                        <div style="font-size:11px; font-weight:600; color:var(--slate); margin-bottom:4px;">Alternatif 1</div>
                        <div id="text_j2" style="font-size:13px; font-weight:500; line-height:1.4;">-</div>
                    </div>
                </label>
                <label id="container_j3" style="display:none; gap:12px; align-items:flex-start; cursor:pointer; padding:12px; border:1px solid var(--cream-dark); border-radius:8px; transition:0.2s;" onmouseover="this.style.borderColor='var(--info)'" onmouseout="this.style.borderColor='var(--cream-dark)'">
                    <input type="radio" name="judul_terpilih" id="radio_j3" value="" style="margin-top:4px;">
                    <div>
                        <div style="font-size:11px; font-weight:600; color:var(--slate); margin-bottom:4px;">Alternatif 2</div>
                        <div id="text_j3" style="font-size:13px; font-weight:500; line-height:1.4;">-</div>
                    </div>
                </label>
            </div>

            <div id="alasanTolak" style="display:none; margin-bottom:24px;">
                <label class="form-label" style="color:var(--danger);">Catatan Penolakan (Hanya diisi jika menolak)</label>
                <textarea class="form-input" name="catatan_penolakan" rows="3" placeholder="Contoh: Judul kurang spesifik, IPK belum memenuhi syarat, dll..."></textarea>
            </div>
    </div>
    
    <div style="padding:24px 32px; border-top:1px solid var(--cream-dark); display:flex; justify-content:space-between; align-items:center; background:#fbfbfc; border-radius:0 0 var(--radius) var(--radius);">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalReview').style.display='none'">Tutup</button>
        <div style="display:flex; gap:12px;">
            <button type="button" class="btn btn-danger" onclick="submitTolak()">❌ Tolak Pengajuan</button>
            <button type="submit" class="btn btn-primary" onclick="submitSetujui()">✅ Setujui Pilihan</button>
        </div>
    </div>
    </form>
    
  </div>
</div>

<script>
function openReviewModal(data) {
    document.getElementById('val_id_judul').value = data.id_judul;
    document.getElementById('val_id_user_mhs').value = data.id_user_mhs;
    
    document.getElementById('modal_mhs_info').innerText = data.nama_lengkap + ' (' + data.nim + ') | ' + data.bidang_penelitian;
    document.getElementById('modal_latar_belakang').innerText = data.latar_belakang;

    // Isi Opsi Judul
    document.getElementById('radio_j1').value = data.judul_indonesia;
    document.getElementById('text_j1').innerText = data.judul_indonesia;
    
    document.getElementById('radio_j2').value = data.judul_alternatif_1;
    document.getElementById('text_j2').innerText = data.judul_alternatif_1;
    
    if (data.judul_alternatif_2 && data.judul_alternatif_2.trim() !== '') {
        document.getElementById('container_j3').style.display = 'flex';
        document.getElementById('radio_j3').value = data.judul_alternatif_2;
        document.getElementById('text_j3').innerText = data.judul_alternatif_2;
    } else {
        document.getElementById('container_j3').style.display = 'none';
    }

    // Tampilkan Modal
    document.getElementById('alasanTolak').style.display = 'none';
    document.getElementById('modalReview').style.display = 'flex';
}

function submitSetujui() {
    document.getElementById('val_aksi').value = 'setujui';
    // Opsi required aktif pada radio button
    document.getElementById('radio_j1').required = true;
}

function submitTolak() {
    // Jika ditolak, radio button tidak wajib
    document.getElementById('radio_j1').required = false;
    document.getElementById('val_aksi').value = 'tolak';
    document.getElementById('alasanTolak').style.display = 'block';
    
    const konfirmasi = confirm("Apakah Anda yakin ingin menolak pengajuan judul ini?");
    if(konfirmasi) {
        document.getElementById('formValidasi').submit();
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>