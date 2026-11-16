<?php

declare(strict_types=1);

/**
 * ALL MODULES: do not retype these strings, use the constants.
 * They must match the ENUM values in the SQL files exactly.
 *
 * M1 INTEGRATION NOTES
 * --------------------
 * Bootstrap (starts the session and loads config, constants, database, flash):
 *   require_once __DIR__ . '/../app/auth/session.php';          // from public/*.php
 *   require_once __DIR__ . '/../../app/auth/session.php';       // from public/admin or public/student
 *
 * Protect a page (guard.php loads the bootstrap, the user model, and CSRF):
 *   require_once __DIR__ . '/../../app/auth/guard.php';
 *   require_login();
 *   require_role(ROLE_STUDENT);   // or ROLE_ADMIN
 *
 * Current user (user_id, full_name, role) or null:
 *   $user = current_user();
 *   if (is_logged_in()) { ... }
 *
 * Flash messages (rendered once by navbar.php):
 *   flash_set('success', 'Saved.');
 *   flash_set('error', 'Please log in.');
 *
 * Keep typed values after a failed POST, then repopulate with old():
 *   flash_old(['full_name' => $name, 'email' => $email]);
 *
 * Every POST form:
 *   <?= csrf_field() ?>
 * Handlers:
 *   if (!csrf_verify()) { ... }
 *
 * Database:
 *   $stmt = db()->prepare('SELECT user_id FROM users WHERE email = :email');
 *   $stmt->execute(['email' => $email]);
 *
 * Links and assets:
 *   url('admin/users.php')
 *   handler_url('login_handler.php')
 *   e($value) for every value printed into HTML
 *
 * Status chips:
 *   <span class="<?= e(chip_class($status)) ?>"><?= e($status) ?></span>
 *
 * Do not include config.php, database.php, or constants.php yourself.
 * session.php is the single bootstrap. config.php is gitignored.
 */

define('ROLE_ADMIN', 'Admin');
define('ROLE_STUDENT', 'Student');

define('USER_STATUS_ACTIVE', 'Active');
define('USER_STATUS_INACTIVE', 'Inactive');

define('STUDENT_NUMBER_PATTERN', '/^\d{4}-\d{2}-\d{5}$/');
define('STUDENT_NUMBER_EXAMPLE', '2024-01-12345');
define('ALLOWED_EMAIL_DOMAIN', 'bicol-u.edu.ph');

// PROPOSED by M1, team must confirm before M2/M3 start; these must match the ENUM values in the SQL files exactly.
define('ITEM_TYPE_LOST', 'Lost');
define('ITEM_TYPE_FOUND', 'Found');

// PROPOSED by M1, team must confirm before M2/M3 start; these must match the ENUM values in the SQL files exactly.
define('ITEM_STATUS_PENDING', 'Pending');
define('ITEM_STATUS_APPROVED', 'Approved');
define('ITEM_STATUS_REJECTED', 'Rejected');
define('ITEM_STATUS_CLAIMED', 'Claimed');
define('ITEM_STATUS_RETURNED', 'Returned');

// PROPOSED by M1, team must confirm before M2/M3 start; these must match the ENUM values in the SQL files exactly.
define('CLAIM_STATUS_PENDING', 'Pending');
define('CLAIM_STATUS_APPROVED', 'Approved');
define('CLAIM_STATUS_REJECTED', 'Rejected');

function normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function is_valid_student_number(string $value): bool
{
    $value = trim($value);

    if ($value === '' || !preg_match(STUDENT_NUMBER_PATTERN, $value)) {
        return false;
    }

    $year = (int) substr($value, 0, 4);
    return $year >= 2000 && $year <= (int) date('Y');
}

function is_allowed_email_domain(string $email): bool
{
    $normalized = normalize_email($email);

    if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $at = strrpos($normalized, '@');

    if ($at === false) {
        return false;
    }

    $domain = substr($normalized, $at + 1);
    return $domain === ALLOWED_EMAIL_DOMAIN;
}

/**
 * Public URL for a file under public/.
 */
function url(string $path = ''): string
{
    $base = rtrim(BASE_URL, '/');
    $path = ltrim($path, '/');

    return $path === '' ? $base . '/' : $base . '/' . $path;
}

/**
 * URL for a POST handler in app/handlers/.
 * BASE_URL must end with /public. Handlers stay outside the public pages folder.
 */
function handler_url(string $script): string
{
    $publicBase = rtrim(BASE_URL, '/');
    $root = preg_replace('#/public$#i', '', $publicBase);

    return $root . '/app/handlers/' . ltrim($script, '/');
}

/**
 * Escape a value for HTML text or an attribute.
 */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * CSS classes for a status or item type stored in constants.php.
 */
function chip_class(string $label): string
{
    $map = [
        ITEM_STATUS_PENDING => 'chip chip-pending',
        ITEM_STATUS_APPROVED => 'chip chip-approved',
        ITEM_STATUS_REJECTED => 'chip chip-rejected',
        ITEM_STATUS_CLAIMED => 'chip chip-claimed',
        ITEM_STATUS_RETURNED => 'chip chip-returned',
        USER_STATUS_ACTIVE => 'chip chip-active',
        USER_STATUS_INACTIVE => 'chip chip-inactive',
        ITEM_TYPE_LOST => 'chip chip-lost',
        ITEM_TYPE_FOUND => 'chip chip-found',
    ];

    return $map[$label] ?? 'chip';
}
