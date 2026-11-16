<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/auth/guard.php';

require_role(ROLE_ADMIN);

$me = current_user();
$users = user_all();
$counts = user_count_by_role();

$page_title = 'Users';
$active_nav = 'users';

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<main id="main" class="container page">
    <div class="page-header">
        <div>
            <p class="eyebrow">Administration</p>
            <h1>Users</h1>
            <p class="muted">
                <?= e((string) count($users)) ?> accounts
                · <?= e((string) $counts[ROLE_ADMIN]) ?> admin
                · <?= e((string) $counts[ROLE_STUDENT]) ?> students
            </p>
        </div>
        <a class="btn btn-primary" href="<?= e(url('admin/user_form.php')) ?>">Add user</a>
    </div>

    <div class="toolbar">
        <label class="search-label" for="user-search">Search users</label>
        <input
            id="user-search"
            class="form-control"
            type="search"
            data-user-filter="#users-table"
            placeholder="Name, email, or student number"
        >
    </div>

    <p class="filter-empty" data-filter-empty hidden>No users match that search.</p>

    <div class="table-wrap card">
        <table id="users-table">
            <caption class="sr-only">Campus accounts</caption>
            <thead>
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Student number</th>
                    <th scope="col">Email</th>
                    <th scope="col">Role</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($users === []): ?>
                    <tr>
                        <td colspan="6">No accounts yet.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($users as $account): ?>
                    <?php $isSelf = $me !== null && (int) $account['user_id'] === $me['user_id']; ?>
                    <tr data-search="<?= e(strtolower((string) $account['full_name'] . ' ' . (string) $account['student_number'] . ' ' . (string) $account['email'] . ' ' . (string) $account['role'] . ' ' . (string) $account['status'])) ?>">
                        <td><?= e((string) $account['full_name']) ?></td>
                        <td><?= e($account['student_number'] !== null && $account['student_number'] !== '' ? (string) $account['student_number'] : '—') ?></td>
                        <td><?= e((string) $account['email']) ?></td>
                        <td><?= e((string) $account['role']) ?></td>
                        <td><span class="<?= e(chip_class((string) $account['status'])) ?>"><?= e((string) $account['status']) ?></span></td>
                        <td>
                            <div class="table-actions">
                                <a class="btn btn-secondary btn-small" href="<?= e(url('admin/user_form.php?id=' . (int) $account['user_id'])) ?>">Edit</a>
                                <?php if ($isSelf): ?>
                                    <span class="you-tag">You</span>
                                <?php else: ?>
                                    <form class="inline-form" method="post" action="<?= e(handler_url('user_save_handler.php')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= e((string) $account['user_id']) ?>">
                                        <?php if ($account['status'] === USER_STATUS_ACTIVE): ?>
                                            <input type="hidden" name="action" value="deactivate">
                                            <button class="btn btn-danger btn-small" type="submit" data-confirm="Deactivate this account?">Deactivate</button>
                                        <?php else: ?>
                                            <input type="hidden" name="action" value="reactivate">
                                            <button class="btn btn-secondary btn-small" type="submit" data-confirm="Reactivate this account?">Reactivate</button>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
