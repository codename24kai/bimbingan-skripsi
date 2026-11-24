<?php
// lupa-sandi.php — Permintaan reset kata sandi ke admin (D3).
// Tidak memakai email: admin mereset ke kata sandi awal (uab# + 6 digit terakhir NIM/NIP).
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$terkirim = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $nomor = trim($_POST['nomor_induk'] ?? '');

    // Batasi percobaan per sesi browser (5 kali per jam)
    $_SESSION['reset_coba'] = array_filter($_SESSION['reset_coba'] ?? [], fn($t) => $t > time() - 3600);

    if (!tabelAda('permintaan_reset')) {
        $error = 'Fitur ini belum aktif. Hubungi bagian akademik secara langsung.';
    } elseif (count($_SESSION['reset_coba']) >= 5) {
        $error = 'Terlalu banyak percobaan. Coba lagi dalam satu jam, atau hubungi bagian akademik.';
    } elseif ($email === '' || $nomor === '') {
        $error = 'Email dan NIM/NIP wajib diisi.';
    } else {
        $_SESSION['reset_coba'][] = time();
        // Cocokkan email dengan NIM (mahasiswa) atau NIP (dosen)
        $user = fetchOne(
            'SELECT u.id_user, u.nama_lengkap, u.role FROM user u
             LEFT JOIN mahasiswa m ON m.id_user = u.id_user
             LEFT JOIN dosen d ON d.id_user = u.id_user
             WHERE u.email = ? AND u.status = "aktif" AND (m.nim = ? OR d.nip = ?)',
            [$email, $nomor, $nomor]
        );
        if ($user) {
            $sudahAda = countRows('SELECT COUNT(*) FROM permintaan_reset WHERE id_user=? AND status="menunggu"', [$user['id_user']]);
            if (!$sudahAda) {
                execute('INSERT INTO permintaan_reset (id_user) VALUES (?)', [$user['id_user']]);
                kirimNotifAdmin('Permintaan reset kata sandi',
                    $user['nama_lengkap'] . ' (' . label($user['role']) . ') lupa kata sandi dan meminta reset.',
                    'peringatan', 'akun', 'admin/reset_sandi.php');
            }
        }
        // Pesan sama untuk data cocok maupun tidak, agar email terdaftar tidak bisa ditebak
        $terkirim = true;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lupa Kata Sandi — SkripsiKu</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=<?= @filemtime(__DIR__ . '/assets/style.css') ?>">
</head>
<body>
<div class="halaman-tunggal">
  <div class="login-card">
    <a href="index.php" class="link fs-13">&larr; Kembali ke login</a>
    <h2 style="margin-top:12px;">Lupa Kata Sandi</h2>

    <?php if ($terkirim): ?>
      <div class="status-card status-card--blok status-card--success" style="margin-top:16px;">
        <div class="status-card__title">Permintaan terkirim</div>
        <p class="fs-14" style="line-height:1.6;">Jika email dan NIM/NIP cocok, admin akan mereset kata sandi Anda ke kata sandi awal (uab# + 6 digit terakhir NIM/NIP). Tunggu konfirmasi dari bagian akademik. Setelah login, Anda akan diminta membuat kata sandi baru.</p>
      </div>
      <?php $kontak = tabelAda('pengumuman') ? pengaturan('kontak_prodi') : ''; if ($kontak): ?>
        <p class="fs-13 text-slate" style="margin-top:14px;line-height:1.6;"><b>Bagian akademik:</b> <?= e($kontak) ?></p>
      <?php endif; ?>
    <?php else: ?>
      <p>Isi email dan NIM (mahasiswa) atau NIP (dosen). Permintaan akan dikirim ke admin.</p>
      <?= tampilFlash() ?>
      <?php if ($error): ?><div class="alert alert-static danger"><?= e($error) ?></div><?php endif; ?>
      <form method="POST"><?= csrfField() ?>
        <div class="form-group">
          <label for="r_email" class="form-label">Email akun</label>
          <input id="r_email" class="form-input" type="email" name="email" required placeholder="nama@univ.ac.id" value="<?= e($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="r_nomor" class="form-label">NIM atau NIP</label>
          <input id="r_nomor" class="form-input" name="nomor_induk" required inputmode="numeric" value="<?= e($_POST['nomor_induk'] ?? '') ?>">
        </div>
        <button type="submit" class="btn btn-primary btn-full login-submit">Kirim permintaan</button>
      </form>
      <p class="fs-12 text-slate" style="margin-top:14px;line-height:1.6;">Akun admin yang lupa kata sandi perlu direset langsung lewat phpMyAdmin oleh pengelola sistem.</p>
    <?php endif; ?>
  </div>
</div>
<script src="assets/script.js?v=<?= @filemtime(__DIR__ . '/assets/script.js') ?>"></script>
</body>
</html>
