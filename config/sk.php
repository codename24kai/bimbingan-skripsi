<?php
// ============================================================
//  config/sk.php — Penerbitan SK Bimbingan otomatis:
//  nomor SK berurutan per tahun, kode verifikasi, dan file PDF.
//  Butuh tabel sk_nomor & kolom SK di database/db_bimbingan_skripsi.sql.
// ============================================================
require_once __DIR__ . '/pdf.php';

/** Awalan nomor SK. Nomor lengkap: UOB/SCDT/SK-BIMBINGAN/<tahun>/<urut 4 digit>. */
const SK_AWALAN = 'UOB/SCDT/SK-BIMBINGAN';

/** Nilai bawaan kop & penandatangan (bisa diubah di Pengaturan). */
const SK_BAWAAN = [
    'sk_institusi'     => 'University of Birmingham',
    'sk_unit'          => 'School of Computing and Digital Technology',
    'sk_alamat'        => 'Edgbaston, Birmingham B15 2TT, United Kingdom',
    'sk_tempat'        => 'Birmingham, United Kingdom',
    'sk_penandatangan' => 'Dr. Sarah J. Williams',
    'sk_jabatan_ttd'   => 'Dean of School of Computing and Digital Technology',
];

function skSiap(): bool {
    return tabelAda('sk_nomor');
}

function pengaturanSk(string $key): string {
    $v = trim(pengaturan($key, ''));
    return $v !== '' ? $v : SK_BAWAAN[$key];
}

function formatNomorSk(int $tahun, int $urut): string {
    return sprintf('%s/%d/%04d', SK_AWALAN, $tahun, $urut);
}

/** Pratinjau nomor berikutnya (tanpa mengunci; nomor final ditentukan saat terbit). */
function nomorSkBerikutnya(?int $tahun = null): string {
    $tahun ??= (int)date('Y');
    $row = fetchOne('SELECT terakhir FROM sk_nomor WHERE tahun=?', [$tahun]);
    return formatNomorSk($tahun, (int)($row['terakhir'] ?? 0) + 1);
}

/**
 * Ambil nomor urut berikutnya untuk $tahun secara atomik. WAJIB dipanggil di
 * dalam transaksi: baris penghitung terkunci sampai commit sehingga dua admin
 * yang menerbitkan bersamaan tidak mendapat nomor yang sama, dan nomor tidak
 * terpakai bila transaksi dibatalkan.
 */
