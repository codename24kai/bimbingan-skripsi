<?php
// admin/dosen.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('admin');

$pageTitle  = 'Kelola Dosen';
$activePage = 'kelola_dosen';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    
    if ($aksi === 'tambah') {
        $db = getDB(); $db->beginTransaction();
        try {
            $idUser = execute('INSERT INTO user (nama_lengkap,email,password_hash,role,status) VALUES (?,?,?,?,?)',
                [$_POST['nama_lengkap'],$_POST['email'],hashPassword('dosen123'),'dosen','aktif']);
            execute('INSERT INTO dosen (id_user,id_prodi,nip,jabatan_fungsional,keahlian,kuota_bimbingan) VALUES (?,?,?,?,?,?)',
                [$idUser,$_POST['id_prodi'],$_POST['nip'],$_POST['jabatan'],$_POST['keahlian'],(int)$_POST['kuota']]);
            $db->commit();
            setFlash('success','Dosen berhasil ditambahkan. Password default: dosen123');
        } catch(\Exception $e) { $db->rollBack(); setFlash('danger','Gagal: '.$e->getMessage()); }
        
    } elseif ($aksi === 'edit') {
        $db = getDB(); $db->beginTransaction();
        try {
            $idUser = (int)$_POST['id_user'];
            
            // Update tabel user
            execute('UPDATE user SET nama_lengkap=?, email=? WHERE id_user=?', 
                [$_POST['nama_lengkap'], $_POST['email'], $idUser]);
                
            // Update tabel dosen
            execute('UPDATE dosen SET id_prodi=?, nip=?, jabatan_fungsional=?, keahlian=?, kuota_bimbingan=? WHERE id_user=?', 
                [$_POST['id_prodi'], $_POST['nip'], $_POST['jabatan'], $_POST['keahlian'], (int)$_POST['kuota'], $idUser]);
                
            $db->commit();
            setFlash('success', 'Data dosen berhasil diperbarui.');
        } catch(\Exception $e) { 
            $db->rollBack(); 
            setFlash('danger', 'Gagal memperbarui: '.$e->getMessage()); 
        }
        
    } elseif ($aksi === 'reset_password') {
        $temp = resetUserPassword((int)($_POST['id_user'] ?? 0), 'dosen');
        if ($temp === null) {
            setFlash('danger','Dosen tidak ditemukan.');
        } else {
            setFlash('success','Password direset. Password sementara: '.$temp.' (catat sekarang, tidak ditampilkan lagi). Pengguna wajib menggantinya saat login.');
        }
    } elseif ($aksi === 'hapus') {
        execute('DELETE FROM user WHERE id_user=?',[(int)$_POST['id_user']]);
        setFlash('success','Data dosen berhasil dihapus.');
    }
    
    header('Location: ' . BASE_URL . '/admin/dosen.php'); exit;
}

