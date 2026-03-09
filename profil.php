<?php
// profil.php — shared
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
requireLogin();

$pageTitle  = 'Profil Saya';
$activePage = 'profil';
$uid        = userId();
$eid        = entityId();
$role       = role();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama  = trim($_POST['nama_lengkap'] ?? '');
    $noTlp = trim($_POST['no_telepon'] ?? '');
    $newPw = $_POST['new_password'] ?? '';

    execute('UPDATE user SET nama_lengkap=? WHERE id_user=?', [$nama, $uid]);
    if ($role === 'mahasiswa') {
        execute('UPDATE mahasiswa SET no_telepon=? WHERE id_mahasiswa=?', [$noTlp, $eid]);
    }
    if ($newPw) {
        execute('UPDATE user SET password_hash=? WHERE id_user=?', [hash('sha256',$newPw), $uid]);
    }

    // Refresh session name
    $_SESSION['user']['nama_lengkap'] = $nama;
    setFlash('success', 'Profil berhasil diperbarui!');
    header('Location: ' . BASE_URL . '/profil.php');
    exit;
}

$userDB = fetchOne('SELECT * FROM user WHERE id_user=?', [$uid]);
$extra  = [];
if ($role === 'mahasiswa') {
    $extra = fetchOne('SELECT m.*,ps.nama_prodi FROM mahasiswa m JOIN program_studi ps ON ps.id_prodi=m.id_prodi WHERE m.id_mahasiswa=?', [$eid]) ?? [];
} elseif ($role === 'dosen') {
    $extra = fetchOne('SELECT d.*,ps.nama_prodi FROM dosen d JOIN program_studi ps ON ps.id_prodi=d.id_prodi WHERE d.id_dosen=?', [$eid]) ?? [];
}

$initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_filter(explode(' ', $userDB['nama_lengkap']))));
$initials = substr($initials, 0, 2);

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<div class="content">
  <?php $flash = getFlash(); if ($flash): ?><div class="alert <?= $flash['type'] ?>"><?= e($flash['msg']) ?></div><?php endif; ?>
  <div class="page-header"><h1>Profil Saya</h1><p>Kelola informasi akun Anda</p></div>

  <div style="display:grid;grid-template-columns:280px 1fr;gap:20px;">
    <!-- Avatar Card -->
    <div class="card" style="text-align:center;padding:32px 24px;height:fit-content;">
      <div style="width:80px;height:80px;border-radius:50%;background:var(--gold);display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;color:var(--navy);margin:0 auto 16px;"><?= e($initials) ?></div>
      <div style="font-family:'Playfair Display',serif;font-size:18px;font-weight:600;margin-bottom:4px;"><?= e($userDB['nama_lengkap']) ?></div>
      <div style="font-size:13px;color:var(--slate);margin-bottom:12px;"><?= e($userDB['email']) ?></div>
      <span class="badge info"><span class="badge-dot"></span><?= e($role) ?></span>

      <?php if (!empty($extra['nama_prodi'])): ?>
        <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--cream-dark);font-size:13px;color:var(--slate);"><?= e($extra['nama_prodi']) ?></div>
      <?php endif; ?>

      <?php if (!empty($extra['nim'])): ?>
        <div style="margin-top:8px;font-family:'DM Mono',monospace;font-size:13px;">NIM: <?= e($extra['nim']) ?></div>
      <?php elseif (!empty($extra['nip'])): ?>
        <div style="margin-top:8px;font-family:'DM Mono',monospace;font-size:13px;">NIP: <?= e($extra['nip']) ?></div>
      <?php endif; ?>

      <div style="margin-top:8px;font-size:12px;color:var(--slate);">
        Login terakhir: <?= $userDB['last_login'] ? date('d M Y H:i', strtotime($userDB['last_login'])) : '—' ?>
      </div>
    </div>

    <!-- Form -->
    <div class="form-card">
      <h2>Edit Informasi</h2>
      <p>Perbarui data profil Anda</p>
      <form method="POST">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Nama Lengkap</label>
            <input class="form-input" name="nama_lengkap" value="<?= e($userDB['nama_lengkap']) ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input class="form-input" value="<?= e($userDB['email']) ?>" readonly style="background:#f5f5f5;">
          </div>
        </div>
        <?php if ($role === 'mahasiswa'): ?>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">No. Telepon</label>
            <input class="form-input" name="no_telepon" value="<?= e($extra['no_telepon'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">NIM</label>
            <input class="form-input" value="<?= e($extra['nim'] ?? '') ?>" readonly style="background:#f5f5f5;">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Program Studi</label>
            <input class="form-input" value="<?= e($extra['nama_prodi'] ?? '') ?>" readonly style="background:#f5f5f5;">
          </div>
          <div class="form-group">
            <label class="form-label">Angkatan</label>
            <input class="form-input" value="<?= e($extra['angkatan'] ?? '') ?>" readonly style="background:#f5f5f5;">
          </div>
        </div>
        <?php endif; ?>

        <div style="border-top:1px solid var(--cream-dark);padding-top:20px;margin-top:8px;">
          <div style="font-size:14px;font-weight:600;color:var(--navy);margin-bottom:14px;">Ganti Password</div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Password Baru (kosongkan jika tidak diganti)</label>
              <input class="form-input" type="password" name="new_password" placeholder="••••••••">
            </div>
            <div class="form-group">
              <label class="form-label">Konfirmasi Password Baru</label>
              <input class="form-input" type="password" placeholder="••••••••">
            </div>
          </div>
        </div>

        <div class="form-actions">
          <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-secondary">Batal</a>
          <button type="submit" class="btn btn-gold">💾 Simpan Perubahan</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
