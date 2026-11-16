<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/auth/guard.php';

require_role(ROLE_ADMIN);

$me = current_user();
$userId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$account = null;

if ($userId > 0) {
    $account = user_find_by_id($userId);
    if ($account === null) {
        flash_set('error', 'That user does not exist.');
        redirect(url('admin/users.php'));
    }
}

$isSelf = $account !== null && $me !== null && (int) $account['user_id'] === $me['user_id'];
$fullName = old('full_name', $account['full_name'] ?? '');
$studentNumber = old('student_number', $account['student_number'] ?? '');
$email = old('email', $account['email'] ?? '');
$role = old('role', $account['role'] ?? ROLE_STUDENT);
$status = old('status', $account['status'] ?? USER_STATUS_ACTIVE);

$page_title = $account === null ? 'Add user' : 'Edit user';
$active_nav = 'users';

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<main id="main" class="container page narrow">
    <div class="page-header">
        <div>
            <p class="eyebrow">Administration</p>
            <h1><?= e($page_title) ?></h1>
            <p class="muted"><?= $account === null ? 'Create a student or staff account.' : 'Update this account. Leave the password blank to keep it.' ?></p>
        </div>
        <a class="btn btn-secondary" href="<?= e(url('admin/users.php')) ?>">Back to users</a>
    </div>

    <?php if ($isSelf): ?>
        <p class="note">This is your account. You can update your name, email, and password. You cannot change your own role or deactivate yourself.</p>
    <?php endif; ?>

    <section class="card">
        <form method="post" action="<?= e(handler_url('user_save_handler.php')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <?php if ($account !== null): ?>
                <input type="hidden" name="user_id" value="<?= e((string) $account['user_id']) ?>">
            <?php endif; ?>

            <div class="form-grid">
                <div class="form-group">
                    <label for="full_name">Full name</label>
                    <input class="form-control" type="text" id="full_name" name="full_name" value="<?= e($fullName) ?>" maxlength="100" required>
                </div>
                <div class="form-group">
                    <label for="student_number">Student number</label>
                    <input class="form-control" type="text" id="student_number" name="student_number" value="<?= e((string) $studentNumber) ?>" maxlength="20" placeholder="2023-10421">
                    <p class="help">Required for students. Optional for admins. Format: 2023-10421.</p>
                </div>
                <div class="form-group form-span">
                    <label for="email">Email</label>
                    <input class="form-control" type="email" id="email" name="email" value="<?= e($email) ?>" maxlength="100" required>
                </div>
                <div class="form-group">
                    <label for="role">Role</label>
                    <select class="form-control" id="role" name="role">
                        <option value="<?= e(ROLE_STUDENT) ?>"<?= $role === ROLE_STUDENT ? ' selected' : '' ?>><?= e(ROLE_STUDENT) ?></option>
                        <option value="<?= e(ROLE_ADMIN) ?>"<?= $role === ROLE_ADMIN ? ' selected' : '' ?>><?= e(ROLE_ADMIN) ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select class="form-control" id="status" name="status">
                        <option value="<?= e(USER_STATUS_ACTIVE) ?>"<?= $status === USER_STATUS_ACTIVE ? ' selected' : '' ?>><?= e(USER_STATUS_ACTIVE) ?></option>
                        <option value="<?= e(USER_STATUS_INACTIVE) ?>"<?= $status === USER_STATUS_INACTIVE ? ' selected' : '' ?>><?= e(USER_STATUS_INACTIVE) ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-field">
                        <input class="form-control" type="password" id="password" name="password" minlength="8" maxlength="72" autocomplete="new-password"<?= $account === null ? ' required' : '' ?>>
                        <button type="button" class="btn btn-ghost btn-small" data-toggle-password="#password" aria-pressed="false">Show</button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="password_confirm">Confirm password</label>
                    <div class="password-field">
                        <input class="form-control" type="password" id="password_confirm" name="password_confirm" minlength="8" maxlength="72" autocomplete="new-password"<?= $account === null ? ' required' : '' ?>>
                        <button type="button" class="btn btn-ghost btn-small" data-toggle-password="#password_confirm" aria-pressed="false">Show</button>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><?= $account === null ? 'Create user' : 'Save changes' ?></button>
            </div>
        </form>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
