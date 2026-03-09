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
                [$_POST['nama_lengkap'], $_POST['email'], hash('sha256','mhs123'), 'mahasiswa', 'aktif']
            );
            execute('INSERT INTO mahasiswa (id_user,id_prodi,nim,angkatan,status_skripsi) VALUES (?,?,?,?,"belum")',
                [$idUser, $_POST['id_prodi'], $_POST['nim'], $_POST['angkatan']]);
            $db->commit();
            setFlash('success', 'Mahasiswa berhasil ditambahkan. Password default: mhs123');
        } catch (\Exception $e) {
            $db->rollBack();
            setFlash('danger', 'Gagal: ' . $e->getMessage());
        }
    } elseif ($aksi === 'hapus') {
        execute('DELETE FROM user WHERE id_user=?', [(int)$_POST['id_user']]);
        setFlash('success', 'Data mahasiswa berhasil dihapus.');
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
      <div class="search-input-wrap"><span class="search-icon">🔍</span><input name="q" placeholder="Cari nama atau NIM..." value="<?= e($q) ?>"></div>
      <button type="submit" class="btn btn-secondary">Cari</button>
    </form>

    <!-- Modal toggle tambah -->
    <button class="btn btn-primary" onclick="document.getElementById('modalTambah').style.display='flex'">+ Tambah Mahasiswa</button>
  </div>

  <div class="card">
    <div class="card-body">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Mahasiswa</th><th>NIM</th><th>Prodi</th><th>Dosen Pembimbing</th><th>Skripsi</th><th>Status</th><th>Aksi</th></tr></thead>
          <tbody>
            <?php foreach ($list as $m): ?>
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
                <td><span class="badge <?= badge($m['status_skripsi']) ?>"><span class="badge-dot"></span><?= $m['status_skripsi'] ?></span></td>
                <td>
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="aksi" value="toggle_status">
                    <input type="hidden" name="id_user" value="<?= $m['id_user'] ?>">
                    <button type="submit" class="badge <?= $m['status_user']==='aktif' ? 'success' : 'danger' ?>" style="cursor:pointer;border:none;">
                      <span class="badge-dot"></span><?= $m['status_user'] ?>
                    </button>
                  </form>
                </td>
                <td>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus mahasiswa ini?')">
                    <input type="hidden" name="aksi" value="hapus">
                    <input type="hidden" name="id_user" value="<?= $m['id_user'] ?>">
                    <button type="submit" class="btn-icon">🗑️</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- MODAL Tambah -->
<div id="modalTambah" style="display:none;position:fixed;inset:0;background:rgba(11,31,58,.6);z-index:1000;align-items:center;justify-content:center;">
  <div style="background:var(--white);border-radius:var(--radius);padding:32px;width:100%;max-width:500px;box-shadow:var(--shadow-lg);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <h2 style="font-family:'Playfair Display',serif;font-size:20px;">Tambah Mahasiswa</h2>
      <button onclick="document.getElementById('modalTambah').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="aksi" value="tambah">
      <div class="form-group"><label class="form-label">Nama Lengkap</label><input class="form-input" name="nama_lengkap" required></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Email</label><input class="form-input" type="email" name="email" required></div>
        <div class="form-group"><label class="form-label">NIM</label><input class="form-input" name="nim" required></div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Program Studi</label>
          <select class="form-input" name="id_prodi" required>
            <?php foreach ($prodi as $p): ?><option value="<?= $p['id_prodi'] ?>"><?= e($p['nama_prodi']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Angkatan</label><input class="form-input" name="angkatan" type="number" value="<?= date('Y') ?>" required></div>
      </div>
      <div class="form-actions">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalTambah').style.display='none'">Batal</button>
        <button type="submit" class="btn btn-gold">Simpan</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
