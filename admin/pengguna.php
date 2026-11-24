<?php
// admin/pengguna.php — Kelola akun mahasiswa dan dosen dalam satu halaman.
// Tab dipilih lewat ?peran=mahasiswa|dosen. Menggantikan admin/mahasiswa.php
// dan admin/dosen.php (keduanya kini hanya mengalihkan ke sini).
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('admin');

$peran = ($_GET['peran'] ?? $_POST['peran'] ?? 'mahasiswa') === 'dosen' ? 'dosen' : 'mahasiswa';
$q     = trim((string)($_GET['q'] ?? ''));

$pageTitle  = 'Kelola Pengguna';
$activePage = 'kelola_pengguna';

$maksKuota = max(1, (int)pengaturan('maksimal_kuota_dosen', '20'));
$prodiIds  = array_map('intval', array_column(fetchAll('SELECT id_prodi FROM program_studi'), 'id_prodi'));

/**
 * Validasi isian form tambah/edit. Mengembalikan [data bersih, pesan galat|null].
 * $idUser diisi saat edit agar pengecekan duplikat mengabaikan akun itu sendiri.
 */
function validasiPengguna(string $peran, array $in, array $prodiIds, int $maksKuota, int $idUser = 0): array {
    $d = [
        'nama'     => trim((string)($in['nama_lengkap'] ?? '')),
        'email'    => strtolower(trim((string)($in['email'] ?? ''))),
        'id_prodi' => (int)($in['id_prodi'] ?? 0),
    ];
    if ($d['nama'] === '' || mb_strlen($d['nama']) > 100) return [$d, 'Nama lengkap wajib diisi (maksimal 100 karakter).'];
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL) || strlen($d['email']) > 100) return [$d, 'Format email tidak valid.'];
    if (countRows('SELECT COUNT(*) FROM user WHERE email=? AND id_user<>?', [$d['email'], $idUser])) return [$d, 'Email ' . $d['email'] . ' sudah dipakai akun lain.'];
    if (!in_array($d['id_prodi'], $prodiIds, true)) return [$d, 'Program studi tidak valid.'];

    if ($peran === 'mahasiswa') {
        $d['nim'] = trim((string)($in['nim'] ?? ''));
        $d['angkatan'] = (int)($in['angkatan'] ?? 0);
        if (!preg_match('/^[0-9A-Za-z.\-]{5,20}$/', $d['nim']) || !preg_match('/\d/', $d['nim'])) return [$d, 'NIM harus 5–20 karakter dan mengandung angka.'];
        if (countRows('SELECT COUNT(*) FROM mahasiswa WHERE nim=? AND id_user<>?', [$d['nim'], $idUser])) return [$d, 'NIM ' . $d['nim'] . ' sudah terdaftar.'];
        if ($d['angkatan'] < 1990 || $d['angkatan'] > (int)date('Y') + 1) return [$d, 'Angkatan harus tahun antara 1990 dan ' . ((int)date('Y') + 1) . '.'];
    } else {
        $d['nip'] = trim((string)($in['nip'] ?? ''));
        $d['jabatan'] = (string)($in['jabatan'] ?? '');
        $d['keahlian'] = (string)($in['keahlian'] ?? '');
        $d['kuota'] = (int)($in['kuota'] ?? 0);
        if (!preg_match('/^[0-9A-Za-z.\-]{5,20}$/', $d['nip']) || !preg_match('/\d/', $d['nip'])) return [$d, 'NIP/NIDN harus 5–20 karakter dan mengandung angka.'];
        if (countRows('SELECT COUNT(*) FROM dosen WHERE nip=? AND id_user<>?', [$d['nip'], $idUser])) return [$d, 'NIP ' . $d['nip'] . ' sudah terdaftar.'];
        if (!in_array($d['jabatan'], JABATAN_DOSEN, true)) return [$d, 'Pilih jabatan fungsional.'];
        if (!in_array($d['keahlian'], KEAHLIAN_DOSEN, true)) return [$d, 'Pilih bidang keahlian.'];
        if ($d['kuota'] < 1 || $d['kuota'] > $maksKuota) return [$d, 'Kuota bimbingan harus 1–' . $maksKuota . ' (batas di Pengaturan).'];
        if ($idUser) {
            $terisi = (int)(fetchOne('SELECT terisi FROM dosen WHERE id_user=?', [$idUser])['terisi'] ?? 0);
            if ($d['kuota'] < $terisi) return [$d, 'Kuota tidak boleh lebih kecil dari jumlah mahasiswa yang sedang dibimbing (' . $terisi . ').'];
        }
    }
    return [$d, null];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi   = $_POST['aksi'] ?? '';
    $idUser = (int)($_POST['id_user'] ?? 0);
    $label  = $peran === 'mahasiswa' ? 'Mahasiswa' : 'Dosen';
    // Akun yang diubah harus benar-benar ber-peran sesuai tab
    $akun = $idUser ? fetchOne('SELECT id_user, nama_lengkap, email, status FROM user WHERE id_user=? AND role=?', [$idUser, $peran]) : null;
    $db = getDB();

    try {
        if ($aksi === 'tambah') {
            [$d, $galat] = validasiPengguna($peran, $_POST, $prodiIds, $maksKuota);
            if ($galat) throw new Exception($galat);
            $sandi = passwordAwal($peran === 'mahasiswa' ? $d['nim'] : $d['nip']);
            $db->beginTransaction();
            $baru = execute('INSERT INTO user (nama_lengkap,email,password_hash,role,status) VALUES (?,?,?,?,"aktif")',
                [$d['nama'], $d['email'], hashPassword($sandi), $peran]);
            if ($peran === 'mahasiswa') {
                execute('INSERT INTO mahasiswa (id_user,id_prodi,nim,angkatan,status_skripsi) VALUES (?,?,?,?,"belum")',
                    [$baru, $d['id_prodi'], $d['nim'], $d['angkatan']]);
            } else {
                execute('INSERT INTO dosen (id_user,id_prodi,nip,jabatan_fungsional,keahlian,kuota_bimbingan) VALUES (?,?,?,?,?,?)',
                    [$baru, $d['id_prodi'], $d['nip'], $d['jabatan'], $d['keahlian'], $d['kuota']]);
            }
            $db->commit();
            setKredensial($label . ' berhasil ditambahkan', $d['nama'], $d['email'], $sandi);

        } elseif ($aksi === 'edit') {
            if (!$akun) throw new Exception($label . ' tidak ditemukan.');
            [$d, $galat] = validasiPengguna($peran, $_POST, $prodiIds, $maksKuota, $idUser);
            if ($galat) throw new Exception($galat);
            $db->beginTransaction();
            execute('UPDATE user SET nama_lengkap=?, email=? WHERE id_user=?', [$d['nama'], $d['email'], $idUser]);
            if ($peran === 'mahasiswa') {
                execute('UPDATE mahasiswa SET id_prodi=?, nim=?, angkatan=? WHERE id_user=?', [$d['id_prodi'], $d['nim'], $d['angkatan'], $idUser]);
            } else {
                execute('UPDATE dosen SET id_prodi=?, nip=?, jabatan_fungsional=?, keahlian=?, kuota_bimbingan=? WHERE id_user=?',
                    [$d['id_prodi'], $d['nip'], $d['jabatan'], $d['keahlian'], $d['kuota'], $idUser]);
            }
            $db->commit();
            setFlash('success', 'Data ' . strtolower($label) . ' ' . $d['nama'] . ' berhasil diperbarui.');

        } elseif ($aksi === 'reset_password') {
            if (!$akun) throw new Exception($label . ' tidak ditemukan.');
            $sandi = resetUserPassword($idUser, $peran);
            // Permintaan lupa sandi yang masih menunggu ikut dianggap selesai
            execute('UPDATE permintaan_reset SET status="selesai", diproses_at=NOW() WHERE id_user=? AND status="menunggu"', [$idUser]);
            setKredensial('Kata sandi berhasil direset', $akun['nama_lengkap'], $akun['email'], (string)$sandi);

        } elseif ($aksi === 'toggle_status') {
            if (!$akun) throw new Exception($label . ' tidak ditemukan.');
            $statusBaru = $akun['status'] === 'aktif' ? 'nonaktif' : 'aktif';
            execute('UPDATE user SET status=? WHERE id_user=?', [$statusBaru, $idUser]);
            setFlash('success', $akun['nama_lengkap'] . ' sekarang ' . ($statusBaru === 'aktif' ? 'aktif dan bisa login.' : 'nonaktif dan tidak bisa login.'));

        } elseif ($aksi === 'hapus') {
            if (!$akun) throw new Exception($label . ' tidak ditemukan.');
            if ($peran === 'dosen') {
                $aktif = countRows('SELECT COUNT(*) FROM bimbingan b JOIN dosen d ON d.id_dosen=b.id_dosen WHERE d.id_user=? AND b.status="aktif"', [$idUser]);
                if ($aktif) throw new Exception($akun['nama_lengkap'] . ' masih membimbing ' . $aktif . ' mahasiswa aktif. Hentikan atau selesaikan relasinya di Relasi & SK, atau nonaktifkan akunnya.');
            }
            $db->beginTransaction();
            if ($peran === 'mahasiswa') {
                // Hentikan dulu bimbingan aktif agar trigger mengurangi kuota terisi dosen
                execute('UPDATE bimbingan b JOIN mahasiswa m ON m.id_mahasiswa=b.id_mahasiswa SET b.status="dihentikan", b.tgl_selesai=CURDATE()
                         WHERE m.id_user=? AND b.status="aktif"', [$idUser]);
            }
            execute('DELETE FROM user WHERE id_user=?', [$idUser]);
            $db->commit();
            setFlash('success', $label . ' ' . $akun['nama_lengkap'] . ' berhasil dihapus.');
        }
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        setFlash('danger', pesanGalat($e, 'Gagal'));
        // Simpan isian agar form tidak perlu diketik ulang
        if (in_array($aksi, ['tambah', 'edit'], true)) {
            $_SESSION['isian_pengguna'] = ['aksi' => $aksi, 'data' => array_diff_key($_POST, ['csrf_token' => 1])];
        }
    }
    header('Location: ' . BASE_URL . '/admin/pengguna.php?peran=' . $peran . ($q !== '' ? '&q=' . urlencode($q) : ''));
    exit;
}

