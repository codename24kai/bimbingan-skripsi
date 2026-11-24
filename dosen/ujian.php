<?php
// dosen/ujian.php — Persetujuan pendaftaran (pembimbing) dan penilaian rubrik (pembimbing & penguji) (D9, D16)
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('dosen');

$pageTitle  = 'Seminar & Sidang';
$activePage = 'ujian_dosen';
$eid        = entityId();

/** Ujian beserta peran dosen yang login (pembimbing / penguji), atau null jika tidak terkait. */
function ujianSaya(int $idUjian, int $idDosen): ?array {
    $u = fetchOne('SELECT u.*, b.id_dosen AS id_pembimbing, b.id_mahasiswa, um.id_user AS uid_mhs, um.nama_lengkap AS nama_mhs
                   FROM ujian u JOIN bimbingan b ON b.id_bimbingan = u.id_bimbingan
                   JOIN mahasiswa m ON m.id_mahasiswa = b.id_mahasiswa JOIN user um ON um.id_user = m.id_user
                   WHERE u.id_ujian = ?', [$idUjian]);
    if (!$u) return null;
    if ((int)$u['id_pembimbing'] === $idDosen) { $u['peran_saya'] = 'pembimbing'; return $u; }
    if (countRows('SELECT COUNT(*) FROM penguji_ujian WHERE id_ujian=? AND id_dosen=?', [$idUjian, $idDosen])) { $u['peran_saya'] = 'penguji'; return $u; }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    $u = ujianSaya((int)($_POST['id_ujian'] ?? 0), $eid);
    if (!$u) {
        setFlash('danger', 'Ujian tidak ditemukan.');
    } elseif (in_array($aksi, ['setujui', 'tolak'], true)) {
        if ($u['peran_saya'] !== 'pembimbing' || $u['status'] !== 'diajukan') {
            setFlash('danger', 'Pendaftaran ini sudah diproses.');
        } elseif ($aksi === 'setujui') {
            execute('UPDATE ujian SET status="disetujui_pembimbing", catatan_pembimbing=? WHERE id_ujian=?', [trim($_POST['catatan'] ?? '') ?: null, $u['id_ujian']]);
            kirimNotif((int)$u['uid_mhs'], label($u['jenis']) . ' disetujui pembimbing', 'Pendaftaran Anda disetujui. Bagian akademik akan menjadwalkan ujian dan menunjuk penguji.', 'sukses', 'ujian', 'mahasiswa/ujian.php');
            kirimNotifAdmin('Ujian perlu dijadwalkan', label($u['jenis']) . ' ' . $u['nama_mhs'] . ' sudah disetujui pembimbing.', 'info', 'ujian', 'admin/ujian.php');
            setFlash('success', 'Pendaftaran disetujui dan diteruskan ke bagian akademik.');
        } else {
            $cat = trim($_POST['catatan'] ?? '');
            if ($cat === '') {
                setFlash('danger', 'Tulis alasan penolakan agar mahasiswa tahu apa yang harus diperbaiki.');
            } else {
                execute('UPDATE ujian SET status="ditolak", catatan_pembimbing=? WHERE id_ujian=?', [$cat, $u['id_ujian']]);
                kirimNotif((int)$u['uid_mhs'], label($u['jenis']) . ' belum disetujui', 'Pembimbing: ' . mb_strimwidth($cat, 0, 160, '…'), 'peringatan', 'ujian', 'mahasiswa/ujian.php');
                setFlash('success', 'Pendaftaran ditolak dan mahasiswa sudah diberi tahu.');
            }
        }
    } elseif ($aksi === 'nilai') {
        if ($u['status'] !== 'terjadwal') {
            setFlash('danger', 'Nilai hanya bisa diisi untuk ujian yang terjadwal dan belum ditetapkan hasilnya.');
        } elseif ($u['tgl_ujian'] > date('Y-m-d')) {
            setFlash('danger', 'Nilai bisa diisi mulai hari pelaksanaan ujian.');
        } else {
            $n = [];
            foreach (array_keys(RUBRIK_NILAI) as $k) $n[$k] = max(0, min(100, (int)($_POST[$k] ?? 0)));
            execute('INSERT INTO nilai_ujian (id_ujian, id_dosen, peran, n_penulisan, n_presentasi, n_penguasaan, n_metodologi, catatan)
                     VALUES (?,?,?,?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE n_penulisan=VALUES(n_penulisan), n_presentasi=VALUES(n_presentasi),
                       n_penguasaan=VALUES(n_penguasaan), n_metodologi=VALUES(n_metodologi), catatan=VALUES(catatan)',
                [$u['id_ujian'], $eid, $u['peran_saya'], $n['n_penulisan'], $n['n_presentasi'], $n['n_penguasaan'], $n['n_metodologi'],
                 mb_substr(trim($_POST['catatan'] ?? ''), 0, 2000) ?: null]);
            setFlash('success', 'Nilai tersimpan.');
        }
    }
    header('Location: ' . BASE_URL . '/dosen/ujian.php' . (isset($_POST['id_ujian']) ? '#u' . (int)$_POST['id_ujian'] : ''));
    exit;
}

$menunggu = fetchAll(
    'SELECT u.*, um.nama_lengkap AS nama_mhs, m.nim, j.judul_indonesia
     FROM ujian u JOIN bimbingan b ON b.id_bimbingan = u.id_bimbingan
     JOIN mahasiswa m ON m.id_mahasiswa = b.id_mahasiswa JOIN user um ON um.id_user = m.id_user JOIN judul_skripsi j ON j.id_judul = b.id_judul
     WHERE b.id_dosen = ? AND u.status = "diajukan" ORDER BY u.created_at', [$eid]
);
$ujianSaya = fetchAll(
    'SELECT u.*, um.nama_lengkap AS nama_mhs, m.nim, j.judul_indonesia,
            IF(b.id_dosen = ?, "pembimbing", "penguji") AS peran_saya
     FROM ujian u JOIN bimbingan b ON b.id_bimbingan = u.id_bimbingan
     JOIN mahasiswa m ON m.id_mahasiswa = b.id_mahasiswa JOIN user um ON um.id_user = m.id_user JOIN judul_skripsi j ON j.id_judul = b.id_judul
     WHERE u.status IN ("disetujui_pembimbing","terjadwal","selesai")
       AND (b.id_dosen = ? OR EXISTS (SELECT 1 FROM penguji_ujian pu WHERE pu.id_ujian = u.id_ujian AND pu.id_dosen = ?))
     ORDER BY (u.status = "terjadwal") DESC, u.tgl_ujian DESC, u.id_ujian DESC LIMIT 50', [$eid, $eid, $eid]
);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">
  <?= tampilFlash() ?>
  <div class="page-header"><h1>Seminar & Sidang</h1><p>Setujui pendaftaran mahasiswa bimbingan dan isi nilai ujian yang Anda bimbing atau uji</p></div>

  <div class="card mb-20">
    <div class="card-header"><div class="card-title">Menunggu persetujuan Anda</div><span class="badge <?= $menunggu ? 'warning' : 'neutral' ?>"><?= count($menunggu) ?></span></div>
    <div class="card-body">
      <?php if (!$menunggu): ?><div class="empty-state"><div class="empty-sub">Tidak ada pendaftaran yang menunggu.</div></div><?php endif; ?>
      <?php foreach ($menunggu as $u): ?>
        <div class="validate-card" id="u<?= (int)$u['id_ujian'] ?>">
          <div class="validate-body">
            <div class="validate-mhs"><?= e($u['nama_mhs']) ?> · <span class="mono"><?= e($u['nim']) ?></span></div>
            <div class="validate-title"><?= e(label($u['jenis'])) ?></div>
            <div class="validate-desc"><?= e($u['judul_indonesia']) ?></div>
            <div class="meta" style="margin-top:6px;">Didaftarkan <?= tgl($u['created_at'], 'jam') ?> · <a class="link" href="<?= e(fileUrl($u['file_berkas'])) ?>">Unduh berkas</a></div>
            <?php if ($u['catatan_mhs']): ?><div class="fs-13" style="margin-top:6px;">Catatan mahasiswa: <?= e($u['catatan_mhs']) ?></div><?php endif; ?>
          </div>
          <form method="POST" class="validate-actions" style="flex-direction:column;align-items:stretch;gap:8px;"><?= csrfField() ?>
            <input type="hidden" name="id_ujian" value="<?= (int)$u['id_ujian'] ?>">
            <label class="sr-only" for="cat<?= (int)$u['id_ujian'] ?>">Catatan</label>
            <textarea class="form-input" id="cat<?= (int)$u['id_ujian'] ?>" name="catatan" rows="2" placeholder="Catatan (wajib jika menolak)"></textarea>
            <div class="row-10" style="justify-content:flex-end;">
              <button class="btn btn-secondary btn-sm" name="aksi" value="tolak">Tolak</button>
              <button class="btn btn-success btn-sm" name="aksi" value="setujui" data-confirm="Setujui mahasiswa ini mengikuti <?= e(mb_strtolower(label($u['jenis']))) ?>?" data-confirm-ok="Setujui" data-confirm-tone="primary">Setujui</button>
            </div>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><div class="card-title">Ujian Anda</div></div>
    <div class="card-body">
      <?php if (!$ujianSaya): ?><div class="empty-state"><div class="empty-sub">Belum ada ujian yang Anda bimbing atau uji.</div></div><?php endif; ?>
      <?php foreach ($ujianSaya as $u):
        $nilaiSaya = fetchOne('SELECT * FROM nilai_ujian WHERE id_ujian=? AND id_dosen=?', [$u['id_ujian'], $eid]);
        $bisaNilai = $u['status'] === 'terjadwal' && $u['tgl_ujian'] && $u['tgl_ujian'] <= date('Y-m-d');
      ?>
        <details class="lipat ujian-item" id="u<?= (int)$u['id_ujian'] ?>" <?= $bisaNilai && !$nilaiSaya ? 'open' : '' ?>>
          <summary>
            <span class="fw-500"><?= e($u['nama_mhs']) ?></span> · <?= e(label($u['jenis'])) ?>
            <span class="badge neutral"><?= e(label($u['peran_saya'])) ?></span>
            <span class="badge <?= badge($u['hasil'] ?: $u['status']) ?>"><span class="badge-dot"></span><?= e(label($u['hasil'] ?: $u['status'])) ?></span>
            <?php if ($u['tgl_ujian']): ?><span class="meta"><?= tgl($u['tgl_ujian'] . ' ' . $u['waktu_mulai'], 'harijam') ?> · <?= e($u['ruang'] ?: '-') ?></span><?php endif; ?>
          </summary>
          <div class="lipat-isi">
            <div class="fs-13" style="margin-bottom:10px;"><?= e($u['judul_indonesia']) ?> · <a class="link" href="<?= e(fileUrl($u['file_berkas'])) ?>">Berkas</a></div>
            <?php if ($u['status'] === 'disetujui_pembimbing'): ?>
              <div class="meta">Menunggu dijadwalkan bagian akademik.</div>
            <?php elseif ($u['status'] === 'selesai'): ?>
              <div class="fs-14">Nilai akhir: <b><?= $u['nilai_akhir'] !== null ? e(number_format((float)$u['nilai_akhir'], 2, ',', '.')) . ' (' . nilaiHuruf((float)$u['nilai_akhir']) . ')' : '-' ?></b>
                <?= $nilaiSaya ? ' · Nilai Anda: ' . e(number_format(array_sum(array_map(fn($k) => $nilaiSaya[$k], array_keys(RUBRIK_NILAI))) / 4, 2, ',', '.')) : '' ?></div>
            <?php elseif (!$bisaNilai): ?>
              <div class="meta">Form nilai terbuka mulai <?= tgl($u['tgl_ujian'], 'lengkap') ?>.</div>
            <?php else: ?>
              <form method="POST" class="rubrik"><?= csrfField() ?>
                <input type="hidden" name="aksi" value="nilai"><input type="hidden" name="id_ujian" value="<?= (int)$u['id_ujian'] ?>">
                <div class="rubrik-grid">
                  <?php foreach (RUBRIK_NILAI as $k => $lbl): ?>
                    <div class="form-group">
                      <label class="form-label" for="<?= $k . $u['id_ujian'] ?>"><?= e($lbl) ?></label>
                      <input class="form-input nilai-input" type="number" min="0" max="100" required id="<?= $k . $u['id_ujian'] ?>" name="<?= $k ?>" value="<?= e((string)($nilaiSaya[$k] ?? '')) ?>">
                    </div>
                  <?php endforeach; ?>
                </div>
                <div class="form-group">
                  <label class="form-label" for="nc<?= (int)$u['id_ujian'] ?>">Catatan perbaikan untuk mahasiswa</label>
                  <textarea class="form-input" id="nc<?= (int)$u['id_ujian'] ?>" name="catatan" rows="2"><?= e($nilaiSaya['catatan'] ?? '') ?></textarea>
                </div>
                <div class="row-10" style="justify-content:space-between;">
                  <span class="meta">Rata-rata: <b class="rubrik-rata">-</b></span>
                  <button class="btn btn-primary btn-sm"><?= $nilaiSaya ? 'Perbarui nilai' : 'Simpan nilai' ?></button>
                </div>
              </form>
            <?php endif; ?>
          </div>
        </details>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<script>
document.querySelectorAll('form.rubrik').forEach(f => {
  const hitung = () => {
    const v = [...f.querySelectorAll('.nilai-input')].map(i => parseFloat(i.value)).filter(n => !isNaN(n));
    f.querySelector('.rubrik-rata').textContent = v.length === 4 ? (v.reduce((a, b) => a + b, 0) / 4).toFixed(2).replace('.', ',') : '-';
  };
  f.addEventListener('input', hitung); hitung();
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
