<?php
// ============================================================
//  config/auth.php — Session & Auth helpers
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION['id_user']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
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

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
