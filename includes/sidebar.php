<?php
// includes/sidebar.php
require_once __DIR__ . '/../config/auth.php';

$user   = currentUser();
$role   = role();
$initials = inisial($user['nama_lengkap'] ?? '');

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

// Jumlah permintaan reset kata sandi yang menunggu (badge menu admin)
$jmlReset = 0;
if ($role === 'admin' && tabelAda('permintaan_reset')) {
    $jmlReset = countRows('SELECT COUNT(*) FROM permintaan_reset WHERE status="menunggu"');
}

// Nav per role
$navs = [
  'mahasiswa' => [
    ['section'=>'Menu Utama','items'=>[
      ['href'=>'dashboard.php',         'icon'=>'dashboard','label'=>'Dashboard',         'id'=>'dashboard', 'locked'=>false],
      ['href'=>'mahasiswa/pengajuan.php','icon'=>'file','label'=>'Pengajuan Judul',   'id'=>'pengajuan', 'locked'=>false],
      
      // MENU BARU: Usulan Pembimbing (Terkunci jika judul belum di ACC)
      ['href'=>'mahasiswa/pengajuan_dosen.php','icon'=>'user','label'=>'Usulan Pembimbing','id'=>'usulan_dosen','locked'=>!$isJudulDisetujui, 'hint'=>'Terbuka setelah judul disetujui'],
      
      ['href'=>'mahasiswa/dokumen.php',  'icon'=>'folder','label'=>'Dokumen & Revisi',  'id'=>'dokumen',   'locked'=>!$isBimbinganAktif, 'hint'=>'Terbuka setelah SK bimbingan terbit'],
      ['href'=>'mahasiswa/bimbingan.php','icon'=>'calendar','label'=>'Jadwal Bimbingan',  'id'=>'bimbingan', 'locked'=>!$isBimbinganAktif, 'hint'=>'Terbuka setelah SK bimbingan terbit'],
      ['href'=>'mahasiswa/riwayat.php',  'icon'=>'clipboard','label'=>'Riwayat Bimbingan', 'id'=>'riwayat',   'locked'=>!$isBimbinganAktif, 'hint'=>'Terbuka setelah SK bimbingan terbit'],
      ['href'=>'mahasiswa/ujian.php',    'icon'=>'award','label'=>'Seminar & Sidang', 'id'=>'ujian',   'locked'=>!$isBimbinganAktif, 'hint'=>'Terbuka setelah SK bimbingan terbit'],
    ]],
    ['section'=>'Informasi','items'=>[
      ['href'=>'info/pengumuman.php','icon'=>'megaphone','label'=>'Pengumuman','id'=>'pengumuman'],
      ['href'=>'kalender.php','icon'=>'calendar','label'=>'Kalender','id'=>'kalender'],
      ['href'=>'info/panduan.php','icon'=>'map','label'=>'Panduan','id'=>'panduan'],
      ['href'=>'info/template.php','icon'=>'download','label'=>'Template Dokumen','id'=>'template'],
      ['href'=>'info/faq.php','icon'=>'help','label'=>'FAQ & Bantuan','id'=>'faq'],
      ['href'=>'info/dosen.php','icon'=>'users','label'=>'Direktori Dosen','id'=>'direktori_dosen'],
    ]],
    ['section'=>'Akun','items'=>[
      ['href'=>'profil.php','icon'=>'user','label'=>'Profil Saya','id'=>'profil', 'locked'=>false],
    ]],
  ],
  'dosen' => [
    ['section'=>'Menu Utama','items'=>[
      ['href'=>'dashboard.php',          'icon'=>'dashboard','label'=>'Dashboard',            'id'=>'dashboard'],
      ['href'=>'dosen/validasi.php',     'icon'=>'check-circle','label'=>'Validasi Judul',         'id'=>'validasi'],
      ['href'=>'dosen/mahasiswa.php',    'icon'=>'users','label'=>'Mahasiswa Bimbingan',    'id'=>'mahasiswa_dosen'],
      ['href'=>'dosen/revisi.php',       'icon'=>'book','label'=>'Review Dokumen',         'id'=>'revisi_dokumen'],
      ['href'=>'dosen/catatan.php',      'icon'=>'file','label'=>'Catatan Bimbingan',      'id'=>'catatan'],
      ['href'=>'dosen/jadwal.php',       'icon'=>'calendar','label'=>'Jadwal Konsultasi',     'id'=>'jadwal_dosen'],
      ['href'=>'dosen/slot.php',         'icon'=>'clock-plus','label'=>'Slot Konsultasi',     'id'=>'slot_dosen'],
      ['href'=>'dosen/ujian.php',        'icon'=>'award','label'=>'Seminar & Sidang',     'id'=>'ujian_dosen'],
    ]],
    ['section'=>'Informasi','items'=>[
      ['href'=>'info/pengumuman.php','icon'=>'megaphone','label'=>'Pengumuman','id'=>'pengumuman'],
      ['href'=>'kalender.php','icon'=>'calendar','label'=>'Kalender','id'=>'kalender'],
      ['href'=>'info/panduan.php','icon'=>'map','label'=>'Panduan','id'=>'panduan'],
      ['href'=>'info/template.php','icon'=>'download','label'=>'Template Dokumen','id'=>'template'],
      ['href'=>'info/faq.php','icon'=>'help','label'=>'FAQ & Bantuan','id'=>'faq'],
      ['href'=>'info/dosen.php','icon'=>'users','label'=>'Direktori Dosen','id'=>'direktori_dosen'],
    ]],
    ['section'=>'Akun','items'=>[
      ['href'=>'profil.php','icon'=>'user','label'=>'Profil Saya','id'=>'profil'],
    ]],
  ],
  'admin' => [
    ['section'=>'Manajemen','items'=>[
      ['href'=>'dashboard.php',             'icon'=>'dashboard','label'=>'Dashboard',            'id'=>'dashboard'],
      ['href'=>'admin/validasi_judul.php',  'icon'=>'check-circle','label'=>'Validasi Judul',       'id'=>'validasi_judul'],
      ['href'=>'admin/relasi.php',          'icon'=>'link','label'=>'Relasi & SK',          'id'=>'relasi'],
      ['href'=>'admin/pengguna.php',        'icon'=>'users','label'=>'Kelola Pengguna',      'id'=>'kelola_pengguna'],
      ['href'=>'admin/ujian.php',           'icon'=>'award','label'=>'Seminar & Sidang',     'id'=>'ujian_admin'],
      ['href'=>'admin/laporan.php',         'icon'=>'chart','label'=>'Laporan & Aktivitas',  'id'=>'laporan'],
    ]],
    ['section'=>'Informasi','items'=>[
      ['href'=>'info/pengumuman.php','icon'=>'megaphone','label'=>'Pengumuman','id'=>'pengumuman'],
      ['href'=>'kalender.php','icon'=>'calendar','label'=>'Kalender','id'=>'kalender'],
      ['href'=>'info/panduan.php','icon'=>'map','label'=>'Panduan','id'=>'panduan'],
      ['href'=>'info/template.php','icon'=>'download','label'=>'Template Dokumen','id'=>'template'],
      ['href'=>'info/faq.php','icon'=>'help','label'=>'FAQ & Bantuan','id'=>'faq'],
    ]],
    ['section'=>'Sistem','items'=>[
      ['href'=>'admin/impor.php','icon'=>'import','label'=>'Impor Data','id'=>'impor'],
      ['href'=>'admin/reset_sandi.php','icon'=>'key','label'=>'Reset Kata Sandi','id'=>'reset_sandi','badge'=>$jmlReset],
      ['href'=>'admin/pengaturan.php','icon'=>'settings','label'=>'Pengaturan','id'=>'pengaturan'],
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
            <span class="nav-icon" aria-hidden="true"><?= icon('lock') ?></span>
            <span class="min-w-0">
              <?= e($item['label']) ?>
              <span class="nav-hint"><?= e($item['hint'] ?? 'Belum tersedia') ?></span>
            </span>
          </span>
        <?php else: ?>
          <a href="<?= BASE_URL . '/' . $item['href'] ?>" class="nav-item <?= $isActive ? 'active' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
            <span class="nav-icon" aria-hidden="true"><?= icon($item['icon']) ?></span>
            <?= e($item['label']) ?>
            <?php if (!empty($item['badge'])): ?><span class="nav-badge"><?= (int)$item['badge'] ?></span><?php endif; ?>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-footer">
    <a href="<?= BASE_URL ?>/logout.php" class="logout-btn">
      <?= icon('logout') ?> Keluar
    </a>
  </div>
</aside>