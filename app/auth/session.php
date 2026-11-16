<?php

declare(strict_types=1);

/**
 * Single bootstrap for every page and handler.
 * Loads config, shared constants, PDO, and flash messages, then starts the session.
 */

$configFile = __DIR__ . '/../../config/config.php';

if (!is_file($configFile)) {
    http_response_code(500);
    exit('Missing config/config.php. Copy config/config.sample.php to config/config.php and set the database password and BASE_URL.');
}

require_once $configFile;
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../helpers/flash.php';

/**
 * Cookie path covers /public pages and /app/handlers under the same project folder.
 */
function session_cookie_path(): string
{
    $publicPath = parse_url(BASE_URL, PHP_URL_PATH);
    $publicPath = is_string($publicPath) ? rtrim($publicPath, '/') : '';
    $root = preg_replace('#/public$#i', '', $publicPath);

    if (!is_string($root) || $root === '') {
        return '/';
    }

    return $root . '/';
}

/**
 * Start the session once with cookie flags that keep the id off JavaScript.
 */
function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => session_cookie_path(),
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

/**
 * Signed-in account stored in the session, or null.
 * Keys: user_id, full_name, role. The password hash is never stored here.
 *
 * @return array{user_id: int, full_name: string, role: string}|null
 */
function current_user(): ?array
{
    $user = $_SESSION['user'] ?? null;

    if (!is_array($user) || !isset($user['user_id'], $user['full_name'], $user['role'])) {
        return null;
    }

    return [
        'user_id' => (int) $user['user_id'],
        'full_name' => (string) $user['full_name'],
        'role' => (string) $user['role'],
    ];
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

/**
 * Store only the fields other pages need, and rotate the session id.
 *
 * @param array{user_id: int|string, full_name: string, role: string} $user
 */
function login_user(array $user): void
{
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'user_id' => (int) $user['user_id'],
        'full_name' => (string) $user['full_name'],
        'role' => (string) $user['role'],
    ];
}

/**
 * Drop session data and expire the cookie.
 */
function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] !== '' ? $params['path'] : '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool) ($params['secure'] ?? false),
            'httponly' => (bool) ($params['httponly'] ?? true),
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
    session_write_close();
}

/**
 * Send the browser somewhere else and stop this request.
 */
function redirect(string $location): void
{
    header('Location: ' . $location);
    exit;
}

if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}

start_session();
