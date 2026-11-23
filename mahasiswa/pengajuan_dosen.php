<?php
// mahasiswa/pengajuan_dosen.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('mahasiswa');

$pageTitle  = 'Usulan Pembimbing';
$activePage = 'usulan_dosen';
$eid        = entityId();

// Cek status judul dan bimbingan
$judulDisetujui = fetchOne('SELECT j.*, d.id_dosen, u.nama_lengkap AS nama_dosen_usulan 
                            FROM judul_skripsi j 
                            LEFT JOIN dosen d ON d.id_dosen = j.usulan_dosen
                            LEFT JOIN user u ON u.id_user = d.id_user
                            WHERE j.id_mahasiswa=? AND j.status="disetujui" LIMIT 1', [$eid]);
                            
$bimbinganAktif = fetchOne('SELECT no_sk FROM bimbingan WHERE id_mahasiswa=? AND status="aktif" LIMIT 1', [$eid]);

// Jika mahasiswa mencoba mengakses via URL padahal belum punya judul ACC
if (!$judulDisetujui) {
    setFlash('warning', 'Akses ditolak. Anda belum memiliki judul skripsi yang disetujui.');
    header('Location: ' . BASE_URL . '/mahasiswa/pengajuan.php');
    exit;
}

// Handle Form Usulan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_dosen'])) {
    if ($bimbinganAktif) {
        setFlash('danger', 'Gagal: SK Bimbingan Anda sudah terbit, tidak dapat mengubah usulan dosen lagi.');
    } else {
        $id_dosen_usul = (int)$_POST['id_dosen'];
        if (!fetchOne('SELECT id_dosen FROM dosen WHERE id_dosen=?', [$id_dosen_usul])) {
            setFlash('danger', 'Dosen yang dipilih tidak ditemukan.');
            header('Location: ' . BASE_URL . '/mahasiswa/pengajuan_dosen.php');
            exit;
        }
        execute('UPDATE judul_skripsi SET usulan_dosen=? WHERE id_judul=?', [$id_dosen_usul, $judulDisetujui['id_judul']]);
        setFlash('success', 'Usulan dosen pembimbing berhasil dikirim dan akan ditinjau oleh Kaprodi.');
        
        // Reload data
        $judulDisetujui = fetchOne('SELECT j.*, d.id_dosen, u.nama_lengkap AS nama_dosen_usulan FROM judul_skripsi j LEFT JOIN dosen d ON d.id_dosen = j.usulan_dosen LEFT JOIN user u ON u.id_user = d.id_user WHERE j.id_judul=?', [$judulDisetujui['id_judul']]);
    }
}

