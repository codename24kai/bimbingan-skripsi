<?php
// login-staff.php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

// Jika sudah login, langsung lempar ke dashboard utama
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? '';

    $validRoles = ['dosen', 'admin']; 

    // 1. Validasi Input yang Lebih Spesifik agar mudah di-debug
    if (empty($role)) {
        $error = 'Silakan pilih Hak Akses (Admin atau Dosen) terlebih dahulu.';
    } elseif (empty($email)) {
        $error = 'Email staf wajib diisi.';
    } elseif (empty($password)) {
        $error = 'Kata sandi wajib diisi.';
    } elseif (!in_array($role, $validRoles, true)) {
        $error = 'Akses ditolak. Peran tidak valid untuk portal ini.';
    } else {
        // 2. Cek user di database berdasarkan email, role, dan status aktif
        $user = fetchOne(
            'SELECT * FROM user WHERE email = ? AND role = ? AND status = "aktif"',
            [$email, $role]
        );

        // 3. Pesan disamakan agar email terdaftar tidak bisa ditebak
        if (!$user || !verifyPassword($password, $user)) {
            $error = 'Email, kata sandi, atau hak akses tidak sesuai.';
        } else {
            // BERHASIL - Load entity-specific data (Hanya untuk dosen dan admin)
            $entityId = 0;
            if ($role === 'dosen') {
                $row = fetchOne('SELECT id_dosen FROM dosen WHERE id_user=?', [$user['id_user']]);
                $entityId = (int)($row['id_dosen'] ?? 0);
            } elseif ($role === 'admin') {
                $row = fetchOne('SELECT id_admin FROM admin WHERE id_user=?', [$user['id_user']]);
                $entityId = (int)($row['id_admin'] ?? 0);
            }
            // Update last_login
            execute('UPDATE user SET last_login=NOW() WHERE id_user=?', [$user['id_user']]);
            // Set session (regenerate ID untuk mencegah session fixation)
            loginUser($user, $entityId, $password);
            // Redirect ke dashboard
            header('Location: ' . BASE_URL . '/dashboard.php');
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Staff & Dosen - Portal Bimbingan</title>
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
            
            <div class="login-tagline">Manajemen Bimbingan<br><em>Lebih Terstruktur</em></div>
            <p class="login-sub">Portal khusus untuk Administrator dan Dosen Pembimbing dalam mengelola, memantau, dan memvalidasi proses bimbingan skripsi mahasiswa secara sistematis.</p>
            
            <div class="feature-pills">
                <div class="pill"><div class="pill-dot"></div> Akses Khusus Staf</div>
                <div class="pill"><div class="pill-dot"></div> Validasi Berkas</div>
                <div class="pill"><div class="pill-dot"></div> Pemantauan Progres</div>
            </div>
        </div>

        <div class="login-right">
            <div class="login-card">
                <h2>Portal Staff & Dosen</h2>
                <p>Silakan masuk dengan akun staf atau dosen Anda.</p>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-static danger" style="margin-bottom: 20px;">
                        ⚠️ <?= htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form action="" method="POST"><?= csrfField() ?>
                    <div class="form-group">
                        <label for="role" class="form-label">Hak Akses</label>
                        <select id="role" name="role" class="form-input" required>
                            <option value="" disabled <?= empty($_POST['role']) ? 'selected' : '' ?>>-- Pilih Peran --</option>
                            <option value="admin" <?= (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'selected' : '' ?>>Administrator (Admin)</option>
                            <option value="dosen" <?= (isset($_POST['role']) && $_POST['role'] == 'dosen') ? 'selected' : '' ?>>Dosen Pembimbing</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">Email Staf / Dosen</label>
                        <input id="email" name="email" type="email" class="form-input" placeholder="nama@univ.ac.id" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
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

                    <button type="submit" class="btn btn-primary btn-full login-submit">Masuk ke Sistem</button>
                    
                    <div class="form-actions" style="flex-direction: column; text-align: center;">
                        <span style="font-size: 13px; color: var(--slate); margin-bottom: 8px;">Mahasiswa yang ingin bimbingan?</span>
                        <a href="index.php" class="btn btn-secondary btn-full" style="justify-content: center;">
                            Masuk sebagai Mahasiswa
                        </a>
                    </div>
                </form>
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
                const isPassword = passwordInput.getAttribute('type') === 'password';
                passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                
                if (isPassword) {
                    eyeClosed.style.display = 'none';
                    eyeOpen.style.display = 'block';
                    toggleBtn.style.color = 'var(--navy)';
                } else {
                    eyeClosed.style.display = 'block';
                    eyeOpen.style.display = 'none';
                    toggleBtn.style.color = 'var(--slate)';
                }
            });
        }
    });
    </script>
</body>
</html>