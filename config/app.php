<?php
// ============================================================
//  config/app.php — Konstanta aplikasi
// ============================================================

define('APP_NAME',    'SkripsiKu');
define('APP_VERSION', '1.0.0');
// BASE_URL dideteksi otomatis dari lokasi folder project di htdocs, mis.
// htdocs/bimbingan-skripsi → http://localhost/bimbingan-skripsi.
// Jika tautan/CSS tidak termuat (mis. memakai alias/virtual host), isi manual:
// define('BASE_URL', 'http://localhost/bimbingan-skripsi');
if (!defined('BASE_URL')) {
    define('BASE_URL', (function (): string {
        $folder  = str_replace('\\', '/', (string)realpath(__DIR__ . '/..'));
        $docRoot = rtrim(str_replace('\\', '/', (string)realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
        if (empty($_SERVER['HTTP_HOST']) || $docRoot === '' || stripos($folder . '/', $docRoot . '/') !== 0) {
            return 'http://localhost/bimbingan-skripsi'; // CLI (cron) atau struktur tak dikenal
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
        $path  = implode('/', array_map('rawurlencode', explode('/', rtrim(substr($folder, strlen($docRoot)), '/'))));
        return ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $path;
    })());
}
define('UPLOAD_DIR',  __DIR__ . '/../uploads/');
define('MAX_FILE_KB', 10240); // 10 MB