$list = fetchAll('SELECT d.*,u.nama_lengkap,u.email,u.status AS status_user,ps.nama_prodi,u.id_user FROM dosen d JOIN user u ON u.id_user=d.id_user JOIN program_studi ps ON ps.id_prodi=d.id_prodi ORDER BY u.nama_lengkap');
$prodi = fetchAll('SELECT * FROM program_studi ORDER BY nama_prodi');

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">
  <?php $flash = getFlash(); if ($flash): ?><div class="alert <?= $flash['type'] ?>"><?= e($flash['msg']) ?></div><?php endif; ?>
  <div class="page-header"><h1>Kelola Dosen</h1><p>Manajemen data dosen pembimbing</p></div>

  <div class="search-bar">
    <div style="flex:1;"></div>
    <button class="btn btn-primary" onclick="document.getElementById('modalDosen').style.display='flex'">+ Tambah Dosen</button>
  </div>

  <div class="card">
    <div class="card-body">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Dosen</th><th>NIP</th><th>Prodi</th><th>Jabatan</th><th>Kuota</th><th>Terisi</th><th>Sisa</th><th style="text-align:right;">Aksi</th></tr></thead>
          <tbody>
            <?php foreach ($list as $d): 
                $jsonData = htmlspecialchars(json_encode($d), ENT_QUOTES, 'UTF-8');
            ?>
              <tr>
                <td>
                  <div style="display:flex;align-items:center;gap:10px;">
                    <div class="mini-avatar" style="width:34px;height:34px;font-size:11px;"><?= strtoupper(substr($d['nama_lengkap'],0,2)) ?></div>
                    <div>
                      <div style="font-weight:500;"><?= e($d['nama_lengkap']) ?></div>
                      <div style="font-size:12px;color:var(--slate);"><?= e($d['email']) ?></div>
                    </div>
                  </div>
                </td>
                <td class="mono" style="font-size:12px;"><?= e($d['nip']) ?></td>
                <td style="font-size:13px;"><?= e($d['nama_prodi']) ?></td>
                <td style="font-size:12px;"><?= e($d['jabatan_fungsional'] ?? '—') ?></td>
                <td style="text-align:center;"><?= $d['kuota_bimbingan'] ?></td>
                <td style="text-align:center;"><?= $d['terisi'] ?></td>
                <td>
                  <?php $sisa = $d['kuota_bimbingan'] - $d['terisi']; ?>
                  <span class="badge <?= $sisa===0?'danger':($sisa<=2?'warning':'success') ?>"><span class="badge-dot"></span><?= $sisa ?></span>
                </td>
                <td style="text-align:right;white-space:nowrap;">
                  <button type="button" class="btn-icon" title="Pratinjau Data" onclick="openPreviewModal(<?= $jsonData ?>)">👁️</button>

                  <button type="button" class="btn-icon" title="Edit Data" onclick="openEditModal(<?= $jsonData ?>)">✏️</button>

                  <form method="POST" style="display:inline;" onsubmit="return confirm('Reset password dosen ini? Password lama tidak akan berlaku lagi.')"><?= csrfField() ?>
                    <input type="hidden" name="aksi" value="reset_password">
                    <input type="hidden" name="id_user" value="<?= $d['id_user'] ?>">
                    <button type="submit" class="btn-icon" title="Reset Password">🔑</button>
                  </form>

                  <form method="POST" onsubmit="return confirm('Hapus dosen ini? Semua data bimbingan terkait akan ikut terhapus.')" style="display:inline;"><?= csrfField() ?>
                    <input type="hidden" name="aksi" value="hapus">
                    <input type="hidden" name="id_user" value="<?= $d['id_user'] ?>">
                    <button type="submit" class="btn-icon" title="Hapus Data">🗑️</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if(empty($list)): ?>
              <tr><td colspan="8" class="empty-state">Data dosen tidak ditemukan.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div id="modalDosen" style="display:none;position:fixed;inset:0;background:rgba(11,31,58,.6);z-index:1000;align-items:center;justify-content:center;">
  <div style="background:var(--white);border-radius:var(--radius);padding:32px;width:100%;max-width:520px;box-shadow:var(--shadow-lg);max-height:90vh;overflow-y:auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <h2 style="font-family:'Playfair Display',serif;font-size:20px;">Tambah Dosen</h2>
      <button type="button" onclick="document.getElementById('modalDosen').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;">×</button>
    </div>
    
    <form method="POST"><?= csrfField() ?>
      <input type="hidden" name="aksi" value="tambah">
      
      <div class="form-group">
          <label class="form-label">Nama Lengkap</label>
          <input class="form-input" name="nama_lengkap" required>
      </div>
      
      <div class="form-row">
        <div class="form-group">
            <label class="form-label">Email</label>
            <input class="form-input" type="email" name="email" required>
        </div>
        <div class="form-group">
            <label class="form-label">NIP</label>
            <input class="form-input" name="nip" required>
        </div>
      </div>
      
      <div class="form-row">
        <div class="form-group">
            <label class="form-label">Program Studi</label>
            <select class="form-input" name="id_prodi" required>
                <?php foreach ($prodi as $p): ?>
                    <option value="<?= $p['id_prodi'] ?>"><?= e($p['nama_prodi']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Jabatan Fungsional</label>
            <select class="form-input" name="jabatan" required>
                <option value="" disabled selected>-- Pilih Jabatan --</option>
                <option value="Tenaga Pengajar">Tenaga Pengajar</option>
                <option value="Asisten Ahli">Asisten Ahli</option>
                <option value="Lektor">Lektor</option>
                <option value="Lektor Kepala">Lektor Kepala</option>
                <option value="Guru Besar">Guru Besar</option>
            </select>
        </div>
      </div>
      
      <div class="form-group">
          <label class="form-label">Bidang Keahlian</label>
          <select class="form-input" name="keahlian" required>
              <option value="" disabled selected>-- Pilih Keahlian --</option>
              <option value="Rekayasa Perangkat Lunak & Web">Rekayasa Perangkat Lunak & Web</option>
              <option value="Pemrograman Mobile">Pemrograman Mobile</option>
              <option value="Kecerdasan Buatan & Machine Learning">Kecerdasan Buatan & Machine Learning</option>
              <option value="Data Science & Analitik">Data Science & Analitik</option>
              <option value="Internet of Things (IoT)">Internet of Things (IoT)</option>
              <option value="Keamanan Siber & Jaringan">Keamanan Siber & Jaringan</option>
              <option value="Manajemen Sistem Informasi">Manajemen Sistem Informasi</option>
          </select>
      </div>
      
      <div class="form-group">
          <label class="form-label">Kuota Bimbingan</label>
          <input class="form-input" type="number" name="kuota" value="10" min="1" max="50" required>
      </div>
      
      <div class="form-actions">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalDosen').style.display='none'">Batal</button>
        <button type="submit" class="btn btn-gold">Simpan</button>
      </div>
    </form>
  </div>
</div>

<div id="modalEdit" style="display:none;position:fixed;inset:0;background:rgba(11,31,58,.6);z-index:1000;align-items:center;justify-content:center;">
  <div style="background:var(--white);border-radius:var(--radius);padding:32px;width:100%;max-width:520px;box-shadow:var(--shadow-lg);max-height:90vh;overflow-y:auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <h2 style="font-family:'Playfair Display',serif;font-size:20px;">Edit Dosen</h2>
      <button type="button" onclick="document.getElementById('modalEdit').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;">×</button>
    </div>
    
    <form method="POST"><?= csrfField() ?>
      <input type="hidden" name="aksi" value="edit">
      <input type="hidden" name="id_user" id="edit_id_user">
      
      <div class="form-group">
          <label class="form-label">Nama Lengkap</label>
          <input class="form-input" name="nama_lengkap" id="edit_nama_lengkap" required>
      </div>
      
      <div class="form-row">
        <div class="form-group">
            <label class="form-label">Email</label>
            <input class="form-input" type="email" name="email" id="edit_email" required>
        </div>
        <div class="form-group">
            <label class="form-label">NIP</label>
            <input class="form-input" name="nip" id="edit_nip" required>
        </div>
      </div>
      
      <div class="form-row">
        <div class="form-group">
            <label class="form-label">Program Studi</label>
            <select class="form-input" name="id_prodi" id="edit_id_prodi" required>
                <?php foreach ($prodi as $p): ?>
                    <option value="<?= $p['id_prodi'] ?>"><?= e($p['nama_prodi']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Jabatan Fungsional</label>
            <select class="form-input" name="jabatan" id="edit_jabatan" required>
                <option value="Tenaga Pengajar">Tenaga Pengajar</option>
                <option value="Asisten Ahli">Asisten Ahli</option>
                <option value="Lektor">Lektor</option>
                <option value="Lektor Kepala">Lektor Kepala</option>
                <option value="Guru Besar">Guru Besar</option>
            </select>
        </div>
      </div>
      
      <div class="form-group">
          <label class="form-label">Bidang Keahlian</label>
          <select class="form-input" name="keahlian" id="edit_keahlian" required>
              <option value="Rekayasa Perangkat Lunak & Web">Rekayasa Perangkat Lunak & Web</option>
              <option value="Pemrograman Mobile">Pemrograman Mobile</option>
              <option value="Kecerdasan Buatan & Machine Learning">Kecerdasan Buatan & Machine Learning</option>
              <option value="Data Science & Analitik">Data Science & Analitik</option>
              <option value="Internet of Things (IoT)">Internet of Things (IoT)</option>
              <option value="Keamanan Siber & Jaringan">Keamanan Siber & Jaringan</option>
              <option value="Manajemen Sistem Informasi">Manajemen Sistem Informasi</option>
          </select>
      </div>
      
      <div class="form-group">
          <label class="form-label">Kuota Bimbingan</label>
          <input class="form-input" type="number" name="kuota" id="edit_kuota" min="1" max="50" required>
      </div>
      
      <div class="form-actions">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalEdit').style.display='none'">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

<div id="modalPreview" style="display:none;position:fixed;inset:0;background:rgba(11,31,58,.6);z-index:1000;align-items:center;justify-content:center;">
  <div style="background:var(--white);border-radius:var(--radius);padding:32px;width:100%;max-width:450px;box-shadow:var(--shadow-lg);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <h2 style="font-family:'Playfair Display',serif;font-size:20px;">Profil Dosen</h2>
      <button type="button" onclick="document.getElementById('modalPreview').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;">×</button>
    </div>
    
    <div style="display:flex; flex-direction:column; gap:16px;">
        <div style="display:grid; grid-template-columns: 140px 1fr; border-bottom: 1px solid var(--cream-dark); padding-bottom: 8px;">
            <span style="color:var(--slate); font-size:13px;">Nama Lengkap</span>
            <strong id="prev_nama" style="font-size:14px;">-</strong>
        </div>
        <div style="display:grid; grid-template-columns: 140px 1fr; border-bottom: 1px solid var(--cream-dark); padding-bottom: 8px;">
            <span style="color:var(--slate); font-size:13px;">Email</span>
            <span id="prev_email" style="font-size:14px;">-</span>
        </div>
        <div style="display:grid; grid-template-columns: 140px 1fr; border-bottom: 1px solid var(--cream-dark); padding-bottom: 8px;">
            <span style="color:var(--slate); font-size:13px;">NIP</span>
            <span id="prev_nip" class="mono" style="font-size:14px;">-</span>
        </div>
        <div style="display:grid; grid-template-columns: 140px 1fr; border-bottom: 1px solid var(--cream-dark); padding-bottom: 8px;">
            <span style="color:var(--slate); font-size:13px;">Program Studi</span>
            <span id="prev_prodi" style="font-size:14px;">-</span>
        </div>
        <div style="display:grid; grid-template-columns: 140px 1fr; border-bottom: 1px solid var(--cream-dark); padding-bottom: 8px;">
            <span style="color:var(--slate); font-size:13px;">Jabatan Fungsional</span>
            <span id="prev_jabatan" style="font-size:14px;">-</span>
        </div>
        <div style="display:grid; grid-template-columns: 140px 1fr; border-bottom: 1px solid var(--cream-dark); padding-bottom: 8px;">
            <span style="color:var(--slate); font-size:13px;">Bidang Keahlian</span>
            <strong id="prev_keahlian" style="font-size:14px; color:var(--navy);">-</strong>
        </div>
        <div style="display:grid; grid-template-columns: 140px 1fr; border-bottom: 1px solid var(--cream-dark); padding-bottom: 8px;">
            <span style="color:var(--slate); font-size:13px;">Kuota Maksimal</span>
            <span id="prev_kuota" style="font-size:14px;">-</span>
        </div>
        <div style="display:grid; grid-template-columns: 140px 1fr;">
            <span style="color:var(--slate); font-size:13px;">Mahasiswa Bimbingan</span>
            <strong id="prev_terisi" style="font-size:14px; color:var(--info);">-</strong>
        </div>
    </div>

    <div class="form-actions" style="margin-top:24px;">
      <button type="button" class="btn btn-secondary btn-full" onclick="document.getElementById('modalPreview').style.display='none'" style="justify-content:center;">Tutup</button>
    </div>
  </div>
</div>

<script>
// Fungsi untuk mengisi dan membuka modal Edit
function openEditModal(data) {
    document.getElementById('edit_id_user').value = data.id_user;
    document.getElementById('edit_nama_lengkap').value = data.nama_lengkap;
    document.getElementById('edit_email').value = data.email;
    document.getElementById('edit_nip').value = data.nip;
    document.getElementById('edit_id_prodi').value = data.id_prodi;
    document.getElementById('edit_jabatan').value = data.jabatan_fungsional;
    document.getElementById('edit_keahlian').value = data.keahlian;
    document.getElementById('edit_kuota').value = data.kuota_bimbingan;
    
    document.getElementById('modalEdit').style.display = 'flex';
}

// Fungsi untuk mengisi dan membuka modal Pratinjau
function openPreviewModal(data) {
    document.getElementById('prev_nama').innerText = data.nama_lengkap;
    document.getElementById('prev_email').innerText = data.email;
    document.getElementById('prev_nip').innerText = data.nip;
    document.getElementById('prev_prodi').innerText = data.nama_prodi;
    document.getElementById('prev_jabatan').innerText = data.jabatan_fungsional ? data.jabatan_fungsional : '-';
    document.getElementById('prev_keahlian').innerText = data.keahlian ? data.keahlian : '-';
    document.getElementById('prev_kuota').innerText = data.kuota_bimbingan + ' Mahasiswa';
    document.getElementById('prev_terisi').innerText = data.terisi + ' Mahasiswa (Aktif)';
    
    document.getElementById('modalPreview').style.display = 'flex';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>