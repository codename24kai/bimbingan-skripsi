<?php
// admin/mahasiswa.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('admin');

$pageTitle  = 'Kelola Mahasiswa';
$activePage = 'kelola_mahasiswa';
$q = trim($_GET['q'] ?? '');

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah') {
        $db = getDB();
        $db->beginTransaction();
        try {
            $idUser = execute(
                'INSERT INTO user (nama_lengkap,email,password_hash,role,status) VALUES (?,?,?,?,?)',
                [$_POST['nama_lengkap'], $_POST['email'], hashPassword('mhs123'), 'mahasiswa', 'aktif']
            );
            execute('INSERT INTO mahasiswa (id_user,id_prodi,nim,angkatan,status_skripsi) VALUES (?,?,?,?,"belum")',
                [$idUser, $_POST['id_prodi'], $_POST['nim'], $_POST['angkatan']]);
            $db->commit();
            setFlash('success', 'Mahasiswa berhasil ditambahkan. Password default: mhs123');
        } catch (\Exception $e) {
            $db->rollBack();
            setFlash('danger', 'Gagal: ' . $e->getMessage());
        }
    } elseif ($aksi === 'edit') {
        $db = getDB();
        $db->beginTransaction();
        try {
            $idUser = (int)$_POST['id_user'];
            
            // 1. Update data dasar di tabel user
            execute('UPDATE user SET nama_lengkap=?, email=? WHERE id_user=?', 
                [$_POST['nama_lengkap'], $_POST['email'], $idUser]);
                
            // 2. Update data akademik di tabel mahasiswa
            execute('UPDATE mahasiswa SET id_prodi=?, nim=?, angkatan=? WHERE id_user=?', 
                [$_POST['id_prodi'], $_POST['nim'], $_POST['angkatan'], $idUser]);
                
            $db->commit();
            setFlash('success', 'Data mahasiswa berhasil diperbarui.');
        } catch (\Exception $e) {
            $db->rollBack();
            setFlash('danger', 'Gagal memperbarui: ' . $e->getMessage());
        }
    } elseif ($aksi === 'hapus') {
        execute('DELETE FROM user WHERE id_user=?', [(int)$_POST['id_user']]);
        setFlash('success', 'Data mahasiswa berhasil dihapus.');
    } elseif ($aksi === 'reset_password') {
        $temp = resetUserPassword((int)($_POST['id_user'] ?? 0), 'mahasiswa');
        if ($temp === null) {
            setFlash('danger', 'Mahasiswa tidak ditemukan.');
        } else {
            setFlash('success', 'Password direset. Password sementara: ' . $temp . ' (catat sekarang, tidak ditampilkan lagi). Pengguna wajib menggantinya saat login.');
        }
    } elseif ($aksi === 'toggle_status') {
        $user = fetchOne('SELECT status FROM user WHERE id_user=?', [(int)$_POST['id_user']]);
        $newStatus = ($user['status'] === 'aktif') ? 'nonaktif' : 'aktif';
        execute('UPDATE user SET status=? WHERE id_user=?', [$newStatus, (int)$_POST['id_user']]);
        setFlash('success', 'Status berhasil diubah.');
    }
    header('Location: ' . BASE_URL . '/admin/mahasiswa.php');
    exit;
}

$sql = 'SELECT m.*,u.nama_lengkap,u.email,u.status AS status_user,ps.nama_prodi,u.id_user,
               (SELECT ud.nama_lengkap FROM bimbingan b JOIN dosen d ON d.id_dosen=b.id_dosen JOIN user ud ON ud.id_user=d.id_user WHERE b.id_mahasiswa=m.id_mahasiswa AND b.status="aktif" LIMIT 1) AS nama_dosen
        FROM mahasiswa m JOIN user u ON u.id_user=m.id_user JOIN program_studi ps ON ps.id_prodi=m.id_prodi';
$params = [];
if ($q) { $sql .= ' WHERE (u.nama_lengkap LIKE ? OR m.nim LIKE ?)'; $params = ["%$q%","%$q%"]; }
$sql .= ' ORDER BY u.nama_lengkap';
$list = fetchAll($sql, $params);

