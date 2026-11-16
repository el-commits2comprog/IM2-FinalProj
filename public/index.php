<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth/guard.php';

if (!is_logged_in()) {
    header('Location: ' . url('login.php'));
    exit;
}                     // guests go to login.php

$user = current_user();

// Admins have their own area; send them there directly.
// Do NOT call redirect_by_role() here: it sends students back to index.php (a loop).
if (($user['role'] ?? '') === ROLE_ADMIN) {
    header('Location: ' . url('admin/dashboard.php'));
    exit;
}


$page_title = 'Home';
$active_nav = 'home';                     // must match the key navbar.php checks

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>
<main id="main" class="container">
    <section class="card">
        <h1>Welcome, <?= e($user['full_name'] ?? '') ?></h1>
        <p>The announcements feed will appear here.</p>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>