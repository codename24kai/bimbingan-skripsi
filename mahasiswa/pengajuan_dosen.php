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
                            
$bimbinganAktif = fetchOne('SELECT b.*, b.id_dosen AS id_dosen_sk FROM bimbingan b WHERE b.id_mahasiswa=? AND b.status="aktif" LIMIT 1', [$eid]);

// Profil lengkap satu dosen (untuk kartu pembimbing / usulan)
function profilDosen(int $idDosen): ?array {
    return fetchOne('SELECT d.*, u.nama_lengkap, u.email, ps.nama_prodi,
                            (SELECT COUNT(*) FROM bimbingan b WHERE b.id_dosen=d.id_dosen AND b.status="aktif") AS aktif
                     FROM dosen d JOIN user u ON u.id_user=d.id_user
                     JOIN program_studi ps ON ps.id_prodi=d.id_prodi
                     WHERE d.id_dosen=?', [$idDosen]);
}

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
        if (!fetchOne('SELECT d.id_dosen FROM dosen d JOIN user u ON u.id_user=d.id_user
                       WHERE d.id_dosen=? AND u.status="aktif" AND d.kuota_bimbingan > d.terisi', [$id_dosen_usul])) {
            setFlash('danger', 'Dosen yang dipilih tidak ditemukan atau kuota bimbingannya sudah penuh.');
            header('Location: ' . BASE_URL . '/mahasiswa/pengajuan_dosen.php');
            exit;
        }
        execute('UPDATE judul_skripsi SET usulan_dosen=? WHERE id_judul=?', [$id_dosen_usul, $judulDisetujui['id_judul']]);
        kirimNotifAdmin('Usulan pembimbing baru', (currentUser()['nama_lengkap'] ?? 'Mahasiswa') . ' mengusulkan dosen pembimbing dan menunggu penerbitan SK.', 'info', 'relasi', 'admin/relasi.php');
        setFlash('success', 'Usulan dosen pembimbing berhasil dikirim dan akan ditinjau oleh Kaprodi.');
        
        // Reload data
        $judulDisetujui = fetchOne('SELECT j.*, d.id_dosen, u.nama_lengkap AS nama_dosen_usulan FROM judul_skripsi j LEFT JOIN dosen d ON d.id_dosen = j.usulan_dosen LEFT JOIN user u ON u.id_user = d.id_user WHERE j.id_judul=?', [$judulDisetujui['id_judul']]);
    }
}

