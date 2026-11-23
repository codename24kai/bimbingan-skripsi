<?php
// info/pengumuman.php — Daftar pengumuman (semua peran) + kelola (admin)
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireLogin();
wajibMigrasiFitur();

$pageTitle  = 'Pengumuman';
$activePage = 'pengumuman';
$isAdmin    = role() === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    $aksi = $_POST['aksi'] ?? '';
    if ($aksi === 'simpan') {
        $id     = (int)($_POST['id_pengumuman'] ?? 0);
        $judul  = trim($_POST['judul'] ?? '');
        $isi    = trim($_POST['isi'] ?? '');
        $target = $_POST['target_role'] ?? 'semua';
        $prodi  = (int)($_POST['id_prodi'] ?? 0) ?: null;
        $pin    = !empty($_POST['disematkan']) ? 1 : 0;
        $sampai = trim($_POST['berlaku_sampai'] ?? '') ?: null;

        if ($judul === '' || $isi === '') {
            setFlash('danger', 'Judul dan isi pengumuman wajib diisi.');
        } elseif (!in_array($target, ['semua', 'mahasiswa', 'dosen'], true)) {
            setFlash('danger', 'Target pengumuman tidak valid.');
        } elseif ($sampai && !DateTime::createFromFormat('Y-m-d', $sampai)) {
            setFlash('danger', 'Tanggal berlaku tidak valid.');
        } elseif ($id) {
            execute('UPDATE pengumuman SET judul=?, isi=?, target_role=?, id_prodi=?, disematkan=?, berlaku_sampai=? WHERE id_pengumuman=?',
                [mb_substr($judul, 0, 150), $isi, $target, $prodi, $pin, $sampai, $id]);
            setFlash('success', 'Pengumuman diperbarui.');
        } else {
            execute('INSERT INTO pengumuman (judul, isi, target_role, id_prodi, disematkan, berlaku_sampai, dibuat_oleh) VALUES (?,?,?,?,?,?,?)',
                [mb_substr($judul, 0, 150), $isi, $target, $prodi, $pin, $sampai, userId()]);

            // Beri notifikasi ke pengguna yang dituju
            if (!empty($_POST['kirim_notif'])) {
                $sql = 'SELECT u.id_user FROM user u
                        LEFT JOIN mahasiswa m ON m.id_user = u.id_user
                        LEFT JOIN dosen d ON d.id_user = u.id_user
                        WHERE u.status = "aktif" AND u.role IN ("mahasiswa","dosen")';
                $p = [];
                if ($target !== 'semua') { $sql .= ' AND u.role = ?'; $p[] = $target; }
                if ($prodi) { $sql .= ' AND COALESCE(m.id_prodi, d.id_prodi) = ?'; $p[] = $prodi; }
                foreach (fetchAll($sql, $p) as $u) {
                    kirimNotif((int)$u['id_user'], 'Pengumuman: ' . mb_substr($judul, 0, 120), mb_strimwidth($isi, 0, 200, '…'), 'info', 'pengumuman', 'info/pengumuman.php');
                }
            }
            setFlash('success', 'Pengumuman diterbitkan.');
        }
    } elseif ($aksi === 'hapus') {
        execute('DELETE FROM pengumuman WHERE id_pengumuman=?', [(int)($_POST['id_pengumuman'] ?? 0)]);
        setFlash('success', 'Pengumuman dihapus.');
    }
    header('Location: ' . BASE_URL . '/info/pengumuman.php');
    exit;
}

