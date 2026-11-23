<?php
// bandingkan.php — Bandingkan dua versi dokumen berdampingan (D15)
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
requireRole('mahasiswa', 'dosen');

$dok = aksesDokumen((int)($_GET['dok'] ?? 0));
if (!$dok) {
    setFlash('danger', 'Dokumen tidak ditemukan atau Anda tidak memiliki akses.');
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$versi = fetchAll('SELECT * FROM dokumen WHERE id_mahasiswa=? AND jenis_bab=? ORDER BY versi DESC', [$dok['id_mahasiswa'], $dok['jenis_bab']]);
$perId = array_column($versi, null, 'id_dokumen');

// Kanan = versi yang dipilih (bawaan terbaru), kiri = versi sebelumnya
$kanan = $perId[(int)($_GET['kanan'] ?? $dok['id_dokumen'])] ?? $versi[0];
$kiri = null;
if (isset($_GET['kiri'], $perId[(int)$_GET['kiri']])) {
    $kiri = $perId[(int)$_GET['kiri']];
} else {
    foreach ($versi as $v) { if ($v['versi'] < $kanan['versi']) { $kiri = $v; break; } }
}
$kiri ??= end($versi);

// Catatan revisi pada versi kiri = apa yang diminta untuk diperbaiki di versi kanan
$revisiKiri = fetchAll('SELECT * FROM revisi WHERE id_dokumen=? ORDER BY tgl_revisi', [$kiri['id_dokumen']]);

function panelVersi(array $v): string {
    $ext = strtolower(pathinfo($v['file_path'], PATHINFO_EXTENSION));
    $url = fileUrl($v['file_path']);
    if ($ext === 'pdf') {
        return '<iframe class="pratinjau-pdf" src="' . e($url) . '#view=FitH" title="Pratinjau v' . (int)$v['versi'] . '"></iframe>';
    }
    return '<div class="empty-state" style="padding:40px 16px;"><div class="empty-icon">📄</div><div class="empty-title">Pratinjau tidak tersedia untuk ' . e(strtoupper($ext)) . '</div>'
        . '<div class="empty-sub">Unggah dalam format PDF agar bisa dibandingkan langsung di browser.</div>'
        . '<a class="btn btn-secondary btn-sm" style="margin-top:10px;" href="' . e($url) . '">' . icon('download', 16) . ' Unduh</a></div>';
}

$pageTitle  = 'Bandingkan Versi';
$activePage = role() === 'mahasiswa' ? 'dokumen' : 'revisi_dokumen';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<div class="content">
  <a class="link fs-13" href="<?= BASE_URL ?>/diskusi.php?dok=<?= (int)$kanan['id_dokumen'] ?>">&larr; Ke diskusi dokumen</a>
  <div class="page-header" style="margin-top:8px;">
    <h1>Bandingkan <?= e(label($dok['jenis_bab'])) ?></h1>
    <p><?= e($dok['nama_mhs']) ?> · <?= count($versi) ?> versi</p>
  </div>

  <?php if (count($versi) < 2): ?>
    <div class="card empty-state" style="padding:40px;"><div class="empty-title">Baru ada satu versi</div><div class="empty-sub">Perbandingan tersedia setelah bab ini diunggah ulang.</div></div>
  <?php else: ?>
  <form method="GET" class="card mb-20"><div class="card-body bandingkan-pilih">
    <input type="hidden" name="dok" value="<?= (int)$dok['id_dokumen'] ?>">
    <?php foreach (['kiri' => ['Versi lama', $kiri], 'kanan' => ['Versi baru', $kanan]] as $nama => [$lbl, $pilih]): ?>
      <div class="form-group" style="margin:0;">
        <label class="form-label" for="v_<?= $nama ?>"><?= $lbl ?></label>
        <select class="form-input" id="v_<?= $nama ?>" name="<?= $nama ?>" onchange="this.form.submit()">
          <?php foreach ($versi as $v): ?>
            <option value="<?= (int)$v['id_dokumen'] ?>" <?= $v['id_dokumen'] == $pilih['id_dokumen'] ? 'selected' : '' ?>>v<?= (int)$v['versi'] ?> · <?= tgl($v['tgl_upload']) ?> · <?= e(label($v['status'])) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endforeach; ?>
    <noscript><button class="btn btn-primary">Tampilkan</button></noscript>
  </div></form>

  <?php if ($revisiKiri): ?>
    <div class="card mb-20">
      <div class="card-header"><div class="card-title">Catatan revisi pada v<?= (int)$kiri['versi'] ?></div>
        <span class="badge neutral"><?= count(array_filter($revisiKiri, fn($r) => $r['status'] === 'sudah')) ?> dari <?= count($revisiKiri) ?> ditandai selesai</span></div>
      <div class="card-body">
        <ul class="cek-revisi">
          <?php foreach ($revisiKiri as $r): ?>
            <li class="<?= $r['status'] === 'sudah' ? 'selesai' : '' ?>">
              <span aria-hidden="true"><?= $r['status'] === 'sudah' ? '✓' : '○' ?></span>
              <div><?= e($r['catatan_revisi']) ?> <span class="meta"><?= $r['halaman_revisi'] ? '· hal. ' . e($r['halaman_revisi']) : '' ?> · <?= e(label($r['prioritas'])) ?></span></div>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  <?php endif; ?>

  <div class="grid-2 bandingkan">
    <?php foreach ([$kiri, $kanan] as $v): ?>
      <div class="card">
        <div class="card-header">
          <div class="card-title">v<?= (int)$v['versi'] ?> <span class="meta">· <?= tgl($v['tgl_upload'], 'jam') ?></span></div>
          <span class="badge <?= badge($v['status']) ?>"><span class="badge-dot"></span><?= e(label($v['status'])) ?></span>
        </div>
        <div class="card-body" style="padding:0;"><?= panelVersi($v) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
