<?php
// diskusi.php — Diskusi mahasiswa dan dosen per dokumen (D2).
// Satu utas per bab: komentar dari semua versi bab yang sama ditampilkan bersama.
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
requireRole('mahasiswa', 'dosen');
wajibMigrasiFitur();

$idDok = (int)($_GET['dok'] ?? 0);
$dok = aksesDokumen($idDok);
if (!$dok) {
    setFlash('danger', 'Dokumen tidak ditemukan atau Anda tidak memiliki akses.');
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}
$pembimbing = $dok['pembimbing'];

$kembali = role() === 'mahasiswa' ? 'mahasiswa/dokumen.php' : 'dosen/revisi.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isi = trim($_POST['isi'] ?? '');
    $idRev = (int)($_POST['id_revisi'] ?? 0) ?: null;
    if ($idRev && !countRows('SELECT COUNT(*) FROM revisi r JOIN dokumen d ON d.id_dokumen=r.id_dokumen
                              WHERE r.id_revisi=? AND d.id_mahasiswa=? AND d.jenis_bab=?', [$idRev, $dok['id_mahasiswa'], $dok['jenis_bab']])) {
        $idRev = null;
    }
    if ($isi === '') {
        setFlash('danger', 'Tulis pesan terlebih dahulu.');
    } elseif (mb_strlen($isi) > 3000) {
        setFlash('danger', 'Pesan terlalu panjang (maksimal 3.000 karakter).');
    } else {
        execute('INSERT INTO komentar_dokumen (id_dokumen, id_revisi, id_user, isi) VALUES (?,?,?,?)', [$idDok, $idRev, userId(), $isi]);
        // Tandai catatan revisi selesai jika mahasiswa mencentangnya
        if ($idRev && role() === 'mahasiswa' && !empty($_POST['tandai_selesai'])) {
            execute('UPDATE revisi SET status="sudah" WHERE id_revisi=?', [$idRev]);
        }
        $penerima = role() === 'mahasiswa' ? (int)($pembimbing['id_user'] ?? 0) : (int)$dok['uid_mhs'];
        $dari = currentUser()['nama_lengkap'] ?? '';
        kirimNotif($penerima, 'Pesan baru: ' . label($dok['jenis_bab']),
            $dari . ': ' . mb_strimwidth($isi, 0, 140, '…'), 'info', 'diskusi', 'diskusi.php?dok=' . $idDok . '#akhir');
        setFlash('success', 'Pesan terkirim.');
    }
    header('Location: ' . BASE_URL . '/diskusi.php?dok=' . $idDok . '#akhir');
    exit;
}

// Semua versi bab yang sama
$versi = fetchAll('SELECT id_dokumen, versi, status, tgl_upload, file_path FROM dokumen WHERE id_mahasiswa=? AND jenis_bab=? ORDER BY versi DESC',
    [$dok['id_mahasiswa'], $dok['jenis_bab']]);
$idsVersi = array_column($versi, 'id_dokumen');
$ph = implode(',', array_fill(0, count($idsVersi), '?'));
$versiDari = array_column($versi, 'versi', 'id_dokumen');

