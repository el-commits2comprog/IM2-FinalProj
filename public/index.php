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

$type = $_GET['type'] ?? 'all';
if (!in_array($type, ['all', 'found', 'lost'], true)) {
    $type = 'all';
}
$q = trim((string)($_GET['q'] ?? ''));
$items = [];

$page_title = 'Feed';
$active_nav = 'feed';                     // must match the key navbar.php checks

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/navbar.php';
?>
<main id="main" class="container">
    <section class="card page-head">
        <div>
            <h1>Welcome, <?= e($user['full_name'] ?? '') ?></h1>
            <p>Browse items reported on campus.</p>
        </div>
        <a class="btn btn-primary" href="<?= e(url('student/report_new.php')) ?>">Report an item</a>
    </section>

    <form class="feed-filters" method="get">
        <div class="feed-tabs">
            <a href="?type=all"<?= $type === 'all' ? ' aria-current="true"' : '' ?>>All</a>
            <a href="?type=found"<?= $type === 'found' ? ' aria-current="true"' : '' ?>>Found</a>
            <a href="?type=lost"<?= $type === 'lost' ? ' aria-current="true"' : '' ?>>Lost</a>
        </div>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search items...">
    </form>

    <?php if (!$items): ?>
        <section class="card empty-state">
            <h2>No items yet</h2>
            <p>Nothing matches your search. Try another filter or report an item.</p>
        </section>
    <?php else: ?>
        <div class="feed-grid">
            <?php foreach ($items as $item): ?>
                <article class="card feed-card">
                    <span class="badge badge-<?= e(strtolower($item['type'])) ?>"><?= e($item['type']) ?></span>
                    <h3><?= e($item['item_name']) ?></h3>
                    <p class="muted"><?= e($item['category']) ?></p>
                    <p><?= e($item['location']) ?> · <?= e($item['date']) ?></p>
                    <p class="feed-desc"><?= e($item['description']) ?></p>
                    <?php if ($item['type'] === 'Found'): ?>
                        <a class="btn btn-primary" href="<?= e(url('student/claim_new.php?id=' . $item['id'])) ?>">Claim</a>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>