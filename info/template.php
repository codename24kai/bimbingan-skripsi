<?php
// info/template.php — Unduhan template dokumen (S2). Admin bisa menambah template sendiri.
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireLogin();
wajibMigrasiFitur();

$pageTitle  = 'Template Dokumen';
$activePage = 'template';
$isAdmin    = role() === 'admin';

const KATEGORI_TEMPLATE = ['proposal', 'bab', 'pengesahan', 'formulir', 'lainnya'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    $aksi = $_POST['aksi'] ?? '';
    if ($aksi === 'unggah') {
        $nama = trim($_POST['nama'] ?? '');
        $kat  = $_POST['kategori'] ?? 'lainnya';
        $ket  = trim($_POST['keterangan'] ?? '');
        try {
            if ($nama === '') throw new Exception('Nama template wajib diisi.');
            if (!in_array($kat, KATEGORI_TEMPLATE, true)) $kat = 'lainnya';
            $path = simpanUpload($_FILES['file'] ?? [], 'tpl', ['pdf', 'doc', 'docx', 'xlsx', 'pptx', 'zip']);
            execute('INSERT INTO template_dokumen (nama, kategori, keterangan, file_path, ukuran_kb) VALUES (?,?,?,?,?)',
                [mb_substr($nama, 0, 150), $kat, mb_substr($ket, 0, 255) ?: null, $path, (int)ceil(($_FILES['file']['size'] ?? 0) / 1024)]);
            setFlash('success', 'Template berhasil ditambahkan.');
        } catch (Exception $ex) {
            setFlash('danger', $ex->getMessage());
        }
    } elseif ($aksi === 'hapus') {
        $t = fetchOne('SELECT file_path FROM template_dokumen WHERE id_template=?', [(int)($_POST['id_template'] ?? 0)]);
        if ($t) {
            hapusUpload($t['file_path']);
            execute('DELETE FROM template_dokumen WHERE id_template=?', [(int)$_POST['id_template']]);
            setFlash('success', 'Template dihapus.');
        }
    }
    header('Location: ' . BASE_URL . '/info/template.php');
    exit;
}

// Template bawaan (file statis di assets/template/)
$bawaan = [
    ['Format_Proposal_Skripsi.docx',   'Format proposal skripsi', 'proposal',   'Sampul, Bab I sampai III, dan daftar pustaka untuk seminar proposal.'],
    ['Template_Skripsi_Bab_I-V.docx',  'Template skripsi Bab I sampai V', 'bab', 'Halaman awal, Bab I sampai V, daftar pustaka, dan lampiran.'],
    ['Lembar_Pengesahan.docx',         'Lembar pengesahan', 'pengesahan', 'Diisi setelah sidang dan ditandatangani pembimbing serta penguji.'],
    ['Formulir_Pengajuan_Judul.docx',  'Formulir pengajuan judul', 'formulir', 'Draf judul utama, dua alternatif, dan usulan pembimbing.'],
    ['Formulir_Persetujuan_Sidang.docx','Formulir persetujuan seminar atau sidang', 'formulir', 'Ditandatangani pembimbing sebelum mendaftar ujian.'],
];
$unggahan = fetchAll('SELECT * FROM template_dokumen ORDER BY kategori, nama');

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">
  <?php $flash = getFlash(); if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div><?php endif; ?>

  <div class="page-header">
    <h1>Template Dokumen</h1>
    <p>Unduh format resmi agar penulisan seragam dan revisi format berkurang</p>
  </div>

  <div class="alert alert-static info mb-20">Format umum: Times New Roman 12, spasi 1,5, margin kiri 4 cm dan sisi lain 3 cm. Jika pedoman prodi berbeda, ikuti pedoman prodi.</div>

  <div class="<?= $isAdmin ? 'split' : '' ?>" style="--side:340px;">
    <div>
      <?php if ($unggahan): ?>
        <div class="card mb-20">
          <div class="card-header"><div class="card-title">Dari bagian akademik</div></div>
          <div class="card-body">
            <div class="berkas-grid">
              <?php foreach ($unggahan as $t): $ext = strtoupper(pathinfo($t['file_path'], PATHINFO_EXTENSION)); ?>
                <div class="berkas-item">
                  <span class="berkas-ext"><?= e($ext) ?></span>
                  <div class="min-w-0" style="flex:1;">
                    <div class="fw-500"><?= e($t['nama']) ?></div>
                    <div class="meta"><?= e(label($t['kategori'])) ?> · <?= (int)$t['ukuran_kb'] ?> KB<?= $t['keterangan'] ? ' · ' . e($t['keterangan']) : '' ?></div>
                  </div>
                  <a class="btn btn-secondary btn-sm" href="<?= e(fileUrl($t['file_path'])) ?>"><?= icon('download', 16) ?> Unduh</a>
                  <?php if ($isAdmin): ?>
                    <form method="POST" data-confirm="Hapus template <?= e($t['nama']) ?>?" data-confirm-ok="Hapus"><?= csrfField() ?>
                      <input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id_template" value="<?= (int)$t['id_template'] ?>">
                      <button class="btn-icon btn-icon--danger" aria-label="Hapus template"><?= icon('trash') ?></button>
                    </form>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div class="card">
        <div class="card-header"><div class="card-title">Template umum</div></div>
        <div class="card-body">
          <div class="berkas-grid">
            <?php foreach ($bawaan as [$file, $nama, $kat, $ket]):
              $full = __DIR__ . '/../assets/template/' . $file;
              if (!is_file($full)) continue; ?>
              <div class="berkas-item">
                <span class="berkas-ext">DOCX</span>
                <div class="min-w-0" style="flex:1;">
                  <div class="fw-500"><?= e($nama) ?></div>
                  <div class="meta"><?= e(label($kat)) ?> · <?= (int)ceil(filesize($full) / 1024) ?> KB · <?= e($ket) ?></div>
                </div>
                <a class="btn btn-secondary btn-sm" href="<?= BASE_URL ?>/assets/template/<?= rawurlencode($file) ?>" download><?= icon('download', 16) ?> Unduh</a>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <?php if ($isAdmin): ?>
      <div class="form-card" style="height:fit-content;">
        <h2>Tambah Template</h2>
        <p>Unggah format resmi dari prodi. Template ini tampil di atas template umum.</p>
        <form method="POST" enctype="multipart/form-data"><?= csrfField() ?>
          <input type="hidden" name="aksi" value="unggah">
          <div class="form-group">
            <label class="form-label" for="t_nama">Nama template <span class="req">*</span></label>
            <input class="form-input" id="t_nama" name="nama" required maxlength="150" placeholder="cth: Pedoman penulisan skripsi 2026">
          </div>
          <div class="form-group">
            <label class="form-label" for="t_kat">Kategori</label>
            <select class="form-input" id="t_kat" name="kategori">
              <?php foreach (KATEGORI_TEMPLATE as $k): ?><option value="<?= $k ?>"><?= e(label($k)) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="t_ket">Keterangan singkat</label>
            <input class="form-input" id="t_ket" name="keterangan" maxlength="255">
          </div>
          <div class="form-group">
            <label class="form-label" for="f_file">File</label>
            <?= uploadZone('file', 'Klik atau seret file', '.pdf,.doc,.docx,.xlsx,.pptx,.zip', 'PDF, Word, Excel, PowerPoint, atau ZIP. Maks 10 MB', true) ?>
          </div>
          <button type="submit" class="btn btn-gold btn-full">Unggah template</button>
        </form>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
