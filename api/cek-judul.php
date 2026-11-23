<?php
// api/cek-judul.php — Cari judul lain yang mirip (D11). Dipakai form pengajuan judul.
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 300);
$kecuali = role() === 'mahasiswa' ? entityId() : null;
$hasil = [];
foreach (judulMirip($q, $kecuali, 5) as $r) {
    $hasil[] = [
        'judul'    => $r['judul_indonesia'],
        'status'   => label($r['status']),
        'angkatan' => $r['angkatan'],
        // Nama mahasiswa lain hanya ditampilkan untuk dosen/admin
        'nama'     => role() === 'mahasiswa' ? null : $r['nama_lengkap'],
        'skor'     => $r['skor'],
    ];
}
echo json_encode(['hasil' => $hasil], JSON_UNESCAPED_UNICODE);
