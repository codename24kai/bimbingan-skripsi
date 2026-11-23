<?php
// includes/sidebar.php
require_once __DIR__ . '/../config/auth.php';

$user   = currentUser();
$role   = role();
$initials = implode('', array_map(fn($w) => strtoupper($w[0]),
              array_filter(explode(' ', $user['nama_lengkap'] ?? 'U'))));
$initials = substr($initials, 0, 2);

$roleLabel = ['mahasiswa'=>'Mahasiswa','dosen'=>'Dosen','admin'=>'Administrator'][$role] ?? $role;

// ==========================================
// 1. LOGIKA SCOPED ACCESS MAHASISWA
// ==========================================
$isBimbinganAktif = false;
$isJudulDisetujui = false; // <-- Penambahan untuk cek judul ACC

if ($role === 'mahasiswa') {
    // Cek Bimbingan
    $cekBimb = fetchOne("SELECT id_bimbingan FROM bimbingan WHERE id_mahasiswa=? AND status='aktif'", [$_SESSION['entity_id']]);
    if ($cekBimb) {
        $isBimbinganAktif = true;
    }
    
    // Cek Judul Disetujui (Untuk mengunci halaman usulan dosen)
    $cekJudul = fetchOne("SELECT id_judul FROM judul_skripsi WHERE id_mahasiswa=? AND status='disetujui'", [$_SESSION['entity_id']]);
    if ($cekJudul) {
        $isJudulDisetujui = true;
    }
}

// Nav per role
$navs = [
  'mahasiswa' => [
    ['section'=>'Menu Utama','items'=>[
      ['href'=>'dashboard.php',         'icon'=>'📊','label'=>'Dashboard',         'id'=>'dashboard', 'locked'=>false],
      ['href'=>'mahasiswa/pengajuan.php','icon'=>'📝','label'=>'Pengajuan Judul',   'id'=>'pengajuan', 'locked'=>false],
      
      // MENU BARU: Usulan Pembimbing (Terkunci jika judul belum di ACC)
      ['href'=>'mahasiswa/pengajuan_dosen.php','icon'=>'👨‍🏫','label'=>'Usulan Pembimbing','id'=>'usulan_dosen','locked'=>!$isJudulDisetujui, 'hint'=>'Terbuka setelah judul disetujui'],
      
      ['href'=>'mahasiswa/dokumen.php',  'icon'=>'📁','label'=>'Dokumen & Revisi',  'id'=>'dokumen',   'locked'=>!$isBimbinganAktif, 'hint'=>'Terbuka setelah SK bimbingan terbit'],
      ['href'=>'mahasiswa/bimbingan.php','icon'=>'🗓️','label'=>'Jadwal Bimbingan',  'id'=>'bimbingan', 'locked'=>!$isBimbinganAktif, 'hint'=>'Terbuka setelah SK bimbingan terbit'],
      ['href'=>'mahasiswa/riwayat.php',  'icon'=>'📋','label'=>'Riwayat Bimbingan', 'id'=>'riwayat',   'locked'=>!$isBimbinganAktif, 'hint'=>'Terbuka setelah SK bimbingan terbit'],
    ]],
    ['section'=>'Akun','items'=>[
      ['href'=>'profil.php','icon'=>'👤','label'=>'Profil Saya','id'=>'profil', 'locked'=>false],
    ]],
  ],
  'dosen' => [
    ['section'=>'Menu Utama','items'=>[
      ['href'=>'dashboard.php',          'icon'=>'📊','label'=>'Dashboard',            'id'=>'dashboard'],
      ['href'=>'dosen/validasi.php',     'icon'=>'✅','label'=>'Validasi Judul',         'id'=>'validasi'],
      ['href'=>'dosen/mahasiswa.php',    'icon'=>'🎒','label'=>'Mahasiswa Bimbingan',    'id'=>'mahasiswa_dosen'],
      ['href'=>'dosen/revisi.php',       'icon'=>'📑','label'=>'Review Dokumen',         'id'=>'revisi_dokumen'],
      ['href'=>'dosen/catatan.php',      'icon'=>'📝','label'=>'Catatan Bimbingan',      'id'=>'catatan'],
      ['href'=>'dosen/jadwal.php',       'icon'=>'🗓️','label'=>'Jadwal Konsultasi',     'id'=>'jadwal_dosen'],
    ]],
    ['section'=>'Akun','items'=>[
      ['href'=>'profil.php','icon'=>'👤','label'=>'Profil Saya','id'=>'profil'],
    ]],
  ],
  'admin' => [
    ['section'=>'Manajemen','items'=>[
      ['href'=>'dashboard.php',             'icon'=>'📊','label'=>'Dashboard',            'id'=>'dashboard'],
      ['href'=>'admin/validasi_judul.php',  'icon'=>'✅','label'=>'Validasi Judul',       'id'=>'validasi_judul'],
      ['href'=>'admin/relasi.php',          'icon'=>'🔗','label'=>'Relasi & SK',          'id'=>'relasi'],
      ['href'=>'admin/mahasiswa.php',       'icon'=>'🎒','label'=>'Kelola Mahasiswa',     'id'=>'kelola_mahasiswa'],
      ['href'=>'admin/dosen.php',           'icon'=>'👨‍🏫','label'=>'Kelola Dosen',         'id'=>'kelola_dosen'],
      ['href'=>'admin/laporan.php',         'icon'=>'📈','label'=>'Laporan & Aktivitas',  'id'=>'laporan'],
    ]],
    ['section'=>'Sistem','items'=>[
      ['href'=>'admin/pengaturan.php','icon'=>'⚙️','label'=>'Pengaturan','id'=>'pengaturan'],
    ]],
  ],
];
?>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo">Skripsi<span>Ku</span></div>
    <div class="sidebar-role-badge">● <?= e($roleLabel) ?></div>
  </div>

  <div class="sidebar-user">
    <div class="avatar"><?= e($initials) ?></div>
    <div style="overflow:hidden;">
      <div class="user-info-name"><?= e($user['nama_lengkap'] ?? '') ?></div>
      <div class="user-info-sub"><?= e($user['email'] ?? '') ?></div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <?php foreach ($navs[$role] ?? [] as $group): ?>
      <div class="nav-section-label"><?= e($group['section']) ?></div>
      <?php foreach ($group['items'] as $item):
        $isActive = ($activePage ?? '') === $item['id'];
        
        // Cek apakah item ini terkunci (scoped access)
        $isLocked = $item['locked'] ?? false;
        
      ?>
        <?php if ($isLocked): ?>
          <span class="nav-item nav-item--locked" aria-disabled="true" title="<?= e($item['hint'] ?? 'Belum tersedia') ?>">
            <span class="nav-icon" aria-hidden="true">🔒</span>
            <span style="min-width:0;">
              <?= e($item['label']) ?>
              <span class="nav-hint"><?= e($item['hint'] ?? 'Belum tersedia') ?></span>
            </span>
          </span>
        <?php else: ?>
          <a href="<?= BASE_URL . '/' . $item['href'] ?>" class="nav-item <?= $isActive ? 'active' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
            <span class="nav-icon" aria-hidden="true"><?= $item['icon'] ?></span>
            <?= e($item['label']) ?>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-footer">
    <a href="<?= BASE_URL ?>/logout.php" class="logout-btn">
      <span>🚪</span> Keluar
    </a>
  </div>
</aside>