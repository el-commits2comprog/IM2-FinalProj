<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../models/User.php';

/**
 * Guests go to the login page. Inactive accounts are signed out.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        flash_set('error', 'Please log in.');
        redirect(url('login.php'));
    }

    $sessionUser = current_user();
    $record = $sessionUser === null ? null : user_find_by_id($sessionUser['user_id']);

    if ($record === null || $record['status'] !== USER_STATUS_ACTIVE) {
        logout_user();
        start_session();
        flash_set('error', 'This account is inactive. Contact an administrator.');
        redirect(url('login.php'));
    }
}

/**
 * Stop the request when the signed-in role is not the one this page allows.
 */
function require_role(string $role): void
{
    require_login();

    $user = current_user();

    if ($user !== null && $user['role'] === $role) {
        return;
    }

    http_response_code(403);
    flash_set('error', 'You do not have access to that page.');
    $page_title = 'Access denied';
    $active_nav = '';

    require __DIR__ . '/../../public/includes/header.php';
    require __DIR__ . '/../../public/includes/navbar.php';
    echo '<main class="container narrow">';
    echo '<section class="card">';
    echo '<h1>Access denied</h1>';
    $home = ($user['role'] ?? '') === ROLE_ADMIN ? url('admin/dashboard.php') : url('index.php');
    echo '<p>Your account cannot open this page.</p>';
    echo '<p><a class="btn btn-primary" href="' . e($home) . '">Continue</a></p>';
    echo '</section>';
    echo '</main>';
    require __DIR__ . '/../../public/includes/footer.php';
    exit;
}

/**
 * Admin lands on the dashboard (M4). Students land on the feed (M2).
 */
function redirect_by_role(): void
{
    $user = current_user();

    if ($user === null) {
        redirect(url('login.php'));
    }

    if ($user['role'] === ROLE_ADMIN) {
        redirect(url('admin/dashboard.php'));
    }

    redirect(url('index.php'));
}

function csrf_token(): string
{
    $token = $_SESSION['csrf_token'] ?? '';

    if (!is_string($token) || $token === '') {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
    }

    return $token;
}

/**
 * Hidden input for every POST form. Already escaped.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    $sent = $_POST['csrf_token'] ?? '';

    if (!is_string($sent) || $sent === '') {
        return false;
    }

    return hash_equals(csrf_token(), $sent);
}