$isian = $_SESSION['isian_pengguna'] ?? null;
unset($_SESSION['isian_pengguna']);

$jumlah = [
    'mahasiswa' => countRows('SELECT COUNT(*) FROM mahasiswa'),
    'dosen'     => countRows('SELECT COUNT(*) FROM dosen'),
];

$params = [];
if ($peran === 'mahasiswa') {
    $sql = 'SELECT m.*, u.nama_lengkap, u.email, u.status AS status_user, u.last_login, ps.nama_prodi, u.id_user,
                   (SELECT ud.nama_lengkap FROM bimbingan b JOIN dosen d ON d.id_dosen=b.id_dosen JOIN user ud ON ud.id_user=d.id_user WHERE b.id_mahasiswa=m.id_mahasiswa AND b.status="aktif" LIMIT 1) AS nama_dosen,
                   (SELECT b.id_bimbingan FROM bimbingan b WHERE b.id_mahasiswa=m.id_mahasiswa AND b.status IN ("aktif","selesai") ORDER BY (b.status="aktif") DESC, b.id_bimbingan DESC LIMIT 1) AS id_bimbingan
            FROM mahasiswa m JOIN user u ON u.id_user=m.id_user JOIN program_studi ps ON ps.id_prodi=m.id_prodi';
    if ($q !== '') { $sql .= ' WHERE (u.nama_lengkap LIKE ? OR m.nim LIKE ? OR u.email LIKE ?)'; $params = ["%$q%", "%$q%", "%$q%"]; }
} else {
    $sql = 'SELECT d.*, u.nama_lengkap, u.email, u.status AS status_user, u.last_login, ps.nama_prodi, u.id_user
            FROM dosen d JOIN user u ON u.id_user=d.id_user JOIN program_studi ps ON ps.id_prodi=d.id_prodi';
    if ($q !== '') { $sql .= ' WHERE (u.nama_lengkap LIKE ? OR d.nip LIKE ? OR u.email LIKE ?)'; $params = ["%$q%", "%$q%", "%$q%"]; }
}
$list  = fetchAll($sql . ' ORDER BY u.nama_lengkap', $params);
$prodi = fetchAll('SELECT * FROM program_studi ORDER BY nama_prodi');
$labelPeran = $peran === 'mahasiswa' ? 'Mahasiswa' : 'Dosen';
$noLabel    = $peran === 'mahasiswa' ? 'NIM' : 'NIP';

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">
  <?= tampilFlash() ?>
  <div class="page-header"><h1>Kelola Pengguna</h1><p>Akun mahasiswa dan dosen: tambah, ubah, reset kata sandi, aktif/nonaktif</p></div>

  <nav class="tab-nav mb-20" aria-label="Jenis pengguna">
    <?php foreach (['mahasiswa' => 'Mahasiswa', 'dosen' => 'Dosen'] as $k => $lbl): ?>
      <a href="?peran=<?= $k ?>" class="<?= $peran === $k ? 'aktif' : '' ?>" <?= $peran === $k ? 'aria-current="page"' : '' ?>><?= $lbl ?> <span class="hitung-tab"><?= $jumlah[$k] ?></span></a>
    <?php endforeach; ?>
  </nav>

  <div class="search-bar">
    <form method="GET" style="display:contents;">
      <input type="hidden" name="peran" value="<?= $peran ?>">
      <div class="search-input-wrap"><span class="search-icon">🔍</span><input name="q" aria-label="Cari <?= strtolower($labelPeran) ?>" placeholder="Cari nama, <?= $noLabel ?>, atau email..." value="<?= e($q) ?>"></div>
      <button type="submit" class="btn btn-secondary">Cari</button>
    </form>
    <a class="btn btn-secondary" href="<?= BASE_URL ?>/admin/impor.php"><?= icon('import', 16) ?> Impor CSV</a>
    <button type="button" class="btn btn-primary" onclick="bukaModal('modalTambah')">+ Tambah <?= $labelPeran ?></button>
  </div>

  <p class="meta mb-20">Kata sandi awal akun baru dan hasil reset: <b class="mono">uab#</b> + 6 digit terakhir <?= $noLabel ?> (contoh <?= $noLabel ?> …123456 → <span class="mono">uab#123456</span>). Pengguna wajib menggantinya saat login pertama.</p>

  <div class="card">
    <div class="card-body">
      <div class="table-wrap">
        <table>
          <thead><tr>
            <th><?= $labelPeran ?></th><th><?= $noLabel ?></th><th>Prodi</th>
            <?php if ($peran === 'mahasiswa'): ?><th>Dosen Pembimbing</th><th>Skripsi</th>
            <?php else: ?><th>Jabatan</th><th class="text-center">Kuota</th><th class="text-center">Sisa</th><?php endif; ?>
            <th>Akun</th><th class="text-right">Aksi</th>
          </tr></thead>
          <tbody>
            <?php foreach ($list as $r):
                $extra = $peran === 'mahasiswa' ? ['status_skripsi_label' => label($r['status_skripsi'])] : [];
                $jsonData = e(json_encode($r + $extra));
            ?>
              <tr>
                <td>
                  <div class="row-10">
                    <div class="mini-avatar" style="width:34px;height:34px;font-size:11px;"><?= e(inisial($r['nama_lengkap'])) ?></div>
                    <div>
                      <div class="fw-500"><?= e($r['nama_lengkap']) ?></div>
                      <div class="meta"><?= e($r['email']) ?></div>
                    </div>
                  </div>
                </td>
                <td class="mono fs-12"><?= e($peran === 'mahasiswa' ? $r['nim'] : $r['nip']) ?></td>
                <td class="fs-13"><?= e($r['nama_prodi']) ?></td>
                <?php if ($peran === 'mahasiswa'): ?>
                  <td class="fs-13"><?= e($r['nama_dosen'] ?? '—') ?></td>
                  <td><span class="badge <?= badge($r['status_skripsi']) ?>"><span class="badge-dot"></span><?= e(label($r['status_skripsi'])) ?></span></td>
                <?php else: $sisa = (int)$r['kuota_bimbingan'] - (int)$r['terisi']; ?>
                  <td class="fs-12"><?= e($r['jabatan_fungsional'] ?? '—') ?></td>
                  <td class="text-center"><?= (int)$r['terisi'] ?>/<?= (int)$r['kuota_bimbingan'] ?></td>
                  <td class="text-center"><span class="badge <?= $sisa <= 0 ? 'danger' : ($sisa <= 2 ? 'warning' : 'success') ?>"><span class="badge-dot"></span><?= $sisa ?></span></td>
                <?php endif; ?>
                <td>
                  <form class="inline" method="POST" data-confirm="<?= $r['status_user'] === 'aktif' ? 'Nonaktifkan akun ' . e($r['nama_lengkap']) . '? Pengguna langsung keluar dan tidak bisa login.' : 'Aktifkan kembali akun ' . e($r['nama_lengkap']) . '?' ?>" data-confirm-ok="<?= $r['status_user'] === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>" data-confirm-tone="<?= $r['status_user'] === 'aktif' ? 'danger' : 'primary' ?>"><?= csrfField() ?>
                    <input type="hidden" name="peran" value="<?= $peran ?>">
                    <input type="hidden" name="aksi" value="toggle_status">
                    <input type="hidden" name="id_user" value="<?= (int)$r['id_user'] ?>">
                    <button type="submit" class="badge <?= $r['status_user'] === 'aktif' ? 'success' : 'danger' ?>" style="cursor:pointer;border:none;" title="Klik untuk mengubah status">
                      <span class="badge-dot"></span><?= e(label($r['status_user'])) ?>
                    </button>
                  </form>
                </td>
                <td class="aksi-cell">
                  <button type="button" class="btn-icon" title="Pratinjau" aria-label="Pratinjau <?= e($r['nama_lengkap']) ?>" onclick="bukaPreview(<?= $jsonData ?>)"><?= icon('eye') ?></button>
                  <button type="button" class="btn-icon" title="Edit" aria-label="Edit <?= e($r['nama_lengkap']) ?>" onclick="bukaEdit(<?= $jsonData ?>)"><?= icon('pencil') ?></button>
                  <?php if ($peran === 'mahasiswa' && $r['id_bimbingan']): ?>
                    <a class="btn-icon" href="<?= BASE_URL ?>/cetak/kartu-bimbingan.php?id=<?= (int)$r['id_bimbingan'] ?>" title="Cetak kartu bimbingan" aria-label="Cetak kartu bimbingan <?= e($r['nama_lengkap']) ?>"><?= icon('printer') ?></a>
                  <?php endif; ?>
                  <form method="POST" style="display:inline;" data-confirm="Reset kata sandi <?= e($r['nama_lengkap']) ?> ke kata sandi awal (uab# + 6 digit terakhir <?= $noLabel ?>)? Kata sandi lama tidak berlaku lagi." data-confirm-ok="Reset kata sandi" data-confirm-tone="primary"><?= csrfField() ?>
                    <input type="hidden" name="peran" value="<?= $peran ?>">
                    <input type="hidden" name="aksi" value="reset_password">
                    <input type="hidden" name="id_user" value="<?= (int)$r['id_user'] ?>">
                    <button type="submit" class="btn-icon" title="Reset kata sandi" aria-label="Reset kata sandi <?= e($r['nama_lengkap']) ?>"><?= icon('key') ?></button>
                  </form>
                  <form method="POST" style="display:inline;" data-confirm="Hapus <?= e($r['nama_lengkap']) ?>? Semua data terkait<?= $peran === 'mahasiswa' ? ', termasuk bimbingan dan dokumen,' : '' ?> ikut terhapus dan tidak bisa dikembalikan." data-confirm-ok="Hapus"><?= csrfField() ?>
                    <input type="hidden" name="peran" value="<?= $peran ?>">
                    <input type="hidden" name="aksi" value="hapus">
                    <input type="hidden" name="id_user" value="<?= (int)$r['id_user'] ?>">
                    <button type="submit" class="btn-icon btn-icon--danger" title="Hapus" aria-label="Hapus <?= e($r['nama_lengkap']) ?>"><?= icon('trash') ?></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$list): ?>
              <tr><td colspan="8" class="empty-state">Data <?= strtolower($labelPeran) ?> tidak ditemukan.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php
