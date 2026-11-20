<?php
// [M2] My Reports: every report the student submitted, with its current status.
declare(strict_types=1);

require_once __DIR__ . '/../../app/auth/guard.php';
require_once __DIR__ . '/../../app/models/Item.php';

if (!is_logged_in()) {
    header('Location: ' . url('login.php'));
    exit;
}

$user = current_user();
if (($user['role'] ?? '') === ROLE_ADMIN) {
    header('Location: ' . url('admin/dashboard.php'));
    exit;
}

$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME),
    DB_USER,
    DB_PASS,
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$reports = item_my_reports($pdo, (int)($user['user_id'] ?? $user['id']));

$page_title = 'My Reports';
$active_nav = 'reports';
$extra_css  = [url('assets/css/items.css')];
$extra_js   = [url('assets/js/items.js')];

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>
<main id="main" class="container">
    <section class="card page-head">
        <div>
            <h1>My Reports</h1>
            <p>Every item you reported and where it stands.</p>
        </div>
        <a class="btn btn-primary" href="<?= e(url('student/report_new.php')) ?>">+ New report</a>
    </section>

    <?php if (!$reports): ?>
        <section class="card empty-state">
            <h2>No reports yet</h2>
            <p>Lost or found something on campus? Report it and an admin will review it.</p>
        </section>
    <?php else: ?>
        <section class="card table-wrap">
            <table>
                <thead>
                    <tr><th>Item</th><th>Type</th><th>Category</th><th>Date</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php foreach ($reports as $r): ?>
                    <tr>
                        <td>
                            <span class="view-detail clickable-item" data-item-id="<?= (int)$r['item_id'] ?>"><?= e($r['item_name']) ?></span>
                            <?php if ($r['status'] === 'Rejected' && $r['review_remarks']): ?>
                                <div class="muted">Admin note: <?= e($r['review_remarks']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-<?= e(strtolower($r['item_type'])) ?>"><?= e($r['item_type']) ?></span></td>
                        <td><?= e($r['category_name']) ?></td>
                        <td><?= e($r['event_date']) ?></td>
                        <td><span class="chip chip-<?= e(strtolower($r['status'])) ?>"><?= e($r['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    <?php endif; ?>
    <div id="itemModal" class="item-modal" hidden role="dialog" aria-modal="true" aria-labelledby="itemModalTitle" data-url="<?= e(url('student/item_view.php')) ?>">>
        <div class="item-modal-content">
            <button type="button" class="item-modal-close" id="closeItemModal" aria-label="Close">&times;</button>
            <div id="itemModalBody"></div>
        </div>
    </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