// Ambil daftar dosen yang kuotanya masih tersedia
$dosenList = fetchAll('SELECT d.id_dosen, u.nama_lengkap, (d.kuota_bimbingan-d.terisi) AS sisa, d.keahlian 
                       FROM dosen d JOIN user u ON u.id_user=d.id_user 
                       WHERE d.kuota_bimbingan > d.terisi AND u.status = "aktif" 
                       ORDER BY u.nama_lengkap');

$pembimbing = $bimbinganAktif ? profilDosen((int)$bimbinganAktif['id_dosen_sk']) : null;
$dosenUsulan = (!$bimbinganAktif && $judulDisetujui['usulan_dosen']) ? profilDosen((int)$judulDisetujui['usulan_dosen']) : null;

// Kartu profil dosen
function kartuDosen(array $d, string $judulKartu, string $badge, string $badgeCls): void { ?>
  <div class="card dosen-card">
    <div class="card-header">
      <div class="card-title"><?= e($judulKartu) ?></div>
      <span class="badge <?= $badgeCls ?>"><span class="badge-dot"></span><?= e($badge) ?></span>
    </div>
    <div class="card-body">
      <div class="dosen-card__head">
        <div class="avatar dosen-card__avatar"><?= e(inisial($d['nama_lengkap'])) ?></div>
        <div class="min-w-0">
          <div class="dosen-card__nama"><?= e($d['nama_lengkap']) ?></div>
          <div class="muted"><?= e($d['jabatan_fungsional'] ?: 'Dosen') ?> · <?= e($d['nama_prodi']) ?></div>
        </div>
      </div>
      <div class="info-grid" style="margin-top:18px;">
        <div><div class="info-label">NIP / NIDN</div><div class="info-value mono"><?= e($d['nip']) ?></div></div>
        <div><div class="info-label">Bidang keahlian</div><div class="info-value"><?= e($d['keahlian'] ?: '-') ?></div></div>
        <div><div class="info-label">Email</div><div class="info-value"><a href="mailto:<?= e($d['email']) ?>" class="link"><?= e($d['email']) ?></a></div></div>
        <div><div class="info-label">Mahasiswa bimbingan</div><div class="info-value"><?= (int)$d['aktif'] ?> dari <?= (int)$d['kuota_bimbingan'] ?> kuota</div></div>
      </div>
    </div>
  </div>
<?php }

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">
  <?= tampilFlash() ?>

  <div class="page-header">
    <h1>Usulan Dosen Pembimbing</h1>
    <p><?= $bimbinganAktif ? 'Profil dosen pembimbing dan informasi SK bimbingan Anda.' : 'Ajukan rekomendasi dosen pembimbing yang sesuai dengan bidang penelitian Anda.' ?></p>
  </div>

  <?php if ($bimbinganAktif): ?>
    <div class="status-card status-card--success">
      <div class="status-card__icon"><?= icon('graduation', 26) ?></div>
      <div class="min-w-0" style="flex:1;">
        <div class="status-card__title">Dosen pembimbing sudah ditetapkan</div>
        <div class="muted">SK bimbingan sudah terbit, jadi usulan pembimbing ditutup. Hubungi pembimbing Anda lewat jadwal bimbingan.</div>
      </div>
    </div>

    <div class="split" style="--side:360px; margin-top:20px;">
      <?php if ($pembimbing) kartuDosen($pembimbing, 'Dosen Pembimbing Anda', 'Pembimbing', 'success'); ?>

      <div class="card">
        <div class="card-header"><div class="card-title">SK Bimbingan</div></div>
        <div class="card-body">
          <div class="info-grid info-grid--1">
            <div><div class="info-label">Nomor SK</div><div class="info-value"><?= e($bimbinganAktif['no_sk'] ?: '-') ?></div></div>
            <div><div class="info-label">Mulai bimbingan</div><div class="info-value"><?= tgl($bimbinganAktif['tgl_mulai'], 'lengkap') ?></div></div>
            <div><div class="info-label">Semester</div><div class="info-value"><?= e($bimbinganAktif['semester']) ?></div></div>
            <div><div class="info-label">Sesi selesai</div><div class="info-value"><?= (int)$bimbinganAktif['total_sesi'] ?> sesi</div></div>
          </div>
          <div class="info-label" style="margin-top:18px;">Judul</div>
          <div class="info-value" style="line-height:1.5;"><?= e($judulDisetujui['judul_indonesia']) ?></div>
          <div class="aksi-stack">
            <?php if ($bimbinganAktif['file_sk']): ?>
              <a href="<?= e(fileUrl($bimbinganAktif['file_sk'])) ?>" target="_blank" class="btn btn-primary btn-full"><?= icon('download', 16) ?> Unduh SK (PDF)</a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/mahasiswa/bimbingan.php" class="btn btn-secondary btn-full"><?= icon('calendar', 16) ?> Ajukan jadwal bimbingan</a>
          </div>
        </div>
      </div>
    </div>
  <?php else: ?>
  
    <div class="split" style="--side:340px;">
        <div class="form-card">
            <h2 style="font-size:16px; margin-bottom:6px;"><?= $dosenUsulan ? 'Ubah Usulan Pembimbing' : 'Pilih Rekomendasi Dosen' ?></h2>
            <p class="muted" style="margin-bottom:16px;"><?= $dosenUsulan ? 'Anda masih bisa mengganti usulan selama SK belum terbit.' : 'Pilih satu dosen. Dosen dengan bidang yang sama dengan judul Anda ditampilkan paling atas.' ?></p>
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
                
                <button type="submit" class="btn btn-primary btn-full"><?= $dosenUsulan ? 'Simpan Perubahan Usulan' : 'Kirim Usulan Pembimbing' ?></button>
            </form>
        </div>

        <div>
            <?php if ($dosenUsulan): ?>
              <?php kartuDosen($dosenUsulan, 'Usulan Anda', 'Menunggu SK', 'warning'); ?>
              <div style="height:20px;"></div>
            <?php else: ?>
              <div class="card" style="margin-bottom:20px;">
                <div class="card-body">
                  <div class="empty-state" style="padding:12px 0;"><div class="empty-sub">Anda belum mengusulkan pembimbing. Profil dosen yang Anda pilih akan tampil di sini.</div></div>
                </div>
              </div>
            <?php endif; ?>
            <div class="card" style="background:rgba(245, 166, 35, 0.05); border:1px solid var(--warning);">
                <div class="card-body">
                    <h3 style="font-size:14px; color:var(--warn-text); margin:0 0 10px 0;">Catatan penting</h3>
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