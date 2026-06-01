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
$bimbinganAktif = fetchOne('SELECT no_sk, file_sk FROM bimbingan WHERE id_mahasiswa=? AND status="aktif" LIMIT 1', [$eid]);

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

    if (empty($judul_utama) || empty($judul_alt1) || empty($bidang) || empty($metode) || empty($latar)) {
        setFlash('danger', 'Judul Utama, Judul Alternatif 1, Bidang, Metode, dan Latar Belakang wajib diisi!');
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
            setFlash('success', 'Pengajuan dengan opsi judul berhasil dikirim! Menunggu verifikasi Kaprodi.');
            header('Location: ' . BASE_URL . '/mahasiswa/pengajuan.php');
            exit;
        } catch (\Exception $e) {
            $db->rollBack();
            setFlash('danger', 'Gagal: ' . $e->getMessage());
        }
    }
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">

  <?php $flash = getFlash(); if ($flash): ?>
    <div class="alert <?= $flash['type'] ?>"><?= e($flash['msg']) ?></div>
  <?php endif; ?>

  <div class="page-header">
    <h1>Pengajuan Judul Skripsi</h1>
    <p>Tahap awal: Ajukan 2-3 alternatif judul dan kelengkapan administrasi.</p>
  </div>

  <?php if ($bimbinganAktif): ?>
    <div class="alert success" style="padding:24px; display:flex; flex-direction:column; align-items:center; text-align:center;">
        <div style="font-size:40px; margin-bottom:10px;">🎉</div>
        <h2 style="margin:0 0 10px 0; color:var(--navy);">Selamat! SK Bimbingan Anda Telah Terbit</h2>
        <p style="margin:0 0 20px 0; font-size:14px; color:var(--slate); max-width:500px;">
            Judul skripsi Anda telah disetujui dan Dosen Pembimbing telah ditetapkan oleh Program Studi. Seluruh akses ke fitur bimbingan skripsi kini telah <b>dibuka</b>.
        </p>
        <div style="background:var(--white); border:1px solid var(--cream-dark); padding:16px; border-radius:8px; display:inline-flex; gap:20px; align-items:center;">
            <div>
                <div style="font-size:11px; color:var(--slate);">Nomor SK Bimbingan</div>
                <div style="font-weight:700; color:var(--navy);"><?= e($bimbinganAktif['no_sk']) ?></div>
            </div>
            <?php if($bimbinganAktif['file_sk']): ?>
                <a href="<?= BASE_URL ?>/<?= e($bimbinganAktif['file_sk']) ?>" target="_blank" class="btn btn-primary" style="font-size:12px;">⬇️ Unduh SK Bimbingan</a>
            <?php endif; ?>
        </div>
    </div>
  <?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 340px;gap:20px; margin-top:20px;">
    <div class="form-card">
      <?php if ($judulDisetujui): ?>
          <?php if (!$bimbinganAktif): ?>
              <div class="alert info">
                 <strong>Judul Disetujui!</strong> Pengajuan ditutup. Silakan tunggu Program Studi mem-plotting Dosen Pembimbing dan menerbitkan SK Anda.
              </div>
          <?php endif; ?>
      <?php else: ?>
          <h2>Formulir Pengajuan (Opsi Judul)</h2>
          <p style="color:var(--slate); font-size:13px; margin-bottom:20px;">Anda wajib mengajukan minimal 2 judul penelitian.</p>
          
          <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
              <label class="form-label">Judul Utama <span style="color:red">*</span></label>
              <input class="form-input" name="judul_indonesia" required placeholder="Judul Prioritas Pertama" value="<?= e($_POST['judul_indonesia'] ?? '') ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Alternatif Judul 1 <span style="color:red">*</span></label>
                  <input class="form-input" name="judul_alternatif_1" required placeholder="Opsi Kedua">
                </div>
                <div class="form-group">
                  <label class="form-label">Alternatif Judul 2</label>
                  <input class="form-input" name="judul_alternatif_2" placeholder="Opsi Ketiga (Opsional)">
                </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Konsentrasi / Bidang Ilmu <span style="color:red">*</span></label>
                <select class="form-input" name="bidang_penelitian" required>
                  <option value="" disabled selected>Pilih Bidang...</option>
                  <?php foreach (['Rekayasa Perangkat Lunak & Web','Pemrograman Mobile','Kecerdasan Buatan & Machine Learning','Data Science & Analitik','Internet of Things (IoT)','Keamanan Siber & Jaringan','Manajemen Sistem Informasi'] as $b): ?>
                    <option value="<?= $b ?>"><?= $b ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Metode Penelitian <span style="color:red">*</span></label>
                <select class="form-input" name="metode_penelitian" required>
                  <option value="" disabled selected>Pilih Metode...</option>
                  <?php foreach (['Agile Software Development','Waterfall Model','RAD (Rapid Application Development)','Prototyping','CRISP-DM (Data Science)','Riset Eksperimental','Kuantitatif','Kualitatif','Lainnya'] as $m): ?>
                    <option value="<?= $m ?>"><?= $m ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Ringkasan Latar Belakang (Untuk ke-3 Judul) <span style="color:red">*</span></label>
              <textarea class="form-input" name="latar_belakang" required rows="5" placeholder="Gambarkan secara singkat urgensi masalah dari judul-judul di atas..."></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Berkas Syarat (Transkrip/KRS) <span style="color:red">*</span></label>
                  <input type="file" name="file_transkrip" accept=".pdf" class="form-input" style="padding:8px;" required>
                  <span style="font-size:11px;color:var(--slate);">Wajib PDF, Maks 5MB.</span>
                </div>
                <div class="form-group">
                  <label class="form-label">Draft Proposal (Bila ada)</label>
                  <input type="file" name="file_proposal" accept=".pdf,.docx,.doc" class="form-input" style="padding:8px;">
                  <span style="font-size:11px;color:var(--slate);">Opsional. PDF/DOCX.</span>
                </div>
            </div>

            <div class="form-actions" style="margin-top:20px;">
              <button type="submit" class="btn btn-primary btn-full">Kirim Pengajuan</button>
            </div>
          </form>
      <?php endif; ?>
    </div>

    <div>
      <div class="card">
        <div class="card-header"><div class="card-title">Status Ajuan Terakhir</div></div>
        <div class="card-body">
          <?php if (empty($riwayat)): ?>
            <div class="empty-state"><div class="empty-sub">Belum ada data.</div></div>
          <?php else: ?>
            <?php foreach ($riwayat as $r): ?>
              <div style="border:1px solid var(--cream-dark);border-radius:8px;padding:14px;margin-bottom:10px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                    <span style="font-size:11px; font-weight:600; color:var(--slate);">Iterasi ke-<?= $r['iterasi'] ?></span>
                    <span class="badge <?= badge($r['status']) ?>"><span class="badge-dot"></span><?= e(strtoupper($r['status'])) ?></span>
                </div>
                
                <div style="font-size:11px; color:var(--slate); margin-bottom:4px;">1. <?= e($r['judul_indonesia']) ?></div>
                <div style="font-size:11px; color:var(--slate); margin-bottom:4px;">2. <?= e($r['judul_alternatif_1']) ?></div>
                <?php if($r['judul_alternatif_2']): ?>
                    <div style="font-size:11px; color:var(--slate); margin-bottom:10px;">3. <?= e($r['judul_alternatif_2']) ?></div>
                <?php endif; ?>

                <div style="display:flex; gap:10px; margin-top:12px; border-top:1px dashed var(--cream-dark); padding-top:10px;">
                    <?php if($r['file_transkrip']): ?>
                        <a href="<?= BASE_URL ?>/<?= $r['file_transkrip'] ?>" target="_blank" style="font-size:11px; color:var(--info); text-decoration:none;">📄 Transkrip</a>
                    <?php endif; ?>
                    <?php if($r['file_proposal']): ?>
                        <a href="<?= BASE_URL ?>/<?= $r['file_proposal'] ?>" target="_blank" style="font-size:11px; color:var(--info); text-decoration:none;">📄 Proposal</a>
                    <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
    
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>