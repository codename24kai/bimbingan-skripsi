<?php
// mahasiswa/ujian.php — Pendaftaran seminar proposal dan sidang akhir (D9)
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('mahasiswa');

$pageTitle  = 'Seminar & Sidang';
$activePage = 'ujian';
$eid        = entityId();

$bimbingan = fetchOne('SELECT b.*, u.id_user AS uid_dosen, u.nama_lengkap AS nama_dosen FROM bimbingan b
                       JOIN dosen d ON d.id_dosen = b.id_dosen JOIN user u ON u.id_user = d.id_user
                       WHERE b.id_mahasiswa = ? AND b.status IN ("aktif","selesai") ORDER BY (b.status="aktif") DESC, b.id_bimbingan DESC LIMIT 1', [$eid]);

/** Pendaftaran terakhir per jenis. */
function ujianTerakhir(int $idBimb, string $jenis): ?array {
    return fetchOne('SELECT * FROM ujian WHERE id_bimbingan=? AND jenis=? ORDER BY id_ujian DESC LIMIT 1', [$idBimb, $jenis]);
}
/** Boleh mendaftar jika belum ada pendaftaran, atau yang terakhir ditolak / tidak lulus. */
function bolehDaftar(?array $u): bool {
    return !$u || $u['status'] === 'ditolak' || ($u['status'] === 'selesai' && $u['hasil'] === 'tidak_lulus');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $bimbingan && $bimbingan['status'] === 'aktif') {
    $jenis = $_POST['jenis'] ?? '';
    try {
        if (!in_array($jenis, JENIS_UJIAN, true)) throw new Exception('Jenis ujian tidak valid.');
        if (!bolehDaftar(ujianTerakhir((int)$bimbingan['id_bimbingan'], $jenis))) throw new Exception('Pendaftaran ' . label($jenis) . ' Anda masih diproses.');
        if ($jenis === 'sidang_akhir') {
            $sem = ujianTerakhir((int)$bimbingan['id_bimbingan'], 'seminar_proposal');
            if (!$sem || $sem['status'] !== 'selesai' || !in_array($sem['hasil'], ['lulus', 'lulus_revisi'], true)) {
                throw new Exception('Sidang akhir bisa didaftarkan setelah lulus seminar proposal.');
            }
        }
        $path = simpanUpload($_FILES['berkas'] ?? [], 'ujian_' . $eid, ['pdf', 'zip'], 20480);
        execute('INSERT INTO ujian (id_bimbingan, jenis, file_berkas, catatan_mhs) VALUES (?,?,?,?)',
            [$bimbingan['id_bimbingan'], $jenis, $path, mb_substr(trim($_POST['catatan'] ?? ''), 0, 2000) ?: null]);
        kirimNotif((int)$bimbingan['uid_dosen'], 'Pendaftaran ' . label($jenis),
            (currentUser()['nama_lengkap'] ?? 'Mahasiswa') . ' mendaftar ' . mb_strtolower(label($jenis)) . ' dan menunggu persetujuan Anda.',
            'info', 'ujian', 'dosen/ujian.php');
        setFlash('success', 'Pendaftaran terkirim. Menunggu persetujuan dosen pembimbing.');
    } catch (Exception $ex) {
        setFlash('danger', $ex->getMessage());
    }
    header('Location: ' . BASE_URL . '/mahasiswa/ujian.php');
    exit;
}

$babOk = [];
if ($bimbingan) {
    foreach (fetchAll('SELECT DISTINCT UPPER(jenis_bab) AS b FROM dokumen WHERE id_mahasiswa=? AND status="disetujui"', [$eid]) as $r) $babOk[$r['b']] = true;
}
$syarat = [
    'seminar_proposal' => [['Proposal atau Bab I sampai III disetujui pembimbing', isset($babOk['PROPOSAL']) || (isset($babOk['BAB I'], $babOk['BAB II'], $babOk['BAB III']))]],
    'sidang_akhir'     => [['Lulus seminar proposal', false], ['Bab I sampai V disetujui pembimbing', count(array_intersect_key(array_flip(TAHAP_BAB), $babOk)) === 5]],
];
$riwayat = $bimbingan ? fetchAll('SELECT * FROM ujian WHERE id_bimbingan=? ORDER BY id_ujian DESC', [$bimbingan['id_bimbingan']]) : [];

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">
  <?php $flash = getFlash(); if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div><?php endif; ?>
  <div class="page-header"><h1>Seminar & Sidang</h1><p>Daftar seminar proposal dan sidang akhir, lalu pantau jadwal, penguji, dan hasilnya</p></div>

  <?php if (!$bimbingan): ?>
    <div class="alert alert-static info">Pendaftaran seminar dan sidang terbuka setelah SK pembimbing terbit.</div>
  <?php else: ?>
  <div class="grid-2">
    <?php foreach (JENIS_UJIAN as $jenis):
      $u = ujianTerakhir((int)$bimbingan['id_bimbingan'], $jenis);
      if ($jenis === 'sidang_akhir') {
          $sem = ujianTerakhir((int)$bimbingan['id_bimbingan'], 'seminar_proposal');
          $syarat['sidang_akhir'][0][1] = $sem && $sem['status'] === 'selesai' && in_array($sem['hasil'], ['lulus', 'lulus_revisi'], true);
      }
      $terkunci = $jenis === 'sidang_akhir' && !$syarat['sidang_akhir'][0][1];
    ?>
      <div class="card">
        <div class="card-header">
          <div class="card-title"><?= icon('award', 18) ?> <?= e(label($jenis)) ?></div>
          <?php if ($u): ?><span class="badge <?= badge($u['hasil'] ?: $u['status']) ?>"><span class="badge-dot"></span><?= e(label($u['hasil'] ?: $u['status'])) ?></span><?php endif; ?>
        </div>
        <div class="card-body">
          <?php if ($u && !bolehDaftar($u)): $penguji = pengujiUjian((int)$u['id_ujian']); ?>
            <div class="detail-row"><span class="info-label">Didaftarkan</span><span><?= tgl($u['created_at'], 'jam') ?></span></div>
            <div class="detail-row"><span class="info-label">Berkas</span><a class="link" href="<?= e(fileUrl($u['file_berkas'])) ?>">Unduh berkas</a></div>
            <?php if ($u['status'] === 'diajukan'): ?>
              <div class="status-card status-card--blok mt-10"><div class="status-card__title">Menunggu persetujuan pembimbing</div><p class="fs-13"><?= e($bimbingan['nama_dosen']) ?> akan memeriksa kelayakan Anda.</p></div>
            <?php elseif ($u['status'] === 'disetujui_pembimbing'): ?>
              <div class="status-card status-card--blok mt-10"><div class="status-card__title">Disetujui pembimbing</div><p class="fs-13">Bagian akademik sedang menyiapkan jadwal dan penguji.</p></div>
            <?php else: ?>
              <div class="detail-row"><span class="info-label">Jadwal</span><span class="fw-500"><?= tgl($u['tgl_ujian'] . ' ' . $u['waktu_mulai'], 'harijam') ?> <?= $u['status'] === 'terjadwal' ? '· ' . tglRelatif($u['tgl_ujian']) : '' ?></span></div>
              <div class="detail-row"><span class="info-label">Ruang</span><span><?= e($u['ruang'] ?: '-') ?></span></div>
              <div class="detail-row"><span class="info-label">Penguji</span><span><?php foreach ($penguji as $i => $p): ?><?= $i ? ', ' : '' ?><?= e($p['nama_lengkap']) ?> (<?= e(mb_strtolower(label($p['peran']))) ?>)<?php endforeach; ?><?= $penguji ? '' : '-' ?></span></div>
              <?php if ($u['status'] === 'selesai'): ?>
                <div class="detail-row detail-row--last"><span class="info-label">Nilai akhir</span><span class="fw-500"><?= $u['nilai_akhir'] !== null ? e(number_format((float)$u['nilai_akhir'], 2, ',', '.')) . ' (' . nilaiHuruf((float)$u['nilai_akhir']) . ')' : '-' ?></span></div>
              <?php endif; ?>
            <?php endif; ?>
          <?php else: ?>
            <?php if ($u && $u['status'] === 'ditolak'): ?>
              <div class="alert alert-static warning">Pendaftaran sebelumnya ditolak pembimbing<?= $u['catatan_pembimbing'] ? ': ' . e($u['catatan_pembimbing']) : '.' ?></div>
            <?php elseif ($u && $u['hasil'] === 'tidak_lulus'): ?>
              <div class="alert alert-static warning">Hasil ujian sebelumnya tidak lulus. Perbaiki naskah lalu daftar ulang.</div>
            <?php endif; ?>
            <div class="info-label" style="margin-bottom:6px;">Persyaratan</div>
            <ul class="cek-revisi" style="margin-bottom:14px;">
              <?php foreach ($syarat[$jenis] as [$t, $ok]): ?>
                <li class="<?= $ok ? 'selesai' : '' ?>"><span aria-hidden="true"><?= $ok ? '✓' : '○' ?></span><div><?= e($t) ?></div></li>
              <?php endforeach; ?>
              <li><span aria-hidden="true">○</span><div>Formulir persetujuan pembimbing dan berkas lain dalam satu PDF atau ZIP (<a class="link" href="<?= BASE_URL ?>/info/template.php">template</a>)</div></li>
            </ul>
            <?php if ($terkunci): ?>
              <div class="empty-state"><div class="empty-sub">Terbuka setelah lulus seminar proposal.</div></div>
            <?php elseif ($bimbingan['status'] === 'aktif'): ?>
              <form method="POST" enctype="multipart/form-data"><?= csrfField() ?>
                <input type="hidden" name="jenis" value="<?= $jenis ?>">
                <div class="form-group">
                  <label class="form-label" for="b_<?= $jenis ?>">Berkas persyaratan</label>
                  <?= uploadZone('berkas', 'Klik atau seret PDF/ZIP', '.pdf,.zip', 'Maksimal 20 MB', true, 'b_' . $jenis) ?>
                </div>
                <div class="form-group">
                  <label class="form-label" for="c_<?= $jenis ?>">Catatan (opsional)</label>
                  <textarea class="form-input" id="c_<?= $jenis ?>" name="catatan" rows="2" placeholder="cth: Mohon dijadwalkan setelah tanggal 10"></textarea>
                </div>
                <button class="btn btn-gold btn-full" type="submit">Daftar <?= e(mb_strtolower(label($jenis))) ?></button>
              </form>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if (count($riwayat) > 1): ?>
    <div class="card" style="margin-top:20px;">
      <div class="card-header"><div class="card-title">Riwayat pendaftaran</div></div>
      <div class="card-body"><div class="table-wrap"><table>
        <thead><tr><th>Jenis</th><th>Didaftarkan</th><th>Jadwal</th><th>Status</th><th>Nilai</th></tr></thead>
        <tbody>
        <?php foreach ($riwayat as $u): ?>
          <tr><td><?= e(label($u['jenis'])) ?></td><td class="nowrap"><?= tgl($u['created_at']) ?></td>
            <td class="nowrap"><?= $u['tgl_ujian'] ? tgl($u['tgl_ujian']) : '-' ?></td>
            <td><span class="badge <?= badge($u['hasil'] ?: $u['status']) ?>"><span class="badge-dot"></span><?= e(label($u['hasil'] ?: $u['status'])) ?></span></td>
            <td><?= $u['nilai_akhir'] !== null ? e(number_format((float)$u['nilai_akhir'], 2, ',', '.')) : '-' ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div></div>
    </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
