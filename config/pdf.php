<?php
// ============================================================
//  config/pdf.php — Penulis PDF minimal tanpa library tambahan.
//  Mendukung font standar PDF (Times, Courier), teks rata kiri/
//  tengah/kanan/kiri-kanan, garis, gambar JPEG, dan pindah halaman
//  otomatis. Dipakai untuk membuat file SK bimbingan.
// ============================================================

class PdfSederhana
{
    public const LEBAR  = 595.28;   // A4 dalam point
    public const TINGGI = 841.89;

    /** Lebar glyph (per 1000 unit) untuk kode WinAnsi 32..255. */
    private const LEBAR_GLYPH = [
        'Times-Roman' => '250,333,408,500,500,833,778,180,333,333,500,564,250,333,250,278,500,500,500,500,500,500,500,500,500,500,278,278,564,564,564,444,921,722,667,667,722,611,556,722,722,333,389,722,611,889,722,722,556,722,667,556,611,722,722,944,722,722,611,333,278,333,469,500,333,444,500,444,500,444,333,500,500,278,278,500,278,778,500,500,500,500,333,389,278,500,500,722,500,500,444,480,200,480,541,761,500,761,333,500,444,1000,500,500,333,1000,556,333,889,761,611,761,761,333,333,444,444,350,500,1000,333,980,389,333,722,761,444,722,250,333,500,500,500,500,200,500,333,760,276,500,564,333,760,333,400,564,300,300,333,500,453,250,333,300,310,500,750,750,750,444,722,722,722,722,722,722,889,667,611,611,611,611,333,333,333,333,722,722,722,722,722,722,722,564,722,722,722,722,722,722,556,500,444,444,444,444,444,444,667,444,444,444,444,444,278,278,278,278,500,500,500,500,500,500,500,564,500,500,500,500,500,500,500,500',
        'Times-Bold' => '250,333,555,500,500,1000,833,278,333,333,500,570,250,333,250,278,500,500,500,500,500,500,500,500,500,500,333,333,570,570,570,500,930,722,667,722,722,667,611,778,778,389,500,778,667,944,722,778,611,778,722,556,667,722,722,1000,722,722,667,333,278,333,581,500,333,500,556,444,556,444,333,500,556,278,333,556,278,833,556,500,556,556,444,389,333,556,500,722,500,500,444,394,220,394,520,761,500,761,333,500,500,1000,500,500,333,1000,556,333,1000,761,667,761,761,333,333,500,500,350,500,1000,333,1000,389,333,722,761,444,722,250,333,500,500,500,500,220,500,333,747,300,500,570,333,747,333,400,570,300,300,333,556,540,250,333,300,330,500,750,750,750,500,722,722,722,722,722,722,1000,722,667,667,667,667,389,389,389,389,722,722,778,778,778,778,778,570,778,722,722,722,722,722,611,556,500,500,500,500,500,500,722,444,444,444,444,444,278,278,278,278,500,556,500,500,500,500,500,570,500,556,556,556,556,500,556,500',
        'Times-Italic' => '250,333,420,500,500,833,778,214,333,333,500,675,250,333,250,278,500,500,500,500,500,500,500,500,500,500,333,333,675,675,675,500,920,611,611,667,722,611,611,722,722,333,444,667,556,833,667,722,611,722,611,500,556,722,611,833,611,556,556,389,278,389,422,500,333,500,500,444,500,444,278,500,500,278,278,444,278,722,500,500,500,500,389,389,278,500,444,667,444,444,389,400,275,400,541,761,500,761,333,500,556,889,500,500,333,1000,500,333,944,761,556,761,761,333,333,556,556,350,500,889,333,980,389,333,667,761,389,556,250,389,500,500,500,500,275,500,333,760,276,500,675,333,760,333,400,675,300,300,333,500,523,250,333,300,310,500,750,750,750,500,611,611,611,611,611,611,889,667,611,611,611,611,333,333,333,333,722,667,722,722,722,722,722,675,722,722,722,722,722,556,611,500,500,500,500,500,500,500,667,444,444,444,444,444,278,278,278,278,500,500,500,500,500,500,500,675,500,500,500,500,500,444,500,444',
    ];
    private const KODE_FONT = [
        'Times-Roman' => 'F1', 'Times-Bold' => 'F2', 'Times-Italic' => 'F3', 'Courier-Bold' => 'F4',
    ];

    public float $y = 0;          // posisi kursor dari tepi atas (pt)
    public float $kiri, $kanan, $atas, $bawah;
    private array $halaman = [];
    private int $aktif = -1;       // indeks halaman yang sedang ditulisi
    private array $gambar = [];
    private array $cacheLebar = [];

    public function __construct(float $kiri = 70, float $kanan = 70, float $atas = 56, float $bawah = 56)
    {
        [$this->kiri, $this->kanan, $this->atas, $this->bawah] = [$kiri, $kanan, $atas, $bawah];
        $this->tambahHalaman();
    }

