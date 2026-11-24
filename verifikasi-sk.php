<?php
// verifikasi-sk.php — Halaman publik untuk memeriksa keaslian SK Bimbingan
// lewat kode verifikasi yang tercetak di file SK. Tidak perlu login.
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/sk.php';

$kode  = strtoupper(trim((string)($_GET['kode'] ?? '')));
$sk    = null;
$error = '';

if ($kode !== '') {
    // Batasi percobaan per sesi browser (30 kali per jam) agar kode tidak ditebak massal
    $_SESSION['verif_sk'] = array_filter($_SESSION['verif_sk'] ?? [], fn($t) => $t > time() - 3600);
    if (!skSiap()) {
        $error = 'Fitur verifikasi SK belum aktif.';
    } elseif (count($_SESSION['verif_sk']) >= 30) {
        $error = 'Terlalu banyak percobaan. Coba lagi dalam satu jam.';
    } elseif (!preg_match('/^SK-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}$/', $kode)) {
        $error = 'Format kode tidak dikenali. Contoh: SK-1A2B-3C4D-5E6F.';
    } else {
        $_SESSION['verif_sk'][] = time();
        $row = fetchOne('SELECT id_bimbingan FROM bimbingan WHERE kode_verifikasi = ?', [$kode]);
        $sk  = $row ? dataSk((int)$row['id_bimbingan']) : null;
        if (!$sk) $error = 'Kode verifikasi tidak terdaftar. Dokumen SK ini tidak dapat dipastikan keasliannya.';
    }
}

/** Samarkan NIM: tampilkan 4 digit awal dan 3 digit akhir. */
function samarkanNim(string $nim): string {
    return mb_strlen($nim) <= 7 ? $nim : mb_substr($nim, 0, 4) . str_repeat('•', mb_strlen($nim) - 7) . mb_substr($nim, -3);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verifikasi SK Bimbingan — SkripsiKu</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=<?= @filemtime(__DIR__ . '/assets/style.css') ?>">
</head>
<body>
<div class="halaman-tunggal">
  <div class="login-card">
    <a href="index.php" class="link fs-13">&larr; Ke halaman login</a>
    <h2 style="margin-top:12px;">Verifikasi SK Bimbingan</h2>
    <p>Masukkan kode verifikasi yang tercetak di bagian bawah SK.</p>

    <form method="GET" style="margin-top:12px;">
      <div class="form-group">
        <label for="v_kode" class="form-label">Kode verifikasi</label>
        <input id="v_kode" class="form-input mono" name="kode" required placeholder="SK-XXXX-XXXX-XXXX" value="<?= e($kode) ?>" autocomplete="off">
      </div>
      <button type="submit" class="btn btn-primary btn-full login-submit">Periksa</button>
    </form>

    <?php if ($error): ?>
      <div class="alert alert-static danger" style="margin-top:16px;"><?= e($error) ?></div>
    <?php elseif ($sk): ?>
      <div class="status-card status-card--blok status-card--success" style="margin-top:16px;">
        <div class="status-card__title">✔ SK terdaftar dan sah</div>
        <div class="detail-row"><span class="muted">Nomor SK</span><strong class="mono"><?= e($sk['no_sk']) ?></strong></div>
        <div class="detail-row"><span class="muted">Tanggal ditetapkan</span><span><?= tgl($sk['tgl_sk'], 'panjang') ?></span></div>
        <div class="detail-row"><span class="muted">Mahasiswa</span><span><?= e($sk['nama_mhs']) ?> (<?= e(samarkanNim((string)$sk['nim'])) ?>)</span></div>
        <div class="detail-row"><span class="muted">Dosen pembimbing</span><span><?= e($sk['nama_dosen']) ?></span></div>
        <div class="detail-row"><span class="muted">Status bimbingan</span><span><?= e(label($sk['status'])) ?></span></div>
      </div>
      <p class="fs-12 text-slate" style="margin-top:12px;line-height:1.6;">Pastikan nomor SK, nama, dan dosen di atas sama dengan yang tercetak di dokumen yang Anda terima.</p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
