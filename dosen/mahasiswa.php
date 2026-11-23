<?php
// dosen/mahasiswa.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('dosen');

$pageTitle  = 'Mahasiswa Bimbingan';
$activePage = 'mahasiswa_dosen';
$eid        = entityId();
$q          = trim($_GET['q'] ?? '');

$sql = 'SELECT m.nim,um.nama_lengkap,ps.nama_prodi,j.judul_indonesia,
               b.total_sesi,b.tgl_mulai,b.status AS status_bimbingan,m.status_skripsi,
               b.id_bimbingan,m.id_mahasiswa
        FROM bimbingan b
        JOIN mahasiswa m ON m.id_mahasiswa=b.id_mahasiswa
        JOIN user um ON um.id_user=m.id_user
        JOIN program_studi ps ON ps.id_prodi=m.id_prodi
        JOIN judul_skripsi j ON j.id_judul=b.id_judul
        WHERE b.id_dosen=?';
$params = [$eid];
if ($q) { $sql .= ' AND (um.nama_lengkap LIKE ? OR m.nim LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
$sql .= ' ORDER BY b.status, um.nama_lengkap';
$list = fetchAll($sql, $params);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">
  <div class="page-header"><h1>Mahasiswa Bimbingan</h1><p>Daftar seluruh mahasiswa yang Anda bimbing</p></div>

  <div class="search-bar">
    <form method="GET" style="display:contents;">
      <div class="search-input-wrap">
        <span class="search-icon">🔍</span>
        <input name="q" aria-label="Cari mahasiswa" placeholder="Cari nama atau NIM..." value="<?= e($q) ?>">
      </div>
      <button type="submit" class="btn btn-secondary">Cari</button>
    </form>
  </div>

  <div class="card">
    <div class="card-body">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Mahasiswa</th><th>NIM</th><th>Prodi</th><th>Judul</th><th>Sesi</th><th>Status</th><th>Aksi</th></tr></thead>
          <tbody>
            <?php if (empty($list)): ?>
              <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--slate);">Tidak ada data.</td></tr>
            <?php else: ?>
              <?php foreach ($list as $m): ?>
                <tr>
                  <td>
                    <div class="row-10">
                      <div class="mini-avatar" style="width:36px;height:36px;font-size:12px;"><?= e(inisial($m['nama_lengkap'])) ?></div>
                      <div class="fw-500"><?= e($m['nama_lengkap']) ?></div>
                    </div>
                  </td>
                  <td class="mono fs-12"><?= e($m['nim']) ?></td>
                  <td class="fs-13"><?= e($m['nama_prodi']) ?></td>
                  <td style="max-width:220px;font-size:13px;"><?= e(mb_strimwidth($m['judul_indonesia'],0,60,'...')) ?></td>
                  <td class="text-center"><?= $m['total_sesi'] ?></td>
                  <td><span class="badge <?= badge($m['status_skripsi']) ?>"><span class="badge-dot"></span><?= e(label($m['status_skripsi'])) ?></span></td>
                  <td>
                    <div style="display:flex;gap:6px;">
                      <a href="<?= BASE_URL ?>/dosen/catatan.php?id_bimbingan=<?= $m['id_bimbingan'] ?>" class="btn-icon" title="Catatan bimbingan" aria-label="Catatan bimbingan <?= e($m['nama_lengkap']) ?>"><?= icon('clipboard') ?></a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
