<?php
// admin/pengaturan.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('admin');

$pageTitle  = 'Pengaturan';
$activePage = 'pengaturan';

$db = getDB();

// 1. AUTO-CREATE TABEL PENGATURAN & NILAI DEFAULT (Jika belum ada)
$db->exec("CREATE TABLE IF NOT EXISTS pengaturan (
    nama_key VARCHAR(50) PRIMARY KEY,
    nilai_value TEXT NOT NULL
) ENGINE=InnoDB COMMENT='Tabel konfigurasi umum sistem';");

$cekPengaturan = fetchOne("SELECT COUNT(*) as cnt FROM pengaturan");
if ($cekPengaturan['cnt'] == 0) {
    // Memberikan nilai default awal
    $db->exec("INSERT INTO pengaturan (nama_key, nilai_value) VALUES 
        ('nama_institusi', 'Universitas Pamulang (UNPAM)'),
        ('tahun_akademik', '2025/2026'),
        ('semester_aktif', 'Ganjil'),
        ('default_kuota_dosen', '10'),
        ('maksimal_kuota_dosen', '20')
    ");
} else {
    // Patching: Cek dan tambahkan key maksimal_kuota_dosen jika tabel sudah ada tapi key ini belum ada
    $cekMax = fetchOne("SELECT COUNT(*) as cnt FROM pengaturan WHERE nama_key='maksimal_kuota_dosen'");
    if ($cekMax['cnt'] == 0) {
        $db->exec("INSERT INTO pengaturan (nama_key, nilai_value) VALUES ('maksimal_kuota_dosen', '20')");
    }
}

// 2. HANDLE REQUEST (POST ACTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'simpan_umum') {
        $default_kuota = (int)$_POST['default_kuota_dosen'];
        $max_kuota     = (int)$_POST['maksimal_kuota_dosen'];

        // Validasi agar default tidak melebihi maksimal
        if ($default_kuota > $max_kuota) {
            setFlash('danger', 'Gagal: Nilai Default Kuota tidak boleh melebihi Batas Maksimal Kuota.');
        } else {
            // Update Pengaturan Umum
            $settings = [
                'nama_institusi'       => $_POST['nama_institusi'],
                'tahun_akademik'       => $_POST['tahun_akademik'],
                'semester_aktif'       => $_POST['semester_aktif'],
                'default_kuota_dosen'  => $default_kuota,
                'maksimal_kuota_dosen' => $max_kuota
            ];
            
            $db->beginTransaction();
            try {
                foreach ($settings as $key => $val) {
                    execute("UPDATE pengaturan SET nilai_value = ? WHERE nama_key = ?", [$val, $key]);
                }
                $db->commit();
                setFlash('success', 'Konfigurasi umum berhasil disimpan.');
            } catch (\Exception $e) {
                $db->rollBack();
                setFlash('danger', 'Gagal menyimpan pengaturan: ' . $e->getMessage());
            }
        }

    } elseif ($aksi === 'tambah_prodi') {
        try {
            execute("INSERT INTO program_studi (kode_prodi, nama_prodi, jenjang, fakultas, akreditasi) VALUES (?, ?, ?, ?, ?)", [
                strtoupper($_POST['kode_prodi']), 
                $_POST['nama_prodi'], 
                $_POST['jenjang'], 
                'Fakultas Ilmu Komputer', 
                strtoupper($_POST['akreditasi'])
            ]);
            setFlash('success', 'Program Studi baru berhasil ditambahkan.');
        } catch (\Exception $e) {
            setFlash('danger', 'Gagal menambah Prodi. Pastikan Kode Prodi belum digunakan.');
        }
        
    } elseif ($aksi === 'edit_prodi') {
        try {
            execute("UPDATE program_studi SET kode_prodi=?, nama_prodi=?, jenjang=?, akreditasi=? WHERE id_prodi=?", [
                strtoupper($_POST['kode_prodi']), 
                $_POST['nama_prodi'], 
                $_POST['jenjang'], 
                strtoupper($_POST['akreditasi']),
                (int)$_POST['id_prodi']
            ]);
            setFlash('success', 'Data Program Studi berhasil diperbarui.');
        } catch (\Exception $e) {
            setFlash('danger', 'Gagal memperbarui Program Studi.');
        }
        
    } elseif ($aksi === 'hapus_prodi') {
        try {
            execute('DELETE FROM program_studi WHERE id_prodi=?', [(int)$_POST['id_prodi']]);
            setFlash('success', 'Program Studi berhasil dihapus.');
        } catch (\Exception $e) {
            setFlash('danger', 'Gagal menghapus: Program Studi ini masih terikat dengan data Dosen atau Mahasiswa.');
        }
    }
    
    header('Location: ' . BASE_URL . '/admin/pengaturan.php'); 
    exit;
}

