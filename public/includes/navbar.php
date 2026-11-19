<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/auth/session.php';

$user = current_user();
$active = $active_nav ?? '';

if ($active === '') {
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $detected = [
        'index.php' => 'feed',
        'report_new.php' => 'report',
        'my_reports.php' => 'reports',
        'claim_new.php' => 'claims',
        'my_claims.php' => 'claims',
        'login.php' => 'login',
        'register.php' => 'register',
        'dashboard.php' => 'dashboard',
        'users.php' => 'users',
        'user_form.php' => 'users',
        'review_queue.php' => 'review',
        'claims.php' => 'claims',
        'matches.php' => 'matches',
        'returns.php' => 'returns',
    ];
    $active = $detected[$script] ?? '';
}

if ($user === null) {
    $home = url('login.php');
    $links = [
        ['login', 'Log in', url('login.php')],
        ['register', 'Register', url('register.php')],
    ];
} elseif ($user['role'] === ROLE_ADMIN) {
    $home = url('admin/dashboard.php');
    $links = [
        ['dashboard', 'Dashboard', url('admin/dashboard.php')],
        ['users', 'Users', url('admin/users.php')],
        ['review', 'Review Queue', url('admin/review_queue.php')],
        ['claims', 'Claims', url('admin/claims.php')],
        ['matches', 'Matches', url('admin/matches.php')],
        ['returns', 'Returns', url('admin/returns.php')],
    ];
} else {
    $home = url('index.php');
    $links = [
        ['feed', 'Feed', url('index.php')],
        ['report', 'Report Item', url('student/report_new.php')],
        ['reports', 'My Reports', url('student/my_reports.php')],
        ['claims', 'My Claims', url('student/my_claims.php')],
    ];
}
?>
<header class="site-header">
    <div class="container site-header-inner">
        <a class="brand" href="<?= e($home) ?>">
            <img src="<?= e(url('assets/img/logo.svg')) ?>" width="40" height="40" alt="">
            <span>
                <span class="brand-name">ByteBack</span>
                <span class="brand-tag">Bicol University</span>
            </span>
        </a>
        <button
            type="button"
            class="nav-toggle"
            data-nav-toggle
            aria-expanded="false"
            aria-controls="site-nav"
        >
            <span class="sr-only">Menu</span>
            <span class="nav-toggle-bar" aria-hidden="true"></span>
            <span class="nav-toggle-bar" aria-hidden="true"></span>
            <span class="nav-toggle-bar" aria-hidden="true"></span>
        </button>
        <nav id="site-nav" class="site-nav" aria-label="Primary">
            <ul class="nav-links">
                <?php foreach ($links as [$key, $label, $href]): ?>
                    <li>
                        <a
                            href="<?= e($href) ?>"
                            <?= $active === $key ? 'aria-current="page"' : '' ?>
                        ><?= e($label) ?></a>
                    </li>
                <?php endforeach; ?>
                <?php if ($user !== null): ?>
                    <li>
                        <a class="nav-logout" href="<?= e(url('logout.php')) ?>" id="logout-link">Log out</a>
                    </li>

                    <dialog id="logout-dialog" class="confirm-dialog">
                        <form method="dialog">
                            <button type="submit" value="cancel" class="confirm-close" aria-label="Close">&times;</button>
                            <h2>Log out?</h2>
                            <p>Are you sure you want to log out?</p>
                            <div class="confirm-actions">
                                <button value="cancel" class="btn">Cancel</button>
                                <a class="btn btn-danger" href="<?= e(url('logout.php')) ?>">Log out</a>
                            </div>
                        </form>   
                    </dialog>
                    
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>
<div class="container">
    <?php flash_render(); ?>
</div>
