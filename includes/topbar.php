<?php
// includes/topbar.php
$user     = currentUser();
$initials = implode('', array_map(fn($w) => strtoupper($w[0]),
              array_filter(explode(' ', $user['nama_lengkap'] ?? 'U'))));
$initials = substr($initials, 0, 2);

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
    <div class="notif-btn" id="notifBtn">
      🔔
      <?php if ($unreadCount > 0): ?>
        <span class="notif-dot"></span>
      <?php endif; ?>
    </div>

    <!-- Notification panel -->
    <div class="notif-panel" id="notifPanel">
      <div class="notif-panel-header">
        Notifikasi
        <a href="<?= BASE_URL ?>/notifikasi.php">Lihat semua</a>
      </div>
      <?php if (empty($notifs)): ?>
        <div style="padding:24px;text-align:center;color:var(--slate);font-size:13px;">
          Tidak ada notifikasi
        </div>
      <?php else: ?>
        <?php foreach ($notifs as $n): ?>
          <div class="notif-list-item <?= $n['is_read'] ? '' : 'unread' ?>">
            <div style="font-size:18px;flex-shrink:0;"><?= $typeIcon[$n['tipe']] ?? 'ℹ️' ?></div>
            <div>
              <div class="notif-msg"><strong><?= e($n['judul']) ?></strong><br><?= e(mb_strimwidth($n['pesan'],0,80,'...')) ?></div>
              <div class="notif-time"><?= date('d M Y H:i', strtotime($n['created_at'])) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Avatar -->
    <div class="avatar" style="width:36px;height:36px;font-size:13px;cursor:pointer;"><?= e($initials) ?></div>
  </div>
</header>
