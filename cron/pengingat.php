<?php
// cron/pengingat.php — Kirim pengingat otomatis (D10) dari Task Scheduler / cron.
// Tanpa ini pengingat tetap berjalan saat ada pengguna yang membuka dashboard
// (maksimal sekali per jam). Contoh Task Scheduler Windows (XAMPP), tiap hari 07.00:
//   Program : C:\xampp\php\php.exe
//   Argumen : C:\xampp\htdocs\basis-data-2\bimbingan-skripsi\cron\pengingat.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';

$jumlah = jalankanPengingat(true);
echo date('Y-m-d H:i:s') . " — $jumlah pengingat dikirim\n";