// 3. AMBIL DATA UNTUK DITAMPILKAN DI VIEW
$rawSettings = fetchAll("SELECT * FROM pengaturan");
$opt = [];
foreach ($rawSettings as $s) {
    $opt[$s['nama_key']] = $s['nilai_value'];
}

$prodi = fetchAll('SELECT * FROM program_studi ORDER BY nama_prodi');

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">
  <?php $flash = getFlash(); if ($flash): ?><div class="alert <?= $flash['type'] ?>"><?= e($flash['msg']) ?></div><?php endif; ?>
  
  <div class="page-header">
      <h1>Pengaturan Sistem</h1>
      <p>Konfigurasi global portal bimbingan skripsi</p>
  </div>

  <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: start;">
    
    <div class="form-card" style="background:var(--white); padding:24px; border-radius:var(--radius); box-shadow:0 1px 3px rgba(0,0,0,0.05);">
      <h2 style="font-size:18px; font-family:'Playfair Display',serif; margin-bottom:8px;">Konfigurasi Umum</h2>
      <p style="font-size:13px; color:var(--slate); margin-bottom:20px;">Pengaturan dasar sistem portal bimbingan.</p>
      
      <form method="POST">
        <input type="hidden" name="aksi" value="simpan_umum">
        
        <div class="form-group">
            <label class="form-label">Nama Institusi / Perguruan Tinggi</label>
            <input class="form-input" name="nama_institusi" value="<?= e($opt['nama_institusi'] ?? '') ?>" required>
        </div>
        
        <div class="form-row">
          <div class="form-group">
              <label class="form-label">Tahun Akademik</label>
              <input class="form-input" name="tahun_akademik" placeholder="Contoh: 2025/2026" value="<?= e($opt['tahun_akademik'] ?? '') ?>" required>
          </div>
          <div class="form-group">
              <label class="form-label">Semester Aktif</label>
              <select class="form-input" name="semester_aktif" required>
                  <option value="Ganjil" <?= ($opt['semester_aktif'] === 'Ganjil') ? 'selected' : '' ?>>Ganjil</option>
                  <option value="Genap" <?= ($opt['semester_aktif'] === 'Genap') ? 'selected' : '' ?>>Genap</option>
              </select>
          </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Default Kuota Dosen</label>
                <input class="form-input" type="number" name="default_kuota_dosen" min="1" value="<?= e($opt['default_kuota_dosen'] ?? '10') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Batas Maksimal Kuota</label>
                <input class="form-input" type="number" name="maksimal_kuota_dosen" min="1" value="<?= e($opt['maksimal_kuota_dosen'] ?? '20') ?>" required>
            </div>
        </div>
        <small style="color:var(--slate); font-size:12px; display:block; margin-bottom:16px;">Angka default akan otomatis terisi saat menambah dosen baru, sedangkan Batas Maksimal adalah kuota tertinggi yang tidak dapat dilampaui.</small>
        
        <button type="submit" class="btn btn-gold" style="width:100%; justify-content:center;">💾 Simpan Konfigurasi</button>
      </form>
    </div>

    <div class="card">
      <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <div class="card-title">Program Studi (Ilkom)</div>
        <button class="btn btn-primary" style="padding:6px 12px; font-size:12px;" onclick="document.getElementById('modalTambahProdi').style.display='flex'">+ Tambah</button>
      </div>
      <div class="card-body">
        <div class="table-wrap">
          <table>
            <thead><tr><th>Kode</th><th>Program Studi</th><th>Akreditasi</th><th style="text-align:right;">Aksi</th></tr></thead>
            <tbody>
              <?php foreach ($prodi as $p): 
                $jsonProdi = htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8');
              ?>
                <tr>
                  <td class="mono" style="font-size:12px; font-weight:600;"><?= e($p['kode_prodi']) ?></td>
                  <td>
                      <div style="font-size:13px; font-weight:500;"><?= e($p['nama_prodi']) ?></div>
                      <div style="font-size:11px; color:var(--slate);">Jenjang: <?= e($p['jenjang']) ?></div>
                  </td>
                  <td>
                      <span class="badge info" style="font-weight:700;"><?= e($p['akreditasi'] ?? '—') ?></span>
                  </td>
                  <td style="text-align:right;">
                      <button type="button" class="btn-icon" title="Edit Prodi" onclick="openEditProdi(<?= $jsonProdi ?>)">✏️</button>
                      <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus prodi ini?')">
                          <input type="hidden" name="aksi" value="hapus_prodi">
                          <input type="hidden" name="id_prodi" value="<?= $p['id_prodi'] ?>">
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
</div>

