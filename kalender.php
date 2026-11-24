<?php
// kalender.php — Kalender bulanan (D5): sesi bimbingan, seminar/sidang, slot dosen,
// dan agenda akademik (S5, dikelola admin di halaman ini).
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
requireLogin();

$pageTitle  = 'Kalender';
$activePage = 'kalender';
$r   = role();
$eid = entityId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $r === 'admin') {
    $aksi = $_POST['aksi'] ?? '';
    if ($aksi === 'tambah_agenda') {
        $nama  = trim($_POST['nama'] ?? '');
        $mulai = $_POST['tgl_mulai'] ?? '';
        $akhir = ($_POST['tgl_selesai'] ?? '') ?: null;
        if ($nama === '' || !DateTime::createFromFormat('Y-m-d', $mulai)) {
            setFlash('danger', 'Nama agenda dan tanggal mulai wajib diisi.');
        } elseif ($akhir && $akhir < $mulai) {
            setFlash('danger', 'Tanggal selesai tidak boleh sebelum tanggal mulai.');
        } else {
            execute('INSERT INTO agenda_akademik (nama, tgl_mulai, tgl_selesai, keterangan) VALUES (?,?,?,?)',
                [mb_substr($nama, 0, 150), $mulai, $akhir, mb_substr(trim($_POST['keterangan'] ?? ''), 0, 255) ?: null]);
            setFlash('success', 'Agenda ditambahkan.');
        }
    } elseif ($aksi === 'hapus_agenda') {
        execute('DELETE FROM agenda_akademik WHERE id_agenda=?', [(int)($_POST['id_agenda'] ?? 0)]);
        setFlash('success', 'Agenda dihapus.');
    }
    header('Location: ' . BASE_URL . '/kalender.php?bulan=' . urlencode($_POST['bulan'] ?? date('Y-m')));
    exit;
}

// Bulan yang ditampilkan
$bulan = preg_match('/^\d{4}-\d{2}$/', $_GET['bulan'] ?? '') ? $_GET['bulan'] : date('Y-m');
$awal  = $bulan . '-01';
$akhir = date('Y-m-t', strtotime($awal));
$sebelum = date('Y-m', strtotime("$awal -1 month"));
$sesudah = date('Y-m', strtotime("$awal +1 month"));

// Kumpulkan acara: [tanggal => [[jenis, judul, keterangan, tautan], ...]]
$acara = [];
// $lanjutan = hari ke-2 dst. dari agenda beberapa hari: tampil di grid, tidak diulang di daftar
$tambah = function (string $tgl, string $jenis, string $judul, string $ket = '', ?string $link = null, bool $lanjutan = false) use (&$acara) {
    $acara[$tgl][] = [$jenis, $judul, $ket, $link, $lanjutan];
};

// 1. Agenda akademik (bisa lebih dari satu hari)
$agenda = fetchAll('SELECT * FROM agenda_akademik WHERE tgl_mulai <= ? AND COALESCE(tgl_selesai, tgl_mulai) >= ? ORDER BY tgl_mulai', [$akhir, $awal]);
foreach ($agenda as $a) {
    $d = $pertama = max($a['tgl_mulai'], $awal);
    $sampai = min($a['tgl_selesai'] ?? $a['tgl_mulai'], $akhir);
    $rentang = $a['tgl_selesai'] && $a['tgl_selesai'] !== $a['tgl_mulai'] ? tgl($a['tgl_mulai']) . ' – ' . tgl($a['tgl_selesai']) : '';
    while ($d <= $sampai) {
        $tambah($d, 'agenda', $a['nama'], trim($rentang . ($a['keterangan'] ? ' · ' . $a['keterangan'] : ''), ' ·'), null, $d !== $pertama);
        $d = date('Y-m-d', strtotime("$d +1 day"));
    }
}

