<?php

declare(strict_types=1);

/**
 * One-shot success and error messages, plus form values kept after a failed POST.
 */

function flash_set(string $type, string $message): void
{
    $allowed = ['success', 'error', 'warning', 'info'];

    if (!in_array($type, $allowed, true)) {
        $type = 'info';
    }

    if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
        $_SESSION['_flash'] = [];
    }

    $_SESSION['_flash'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

/**
 * Read and clear the flash queue.
 *
 * @return list<array{type: string, message: string}>
 */
function flash_get_all(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);

    if (!is_array($messages)) {
        return [];
    }

    return array_values(array_filter($messages, 'is_array'));
}

/**
 * Accessible alerts. Calling this twice in one request shows nothing the second time.
 */
function flash_render(): void
{
    $messages = flash_get_all();

    if ($messages === []) {
        return;
    }

    $allowed = ['success', 'error', 'warning', 'info'];

    echo '<div class="flash-stack">';

    foreach ($messages as $flash) {
        $type = (string) ($flash['type'] ?? 'info');
        if (!in_array($type, $allowed, true)) {
            $type = 'info';
        }

        $role = ($type === 'error' || $type === 'warning') ? 'alert' : 'status';
        $message = (string) ($flash['message'] ?? '');

        echo '<div class="alert alert-' . e($type) . '" role="' . $role . '" data-flash>';
        echo '<p>' . e($message) . '</p>';
        echo '<button type="button" class="alert-close" data-flash-close aria-label="Dismiss message">&times;</button>';
        echo '</div>';
    }

    echo '</div>';
}

/**
 * Remember non-secret fields so the form can be filled in again.
 * Passwords are dropped here even if a caller includes them.
 *
 * @param array<string, mixed> $input
 */
function flash_old(array $input): void
{
    unset($input['password'], $input['password_confirm'], $input['password_hash'], $input['csrf_token']);

    $clean = [];

    foreach ($input as $key => $value) {
        if (!is_string($key) || !is_scalar($value)) {
            continue;
        }

        $clean[$key] = (string) $value;
    }

    $_SESSION['_old'] = $clean;
}

/**
 * @return array<string, string>
 */
function flash_old_input(): array
{
    static $loaded = false;
    static $data = [];

    if ($loaded) {
        return $data;
    }

    $stored = $_SESSION['_old'] ?? [];
    unset($_SESSION['_old']);
    $loaded = true;
    $data = is_array($stored) ? $stored : [];

    return $data;
}

function old(string $key, string $default = ''): string
{
    $data = flash_old_input();

    if (!array_key_exists($key, $data)) {
        return $default;
    }

    return (string) $data[$key];
}
