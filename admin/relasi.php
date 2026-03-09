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
    if ($aksi === 'buat') {
        $id_mhs   = (int)$_POST['id_mahasiswa'];
        $id_dosen = (int)$_POST['id_dosen'];
        $id_judul = (int)$_POST['id_judul'];
        $semester = trim($_POST['semester'] ?? '');

        $existing = countRows('SELECT COUNT(*) FROM bimbingan WHERE id_mahasiswa=? AND status="aktif"', [$id_mhs]);
        if ($existing > 0) {
            setFlash('danger', 'Mahasiswa sudah memiliki bimbingan aktif.');
        } else {
            execute('INSERT INTO bimbingan (id_mahasiswa,id_dosen,id_judul,semester,tgl_mulai,status)
                     VALUES (?,?,?,?,CURDATE(),"aktif")', [$id_mhs,$id_dosen,$id_judul,$semester]);
            setFlash('success', 'Relasi bimbingan berhasil dibuat.');
        }
    } elseif ($aksi === 'hentikan') {
        execute('UPDATE bimbingan SET status="dihentikan",tgl_selesai=CURDATE() WHERE id_bimbingan=?', [(int)$_POST['id_bimbingan']]);
        setFlash('warning', 'Relasi bimbingan dihentikan.');
    }
    header('Location: ' . BASE_URL . '/admin/relasi.php');
    exit;
}

$relasi = fetchAll(
    'SELECT b.*,um.nama_lengkap AS nama_mhs,m.nim,ud.nama_lengkap AS nama_dosen,j.judul_indonesia
     FROM bimbingan b
     JOIN mahasiswa m ON m.id_mahasiswa=b.id_mahasiswa JOIN user um ON um.id_user=m.id_user
     JOIN dosen d ON d.id_dosen=b.id_dosen JOIN user ud ON ud.id_user=d.id_user
     JOIN judul_skripsi j ON j.id_judul=b.id_judul
     ORDER BY b.status,b.tgl_mulai DESC'
);

// Mahasiswa tanpa bimbingan aktif
$mhsTanpaBimb = fetchAll(
    'SELECT m.id_mahasiswa,m.nim,u.nama_lengkap FROM mahasiswa m JOIN user u ON u.id_user=m.id_user
     WHERE NOT EXISTS (SELECT 1 FROM bimbingan b WHERE b.id_mahasiswa=m.id_mahasiswa AND b.status="aktif")
     ORDER BY u.nama_lengkap'
);
$dosenList = fetchAll('SELECT d.id_dosen,u.nama_lengkap,(d.kuota_bimbingan-d.terisi) AS sisa FROM dosen d JOIN user u ON u.id_user=d.id_user WHERE d.kuota_bimbingan>d.terisi');
$judulList = fetchAll('SELECT j.id_judul,j.judul_indonesia,m.nim FROM judul_skripsi j JOIN mahasiswa m ON m.id_mahasiswa=j.id_mahasiswa WHERE j.status="disetujui"');

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">
  <?php $flash = getFlash(); if ($flash): ?><div class="alert <?= $flash['type'] ?>"><?= e($flash['msg']) ?></div><?php endif; ?>
  <div class="page-header"><h1>Relasi Pembimbingan</h1><p>Atur relasi antara mahasiswa dan dosen pembimbing</p></div>

  <div style="display:grid;grid-template-columns:1fr 340px;gap:20px;margin-bottom:20px;">
    <!-- Form Buat Relasi -->
    <div class="form-card" style="height:fit-content;">
      <h2>Buat Relasi Baru</h2>
      <p>Hubungkan mahasiswa dengan dosen pembimbing</p>
      <form method="POST">
        <input type="hidden" name="aksi" value="buat">
        <div class="form-group">
          <label class="form-label">Mahasiswa (tanpa bimbingan aktif)</label>
          <select class="form-input" name="id_mahasiswa" required>
            <option value="">Pilih mahasiswa...</option>
            <?php foreach ($mhsTanpaBimb as $m): ?><option value="<?= $m['id_mahasiswa'] ?>"><?= e($m['nama_lengkap']) ?> (<?= $m['nim'] ?>)</option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Dosen Pembimbing (ada kuota)</label>
          <select class="form-input" name="id_dosen" required>
            <option value="">Pilih dosen...</option>
            <?php foreach ($dosenList as $d): ?><option value="<?= $d['id_dosen'] ?>"><?= e($d['nama_lengkap']) ?> (Sisa: <?= $d['sisa'] ?>)</option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Judul Skripsi (sudah disetujui)</label>
          <select class="form-input" name="id_judul" required>
            <option value="">Pilih judul...</option>
            <?php foreach ($judulList as $j): ?><option value="<?= $j['id_judul'] ?>"><?= e(mb_strimwidth($j['judul_indonesia'],0,60,'...')) ?> [<?= $j['nim'] ?>]</option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Semester</label>
          <input class="form-input" name="semester" value="Genap <?= date('Y') ?>/<?= date('Y')+1 ?>">
        </div>
        <button type="submit" class="btn btn-gold btn-full">🔗 Buat Relasi</button>
      </form>
    </div>

    <!-- Stat Box -->
    <div>
      <div class="card">
        <div class="card-header"><div class="card-title">Ringkasan</div></div>
        <div class="card-body">
          <?php foreach ([
            ['Aktif',  countRows('SELECT COUNT(*) FROM bimbingan WHERE status="aktif"'),    'success'],
            ['Selesai',countRows('SELECT COUNT(*) FROM bimbingan WHERE status="selesai"'), 'info'],
            ['Dihentikan',countRows('SELECT COUNT(*) FROM bimbingan WHERE status="dihentikan"'),'danger'],
          ] as [$l,$n,$b]): ?>
            <div style="display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--cream-dark);">
              <span class="badge <?= $b ?>"><span class="badge-dot"></span><?= $l ?></span>
              <strong style="font-family:'Playfair Display',serif;font-size:20px;"><?= $n ?></strong>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Tabel Relasi -->
  <div class="card">
    <div class="card-header"><div class="card-title">Semua Relasi</div></div>
    <div class="card-body">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Mahasiswa</th><th>Dosen</th><th>Judul</th><th>Semester</th><th>Mulai</th><th>Sesi</th><th>Status</th><th>Aksi</th></tr></thead>
          <tbody>
            <?php foreach ($relasi as $r): ?>
              <tr>
                <td style="font-weight:500;"><?= e($r['nama_mhs']) ?> <span class="mono" style="font-size:11px;color:var(--slate);"><?= $r['nim'] ?></span></td>
                <td><?= e($r['nama_dosen']) ?></td>
                <td style="max-width:200px;font-size:13px;"><?= e(mb_strimwidth($r['judul_indonesia'],0,50,'...')) ?></td>
                <td style="font-size:12px;"><?= e($r['semester']) ?></td>
                <td style="font-size:12px;"><?= date('d M Y', strtotime($r['tgl_mulai'])) ?></td>
                <td style="text-align:center;"><?= $r['total_sesi'] ?></td>
                <td><span class="badge <?= badge($r['status']) ?>"><span class="badge-dot"></span><?= $r['status'] ?></span></td>
                <td>
                  <?php if ($r['status'] === 'aktif'): ?>
                    <form method="POST" onsubmit="return confirm('Hentikan relasi ini?')">
                      <input type="hidden" name="aksi" value="hentikan">
                      <input type="hidden" name="id_bimbingan" value="<?= $r['id_bimbingan'] ?>">
                      <button class="btn btn-danger btn-sm">🛑 Hentikan</button>
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
<?php include __DIR__ . '/../includes/footer.php'; ?>
