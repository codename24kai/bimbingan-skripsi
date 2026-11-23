<?php
// admin/ujian.php — Penjadwalan seminar/sidang, penunjukan penguji, dan penetapan hasil (D9, D16)
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('admin');

$pageTitle  = 'Seminar & Sidang';
$activePage = 'ujian_admin';

function ujianLengkap(int $id): ?array {
    return fetchOne('SELECT u.*, b.id_dosen AS id_pembimbing, b.id_mahasiswa, b.id_bimbingan, um.id_user AS uid_mhs, um.nama_lengkap AS nama_mhs
                     FROM ujian u JOIN bimbingan b ON b.id_bimbingan = u.id_bimbingan
                     JOIN mahasiswa m ON m.id_mahasiswa = b.id_mahasiswa JOIN user um ON um.id_user = m.id_user
                     WHERE u.id_ujian = ?', [$id]);
}
function uidDosen(int $idDosen): int {
    return (int)(fetchOne('SELECT id_user FROM dosen WHERE id_dosen=?', [$idDosen])['id_user'] ?? 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    $u = ujianLengkap((int)($_POST['id_ujian'] ?? 0));
    $db = getDB();
    try {
        if (!$u) throw new Exception('Ujian tidak ditemukan.');

        if ($aksi === 'jadwalkan') {
            if (!in_array($u['status'], ['disetujui_pembimbing', 'terjadwal'], true)) throw new Exception('Ujian ini belum bisa dijadwalkan.');
            $tgl   = $_POST['tgl_ujian'] ?? '';
            $jam   = $_POST['waktu_mulai'] ?? '';
            $ruang = mb_substr(trim($_POST['ruang'] ?? ''), 0, 150);
            $ketua = (int)($_POST['ketua'] ?? 0);
            $anggota = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['anggota'] ?? [])))));
            if (!DateTime::createFromFormat('Y-m-d', $tgl) || !preg_match('/^\d{2}:\d{2}/', $jam)) throw new Exception('Tanggal dan jam ujian wajib diisi.');
            if ($ruang === '') throw new Exception('Ruang atau tautan ujian wajib diisi.');
            if (!$ketua) throw new Exception('Pilih ketua penguji.');
            if ($ketua === (int)$u['id_pembimbing'] || in_array((int)$u['id_pembimbing'], $anggota, true)) throw new Exception('Dosen pembimbing tidak boleh menjadi penguji.');
            $anggota = array_diff($anggota, [$ketua]);

            $db->beginTransaction();
            $baru = $u['status'] === 'disetujui_pembimbing';
            execute('UPDATE ujian SET status="terjadwal", tgl_ujian=?, waktu_mulai=?, ruang=? WHERE id_ujian=?', [$tgl, $jam, $ruang, $u['id_ujian']]);
            execute('DELETE FROM penguji_ujian WHERE id_ujian=?', [$u['id_ujian']]);
            execute('INSERT INTO penguji_ujian (id_ujian, id_dosen, peran) VALUES (?,?,"ketua")', [$u['id_ujian'], $ketua]);
            foreach ($anggota as $a) execute('INSERT INTO penguji_ujian (id_ujian, id_dosen, peran) VALUES (?,?,"anggota")', [$u['id_ujian'], $a]);
            $db->commit();

            $waktu = tgl("$tgl $jam", 'harijam');
            $judulN = label($u['jenis']) . ($baru ? ' dijadwalkan' : ' dijadwal ulang');
            kirimNotif((int)$u['uid_mhs'], $judulN, "$waktu, ruang $ruang. Persiapkan presentasi Anda.", 'sukses', 'ujian', 'mahasiswa/ujian.php');
            kirimNotif(uidDosen((int)$u['id_pembimbing']), $judulN, "{$u['nama_mhs']} pada $waktu, ruang $ruang.", 'info', 'ujian', 'dosen/ujian.php');
            foreach (array_merge([$ketua], $anggota) as $d) {
                kirimNotif(uidDosen($d), 'Tugas menguji: ' . label($u['jenis']), "{$u['nama_mhs']} pada $waktu, ruang $ruang.", 'info', 'ujian', 'dosen/ujian.php');
            }
            setFlash('success', 'Jadwal tersimpan dan semua pihak sudah diberi tahu.');

        } elseif ($aksi === 'hasil') {
            if ($u['status'] !== 'terjadwal') throw new Exception('Hasil hanya bisa ditetapkan untuk ujian terjadwal.');
            $hasil = $_POST['hasil'] ?? '';
            if (!in_array($hasil, ['lulus', 'lulus_revisi', 'tidak_lulus'], true)) throw new Exception('Pilih hasil ujian.');
            $nilai = ($_POST['nilai_akhir'] ?? '') === '' ? null : max(0, min(100, (float)str_replace(',', '.', $_POST['nilai_akhir'])));

            $db->beginTransaction();
            execute('UPDATE ujian SET status="selesai", hasil=?, nilai_akhir=? WHERE id_ujian=?', [$hasil, $nilai, $u['id_ujian']]);
            $tutup = $u['jenis'] === 'sidang_akhir' && $hasil !== 'tidak_lulus' && !empty($_POST['tutup_bimbingan']);
            if ($tutup) {
                execute('UPDATE bimbingan SET status="selesai", tgl_selesai=CURDATE() WHERE id_bimbingan=?', [$u['id_bimbingan']]);
                // Kuota dosen dikurangi otomatis oleh trigger trg_bimbingan_update
                sinkronStatusSkripsi((int)$u['id_mahasiswa']);
            }
            $db->commit();

            $pesan = 'Hasil: ' . label($hasil) . ($nilai !== null ? ', nilai ' . number_format($nilai, 2, ',', '.') . ' (' . nilaiHuruf($nilai) . ')' : '') . '.';
            kirimNotif((int)$u['uid_mhs'], 'Hasil ' . mb_strtolower(label($u['jenis'])), $pesan . ($tutup ? ' Selamat, skripsi Anda dinyatakan selesai.' : ''),
                $hasil === 'tidak_lulus' ? 'peringatan' : 'sukses', 'ujian', 'mahasiswa/ujian.php');
            setFlash('success', 'Hasil ujian ditetapkan' . ($tutup ? ' dan bimbingan ditutup.' : '.'));
        }
    } catch (Exception $ex) {
        if ($db->inTransaction()) $db->rollBack();
        setFlash('danger', $ex->getMessage());
    }
    header('Location: ' . BASE_URL . '/admin/ujian.php?tab=' . urlencode($_POST['tab'] ?? 'jadwalkan') . '#u' . (int)($_POST['id_ujian'] ?? 0));
    exit;
}

