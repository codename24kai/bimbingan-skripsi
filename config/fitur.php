<?php
// ============================================================
//  config/fitur.php — Helper bersama untuk fitur-fitur baru
//  (notifikasi, unggah file, pengumuman, progres, kemiripan judul,
//  pengingat otomatis). Dimuat otomatis oleh config/auth.php.
// ============================================================

/** Kirim notifikasi ke satu pengguna. $tautan = path relatif dari BASE_URL, mis. "diskusi.php?dok=3". */
function kirimNotif(int $idUser, string $judul, string $pesan, string $tipe = 'info', ?string $modul = null, ?string $tautan = null): void {
    if ($idUser <= 0) return;
    execute('INSERT INTO notifikasi (id_user, judul, pesan, tipe, modul, tautan) VALUES (?,?,?,?,?,?)',
        [$idUser, $judul, $pesan, $tipe, $modul, $tautan]);
}

/** Kirim notifikasi ke semua admin aktif. */
function kirimNotifAdmin(string $judul, string $pesan, string $tipe = 'info', ?string $modul = null, ?string $tautan = null): void {
    foreach (fetchAll('SELECT id_user FROM user WHERE role="admin" AND status="aktif"') as $a) {
        kirimNotif((int)$a['id_user'], $judul, $pesan, $tipe, $modul, $tautan);
    }
}

/** Tautan notifikasi: file upload lewat download.php, selain itu halaman biasa. */
function tautanNotif(?string $tautan): ?array {
    if (!$tautan) return null;
    if (str_starts_with($tautan, 'uploads/')) return [fileUrl($tautan), 'Unduh lampiran'];
    return [BASE_URL . '/' . ltrim($tautan, '/'), 'Buka'];
}

/** Cek tabel ada (dipakai untuk memberi tahu jika database masih versi lama). */
function tabelAda(string $nama): bool {
    static $cache = [];
    if (!isset($cache[$nama])) {
        $cache[$nama] = countRows(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$nama]
        ) > 0;
    }
    return $cache[$nama];
}

/** Hentikan halaman dengan pesan ramah jika database masih versi lama. */
function wajibMigrasiFitur(): void {
    if (tabelAda('pengumuman')) return;
    http_response_code(500);
    exit('<div style="font-family:sans-serif;max-width:560px;margin:60px auto;line-height:1.6">'
        . '<h2>Database belum diperbarui</h2><p>Fitur ini membutuhkan tabel baru. Buka phpMyAdmin, lalu impor ulang file '
        . '<code>database/db_bimbingan_skripsi.sql</code> (tab Import) ke database <b>' . e(DB_NAME) . '</b>.</p>'
        . '<p><a href="' . BASE_URL . '/dashboard.php">Kembali ke dashboard</a></p></div>');
}

/**
 * Simpan file unggahan ke folder uploads/. Mengembalikan path "uploads/nama.ext".
 * Melempar Exception berisi pesan yang aman ditampilkan ke pengguna.
 */
function simpanUpload(array $file, string $prefix, array $ekstensi, int $maksKb = MAX_FILE_KB): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new Exception('Pilih file terlebih dahulu.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File gagal diunggah (kode ' . (int)$file['error'] . '). Coba lagi.');
    }
    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $ekstensi, true)) {
        throw new Exception('Format file harus ' . strtoupper(implode(', ', $ekstensi)) . '.');
    }
    if ($file['size'] > $maksKb * 1024) {
        throw new Exception('Ukuran file maksimal ' . round($maksKb / 1024) . ' MB.');
    }
    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true)) {
        throw new Exception('Folder uploads tidak bisa dibuat.');
    }
    $nama = preg_replace('/[^A-Za-z0-9_]/', '', $prefix) . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $nama)) {
        throw new Exception('File tidak bisa disimpan ke server.');
    }
    return 'uploads/' . $nama;
}

/** Hapus file fisik di uploads/ (abaikan jika tidak ada). */
function hapusUpload(?string $path): void {
    if ($path && preg_match('#^uploads/[A-Za-z0-9_\-]+\.[a-z0-9]+$#', $path)) {
        @unlink(UPLOAD_DIR . basename($path));
    }
}

