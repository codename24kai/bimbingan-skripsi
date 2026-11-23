<?php
// ============================================================
//  config/format.php — Format tampilan: tanggal & label berbahasa Indonesia
// ============================================================

const NAMA_HARI  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
const NAMA_BULAN = ['', 'Januari','Februari','Maret','April','Mei','Juni','Juli',
                    'Agustus','September','Oktober','November','Desember'];
const BULAN_PENDEK = ['', 'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

/**
 * Format tanggal Indonesia.
 *  'pendek'  → 23 Nov 2026          (default)
 *  'jam'     → 23 Nov 2026, 13.00
 *  'hari'    → Senin, 23 Nov 2026
 *  'harijam' → Senin, 23 Nov 2026, 13.00
 *  'panjang' → 23 November 2026
 *  'lengkap' → Senin, 23 November 2026
 *  'tglbln'  → 23 Nov
 *  'tglblnjam' → 23 Nov, 13.00
 */
function tgl(?string $waktu, string $format = 'pendek'): string {
    if ($waktu === null || $waktu === '') return '-';
    $ts = is_numeric($waktu) ? (int)$waktu : strtotime($waktu);
    if ($ts === false) return '-';

    $d   = (int)date('j', $ts);
    $m   = (int)date('n', $ts);
    $y   = date('Y', $ts);
    $jam = date('H.i', $ts);
    $hari = NAMA_HARI[(int)date('w', $ts)];
    $pendek = sprintf('%d %s %s', $d, BULAN_PENDEK[$m], $y);

    return match ($format) {
        'jam'       => "$pendek, $jam",
        'hari'      => "$hari, $pendek",
        'harijam'   => "$hari, $pendek, $jam",
        'panjang'   => sprintf('%d %s %s', $d, NAMA_BULAN[$m], $y),
        'lengkap'   => sprintf('%s, %d %s %s', $hari, $d, NAMA_BULAN[$m], $y),
        'tglbln'    => sprintf('%d %s', $d, BULAN_PENDEK[$m]),
        'tglblnjam' => sprintf('%d %s, %s', $d, BULAN_PENDEK[$m], $jam),
        default     => $pendek,
    };
}

/** "Hari ini", "Besok", "3 hari lagi", "Kemarin", "5 hari lalu". */
function tglRelatif(?string $tanggal): string {
    if (!$tanggal) return '';
    $hari = (int)floor((strtotime(date('Y-m-d', strtotime($tanggal))) - strtotime(date('Y-m-d'))) / 86400);
    return match (true) {
        $hari === 0  => 'Hari ini',
        $hari === 1  => 'Besok',
        $hari === -1 => 'Kemarin',
        $hari > 1    => "$hari hari lagi",
        default      => abs($hari) . ' hari lalu',
    };
}

/** Ubah nilai mentah database menjadi teks yang ramah dibaca. */
function label(?string $nilai): string {
    if ($nilai === null || $nilai === '') return '-';
    $map = [
        // status sesi / jadwal
        'dijadwalkan'  => 'Menunggu konfirmasi',
        'dikonfirmasi' => 'Dikonfirmasi',
        'selesai'      => 'Selesai',
        'batal'        => 'Dibatalkan',
        // metode bimbingan
        'tatap_muka'   => 'Tatap muka',
        'zoom'         => 'Zoom',
        'gmeet'        => 'Google Meet',
        'lainnya'      => 'Lainnya',
        // status skripsi mahasiswa
        'belum'        => 'Belum mulai',
        'proses'       => 'Sedang berjalan',
        // judul / dokumen / catatan
        'draft'        => 'Draf',
        'diajukan'     => 'Diajukan',
        'revisi'       => 'Perlu revisi',
        'disetujui'    => 'Disetujui',
        'ditolak'      => 'Ditolak',
        'menunggu'     => 'Menunggu review',
        'direvisi'     => 'Direvisi',
        // prioritas revisi
        'minor'        => 'Minor',
        'mayor'        => 'Mayor',
        'kritis'       => 'Kritis',
        // status akun / bimbingan
        'aktif'        => 'Aktif',
        'nonaktif'     => 'Nonaktif',
        'dihentikan'   => 'Dihentikan',
        // tipe notifikasi
        'info'         => 'Info',
        'sukses'       => 'Sukses',
        'peringatan'   => 'Peringatan',
        'error'        => 'Galat',
        // role
        'mahasiswa'    => 'Mahasiswa',
        'dosen'        => 'Dosen',
        'admin'        => 'Admin',
    ];
    if (isset($map[$nilai])) return $map[$nilai];
    $bab = labelBab($nilai);
    if ($bab !== $nilai) return $bab;
    return ucfirst(str_replace('_', ' ', $nilai));
}

/** BAB_1 / BAB 1 / bab ii → "Bab I", "Bab II". Nilai lain dikembalikan apa adanya. */
function labelBab(?string $nilai): string {
    $nilai = (string)$nilai;
    if (!preg_match('/^\s*bab[\s_-]*([0-9]+|[ivx]+)\s*$/i', $nilai, $m)) return $nilai;
    $romawi = ['', 'I','II','III','IV','V','VI','VII','VIII','IX','X'];
    $n = $m[1];
    if (ctype_digit($n)) $n = $romawi[(int)$n] ?? $n;
    return 'Bab ' . strtoupper($n);
}