$tabs = [
    'jadwalkan' => ['Perlu dijadwalkan', 'u.status = "disetujui_pembimbing"'],
    'terjadwal' => ['Terjadwal', 'u.status = "terjadwal"'],
    'diajukan'  => ['Menunggu pembimbing', 'u.status = "diajukan"'],
    'selesai'   => ['Selesai', 'u.status IN ("selesai","ditolak")'],
];
$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'jadwalkan';
$hitung = [];
foreach ($tabs as $k => [, $w]) $hitung[$k] = countRows("SELECT COUNT(*) FROM ujian u WHERE $w");

$daftar = fetchAll(
    'SELECT u.*, um.nama_lengkap AS nama_mhs, m.nim, j.judul_indonesia, b.id_dosen AS id_pembimbing, ud.nama_lengkap AS nama_pembimbing
     FROM ujian u JOIN bimbingan b ON b.id_bimbingan = u.id_bimbingan
     JOIN mahasiswa m ON m.id_mahasiswa = b.id_mahasiswa JOIN user um ON um.id_user = m.id_user
     JOIN judul_skripsi j ON j.id_judul = b.id_judul
     JOIN dosen d ON d.id_dosen = b.id_dosen JOIN user ud ON ud.id_user = d.id_user
     WHERE ' . $tabs[$tab][1] . ' ORDER BY ' . ($tab === 'selesai' ? 'u.tgl_ujian DESC, u.id_ujian DESC LIMIT 50' : 'COALESCE(u.tgl_ujian, u.created_at)')
);
$dosenList = fetchAll('SELECT d.id_dosen, u.nama_lengkap, d.keahlian FROM dosen d JOIN user u ON u.id_user = d.id_user WHERE u.status="aktif" ORDER BY u.nama_lengkap');

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">
  <?php $flash = getFlash(); if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div><?php endif; ?>
  <div class="page-header"><h1>Seminar & Sidang</h1><p>Jadwalkan ujian, tunjuk penguji, lalu tetapkan hasil dari nilai pembimbing dan penguji</p></div>

  <nav class="tab-nav mb-20" aria-label="Status ujian">
    <?php foreach ($tabs as $k => [$lbl]): ?>
      <a href="?tab=<?= $k ?>" class="<?= $tab === $k ? 'aktif' : '' ?>" <?= $tab === $k ? 'aria-current="page"' : '' ?>><?= e($lbl) ?> <span class="hitung-tab"><?= $hitung[$k] ?></span></a>
    <?php endforeach; ?>
  </nav>

  <?php if (!$daftar): ?>
    <div class="card empty-state" style="padding:40px;"><div class="empty-sub">Tidak ada ujian pada daftar ini.</div></div>
  <?php endif; ?>

  <?php foreach ($daftar as $u): $penguji = pengujiUjian((int)$u['id_ujian']); ?>
    <div class="card mb-20" id="u<?= (int)$u['id_ujian'] ?>">
      <div class="card-header">
        <div class="min-w-0">
          <div class="card-title"><?= e($u['nama_mhs']) ?> <span class="meta mono"><?= e($u['nim']) ?></span></div>
          <div class="meta"><?= e(label($u['jenis'])) ?> · Pembimbing: <?= e($u['nama_pembimbing']) ?> · <a class="link" href="<?= e(fileUrl($u['file_berkas'])) ?>">Berkas</a></div>
        </div>
        <span class="badge <?= badge($u['hasil'] ?: $u['status']) ?>"><span class="badge-dot"></span><?= e(label($u['hasil'] ?: $u['status'])) ?></span>
      </div>
      <div class="card-body">
        <div class="fs-13" style="margin-bottom:12px;"><?= e($u['judul_indonesia']) ?></div>

        <?php if (in_array($u['status'], ['disetujui_pembimbing', 'terjadwal'], true)):
          $ketuaNow = array_values(array_filter($penguji, fn($p) => $p['peran'] === 'ketua'))[0]['id_dosen'] ?? 0;
          $anggotaNow = array_column(array_filter($penguji, fn($p) => $p['peran'] === 'anggota'), 'id_dosen'); ?>
          <details class="lipat" <?= $u['status'] === 'disetujui_pembimbing' ? 'open' : '' ?>>
            <summary><?= $u['status'] === 'terjadwal' ? 'Ubah jadwal & penguji' : 'Jadwalkan & tunjuk penguji' ?></summary>
            <form method="POST" class="lipat-isi"><?= csrfField() ?>
              <input type="hidden" name="aksi" value="jadwalkan"><input type="hidden" name="id_ujian" value="<?= (int)$u['id_ujian'] ?>"><input type="hidden" name="tab" value="<?= $tab ?>">
              <div class="form-row">
                <div class="form-group"><label class="form-label" for="t<?= $u['id_ujian'] ?>">Tanggal</label><input class="form-input" type="date" id="t<?= $u['id_ujian'] ?>" name="tgl_ujian" required value="<?= e($u['tgl_ujian'] ?? '') ?>"></div>
                <div class="form-group"><label class="form-label" for="j<?= $u['id_ujian'] ?>">Jam</label><input class="form-input" type="time" id="j<?= $u['id_ujian'] ?>" name="waktu_mulai" required value="<?= e(substr((string)($u['waktu_mulai'] ?? '09:00'), 0, 5)) ?>"></div>
              </div>
              <div class="form-group"><label class="form-label" for="r<?= $u['id_ujian'] ?>">Ruang atau tautan</label><input class="form-input" id="r<?= $u['id_ujian'] ?>" name="ruang" required maxlength="150" value="<?= e($u['ruang'] ?? '') ?>"></div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label" for="k<?= $u['id_ujian'] ?>">Ketua penguji</label>
                  <select class="form-input" id="k<?= $u['id_ujian'] ?>" name="ketua" required>
                    <option value="">Pilih dosen…</option>
                    <?php foreach ($dosenList as $d): if ((int)$d['id_dosen'] === (int)$u['id_pembimbing']) continue; ?>
                      <option value="<?= (int)$d['id_dosen'] ?>" <?= (int)$d['id_dosen'] === (int)$ketuaNow ? 'selected' : '' ?>><?= e($d['nama_lengkap']) ?><?= $d['keahlian'] ? ' · ' . e($d['keahlian']) : '' ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label" for="a<?= $u['id_ujian'] ?>">Anggota penguji (opsional, Ctrl+klik untuk banyak)</label>
                  <select class="form-input" id="a<?= $u['id_ujian'] ?>" name="anggota[]" multiple size="3">
                    <?php foreach ($dosenList as $d): if ((int)$d['id_dosen'] === (int)$u['id_pembimbing']) continue; ?>
                      <option value="<?= (int)$d['id_dosen'] ?>" <?= in_array($d['id_dosen'], $anggotaNow) ? 'selected' : '' ?>><?= e($d['nama_lengkap']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <button class="btn btn-primary btn-sm"><?= $u['status'] === 'terjadwal' ? 'Simpan perubahan' : 'Jadwalkan' ?></button>
            </form>
          </details>
        <?php endif; ?>

        <?php if (in_array($u['status'], ['terjadwal', 'selesai'], true)): $nilai = nilaiUjian((int)$u['id_ujian']); ?>
          <div class="info-grid" style="margin:12px 0;">
            <div><div class="info-label">Jadwal</div><div class="info-value"><?= tgl($u['tgl_ujian'] . ' ' . $u['waktu_mulai'], 'harijam') ?></div></div>
            <div><div class="info-label">Ruang</div><div class="info-value"><?= e($u['ruang']) ?></div></div>
            <div><div class="info-label">Penguji</div><div class="info-value"><?php foreach ($penguji as $i => $p): ?><?= $i ? ', ' : '' ?><?= e($p['nama_lengkap']) ?> (<?= e(mb_strtolower(label($p['peran']))) ?>)<?php endforeach; ?></div></div>
          </div>
          <div class="table-wrap"><table>
            <thead><tr><th>Dosen</th><th>Peran</th><?php foreach (RUBRIK_NILAI as $lbl): ?><th class="text-center"><?= e($lbl) ?></th><?php endforeach; ?><th class="text-center">Rata-rata</th></tr></thead>
            <tbody>
              <?php foreach ($nilai['baris'] as $n): ?>
                <tr><td><?= e($n['nama_lengkap']) ?></td><td><?= e(label($n['peran'])) ?></td>
                  <?php foreach (array_keys(RUBRIK_NILAI) as $k): ?><td class="text-center"><?= (int)$n[$k] ?></td><?php endforeach; ?>
                  <td class="text-center fw-500"><?= number_format($n['rata'], 2, ',', '.') ?></td></tr>
              <?php endforeach; ?>
              <?php if (!$nilai['baris']): ?><tr><td colspan="7" class="text-center text-slate">Belum ada dosen yang mengisi nilai.</td></tr><?php endif; ?>
            </tbody>
          </table></div>

          <?php if ($u['status'] === 'terjadwal'): ?>
            <form method="POST" class="hasil-form"><?= csrfField() ?>
              <input type="hidden" name="aksi" value="hasil"><input type="hidden" name="id_ujian" value="<?= (int)$u['id_ujian'] ?>"><input type="hidden" name="tab" value="<?= $tab ?>">
              <div class="form-group"><label class="form-label" for="h<?= $u['id_ujian'] ?>">Hasil</label>
                <select class="form-input" id="h<?= $u['id_ujian'] ?>" name="hasil" required>
                  <option value="">Pilih…</option>
                  <?php foreach (['lulus', 'lulus_revisi', 'tidak_lulus'] as $h): ?><option value="<?= $h ?>"><?= e(label($h)) ?></option><?php endforeach; ?>
                </select></div>
              <div class="form-group"><label class="form-label" for="n<?= $u['id_ujian'] ?>">Nilai akhir</label>
                <input class="form-input" id="n<?= $u['id_ujian'] ?>" name="nilai_akhir" inputmode="decimal" value="<?= $nilai['rata'] !== null ? e(number_format($nilai['rata'], 2, ',', '')) : '' ?>" placeholder="0–100"></div>
              <?php if ($u['jenis'] === 'sidang_akhir'): ?>
                <label class="cek" style="align-self:end;margin-bottom:14px;"><input type="checkbox" name="tutup_bimbingan" value="1" checked> Tutup bimbingan jika lulus</label>
              <?php endif; ?>
              <button class="btn btn-gold" style="align-self:end;margin-bottom:14px;" data-confirm="Tetapkan hasil ujian? Nilai tidak bisa diubah dosen setelah ini." data-confirm-ok="Tetapkan" data-confirm-tone="primary">Tetapkan hasil</button>
            </form>
            <?php if ($u['tgl_ujian'] > date('Y-m-d')): ?><div class="meta">Ujian belum berlangsung. Dosen bisa mengisi nilai mulai <?= tgl($u['tgl_ujian'], 'lengkap') ?>.</div><?php endif; ?>
          <?php elseif ($u['nilai_akhir'] !== null): ?>
            <div class="fs-14" style="margin-top:10px;">Nilai akhir: <b><?= e(number_format((float)$u['nilai_akhir'], 2, ',', '.')) ?> (<?= nilaiHuruf((float)$u['nilai_akhir']) ?>)</b></div>
          <?php endif; ?>
        <?php elseif ($u['status'] === 'ditolak' && $u['catatan_pembimbing']): ?>
          <div class="meta">Ditolak pembimbing: <?= e($u['catatan_pembimbing']) ?></div>
        <?php elseif ($u['status'] === 'diajukan'): ?>
          <div class="meta">Didaftarkan <?= tgl($u['created_at'], 'jam') ?>. Menunggu persetujuan pembimbing.</div>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
