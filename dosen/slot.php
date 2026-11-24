<?php
// dosen/slot.php — Dosen membuka jam konsultasi yang bisa dipesan mahasiswa (D4)
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('dosen');

$pageTitle  = 'Slot Konsultasi';
$activePage = 'slot_dosen';
$eid        = entityId();

const METODE_SESI = ['tatap_muka', 'zoom', 'gmeet', 'lainnya'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    if ($aksi === 'tambah') {
        $tgl    = $_POST['tgl'] ?? '';
        $mulai  = $_POST['jam_mulai'] ?? '';
        $selesai= $_POST['jam_selesai'] ?? '';
        $kuota  = max(1, min(10, (int)($_POST['kuota'] ?? 1)));
        $metode = in_array($_POST['metode'] ?? '', METODE_SESI, true) ? $_POST['metode'] : 'tatap_muka';
        $lokasi = mb_substr(trim($_POST['lokasi_link'] ?? ''), 0, 255) ?: null;
        $ulang  = max(0, min(12, (int)($_POST['ulang_minggu'] ?? 0)));

        if (!DateTime::createFromFormat('Y-m-d', $tgl) || !preg_match('/^\d{2}:\d{2}$/', $mulai) || !preg_match('/^\d{2}:\d{2}$/', $selesai)) {
            setFlash('danger', 'Tanggal dan jam wajib diisi dengan benar.');
        } elseif ($tgl < date('Y-m-d')) {
            setFlash('danger', 'Tanggal tidak boleh di masa lalu.');
        } elseif ($selesai <= $mulai) {
            setFlash('danger', 'Jam selesai harus setelah jam mulai.');
        } else {
            $dibuat = 0; $bentrok = 0;
            for ($i = 0; $i <= $ulang; $i++) {
                $t = date('Y-m-d', strtotime("$tgl +$i week"));
                // Lewati jika bertabrakan dengan slot lain di hari yang sama
                $ada = countRows('SELECT COUNT(*) FROM slot_dosen WHERE id_dosen=? AND tgl=? AND jam_mulai < ? AND jam_selesai > ?', [$eid, $t, $selesai, $mulai]);
                if ($ada) { $bentrok++; continue; }
                execute('INSERT INTO slot_dosen (id_dosen, tgl, jam_mulai, jam_selesai, kuota, metode, lokasi_link) VALUES (?,?,?,?,?,?,?)',
                    [$eid, $t, $mulai, $selesai, $kuota, $metode, $lokasi]);
                $dibuat++;
            }
            if ($dibuat) {
                // Beri tahu mahasiswa bimbingan aktif
                foreach (fetchAll('SELECT m.id_user FROM bimbingan b JOIN mahasiswa m ON m.id_mahasiswa=b.id_mahasiswa WHERE b.id_dosen=? AND b.status="aktif"', [$eid]) as $m) {
                    kirimNotif((int)$m['id_user'], 'Slot konsultasi baru', 'Dosen pembimbing membuka ' . $dibuat . ' slot konsultasi mulai ' . tgl($tgl, 'hari') . '. Pesan slot di Jadwal Bimbingan.', 'info', 'jadwal', 'mahasiswa/bimbingan.php');
                }
            }
            setFlash($dibuat ? 'success' : 'warning', "$dibuat slot dibuat." . ($bentrok ? " $bentrok dilewati karena bertabrakan dengan slot lain." : ''));
        }
    } elseif ($aksi === 'hapus') {
        $idSlot = (int)($_POST['id_slot'] ?? 0);
        $terpakai = countRows('SELECT COUNT(*) FROM sesi_bimbingan WHERE id_slot=? AND status IN ("dijadwalkan","dikonfirmasi")', [$idSlot]);
        if ($terpakai) {
            setFlash('danger', 'Slot sudah dipesan mahasiswa. Batalkan dulu sesinya di Jadwal Konsultasi.');
        } else {
            execute('DELETE FROM slot_dosen WHERE id_slot=? AND id_dosen=?', [$idSlot, $eid]);
            setFlash('success', 'Slot dihapus.');
        }
    }
    header('Location: ' . BASE_URL . '/dosen/slot.php');
    exit;
}

