# M1 integration notes

`config/constants.php` is the source of truth for role, status, and item-type spellings. Do not retype those strings in SQL or PHP. Item and claim constants are proposed by M1 and must be confirmed before M2 and M3 write their ENUM columns.

## Include one bootstrap

From `public/login.php`:

```php
require_once __DIR__ . '/../app/auth/session.php';
```

From `public/admin/` or `public/student/`:

```php
require_once __DIR__ . '/../../app/auth/guard.php';
require_login();
require_role(ROLE_ADMIN);
```

`session.php` loads `config.php`, `constants.php`, `database.php`, and `flash.php`, then starts the session. `guard.php` loads that bootstrap plus `User.php`, CSRF helpers, and the role checks. Do not open a second PDO connection.

## Everyday calls

- Current account: `current_user()` returns `user_id`, `full_name`, and `role`, or null.
- Flash: `flash_set('success'|'error'|'warning'|'info', 'message')`. `navbar.php` prints the queue once.
- Failed form: `flash_old([...])`, then `old('email')` in the input value. Never store a password in old input.
- Forms: `<?= csrf_field() ?>`. Handlers call `csrf_verify()`.
- Queries: `$stmt = db()->prepare(...)`.
- Page links: `url('student/report_new.php')`.
- POST target: `handler_url('item_submit_handler.php')`.
- HTML: `e($value)`.
- Chips: `chip_class($status)` returns classes such as `chip chip-pending`.

## Setup

1. Copy `config/config.sample.php` to `config/config.php`.
2. Point `BASE_URL` at the `public` directory with no trailing slash.
3. From the `database/` folder, run `run_all.sql`.

`BASE_URL` must end in `/public`. Session cookies are scoped to the project folder so both `public/` pages and `app/handlers/` receive them. Serve the project from XAMPP `htdocs` (for example `http://localhost/IM2-FinalProj/public/`). Do not set the Apache document root to `public/` unless `/app` is aliased, or the handlers will 404.

## Accounts

See the comment block at the top of `database/01_users.sql`. Password for every sample account: `Password123!`.

Student self-registration forces the Student role. Student numbers use `YYYY-NNNNN` (example `2023-10421`) until the team confirms the real Bicol University format.
