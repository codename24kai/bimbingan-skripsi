<?php
// Letakkan blok PHP ini di Paling Atas file includes/sidebar.php
$isBimbinganAktif = false;

// Cek hanya untuk role mahasiswa
if (isset($_SESSION['role']) && $_SESSION['role'] === 'mahasiswa') {
    // Mencari apakah mahasiswa punya bimbingan yang aktif (SK Terbit)
    $cekBimb = fetchOne("SELECT id_bimbingan FROM bimbingan WHERE id_mahasiswa=? AND status='aktif'", [$_SESSION['entity_id']]);
    if ($cekBimb) {
        $isBimbinganAktif = true;
    }
}

// includes/sidebar.php
require_once __DIR__ . '/../config/auth.php';

$user   = currentUser();
$role   = role();
$initials = implode('', array_map(fn($w) => strtoupper($w[0]),
              array_filter(explode(' ', $user['nama_lengkap'] ?? 'U'))));
$initials = substr($initials, 0, 2);

$roleLabel = ['mahasiswa'=>'Mahasiswa','dosen'=>'Dosen','admin'=>'Administrator'][$role] ?? $role;

// Count unread notif for badge
$unread = countRows('SELECT COUNT(*) FROM notifikasi WHERE id_user=? AND is_read=0', [userId()]);

// Nav per role
$navs = [
  'mahasiswa' => [
    ['section'=>'Menu Utama','items'=>[
      ['href'=>'dashboard.php',         'icon'=>'📊','label'=>'Dashboard',         'id'=>'dashboard'],
      ['href'=>'mahasiswa/pengajuan.php','icon'=>'📝','label'=>'Pengajuan Judul',   'id'=>'pengajuan'],
      ['href'=>'mahasiswa/dokumen.php',  'icon'=>'📁','label'=>'Dokumen & Revisi',  'id'=>'dokumen'],
      ['href'=>'mahasiswa/bimbingan.php','icon'=>'🗓️','label'=>'Jadwal Bimbingan',  'id'=>'bimbingan'],
      ['href'=>'mahasiswa/riwayat.php',  'icon'=>'📋','label'=>'Riwayat Bimbingan', 'id'=>'riwayat'],
    ]],
    ['section'=>'Akun','items'=>[
      ['href'=>'profil.php','icon'=>'👤','label'=>'Profil Saya','id'=>'profil'],
    ]],
  ],
  'dosen' => [
    ['section'=>'Menu Utama','items'=>[
      ['href'=>'dashboard.php',          'icon'=>'📊','label'=>'Dashboard',              'id'=>'dashboard'],
      ['href'=>'dosen/validasi.php',     'icon'=>'✅','label'=>'Validasi Judul',         'id'=>'validasi'],
      ['href'=>'dosen/mahasiswa.php',    'icon'=>'🎒','label'=>'Mahasiswa Bimbingan',    'id'=>'mahasiswa_dosen'],
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
      ['href'=>'admin/mahasiswa.php',       'icon'=>'🎒','label'=>'Kelola Mahasiswa',     'id'=>'kelola_mahasiswa'],
      ['href'=>'admin/dosen.php',           'icon'=>'👨‍🏫','label'=>'Kelola Dosen',         'id'=>'kelola_dosen'],
      ['href'=>'admin/relasi.php',          'icon'=>'🔗','label'=>'Relasi Pembimbingan',  'id'=>'relasi'],
      ['href'=>'admin/laporan.php',         'icon'=>'📈','label'=>'Laporan & Aktivitas',  'id'=>'laporan'],
      ['href'=>'admin/validasi_judul.php',  'icon'=>'✅','label'=>'Validasi Judul',       'id'=>'validasi_judul'],
    ]],
    ['section'=>'Sistem','items'=>[
      ['href'=>'admin/pengaturan.php','icon'=>'⚙️','label'=>'Pengaturan','id'=>'pengaturan'],
    ]],
  ],
];
?>

<aside class="sidebar">
    <div class="sidebar-logo">SkripsiKu</div>
    <ul class="sidebar-menu">
        
        <li><a href="<?= BASE_URL ?>/dashboard.php">🏠 Dashboard</a></li>

        <?php if ($_SESSION['role'] === 'mahasiswa'): ?>
            
            <li><a href="<?= BASE_URL ?>/mahasiswa/pengajuan.php">📝 Pengajuan Judul</a></li>
            
            <?php if ($isBimbinganAktif): ?>
                <li><a href="<?= BASE_URL ?>/mahasiswa/dokumen.php">📁 Dokumen & Revisi</a></li>
                <li><a href="<?= BASE_URL ?>/mahasiswa/jadwal.php">📅 Jadwal Bimbingan</a></li>
                <li><a href="<?= BASE_URL ?>/mahasiswa/riwayat.php">⏳ Riwayat Bimbingan</a></li>
            <?php else: ?>
                <li>
                    <a href="#" onclick="alert('🔒 Akses Terkunci!\n\nMenu ini baru akan terbuka setelah judul Anda disetujui dan Program Studi menerbitkan SK Bimbingan.')" style="opacity:0.5; cursor:not-allowed; background:#f8f9fa;">
                        🔒 Dokumen & Revisi
                    </a>
                </li>
                <li>
                    <a href="#" onclick="alert('🔒 Akses Terkunci!\n\nMenu ini baru akan terbuka setelah judul Anda disetujui dan Program Studi menerbitkan SK Bimbingan.')" style="opacity:0.5; cursor:not-allowed; background:#f8f9fa;">
                        🔒 Jadwal Bimbingan
                    </a>
                </li>
                <li>
                    <a href="#" onclick="alert('🔒 Akses Terkunci!\n\nMenu ini baru akan terbuka setelah judul Anda disetujui dan Program Studi menerbitkan SK Bimbingan.')" style="opacity:0.5; cursor:not-allowed; background:#f8f9fa;">
                        🔒 Riwayat Bimbingan
                    </a>
                </li>
            <?php endif; ?>

        <?php endif; ?>
        
        </ul>
</aside>

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
      ?>
        <a href="<?= BASE_URL ?>/<?= $item['href'] ?>"
           class="nav-item <?= $isActive ? 'active' : '' ?>">
          <span class="nav-icon"><?= $item['icon'] ?></span>
          <?= e($item['label']) ?>
          <?php if ($item['id'] === 'riwayat' && $unread > 0): ?>
            <span class="nav-badge"><?= $unread ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-footer">
    <a href="<?= BASE_URL ?>/logout.php" class="logout-btn">
      <span>🚪</span> Keluar
    </a>
  </div>
</aside>
