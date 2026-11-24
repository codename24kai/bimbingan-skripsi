<?php
// admin/dosen.php — Dipindahkan ke halaman gabungan Kelola Pengguna.
// Dipertahankan agar tautan/bookmark lama tetap berfungsi.
require_once __DIR__ . '/../config/app.php';
$q = isset($_GET['q']) ? '&q=' . urlencode((string)$_GET['q']) : '';
header('Location: ' . BASE_URL . '/admin/pengguna.php?peran=dosen' . $q);
exit;
