<?php
// includes/topbar.php
$user     = currentUser();
$initials = inisial($user['nama_lengkap'] ?? '');

// Notifikasi yang mengarah ke halaman ini otomatis dianggap sudah dibaca,
// sehingga badge tidak tertinggal setelah pengguna membuka halamannya.
tandaiNotifDibaca([halamanIni()]);

// Fetch latest 5 notifications
$notifs = fetchAll(
    'SELECT * FROM notifikasi WHERE id_user=? ORDER BY created_at DESC LIMIT 5',
    [userId()]
);
$unreadCount = countRows('SELECT COUNT(*) FROM notifikasi WHERE id_user=? AND is_read=0',[userId()]);

$typeIcon = ['info'=>'ℹ️','sukses'=>'✅','peringatan'=>'⚠️','error'=>'❌'];
?>

<header class="topbar">
  <div style="display:flex;align-items:center;gap:14px;">
    <button class="hamburger" onclick="toggleSidebar()">
      <span></span><span></span><span></span>
    </button>
    <div class="topbar-title"><?= e($pageTitle ?? 'Portal') ?></div>
  </div>

  <div class="topbar-right">
    <!-- Notification button -->
    <button type="button" class="notif-btn" id="notifBtn"
            aria-label="Notifikasi<?= $unreadCount > 0 ? ', ' . $unreadCount . ' belum dibaca' : '' ?>">
      <?= icon('bell', 18) ?>
      <?php if ($unreadCount > 0): ?>
        <span class="notif-count" aria-hidden="true"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span>
      <?php endif; ?>
    </button>

    <!-- Notification panel -->
    <div class="notif-panel" id="notifPanel">
      <div class="notif-panel-header">
        Notifikasi
        <div class="notif-panel-aksi">
          <?php if ($unreadCount > 0): ?>
            <form method="POST" action="<?= BASE_URL ?>/notifikasi.php"><?= csrfField() ?>
              <input type="hidden" name="aksi" value="tandai_semua">
              <input type="hidden" name="kembali" value="<?= e(halamanIni()) ?>">
              <button type="submit" class="link-btn">Tandai semua dibaca</button>
            </form>
          <?php endif; ?>
          <a href="<?= BASE_URL ?>/notifikasi.php">Lihat semua</a>
        </div>
      </div>
      <?php if (empty($notifs)): ?>
        <div style="padding:24px;text-align:center;color:var(--slate);font-size:13px;">
          Tidak ada notifikasi
        </div>
      <?php else: ?>
        <?php foreach ($notifs as $n): ?>
          <a href="<?= BASE_URL ?>/notifikasi.php?buka=<?= (int)$n['id_notif'] ?>" class="notif-list-item <?= $n['is_read'] ? '' : 'unread' ?>" style="text-decoration:none;">
            <div style="font-size:18px;flex-shrink:0;"><?= $typeIcon[$n['tipe']] ?? 'ℹ️' ?></div>
            <div>
              <div class="notif-msg"><strong><?= e($n['judul']) ?></strong><br><?= e(mb_strimwidth($n['pesan'],0,80,'...')) ?></div>
              <div class="notif-time"><?= tgl($n['created_at'], 'jam') ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Avatar -->
    <a href="<?= BASE_URL ?>/profil.php" class="avatar" style="width:36px;height:36px;font-size:13px;" aria-label="Profil saya" title="Profil saya"><?= e($initials) ?></a>
  </div>
</header>