/** Nilai dari tabel pengaturan. */
function pengaturan(string $key, string $default = ''): string {
    $row = fetchOne('SELECT nilai_value FROM pengaturan WHERE nama_key=?', [$key]);
    return $row['nilai_value'] ?? $default;
}

function simpanPengaturan(string $key, string $nilai): void {
    execute('INSERT INTO pengaturan (nama_key, nilai_value) VALUES (?,?) ON DUPLICATE KEY UPDATE nilai_value=VALUES(nilai_value)', [$key, $nilai]);
}

// ── Pengumuman ──────────────────────────────────────────────

/** id_prodi milik pengguna yang login (mahasiswa/dosen), atau null. */
function prodiPengguna(): ?int {
    $r = match (role()) {
        'mahasiswa' => fetchOne('SELECT id_prodi FROM mahasiswa WHERE id_mahasiswa=?', [entityId()]),
        'dosen'     => fetchOne('SELECT id_prodi FROM dosen WHERE id_dosen=?', [entityId()]),
        default     => null,
    };
    return $r ? (int)$r['id_prodi'] : null;
}

/** Pengumuman yang masih berlaku dan ditujukan ke pengguna yang login. */
function pengumumanUntukSaya(int $limit = 50): array {
    if (!tabelAda('pengumuman')) return [];
    $sql = 'SELECT p.*, ps.nama_prodi FROM pengumuman p
            LEFT JOIN program_studi ps ON ps.id_prodi = p.id_prodi
            WHERE (p.berlaku_sampai IS NULL OR p.berlaku_sampai >= CURDATE())';
    $params = [];
    if (role() !== 'admin') {
        $sql .= ' AND p.target_role IN ("semua", ?) AND (p.id_prodi IS NULL OR p.id_prodi = ?)';
        $params = [role(), prodiPengguna() ?? 0];
    }
    $sql .= ' ORDER BY p.disematkan DESC, p.created_at DESC LIMIT ' . max(1, $limit);
    return fetchAll($sql, $params);
}

// ── Progres skripsi per tahap (D7) ──────────────────────────

const TAHAP_BAB = ['BAB I', 'BAB II', 'BAB III', 'BAB IV', 'BAB V'];

/**
 * Hitung progres skripsi mahasiswa dari data yang ada.
 * Mengembalikan ['tahap' => [[nama, selesai(bool), keterangan], ...], 'persen' => 0-100, 'sekarang' => nama tahap].
 */
function progresSkripsi(int $idMhs): array {
    $judul = fetchOne('SELECT status FROM judul_skripsi WHERE id_mahasiswa=? ORDER BY id_judul DESC LIMIT 1', [$idMhs]);
    $bimb  = fetchOne('SELECT id_bimbingan, status FROM bimbingan WHERE id_mahasiswa=? AND status IN ("aktif","selesai") ORDER BY id_bimbingan DESC LIMIT 1', [$idMhs]);

    $babOk = [];
    foreach (fetchAll('SELECT DISTINCT jenis_bab FROM dokumen WHERE id_mahasiswa=? AND status="disetujui"', [$idMhs]) as $r) {
        $babOk[strtoupper(trim($r['jenis_bab']))] = true;
    }

    $ujian = [];
    if ($bimb && tabelAda('ujian')) {
        foreach (fetchAll('SELECT jenis, status, hasil, tgl_ujian FROM ujian WHERE id_bimbingan=? AND status<>"ditolak" ORDER BY id_ujian', [$bimb['id_bimbingan']]) as $u) {
            $ujian[$u['jenis']] = $u; // yang terakhir menang
        }
    }
    $lulus = fn(?array $u) => $u && $u['status'] === 'selesai' && in_array($u['hasil'], ['lulus', 'lulus_revisi'], true);
    $ketUjian = function (?array $u) {
        if (!$u) return 'Belum mendaftar';
        if ($u['status'] === 'selesai') return 'Hasil: ' . label($u['hasil']);
        if ($u['status'] === 'terjadwal') return 'Dijadwalkan ' . tgl($u['tgl_ujian']);
        return label($u['status']);
    };

    $tahap = [
        ['Judul disetujui', ($judul['status'] ?? '') === 'disetujui', $judul ? label($judul['status']) : 'Belum mengajukan'],
        ['SK pembimbing terbit', (bool)$bimb, $bimb ? 'Sudah terbit' : 'Menunggu penetapan'],
    ];
    foreach (TAHAP_BAB as $bab) {
        $tahap[] = [labelBab($bab) . ' disetujui', isset($babOk[$bab]), isset($babOk[$bab]) ? 'Disetujui pembimbing' : 'Belum disetujui'];
    }
    $sp = $ujian['seminar_proposal'] ?? null;
    $sa = $ujian['sidang_akhir'] ?? null;
    $tahap[] = ['Seminar proposal', $lulus($sp), $ketUjian($sp)];
    $tahap[] = ['Sidang akhir', $lulus($sa), $ketUjian($sa)];
    $tahap[] = ['Selesai', ($bimb['status'] ?? '') === 'selesai', ($bimb['status'] ?? '') === 'selesai' ? 'Bimbingan ditutup' : 'Belum selesai'];

    $jumlahSelesai = count(array_filter($tahap, fn($t) => $t[1]));
    $sekarang = 'Selesai';
    foreach ($tahap as $t) { if (!$t[1]) { $sekarang = $t[0]; break; } }

    return ['tahap' => $tahap, 'persen' => (int)round($jumlahSelesai / count($tahap) * 100), 'sekarang' => $sekarang];
}

