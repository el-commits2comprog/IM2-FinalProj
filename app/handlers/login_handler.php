<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/guard.php';

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCK_SECONDS = 60;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    flash_set('error', 'That action is not allowed.');
    redirect(url('login.php'));
}

if (!csrf_verify()) {
    flash_set('error', 'Your session expired. Please try again.');
    redirect(url('login.php'));
}

$email = normalize_email(login_input('email'));
$password = login_input('password');

if (login_is_locked()) {
    flash_set('error', 'Too many attempts. Please wait ' . login_lock_seconds_left() . ' seconds and try again.');
    flash_old(['email' => $email]);
    redirect(url('login.php'));
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !is_allowed_email_domain($email) || $password === '') {
    login_note_failure();

    if (login_is_locked()) {
        flash_set('error', 'Too many attempts. Please wait ' . LOGIN_LOCK_SECONDS . ' seconds and try again.');
    } else {
        flash_set('error', 'Invalid email or password.');
    }

    flash_old(['email' => $email]);
    redirect(url('login.php'));
}

$user = user_find_by_email($email);
$hash = is_array($user) ? (string) ($user['password_hash'] ?? '') : '';

if ($user === null || $hash === '' || !password_verify($password, $hash)) {
    login_note_failure();

    if (login_is_locked()) {
        flash_set('error', 'Too many attempts. Please wait ' . LOGIN_LOCK_SECONDS . ' seconds and try again.');
    } else {
        flash_set('error', 'Invalid email or password.');
    }

    flash_old(['email' => $email]);
    redirect(url('login.php'));
}

if ($user['status'] !== USER_STATUS_ACTIVE) {
    flash_set('error', 'This account is inactive. Contact an administrator.');
    flash_old(['email' => $email]);
    redirect(url('login.php'));
}

login_clear_failures();
login_user($user);
flash_set('success', 'Welcome back.');
redirect_by_role();

function login_input(string $key): string
{
    $value = $_POST[$key] ?? '';

    return is_string($value) ? $value : '';
}

function login_is_locked(): bool
{
    $until = $_SESSION['login_lock_until'] ?? 0;

    return is_int($until) && $until > time() || (is_numeric($until) && (int) $until > time());
}

function login_lock_seconds_left(): int
{
    $until = (int) ($_SESSION['login_lock_until'] ?? 0);

    return max(1, $until - time());
}

function login_note_failure(): void
{
    $attempts = (int) ($_SESSION['login_attempts'] ?? 0) + 1;

    if ($attempts >= LOGIN_MAX_ATTEMPTS) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_lock_until'] = time() + LOGIN_LOCK_SECONDS;
        return;
    }

    $_SESSION['login_attempts'] = $attempts;
}

function login_clear_failures(): void
{
    unset($_SESSION['login_attempts'], $_SESSION['login_lock_until']);
}
