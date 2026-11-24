<?php
// notifikasi.php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

requireLogin();

$pageTitle  = 'Semua Notifikasi';
$activePage = 'notifikasi'; 
$id_user    = userId();

// Handle Aksi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    
    if ($aksi === 'tandai_semua') {
        execute('UPDATE notifikasi SET is_read = 1 WHERE id_user = ?', [$id_user]);
        // Dari panel lonceng: kembali ke halaman asal (hanya path relatif aplikasi)
        $kembali = (string)($_POST['kembali'] ?? '');
        if ($kembali !== '' && preg_match('~^[A-Za-z0-9_/-]+\.php(\?[^\s#]*)?$~', $kembali) && !str_contains($kembali, '..')) {
            header('Location: ' . BASE_URL . '/' . $kembali);
            exit;
        }
        setFlash('success', 'Semua notifikasi telah ditandai sebagai dibaca.');
    } elseif ($aksi === 'hapus') {
        $id_notif = (int)$_POST['id_notif'];
        execute('DELETE FROM notifikasi WHERE id_notif = ? AND id_user = ?', [$id_notif, $id_user]);
        setFlash('success', 'Notifikasi berhasil dihapus.');
    }
    
    header('Location: ' . BASE_URL . '/notifikasi.php');
    exit;
}

// Klik notifikasi: tandai dibaca lalu buka halaman tujuannya
if (isset($_GET['buka'])) {
    $n = fetchOne('SELECT id_notif, tautan FROM notifikasi WHERE id_notif = ? AND id_user = ?', [(int)$_GET['buka'], $id_user]);
    if ($n) {
        execute('UPDATE notifikasi SET is_read = 1 WHERE id_notif = ?', [$n['id_notif']]);
        $tn = tautanNotif($n['tautan'] ?? null);
        if ($tn) {
            header('Location: ' . $tn[0]);
            exit;
        }
    }
    header('Location: ' . BASE_URL . '/notifikasi.php' . ($n ? '#n' . (int)$n['id_notif'] : ''));
    exit;
}

if (isset($_GET['read_id'])) {
    $read_id = (int)$_GET['read_id'];
    execute('UPDATE notifikasi SET is_read = 1 WHERE id_notif = ? AND id_user = ?', [$read_id, $id_user]);
    header('Location: ' . BASE_URL . '/notifikasi.php');
    exit;
}

// Ambil data notifikasi
$semua_notif = fetchAll('SELECT * FROM notifikasi WHERE id_user = ? ORDER BY created_at DESC', [$id_user]);
$typeIcon    = ['info'=>'ℹ️', 'sukses'=>'✅', 'peringatan'=>'⚠️', 'error'=>'❌', 'danger'=>'❌'];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="layout">
    <main class="main">
        <?php include __DIR__ . '/includes/topbar.php'; ?>
        
        <div class="content">
            <?= tampilFlash() ?>

            <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>Kotak Masuk Notifikasi</h1>
                    <p>Pemberitahuan aktivitas, pesan, dan dokumen masuk</p>
                </div>
                
                <?php if (!empty($semua_notif)): ?>
                    <form method="POST"><?= csrfField() ?>
                        <input type="hidden" name="aksi" value="tandai_semua">
                        <button type="submit" class="btn btn-secondary" style="font-size: 13px;">
                            ✔️ Tandai Semua Dibaca
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="card-body" style="padding: 0;">
                    <?php if (empty($semua_notif)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📭</div>
                            <div class="empty-title">Belum ada pemberitahuan</div>
                            <div class="empty-sub">Anda tidak memiliki notifikasi saat ini.</div>
                        </div>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column;">
                            <?php foreach ($semua_notif as $n): 
                                $isUnread = $n['is_read'] == 0;
                            ?>
                                <div id="n<?= (int)$n['id_notif'] ?>" style="display: flex; align-items: flex-start; gap: 16px; padding: 20px; border-bottom: 1px solid var(--cream-dark); background-color: <?= $isUnread ? 'rgba(74, 144, 217, 0.04)' : 'transparent' ?>;">
                                    
                                    <div style="font-size: 24px; flex-shrink: 0;">
                                        <?= $typeIcon[$n['tipe']] ?? 'ℹ️' ?>
                                    </div>
                                    
                                    <div style="flex: 1;">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                            <h3 style="font-size: 15px; margin: 0 0 6px 0; color: var(--navy); <?= $isUnread ? 'font-weight: 700;' : 'font-weight: 500;' ?>">
                                                <?= e($n['judul']) ?>
                                            </h3>
                                            <span style="font-size: 12px; color: var(--slate); white-space: nowrap;">
                                                <?= tgl($n['created_at'], 'jam') ?>
                                            </span>
                                        </div>
                                        
                                        <p style="font-size: 13px; color: <?= $isUnread ? 'var(--navy)' : 'var(--slate)' ?>; margin: 0 0 12px 0; line-height: 1.5;">
                                            <?= e($n['pesan']) ?>
                                        </p>
                                        
                                        <?php if ($tn = tautanNotif($n['tautan'] ?? null)): ?>
                                            <div style="margin-bottom: 12px;">
                                                <a href="<?= BASE_URL ?>/notifikasi.php?buka=<?= (int)$n['id_notif'] ?>" class="btn btn-primary" style="font-size: 11px; padding: 6px 12px; text-decoration: none; display: inline-flex; gap: 6px;">
                                                    <?= e($tn[1]) ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div style="display: flex; gap: 12px; align-items: center; margin-top:4px;">
                                            <?php if ($isUnread): ?>
                                                <a href="notifikasi.php?read_id=<?= $n['id_notif'] ?>" class="badge info" style="text-decoration: none;">
                                                    <span class="badge-dot"></span>Tandai Dibaca
                                                </a>
                                            <?php endif; ?>
                                            
                                            <form method="POST" data-confirm="Hapus notifikasi ini?" data-confirm-ok="Hapus" style="margin: 0;"><?= csrfField() ?>
                                                <input type="hidden" name="aksi" value="hapus">
                                                <input type="hidden" name="id_notif" value="<?= $n['id_notif'] ?>">
                                                <button type="submit" style="background: none; border: none; color:var(--danger-text); font-size: 12px; cursor: pointer; text-decoration: underline; padding:0;">
                                                    Hapus Pesan
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    
                                    <?php if ($isUnread): ?>
                                        <div style="width: 8px; height: 8px; background-color:var(--info-text); border-radius: 50%; flex-shrink: 0; margin-top: 8px;"></div>
                                    <?php endif; ?>
                                    
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>