/** Perbarui kolom mahasiswa.status_skripsi agar sesuai keadaan sebenarnya. */
function sinkronStatusSkripsi(int $idMhs): void {
    $b = fetchOne('SELECT status FROM bimbingan WHERE id_mahasiswa=? ORDER BY (status="aktif") DESC, id_bimbingan DESC LIMIT 1', [$idMhs]);
    $status = match ($b['status'] ?? null) {
        'aktif'   => 'proses',
        'selesai' => 'selesai',
        default   => 'belum',
    };
    execute('UPDATE mahasiswa SET status_skripsi=? WHERE id_mahasiswa=?', [$status, $idMhs]);
}

/** Bilah progres kecil untuk tabel. */
function barProgres(int $persen): string {
    $persen = max(0, min(100, $persen));
    return '<div class="progres-mini" title="' . $persen . '% selesai"><div class="progress-bar"><div class="progress-fill" style="width:' . $persen . '%"></div></div><span class="mono fs-12">' . $persen . '%</span></div>';
}

// ── Kemiripan judul (D11) ───────────────────────────────────

const KATA_UMUM = ['dan','yang','di','ke','dari','untuk','pada','dengan','dalam','sebagai','berbasis','menggunakan',
    'sistem','aplikasi','rancang','bangun','perancangan','implementasi','pengembangan','analisis','studi','kasus',
    'metode','terhadap','atau','the','of','and','for','a','an','in','on','web','informasi'];

function kataKunciJudul(string $judul): array {
    $judul = mb_strtolower($judul);
    $kata = preg_split('/[^a-z0-9]+/u', $judul, -1, PREG_SPLIT_NO_EMPTY);
    return array_values(array_unique(array_filter($kata, fn($k) => mb_strlen($k) > 2 && !in_array($k, KATA_UMUM, true))));
}

/**
 * Cari judul lain yang mirip. Skor 0-100 dari irisan kata kunci (Jaccard)
 * digabung dengan similar_text agar urutan kata ikut diperhitungkan.
 */
function judulMirip(string $judul, ?int $kecualiMhs = null, int $limit = 5, int $ambang = 35): array {
    $kunci = kataKunciJudul($judul);
    if (count($kunci) < 2) return [];
    $rows = fetchAll(
        'SELECT j.id_judul, j.judul_indonesia, j.status, j.tgl_pengajuan, u.nama_lengkap, m.nim, m.angkatan
         FROM judul_skripsi j
         JOIN mahasiswa m ON m.id_mahasiswa = j.id_mahasiswa
         JOIN user u ON u.id_user = m.id_user
         WHERE j.status IN ("disetujui","diajukan","revisi") AND j.id_mahasiswa <> ?',
        [$kecualiMhs ?? 0]
    );
    $hasil = [];
    foreach ($rows as $r) {
        $lain = kataKunciJudul($r['judul_indonesia']);
        if (!$lain) continue;
        $irisan = count(array_intersect($kunci, $lain));
        $gabung = count(array_unique(array_merge($kunci, $lain)));
        $jaccard = $gabung ? $irisan / $gabung * 100 : 0;
        similar_text(mb_strtolower($judul), mb_strtolower($r['judul_indonesia']), $persen);
        $skor = (int)round($jaccard * 0.6 + $persen * 0.4);
        if ($skor >= $ambang) {
            $r['skor'] = $skor;
            $hasil[] = $r;
        }
    }
    usort($hasil, fn($a, $b) => $b['skor'] <=> $a['skor']);
    return array_slice($hasil, 0, $limit);
}

