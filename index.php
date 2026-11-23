<?php
// index.php — Login page Khusus Mahasiswa
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
    
    // Karena ini halaman khusus mahasiswa, kita tetapkan rolenya secara mutlak
    $role = 'mahasiswa';

    if (empty($email) || empty($password)) {
        $error = 'Email dan kata sandi wajib diisi.';
    } else {
        // Query disesuaikan agar HANYA mencari user dengan role mahasiswa
        $user = fetchOne(
            'SELECT * FROM user WHERE email = ? AND role = ? AND status = "aktif"',
            [$email, $role]
        );

        if ($user && verifyPassword($password, $user)) {
            // Load entity-specific data HANYA untuk mahasiswa
            $row = fetchOne('SELECT id_mahasiswa FROM mahasiswa WHERE id_user=?', [$user['id_user']]);
            $entityId = (int)($row['id_mahasiswa'] ?? 0);

            // Update last_login
            execute('UPDATE user SET last_login=NOW() WHERE id_user=?', [$user['id_user']]);

            // Set session (regenerate ID untuk mencegah session fixation)
            loginUser($user, $entityId, $password);

            header('Location: ' . BASE_URL . '/dashboard.php');
            exit;
        } else {
            $error = 'Email atau kata sandi salah, atau akun Anda bukan mahasiswa.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Mahasiswa — Portal Skripsi</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=<?= @filemtime(__DIR__ . '/assets/style.css') ?>">
</head>
<body>

<div class="login-wrap">
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

  <div class="login-right">
    <div class="login-card">
      <h2>Selamat Datang 👋</h2>
      <p>Masuk ke portal khusus <b>Mahasiswa</b></p>

      <?php if ($error): ?>
        <div class="alert alert-static danger">⚠️ <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" action=""><?= csrfField() ?>
        <div class="form-group">
          <label for="f_email" class="form-label">Email Mahasiswa</label>
          <input id="f_email" class="form-input" type="email" name="email" required
            placeholder="nama@univ.ac.id"
            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>

        <div class="form-group">
            <div class="label-baris"><label for="password" class="form-label">Kata Sandi</label><a class="link fs-12" href="lupa-sandi.php">Lupa kata sandi?</a></div>

            <div style="position: relative; width: 100%;">

                <input id="password" name="password" type="password" class="form-input" placeholder="••••••••" required style="padding-right: 42px;">

                <div id="togglePasswordBtn" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--slate); display: flex; align-items: center; justify-content: center; height: 100%; z-index: 10;">

                    <svg id="eyeClosed" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>

                    <svg id="eyeOpen" style="display: none;" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>

                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-full login-submit">
          Masuk ke Portal
        </button>
      </form>
      
      <div class="form-actions" style="flex-direction: column; text-align: center;">
        <span style="font-size: 13px; color: var(--slate); margin-bottom: 8px;">Akses untuk staf akademik dan pembimbing?</span>

        <a href="login-staff.php" class="btn btn-secondary btn-full" style="justify-content: center;">
            Masuk sebagai Admin / Dosen
        </a>
        <a href="beranda.php" class="link fs-13" style="margin-top:10px;">Tentang SkripsiKu dan alur skripsi</a>
      </div>
    </div>
  </div>
</div>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const toggleBtn = document.getElementById('togglePasswordBtn');
        const passwordInput = document.getElementById('password');
        const eyeClosed = document.getElementById('eyeClosed');
        const eyeOpen = document.getElementById('eyeOpen');

        if (toggleBtn && passwordInput) {
            toggleBtn.addEventListener('click', function () {
                // Mengecek tipe saat ini
                const isPassword = passwordInput.getAttribute('type') === 'password';
                
                // Mengubah tipe input text/password
                passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                
                // Menukar tampilan Ikon SVG
                if (isPassword) {
                    eyeClosed.style.display = 'none';
                    eyeOpen.style.display = 'block';
                    toggleBtn.style.color = 'var(--navy)'; // Ubah warna saat aktif
                } else {
                    eyeClosed.style.display = 'block';
                    eyeOpen.style.display = 'none';
                    toggleBtn.style.color = 'var(--slate)'; // Kembali ke warna redup
                }
            });
        }
    });
</script>
</body>
</html>