// 2. Sesi bimbingan
$sqlSesi = 'SELECT sb.tgl_sesi, sb.waktu_mulai, sb.topik, sb.status, um.nama_lengkap AS nama_mhs, ud.nama_lengkap AS nama_dsn
            FROM sesi_bimbingan sb JOIN bimbingan b ON b.id_bimbingan = sb.id_bimbingan
            JOIN mahasiswa m ON m.id_mahasiswa = b.id_mahasiswa JOIN user um ON um.id_user = m.id_user
            JOIN dosen d ON d.id_dosen = b.id_dosen JOIN user ud ON ud.id_user = d.id_user
            WHERE sb.status <> "batal" AND sb.tgl_sesi BETWEEN ? AND ?';
$p = [$awal, $akhir];
if ($r === 'mahasiswa') { $sqlSesi .= ' AND b.id_mahasiswa = ?'; $p[] = $eid; }
if ($r === 'dosen')     { $sqlSesi .= ' AND b.id_dosen = ?';     $p[] = $eid; }
foreach (fetchAll($sqlSesi . ' ORDER BY sb.waktu_mulai', $p) as $s) {
    $siapa = $r === 'mahasiswa' ? $s['nama_dsn'] : $s['nama_mhs'];
    $link = $r === 'mahasiswa' ? 'mahasiswa/bimbingan.php' : ($r === 'dosen' ? 'dosen/jadwal.php' : null);
    $tambah($s['tgl_sesi'], 'sesi', substr($s['waktu_mulai'], 0, 5) . ' Bimbingan · ' . $siapa, $s['topik'] . ' (' . label($s['status']) . ')', $link);
}

// 3. Seminar & sidang terjadwal
$sqlUjian = 'SELECT u.jenis, u.tgl_ujian, u.waktu_mulai, u.ruang, um.nama_lengkap AS nama_mhs
             FROM ujian u JOIN bimbingan b ON b.id_bimbingan = u.id_bimbingan
             JOIN mahasiswa m ON m.id_mahasiswa = b.id_mahasiswa JOIN user um ON um.id_user = m.id_user
             WHERE u.status IN ("terjadwal","selesai") AND u.tgl_ujian BETWEEN ? AND ?';
$p = [$awal, $akhir];
if ($r === 'mahasiswa') { $sqlUjian .= ' AND b.id_mahasiswa = ?'; $p[] = $eid; }
if ($r === 'dosen') {
    $sqlUjian .= ' AND (b.id_dosen = ? OR EXISTS (SELECT 1 FROM penguji_ujian pu WHERE pu.id_ujian = u.id_ujian AND pu.id_dosen = ?))';
    array_push($p, $eid, $eid);
}
$linkUjian = ['mahasiswa' => 'mahasiswa/ujian.php', 'dosen' => 'dosen/ujian.php', 'admin' => 'admin/ujian.php'][$r] ?? null;
foreach (fetchAll($sqlUjian, $p) as $u) {
    $tambah($u['tgl_ujian'], 'ujian', substr((string)$u['waktu_mulai'], 0, 5) . ' ' . label($u['jenis']) . ' · ' . $u['nama_mhs'], 'Ruang ' . ($u['ruang'] ?: '-'), $linkUjian);
}

