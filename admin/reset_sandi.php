<?php
// admin/reset_sandi.php — Proses permintaan lupa kata sandi (D3)
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('admin');
wajibMigrasiFitur();

$pageTitle  = 'Reset Kata Sandi';
$activePage = 'reset_sandi';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id  = (int)($_POST['id_permintaan'] ?? 0);
    $req = fetchOne('SELECT p.*, u.role, u.nama_lengkap, u.email FROM permintaan_reset p JOIN user u ON u.id_user=p.id_user
                     WHERE p.id_permintaan=? AND p.status="menunggu"', [$id]);
    if (!$req) {
        setFlash('danger', 'Permintaan tidak ditemukan atau sudah diproses.');
    } elseif (($_POST['aksi'] ?? '') === 'reset') {
        $temp = resetUserPassword((int)$req['id_user'], $req['role']);
        if ($temp === null) {
            setFlash('danger', 'Akun tidak ditemukan.');
        } else {
            execute('UPDATE permintaan_reset SET status="selesai", diproses_at=NOW() WHERE id_permintaan=?', [$id]);
            kirimNotif((int)$req['id_user'], 'Kata sandi direset', 'Permintaan reset kata sandi Anda sudah diproses admin. Segera ganti kata sandi setelah login.', 'info', 'akun', 'profil.php');
            // Ditampilkan sekali dalam kotak yang tidak hilang otomatis
            setKredensial('Kata sandi berhasil direset', $req['nama_lengkap'], $req['email'], $temp);
        }
    } elseif (($_POST['aksi'] ?? '') === 'tolak') {
        execute('UPDATE permintaan_reset SET status="ditolak", catatan=?, diproses_at=NOW() WHERE id_permintaan=?',
            [mb_substr(trim($_POST['catatan'] ?? ''), 0, 255) ?: null, $id]);
        setFlash('success', 'Permintaan ditolak.');
    }
    header('Location: ' . BASE_URL . '/admin/reset_sandi.php');
    exit;
}


$menunggu = fetchAll(
    'SELECT p.*, u.nama_lengkap, u.email, u.role, u.last_login, COALESCE(m.nim, d.nip) AS nomor
     FROM permintaan_reset p JOIN user u ON u.id_user = p.id_user
     LEFT JOIN mahasiswa m ON m.id_user = u.id_user LEFT JOIN dosen d ON d.id_user = u.id_user
     WHERE p.status = "menunggu" ORDER BY p.created_at'
);
$riwayat = fetchAll(
    'SELECT p.*, u.nama_lengkap, u.role FROM permintaan_reset p JOIN user u ON u.id_user = p.id_user
     WHERE p.status <> "menunggu" ORDER BY p.diproses_at DESC LIMIT 20'
);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">
  <?= tampilFlash() ?>

  <div class="page-header">
    <h1>Reset Kata Sandi</h1>
    <p>Permintaan dari halaman "Lupa kata sandi". Pastikan identitas pemohon sebelum menyerahkan kata sandi baru. Kata sandi direset ke format awal <b>uab#</b> + 6 digit terakhir NIM/NIP dan wajib diganti saat login.</p>
  </div>


  <div class="card mb-20">
    <div class="card-header"><div class="card-title">Menunggu diproses</div><span class="badge <?= $menunggu ? 'warning' : 'neutral' ?>"><?= count($menunggu) ?></span></div>
    <div class="card-body">
      <?php if (!$menunggu): ?>
        <div class="empty-state"><div class="empty-sub">Tidak ada permintaan reset.</div></div>
      <?php else: ?>
        <div class="table-wrap"><table>
          <thead><tr><th>Pengguna</th><th>NIM/NIP</th><th>Diminta</th><th>Login terakhir</th><th class="text-right">Aksi</th></tr></thead>
          <tbody>
          <?php foreach ($menunggu as $p): ?>
            <tr>
              <td><div class="fw-500"><?= e($p['nama_lengkap']) ?></div><div class="meta"><?= e($p['email']) ?> · <?= e(label($p['role'])) ?></div></td>
              <td class="mono fs-12"><?= e($p['nomor']) ?></td>
              <td class="nowrap"><?= tgl($p['created_at'], 'jam') ?></td>
              <td class="nowrap"><?= $p['last_login'] ? tgl($p['last_login'], 'jam') : '-' ?></td>
              <td class="text-right">
                <div class="aksi-stack" style="justify-content:flex-end;">
                  <form method="POST" data-confirm="Reset kata sandi <?= e($p['nama_lengkap']) ?> ke kata sandi awal (uab# + 6 digit terakhir NIM/NIP)? Kata sandi lama tidak berlaku lagi." data-confirm-ok="Reset" data-confirm-tone="primary"><?= csrfField() ?>
                    <input type="hidden" name="aksi" value="reset"><input type="hidden" name="id_permintaan" value="<?= (int)$p['id_permintaan'] ?>">
                    <button class="btn btn-primary btn-sm"><?= icon('key', 14) ?> Reset</button>
                  </form>
                  <form method="POST" data-confirm="Tolak permintaan ini?" data-confirm-ok="Tolak"><?= csrfField() ?>
                    <input type="hidden" name="aksi" value="tolak"><input type="hidden" name="id_permintaan" value="<?= (int)$p['id_permintaan'] ?>">
                    <button class="btn btn-secondary btn-sm">Tolak</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($riwayat): ?>
    <div class="card">
      <div class="card-header"><div class="card-title">Riwayat</div></div>
      <div class="card-body"><div class="table-wrap"><table>
        <thead><tr><th>Pengguna</th><th>Status</th><th>Diproses</th></tr></thead>
        <tbody>
        <?php foreach ($riwayat as $p): ?>
          <tr><td><?= e($p['nama_lengkap']) ?> <span class="meta">· <?= e(label($p['role'])) ?></span></td>
            <td><span class="badge <?= $p['status'] === 'selesai' ? 'success' : 'neutral' ?>"><span class="badge-dot"></span><?= $p['status'] === 'selesai' ? 'Direset' : 'Ditolak' ?></span></td>
            <td class="nowrap"><?= tgl($p['diproses_at'], 'jam') ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div></div>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
