<?php
// info/dosen.php — Direktori dosen dan bidang keahlian (S7), hanya baca
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('mahasiswa', 'dosen');

$pageTitle  = 'Direktori Dosen';
$activePage = 'direktori_dosen';

$q   = trim($_GET['q'] ?? '');
$bid = trim($_GET['bidang'] ?? '');

$sql = 'SELECT d.*, u.nama_lengkap, ps.nama_prodi,
               (SELECT COUNT(*) FROM bimbingan b WHERE b.id_dosen=d.id_dosen AND b.status="aktif") AS aktif,
               (SELECT COUNT(*) FROM bimbingan b WHERE b.id_dosen=d.id_dosen AND b.status="selesai") AS lulus,
               (SELECT GROUP_CONCAT(j.judul_indonesia ORDER BY b.id_bimbingan DESC SEPARATOR "||") FROM bimbingan b
                  JOIN judul_skripsi j ON j.id_judul=b.id_judul WHERE b.id_dosen=d.id_dosen) AS judul_bimbingan
        FROM dosen d JOIN user u ON u.id_user=d.id_user JOIN program_studi ps ON ps.id_prodi=d.id_prodi
        WHERE u.status="aktif"';
$p = [];
if ($q !== '')   { $sql .= ' AND (u.nama_lengkap LIKE ? OR d.keahlian LIKE ?)'; array_push($p, "%$q%", "%$q%"); }
if ($bid !== '') { $sql .= ' AND d.keahlian = ?'; $p[] = $bid; }
$dosen = fetchAll($sql . ' ORDER BY u.nama_lengkap', $p);
$bidangList = array_column(fetchAll('SELECT DISTINCT keahlian FROM dosen WHERE keahlian IS NOT NULL ORDER BY keahlian'), 'keahlian');

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">
  <div class="page-header"><h1>Direktori Dosen</h1><p>Kenali bidang keahlian dosen untuk memilih pembimbing yang sesuai topik</p></div>

  <form method="GET" class="search-bar">
    <div class="search-input-wrap"><span class="search-icon" aria-hidden="true">🔍</span>
      <input name="q" aria-label="Cari dosen" placeholder="Cari nama atau keahlian…" value="<?= e($q) ?>"></div>
    <label class="sr-only" for="fBidang">Bidang keahlian</label>
    <select class="form-input" id="fBidang" name="bidang" style="max-width:280px;" onchange="this.form.submit()">
      <option value="">Semua bidang</option>
      <?php foreach ($bidangList as $b): ?><option <?= $b === $bid ? 'selected' : '' ?>><?= e($b) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-secondary">Cari</button>
  </form>

  <?php if (!$dosen): ?>
    <div class="card empty-state" style="padding:40px;"><div class="empty-sub">Tidak ada dosen yang cocok.</div></div>
  <?php endif; ?>
  <div class="dosen-grid">
    <?php foreach ($dosen as $d):
      $sisa = max(0, (int)$d['kuota_bimbingan'] - (int)$d['aktif']);
      $judul = array_slice(array_filter(explode('||', (string)$d['judul_bimbingan'])), 0, 3); ?>
      <article class="card dosen-kartu">
        <div class="card-body">
          <div class="dosen-card__head">
            <div class="dosen-card__avatar" aria-hidden="true"><?= e(inisial($d['nama_lengkap'])) ?></div>
            <div class="min-w-0">
              <div class="dosen-card__nama"><?= e($d['nama_lengkap']) ?></div>
              <div class="meta"><?= e($d['jabatan_fungsional'] ?: 'Dosen') ?> · <?= e($d['nama_prodi']) ?></div>
            </div>
          </div>
          <?php if ($d['keahlian']): ?><span class="badge info" style="margin-top:10px;"><?= e($d['keahlian']) ?></span><?php endif; ?>
          <div class="dosen-angka">
            <div><b><?= (int)$d['aktif'] ?></b><span>dibimbing</span></div>
            <div><b><?= (int)$d['lulus'] ?></b><span>selesai</span></div>
            <div><b class="<?= $sisa ? '' : 'penuh' ?>"><?= $sisa ?></b><span>kuota sisa</span></div>
          </div>
          <?php if ($judul): ?>
            <div class="info-label" style="margin-top:12px;">Topik yang pernah dibimbing</div>
            <ul class="dosen-judul">
              <?php foreach ($judul as $j): ?><li><?= e(mb_strimwidth($j, 0, 90, '…')) ?></li><?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