$daftar = pengumumanUntukSaya(100);
$kedaluwarsa = $isAdmin ? fetchAll('SELECT p.*, ps.nama_prodi FROM pengumuman p LEFT JOIN program_studi ps ON ps.id_prodi=p.id_prodi
                                     WHERE p.berlaku_sampai < CURDATE() ORDER BY p.berlaku_sampai DESC LIMIT 20') : [];
$prodiList = $isAdmin ? fetchAll('SELECT id_prodi, nama_prodi FROM program_studi ORDER BY nama_prodi') : [];
$edit = null;
if ($isAdmin && isset($_GET['edit'])) {
    $edit = fetchOne('SELECT * FROM pengumuman WHERE id_pengumuman=?', [(int)$_GET['edit']]);
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">
  <?php $flash = getFlash(); if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div><?php endif; ?>

  <div class="page-header">
    <h1>Pengumuman</h1>
    <p><?= $isAdmin ? 'Terbitkan informasi untuk mahasiswa dan dosen' : 'Informasi terbaru dari bagian akademik' ?></p>
  </div>

  <div class="<?= $isAdmin ? 'split' : '' ?>" style="--side:360px;">
    <div>
      <?php if (empty($daftar)): ?>
        <div class="card empty-state" style="padding:48px;">
          <div class="empty-icon">📢</div>
          <div class="empty-title">Belum ada pengumuman</div>
          <div class="empty-sub">Pengumuman dari bagian akademik akan muncul di sini.</div>
        </div>
      <?php endif; ?>

      <?php foreach ($daftar as $p): ?>
        <article class="card pengumuman-card mb-20 <?= $p['disematkan'] ? 'is-pinned' : '' ?>" id="p<?= (int)$p['id_pengumuman'] ?>">
          <div class="card-body">
            <div class="pengumuman-head">
              <div class="min-w-0">
                <?php if ($p['disematkan']): ?><span class="badge warning mb-4"><span class="badge-dot"></span>Disematkan</span><?php endif; ?>
                <h2 class="pengumuman-judul"><?= e($p['judul']) ?></h2>
                <div class="meta"><?= tgl($p['created_at'], 'hari') ?>
                  <?php if ($isAdmin): ?> · Untuk <?= e(label($p['target_role'])) ?><?= $p['nama_prodi'] ? ' · ' . e($p['nama_prodi']) : '' ?><?php endif; ?>
                  <?php if ($p['berlaku_sampai']): ?> · Berlaku sampai <?= tgl($p['berlaku_sampai']) ?><?php endif; ?>
                </div>
              </div>
              <?php if ($isAdmin): ?>
                <div class="row-10">
                  <a class="btn-icon" href="?edit=<?= (int)$p['id_pengumuman'] ?>" aria-label="Edit pengumuman" title="Edit"><?= icon('pencil') ?></a>
                  <form method="POST" data-confirm="Hapus pengumuman ini?" data-confirm-ok="Hapus"><?= csrfField() ?>
                    <input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id_pengumuman" value="<?= (int)$p['id_pengumuman'] ?>">
                    <button class="btn-icon btn-icon--danger" aria-label="Hapus pengumuman" title="Hapus"><?= icon('trash') ?></button>
                  </form>
                </div>
              <?php endif; ?>
            </div>
            <div class="pengumuman-isi"><?= nl2br(e($p['isi'])) ?></div>
          </div>
        </article>
      <?php endforeach; ?>

      <?php if ($kedaluwarsa): ?>
        <div class="card">
          <div class="card-header"><div class="card-title">Sudah kedaluwarsa</div></div>
          <div class="card-body"><div class="table-wrap"><table>
            <thead><tr><th>Judul</th><th>Berakhir</th><th class="text-right">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($kedaluwarsa as $p): ?>
              <tr><td><?= e($p['judul']) ?></td><td class="nowrap"><?= tgl($p['berlaku_sampai']) ?></td>
                <td class="text-right"><a class="link" href="?edit=<?= (int)$p['id_pengumuman'] ?>">Edit</a></td></tr>
            <?php endforeach; ?>
            </tbody>
          </table></div></div>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($isAdmin): ?>
      <div class="form-card" style="height:fit-content;">
        <h2><?= $edit ? 'Edit Pengumuman' : 'Pengumuman Baru' ?></h2>
        <form method="POST"><?= csrfField() ?>
          <input type="hidden" name="aksi" value="simpan">
          <input type="hidden" name="id_pengumuman" value="<?= (int)($edit['id_pengumuman'] ?? 0) ?>">
          <div class="form-group">
            <label class="form-label" for="p_judul">Judul <span class="req">*</span></label>
            <input class="form-input" id="p_judul" name="judul" maxlength="150" required value="<?= e($edit['judul'] ?? '') ?>" placeholder="cth: Batas pendaftaran sidang Januari">
          </div>
          <div class="form-group">
            <label class="form-label" for="p_isi">Isi <span class="req">*</span></label>
            <textarea class="form-input" id="p_isi" name="isi" rows="6" required><?= e($edit['isi'] ?? '') ?></textarea>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="p_target">Untuk</label>
              <select class="form-input" id="p_target" name="target_role">
                <?php foreach (['semua' => 'Semua', 'mahasiswa' => 'Mahasiswa', 'dosen' => 'Dosen'] as $v => $l): ?>
                  <option value="<?= $v ?>" <?= ($edit['target_role'] ?? 'semua') === $v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label" for="p_prodi">Program studi</label>
              <select class="form-input" id="p_prodi" name="id_prodi">
                <option value="">Semua prodi</option>
                <?php foreach ($prodiList as $ps): ?>
                  <option value="<?= (int)$ps['id_prodi'] ?>" <?= (int)($edit['id_prodi'] ?? 0) === (int)$ps['id_prodi'] ? 'selected' : '' ?>><?= e($ps['nama_prodi']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label" for="p_sampai">Berlaku sampai (opsional)</label>
            <input class="form-input" type="date" id="p_sampai" name="berlaku_sampai" value="<?= e($edit['berlaku_sampai'] ?? '') ?>">
          </div>
          <label class="cek"><input type="checkbox" name="disematkan" value="1" <?= !empty($edit['disematkan']) ? 'checked' : '' ?>> Sematkan di atas</label>
          <?php if (!$edit): ?>
            <label class="cek"><input type="checkbox" name="kirim_notif" value="1" checked> Kirim notifikasi ke pengguna yang dituju</label>
          <?php endif; ?>
          <div class="form-actions" style="margin-top:16px;">
            <?php if ($edit): ?><a href="<?= BASE_URL ?>/info/pengumuman.php" class="btn btn-secondary">Batal</a><?php endif; ?>
            <button type="submit" class="btn btn-gold"><?= $edit ? 'Simpan perubahan' : 'Terbitkan' ?></button>
          </div>
        </form>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