// 4. Slot konsultasi (dosen: slot miliknya; mahasiswa: slot pembimbingnya)
if (in_array($r, ['dosen', 'mahasiswa'], true)) {
    $idDosen = $r === 'dosen' ? $eid : (int)(fetchOne('SELECT id_dosen FROM bimbingan WHERE id_mahasiswa=? AND status="aktif" LIMIT 1', [$eid])['id_dosen'] ?? 0);
    if ($idDosen) {
        foreach (fetchAll('SELECT tgl, jam_mulai, jam_selesai, kuota,
                           (SELECT COUNT(*) FROM sesi_bimbingan sb WHERE sb.id_slot = s.id_slot AND sb.status <> "batal") AS terisi
                           FROM slot_dosen s WHERE id_dosen = ? AND tgl BETWEEN ? AND ? ORDER BY jam_mulai', [$idDosen, $awal, $akhir]) as $s) {
            $tambah($s['tgl'], 'slot', substr($s['jam_mulai'], 0, 5) . '–' . substr($s['jam_selesai'], 0, 5) . ' Slot konsultasi', "Terisi {$s['terisi']}/{$s['kuota']}",
                $r === 'dosen' ? 'dosen/slot.php' : 'mahasiswa/bimbingan.php');
        }
    }
}
ksort($acara);

$agendaSemester = fetchAll('SELECT * FROM agenda_akademik WHERE COALESCE(tgl_selesai, tgl_mulai) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) ORDER BY tgl_mulai LIMIT 30');
$namaJenis = ['agenda' => 'Agenda akademik', 'sesi' => 'Bimbingan', 'ujian' => 'Seminar / sidang', 'slot' => 'Slot konsultasi'];

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/includes/topbar.php'; ?>
<div class="content">
  <?= tampilFlash() ?>
  <div class="page-header"><h1>Kalender</h1><p>Jadwal bimbingan, seminar, sidang, dan tanggal penting akademik</p></div>

  <div class="card mb-20">
    <div class="card-header kalender-nav">
      <a class="btn btn-secondary btn-sm" href="?bulan=<?= $sebelum ?>" aria-label="Bulan sebelumnya">&larr;</a>
      <div class="card-title"><?= NAMA_BULAN[(int)substr($bulan, 5, 2)] . ' ' . substr($bulan, 0, 4) ?></div>
      <div class="row-10">
        <?php if ($bulan !== date('Y-m')): ?><a class="btn btn-secondary btn-sm" href="?bulan=<?= date('Y-m') ?>">Hari ini</a><?php endif; ?>
        <a class="btn btn-secondary btn-sm" href="?bulan=<?= $sesudah ?>" aria-label="Bulan berikutnya">&rarr;</a>
      </div>
    </div>
    <div class="card-body">
      <div class="kalender-legenda">
        <?php foreach ($namaJenis as $k => $v): ?><span><i class="titik titik--<?= $k ?>"></i><?= $v ?></span><?php endforeach; ?>
      </div>
      <div class="kalender" role="grid" aria-label="Kalender <?= e($bulan) ?>">
        <?php foreach (['Sen','Sel','Rab','Kam','Jum','Sab','Min'] as $h): ?><div class="kalender-hari" role="columnheader"><?= $h ?></div><?php endforeach; ?>
        <?php
          $offset = ((int)date('N', strtotime($awal))) - 1; // Senin = 0
          for ($i = 0; $i < $offset; $i++) echo '<div class="kalender-sel kosong"></div>';
          for ($d = $awal; $d <= $akhir; $d = date('Y-m-d', strtotime("$d +1 day"))):
            $list = $acara[$d] ?? [];
        ?>
          <div class="kalender-sel <?= $d === date('Y-m-d') ? 'hari-ini' : '' ?>" role="gridcell">
            <div class="kalender-tgl"><?= (int)substr($d, 8) ?></div>
            <?php foreach (array_slice($list, 0, 3) as [$jenis, $judul, $ket, $link]): ?>
              <div class="kalender-acara acara--<?= $jenis ?>" title="<?= e($judul . ($ket ? ' — ' . $ket : '')) ?>"><?= e($judul) ?></div>
            <?php endforeach; ?>
            <?php if (count($list) > 3): ?><div class="meta-sm">+<?= count($list) - 3 ?> lagi</div><?php endif; ?>
            <?php if ($list): ?><div class="kalender-titik"><?php foreach (array_slice($list, 0, 4) as $a): ?><i class="titik titik--<?= $a[0] ?>"></i><?php endforeach; ?></div><?php endif; ?>
          </div>
        <?php endfor; ?>
      </div>
    </div>
  </div>

  <div class="<?= $r === 'admin' ? 'split' : 'grid-2' ?>" style="--side:340px;">
    <div class="card">
      <div class="card-header"><div class="card-title">Daftar acara bulan ini</div></div>
      <div class="card-body">
        <?php
          $daftarAcara = array_filter(array_map(fn($l) => array_values(array_filter($l, fn($a) => !$a[4])), $acara));
        ?>
        <?php if (!$daftarAcara): ?>
          <div class="empty-state"><div class="empty-sub">Tidak ada acara di bulan ini.</div></div>
        <?php endif; ?>
        <?php foreach ($daftarAcara as $tglA => $list): ?>
          <div class="agenda-hari">
            <div class="agenda-tgl"><?= tgl($tglA, 'hari') ?></div>
            <?php foreach ($list as [$jenis, $judul, $ket, $link]): ?>
              <div class="agenda-baris"><i class="titik titik--<?= $jenis ?>"></i>
                <div class="min-w-0"><?php if ($link): ?><a class="fw-500" href="<?= BASE_URL . '/' . $link ?>"><?= e($judul) ?></a><?php else: ?><span class="fw-500"><?= e($judul) ?></span><?php endif; ?>
                  <?php if ($ket): ?><div class="meta"><?= e($ket) ?></div><?php endif; ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div>
      <div class="card <?= $r === 'admin' ? 'mb-20' : '' ?>">
        <div class="card-header"><div class="card-title">Kalender akademik</div></div>
        <div class="card-body">
          <?php if (!$agendaSemester): ?>
            <div class="empty-state"><div class="empty-sub"><?= $r === 'admin' ? 'Tambahkan tanggal penting seperti batas pendaftaran sidang.' : 'Belum ada agenda akademik dari prodi.' ?></div></div>
          <?php endif; ?>
          <?php foreach ($agendaSemester as $a): $lewat = ($a['tgl_selesai'] ?? $a['tgl_mulai']) < date('Y-m-d'); ?>
            <div class="agenda-baris <?= $lewat ? 'lewat' : '' ?>">
              <i class="titik titik--agenda"></i>
              <div class="min-w-0" style="flex:1;">
                <div class="fw-500"><?= e($a['nama']) ?></div>
                <div class="meta"><?= tgl($a['tgl_mulai']) ?><?= $a['tgl_selesai'] && $a['tgl_selesai'] !== $a['tgl_mulai'] ? ' – ' . tgl($a['tgl_selesai']) : '' ?><?= $a['keterangan'] ? ' · ' . e($a['keterangan']) : '' ?></div>
              </div>
              <?php if ($r === 'admin'): ?>
                <form method="POST" data-confirm="Hapus agenda <?= e($a['nama']) ?>?" data-confirm-ok="Hapus"><?= csrfField() ?>
                  <input type="hidden" name="aksi" value="hapus_agenda"><input type="hidden" name="id_agenda" value="<?= (int)$a['id_agenda'] ?>"><input type="hidden" name="bulan" value="<?= e($bulan) ?>">
                  <button class="btn-icon btn-icon--danger" aria-label="Hapus agenda"><?= icon('trash', 16) ?></button>
                </form>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if ($r === 'admin'): ?>
        <div class="form-card">
          <h2>Tambah Agenda</h2>
          <form method="POST"><?= csrfField() ?>
            <input type="hidden" name="aksi" value="tambah_agenda"><input type="hidden" name="bulan" value="<?= e($bulan) ?>">
            <div class="form-group"><label class="form-label" for="a_nama">Nama agenda <span class="req">*</span></label>
              <input class="form-input" id="a_nama" name="nama" maxlength="150" required placeholder="cth: Pendaftaran sidang akhir"></div>
            <div class="form-row">
              <div class="form-group"><label class="form-label" for="a_mulai">Mulai</label><input class="form-input" type="date" id="a_mulai" name="tgl_mulai" required></div>
              <div class="form-group"><label class="form-label" for="a_akhir">Selesai</label><input class="form-input" type="date" id="a_akhir" name="tgl_selesai"></div>
            </div>
            <div class="form-group"><label class="form-label" for="a_ket">Keterangan</label><input class="form-input" id="a_ket" name="keterangan" maxlength="255"></div>
            <button class="btn btn-gold btn-full">Simpan agenda</button>
          </form>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
