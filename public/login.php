<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth/guard.php';

if (is_logged_in()) {
    redirect_by_role();
}

$page_title = 'Log in';
$active_nav = 'login';

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>
<main id="main" class="auth-layout">
    <section class="auth-aside">
        <img src="<?= e(url('assets/img/logo.svg')) ?>" width="72" height="72" alt="">
        <p class="eyebrow">Campus lost and found</p>
        <h1>Bring lost things back.</h1>
        <p>Students report items. Staff review claims and record returns. Sign in with your campus account.</p>
    </section>
    <section class="card auth-card">
        <h2>Log in</h2>
        <form method="post" action="<?= e(handler_url('login_handler.php')) ?>">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="email">Email</label>
                <input
                    class="form-control"
                    type="email"
                    id="email"
                    name="email"
                    value="<?= e(old('email')) ?>"
                    maxlength="100"
                    autocomplete="username"
                    required
                >
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-field">
                    <input
                        class="form-control"
                        type="password"
                        id="password"
                        name="password"
                        autocomplete="current-password"
                        required
                    >
                    <button type="button" class="btn btn-ghost btn-small" data-toggle-password="#password" aria-pressed="false">Show</button>
                </div>
            </div>
            <button class="btn btn-primary btn-block" type="submit">Log in</button>
        </form>
        <p class="form-switch">New here? <a href="<?= e(url('register.php')) ?>">Create a student account</a></p>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
