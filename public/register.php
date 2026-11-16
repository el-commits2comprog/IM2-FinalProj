<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth/guard.php';

if (is_logged_in()) {
    redirect_by_role();
}

$page_title = 'Register';
$active_nav = 'register';

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>
<main id="main" class="auth-layout">
    <section class="auth-aside">
        <img src="<?= e(url('assets/img/logo.svg')) ?>" width="72" height="72" alt="">
        <p class="eyebrow">Student registration</p>
        <h1>Open a ByteBack account.</h1>
        <p>Registration is for students. Staff accounts are created by an administrator. Your role is assigned on the server.</p>
    </section>
    <section class="card auth-card">
        <h2>Register</h2>
        <form method="post" action="<?= e(handler_url('register_handler.php')) ?>">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="full_name">Full name</label>
                <input
                    class="form-control"
                    type="text"
                    id="full_name"
                    name="full_name"
                    value="<?= e(old('full_name')) ?>"
                    maxlength="100"
                    autocomplete="name"
                    required
                >
            </div>
            <div class="form-group">
                <label for="student_number">Student number</label>
                <input
                    class="form-control"
                    type="text"
                    id="student_number"
                    name="student_number"
                    value="<?= e(old('student_number')) ?>"
                    maxlength="20"
                    inputmode="numeric"
                    placeholder="2023-10421"
                    autocomplete="off"
                    required
                >
                <p class="help">Use the form year-number, such as 2023-10421.</p>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input
                    class="form-control"
                    type="email"
                    id="email"
                    name="email"
                    value="<?= e(old('email')) ?>"
                    maxlength="100"
                    autocomplete="email"
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
                        minlength="8"
                        maxlength="72"
                        autocomplete="new-password"
                        required
                    >
                    <button type="button" class="btn btn-ghost btn-small" data-toggle-password="#password" aria-pressed="false">Show</button>
                </div>
                <p class="help">At least 8 characters.</p>
            </div>
            <div class="form-group">
                <label for="password_confirm">Confirm password</label>
                <div class="password-field">
                    <input
                        class="form-control"
                        type="password"
                        id="password_confirm"
                        name="password_confirm"
                        minlength="8"
                        maxlength="72"
                        autocomplete="new-password"
                        required
                    >
                    <button type="button" class="btn btn-ghost btn-small" data-toggle-password="#password_confirm" aria-pressed="false">Show</button>
                </div>
            </div>
            <button class="btn btn-primary btn-block" type="submit">Create account</button>
        </form>
        <p class="form-switch">Already registered? <a href="<?= e(url('login.php')) ?>">Log in</a></p>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
