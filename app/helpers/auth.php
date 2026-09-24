<?php

/**
 * Mulai session dengan pengaturan cookie yang aman.
 */
function mulaiSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        session_start();
    }
}

/**
 * Panggil ini persis setelah password_verify() berhasil.
 * Session di-regenerate biar aman dari session fixation.
 */
function loginUser(array $user): void
{
    mulaiSession();
    session_regenerate_id(true);

    $_SESSION['status']         = 'login';
    $_SESSION['user_id']        = $user['id'];
    $_SESSION['nama']           = $user['nama'] ?? '';
    $_SESSION['username']       = $user['username'] ?? '';
    $_SESSION['email']          = $user['email'] ?? '';
    $_SESSION['role']           = $user['role'] ?? '';
    $_SESSION['last_activity']  = time();
}

function logoutUser(): void
{
    mulaiSession();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie('PHPSESSID', '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

function sudahLogin(): bool
{
    return isset($_SESSION['status'], $_SESSION['user_id']) && $_SESSION['status'] === 'login';
}

/**
 * Path root project, dihitung relatif dari file app/helpers/auth.php ini,
 * biar redirect-nya selalu bener dari kedalaman folder manapun cekLogin() dipanggil.
 */
function baseUrlPath(): string
{
    // app/helpers -> app -> root
    $root = dirname(__DIR__, 2);
    $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $rootReal = str_replace('\\', '/', $root);

    if ($docRoot !== '' && str_starts_with($rootReal, $docRoot)) {
        return substr($rootReal, strlen($docRoot)) ?: '';
    }

    return '';
}

function cekLogin(): void
{
    mulaiSession();

    if (!sudahLogin()) {
        header('Location: ' . baseUrlPath() . '/login/pages/login.php');
        exit;
    }

    // Timeout otomatis kalau gak ada aktivitas 30 menit
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
        logoutUser();
        header('Location: ' . baseUrlPath() . '/login/pages/login.php?expired=1');
        exit;
    }

    $_SESSION['last_activity'] = time();
}

function cekRole(array $roles): void
{
    cekLogin();

    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $roles, true)) {
        http_response_code(403);
        exit('Akses ditolak: kamu gak punya izin buat buka halaman ini.');
    }
}

function currentUser(): ?array
{
    mulaiSession();

    if (!sudahLogin()) {
        return null;
    }

    return [
        'id'       => $_SESSION['user_id'],
        'nama'     => $_SESSION['nama'] ?? '',
        'username' => $_SESSION['username'] ?? '',
        'role'     => $_SESSION['role'] ?? '',
        'email'    => $_SESSION['email'] ?? '',
    ];
}

/* ================= SECURITY HEADERS ================= */

/**
 * Panggil di awal setiap entry point (frontend, login, backend).
 * Menangkal clickjacking, MIME sniffing, dan mengurangi dampak XSS kalau ada yang lolos.
 */
function applySecurityHeaders(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: frame-ancestors 'none'; base-uri 'self';");
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

/* ================= CSRF ================= */

function csrfToken(): string
{
    mulaiSession();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function verifyCsrf(?string $token): bool
{
    mulaiSession();
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/* ================= FLASH MESSAGE ================= */

function setFlash(string $type, string $message): void
{
    mulaiSession();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    mulaiSession();

    if (empty($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}