// Form tambah & edit memakai field yang sama; prefix id membedakan keduanya.
function formPengguna(string $pre, string $peran, array $prodi, int $maksKuota): void { ?>
      <div class="form-group"><label for="<?= $pre ?>nama_lengkap" class="form-label">Nama Lengkap</label><input id="<?= $pre ?>nama_lengkap" class="form-input" name="nama_lengkap" maxlength="100" required></div>
      <div class="form-row">
        <div class="form-group"><label for="<?= $pre ?>email" class="form-label">Email</label><input id="<?= $pre ?>email" class="form-input" type="email" name="email" maxlength="100" required></div>
        <?php if ($peran === 'mahasiswa'): ?>
          <div class="form-group"><label for="<?= $pre ?>nim" class="form-label">NIM</label><input id="<?= $pre ?>nim" class="form-input" name="nim" maxlength="20" pattern="[0-9A-Za-z.\-]{5,20}" required></div>
        <?php else: ?>
          <div class="form-group"><label for="<?= $pre ?>nip" class="form-label">NIP / NIDN</label><input id="<?= $pre ?>nip" class="form-input" name="nip" maxlength="20" pattern="[0-9A-Za-z.\-]{5,20}" required></div>
        <?php endif; ?>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="<?= $pre ?>id_prodi" class="form-label">Program Studi</label>
          <select id="<?= $pre ?>id_prodi" class="form-input" name="id_prodi" required>
            <?php foreach ($prodi as $p): ?><option value="<?= (int)$p['id_prodi'] ?>"><?= e($p['nama_prodi']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <?php if ($peran === 'mahasiswa'): ?>
          <div class="form-group"><label for="<?= $pre ?>angkatan" class="form-label">Angkatan</label><input id="<?= $pre ?>angkatan" class="form-input" name="angkatan" type="number" min="1990" max="<?= (int)date('Y') + 1 ?>" value="<?= date('Y') ?>" required></div>
        <?php else: ?>
          <div class="form-group">
            <label for="<?= $pre ?>jabatan" class="form-label">Jabatan Fungsional</label>
            <select id="<?= $pre ?>jabatan" class="form-input" name="jabatan" required>
              <option value="" disabled selected>-- Pilih Jabatan --</option>
              <?php foreach (JABATAN_DOSEN as $j): ?><option value="<?= e($j) ?>"><?= e($j) ?></option><?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
      </div>
      <?php if ($peran === 'dosen'): ?>
        <div class="form-row">
          <div class="form-group">
            <label for="<?= $pre ?>keahlian" class="form-label">Bidang Keahlian</label>
            <select id="<?= $pre ?>keahlian" class="form-input" name="keahlian" required>
              <option value="" disabled selected>-- Pilih Keahlian --</option>
              <?php foreach (KEAHLIAN_DOSEN as $k): ?><option value="<?= e($k) ?>"><?= e($k) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label for="<?= $pre ?>kuota" class="form-label">Kuota Bimbingan</label><input id="<?= $pre ?>kuota" class="form-input" type="number" name="kuota" value="<?= (int)pengaturan('default_kuota_dosen', '10') ?>" min="1" max="<?= $maksKuota ?>" required></div>
        </div>
      <?php endif; ?>
<?php } ?>

<div class="modal-overlay" id="modalTambah" style="display:none;">
  <div class="modal-box" style="max-width:540px;max-height:90vh;overflow-y:auto;">
    <div class="modal-head">
      <h2 class="modal-title">Tambah <?= $labelPeran ?></h2>
      <button type="button" aria-label="Tutup" class="modal-close" onclick="tutupModal('modalTambah')">×</button>
    </div>
    <form method="POST"><?= csrfField() ?>
      <input type="hidden" name="peran" value="<?= $peran ?>">
      <input type="hidden" name="aksi" value="tambah">
      <?php formPengguna('t_', $peran, $prodi, $maksKuota); ?>
      <p class="meta">Kata sandi awal dibuat otomatis: <span class="mono">uab#</span> + 6 digit terakhir <?= $noLabel ?>, dan ditampilkan setelah disimpan.</p>
      <div class="form-actions">
        <button type="button" class="btn btn-secondary" onclick="tutupModal('modalTambah')">Batal</button>
        <button type="submit" class="btn btn-gold">Simpan</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="modalEdit" style="display:none;">
  <div class="modal-box" style="max-width:540px;max-height:90vh;overflow-y:auto;">
    <div class="modal-head">
      <h2 class="modal-title">Edit <?= $labelPeran ?></h2>
      <button type="button" aria-label="Tutup" class="modal-close" onclick="tutupModal('modalEdit')">×</button>
    </div>
    <form method="POST"><?= csrfField() ?>
      <input type="hidden" name="peran" value="<?= $peran ?>">
      <input type="hidden" name="aksi" value="edit">
      <input type="hidden" name="id_user" id="e_id_user">
      <?php formPengguna('e_', $peran, $prodi, $maksKuota); ?>
      <div class="form-actions">
        <button type="button" class="btn btn-secondary" onclick="tutupModal('modalEdit')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="modalPreview" style="display:none;">
  <div class="modal-box">
    <div class="modal-head">
      <h2 class="modal-title">Detail <?= $labelPeran ?></h2>
      <button type="button" aria-label="Tutup" class="modal-close" onclick="tutupModal('modalPreview')">×</button>
    </div>
    <div id="previewIsi" style="display:flex;flex-direction:column;gap:16px;"></div>
    <div class="form-actions" style="margin-top:24px;">
      <button type="button" class="btn btn-secondary btn-full" onclick="tutupModal('modalPreview')" style="justify-content:center;">Tutup</button>
    </div>
  </div>
</div>

<script>
const PERAN = <?= json_encode($peran) ?>;
function bukaModal(id) { document.getElementById(id).style.display = 'flex'; }
function tutupModal(id) { document.getElementById(id).style.display = 'none'; }

// Isi form dengan data (dipakai saat edit dan saat memulihkan isian yang gagal disimpan)
function isiForm(pre, d) {
  const set = (k, v) => { const el = document.getElementById(pre + k); if (el && v !== undefined && v !== null) el.value = v; };
  set('nama_lengkap', d.nama_lengkap); set('email', d.email); set('id_prodi', d.id_prodi);
  if (PERAN === 'mahasiswa') { set('nim', d.nim); set('angkatan', d.angkatan); }
  else { set('nip', d.nip); set('jabatan', d.jabatan_fungsional ?? d.jabatan); set('keahlian', d.keahlian); set('kuota', d.kuota_bimbingan ?? d.kuota); }
}
function bukaEdit(d) {
  document.getElementById('e_id_user').value = d.id_user;
  isiForm('e_', d);
  bukaModal('modalEdit');
}
function bukaPreview(d) {
  const baris = PERAN === 'mahasiswa'
    ? [['Nama Lengkap', d.nama_lengkap], ['Email', d.email], ['NIM', d.nim], ['Program Studi', d.nama_prodi], ['Angkatan', d.angkatan],
       ['No. Telepon', d.no_telepon || 'Belum diisi'], ['Status Skripsi', d.status_skripsi_label], ['Dosen Pembimbing', d.nama_dosen || 'Belum ada pembimbing aktif']]
    : [['Nama Lengkap', d.nama_lengkap], ['Email', d.email], ['NIP / NIDN', d.nip], ['Program Studi', d.nama_prodi], ['Jabatan Fungsional', d.jabatan_fungsional || '-'],
       ['Bidang Keahlian', d.keahlian || '-'], ['Kuota', d.kuota_bimbingan + ' mahasiswa'], ['Sedang dibimbing', d.terisi + ' mahasiswa']];
  baris.push(['Login terakhir', d.last_login || 'Belum pernah']);
  const wadah = document.getElementById('previewIsi');
  wadah.innerHTML = '';
  baris.forEach(([k, v], i) => {
    const row = document.createElement('div');
    row.className = 'detail-row' + (i === baris.length - 1 ? ' detail-row--last' : '');
    const a = document.createElement('span'); a.className = 'muted'; a.textContent = k;
    const b = document.createElement('span'); b.className = 'fs-14'; b.textContent = v;
    row.append(a, b); wadah.append(row);
  });
  bukaModal('modalPreview');
}
<?php if ($isian): ?>
// Isian terakhir gagal disimpan: buka kembali form dengan data yang sudah diketik
(function () {
  const d = <?= json_encode($isian['data'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  if (<?= json_encode($isian['aksi']) ?> === 'edit') { document.getElementById('e_id_user').value = d.id_user; isiForm('e_', d); bukaModal('modalEdit'); }
  else { isiForm('t_', d); bukaModal('modalTambah'); }
})();
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
