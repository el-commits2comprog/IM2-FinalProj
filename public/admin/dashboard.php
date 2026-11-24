<?php
declare(strict_types=1);

// Load authentication and shared functions.
require_once __DIR__ . '/../../app/auth/guard.php';

// Load the models used for the counts.
require_once __DIR__ . '/../../app/models/ItemReview.php';
require_once __DIR__ . '/../../app/models/Claim.php';
require_once __DIR__ . '/../../app/models/itemMatch.php';
require_once __DIR__ . '/../../app/models/Transaction.php';

// Only admins can access this page.
require_role(ROLE_ADMIN);

$me = current_user();

// Count the work waiting for the admin.
$pendingReports = count(item_review_pending_all());   // reports waiting for approval
$pendingClaims = count(claim_all_pending());          // claims waiting for approval
$possibleMatches = count(item_match_suggestions());   // Lost/Found pairs that look alike

// Count the Found items that nobody has claimed yet (approved, not claimed).
$stmt = db()->prepare('SELECT COUNT(*) FROM items WHERE item_type = :item_type AND status = :status');
$stmt->execute(['item_type' => ITEM_TYPE_FOUND, 'status' => ITEM_STATUS_APPROVED]);
$unclaimedItems = (int) $stmt->fetchColumn();

// Count the returns.
$awaitingReturn = transaction_count_awaiting_return();  // approved claims, item not handed over yet
$totalReturned = transaction_count_returned();          // items already handed over

// Each card: label, number, short help text, and the page it opens (or null).
$cards = [
    ['Pending reports', $pendingReports, 'Lost and Found reports to review', url('admin/review_queue.php')],
    ['Pending claims', $pendingClaims, 'Student claims to review', url('admin/claims.php')],
    ['Possible matches', $possibleMatches, 'Lost and Found pairs that look alike', url('admin/matches.php')],
    ['Unclaimed found items', $unclaimedItems, 'Approved Found items with no claim yet', null],
    ['Waiting to be returned', $awaitingReturn, 'Approved claims, item not handed over', url('admin/returns.php')],
    ['Items returned', $totalReturned, 'Handed over to their owners', url('admin/returns.php')],
];

// Set the page title and navigation.
$page_title = 'Dashboard';
$active_nav = 'dashboard';

// Load the shared layout.
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>

<main id="main" class="container page">

    <div class="page-header">
        <div>
            <p class="eyebrow">Administration</p>
            <h1>Dashboard</h1>
            <p class="muted">
                Welcome, <?= e((string) ($me['full_name'] ?? 'Admin')) ?>. Here is what needs your attention.
            </p>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">

        <?php foreach ($cards as [$label, $count, $help, $link]): ?>

            <section class="card" style="padding: 20px;">
                <p class="eyebrow"><?= e($label) ?></p>
                <p style="font-size: 2.5rem; font-weight: 700; margin: 4px 0;">
                    <?= e((string) $count) ?>
                </p>
                <p class="muted"><?= e($help) ?></p>

                <?php if ($link !== null): ?>
                    <a class="btn btn-secondary btn-small" href="<?= e($link) ?>">Open</a>
                <?php endif; ?>
            </section>

        <?php endforeach; ?>

    </div>

</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>