<div id="modalTambahProdi" style="display:none;position:fixed;inset:0;background:rgba(11,31,58,.6);z-index:1000;align-items:center;justify-content:center;">
  <div style="background:var(--white);border-radius:var(--radius);padding:32px;width:100%;max-width:450px;box-shadow:var(--shadow-lg);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <h2 style="font-family:'Playfair Display',serif;font-size:18px;">Tambah Program Studi</h2>
      <button onclick="document.getElementById('modalTambahProdi').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="aksi" value="tambah_prodi">
      <div class="form-group">
          <label class="form-label">Nama Program Studi</label>
          <input class="form-input" name="nama_prodi" required>
      </div>
      <div class="form-row">
        <div class="form-group">
            <label class="form-label">Kode</label>
            <input class="form-input" name="kode_prodi" required>
        </div>
        <div class="form-group">
            <label class="form-label">Jenjang</label>
            <select class="form-input" name="jenjang" required>
                <option value="D3">D3</option>
                <option value="S1" selected>S1</option>
                <option value="S2">S2</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Akreditasi</label>
            <input class="form-input" name="akreditasi" maxlength="10">
        </div>
      </div>
      <div class="form-actions">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalTambahProdi').style.display='none'">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
      </div>
    </form>
  </div>
</div>

<div id="modalEditProdi" style="display:none;position:fixed;inset:0;background:rgba(11,31,58,.6);z-index:1000;align-items:center;justify-content:center;">
  <div style="background:var(--white);border-radius:var(--radius);padding:32px;width:100%;max-width:450px;box-shadow:var(--shadow-lg);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
      <h2 style="font-family:'Playfair Display',serif;font-size:18px;">Edit Program Studi</h2>
      <button onclick="document.getElementById('modalEditProdi').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="aksi" value="edit_prodi">
      <input type="hidden" name="id_prodi" id="edit_id_prodi">
      <div class="form-group">
          <label class="form-label">Nama Program Studi</label>
          <input class="form-input" name="nama_prodi" id="edit_nama_prodi" required>
      </div>
      <div class="form-row">
        <div class="form-group">
            <label class="form-label">Kode</label>
            <input class="form-input" name="kode_prodi" id="edit_kode_prodi" required>
        </div>
        <div class="form-group">
            <label class="form-label">Jenjang</label>
            <select class="form-input" name="jenjang" id="edit_jenjang" required>
                <option value="D3">D3</option>
                <option value="S1">S1</option>
                <option value="S2">S2</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Akreditasi</label>
            <input class="form-input" name="akreditasi" id="edit_akreditasi" maxlength="10">
        </div>
      </div>
      <div class="form-actions">
        <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalEditProdi').style.display='none'">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditProdi(data) {
    document.getElementById('edit_id_prodi').value = data.id_prodi;
    document.getElementById('edit_nama_prodi').value = data.nama_prodi;
    document.getElementById('edit_kode_prodi').value = data.kode_prodi;
    document.getElementById('edit_jenjang').value = data.jenjang;
    document.getElementById('edit_akreditasi').value = data.akreditasi;
    
    document.getElementById('modalEditProdi').style.display = 'flex';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>