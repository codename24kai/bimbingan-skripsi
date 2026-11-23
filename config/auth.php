<?php
// ============================================================
//  config/auth.php — Session & Auth helpers
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

// Password bawaan untuk akun baru / data contoh. Pengguna yang login dengan
// salah satu password ini wajib menggantinya sebelum memakai sistem.
const DEFAULT_PASSWORDS = ['mhs123', 'dosen123', 'admin123', 'mahasiswa123'];
const MIN_PASSWORD_LENGTH = 8;
// Password sementara hasil reset admin selalu diawali prefix ini, sehingga
// pengguna wajib menggantinya saat login pertama (tanpa perlu kolom baru).
const TEMP_PASSWORD_PREFIX = 'Reset-';

function isDefaultOrTempPassword(string $password): bool {
    return in_array($password, DEFAULT_PASSWORDS, true)
        || str_starts_with($password, TEMP_PASSWORD_PREFIX);
}

/** Buat password sementara acak, mis. "Reset-k7Qm2xPa". */
function generateTempPassword(): string {
    $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < 8; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return TEMP_PASSWORD_PREFIX . $out;
}

/**
 * Reset password user dengan role tertentu menjadi password sementara.
 * Mengembalikan password sementara, atau null jika user tidak ditemukan.
 */
function resetUserPassword(int $idUser, string $role): ?string {
    $user = fetchOne('SELECT id_user FROM user WHERE id_user=? AND role=?', [$idUser, $role]);
    if (!$user) {
        return null;
    }
    $temp = generateTempPassword();
    execute('UPDATE user SET password_hash=? WHERE id_user=?', [hashPassword($temp), $idUser]);
    return $temp;
}

// ── CSRF ─────────────────────────────────────────────────────

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

// Semua request POST wajib membawa token CSRF yang valid.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sent)) {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Sesi formulir kedaluwarsa. Silakan coba lagi.'];
        $back = strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: (BASE_URL . '/index.php');
        header('Location: ' . $back);
        exit;
    }
}

// ── Password ─────────────────────────────────────────────────

function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Cek password. Mendukung hash SHA-256 lama dan otomatis meng-upgrade
 * ke password_hash() saat login berhasil.
 */
function verifyPassword(string $password, array $user): bool {
    $stored = (string)$user['password_hash'];

    if (preg_match('/^[a-f0-9]{64}$/', $stored)) {
        if (!hash_equals($stored, hash('sha256', $password))) {
            return false;
        }
        execute('UPDATE user SET password_hash=? WHERE id_user=?', [hashPassword($password), $user['id_user']]);
        return true;
    }

    if (!password_verify($password, $stored)) {
        return false;
    }
    if (password_needs_rehash($stored, PASSWORD_DEFAULT)) {
        execute('UPDATE user SET password_hash=? WHERE id_user=?', [hashPassword($password), $user['id_user']]);
    }
    return true;
}

/** Mengisi sesi setelah login berhasil. */
function loginUser(array $user, int $entityId, string $plainPassword): void {
    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);

    $_SESSION['id_user']   = $user['id_user'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['entity_id'] = $entityId;
    $_SESSION['user']      = [
        'id_user'      => $user['id_user'],
        'nama_lengkap' => $user['nama_lengkap'],
        'email'        => $user['email'],
    ];
    $_SESSION['must_change_password'] = isDefaultOrTempPassword($plainPassword);
}

function isLoggedIn(): bool {
    return isset($_SESSION['id_user']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
    // Paksa ganti password bawaan sebelum bisa membuka halaman lain
    if (!empty($_SESSION['must_change_password'])) {
        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        if (!in_array($script, ['profil.php', 'logout.php'], true)) {
            $_SESSION['flash'] = ['type' => 'warning', 'msg' => 'Anda masih memakai password bawaan. Silakan ganti password terlebih dahulu.'];
            header('Location: ' . BASE_URL . '/profil.php');
            exit;
        }
    }
}

function requireRole(string ...$roles): void {
    requireLogin();
    if (!in_array($_SESSION['role'], $roles, true)) {
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}

function currentUser(): array {
    return $_SESSION['user'] ?? [];
}

function role(): string {
    return $_SESSION['role'] ?? '';
}

function userId(): int {
    return (int)($_SESSION['id_user'] ?? 0);
}

// Returns entity id based on role
function entityId(): int {
    return (int)($_SESSION['entity_id'] ?? 0);
}

function setFlash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function badge(string $status): string {
    $map = [
        'disetujui'   => 'success',
        'aktif'       => 'success',
        'selesai'     => 'success',
        'diajukan'    => 'info',
        'dijadwalkan' => 'info',
        'menunggu'    => 'info',
        'proses'      => 'info',
        'revisi'      => 'warning',
        'direvisi'    => 'warning',
        'peringatan'  => 'warning',
        'ditolak'     => 'danger',
        'dihentikan'  => 'danger',
        'batal'       => 'danger',
        'nonaktif'    => 'neutral',
        'belum'       => 'neutral',
        'draft'       => 'neutral',
    ];
    return $map[$status] ?? 'neutral';
}

function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// URL aman untuk file upload (lewat download.php yang memeriksa hak akses)
function fileUrl(?string $path): string {
    return BASE_URL . '/download.php?f=' . rawurlencode((string)$path);
}
