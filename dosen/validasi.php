<?php
// dosen/validasi.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('dosen');

$pageTitle  = 'Validasi Judul';
$activePage = 'validasi';
$eid        = entityId();

// Handle aksi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_judul = (int)($_POST['id_judul'] ?? 0);
    $aksi     = $_POST['aksi'] ?? '';

    $judul = fetchOne('SELECT j.*,m.id_mahasiswa,u.id_user AS id_user_mhs
                       FROM judul_skripsi j
                       JOIN mahasiswa m ON m.id_mahasiswa=j.id_mahasiswa
                       JOIN user u ON u.id_user=m.id_user
                       WHERE j.id_judul=?
                         AND EXISTS (SELECT 1 FROM bimbingan b
                                     WHERE b.id_mahasiswa = j.id_mahasiswa AND b.id_dosen = ?)', [$id_judul, $eid]);

    if (!$judul) {
        setFlash('danger', 'Pengajuan judul tidak ditemukan atau bukan milik mahasiswa bimbingan Anda.');
        header('Location: ' . BASE_URL . '/dosen/validasi.php');
        exit;
    }

    if ($judul) {
        if ($aksi === 'setujui') {
            execute('UPDATE judul_skripsi SET status="disetujui" WHERE id_judul=?', [$id_judul]);
            execute('INSERT INTO notifikasi (id_user,judul,pesan,tipe,modul) VALUES (?,?,?,?,?)',
                [$judul['id_user_mhs'], 'Judul Skripsi Disetujui',
                 'Selamat! Judul skripsi Anda telah disetujui oleh dosen pembimbing.','sukses','judul']);
            setFlash('success', 'Judul berhasil disetujui.');

        } elseif ($aksi === 'revisi') {
            execute('UPDATE judul_skripsi SET status="revisi",iterasi=iterasi+1 WHERE id_judul=?', [$id_judul]);
            execute('INSERT INTO notifikasi (id_user,judul,pesan,tipe,modul) VALUES (?,?,?,?,?)',
                [$judul['id_user_mhs'], 'Judul Perlu Direvisi',
                 'Dosen meminta revisi atas pengajuan judul Anda.','peringatan','judul']);
            setFlash('warning', 'Pengajuan dikembalikan untuk direvisi.');

        } elseif ($aksi === 'tolak') {
            execute('UPDATE judul_skripsi SET status="ditolak" WHERE id_judul=?', [$id_judul]);
            execute('INSERT INTO notifikasi (id_user,judul,pesan,tipe,modul) VALUES (?,?,?,?,?)',
                [$judul['id_user_mhs'], 'Judul Ditolak',
                 'Maaf, pengajuan judul Anda ditolak. Silakan ajukan judul baru.','error','judul']);
            setFlash('danger', 'Pengajuan judul ditolak.');
        }
        header('Location: ' . BASE_URL . '/dosen/validasi.php');
        exit;
    }
}

$pending = fetchAll(
    'SELECT j.*,m.nim,u.nama_lengkap AS nama_mhs
     FROM judul_skripsi j
     JOIN mahasiswa m ON m.id_mahasiswa=j.id_mahasiswa
     JOIN user u ON u.id_user=m.id_user
     JOIN bimbingan b ON b.id_mahasiswa=m.id_mahasiswa
     WHERE b.id_dosen=? AND j.status="diajukan"
     ORDER BY j.tgl_pengajuan ASC', [$eid]
);