function ambilNomorSk(int $tahun): int {
    for ($coba = 0; $coba < 50; $coba++) {
        execute('INSERT INTO sk_nomor (tahun, terakhir) VALUES (?, LAST_INSERT_ID(1))
                 ON DUPLICATE KEY UPDATE terakhir = LAST_INSERT_ID(terakhir + 1)', [$tahun]);
        $urut = (int)fetchOne('SELECT LAST_INSERT_ID() AS n')['n'];
        // Lewati nomor yang ternyata sudah dipakai (mis. diisi manual sebelum fitur ini ada)
        if (!countRows('SELECT COUNT(*) FROM bimbingan WHERE sk_tahun=? AND sk_urut=?', [$tahun, $urut])) {
            return $urut;
        }
    }
    throw new Exception('Nomor SK tidak dapat diterbitkan. Periksa tabel sk_nomor.');
}

function buatKodeVerifikasi(): string {
    return 'SK-' . strtoupper(implode('-', str_split(bin2hex(random_bytes(6)), 4)));
}

function urlVerifikasiSk(string $kode): string {
    return BASE_URL . '/verifikasi-sk.php?kode=' . rawurlencode($kode);
}

/** Tahun akademik dari tanggal SK (Agustus ke atas = tahun ajaran baru). */
function tahunAkademikSk(string $tgl): string {
    $ts = strtotime($tgl);
    $y  = (int)date('Y', $ts);
    return (int)date('n', $ts) >= 8 ? $y . '/' . ($y + 1) : ($y - 1) . '/' . $y;
}

/** Semua data yang dicetak di SK. */
function dataSk(int $idBimbingan): ?array {
    return fetchOne(
        'SELECT b.id_bimbingan, b.id_mahasiswa, b.id_dosen, b.no_sk, b.file_sk, b.tgl_sk, b.sk_tahun, b.sk_urut,
                b.kode_verifikasi, b.semester, b.status,
                m.nim, um.id_user AS id_user_mhs, um.nama_lengkap AS nama_mhs, ps.nama_prodi, ps.jenjang,
                j.judul_indonesia,
                d.nip, d.jabatan_fungsional, d.keahlian, ud.id_user AS id_user_dosen, ud.nama_lengkap AS nama_dosen
         FROM bimbingan b
         JOIN mahasiswa m ON m.id_mahasiswa = b.id_mahasiswa JOIN user um ON um.id_user = m.id_user
         LEFT JOIN program_studi ps ON ps.id_prodi = m.id_prodi
         JOIN judul_skripsi j ON j.id_judul = b.id_judul
         JOIN dosen d ON d.id_dosen = b.id_dosen JOIN user ud ON ud.id_user = d.id_user
         WHERE b.id_bimbingan = ?', [$idBimbingan]);
}

/**
 * Terbitkan (atau buat ulang) file SK untuk satu relasi bimbingan.
 * - Relasi yang belum punya nomor otomatis mendapat nomor, tanggal, dan kode verifikasi baru.
 * - Relasi yang sudah punya nomor dibuat ulang dengan nomor & kode yang sama (data terbaru).
 * Panggil di dalam transaksi. Mengembalikan ['path' => file baru, 'lama' => file lama, 'no_sk' => ...].
 * Pemanggil menghapus 'lama' setelah commit, atau 'path' bila transaksi dibatalkan.
 */
function terbitkanSk(int $idBimbingan): array {
    $d = dataSk($idBimbingan);
    if (!$d) throw new Exception('Relasi bimbingan tidak ditemukan.');

    if (!$d['sk_urut']) {
        $tgl   = date('Y-m-d');
        $tahun = (int)date('Y');
        $urut  = ambilNomorSk($tahun);
        $kode  = buatKodeVerifikasi();
        execute('UPDATE bimbingan SET sk_tahun=?, sk_urut=?, no_sk=?, tgl_sk=?, kode_verifikasi=? WHERE id_bimbingan=?',
            [$tahun, $urut, formatNomorSk($tahun, $urut), $tgl, $kode, $idBimbingan]);
        $d = dataSk($idBimbingan);
    } elseif (!$d['kode_verifikasi'] || !$d['tgl_sk']) {
        // Nomor lama hasil migrasi: lengkapi kode verifikasi & tanggal
        execute('UPDATE bimbingan SET kode_verifikasi=COALESCE(kode_verifikasi, ?), tgl_sk=COALESCE(tgl_sk, CURDATE()) WHERE id_bimbingan=?',
            [buatKodeVerifikasi(), $idBimbingan]);
        $d = dataSk($idBimbingan);
    }

    $nama = sprintf('SK_Bimbingan_%d_%04d_%s.pdf', $d['sk_tahun'], $d['sk_urut'], bin2hex(random_bytes(4)));
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
    if (file_put_contents(UPLOAD_DIR . $nama, pdfSk($d)) === false) {
        throw new Exception('Gagal menyimpan file SK ke folder uploads/.');
    }
    $path = 'uploads/' . $nama;
    try {
        execute('UPDATE bimbingan SET file_sk=? WHERE id_bimbingan=?', [$path, $idBimbingan]);
    } catch (Throwable $e) {
        hapusUpload($path);
        throw $e;
    }
    return ['path' => $path, 'lama' => $d['file_sk'], 'no_sk' => $d['no_sk']];
}

/** Susun isi PDF SK dari hasil dataSk(). */
function pdfSk(array $d): string {
    $institusi = pengaturanSk('sk_institusi');
    $unit      = pengaturanSk('sk_unit');
    $tglSk     = $d['tgl_sk'] ?: date('Y-m-d');
    $ta        = trim(pengaturan('tahun_akademik', '')) ?: tahunAkademikSk($tglSk);

    $pdf = new PdfSederhana(70.87, 62.36, 40, 56);   // kiri 25 mm, kanan 22 mm
    $L = $pdf->kiri;
    $W = $pdf->lebarIsi();
    $R = 'Times-Roman'; $B = 'Times-Bold'; $I = 'Times-Italic';
    $fs = 11; $lh = 14.2;

    // ── Kop surat ─────────────────────────────────────────
    $t = $pdf->y;
    $pdf->gambarJpeg(__DIR__ . '/../assets/sk-logo.jpg', $L, $t, 66, 66);
    $tx = $L + 72; $tw = $W - 72;
    $pdf->teks($tx, $t + 20, mb_strtoupper($institusi), $B, 16, 'C', $tw);
    $pdf->teks($tx, $t + 38, $unit, $B, 12.5, 'C', $tw);
    $pdf->teks($tx, $t + 53, pengaturanSk('sk_alamat'), $R, 10, 'C', $tw);
    $pdf->teks($tx, $t + 65, 'Sistem Bimbingan Skripsi Online (BSO)', $I, 9.5, 'C', $tw);
    $g = $t + 74;
    $pdf->garis($L, $g, $L + $W, $g, 2);
    $pdf->garis($L, $g + 3, $L + $W, $g + 3, 0.6);
    $pdf->y = $g + 10;

    // ── Judul ─────────────────────────────────────────────
    $tengah = function (string $s, string $font = 'Times-Bold', float $ukuran = 11.5, float $jarak = 14.5) use ($pdf, $L, $W) {
        $pdf->paragraf($L, $W, $s, $font, $ukuran, $jarak, 'C');
    };
    $tengah('SURAT KEPUTUSAN', $B, 13, 16);
    $tengah('DEKAN ' . mb_strtoupper($unit));
    $tengah(mb_strtoupper($institusi));
    $tengah('Nomor: ' . $d['no_sk'], $R, 11.5, 16);
    $pdf->y += 5;
    $tengah('TENTANG');
    $tengah('PENETAPAN DOSEN PEMBIMBING SKRIPSI');
    $tengah('TAHUN AKADEMIK ' . $ta);
    $pdf->y += 5;
    $tengah('DEKAN ' . mb_strtoupper($unit) . ',', $R, $fs, $lh);
    $pdf->y += 3;

    // ── Bagian berlabel (Menimbang, Mengingat, KESATU, ...) ──
    $xTitik = $L + 78; $xIsi = $L + 88; $wIsi = $W - 88;
    $bagian = function (string $label, array $butir, string $fontLabel = 'Times-Roman', string $fontIsi = 'Times-Roman', string $align = 'J')
        use ($pdf, $L, $xTitik, $xIsi, $wIsi, $fs, $lh) {
        $pdf->pastikanRuang($lh * 2);
        $pdf->teks($L, $pdf->y + $lh, $label, $fontLabel, $fs);
        $pdf->teks($xTitik, $pdf->y + $lh, ':', 'Times-Roman', $fs);
        foreach ($butir as $tanda => $isi) {
            if (is_string($tanda)) {
                $pdf->pastikanRuang($lh);
                $pdf->teks($xIsi, $pdf->y + $lh, $tanda, 'Times-Roman', $fs);
                $pdf->paragraf($xIsi + 16, $wIsi - 16, $isi, $fontIsi, $fs, $lh, $align);
            } else {
                $pdf->paragraf($xIsi, $wIsi, $isi, $fontIsi, $fs, $lh, $align);
            }
        }
        $pdf->y += 4;
    };

    $bagian('Menimbang', [
        'a.' => 'bahwa untuk kelancaran penyusunan tugas akhir mahasiswa, diperlukan dosen pembimbing yang kompeten sesuai bidang keilmuan;',
        'b.' => 'bahwa mahasiswa yang namanya tercantum dalam keputusan ini telah memenuhi persyaratan akademik untuk melaksanakan penyusunan skripsi;',
        'c.' => 'bahwa berdasarkan pertimbangan sebagaimana dimaksud pada huruf a dan huruf b, perlu menetapkan Keputusan Dekan tentang Penetapan Dosen Pembimbing Skripsi;',
    ]);
    $bagian('Mengingat', [
        '1.' => "Academic Regulations {$institusi} Tahun Akademik {$ta};",
        '2.' => "Research Project Guidelines {$unit};",
        '3.' => "Ketentuan akademik yang berlaku di {$institusi}.",
    ]);

    $pdf->y += 2;
    $tengah('MEMUTUSKAN', $B, 12, 16);
    $pdf->y += 2;
    $bagian('Menetapkan', ['KEPUTUSAN DEKAN ' . mb_strtoupper($unit) . ' TENTANG PENETAPAN DOSEN PEMBIMBING SKRIPSI.'], 'Times-Roman', 'Times-Bold', 'L');

    // KESATU + tabel data
    $bagian('KESATU', ['Menunjuk dosen pembimbing skripsi sebagai berikut:'], 'Times-Bold');
    $pdf->y -= 2;
    $xLbl = $xIsi + 16; $xTtk = $xLbl + 118; $xVal = $xTtk + 8; $wVal = $L + $W - $xVal;
    $baris = function (string $label, string $nilai, string $font = 'Times-Roman') use ($pdf, $xLbl, $xTtk, $xVal, $wVal, $fs) {
        $jarak = 13.6;
        $pdf->pastikanRuang($jarak);
        $pdf->teks($xLbl, $pdf->y + $jarak, $label, 'Times-Roman', $fs);
        $pdf->teks($xTtk, $pdf->y + $jarak, ':', 'Times-Roman', $fs);
        $pdf->paragraf($xVal, $wVal, $nilai !== '' ? $nilai : '-', $font, $fs, $jarak);
    };
    $judul = function (string $tanda, string $s) use ($pdf, $xIsi, $fs) {
        $pdf->pastikanRuang(40);
        $pdf->y += 15;
        $pdf->teks($xIsi, $pdf->y, $tanda, 'Times-Bold', $fs);
        $pdf->teks($xIsi + 16, $pdf->y, $s, 'Times-Bold', $fs);
    };
    $judul('a.', 'Data Mahasiswa');
    $baris('Nama Mahasiswa', $d['nama_mhs'], 'Times-Bold');
    $baris('Nomor Induk Mahasiswa', $d['nim']);
    $baris('Program Studi', trim(($d['jenjang'] ?? '') . ' ' . ($d['nama_prodi'] ?? '')));
    $baris('Judul Skripsi', $d['judul_indonesia']);
    $judul('b.', 'Dosen Pembimbing');
    $baris('Nama Dosen', $d['nama_dosen'], 'Times-Bold');
    $baris('NIP', (string)$d['nip']);
    $baris('Jabatan Akademik', (string)$d['jabatan_fungsional']);
    $baris('Bidang Keahlian', (string)$d['keahlian']);
    $pdf->y += 6;

    $bagian('KEDUA', ['Dosen pembimbing bertugas memberikan arahan, bimbingan, evaluasi, dan persetujuan terhadap proses penyusunan skripsi mahasiswa sampai dengan penyelesaian penelitian.'], 'Times-Bold');
    $bagian('KETIGA', ['Mahasiswa wajib melaksanakan bimbingan secara berkala melalui Sistem Bimbingan Skripsi Online (BSO) maupun pertemuan akademik yang ditetapkan oleh pembimbing.'], 'Times-Bold');
    $bagian('KEEMPAT', ['Keputusan ini berlaku sejak tanggal ditetapkan, dengan ketentuan apabila di kemudian hari terdapat kekeliruan akan diadakan perbaikan sebagaimana mestinya.'], 'Times-Bold');

    // ── Tanda tangan & kode verifikasi ────────────────────
    $pdf->pastikanRuang(165);
    $pdf->y += 8;
    $top = $pdf->y;
    $xT = $L + $W * 0.50; $wT = $W * 0.50;
    $pdf->teks($xT, $top + 12, 'Ditetapkan di', $R, $fs);
    $pdf->teks($xT + 72, $top + 12, ': ' . pengaturanSk('sk_tempat'), $R, $fs);
    $pdf->teks($xT, $top + 26, 'Pada tanggal', $R, $fs);
    $pdf->teks($xT + 72, $top + 26, ': ' . tgl($tglSk, 'panjang'), $R, $fs);
    $pdf->garis($xT, $top + 31, $L + $W, $top + 31, 0.6);
    $pdf->y = $top + 33;
    $pdf->paragraf($xT, $wT, 'a.n. Dekan ' . $unit, $R, $fs, 13.5);
    $pdf->paragraf($xT, $wT, $institusi . ',', $R, $fs, 13.5);
    $pdf->y += 16;
    $pdf->teks($xT, $pdf->y, 'Ditandatangani secara elektronik', $I, 9);
    $pdf->y += 30;
    $nama = pengaturanSk('sk_penandatangan');
    $pdf->teks($xT, $pdf->y, $nama, $B, $fs);
    $pdf->garis($xT, $pdf->y + 1.8, $xT + $pdf->lebarTeks($nama, $B, $fs), $pdf->y + 1.8, 0.6);
    $pdf->paragraf($xT, $wT, pengaturanSk('sk_jabatan_ttd'), $R, 10, 12.5);
    $akhirTtd = $pdf->y;

    // Kotak kode verifikasi di sebelah kiri
    $wK = $W * 0.44; $yK = $top + 44;
    $pdf->teks($L + 8, $yK + 15, 'Kode Verifikasi Dokumen', $B, 9.5);
    $pdf->teks($L + 8, $yK + 33, (string)$d['kode_verifikasi'], 'Courier-Bold', 12);
    $pdf->y = $yK + 37;
    $pdf->paragraf($L + 8, $wK - 16, 'Periksa keaslian dokumen di:', $R, 8, 10);
    $pdf->paragraf($L + 8, $wK - 16, urlVerifikasiSk((string)$d['kode_verifikasi']), $R, 7, 9);
    $tinggiK = $pdf->y - $yK + 8;
    $pdf->kotak($L, $yK, $wK, $tinggiK, 0.6);
    $pdf->y = max($akhirTtd, $yK + $tinggiK) + 14;

    // ── Catatan ───────────────────────────────────────────
    $pdf->pastikanRuang(60);
    $pdf->garis($L, $pdf->y, $L + $W, $pdf->y, 0.4);
    $pdf->y += 2;
    $pdf->paragraf($L, $W, 'Catatan:', $B, 9, 12);
    foreach ([
        'SK ini diterbitkan secara elektronik melalui Sistem Bimbingan Skripsi Online (BSO).',
        'Dokumen ini sah tanpa tanda tangan basah apabila kode verifikasi valid.',
        'Keaslian dokumen dapat diverifikasi melalui portal BSO ' . $institusi . ' dengan kode verifikasi di atas.',
    ] as $i => $c) {
        $pdf->pastikanRuang(12);
        $pdf->teks($L, $pdf->y + 11.5, ($i + 1) . '.', $R, 9);
        $pdf->paragraf($L + 12, $W - 12, $c, $R, 9, 11.5);
    }

    $noSk = (string)$d['no_sk'];
    return $pdf->hasil(function (PdfSederhana $p, int $ke, int $total) use ($L, $W, $noSk) {
        $y = PdfSederhana::TINGGI - 30;
        $p->teks($L, $y, $noSk, 'Times-Roman', 8);
        $p->teks($L, $y, "Halaman $ke dari $total", 'Times-Roman', 8, 'R', $W);
    }, 'SK Bimbingan ' . $noSk . ' - ' . $d['nama_mhs']);
}