$prodi = fetchAll('SELECT * FROM program_studi ORDER BY nama_prodi');

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">
  <?php $flash = getFlash(); if ($flash): ?><div class="alert <?= $flash['type'] ?>"><?= e($flash['msg']) ?></div><?php endif; ?>
  <div class="page-header"><h1>Kelola Mahasiswa</h1><p>Manajemen data akun dan status mahasiswa</p></div>

  <div class="search-bar">
    <form method="GET" style="display:contents;">
      <div class="search-input-wrap"><span class="search-icon">🔍</span><input name="q" aria-label="Cari mahasiswa" placeholder="Cari nama atau NIM..." value="<?= e($q) ?>"></div>
      <button type="submit" class="btn btn-secondary">Cari</button>
    </form>
    <button class="btn btn-primary" onclick="document.getElementById('modalTambah').style.display='flex'">+ Tambah Mahasiswa</button>
  </div>

  <div class="card">
    <div class="card-body">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Mahasiswa</th><th>NIM</th><th>Prodi</th><th>Dosen Pembimbing</th><th>Skripsi</th><th>Status</th><th style="text-align:right;">Aksi</th></tr></thead>
          <tbody>
            <?php foreach ($list as $m): 
                // Encode data row menjadi JSON agar mudah dibaca oleh Javascript
                $jsonData = htmlspecialchars(json_encode($m + ['status_skripsi_label' => label($m['status_skripsi'])]), ENT_QUOTES, 'UTF-8');
            ?>
              <tr>
                <td>
                  <div style="display:flex;align-items:center;gap:10px;">
                    <div class="mini-avatar" style="width:34px;height:34px;font-size:11px;"><?= strtoupper(substr($m['nama_lengkap'],0,2)) ?></div>
                    <div>
                      <div style="font-weight:500;"><?= e($m['nama_lengkap']) ?></div>
                      <div style="font-size:12px;color:var(--slate);"><?= e($m['email']) ?></div>
                    </div>
                  </div>
                </td>
                <td class="mono" style="font-size:12px;"><?= e($m['nim']) ?></td>
                <td style="font-size:13px;"><?= e($m['nama_prodi']) ?></td>
                <td style="font-size:13px;"><?= e($m['nama_dosen'] ?? '—') ?></td>
                <td><span class="badge <?= badge($m['status_skripsi']) ?>"><span class="badge-dot"></span><?= e(label($m['status_skripsi'])) ?></span></td>
                <td>
                  <form method="POST" style="display:inline;"><?= csrfField() ?>
                    <input type="hidden" name="aksi" value="toggle_status">
                    <input type="hidden" name="id_user" value="<?= $m['id_user'] ?>">
                    <button type="submit" class="badge <?= $m['status_user']==='aktif' ? 'success' : 'danger' ?>" style="cursor:pointer;border:none;" title="Klik untuk mengubah status">
                      <span class="badge-dot"></span><?= e(label($m['status_user'])) ?>
                    </button>
                  </form>
                </td>
                <td style="text-align:right;">
                  <button type="button" class="btn-icon" title="Pratinjau Data" onclick="openPreviewModal(<?= $jsonData ?>)">👁️</button>
                  
                  <button type="button" class="btn-icon" title="Edit Data" onclick="openEditModal(<?= $jsonData ?>)">✏️</button>
                  
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Reset password mahasiswa ini? Password lama tidak akan berlaku lagi.')"><?= csrfField() ?>
                    <input type="hidden" name="aksi" value="reset_password">
                    <input type="hidden" name="id_user" value="<?= $m['id_user'] ?>">
                    <button type="submit" class="btn-icon" title="Reset Password">🔑</button>
                  </form>

                  <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus mahasiswa ini? Semua data terkait (bimbingan, dokumen) akan ikut terhapus.')"><?= csrfField() ?>
                    <input type="hidden" name="aksi" value="hapus">
                    <input type="hidden" name="id_user" value="<?= $m['id_user'] ?>">
                    <button type="submit" class="btn-icon" title="Hapus Data">🗑️</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if(empty($list)): ?>
              <tr><td colspan="7" class="empty-state">Data mahasiswa tidak ditemukan.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div id="modalTambah" style="display:none;position:fixed;inset:0;background:rgba(11,31,58,.6);z-index:1000;align-items:center;justify-content:center;">
  <div style="background:var(--white);border-radius:var(--radius);padding:32px;width:100%;max-width:500px;box-shadow:var(--shadow-lg);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <h2 style="font-family:'Playfair Display',serif;font-size:20px;">Tambah Mahasiswa</h2>
      <button onclick="document.getElementById('modalTambah').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;">×</button>
    </div>
    <form method="POST"><?= csrfField() ?>
      <input type="hidden" name="aksi" value="tambah">
      <div class="form-group"><label for="f_nama_lengkap" class="form-label">Nama Lengkap</label><input id="f_nama_lengkap" class="form-input" name="nama_lengkap" required></div>
      <div class="form-row">
        <div class="form-group"><label for="f_email" class="form-label">Email</label><input id="f_email" class="form-input" type="email" name="email" required></div>
        <div class="form-group"><label for="f_nim" class="form-label">NIM</label><input id="f_nim" class="form-input" name="nim" required></div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="f_id_prodi" class="form-label">Program Studi</label>
          <select id="f_id_prodi" class="form-input" name="id_prodi" required>
            <?php foreach ($prodi as $p): ?><option value="<?= $p['id_prodi'] ?>"><?= e($p['nama_prodi']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label for="f_angkatan" class="form-label">Angkatan</label><input id="f_angkatan" class="form-input" name="angkatan" type="number" value="<?= date('Y') ?>" required></div>
      </div>
      <div class="form-actions">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalTambah').style.display='none'">Batal</button>
        <button type="submit" class="btn btn-gold">Simpan</button>
      </div>
    </form>
  </div>
</div>

<div id="modalEdit" style="display:none;position:fixed;inset:0;background:rgba(11,31,58,.6);z-index:1000;align-items:center;justify-content:center;">
  <div style="background:var(--white);border-radius:var(--radius);padding:32px;width:100%;max-width:500px;box-shadow:var(--shadow-lg);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <h2 style="font-family:'Playfair Display',serif;font-size:20px;">Edit Mahasiswa</h2>
      <button onclick="document.getElementById('modalEdit').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;">×</button>
    </div>
    <form method="POST"><?= csrfField() ?>
      <input type="hidden" name="aksi" value="edit">
      <input type="hidden" name="id_user" id="edit_id_user">
      <div class="form-group"><label for="edit_nama_lengkap" class="form-label">Nama Lengkap</label><input class="form-input" name="nama_lengkap" id="edit_nama_lengkap" required></div>
      <div class="form-row">
        <div class="form-group"><label for="edit_email" class="form-label">Email</label><input class="form-input" type="email" name="email" id="edit_email" required></div>
        <div class="form-group"><label for="edit_nim" class="form-label">NIM</label><input class="form-input" name="nim" id="edit_nim" required></div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="edit_id_prodi" class="form-label">Program Studi</label>
          <select class="form-input" name="id_prodi" id="edit_id_prodi" required>
            <?php foreach ($prodi as $p): ?><option value="<?= $p['id_prodi'] ?>"><?= e($p['nama_prodi']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label for="edit_angkatan" class="form-label">Angkatan</label><input class="form-input" name="angkatan" id="edit_angkatan" type="number" required></div>
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
      <h2 style="font-family:'Playfair Display',serif;font-size:20px;">Detail Mahasiswa</h2>
      <button onclick="document.getElementById('modalPreview').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;">×</button>
    </div>
    
    <div style="display:flex; flex-direction:column; gap:16px;">
        <div class="detail-row">
            <span style="color:var(--slate); font-size:13px;">Nama Lengkap</span>
            <strong id="prev_nama" style="font-size:14px;">-</strong>
        </div>
        <div class="detail-row">
            <span style="color:var(--slate); font-size:13px;">Email</span>
            <span id="prev_email" style="font-size:14px;">-</span>
        </div>
        <div class="detail-row">
            <span style="color:var(--slate); font-size:13px;">NIM</span>
            <span id="prev_nim" class="mono" style="font-size:14px;">-</span>
        </div>
        <div class="detail-row">
            <span style="color:var(--slate); font-size:13px;">Program Studi</span>
            <span id="prev_prodi" style="font-size:14px;">-</span>
        </div>
        <div class="detail-row">
            <span style="color:var(--slate); font-size:13px;">Angkatan</span>
            <span id="prev_angkatan" style="font-size:14px;">-</span>
        </div>
        <div class="detail-row">
            <span style="color:var(--slate); font-size:13px;">No. Telepon</span>
            <span id="prev_notelp" style="font-size:14px;">-</span>
        </div>
        <div class="detail-row">
            <span style="color:var(--slate); font-size:13px;">Status Skripsi</span>
            <span id="prev_status" style="font-size:14px;">-</span>
        </div>
        <div class="detail-row detail-row--last">
            <span style="color:var(--slate); font-size:13px;">Dosen Pembimbing</span>
            <strong id="prev_dosen" style="font-size:14px; color:var(--info-text);">Belum ada</strong>
        </div>
    </div>

    <div class="form-actions" style="margin-top:24px;">
      <button type="button" class="btn btn-secondary btn-full" onclick="document.getElementById('modalPreview').style.display='none'" style="justify-content:center;">Tutup</button>
    </div>
  </div>
</div>

<script>
// Fungsi untuk memicu modal Edit beserta pengisian data
function openEditModal(data) {
    document.getElementById('edit_id_user').value = data.id_user;
    document.getElementById('edit_nama_lengkap').value = data.nama_lengkap;
    document.getElementById('edit_email').value = data.email;
    document.getElementById('edit_nim').value = data.nim;
    document.getElementById('edit_id_prodi').value = data.id_prodi;
    document.getElementById('edit_angkatan').value = data.angkatan;
    
    document.getElementById('modalEdit').style.display = 'flex';
}

// Fungsi untuk memicu modal Pratinjau beserta pengisian data
function openPreviewModal(data) {
    document.getElementById('prev_nama').innerText = data.nama_lengkap;
    document.getElementById('prev_email').innerText = data.email;
    document.getElementById('prev_nim').innerText = data.nim;
    document.getElementById('prev_prodi').innerText = data.nama_prodi;
    document.getElementById('prev_angkatan').innerText = data.angkatan;
    document.getElementById('prev_notelp').innerText = data.no_telepon ? data.no_telepon : 'Belum diisi';
    document.getElementById('prev_status').innerText = data.status_skripsi_label || data.status_skripsi;
    document.getElementById('prev_dosen').innerText = data.nama_dosen ? data.nama_dosen : 'Belum ada pembimbing aktif';
    
    document.getElementById('modalPreview').style.display = 'flex';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>