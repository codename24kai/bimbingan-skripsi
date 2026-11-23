<?php
// download.php — Menyajikan file upload hanya untuk pengguna yang berhak.
// Folder uploads/ diblokir dari akses langsung (lihat uploads/.htaccess).
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';
requireLogin();

$path = (string)($_GET['f'] ?? '');

// Hanya izinkan nama file sederhana di dalam folder uploads/
if (!preg_match('#^uploads/([A-Za-z0-9_\-]+\.(pdf|docx|doc|xlsx|pptx|zip))$#', $path, $m)) {
    http_response_code(404);
    exit('File tidak ditemukan.');
}
$fullPath = UPLOAD_DIR . $m[1];
if (!is_file($fullPath)) {
    http_response_code(404);
    exit('File tidak ditemukan.');
}

// Template dokumen boleh diunduh semua pengguna yang login
$isTemplate = tabelAda('template_dokumen')
    && countRows('SELECT COUNT(*) FROM template_dokumen WHERE file_path = ?', [$path]) > 0;

// Cari mahasiswa pemilik file
$sqlOwner = 'SELECT id_mahasiswa FROM judul_skripsi WHERE file_proposal = ? OR file_transkrip = ?
     UNION SELECT id_mahasiswa FROM dokumen WHERE file_path = ?
     UNION SELECT id_mahasiswa FROM bimbingan WHERE file_sk = ?';
$paramsOwner = [$path, $path, $path, $path];
$idUjian = 0;
if (tabelAda('ujian')) {
    $uj = fetchOne('SELECT u.id_ujian, b.id_mahasiswa FROM ujian u JOIN bimbingan b ON b.id_bimbingan = u.id_bimbingan WHERE u.file_berkas = ?', [$path]);
    if ($uj) $idUjian = (int)$uj['id_ujian'];
}
$owner = $isTemplate ? null : fetchOne($sqlOwner . ' LIMIT 1', $paramsOwner);
if (!$owner && !empty($uj)) $owner = ['id_mahasiswa' => $uj['id_mahasiswa']];

$allowed = $isTemplate;
if ($owner) {
    $idMhs = (int)$owner['id_mahasiswa'];
    switch (role()) {
        case 'admin':
            $allowed = true;
            break;
        case 'mahasiswa':
            $allowed = ($idMhs === entityId());
            break;
        case 'dosen':
            $allowed = countRows(
                'SELECT COUNT(*) FROM bimbingan WHERE id_mahasiswa = ? AND id_dosen = ?',
                [$idMhs, entityId()]
            ) > 0;
            // Dosen penguji boleh membuka berkas ujian yang ia uji
            if (!$allowed && $idUjian) {
                $allowed = countRows('SELECT COUNT(*) FROM penguji_ujian WHERE id_ujian = ? AND id_dosen = ?', [$idUjian, entityId()]) > 0;
            }
            break;
    }
}

if (!$allowed) {
    http_response_code(403);
    exit('Anda tidak memiliki akses ke file ini.');
}

$ext  = strtolower($m[2]);
$mime = [
    'pdf'  => 'application/pdf',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'doc'  => 'application/msword',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'zip'  => 'application/zip',
][$ext];

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fullPath));
header('Content-Disposition: ' . ($ext === 'pdf' ? 'inline' : 'attachment') . '; filename="' . $m[1] . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($fullPath);
exit;
