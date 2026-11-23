<?php
// admin/impor.php — Impor akun mahasiswa/dosen dari CSV (D12).
// Excel: File → Save As → "CSV UTF-8" (pemisah koma atau titik koma keduanya diterima).
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('admin');

$pageTitle  = 'Impor Data';
$activePage = 'impor';

const KOLOM_IMPOR = [
    'mahasiswa' => ['nama_lengkap', 'email', 'nim', 'angkatan', 'kode_prodi', 'no_telepon'],
    'dosen'     => ['nama_lengkap', 'email', 'nip', 'kode_prodi', 'jabatan_fungsional', 'keahlian', 'kuota_bimbingan'],
];
const WAJIB_IMPOR = [
    'mahasiswa' => ['nama_lengkap', 'email', 'nim', 'angkatan', 'kode_prodi'],
    'dosen'     => ['nama_lengkap', 'email', 'nip', 'kode_prodi'],
];
const JABATAN = ['Tenaga Pengajar', 'Asisten Ahli', 'Lektor', 'Lektor Kepala', 'Guru Besar'];
const KEAHLIAN = ['Rekayasa Perangkat Lunak & Web', 'Pemrograman Mobile', 'Kecerdasan Buatan & Machine Learning', 'Data Science & Analitik',
                  'Internet of Things (IoT)', 'Keamanan Siber & Jaringan', 'Manajemen Sistem Informasi'];

// Unduh contoh CSV
if (isset($_GET['contoh']) && isset(KOLOM_IMPOR[$_GET['contoh']])) {
    $jenis = $_GET['contoh'];
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="contoh_impor_' . $jenis . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
    fputcsv($out, KOLOM_IMPOR[$jenis], ';');
    fputcsv($out, $jenis === 'mahasiswa'
        ? ['Budi Santoso', 'budi@univ.ac.id', '231011400001', '2023', 'TIF', '081234567890']
        : ['Dr. Siti Aminah, M.Kom.', 'siti@univ.ac.id', '198001012010012001', 'SIF', 'Lektor', 'Data Science & Analitik', '10'], ';');
    fclose($out);
    exit;
}

/** Baca CSV jadi array baris asosiatif. Mengembalikan [baris, galat]. */
function bacaCsv(string $file, string $jenis): array {
    $isi = file_get_contents($file);
    $isi = preg_replace('/^\xEF\xBB\xBF/', '', (string)$isi);
    $barisPertama = strtok($isi, "\n");
    $pemisah = substr_count($barisPertama, ';') > substr_count($barisPertama, ',') ? ';' : ',';
    $fh = fopen('php://temp', 'r+');
    fwrite($fh, $isi); rewind($fh);
    $header = array_map(fn($h) => strtolower(trim((string)$h)), fgetcsv($fh, 0, $pemisah) ?: []);
    $kurang = array_diff(WAJIB_IMPOR[$jenis], $header);
    if ($kurang) return [[], 'Kolom wajib tidak ada: ' . implode(', ', $kurang) . '. Gunakan file contoh.'];
    $rows = [];
    while (($r = fgetcsv($fh, 0, $pemisah)) !== false) {
        if (count(array_filter($r, fn($v) => trim((string)$v) !== '')) === 0) continue;
        $rows[] = array_combine($header, array_pad(array_map(fn($v) => trim((string)$v), $r), count($header), ''));
        if (count($rows) > 1000) return [[], 'Maksimal 1.000 baris per impor.'];
    }
    return [$rows, null];
}

/** Validasi satu baris. Mengembalikan daftar masalah (kosong = siap diimpor). */
function cekBaris(array $r, string $jenis, array $prodi, array &$sudah): array {
    $masalah = [];
    foreach (WAJIB_IMPOR[$jenis] as $k) if (($r[$k] ?? '') === '') $masalah[] = "$k kosong";
    $email = strtolower($r['email'] ?? '');
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $masalah[] = 'email tidak valid';
    if ($email && (isset($sudah['email'][$email]) || countRows('SELECT COUNT(*) FROM user WHERE email=?', [$email]))) $masalah[] = 'email sudah terdaftar';
    if (!isset($prodi[strtoupper($r['kode_prodi'] ?? '')])) $masalah[] = 'kode_prodi tidak dikenal';
    if ($jenis === 'mahasiswa') {
        $nim = $r['nim'] ?? '';
        if ($nim && !preg_match('/^[0-9A-Za-z.\-]{5,20}$/', $nim)) $masalah[] = 'format NIM';
        if ($nim && (isset($sudah['no'][$nim]) || countRows('SELECT COUNT(*) FROM mahasiswa WHERE nim=?', [$nim]))) $masalah[] = 'NIM sudah terdaftar';
        if (($r['angkatan'] ?? '') !== '' && !preg_match('/^(19|20)\d{2}$/', $r['angkatan'])) $masalah[] = 'angkatan harus 4 digit tahun';
    } else {
        $nip = $r['nip'] ?? '';
        if ($nip && (isset($sudah['no'][$nip]) || countRows('SELECT COUNT(*) FROM dosen WHERE nip=?', [$nip]))) $masalah[] = 'NIP sudah terdaftar';
        if (($r['jabatan_fungsional'] ?? '') !== '' && !in_array($r['jabatan_fungsional'], JABATAN, true)) $masalah[] = 'jabatan_fungsional tidak dikenal';
        if (($r['keahlian'] ?? '') !== '' && !in_array($r['keahlian'], KEAHLIAN, true)) $masalah[] = 'keahlian tidak dikenal';
    }
    if (!$masalah) {
        $sudah['email'][$email] = true;
        $sudah['no'][$r[$jenis === 'mahasiswa' ? 'nim' : 'nip']] = true;
    }
    return $masalah;
}

