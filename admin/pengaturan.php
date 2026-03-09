<?php
// admin/pengaturan.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('admin');

$pageTitle  = 'Pengaturan';
$activePage = 'pengaturan';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    setFlash('success', 'Pengaturan berhasil disimpan.');
    header('Location: ' . BASE_URL . '/admin/pengaturan.php'); exit;
}

$prodi = fetchAll('SELECT * FROM program_studi ORDER BY nama_prodi');

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">
  <?php $flash = getFlash(); if ($flash): ?><div class="alert <?= $flash['type'] ?>"><?= e($flash['msg']) ?></div><?php endif; ?>
  <div class="page-header"><h1>Pengaturan Sistem</h1><p>Konfigurasi portal bimbingan skripsi</p></div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
    <!-- Umum -->
    <div class="form-card">
      <h2>Konfigurasi Umum</h2>
      <p>Setting dasar portal</p>
      <form method="POST">
        <div class="form-group"><label class="form-label">Nama Institusi</label><input class="form-input" value="Universitas Contoh" name="institusi"></div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Tahun Akademik</label><input class="form-input" value="2024/2025" name="tahun_akademik"></div>
          <div class="form-group"><label class="form-label">Semester Aktif</label><select class="form-input" name="semester"><option>Genap</option><option>Ganjil</option></select></div>
        </div>
        <div class="form-group"><label class="form-label">Default Kuota Bimbingan</label><input class="form-input" type="number" value="10" name="default_kuota"></div>
        <button type="submit" class="btn btn-gold">💾 Simpan</button>
      </form>
    </div>

    <!-- Program Studi -->
    <div class="card">
      <div class="card-header">
        <div class="card-title">Program Studi</div>
      </div>
      <div class="card-body">
        <div class="table-wrap">
          <table>
            <thead><tr><th>Kode</th><th>Nama</th><th>Jenjang</th><th>Akreditasi</th></tr></thead>
            <tbody>
              <?php foreach ($prodi as $p): ?>
                <tr>
                  <td class="mono" style="font-size:12px;"><?= e($p['kode_prodi']) ?></td>
                  <td style="font-size:13px;"><?= e($p['nama_prodi']) ?></td>
                  <td><span class="badge info"><?= e($p['jenjang']) ?></span></td>
                  <td style="text-align:center;font-weight:700;"><?= e($p['akreditasi'] ?? '—') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
    <!-- Keamanan -->
    <div class="form-card">
      <h2>Keamanan</h2>
      <p>Konfigurasi keamanan sistem</p>
      <div class="form-group"><label class="form-label">Timeout Sesi (menit)</label><input class="form-input" type="number" value="120"></div>
      <div class="form-group">
        <label class="form-label">Panjang Minimum Password</label>
        <input class="form-input" type="number" value="6">
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--cream-dark);">
        <span style="font-size:14px;">Log Audit Aktif</span>
        <span class="badge success"><span class="badge-dot"></span>Aktif</span>
      </div>
      <button class="btn btn-gold" style="margin-top:20px;">💾 Simpan</button>
    </div>

    <!-- Info sistem -->
    <div class="card">
      <div class="card-header"><div class="card-title">Info Sistem</div></div>
      <div class="card-body">
        <?php foreach ([
          ['Versi Aplikasi', APP_VERSION],
          ['PHP Version', PHP_VERSION],
          ['Database', DB_NAME],
          ['Waktu Server', date('d M Y H:i:s')],
        ] as [$k,$v]): ?>
          <div style="display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--cream-dark);">
            <span style="font-size:13px;color:var(--slate);"><?= $k ?></span>
            <span class="mono" style="font-size:12px;"><?= e($v) ?></span>
          </div>
        <?php endforeach; ?>
        <div style="margin-top:20px;">
          <a href="<?= BASE_URL ?>/skripsi_portal.sql" class="btn btn-secondary btn-sm">📥 Export SQL Schema</a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