$slots = fetchAll(
    'SELECT s.*, COUNT(sb.id_sesi) AS terpesan,
            GROUP_CONCAT(u.nama_lengkap ORDER BY sb.id_sesi SEPARATOR ", ") AS pemesan
     FROM slot_dosen s
     LEFT JOIN sesi_bimbingan sb ON sb.id_slot = s.id_slot AND sb.status IN ("dijadwalkan","dikonfirmasi","selesai")
     LEFT JOIN bimbingan b ON b.id_bimbingan = sb.id_bimbingan
     LEFT JOIN mahasiswa m ON m.id_mahasiswa = b.id_mahasiswa
     LEFT JOIN user u ON u.id_user = m.id_user
     WHERE s.id_dosen = ? AND s.tgl >= CURDATE()
     GROUP BY s.id_slot ORDER BY s.tgl, s.jam_mulai', [$eid]
);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">
  <?= tampilFlash() ?>
  <div class="page-header"><h1>Slot Konsultasi</h1><p>Buka jam konsultasi. Mahasiswa bimbingan memesan slot kosong dan jadwalnya langsung terkonfirmasi.</p></div>

  <div class="split" style="--side:340px;">
    <div class="card">
      <div class="card-header"><div class="card-title">Slot mendatang</div><span class="badge neutral"><?= count($slots) ?></span></div>
      <div class="card-body">
        <?php if (!$slots): ?>
          <div class="empty-state"><div class="empty-icon">🕘</div><div class="empty-title">Belum ada slot</div><div class="empty-sub">Buat slot pertama lewat form di samping. Centang ulangi mingguan untuk jam konsultasi rutin.</div></div>
        <?php else: ?>
          <div class="table-wrap"><table>
            <thead><tr><th>Waktu</th><th>Metode</th><th>Terisi</th><th>Pemesan</th><th class="text-right">Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($slots as $s): $penuh = $s['terpesan'] >= $s['kuota']; ?>
              <tr>
                <td class="nowrap"><div class="fw-500"><?= tgl($s['tgl'], 'hari') ?></div><div class="meta"><?= substr($s['jam_mulai'], 0, 5) ?>–<?= substr($s['jam_selesai'], 0, 5) ?> · <?= tglRelatif($s['tgl']) ?></div></td>
                <td><?= e(label($s['metode'])) ?><?= $s['lokasi_link'] ? '<div class="meta">' . e(mb_strimwidth($s['lokasi_link'], 0, 40, '…')) . '</div>' : '' ?></td>
                <td><span class="badge <?= $penuh ? 'success' : ($s['terpesan'] ? 'info' : 'neutral') ?>"><?= (int)$s['terpesan'] ?>/<?= (int)$s['kuota'] ?></span></td>
                <td class="fs-13"><?= e($s['pemesan'] ?: '-') ?></td>
                <td class="text-right">
                  <?php if (!$s['terpesan']): ?>
                    <form method="POST" data-confirm="Hapus slot <?= e(tgl($s['tgl'], 'hari')) ?>?" data-confirm-ok="Hapus"><?= csrfField() ?>
                      <input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id_slot" value="<?= (int)$s['id_slot'] ?>">
                      <button class="btn-icon btn-icon--danger" aria-label="Hapus slot"><?= icon('trash') ?></button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="form-card" style="height:fit-content;">
      <h2>Buka Slot</h2>
      <form method="POST"><?= csrfField() ?>
        <input type="hidden" name="aksi" value="tambah">
        <div class="form-group">
          <label class="form-label" for="s_tgl">Tanggal <span class="req">*</span></label>
          <input class="form-input" type="date" id="s_tgl" name="tgl" required min="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label" for="s_mulai">Mulai</label><input class="form-input" type="time" id="s_mulai" name="jam_mulai" value="09:00" required></div>
          <div class="form-group"><label class="form-label" for="s_selesai">Selesai</label><input class="form-input" type="time" id="s_selesai" name="jam_selesai" value="10:00" required></div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="s_kuota">Kuota mahasiswa</label>
            <input class="form-input" type="number" id="s_kuota" name="kuota" min="1" max="10" value="1">
          </div>
          <div class="form-group">
            <label class="form-label" for="s_ulang">Ulangi tiap minggu</label>
            <select class="form-input" id="s_ulang" name="ulang_minggu">
              <option value="0">Tidak</option>
              <?php foreach ([1, 3, 7, 11] as $n): ?><option value="<?= $n ?>"><?= $n + 1 ?> minggu</option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label" for="s_metode">Metode</label>
          <select class="form-input" id="s_metode" name="metode">
            <?php foreach (METODE_SESI as $m): ?><option value="<?= $m ?>"><?= e(label($m)) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="s_lokasi">Ruang atau tautan rapat</label>
          <input class="form-input" id="s_lokasi" name="lokasi_link" maxlength="255" placeholder="cth: Ruang Dosen B2 atau https://meet.google.com/…">
        </div>
        <button class="btn btn-gold btn-full" type="submit">Buka slot</button>
      </form>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
