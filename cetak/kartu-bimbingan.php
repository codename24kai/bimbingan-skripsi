<?php
// cetak/kartu-bimbingan.php — Kartu bimbingan / logbook siap cetak (D1).
// Simpan sebagai PDF lewat dialog cetak browser (Ctrl+P → "Simpan sebagai PDF").
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id && role() === 'mahasiswa') {
    $b = fetchOne('SELECT id_bimbingan FROM bimbingan WHERE id_mahasiswa=? ORDER BY (status="aktif") DESC, id_bimbingan DESC LIMIT 1', [entityId()]);
    $id = (int)($b['id_bimbingan'] ?? 0);
}

$data = fetchOne(
    'SELECT b.*, m.nim, m.angkatan, um.nama_lengkap AS nama_mhs, ps.nama_prodi, ps.fakultas,
            j.judul_indonesia, ud.nama_lengkap AS nama_dosen, d.nip, d.id_dosen
     FROM bimbingan b
     JOIN mahasiswa m ON m.id_mahasiswa = b.id_mahasiswa JOIN user um ON um.id_user = m.id_user
     JOIN program_studi ps ON ps.id_prodi = m.id_prodi
     JOIN judul_skripsi j ON j.id_judul = b.id_judul
     JOIN dosen d ON d.id_dosen = b.id_dosen JOIN user ud ON ud.id_user = d.id_user
     WHERE b.id_bimbingan = ?', [$id]
);

$boleh = $data && match (role()) {
    'admin'     => true,
    'mahasiswa' => (int)$data['id_mahasiswa'] === entityId(),
    'dosen'     => (int)$data['id_dosen'] === entityId(),
    default     => false,
};
if (!$boleh) {
    http_response_code(404);
    exit('Data bimbingan tidak ditemukan.');
}

