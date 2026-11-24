<?php
// mahasiswa/pengajuan.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('mahasiswa');

$pageTitle  = 'Pengajuan Judul';
$activePage = 'pengajuan';
$eid        = entityId();

// Riwayat pengajuan
$riwayat = fetchAll('SELECT * FROM judul_skripsi WHERE id_mahasiswa=? ORDER BY tgl_pengajuan DESC', [$eid]);
// Cek judul disetujui
$judulDisetujui = fetchOne('SELECT * FROM judul_skripsi WHERE id_mahasiswa=? AND status="disetujui" LIMIT 1', [$eid]);
// Cek status bimbingan (Apakah SK sudah terbit)
$bimbinganAktif = fetchOne('SELECT b.no_sk, b.file_sk, b.tgl_mulai, b.semester, u.nama_lengkap AS nama_dosen
                           FROM bimbingan b JOIN dosen d ON d.id_dosen=b.id_dosen JOIN user u ON u.id_user=d.id_user
                           WHERE b.id_mahasiswa=? AND b.status="aktif" LIMIT 1', [$eid]);

// Handle POST Pengajuan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$eid) {
        setFlash('danger', 'Sesi tidak valid.');
        header('Location: ' . BASE_URL . '/mahasiswa/pengajuan.php');
        exit;
    }
    if ($judulDisetujui) {
        setFlash('warning', 'Pengajuan ditutup. Anda sudah memiliki judul disetujui.');
        header('Location: ' . BASE_URL . '/mahasiswa/pengajuan.php');
        exit;
    }

    $judul_utama = trim($_POST['judul_indonesia'] ?? '');
    $judul_alt1  = trim($_POST['judul_alternatif_1'] ?? '');
    $judul_alt2  = trim($_POST['judul_alternatif_2'] ?? '');
    $bidang      = trim($_POST['bidang_penelitian'] ?? '');
    $metode      = trim($_POST['metode_penelitian'] ?? '');
    $latar       = trim($_POST['latar_belakang'] ?? '');

    $opsi = array_filter([mb_strtolower($judul_utama), mb_strtolower($judul_alt1), mb_strtolower($judul_alt2)]);
    if (countRows('SELECT COUNT(*) FROM judul_skripsi WHERE id_mahasiswa=? AND status="diajukan"', [$eid])) {
        setFlash('warning', 'Pengajuan Anda sebelumnya masih menunggu validasi. Tunggu hasilnya sebelum mengajukan lagi.');
    } elseif (empty($judul_utama) || empty($judul_alt1) || empty($bidang) || empty($metode) || empty($latar)) {
        setFlash('danger', 'Judul Utama, Judul Alternatif 1, Bidang, Metode, dan Latar Belakang wajib diisi!');
    } elseif (!in_array($bidang, KEAHLIAN_DOSEN, true) || !in_array($metode, METODE_PENELITIAN, true)) {
        setFlash('danger', 'Bidang atau metode penelitian tidak valid. Pilih dari daftar yang tersedia.');
    } elseif (max(array_map('mb_strlen', [$judul_utama, $judul_alt1, $judul_alt2])) > 300) {
        setFlash('danger', 'Setiap judul maksimal 300 karakter.');
    } elseif (count($opsi) !== count(array_unique($opsi))) {
        setFlash('danger', 'Pilihan judul tidak boleh sama satu sama lain.');
    } else {
        $db = getDB();
        $db->beginTransaction();
        try {
            // Check Iterasi
            $prev = fetchOne('SELECT COUNT(*) AS n FROM judul_skripsi WHERE id_mahasiswa=?', [$eid]);
            $iterasi = ($prev['n'] ?? 0) + 1;

            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

            // 1. Handle Upload Proposal (Opsional)
            $filePathProposal = null;
            if (isset($_FILES['file_proposal']) && $_FILES['file_proposal']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['file_proposal']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['pdf', 'docx'])) throw new \Exception('Proposal harus PDF/DOCX.');
                if ($_FILES['file_proposal']['size'] > 10 * 1024 * 1024) throw new \Exception('Proposal maks 10MB.');
                
                $nameP = 'proposal_' . $eid . '_' . uniqid() . '.' . $ext;
                if (!move_uploaded_file($_FILES['file_proposal']['tmp_name'], UPLOAD_DIR . $nameP)) throw new \Exception('Gagal upload proposal.');
                $filePathProposal = 'uploads/' . $nameP;
            }

            // 2. Handle Upload Transkrip/KRS (Wajib PDF)
            $filePathTranskrip = null;
            if (isset($_FILES['file_transkrip']) && $_FILES['file_transkrip']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['file_transkrip']['name'], PATHINFO_EXTENSION));
                if ($ext !== 'pdf') throw new \Exception('Transkrip Nilai wajib berformat PDF.');
                if ($_FILES['file_transkrip']['size'] > 5 * 1024 * 1024) throw new \Exception('Transkrip maks 5MB.');
                
                $nameT = 'syarat_' . $eid . '_' . uniqid() . '.pdf';
                if (!move_uploaded_file($_FILES['file_transkrip']['tmp_name'], UPLOAD_DIR . $nameT)) throw new \Exception('Gagal upload transkrip.');
                $filePathTranskrip = 'uploads/' . $nameT;
            } else {
                throw new \Exception('Dokumen Persyaratan (Transkrip/KRS) wajib diunggah.');
            }

            // Insert Database
            execute(
                'INSERT INTO judul_skripsi (id_mahasiswa, judul_indonesia, judul_alternatif_1, judul_alternatif_2, bidang_penelitian, metode_penelitian, latar_belakang, iterasi, status, file_proposal, file_transkrip)
                 VALUES (?,?,?,?,?,?,?,?,"diajukan",?,?)',
                [$eid, $judul_utama, $judul_alt1, $judul_alt2, $bidang, $metode, $latar, $iterasi, $filePathProposal, $filePathTranskrip]
            );

            $db->commit();
            kirimNotifAdmin('Pengajuan judul baru', (currentUser()['nama_lengkap'] ?? 'Mahasiswa') . ' mengajukan judul: ' . mb_strimwidth($judul_utama, 0, 120, '…'),
                'info', 'judul', 'admin/validasi_judul.php');
            setFlash('success', 'Pengajuan dengan opsi judul berhasil dikirim! Menunggu verifikasi Kaprodi.');
            header('Location: ' . BASE_URL . '/mahasiswa/pengajuan.php');
            exit;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            // Jangan tinggalkan file yatim jika pengajuan gagal disimpan
            hapusUpload($filePathProposal ?? null);
            hapusUpload($filePathTranskrip ?? null);
            setFlash('danger', pesanGalat($e));
        }
    }
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">

  <?= tampilFlash() ?>

  <div class="page-header">
    <h1>Pengajuan Judul Skripsi</h1>
    <p><?= $judulDisetujui ? 'Ringkasan judul skripsi Anda yang sudah disetujui beserta riwayat pengajuannya.' : 'Langkah pertama: ajukan 2 sampai 3 pilihan judul beserta berkas syarat.' ?></p>
  </div>

  <?php if ($judulDisetujui): ?>
    <?php if ($bimbinganAktif): ?>
      <div class="status-card status-card--success">
        <div class="status-card__icon"><?= icon('graduation', 26) ?></div>
        <div class="min-w-0" style="flex:1;">
          <div class="status-card__title">SK bimbingan sudah terbit</div>
          <div class="muted">No. SK <strong><?= e($bimbinganAktif['no_sk']) ?></strong> · mulai <?= tgl($bimbinganAktif['tgl_mulai']) ?> · semester <?= e($bimbinganAktif['semester']) ?> · pembimbing <strong><?= e($bimbinganAktif['nama_dosen']) ?></strong></div>
        </div>
        <div class="status-card__aksi">
          <?php if ($bimbinganAktif['file_sk']): ?>
            <a href="<?= e(fileUrl($bimbinganAktif['file_sk'])) ?>" target="_blank" class="btn btn-primary btn-sm"><?= icon('download', 16) ?> Unduh SK</a>
          <?php endif; ?>
          <a href="<?= BASE_URL ?>/mahasiswa/bimbingan.php" class="btn btn-secondary btn-sm"><?= icon('calendar', 16) ?> Ajukan jadwal</a>
        </div>
      </div>
    <?php else: ?>
      <div class="status-card status-card--info">
        <div class="status-card__icon"><?= icon('check-circle', 26) ?></div>
        <div class="min-w-0" style="flex:1;">
          <div class="status-card__title">Judul Anda sudah disetujui</div>
          <div class="muted"><?= $judulDisetujui['usulan_dosen'] ? 'Usulan pembimbing sudah dikirim. Tunggu Program Studi menerbitkan SK bimbingan.' : 'Langkah berikutnya: usulkan dosen pembimbing yang sesuai bidang Anda.' ?></div>
        </div>
        <div class="status-card__aksi">
          <a href="<?= BASE_URL ?>/mahasiswa/pengajuan_dosen.php" class="btn btn-gold btn-sm"><?= icon('user', 16) ?> <?= $judulDisetujui['usulan_dosen'] ? 'Lihat usulan pembimbing' : 'Usulkan pembimbing' ?></a>
        </div>
      </div>
    <?php endif; ?>

    <div class="split" style="--side:340px; margin-top:20px;">
      <div class="card">
        <div class="card-header">
          <div class="card-title">Judul Skripsi Anda</div>
          <span class="badge success"><span class="badge-dot"></span>Disetujui</span>
        </div>
        <div class="card-body">
          <div class="judul-utama"><?= e($judulDisetujui['judul_indonesia']) ?></div>

          <div class="info-grid">
            <div><div class="info-label">Bidang penelitian</div><div class="info-value"><?= e($judulDisetujui['bidang_penelitian']) ?></div></div>
            <div><div class="info-label">Metode penelitian</div><div class="info-value"><?= e($judulDisetujui['metode_penelitian'] ?: '-') ?></div></div>
            <div><div class="info-label">Tanggal diajukan</div><div class="info-value"><?= tgl($judulDisetujui['tgl_pengajuan']) ?></div></div>
            <div><div class="info-label">Pengajuan ke</div><div class="info-value"><?= (int)$judulDisetujui['iterasi'] ?></div></div>
          </div>

          <?php if ($judulDisetujui['latar_belakang']): ?>
            <div class="info-label" style="margin-top:20px;">Latar belakang</div>
            <details class="latar">
              <summary>
                <span class="latar__ringkas"><?= e(mb_strimwidth($judulDisetujui['latar_belakang'], 0, 260, '…')) ?></span>
                <?php if (mb_strlen($judulDisetujui['latar_belakang']) > 260): ?><span class="latar__toggle">Tampilkan selengkapnya</span><?php endif; ?>
              </summary>
              <div class="latar__penuh"><?= nl2br(e($judulDisetujui['latar_belakang'])) ?></div>
            </details>
          <?php endif; ?>

          <?php $alternatif = array_filter([$judulDisetujui['judul_alternatif_1'], $judulDisetujui['judul_alternatif_2']], fn($j) => $j && $j !== $judulDisetujui['judul_indonesia']); ?>
          <?php if ($alternatif): ?>
            <div class="info-label" style="margin-top:20px;">Judul cadangan yang diajukan</div>
            <ul class="judul-alt">
              <?php foreach ($alternatif as $alt): ?><li><?= e($alt) ?></li><?php endforeach; ?>
            </ul>
          <?php endif; ?>

          <div class="info-label" style="margin-top:20px;">Berkas</div>
          <div class="berkas-list">
            <?php if ($judulDisetujui['file_transkrip']): ?>
              <a href="<?= e(fileUrl($judulDisetujui['file_transkrip'])) ?>" target="_blank" class="berkas"><?= icon('file', 18) ?><span>Transkrip / KRS</span><?= icon('download', 16) ?></a>
            <?php endif; ?>
            <?php if ($judulDisetujui['file_proposal']): ?>
              <a href="<?= e(fileUrl($judulDisetujui['file_proposal'])) ?>" target="_blank" class="berkas"><?= icon('file', 18) ?><span>Draf proposal</span><?= icon('download', 16) ?></a>
            <?php endif; ?>
            <?php if (!$judulDisetujui['file_transkrip'] && !$judulDisetujui['file_proposal']): ?>
              <span class="muted">Tidak ada berkas.</span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div>
      <div class="card">
        <div class="card-header"><div class="card-title">Riwayat Pengajuan</div></div>
        <div class="card-body">
          <?php if (empty($riwayat)): ?>
            <div class="empty-state"><div class="empty-sub">Belum ada pengajuan. Isi formulir di samping untuk mengajukan judul pertama Anda.</div></div>
          <?php else: ?>
            <?php foreach ($riwayat as $r): ?>
              <div style="border:1px solid var(--cream-dark);border-radius:8px;padding:14px;margin-bottom:10px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <span style="font-size:11px; font-weight:600; color:var(--slate);">Iterasi ke-<?= $r['iterasi'] ?></span>
                    <span class="badge <?= badge($r['status']) ?>"><span class="badge-dot"></span><?= e(label($r['status'])) ?></span>
                </div>
                
                <div class="meta-sm mb-4">1. <?= e($r['judul_indonesia']) ?></div>
                <div class="meta-sm mb-4">2. <?= e($r['judul_alternatif_1']) ?></div>
                <?php if($r['judul_alternatif_2']): ?>
                    <div style="font-size:11px; color:var(--slate); margin-bottom:10px;">3. <?= e($r['judul_alternatif_2']) ?></div>
                <?php endif; ?>

                <div style="display:flex; gap:10px; margin-top:12px; border-top:1px dashed var(--cream-dark); padding-top:10px;">
                    <?php if($r['file_transkrip']): ?>
                        <a href="<?= e(fileUrl($r['file_transkrip'])) ?>" target="_blank" style="font-size:11px; color:var(--info-text); text-decoration:none;">📄 Transkrip</a>
                    <?php endif; ?>
                    <?php if($r['file_proposal']): ?>
                        <a href="<?= e(fileUrl($r['file_proposal'])) ?>" target="_blank" style="font-size:11px; color:var(--info-text); text-decoration:none;">📄 Proposal</a>
                    <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
      </div>
    </div>

  <?php else: ?>
    <div class="split" style="--side:340px; margin-top:20px;">
      <?php $menungguValidasi = array_filter($riwayat, fn($r) => $r['status'] === 'diajukan'); ?>
      <?php if ($menungguValidasi): ?>
      <div class="status-card status-card--info" style="height:fit-content;">
        <div class="status-card__icon"><?= icon('clock', 26) ?></div>
        <div class="min-w-0" style="flex:1;">
          <div class="status-card__title">Pengajuan sedang divalidasi</div>
          <div class="muted">Pengajuan judul Anda menunggu keputusan Kaprodi/dosen. Formulir dibuka lagi bila pengajuan ditolak atau diminta revisi.</div>
        </div>
      </div>
      <?php else: ?>
      <div class="form-card">
          <h2>Formulir Pengajuan Judul</h2>
          <p style="color:var(--slate); font-size:13px; margin-bottom:20px;">Isi minimal 2 pilihan judul. Dosen akan memilih atau meminta revisi.</p>
          
          <form method="POST" enctype="multipart/form-data"><?= csrfField() ?>
            <div class="form-group">
              <label for="f_judul_indonesia" class="form-label">Judul Utama <span class="req" aria-hidden="true">*</span></label>
              <input id="f_judul_indonesia" class="form-input" name="judul_indonesia" required placeholder="Judul yang paling Anda pilih" value="<?= e($_POST['judul_indonesia'] ?? '') ?>" data-cek-judul="cekJudulHasil">
              <div id="cekJudulHasil" class="cek-judul" aria-live="polite"></div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                  <label for="f_judul_alternatif_1" class="form-label">Judul Cadangan 1 <span class="req" aria-hidden="true">*</span></label>
                  <input id="f_judul_alternatif_1" class="form-input" name="judul_alternatif_1" required placeholder="Pilihan judul kedua" value="<?= e($_POST['judul_alternatif_1'] ?? '') ?>" data-cek-judul="cekJudulAlt1">
                  <div id="cekJudulAlt1" class="cek-judul" aria-live="polite"></div>
                </div>
                <div class="form-group">
                  <label for="f_judul_alternatif_2" class="form-label">Judul Cadangan 2 <span style="font-weight:400;color:var(--slate);">(opsional)</span></label>
                  <input id="f_judul_alternatif_2" class="form-input" name="judul_alternatif_2" placeholder="Pilihan judul ketiga" value="<?= e($_POST['judul_alternatif_2'] ?? '') ?>" data-cek-judul="cekJudulAlt2">
                  <div id="cekJudulAlt2" class="cek-judul" aria-live="polite"></div>
                </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="f_bidang_penelitian" class="form-label">Konsentrasi / Bidang Ilmu <span class="req" aria-hidden="true">*</span></label>
                <select id="f_bidang_penelitian" class="form-input" name="bidang_penelitian" required>
                  <option value="" disabled selected>Pilih Bidang...</option>
                  <?php foreach (KEAHLIAN_DOSEN as $b): ?>
                    <option value="<?= e($b) ?>" <?= ($_POST['bidang_penelitian'] ?? '') === $b ? 'selected' : '' ?>><?= e($b) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="f_metode_penelitian" class="form-label">Metode Penelitian <span class="req" aria-hidden="true">*</span></label>
                <select id="f_metode_penelitian" class="form-input" name="metode_penelitian" required>
                  <option value="" disabled selected>Pilih Metode...</option>
                  <?php foreach (METODE_PENELITIAN as $m): ?>
                    <option value="<?= e($m) ?>" <?= ($_POST['metode_penelitian'] ?? '') === $m ? 'selected' : '' ?>><?= e($m) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label for="f_latar_belakang" class="form-label">Latar Belakang Singkat <span class="req" aria-hidden="true">*</span></label>
              <textarea id="f_latar_belakang" class="form-input" name="latar_belakang" required rows="5" placeholder="Jelaskan masalah yang ingin Anda selesaikan dan mengapa penting. Cukup satu ringkasan untuk semua pilihan judul."><?= e($_POST['latar_belakang'] ?? '') ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                  <label for="f_file_transkrip" class="form-label">Transkrip atau KRS <span class="req" aria-hidden="true">*</span></label>
                  <?= uploadZone('file_transkrip', 'Klik atau seret file PDF', '.pdf', 'Format PDF, maksimal 5 MB', true) ?>
                </div>
                <div class="form-group">
                  <label for="f_file_proposal" class="form-label">Draf Proposal <span style="font-weight:400;color:var(--slate);">(opsional)</span></label>
                  <?= uploadZone('file_proposal', 'Klik atau seret file', '.pdf,.docx,.doc', 'Format PDF atau DOCX') ?>
                </div>
            </div>

            <div class="form-actions" style="margin-top:20px;">
              <button type="submit" class="btn btn-primary btn-full">Kirim Pengajuan</button>
            </div>
          </form>
      </div>
      <?php endif; ?>
      <div>
      <div class="card">
        <div class="card-header"><div class="card-title">Riwayat Pengajuan</div></div>
        <div class="card-body">
          <?php if (empty($riwayat)): ?>
            <div class="empty-state"><div class="empty-sub">Belum ada pengajuan. Isi formulir di samping untuk mengajukan judul pertama Anda.</div></div>
          <?php else: ?>
            <?php foreach ($riwayat as $r): ?>
              <div style="border:1px solid var(--cream-dark);border-radius:8px;padding:14px;margin-bottom:10px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <span style="font-size:11px; font-weight:600; color:var(--slate);">Iterasi ke-<?= $r['iterasi'] ?></span>
                    <span class="badge <?= badge($r['status']) ?>"><span class="badge-dot"></span><?= e(label($r['status'])) ?></span>
                </div>
                
                <div class="meta-sm mb-4">1. <?= e($r['judul_indonesia']) ?></div>
                <div class="meta-sm mb-4">2. <?= e($r['judul_alternatif_1']) ?></div>
                <?php if($r['judul_alternatif_2']): ?>
                    <div style="font-size:11px; color:var(--slate); margin-bottom:10px;">3. <?= e($r['judul_alternatif_2']) ?></div>
                <?php endif; ?>

                <div style="display:flex; gap:10px; margin-top:12px; border-top:1px dashed var(--cream-dark); padding-top:10px;">
                    <?php if($r['file_transkrip']): ?>
                        <a href="<?= e(fileUrl($r['file_transkrip'])) ?>" target="_blank" style="font-size:11px; color:var(--info-text); text-decoration:none;">📄 Transkrip</a>
                    <?php endif; ?>
                    <?php if($r['file_proposal']): ?>
                        <a href="<?= e(fileUrl($r['file_proposal'])) ?>" target="_blank" style="font-size:11px; color:var(--info-text); text-decoration:none;">📄 Proposal</a>
                    <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>