    public function lebarIsi(): float { return self::LEBAR - $this->kiri - $this->kanan; }
    public function jumlahHalaman(): int { return count($this->halaman); }

    public function tambahHalaman(): void
    {
        $this->halaman[] = '';
        $this->aktif = count($this->halaman) - 1;
        $this->y = $this->atas;
    }

    /** Pindah halaman bila sisa ruang kurang dari $tinggi. */
    public function pastikanRuang(float $tinggi): void
    {
        if ($this->y + $tinggi > self::TINGGI - $this->bawah) $this->tambahHalaman();
    }

    /** UTF-8 → WinAnsi (CP1252); karakter yang tidak ada diganti "?". */
    public static function enkode(string $s): string
    {
        $s = str_replace(["\r", "\t"], ['', ' '], $s);
        $hasil = @mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
        return $hasil === false ? preg_replace('/[^\x20-\x7E]/', '?', $s) : $hasil;
    }

    public function lebarTeks(string $s, string $font = 'Times-Roman', float $ukuran = 11): float
    {
        $tabel = $this->tabelLebar($font);
        $total = 0;
        $b = self::enkode($s);
        for ($i = 0, $n = strlen($b); $i < $n; $i++) {
            $k = ord($b[$i]);
            $total += $k >= 32 ? $tabel[$k - 32] : 0;
        }
        return $total * $ukuran / 1000;
    }

    private function tabelLebar(string $font): array
    {
        if (!isset($this->cacheLebar[$font])) {
            $this->cacheLebar[$font] = str_starts_with($font, 'Courier')
                ? array_fill(0, 224, 600)
                : array_map('intval', explode(',', self::LEBAR_GLYPH[$font]));
        }
        return $this->cacheLebar[$font];
    }

    private function tulis(string $perintah): void
    {
        $this->halaman[$this->aktif] .= $perintah . "\n";
    }

    private static function angka(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }

    private static function escape(string $b): string
    {
        return strtr($b, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
    }

    /**
     * Tulis satu baris teks. $baseline diukur dari tepi atas halaman.
     * $align: L, C, R (relatif terhadap $x..$x+$lebar).
     */
    public function teks(float $x, float $baseline, string $s, string $font = 'Times-Roman', float $ukuran = 11,
                         string $align = 'L', float $lebar = 0, float $spasiKata = 0): void
    {
        if ($s === '') return;
        if ($align === 'C') $x += ($lebar - $this->lebarTeks($s, $font, $ukuran)) / 2;
        if ($align === 'R') $x += $lebar - $this->lebarTeks($s, $font, $ukuran);
        $tw = $spasiKata ? self::angka($spasiKata) . ' Tw ' : '';
        $this->tulis(sprintf('BT /%s %s Tf %s%s %s Td (%s) Tj ET%s',
            self::KODE_FONT[$font], self::angka($ukuran), $tw, self::angka($x), self::angka(self::TINGGI - $baseline),
            self::escape(self::enkode($s)), $spasiKata ? ' 0 Tw' : ''));
    }

    public function garis(float $x1, float $y1, float $x2, float $y2, float $tebal = 0.6): void
    {
        $this->tulis(sprintf('%s w %s %s m %s %s l S', self::angka($tebal),
            self::angka($x1), self::angka(self::TINGGI - $y1), self::angka($x2), self::angka(self::TINGGI - $y2)));
    }

    public function kotak(float $x, float $y, float $w, float $h, float $tebal = 0.6): void
    {
        $this->tulis(sprintf('%s w %s %s %s %s re S', self::angka($tebal),
            self::angka($x), self::angka(self::TINGGI - $y - $h), self::angka($w), self::angka($h)));
    }

    /** Sisipkan gambar JPEG (baseline). Diam saja bila file tidak ada/tidak valid. */
    public function gambarJpeg(string $path, float $x, float $y, float $w, float $h): void
    {
        $info = is_file($path) ? @getimagesize($path) : false;
        if (!$info || $info[2] !== IMAGETYPE_JPEG) return;
        $nama = 'Im' . (count($this->gambar) + 1);
        $this->gambar[$nama] = [
            'data' => file_get_contents($path), 'w' => $info[0], 'h' => $info[1],
            'cs' => match ($info['channels'] ?? 3) { 1 => 'DeviceGray', 4 => 'DeviceCMYK', default => 'DeviceRGB' },
        ];
        $this->tulis(sprintf('q %s 0 0 %s %s %s cm /%s Do Q', self::angka($w), self::angka($h),
            self::angka($x), self::angka(self::TINGGI - $y - $h), $nama));
    }

    /** Pecah teks menjadi baris-baris yang muat dalam $lebar. */
    public function bungkus(string $s, float $lebar, string $font = 'Times-Roman', float $ukuran = 11): array
    {
        $baris = [];
        foreach (preg_split('/\n/', trim($s)) as $paragraf) {
            $sekarang = '';
            foreach (preg_split('/\s+/u', trim($paragraf), -1, PREG_SPLIT_NO_EMPTY) as $kata) {
                // Kata yang lebih panjang dari satu baris dipotong paksa
                while ($this->lebarTeks($kata, $font, $ukuran) > $lebar && mb_strlen($kata) > 1) {
                    $potong = mb_strlen($kata) - 1;
                    while ($potong > 1 && $this->lebarTeks(mb_substr($kata, 0, $potong), $font, $ukuran) > $lebar) $potong--;
                    if ($sekarang !== '') { $baris[] = $sekarang; $sekarang = ''; }
                    $baris[] = mb_substr($kata, 0, $potong);
                    $kata = mb_substr($kata, $potong);
                }
                $coba = $sekarang === '' ? $kata : $sekarang . ' ' . $kata;
                if ($sekarang !== '' && $this->lebarTeks($coba, $font, $ukuran) > $lebar) {
                    $baris[] = $sekarang;
                    $sekarang = $kata;
                } else {
                    $sekarang = $coba;
                }
            }
            $baris[] = $sekarang;
        }
        return $baris;
    }

    /**
     * Tulis paragraf mulai dari kursor $this->y (kursor = baseline baris pertama
     * dikurangi $jarak). $align: L, C, R, J (rata kiri-kanan). Mengembalikan jumlah baris.
     */
    public function paragraf(float $x, float $lebar, string $s, string $font = 'Times-Roman', float $ukuran = 11,
                             float $jarak = 14, string $align = 'L'): int
    {
        $baris = $this->bungkus($s, $lebar, $font, $ukuran);
        $akhir = count($baris) - 1;
        foreach ($baris as $i => $b) {
            $this->pastikanRuang($jarak);
            $this->y += $jarak;
            $sk = 0;
            $al = $align;
            if ($align === 'J') {
                $al = 'L';
                $spasi = substr_count($b, ' ');
                if ($i < $akhir && $spasi > 0) $sk = ($lebar - $this->lebarTeks($b, $font, $ukuran)) / $spasi;
            }
            $this->teks($x, $this->y, $b, $font, $ukuran, $al, $lebar, $sk);
        }
        return count($baris);
    }

    /** Hasilkan isi file PDF. $footer($pdf, halamanKe, total) dipanggil untuk menggambar kaki tiap halaman. */
    public function hasil(?callable $footer = null, string $judul = ''): string
    {
        $total = count($this->halaman);
        $objek = [];
        $objek[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $fontObj = [];
        $id = 3;
        foreach (self::KODE_FONT as $nama => $kode) {
            $fontObj[$kode] = $id;
            $objek[$id++] = "<< /Type /Font /Subtype /Type1 /BaseFont /$nama /Encoding /WinAnsiEncoding >>";
        }
        $gambarObj = [];
        foreach ($this->gambar as $nama => $g) {
            $gambarObj[$nama] = $id;
            $objek[$id++] = sprintf("<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /%s /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
                $g['w'], $g['h'], $g['cs'], strlen($g['data']), $g['data']);
        }
        $res = '<< /Font << ' . implode(' ', array_map(fn($k, $v) => "/$k $v 0 R", array_keys($fontObj), $fontObj)) . ' >>';
        if ($gambarObj) $res .= ' /XObject << ' . implode(' ', array_map(fn($k, $v) => "/$k $v 0 R", array_keys($gambarObj), $gambarObj)) . ' >>';
        $res .= ' >>';

        $kids = [];
        foreach (array_keys($this->halaman) as $i) {
            if ($footer) {
                $this->aktif = $i;                 // footer ditulis ke halaman ini
                $footer($this, $i + 1, $total);
            }
            $isi = $this->halaman[$i];
            $data = function_exists('gzcompress') ? gzcompress($isi) : $isi;
            $filter = function_exists('gzcompress') ? ' /Filter /FlateDecode' : '';
            $objek[$id] = sprintf("<< /Length %d%s >>\nstream\n%s\nendstream", strlen($data), $filter, $data);
            $objek[$id + 1] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %s %s] /Resources %s /Contents %d 0 R >>',
                self::angka(self::LEBAR), self::angka(self::TINGGI), $res, $id);
            $kids[] = ($id + 1) . ' 0 R';
            $id += 2;
        }
        $objek[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $total . ' >>';
        $info = $id;
        $objek[$info] = '<< /Title (' . self::escape(self::enkode($judul)) . ') /Producer (' . self::escape(APP_NAME) . ') /CreationDate (D:' . date('YmdHis') . ') >>';
        ksort($objek);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offset = [];
        foreach ($objek as $n => $o) {
            $offset[$n] = strlen($pdf);
            $pdf .= "$n 0 obj\n$o\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objek) + 1) . "\n0000000000 65535 f \n";
        foreach ($offset as $o) $pdf .= sprintf("%010d 00000 n \n", $o);
        $pdf .= 'trailer << /Size ' . (count($objek) + 1) . " /Root 1 0 R /Info $info 0 R >>\nstartxref\n$xref\n%%EOF";
        return $pdf;
    }
}