$sesi = fetchAll(
    'SELECT sb.tgl_sesi, sb.waktu_mulai, sb.durasi_menit, sb.topik, sb.metode, sb.status,
            cb.isi_catatan, cb.keputusan, cb.bab_terkait
     FROM sesi_bimbingan sb LEFT JOIN catatan_bimbingan cb ON cb.id_sesi = sb.id_sesi
     WHERE sb.id_bimbingan = ? AND sb.status = "selesai"
     ORDER BY sb.tgl_sesi, sb.waktu_mulai', [$id]
);
$dok = fetchAll(
    'SELECT jenis_bab, MAX(versi) AS versi, MAX(status="disetujui") AS ok FROM dokumen WHERE id_mahasiswa=? GROUP BY jenis_bab ORDER BY jenis_bab',
    [$data['id_mahasiswa']]
);
$institusi = pengaturan('nama_institusi', 'Universitas');
$ttdTanggal = tgl(date('Y-m-d'), 'panjang');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kartu Bimbingan <?= e($data['nim']) ?> — <?= e($data['nama_mhs']) ?></title>
<style>
  @page { size: A4; margin: 18mm 16mm; }
  * { box-sizing: border-box; }
  body { font-family: "Times New Roman", Times, serif; font-size: 12pt; color: #000; margin: 0; background: #eee; }
  .kertas { background: #fff; max-width: 210mm; margin: 16px auto; padding: 18mm 16mm; box-shadow: 0 2px 12px rgba(0,0,0,.15); }
  .kop { text-align: center; border-bottom: 3px double #000; padding-bottom: 8px; margin-bottom: 14px; }
  .kop h1 { font-size: 15pt; margin: 0; text-transform: uppercase; }
  .kop div { font-size: 11pt; }
  h2 { text-align: center; font-size: 13pt; text-transform: uppercase; margin: 10px 0 14px; text-decoration: underline; }
  table { border-collapse: collapse; width: 100%; }
  .identitas td { padding: 2px 4px; vertical-align: top; }
  .identitas td:first-child { width: 150px; }
  .log { margin-top: 12px; font-size: 11pt; }
  .log th, .log td { border: 1px solid #000; padding: 5px 6px; vertical-align: top; }
  .log th { background: #f0f0f0; }
  .log td.c { text-align: center; }
  .paraf { width: 60px; }
  .ttd { margin-top: 28px; display: flex; justify-content: flex-end; }
  .ttd > div { width: 260px; text-align: center; }
  .ttd .nama { margin-top: 70px; font-weight: bold; text-decoration: underline; }
  .ringkas { margin-top: 10px; font-size: 11pt; }
  .alat { max-width: 210mm; margin: 16px auto 0; display: flex; gap: 8px; justify-content: flex-end; font-family: sans-serif; }
  .alat button, .alat a { padding: 8px 14px; border-radius: 6px; border: 1px solid #0B1F3A; background: #0B1F3A; color: #fff; font-size: 14px; cursor: pointer; text-decoration: none; }
  .alat a { background: #fff; color: #0B1F3A; }
  @media print {
    body { background: #fff; }
    .kertas { box-shadow: none; margin: 0; padding: 0; max-width: none; }
    .alat { display: none; }
  }
</style>
</head>
<body>
<div class="alat">
  <a href="javascript:history.back()">Kembali</a>
  <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
</div>
<div class="kertas">
  <div class="kop">
    <h1><?= e($institusi) ?></h1>
    <div><?= e($data['fakultas']) ?> · Program Studi <?= e($data['nama_prodi']) ?></div>
  </div>
  <h2>Kartu Bimbingan Skripsi</h2>

  <table class="identitas">
    <tr><td>Nama</td><td>: <?= e($data['nama_mhs']) ?></td></tr>
    <tr><td>NIM / Angkatan</td><td>: <?= e($data['nim']) ?> / <?= e($data['angkatan']) ?></td></tr>
    <tr><td>Judul skripsi</td><td>: <?= e($data['judul_indonesia']) ?></td></tr>
    <tr><td>Dosen pembimbing</td><td>: <?= e($data['nama_dosen']) ?> (NIP <?= e($data['nip']) ?>)</td></tr>
    <tr><td>No. SK</td><td>: <?= e($data['no_sk'] ?: '-') ?>, semester <?= e($data['semester']) ?>, mulai <?= tgl($data['tgl_mulai'], 'panjang') ?></td></tr>
  </table>

  <table class="log">
    <thead><tr><th style="width:32px;">No</th><th style="width:105px;">Tanggal</th><th>Topik / Bab</th><th>Catatan pembimbing</th><th style="width:85px;">Keputusan</th><th class="paraf">Paraf</th></tr></thead>
    <tbody>
    <?php foreach ($sesi as $i => $s): ?>
      <tr>
        <td class="c"><?= $i + 1 ?></td>
        <td><?= tgl($s['tgl_sesi']) ?></td>
        <td><?= e($s['topik']) ?><?= $s['bab_terkait'] ? '<br><i>' . e(label($s['bab_terkait'])) . '</i>' : '' ?></td>
        <td><?= nl2br(e($s['isi_catatan'] ?: '-')) ?></td>
        <td class="c"><?= e($s['keputusan'] ? label($s['keputusan']) : '-') ?></td>
        <td></td>
      </tr>
    <?php endforeach; ?>
    <?php for ($i = count($sesi); $i < max(8, count($sesi)); $i++): ?>
      <tr><td class="c"><?= $i + 1 ?></td><td>&nbsp;</td><td></td><td></td><td></td><td></td></tr>
    <?php endfor; ?>
    </tbody>
  </table>

  <div class="ringkas">
    Jumlah bimbingan selesai: <b><?= count($sesi) ?> kali</b>.
    <?php if ($dok): ?>
      Status dokumen: <?php foreach ($dok as $k => $d): ?><?= $k ? ', ' : '' ?><?= e(label($d['jenis_bab'])) ?> v<?= (int)$d['versi'] ?> (<?= $d['ok'] ? 'disetujui' : 'proses' ?>)<?php endforeach; ?>.
    <?php endif; ?>
  </div>

  <div class="ttd">
    <div>
      <?= e($ttdTanggal) ?><br>Dosen Pembimbing,
      <div class="nama"><?= e($data['nama_dosen']) ?></div>
      NIP <?= e($data['nip']) ?>
    </div>
  </div>
</div>
</body>
</html>
