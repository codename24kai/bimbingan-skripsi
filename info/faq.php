<?php
// info/faq.php — Pertanyaan umum (S3) dan kontak bantuan (S8)
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireLogin();

$pageTitle  = 'FAQ & Bantuan';
$activePage = 'faq';

$kuota  = pengaturan('default_kuota_dosen', '10');
$kontak = tabelAda('pengumuman') ? pengaturan('kontak_prodi', '') : '';

$faq = [
    'Judul & pembimbing' => [
        ['Berapa judul yang harus saya ajukan?', 'Satu judul utama dan dua judul alternatif. Jika judul utama kurang cocok, penilai bisa memilih salah satu alternatif tanpa Anda mengajukan ulang.'],
        ['Bagaimana jika judul saya mirip dengan judul lain?', 'Saat mengetik judul, sistem menampilkan judul lain yang mirip. Ubah fokus, objek, atau metode penelitian agar berbeda. Kemiripan tinggi bisa membuat judul diminta revisi.'],
        ['Bisakah saya mengganti judul setelah disetujui?', 'Bisa, tetapi harus lewat bagian akademik dan pembimbing karena SK pembimbing mengacu pada judul. Hubungi admin prodi.'],
        ['Mengapa dosen yang saya inginkan tidak bisa dipilih?', "Setiap dosen punya kuota bimbingan (bawaan $kuota mahasiswa). Jika kuotanya penuh, pilih dosen lain dengan bidang keahlian serupa di Direktori Dosen."],
        ['Bisakah saya mengganti dosen pembimbing?', 'Penggantian pembimbing hanya dilakukan admin atas dasar alasan yang jelas, misalnya dosen cuti panjang. Ajukan permohonan tertulis ke prodi.'],
    ],
    'Bimbingan & dokumen' => [
        ['Format file apa yang diterima?', 'Dokumen bab: PDF atau DOCX, maksimal 10 MB. Berkas seminar atau sidang: PDF atau ZIP. Template resmi ada di menu Template Dokumen.'],
        ['Bagaimana cara mengunggah revisi?', 'Unggah ulang dengan memilih bab yang sama. Sistem otomatis membuat versi baru, sehingga dosen bisa membandingkan versi lama dan baru.'],
        ['Berapa kali minimal bimbingan sebelum sidang?', 'Ikuti ketentuan prodi. Jumlah sesi yang sudah selesai tercatat di kartu bimbingan yang bisa Anda cetak dari Riwayat Bimbingan.'],
        ['Jadwal saya belum dikonfirmasi dosen, apa yang harus dilakukan?', 'Pilih slot konsultasi yang dibuka dosen agar langsung terkonfirmasi. Jika tetap tidak ada slot, tanyakan melalui diskusi dokumen atau hubungi dosen langsung.'],
    ],
    'Akun' => [
        ['Saya lupa kata sandi.', 'Klik "Lupa kata sandi?" di halaman login, isi email dan NIM atau NIP. Admin akan membuat kata sandi sementara dan menyampaikannya langsung kepada Anda.'],
        ['Mengapa saya diminta mengganti kata sandi saat login?', 'Akun baru dan akun yang direset admin memakai kata sandi sementara. Demi keamanan, Anda wajib menggantinya dengan kata sandi minimal 8 karakter.'],
        ['Data saya (nama, NIM, prodi) salah.', 'Nama dan nomor telepon bisa diubah di Profil Saya. NIM, prodi, dan email hanya bisa diubah admin.'],
    ],
];

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="layout"><main class="main">
<?php include __DIR__ . '/../includes/topbar.php'; ?>
<div class="content">
  <div class="page-header">
    <h1>FAQ & Bantuan</h1>
    <p>Jawaban untuk pertanyaan yang paling sering diajukan</p>
  </div>

  <div class="split" style="--side:320px;">
    <div>
      <div class="search-bar mb-20">
        <div class="search-input-wrap" style="flex:1;">
          <span class="search-icon" aria-hidden="true">🔍</span>
          <label for="cariFaq" class="sr-only">Cari pertanyaan</label>
          <input id="cariFaq" class="form-input" type="search" placeholder="Cari pertanyaan, mis. kata sandi">
        </div>
      </div>

      <?php foreach ($faq as $grup => $daftar): ?>
        <div class="card mb-20 faq-grup">
          <div class="card-header"><div class="card-title"><?= e($grup) ?></div></div>
          <div class="card-body">
            <?php foreach ($daftar as [$q, $a]): ?>
              <details class="lipat faq-item">
                <summary><?= e($q) ?></summary>
                <div class="lipat-isi"><p><?= e($a) ?></p></div>
              </details>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
      <div id="faqKosong" class="card empty-state" style="display:none;padding:32px;">
        <div class="empty-title">Pertanyaan tidak ditemukan</div>
        <div class="empty-sub">Coba kata lain, atau hubungi bagian akademik.</div>
      </div>
    </div>

    <div>
      <div class="card" style="height:fit-content;">
        <div class="card-header"><div class="card-title">Masih butuh bantuan?</div></div>
        <div class="card-body">
          <?php if ($kontak): ?>
            <p class="fs-14" style="line-height:1.6;margin-bottom:12px;"><?= nl2br(e($kontak)) ?></p>
          <?php endif; ?>
          <p class="fs-13 text-slate" style="line-height:1.6;">Untuk urusan bimbingan, gunakan diskusi dokumen agar pertanyaan dan jawabannya tersimpan.</p>
          <div style="display:flex;flex-direction:column;gap:8px;margin-top:14px;">
            <a class="btn btn-secondary btn-sm" href="<?= BASE_URL ?>/info/panduan.php"><?= icon('map', 16) ?> Baca panduan</a>
            <a class="btn btn-secondary btn-sm" href="<?= BASE_URL ?>/info/template.php"><?= icon('download', 16) ?> Template dokumen</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
(() => {
  const input = document.getElementById('cariFaq');
  input?.addEventListener('input', () => {
    const q = input.value.trim().toLowerCase();
    let ada = 0;
    document.querySelectorAll('.faq-grup').forEach(g => {
      let n = 0;
      g.querySelectorAll('.faq-item').forEach(it => {
        const cocok = !q || it.textContent.toLowerCase().includes(q);
        it.style.display = cocok ? '' : 'none';
        if (cocok) { n++; if (q) it.open = true; }
      });
      g.style.display = n ? '' : 'none';
      ada += n;
    });
    document.getElementById('faqKosong').style.display = ada ? 'none' : '';
  });
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
