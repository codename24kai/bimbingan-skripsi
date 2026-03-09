<?php
// index.php — Login page
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? '';

    $validRoles = ['mahasiswa','dosen','admin'];
    if (!in_array($role, $validRoles, true)) {
        $error = 'Role tidak valid.';
    } else {
        $user = fetchOne(
            'SELECT * FROM user WHERE email = ? AND role = ? AND status = "aktif"',
            [$email, $role]
        );

        if ($user && hash_equals($user['password_hash'], hash('sha256', $password))) {
            // Load entity-specific data
            $entityId = 0;
            if ($role === 'mahasiswa') {
                $row = fetchOne('SELECT id_mahasiswa FROM mahasiswa WHERE id_user=?', [$user['id_user']]);
                $entityId = $row['id_mahasiswa'] ?? 0;
            } elseif ($role === 'dosen') {
                $row = fetchOne('SELECT id_dosen FROM dosen WHERE id_user=?', [$user['id_user']]);
                $entityId = $row['id_dosen'] ?? 0;
            } elseif ($role === 'admin') {
                $row = fetchOne('SELECT id_admin FROM admin WHERE id_user=?', [$user['id_user']]);
                $entityId = $row['id_admin'] ?? 0;
            }

            // Update last_login
            execute('UPDATE user SET last_login=NOW() WHERE id_user=?', [$user['id_user']]);

            // Set session
            $_SESSION['id_user']   = $user['id_user'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['entity_id'] = $entityId;
            $_SESSION['user']      = [
                'id_user'      => $user['id_user'],
                'nama_lengkap' => $user['nama_lengkap'],
                'email'        => $user['email'],
                'foto_profil'  => $user['foto_profil'],
            ];

            header('Location: ' . BASE_URL . '/dashboard.php');
            exit;
        } else {
            $error = 'Email, password, atau role tidak sesuai.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — SkripsiKu</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="login-wrap">
  <!-- LEFT PANEL -->
  <div class="login-left">
    <div class="login-logo">
      <div class="login-logo-icon">🎓</div>
      <div class="login-logo-text">Skripsi<span>Ku</span></div>
    </div>
    <div class="login-tagline">
      Portal Bimbingan<br>Skripsi yang <em>Terstruktur</em><br>&amp; Transparan
    </div>
    <p class="login-sub">
      Platform manajemen tugas akhir terpadu untuk mahasiswa, dosen pembimbing, dan administrator akademik.
    </p>
    <div class="feature-pills">
      <div class="pill"><span class="pill-dot"></span>Pengajuan Judul</div>
      <div class="pill"><span class="pill-dot"></span>Riwayat Bimbingan</div>
      <div class="pill"><span class="pill-dot"></span>Unggah Dokumen</div>
      <div class="pill"><span class="pill-dot"></span>Validasi Dosen</div>
      <div class="pill"><span class="pill-dot"></span>Monitor Progres</div>
    </div>
  </div>

  <!-- RIGHT PANEL -->
  <div class="login-right">
    <div class="login-card">
      <h2>Selamat Datang 👋</h2>
      <p>Masuk ke portal bimbingan skripsi Anda</p>

      <?php if ($error): ?>
        <div class="alert danger">⚠️ <?= e($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="">
        <div class="form-group">
          <label class="form-label">Masuk Sebagai</label>
          <div class="role-selector">
            <?php foreach (['mahasiswa'=>['🎒','Mahasiswa'],'dosen'=>['👨‍🏫','Dosen'],'admin'=>['⚙️','Admin']] as $r=>[$icon,$lbl]): ?>
              <label class="role-btn <?= ($_POST['role']??'mahasiswa')===$r?'active':'' ?>" style="cursor:pointer;">
                <input type="radio" name="role" value="<?= $r ?>" style="display:none;"
                  <?= ($_POST['role']??'mahasiswa')===$r?'checked':'' ?>>
                <span class="role-icon"><?= $icon ?></span><?= $lbl ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Email</label>
          <input class="form-input" type="email" name="email" required
            placeholder="email@contoh.ac.id"
            value="<?= e($_POST['email'] ?? '') ?>">
        </div>

        <div class="form-group">
          <label class="form-label">Kata Sandi</label>
          <input class="form-input" type="password" name="password" required placeholder="••••••••">
        </div>

        <button type="submit" class="btn btn-primary btn-full" style="margin-top:4px;">
          Masuk ke Portal
        </button>
      </form>

      <p style="text-align:center;margin-top:20px;font-size:12px;color:var(--slate);">
        Demo: <code>ahmad.fauzi@mhs.univ.ac.id</code> / <code>mhs123</code>
      </p>
    </div>
  </div>
</div>

<script>
// Role button radio sync
document.querySelectorAll('.role-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.role-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
  });
});
</script>
</body>
</html>