$hasil = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jenis = $_POST['jenis'] ?? '';
    $simpan = ($_POST['aksi'] ?? '') === 'impor';
    if (!isset(KOLOM_IMPOR[$jenis])) {
        setFlash('danger', 'Pilih jenis data.');
    } elseif (($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        setFlash('danger', 'Pilih file CSV terlebih dahulu.');
    } elseif (strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION)) !== 'csv') {
        setFlash('danger', 'File harus berformat .csv. Di Excel pilih Save As → CSV UTF-8.');
    } else {
        [$rows, $galat] = bacaCsv($_FILES['file']['tmp_name'], $jenis);
        if ($galat) {
            setFlash('danger', $galat);
        } else {
            $prodi = array_column(fetchAll('SELECT id_prodi, UPPER(kode_prodi) AS kode FROM program_studi'), 'id_prodi', 'kode');
            $sudah = ['email' => [], 'no' => []];
            $default = (int)pengaturan('default_kuota_dosen', '10');
            $maks = (int)pengaturan('maksimal_kuota_dosen', '20');
            $hasil = ['jenis' => $jenis, 'simpan' => $simpan, 'baris' => [], 'ok' => 0, 'gagal' => 0];
            $db = getDB();
            foreach ($rows as $i => $r) {
                $masalah = cekBaris($r, $jenis, $prodi, $sudah);
                if (!$masalah && $simpan) {
                    try {
                        $db->beginTransaction();
                        $idUser = execute('INSERT INTO user (nama_lengkap,email,password_hash,role,status) VALUES (?,?,?,?,"aktif")',
                            [mb_substr($r['nama_lengkap'], 0, 100), strtolower($r['email']), hashPassword($jenis === 'mahasiswa' ? 'mhs123' : 'dosen123'), $jenis]);
                        $idProdi = $prodi[strtoupper($r['kode_prodi'])];
                        if ($jenis === 'mahasiswa') {
                            execute('INSERT INTO mahasiswa (id_user,id_prodi,nim,angkatan,status_skripsi,no_telepon) VALUES (?,?,?,?,"belum",?)',
                                [$idUser, $idProdi, $r['nim'], $r['angkatan'], mb_substr($r['no_telepon'] ?? '', 0, 15) ?: null]);
                        } else {
                            $kuota = ($r['kuota_bimbingan'] ?? '') === '' ? $default : max(1, min($maks, (int)$r['kuota_bimbingan']));
                            execute('INSERT INTO dosen (id_user,id_prodi,nip,jabatan_fungsional,keahlian,kuota_bimbingan) VALUES (?,?,?,?,?,?)',
                                [$idUser, $idProdi, $r['nip'], $r['jabatan_fungsional'] ?: null, $r['keahlian'] ?: null, $kuota]);
                        }
                        $db->commit();
                    } catch (Exception $ex) {
                        $db->rollBack();
                        $masalah[] = 'gagal disimpan';
                    }
                }
                $hasil['baris'][] = ['no' => $i + 2, 'data' => $r, 'masalah' => $masalah];
                $masalah ? $hasil['gagal']++ : $hasil['ok']++;
            }
            if ($simpan) {
                setFlash($hasil['ok'] ? 'success' : 'warning', $hasil['ok'] . ' akun ' . $jenis . ' berhasil diimpor' . ($hasil['gagal'] ? ', ' . $hasil['gagal'] . ' baris dilewati.' : '.')
                    . ' Kata sandi awal: ' . ($jenis === 'mahasiswa' ? 'mhs123' : 'dosen123') . ' (wajib diganti saat login).');
            }
        }
    }
    if (!$hasil || $simpan) {
        $_SESSION['hasil_impor'] = $hasil;
        header('Location: ' . BASE_URL . '/admin/impor.php');
        exit;
    }
}
if (!$hasil && isset($_SESSION['hasil_impor'])) {
    $hasil = $_SESSION['hasil_impor'];
    unset($_SESSION['hasil_impor']);
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">
  <?php $flash = getFlash(); if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div><?php endif; ?>
  <div class="page-header"><h1>Impor Data</h1><p>Tambahkan banyak akun mahasiswa atau dosen sekaligus dari file CSV</p></div>

  <div class="split" style="--side:360px;">
    <div>
      <?php if ($hasil): ?>
        <div class="card">
          <div class="card-header">
            <div class="card-title"><?= $hasil['simpan'] ? 'Hasil impor' : 'Pratinjau' ?> <?= e(label($hasil['jenis'])) ?></div>
            <span class="badge success"><?= $hasil['ok'] ?> <?= $hasil['simpan'] ? 'masuk' : 'siap' ?></span>
            <?php if ($hasil['gagal']): ?><span class="badge danger"><?= $hasil['gagal'] ?> bermasalah</span><?php endif; ?>
          </div>
          <div class="card-body">
            <?php if (!$hasil['simpan']): ?>
              <div class="alert alert-static info mb-20">Belum ada data yang disimpan. Periksa baris bermasalah, lalu unggah ulang file yang sama dan pilih <b>Impor sekarang</b>. Baris bermasalah akan dilewati.</div>
            <?php endif; ?>
            <div class="table-wrap"><table>
              <thead><tr><th>Baris</th><th>Nama</th><th>Email</th><th><?= $hasil['jenis'] === 'mahasiswa' ? 'NIM' : 'NIP' ?></th><th>Prodi</th><th>Keterangan</th></tr></thead>
              <tbody>
              <?php foreach ($hasil['baris'] as $b): ?>
                <tr class="<?= $b['masalah'] ? 'baris-galat' : '' ?>">
                  <td class="mono fs-12"><?= (int)$b['no'] ?></td>
                  <td><?= e($b['data']['nama_lengkap'] ?? '') ?></td>
                  <td class="fs-13"><?= e($b['data']['email'] ?? '') ?></td>
                  <td class="mono fs-12"><?= e($b['data'][$hasil['jenis'] === 'mahasiswa' ? 'nim' : 'nip'] ?? '') ?></td>
                  <td><?= e($b['data']['kode_prodi'] ?? '') ?></td>
                  <td class="fs-13"><?= $b['masalah'] ? '<span style="color:var(--danger-text)">' . e(implode(', ', $b['masalah'])) . '</span>' : '<span style="color:var(--success-text)">OK</span>' ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table></div>
          </div>
        </div>
      <?php else: ?>
        <div class="card">
          <div class="card-header"><div class="card-title">Cara impor</div></div>
          <div class="card-body">
            <ol class="langkah">
              <li class="langkah-item todo"><span class="langkah-no">1</span><div><div class="langkah-title">Unduh file contoh</div><div class="langkah-sub">
                <a class="link" href="?contoh=mahasiswa">contoh_impor_mahasiswa.csv</a> atau <a class="link" href="?contoh=dosen">contoh_impor_dosen.csv</a></div></div></li>
              <li class="langkah-item todo"><span class="langkah-no">2</span><div><div class="langkah-title">Isi di Excel</div><div class="langkah-sub">Jangan ubah baris judul kolom. Kode prodi: <?= e(implode(', ', array_column(fetchAll('SELECT kode_prodi FROM program_studi ORDER BY kode_prodi'), 'kode_prodi'))) ?>.</div></div></li>
              <li class="langkah-item todo"><span class="langkah-no">3</span><div><div class="langkah-title">Simpan sebagai CSV UTF-8</div><div class="langkah-sub">File → Save As → CSV UTF-8 (Comma delimited).</div></div></li>
              <li class="langkah-item todo"><span class="langkah-no">4</span><div><div class="langkah-title">Cek dulu, lalu impor</div><div class="langkah-sub">Pratinjau menunjukkan baris yang bermasalah tanpa menyimpan apa pun.</div></div></li>
            </ol>
            <p class="fs-13 text-slate" style="margin-top:10px;">Isian dosen yang valid: jabatan <?= e(implode(', ', JABATAN)) ?>. Keahlian: <?= e(implode(', ', KEAHLIAN)) ?>.</p>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="form-card" style="height:fit-content;">
      <h2>Unggah CSV</h2>
      <form method="POST" enctype="multipart/form-data"><?= csrfField() ?>
        <div class="form-group">
          <label class="form-label" for="i_jenis">Jenis data</label>
          <select class="form-input" id="i_jenis" name="jenis" required>
            <option value="mahasiswa" <?= ($hasil['jenis'] ?? '') === 'mahasiswa' ? 'selected' : '' ?>>Mahasiswa</option>
            <option value="dosen" <?= ($hasil['jenis'] ?? '') === 'dosen' ? 'selected' : '' ?>>Dosen</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label" for="f_file">File CSV</label>
          <?= uploadZone('file', 'Klik atau seret file .csv', '.csv', 'Maksimal 1.000 baris', true) ?>
        </div>
        <div class="row-10" style="flex-wrap:wrap;">
          <button class="btn btn-secondary" name="aksi" value="cek">Cek dulu</button>
          <button class="btn btn-gold" name="aksi" value="impor" data-confirm="Impor semua baris yang valid? Akun langsung aktif dengan kata sandi bawaan." data-confirm-ok="Impor" data-confirm-tone="primary">Impor sekarang</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
