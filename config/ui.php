<?php
// ============================================================
//  config/ui.php — Komponen tampilan bersama: ikon SVG, inisial, upload
// ============================================================

// Ikon garis 24x24 (gaya Lucide, lisensi ISC). Warna mengikuti currentColor.
const ICON_PATHS = [
    'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
    'file'      => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/>',
    'users'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    'user'      => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    'folder'    => '<path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/>',
    'calendar'  => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
    'clipboard' => '<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>',
    'check-circle' => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
    'graduation'=> '<path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>',
    'book'      => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
    'link'      => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
    'chart'     => '<path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
    'settings'  => '<path d="M4 21v-7"/><path d="M4 10V3"/><path d="M12 21v-9"/><path d="M12 8V3"/><path d="M20 21v-5"/><path d="M20 12V3"/><path d="M2 14h4"/><path d="M10 8h4"/><path d="M18 16h4"/>',
    'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
    'bell'      => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
    'lock'      => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    'eye'       => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    'pencil'    => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
    'key'       => '<circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/>',
    'trash'     => '<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M10 11v6"/><path d="M14 11v6"/>',
    'download'  => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/>',
    'upload'    => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/>',
    'check'     => '<path d="M20 6 9 17l-5-5"/>',
    'x'         => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
    'alert'     => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
    'clock'     => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
    'megaphone' => '<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
    'help'      => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/>',
    'chat'      => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    'printer'   => '<path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
    'award'     => '<circle cx="12" cy="8" r="6"/><path d="M15.48 12.89 17 22l-5-3-5 3 1.52-9.11"/>',
    'map'       => '<path d="M3 6l6-3 6 3 6-3v15l-6 3-6-3-6 3z"/><path d="M9 3v15"/><path d="M15 6v15"/>',
    'columns'   => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M12 3v18"/>',
    'clock-plus'=> '<path d="M21 12a9 9 0 1 0-9 9"/><path d="M12 7v5l3 2"/><path d="M19 16v6"/><path d="M16 19h6"/>',
    'import'    => '<path d="M12 3v12"/><path d="m8 11 4 4 4-4"/><path d="M8 5H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-4"/>',
];

/** Ikon SVG inline. Dekoratif (aria-hidden); beri label pada tombol pembungkusnya. */
function icon(string $nama, int $ukuran = 18): string {
    $p = ICON_PATHS[$nama] ?? ICON_PATHS['file'];
    return '<svg class="icon" width="' . $ukuran . '" height="' . $ukuran . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $p . '</svg>';
}

/** Inisial nama, maksimal 2 huruf: "Muhammad Keisa Nabhan" → "MK". */
function inisial(?string $nama): string {
    $kata = preg_split('/\s+/u', trim((string)$nama), -1, PREG_SPLIT_NO_EMPTY);
    if (!$kata) return '?';
    $hasil = '';
    foreach (array_slice($kata, 0, 2) as $k) $hasil .= mb_strtoupper(mb_substr($k, 0, 1));
    return $hasil;
}

/**
 * Area unggah file seragam (klik atau seret). Nama file terpilih ditampilkan
 * otomatis oleh assets/script.js.
 */
function uploadZone(string $name, string $label, string $accept, string $hint, bool $required = false, ?string $id = null): string {
    $id = $id ?? 'f_' . $name;
    return '<div class="upload-zone">'
        . '<div class="upload-icon">' . icon('upload', 28) . '</div>'
        . '<div class="upload-label" data-default="' . e($label) . '">' . e($label) . '</div>'
        . '<div class="upload-sub">' . e($hint) . '</div>'
        . '<input type="file" id="' . e($id) . '" name="' . e($name) . '" accept="' . e($accept) . '"'
        . ($required ? ' required' : '') . ' class="upload-input">'
        . '</div>';
}

/**
 * Pesan flash + kotak kredensial (password awal / hasil reset).
 * Semua pesan TETAP tampil sampai pengguna menutupnya (tombol ×), tidak
 * pernah hilang sendiri, supaya informasi penting tidak lenyap sebelum terbaca.
 */
function tampilFlash(): string {
    $html = '';
    $k = $_SESSION['kredensial'] ?? null;
    unset($_SESSION['kredensial']);
    if ($k) {
        $html .= '<div class="status-card status-card--blok status-card--success mb-20 kredensial" role="alert">'
            . '<button type="button" class="alert-close" data-tutup aria-label="Tutup">' . icon('x', 16) . '</button>'
            . '<div class="status-card__title">' . e($k['judul']) . '</div>'
            . '<p class="fs-14">' . e($k['nama']) . ' · login dengan <b>' . e($k['login']) . '</b></p>'
            . '<p class="fs-14">Kata sandi:</p>'
            . '<div class="sandi-box mono">' . e($k['sandi']) . '</div>'
            . '<button type="button" class="btn btn-secondary btn-sm" data-salin="' . e($k['sandi']) . '">' . icon('clipboard', 14) . ' Salin</button>'
            . '<p class="fs-13 text-slate" style="margin-top:8px;">Sampaikan langsung ke pengguna. Pengguna wajib menggantinya saat login pertama. '
            . 'Kotak ini tidak ditampilkan lagi setelah halaman dimuat ulang.</p>'
            . '</div>';
    }
    $f = getFlash();
    if ($f) {
        $tipe = in_array($f['type'], ['success', 'danger', 'warning', 'info'], true) ? $f['type'] : 'info';
        $html .= '<div class="alert ' . $tipe . '" role="' . ($tipe === 'danger' ? 'alert' : 'status') . '">'
            . '<span class="alert-msg">' . e($f['msg']) . '</span>'
            . '<button type="button" class="alert-close" data-tutup aria-label="Tutup pesan">' . icon('x', 16) . '</button>'
            . '</div>';
    }
    return $html;
}
