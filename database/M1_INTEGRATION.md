# ByteBack M1 handoff guide

This document is the M1 handoff for M2, M3, and M4. M1 owns the auth, account management, session bootstrap, base helpers, and the SQL seed data. Other modules must not edit M1 files without telling M1 first.

## 1) Setup

1. Install XAMPP and start Apache + MySQL.
2. Copy `config/config.sample.php` to `config/config.php`.
3. In `config/config.php`, confirm:
   - `DB_HOST` = `127.0.0.1` or your local MySQL host.
   - `DB_PORT` = `3306`.
   - `DB_NAME` = `byteback`.
   - `DB_USER` = `root`.
   - `DB_PASS` = your local MySQL password if needed.
   - `BASE_URL` = `http://localhost/byteback/public`.
4. Run the SQL files in this order from the `database/` folder:
   - `00_create_database.sql`
   - `01_users.sql`
5. `run_all.sql` also runs these files, but the `SOURCE` command only works in the MySQL CLI. In phpMyAdmin or MySQL Workbench, run `00_create_database.sql` and `01_users.sql` directly instead.

> Do not point the Apache document root to `public/` unless `/app` is also served, or handlers will 404.

## 2) Sample logins

Every sample account uses the same password: `Password123!`.

- Admin: `maria.lourdes.santos@bicol-u.edu.ph`
- Student: `rafael.cruz@bicol-u.edu.ph`
- Student: `ana.villanueva@bicol-u.edu.ph`
- Student: `jose.mendoza@bicol-u.edu.ph`

## 3) Shared M1 patterns and rules

The source of truth is `config/constants.php`.

- Roles: `ROLE_ADMIN`, `ROLE_STUDENT`
- Statuses: `USER_STATUS_ACTIVE`, `USER_STATUS_INACTIVE`
- Student number format: `STUDENT_NUMBER_PATTERN = '/^\d{4}-\d{2}-\d{5}$/'`, example `STUDENT_NUMBER_EXAMPLE = '2024-01-13830'`
- Allowed email domain: `ALLOWED_EMAIL_DOMAIN = 'bicol-u.edu.ph'`
- Item constants and claim constants are proposed by M1 and must be confirmed before M2/M3 start.

Never retype these strings in PHP or SQL. Use the constants instead.

## 4) Shared page variables and valid navigation keys

`public/includes/header.php` reads these variables:

- `$page_title` (page title, default `ByteBack`)
- `$extra_css` (optional extra stylesheet array)

`public/includes/navbar.php` reads:

- `$active_nav` (navigation highlight)
- `$page_title` is optional for page titles but is not required by the navbar itself

Valid `$active_nav` values are:

- Guest pages: `login`, `register`
- Student pages: `feed`, `report`, `reports`, `claims`
- Admin pages: `dashboard`, `users`, `review`, `claims`, `matches`, `returns`

If a page uses a custom nav key, it must be added to the `navbar.php` switch logic and kept in sync with the valid keys above.

## 5) Skeletons for teammates

### Protected student page

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/auth/guard.php';
require_login();
require_role(ROLE_STUDENT);

$page_title = 'My Page';
$active_nav = 'feed';

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<main id="main" class="container">
    <section class="card">
        <h1>Student page</h1>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
```

### Protected admin page

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/auth/guard.php';
require_login();
require_role(ROLE_ADMIN);

$page_title = 'Admin Page';
$active_nav = 'users';

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<main id="main" class="container page">
    <section class="card">
        <h1>Admin page</h1>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
```

### POST handler skeleton

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/guard.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    flash_set('error', 'That action is not allowed.');
    redirect(url('index.php'));
}

if (!csrf_verify()) {
    flash_set('error', 'Your session expired. Please try again.');
    redirect(url('index.php'));
}

$payload = trim($_POST['field'] ?? '');

if ($payload === '') {
    flash_set('error', 'Please provide a value.');
    redirect(url('index.php'));
}

flash_set('success', 'Saved.');
redirect(url('index.php'));
```

### Model function skeleton

```php
<?php
declare(strict_types=1);

function item_find_by_id(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT item_id, item_type, status FROM items WHERE item_id = :item_id LIMIT 1'
    );
    $stmt->execute(['item_id' => $id]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}
```

## 6) Shared CSS classes

The base theme is defined in the public CSS. Reuse these classes when you add pages:

- Buttons: `btn`, `btn-primary`, `btn-secondary`, `btn-danger`, `btn-ghost`, `btn-small`, `btn-block`
- Forms: `form-control`, `form-group`, `form-grid`, `form-span`, `password-field`
- Cards: `card`, `page`, `page-header`, `table-wrap`, `toolbar`
- Tables: `table-actions`, `inline-form`, `filter-empty`, `you-tag`
- Alerts: `alert`, `alert-success`, `alert-error`, `alert-warning`, `alert-info`, `flash-stack`
- Status chips: `chip`, `chip-pending`, `chip-approved`, `chip-rejected`, `chip-claimed`, `chip-returned`, `chip-active`, `chip-inactive`, `chip-lost`, `chip-found`

## 7) Guardrails for M1 and the modules

- Do not edit M1 files without telling M1.
- M1 owns: `app/auth/*`, `app/helpers/*`, `app/handlers/login_handler.php`, `app/handlers/register_handler.php`, `app/handlers/user_save_handler.php`, `app/models/User.php`, `config/constants.php`, `config/database.php`, `database/00_create_database.sql`, `database/01_users.sql`, `public/login.php`, `public/register.php`, `public/admin/user_form.php`, `public/admin/users.php`, `public/includes/header.php`, `public/includes/navbar.php`, `uploads/.htaccess`, and `public/.htaccess`.
- M2, M3, and M4 own their own modules and should not edit the M1 files above without coordination.
- To add a new SQL file for a later phase, append the `SOURCE` line to `database/run_all.sql` in the correct order, then keep the file name and sequence documented in the handoff.

## 8) Pages M1 links to that other modules must create

The following pages are intentionally referenced by M1 but not implemented here:

- `public/index.php` (student home feed)
- `public/student/report_new.php`
- `public/student/my_reports.php`
- `public/student/my_claims.php`
- `public/admin/dashboard.php`
- `public/admin/review_queue.php`
- `public/admin/claims.php`
- `public/admin/matches.php`
- `public/admin/returns.php`

M1 may link to these pages, but it does not own their implementation. The `public/index.php` student home page must not call `redirect_by_role()`. It should redirect guests to `login.php` and direct admins to `admin/dashboard.php` without looping back to the student feed.

## 9) Allowed account rules

- Student self-registration is forced to `ROLE_STUDENT`.
- Email addresses must be normalized to lowercase before storage and lookup.
- Only users with a `@bicol-u.edu.ph` email can register or log in.
- Student numbers must match `YYYY-NN-NNNNN` and the year must be between 2000 and the current year.
- Passwords are never printed into views, and CSRF is required on every POST form.

## 10) Module ownership summary

- M1: auth, session, user data, base routing, DB bootstrap, sample accounts, and common helpers.
- M2: lost-item workflows and student item feed.
- M3: claims, matches, and returns logic.
- M4: review queue, dashboard, policies, and management views.

Do not add features that are not in the M1 list.