// ── Pengingat otomatis (D10) ────────────────────────────────

/** Catat pengingat; true jika baru (belum pernah dikirim). */
function catatPengingat(string $jenis, int $refId, int $idUser, string $periode = ''): bool {
    $stmt = getDB()->prepare('INSERT IGNORE INTO pengingat_log (jenis, ref_id, id_user, periode) VALUES (?,?,?,?)');
    $stmt->execute([$jenis, $refId, $idUser, $periode]);
    return $stmt->rowCount() > 0;
}

/**
 * Kirim pengingat H-1 jadwal bimbingan/ujian dan peringatan mahasiswa yang lama
 * tidak bimbingan. Dijalankan paling banyak sekali per jam (dipicu saat dashboard
 * dibuka, atau lewat cron/pengingat.php dari Task Scheduler). Mengembalikan jumlah notifikasi.
 */
function jalankanPengingat(bool $paksa = false): int {
    if (!tabelAda('pengingat_log')) return 0;
    $terakhir = pengaturan('pengingat_terakhir');
    if (!$paksa && $terakhir && strtotime($terakhir) > time() - 3600) return 0;
    simpanPengaturan('pengingat_terakhir', date('Y-m-d H:i:s'));

    $jumlah = 0;

    // 1. Jadwal bimbingan besok (mahasiswa + dosen)
    $besok = fetchAll(
        'SELECT sb.id_sesi, sb.tgl_sesi, sb.waktu_mulai, sb.topik, sb.metode, sb.lokasi_link,
                um.id_user AS uid_mhs, um.nama_lengkap AS nama_mhs, ud.id_user AS uid_dsn, ud.nama_lengkap AS nama_dsn
         FROM sesi_bimbingan sb
         JOIN bimbingan b ON b.id_bimbingan = sb.id_bimbingan
         JOIN mahasiswa m ON m.id_mahasiswa = b.id_mahasiswa JOIN user um ON um.id_user = m.id_user
         JOIN dosen d ON d.id_dosen = b.id_dosen JOIN user ud ON ud.id_user = d.id_user
         WHERE sb.status IN ("dijadwalkan","dikonfirmasi") AND sb.tgl_sesi = DATE_ADD(CURDATE(), INTERVAL 1 DAY)'
    );
    foreach ($besok as $s) {
        $waktu = tgl($s['tgl_sesi'] . ' ' . $s['waktu_mulai'], 'harijam');
        if (catatPengingat('sesi_h1', (int)$s['id_sesi'], (int)$s['uid_mhs'])) {
            kirimNotif((int)$s['uid_mhs'], 'Pengingat: bimbingan besok',
                "Bimbingan dengan {$s['nama_dsn']} pada $waktu (" . label($s['metode']) . "). Topik: {$s['topik']}.",
                'info', 'jadwal', 'mahasiswa/bimbingan.php');
            $jumlah++;
        }
        if (catatPengingat('sesi_h1', (int)$s['id_sesi'], (int)$s['uid_dsn'])) {
            kirimNotif((int)$s['uid_dsn'], 'Pengingat: bimbingan besok',
                "Bimbingan dengan {$s['nama_mhs']} pada $waktu. Topik: {$s['topik']}.",
                'info', 'jadwal', 'dosen/jadwal.php');
            $jumlah++;
        }
    }

    // 2. Ujian besok (mahasiswa, pembimbing, penguji)
    if (tabelAda('ujian')) {
        $ujianBesok = fetchAll(
            'SELECT u.id_ujian, u.jenis, u.tgl_ujian, u.waktu_mulai, u.ruang, b.id_dosen,
                    um.id_user AS uid_mhs, um.nama_lengkap AS nama_mhs
             FROM ujian u JOIN bimbingan b ON b.id_bimbingan = u.id_bimbingan
             JOIN mahasiswa m ON m.id_mahasiswa = b.id_mahasiswa JOIN user um ON um.id_user = m.id_user
             WHERE u.status = "terjadwal" AND u.tgl_ujian = DATE_ADD(CURDATE(), INTERVAL 1 DAY)'
        );
        foreach ($ujianBesok as $u) {
            $pesan = label($u['jenis']) . ' ' . $u['nama_mhs'] . ' pada ' . tgl($u['tgl_ujian'] . ' ' . $u['waktu_mulai'], 'harijam') . ', ruang ' . ($u['ruang'] ?: '-') . '.';
            $penerima = [(int)$u['uid_mhs'] => 'mahasiswa/ujian.php'];
            $dosenIds = array_merge([(int)$u['id_dosen']], array_map('intval', array_column(fetchAll('SELECT id_dosen FROM penguji_ujian WHERE id_ujian=?', [$u['id_ujian']]), 'id_dosen')));
            foreach ($dosenIds as $idD) {
                $ud = fetchOne('SELECT id_user FROM dosen WHERE id_dosen=?', [$idD]);
                if ($ud) $penerima[(int)$ud['id_user']] = 'dosen/ujian.php';
            }
            foreach ($penerima as $uid => $link) {
                if (catatPengingat('ujian_h1', (int)$u['id_ujian'], $uid)) {
                    kirimNotif($uid, 'Pengingat: ' . label($u['jenis']) . ' besok', $pesan, 'peringatan', 'ujian', $link);
                    $jumlah++;
                }
            }
        }
    }

    // 3. Mahasiswa dengan bimbingan aktif yang lama tidak bimbingan
    $batas = max(7, (int)pengaturan('batas_tidak_aktif_hari', '30'));
    $periode = date('Y-m');
    $diam = fetchAll(
        'SELECT b.id_bimbingan, um.id_user AS uid_mhs, um.nama_lengkap AS nama_mhs, ud.id_user AS uid_dsn,
                GREATEST(b.tgl_mulai, COALESCE(MAX(sb.tgl_sesi), b.tgl_mulai)) AS terakhir
         FROM bimbingan b
         JOIN mahasiswa m ON m.id_mahasiswa = b.id_mahasiswa JOIN user um ON um.id_user = m.id_user
         JOIN dosen d ON d.id_dosen = b.id_dosen JOIN user ud ON ud.id_user = d.id_user
         LEFT JOIN sesi_bimbingan sb ON sb.id_bimbingan = b.id_bimbingan AND sb.status <> "batal"
         WHERE b.status = "aktif"
         GROUP BY b.id_bimbingan
         HAVING terakhir < DATE_SUB(CURDATE(), INTERVAL ? DAY)', [$batas]
    );
    foreach ($diam as $r) {
        $hari = (int)floor((time() - strtotime($r['terakhir'])) / 86400);
        if (catatPengingat('tidak_aktif', (int)$r['id_bimbingan'], (int)$r['uid_mhs'], $periode)) {
            kirimNotif((int)$r['uid_mhs'], 'Sudah lama tidak bimbingan',
                "Bimbingan terakhir Anda $hari hari lalu. Yuk ajukan jadwal bimbingan berikutnya.", 'peringatan', 'jadwal', 'mahasiswa/bimbingan.php');
            $jumlah++;
        }
        if (catatPengingat('tidak_aktif', (int)$r['id_bimbingan'], (int)$r['uid_dsn'], $periode)) {
            kirimNotif((int)$r['uid_dsn'], 'Mahasiswa tidak aktif',
                "{$r['nama_mhs']} belum bimbingan selama $hari hari.", 'peringatan', 'jadwal', 'dosen/mahasiswa.php');
            $jumlah++;
        }
    }
    return $jumlah;
}