$semua = fetchAll(
    'SELECT j.*,m.nim,u.nama_lengkap AS nama_mhs
     FROM judul_skripsi j
     JOIN mahasiswa m ON m.id_mahasiswa=j.id_mahasiswa
     JOIN user u ON u.id_user=m.id_user
     JOIN bimbingan b ON b.id_mahasiswa=m.id_mahasiswa
     WHERE b.id_dosen=? AND j.status != "diajukan"
     ORDER BY j.tgl_pengajuan DESC LIMIT 20', [$eid]
);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">
  <?php $flash = getFlash(); if ($flash): ?><div class="alert <?= $flash['type'] ?>"><?= e($flash['msg']) ?></div><?php endif; ?>
  <div class="page-header">
    <h1>Validasi Judul Skripsi</h1>
    <p>Tinjau dan berikan keputusan atas pengajuan judul mahasiswa bimbingan</p>
  </div>

  <?php if (!empty($pending)): ?>
    <div style="margin-bottom:12px;">
      <span class="badge warning" style="font-size:12px;padding:6px 14px;">⏳ <?= count($pending) ?> pengajuan menunggu validasi</span>
    </div>
    <?php foreach ($pending as $j): ?>
      <div class="validate-card">
        <div class="mini-avatar"><?= e(inisial($j['nama_mhs'])) ?></div>
        <div class="validate-body">
          <div class="validate-title"><?= e($j['judul_indonesia']) ?></div>
          <div class="validate-mhs">👤 <?= e($j['nama_mhs']) ?> · NIM: <?= e($j['nim']) ?> · Diajukan: <?= tgl($j['tgl_pengajuan']) ?> · Iterasi: <?= $j['iterasi'] ?></div>
          <?php if ($j['bidang_penelitian']): ?>
            <div style="font-size:12px;margin-bottom:6px;"><span class="badge info"><?= e($j['bidang_penelitian']) ?></span></div>
          <?php endif; ?>
          <?php if ($j['latar_belakang']): ?>
            <div class="validate-desc"><?= e(mb_strimwidth($j['latar_belakang'],0,300,'...')) ?></div>
          <?php endif; ?>
          <form method="POST" class="validate-actions"><?= csrfField() ?>
            <input type="hidden" name="id_judul" value="<?= $j['id_judul'] ?>">
            <button name="aksi" value="setujui" class="btn btn-success btn-sm">✅ Setujui</button>
            <button name="aksi" value="revisi"  class="btn btn-warn btn-sm">✏️ Minta Revisi</button>
            <button name="aksi" value="tolak"   class="btn btn-danger btn-sm"
              data-confirm="Tolak pengajuan judul <?= e($j['nama_mhs']) ?>? Mahasiswa harus mengajukan judul baru." data-confirm-ok="Tolak judul">❌ Tolak</button>
            <?php if ($j['file_proposal']): ?>
              <a href="<?= e(fileUrl($j['file_proposal'])) ?>" target="_blank" class="btn btn-secondary btn-sm">📄 Lihat Proposal</a>
            <?php endif; ?>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="alert alert-static info">✅ Tidak ada pengajuan yang menunggu validasi saat ini.</div>
  <?php endif; ?>

  <!-- Riwayat -->
  <?php if (!empty($semua)): ?>
    <div class="card" style="margin-top:20px;">
      <div class="card-header"><div class="card-title">Riwayat Validasi</div></div>
      <div class="card-body">
        <div class="table-wrap">
          <table>
            <thead><tr><th>Mahasiswa</th><th>Judul</th><th>Bidang</th><th>Status</th><th>Iterasi</th><th>Tanggal</th></tr></thead>
            <tbody>
              <?php foreach ($semua as $j): ?>
                <tr>
                  <td class="fw-500"><?= e($j['nama_mhs']) ?></td>
                  <td style="max-width:280px;font-size:13px;"><?= e(mb_strimwidth($j['judul_indonesia'],0,80,'...')) ?></td>
                  <td class="fs-13"><?= e($j['bidang_penelitian']) ?></td>
                  <td><span class="badge <?= badge($j['status']) ?>"><span class="badge-dot"></span><?= e(label($j['status'])) ?></span></td>
                  <td class="text-center"><?= $j['iterasi'] ?></td>
                  <td class="meta"><?= tgl($j['tgl_pengajuan']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