$revisi = fetchAll("SELECT r.*, d.versi FROM revisi r JOIN dokumen d ON d.id_dokumen = r.id_dokumen
                    WHERE r.id_dokumen IN ($ph) ORDER BY r.tgl_revisi", $idsVersi);
$komentar = fetchAll("SELECT k.*, u.nama_lengkap, u.role FROM komentar_dokumen k JOIN user u ON u.id_user = k.id_user
                      WHERE k.id_dokumen IN ($ph) ORDER BY k.created_at", $idsVersi);

// Gabungkan catatan revisi dan komentar menjadi satu linimasa
$utas = [];
foreach ($revisi as $r) {
    $utas[] = ['jenis' => 'revisi', 'waktu' => $r['tgl_revisi'], 'data' => $r];
}
foreach ($komentar as $k) {
    $utas[] = ['jenis' => 'komentar', 'waktu' => $k['created_at'], 'data' => $k];
}
usort($utas, fn($a, $b) => strcmp($a['waktu'], $b['waktu']));
$revisiTerbuka = array_filter($revisi, fn($r) => $r['status'] === 'belum');

$pageTitle  = 'Diskusi ' . label($dok['jenis_bab']);
$activePage = role() === 'mahasiswa' ? 'dokumen' : 'revisi_dokumen';
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<div class="content">
  <?php $flash = getFlash(); if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div><?php endif; ?>

  <a class="link fs-13" href="<?= BASE_URL . '/' . $kembali ?>">&larr; Kembali</a>
  <div class="page-header" style="margin-top:8px;">
    <h1>Diskusi <?= e(label($dok['jenis_bab'])) ?></h1>
    <p><?= e($dok['nama_mhs']) ?> (<?= e($dok['nim']) ?>) · Pembimbing: <?= e($pembimbing['nama_lengkap'] ?? '-') ?></p>
  </div>

  <div class="split" style="--side:300px;">
    <div class="card">
      <div class="card-body">
        <?php if (!$utas): ?>
          <div class="empty-state"><div class="empty-icon">💬</div><div class="empty-title">Belum ada diskusi</div>
            <div class="empty-sub">Tulis pertanyaan atau tanggapan tentang dokumen ini di bawah.</div></div>
        <?php endif; ?>
        <div class="utas">
          <?php foreach ($utas as $u): $d = $u['data']; ?>
            <?php if ($u['jenis'] === 'revisi'): ?>
              <div class="utas-revisi" id="rev<?= (int)$d['id_revisi'] ?>">
                <div class="utas-meta">
                  <span class="badge <?= $d['prioritas'] === 'kritis' ? 'danger' : ($d['prioritas'] === 'mayor' ? 'warning' : 'neutral') ?>">Revisi <?= e(label($d['prioritas'])) ?></span>
                  <span class="badge <?= $d['status'] === 'sudah' ? 'success' : 'warning' ?>"><span class="badge-dot"></span><?= $d['status'] === 'sudah' ? 'Sudah dikerjakan' : 'Belum dikerjakan' ?></span>
                  <span class="meta">v<?= (int)$d['versi'] ?><?= $d['halaman_revisi'] ? ' · hal. ' . e($d['halaman_revisi']) : '' ?> · <?= tgl($d['tgl_revisi'], 'jam') ?></span>
                </div>
                <div class="utas-isi"><?= nl2br(e($d['catatan_revisi'])) ?></div>
              </div>
            <?php else: $saya = (int)$d['id_user'] === userId(); ?>
              <div class="utas-pesan <?= $saya ? 'saya' : '' ?>">
                <div class="mini-avatar" aria-hidden="true"><?= e(inisial($d['nama_lengkap'])) ?></div>
                <div class="utas-gelembung">
                  <div class="utas-meta"><b><?= e($saya ? 'Anda' : $d['nama_lengkap']) ?></b> <span class="meta">· <?= e(label($d['role'])) ?> · v<?= (int)($versiDari[$d['id_dokumen']] ?? 0) ?> · <?= tgl($d['created_at'], 'tglblnjam') ?></span></div>
                  <?php if ($d['id_revisi']): ?><a class="utas-rujuk" href="#rev<?= (int)$d['id_revisi'] ?>">Menanggapi catatan revisi</a><?php endif; ?>
                  <div class="utas-isi"><?= nl2br(e($d['isi'])) ?></div>
                </div>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>

        <form method="POST" class="utas-form" id="akhir"><?= csrfField() ?>
          <?php if ($revisi): ?>
            <div class="form-group">
              <label class="form-label" for="k_rev">Menanggapi catatan revisi (opsional)</label>
              <select class="form-input" id="k_rev" name="id_revisi">
                <option value="">Pesan umum</option>
                <?php foreach (array_reverse($revisi) as $r): ?>
                  <option value="<?= (int)$r['id_revisi'] ?>">v<?= (int)$r['versi'] ?> · <?= e(mb_strimwidth($r['catatan_revisi'], 0, 70, '…')) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>
          <div class="form-group">
            <label class="form-label" for="k_isi">Pesan</label>
            <textarea class="form-input" id="k_isi" name="isi" rows="3" maxlength="3000" required placeholder="Tulis pertanyaan atau tanggapan…"></textarea>
          </div>
          <div class="utas-form-aksi">
            <?php if (role() === 'mahasiswa' && $revisiTerbuka): ?>
              <label class="cek"><input type="checkbox" name="tandai_selesai" value="1"> Tandai catatan revisi yang dipilih sudah dikerjakan</label>
            <?php endif; ?>
            <button class="btn btn-gold" type="submit"><?= icon('chat', 16) ?> Kirim</button>
          </div>
        </form>
      </div>
    </div>

    <div>
      <div class="card" style="height:fit-content;">
        <div class="card-header"><div class="card-title">Versi dokumen</div></div>
        <div class="card-body">
          <?php foreach ($versi as $v): ?>
            <div class="versi-baris <?= (int)$v['id_dokumen'] === $idDok ? 'aktif' : '' ?>">
              <span class="mono">v<?= (int)$v['versi'] ?></span>
              <span class="badge <?= badge($v['status']) ?>"><span class="badge-dot"></span><?= e(label($v['status'])) ?></span>
              <span class="meta"><?= tgl($v['tgl_upload'], 'tglbln') ?></span>
              <a class="btn-icon" href="<?= e(fileUrl($v['file_path'])) ?>" target="_blank" aria-label="Buka v<?= (int)$v['versi'] ?>"><?= icon('download', 16) ?></a>
            </div>
          <?php endforeach; ?>
          <?php if (count($versi) > 1): ?>
            <a class="btn btn-secondary btn-sm btn-full" style="margin-top:10px;" href="<?= BASE_URL ?>/bandingkan.php?dok=<?= (int)$versi[0]['id_dokumen'] ?>"><?= icon('columns', 16) ?> Bandingkan versi</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