// ── Akses dokumen (diskusi & perbandingan versi) ────────────

/**
 * Ambil dokumen beserta pemilik dan pembimbingnya jika pengguna yang login
 * berhak (mahasiswa pemilik atau dosen pembimbingnya). Null jika tidak berhak.
 */
function aksesDokumen(int $idDok): ?array {
    $dok = fetchOne(
        'SELECT d.*, um.nama_lengkap AS nama_mhs, um.id_user AS uid_mhs, m.nim
         FROM dokumen d JOIN mahasiswa m ON m.id_mahasiswa = d.id_mahasiswa JOIN user um ON um.id_user = m.id_user
         WHERE d.id_dokumen = ?', [$idDok]
    );
    if (!$dok) return null;
    $pb = fetchOne(
        'SELECT b.id_dosen, u.id_user, u.nama_lengkap FROM bimbingan b JOIN dosen d ON d.id_dosen = b.id_dosen JOIN user u ON u.id_user = d.id_user
         WHERE b.id_mahasiswa = ? ORDER BY (b.status = "aktif") DESC, b.id_bimbingan DESC LIMIT 1', [$dok['id_mahasiswa']]
    );
    $dok['pembimbing'] = $pb;
    $boleh = (role() === 'mahasiswa' && (int)$dok['id_mahasiswa'] === entityId())
          || (role() === 'dosen' && $pb && (int)$pb['id_dosen'] === entityId());
    return $boleh ? $dok : null;
}