// Ambil daftar dosen yang kuotanya masih tersedia
$dosenList = fetchAll('SELECT d.id_dosen, u.nama_lengkap, (d.kuota_bimbingan-d.terisi) AS sisa, d.keahlian 
                       FROM dosen d JOIN user u ON u.id_user=d.id_user 
                       WHERE d.kuota_bimbingan > d.terisi 
                       ORDER BY u.nama_lengkap');

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">
  <?php $flash = getFlash(); if ($flash): ?><div class="alert <?= $flash['type'] ?>"><?= e($flash['msg']) ?></div><?php endif; ?>

  <div class="page-header">
    <h1>Usulan Dosen Pembimbing</h1>
    <p>Ajukan rekomendasi dosen pembimbing yang sesuai dengan bidang penelitian Anda.</p>
  </div>

  <?php if ($bimbinganAktif): ?>
    <div class="alert alert-static success" style="padding:20px; text-align:center;">
        <div style="font-size:30px; margin-bottom:10px;">🎓</div>
        <h3 style="margin:0 0 10px 0; color:var(--navy);">Dosen Pembimbing Telah Ditetapkan</h3>
        <p style="margin:0; font-size:13px; color:var(--slate);">SK Bimbingan Anda sudah terbit (No: <b><?= e($bimbinganAktif['no_sk']) ?></b>). Proses usulan telah ditutup.</p>
    </div>
  <?php else: ?>
  
    <div class="split" style="--side:340px;">
        <div class="form-card">
            <?php if ($judulDisetujui['usulan_dosen']): ?>
                <div class="alert alert-static info mb-20">
                    <strong>⭐ Usulan Tersimpan:</strong> Anda telah mengusulkan <b><?= e($judulDisetujui['nama_dosen_usulan']) ?></b> sebagai pembimbing. Prodi akan meninjau ketersediaan kuota. Anda masih dapat mengubah usulan selama SK belum terbit.
                </div>
            <?php endif; ?>

            <h2 style="font-size:16px; margin-bottom:16px;">Pilih Rekomendasi Dosen</h2>
            <form method="POST"><?= csrfField() ?>
                <div class="form-group">
                    <label class="form-label">Judul Skripsi (Disetujui)</label>
                    <div style="padding:10px; background:var(--cream); border-radius:6px; font-size:13px; font-weight:500; line-height:1.4;">
                        <?= e($judulDisetujui['judul_indonesia']) ?>
                        <div style="font-size:11px; color:var(--info-text); margin-top:4px;">Bidang: <?= e($judulDisetujui['bidang_penelitian']) ?></div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="f_id_dosen" class="form-label">Dosen Pembimbing Tersedia</label>
                    <select id="f_id_dosen" class="form-input" name="id_dosen" required size="8" style="padding:10px;">
                        
                        <optgroup label="🌟 Rekomendasi Sesuai Bidang (<?= e($judulDisetujui['bidang_penelitian']) ?>)">
                            <?php 
                            $adaRekom = false;
                            foreach ($dosenList as $d) {
                                if ($d['keahlian'] === $judulDisetujui['bidang_penelitian']) {
                                    $sel = ($d['id_dosen'] == $judulDisetujui['usulan_dosen']) ? 'selected' : '';
                                    echo '<option value="'.$d['id_dosen'].'" '.$sel.'>✅ '.e($d['nama_lengkap']).' (Sisa Kuota: '.$d['sisa'].')</option>';
                                    $adaRekom = true;
                                }
                            }
                            if (!$adaRekom) echo '<option disabled>Tidak ada dosen dengan bidang ini (atau kuota penuh)</option>';
                            ?>
                        </optgroup>

                        <optgroup label="👨‍🏫 Dosen Bidang Lainnya">
                            <?php 
                            foreach ($dosenList as $d) {
                                if ($d['keahlian'] !== $judulDisetujui['bidang_penelitian']) {
                                    $sel = ($d['id_dosen'] == $judulDisetujui['usulan_dosen']) ? 'selected' : '';
                                    echo '<option value="'.$d['id_dosen'].'" '.$sel.'>'.e($d['nama_lengkap']).' (Bidang: '.e($d['keahlian']).')</option>';
                                }
                            }
                            ?>
                        </optgroup>

                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary btn-full">Kirim Usulan Pembimbing</button>
            </form>
        </div>

        <div>
            <div class="card" style="background:rgba(245, 166, 35, 0.05); border:1px solid var(--warning);">
                <div class="card-body">
                    <h3 style="font-size:14px; color:var(--warning); margin:0 0 10px 0;">📌 Catatan Penting</h3>
                    <ul style="font-size:12px; color:var(--slate); padding-left:16px; margin:0; line-height:1.6;">
                        <li>Usulan ini bersifat <b>rekomendasi</b>. Keputusan akhir penetapan dosen pembimbing sepenuhnya berada di tangan Ketua Program Studi.</li>
                        <li>Prodi akan mempertimbangkan sisa kuota bimbingan dari dosen yang Anda usulkan.</li>
                        <li>Jika usulan Anda disetujui, SK Bimbingan akan diterbitkan dan Anda akan menerima notifikasi.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
  <?php endif; ?>

</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>