// ── Seminar, sidang, dan nilai (D9, D16) ────────────────────

const JENIS_UJIAN = ['seminar_proposal', 'sidang_akhir'];
const RUBRIK_NILAI = [
    'n_penulisan'  => 'Penulisan naskah',
    'n_presentasi' => 'Presentasi',
    'n_penguasaan' => 'Penguasaan materi',
    'n_metodologi' => 'Metodologi & hasil',
];

/** Nilai angka → huruf (skala umum; sesuaikan dengan pedoman kampus). */
function nilaiHuruf(?float $n): string {
    if ($n === null) return '-';
    return match (true) {
        $n >= 85 => 'A', $n >= 80 => 'A-', $n >= 75 => 'B+', $n >= 70 => 'B',
        $n >= 65 => 'B-', $n >= 60 => 'C+', $n >= 55 => 'C', $n >= 45 => 'D',
        default  => 'E',
    };
}

/** Nilai yang sudah diisi tiap dosen untuk satu ujian, plus rata-rata keseluruhan. */
function nilaiUjian(int $idUjian): array {
    $rows = fetchAll('SELECT n.*, u.nama_lengkap FROM nilai_ujian n JOIN dosen d ON d.id_dosen = n.id_dosen JOIN user u ON u.id_user = d.id_user
                      WHERE n.id_ujian = ? ORDER BY n.peran DESC, u.nama_lengkap', [$idUjian]);
    $total = 0;
    foreach ($rows as &$r) {
        $r['rata'] = array_sum(array_map(fn($k) => (int)$r[$k], array_keys(RUBRIK_NILAI))) / count(RUBRIK_NILAI);
        $total += $r['rata'];
    }
    unset($r);
    return ['baris' => $rows, 'rata' => $rows ? round($total / count($rows), 2) : null];
}

/** Penguji suatu ujian: [[id_dosen, nama, peran], ...] */
function pengujiUjian(int $idUjian): array {
    return fetchAll('SELECT pu.id_dosen, pu.peran, u.nama_lengkap FROM penguji_ujian pu JOIN dosen d ON d.id_dosen = pu.id_dosen
                     JOIN user u ON u.id_user = d.id_user WHERE pu.id_ujian = ? ORDER BY pu.peran DESC, u.nama_lengkap', [$idUjian]);
}

/** Kotak peringatan judul mirip untuk halaman validasi. */
function kotakJudulMirip(string $judul, int $idMhs): string {
    $mirip = judulMirip($judul, $idMhs, 3);
    if (!$mirip) return '';
    $html = '<div class="judul-mirip"><div class="judul-mirip__judul">' . icon('alert', 16) . ' Mirip dengan judul lain</div><ul>';
    foreach ($mirip as $m) {
        $html .= '<li><span class="badge ' . ($m['skor'] >= 60 ? 'danger' : 'warning') . '">' . (int)$m['skor'] . '%</span> '
            . e($m['judul_indonesia']) . ' <span class="meta">· ' . e($m['nama_lengkap']) . ', ' . e(label($m['status'])) . '</span></li>';
    }
    return $html . '</ul></div>';
}

// ── Pilihan baku (harus sama dengan ENUM di database) ───────

const JABATAN_DOSEN = ['Tenaga Pengajar', 'Asisten Ahli', 'Lektor', 'Lektor Kepala', 'Guru Besar'];
const KEAHLIAN_DOSEN = ['Rekayasa Perangkat Lunak & Web', 'Pemrograman Mobile', 'Kecerdasan Buatan & Machine Learning',
    'Data Science & Analitik', 'Internet of Things (IoT)', 'Keamanan Siber & Jaringan', 'Manajemen Sistem Informasi'];
const METODE_PENELITIAN = ['Agile Software Development', 'Waterfall Model', 'RAD (Rapid Application Development)', 'Prototyping',
    'CRISP-DM (Data Science)', 'Riset Eksperimental', 'Kuantitatif', 'Kualitatif', 'Lainnya'];
const JENIS_BAB = ['BAB I', 'BAB II', 'BAB III', 'BAB IV', 'BAB V', 'Proposal', 'Lampiran', 'Lainnya'];

/** Validasi tanggal Y-m-d yang benar-benar ada di kalender. */
function tanggalValid(string $tgl): bool {
    $d = DateTime::createFromFormat('!Y-m-d', $tgl);
    return $d && $d->format('Y-m-d') === $tgl;
}

/** Validasi jam HH:MM (opsional :SS). */
function jamValid(string $jam): bool {
    return preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $jam) === 1;
}

// ── Notifikasi terkait halaman ──────────────────────────────

/**
 * Tandai sudah dibaca semua notifikasi milik pengguna yang tautannya menuju
 * halaman tertentu (path relatif dari BASE_URL, tanpa #fragmen).
 */
function tandaiNotifDibaca(array $tautan): void {
    $tautan = array_values(array_unique(array_filter($tautan)));
    if (!$tautan || !userId()) return;
    $ph = implode(',', array_fill(0, count($tautan), '?'));
    execute("UPDATE notifikasi SET is_read=1 WHERE id_user=? AND is_read=0 AND SUBSTRING_INDEX(tautan,'#',1) IN ($ph)",
        array_merge([userId()], $tautan));
}

/** Path halaman yang sedang dibuka relatif dari BASE_URL, mis. "diskusi.php?dok=3". */
function halamanIni(): string {
    $basePath = rtrim((string)parse_url(BASE_URL, PHP_URL_PATH), '/');
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $path = (string)parse_url($uri, PHP_URL_PATH);
    $query = (string)parse_url($uri, PHP_URL_QUERY);
    if ($basePath !== '' && str_starts_with($path, $basePath . '/')) {
        $path = substr($path, strlen($basePath) + 1);
    } else {
        $path = ltrim($path, '/');
    }
    return $path . ($query !== '' ? '?' . $query : '');
}

/**
 * Jumlah pesan diskusi yang belum dibaca pengguna, per (id_mahasiswa|jenis_bab).
 * Sumbernya notifikasi "Pesan baru" yang belum dibaca, sehingga angka di tombol
 * diskusi selalu sama dengan lonceng notifikasi dan hilang begitu utas dibuka.
 */
function pesanBelumDibaca(): array {
    $rows = fetchAll(
        "SELECT d.id_mahasiswa, d.jenis_bab, COUNT(*) AS n
         FROM notifikasi n
         JOIN dokumen d ON d.id_dokumen = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(n.tautan,'#',1),'dok=',-1) AS UNSIGNED)
         WHERE n.id_user = ? AND n.is_read = 0 AND n.modul = 'diskusi' AND n.tautan LIKE 'diskusi.php?dok=%'
         GROUP BY d.id_mahasiswa, d.jenis_bab", [userId()]);
    $hasil = [];
    foreach ($rows as $r) $hasil[$r['id_mahasiswa'] . '|' . $r['jenis_bab']] = (int)$r['n'];
    return $hasil;
}
