<?php
// [M2] My Reports: every report the student submitted, with its current status.
declare(strict_types=1);

require_once __DIR__ . '/../../app/auth/guard.php';
require_once __DIR__ . '/../../app/models/Item.php';
require_once __DIR__ . '/../../app/models/itemMatch.php';

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

/*Possible matches are loaded once, and only for this student's own
Approved Lost reports. Found reports never get a matches section. */
$reportMatches = [];

$claimUrl = static fn (int $foundItemId): string =>
    url('student/claim_new.php?item_id=' . $foundItemId);

foreach ($reports as $r) {
    if (
        $r['item_type'] === ITEM_TYPE_LOST &&
        $r['status'] === ITEM_STATUS_APPROVED
    ) {
        $itemId = (int)$r['item_id'];
        $found  = item_match_student_candidates($itemId);

        if ($found) {
            $reportMatches[$itemId] = $found;
        }
    }
}

$reports = array_values(array_filter(
    $reports,
    static function (array $r) use ($reportMatches): bool {
        if (($r['status'] ?? '') === 'Returned') {
            return false;
        }

        $first = $reportMatches[(int)$r['item_id']][0] ?? null;

        return !(
            $first !== null
            && !empty($first['confirmed'])
            && ($first['item']['status'] ?? '') === 'Returned'
        );
    }
));

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
            <p>Stay updated on the status of your reports.</p>
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
                <tr>
                    <th>Item</th>
                    <th>Type</th>
                    <th>Category</th>
                    <th>Date</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>
            <?php foreach ($reports as $r): ?>
                <?php
                $itemId     = (int)$r['item_id'];
                $matches    = $reportMatches[$itemId] ?? [];
                $matchCount  = count($matches);
                $isConfirmed = $matchCount > 0 && !empty($matches[0]['confirmed']);
                ?>
                <tr>
                    <td>
                        <span
                            class="view-detail clickable-item"
                            data-item-id="<?= $itemId ?>"
                        >
                            <?= e($r['item_name']) ?>
                        </span>

                        <?php if ($r['status'] === 'Rejected' && $r['review_remarks']): ?>
                            <div class="muted">
                                Admin note: <?= e($r['review_remarks']) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($matchCount > 0): ?>
                            <button
                                type="button"
                                class="matches-pill view-matches-btn<?= $isConfirmed ? ' is-confirmed' : '' ?>"
                                data-match-id="match-content-<?= $itemId ?>"
                                aria-label="<?= $isConfirmed ? 'View confirmed match' : 'View possible matches' ?> for <?= e($r['item_name']) ?>"
                            >
                                <?php if ($isConfirmed): ?>
                                    <span class="matches-pill-count" aria-hidden="true">&#10003;</span>
                                    Match confirmed
                                <?php else: ?>
                                    <span class="matches-pill-count"><?= $matchCount ?></span>
                                    Possible <?= $matchCount === 1 ? 'match' : 'matches' ?>
                                <?php endif; ?>
                            </button>
                        <?php endif; ?>
                    </td>

                    <td>
                        <span class="badge badge-<?= e(strtolower($r['item_type'])) ?>">
                            <?= e($r['item_type']) ?>
                        </span>
                    </td>

                    <td><?= e($r['category_name']) ?></td>
                    <td><?= e($r['event_date']) ?></td>

                    <td>
                        <span class="chip chip-<?= e(strtolower($r['status'])) ?>">
                            <?= e($r['status']) ?>
                        </span>
                    </td>
                </tr>

                <?php if ($matchCount > 0): ?>
                    <template id="match-content-<?= $itemId ?>">
                        <?php if ($isConfirmed): ?>
                            <h2 id="matchModalTitle">Match confirmed</h2>
                            <p class="muted">
                                An administrator confirmed that this Found report matches your Lost item
                                <strong><?= e($r['item_name']) ?></strong>.
                            </p>
                        <?php else: ?>
                            <h2 id="matchModalTitle">Possible matches</h2>
                            <p class="muted">
                                <?= $matchCount ?> Found <?= $matchCount === 1 ? 'report' : 'reports' ?>
                                might match your lost item
                                <strong><?= e($r['item_name']) ?></strong>.
                            </p>
                        <?php endif; ?>

                        <div class="match-list">
                            <?php foreach ($matches as $match): ?>
                                <?php $candidate = $match['item']; ?>
                                <article class="match-detail-card<?= !empty($match['confirmed']) ? ' is-confirmed' : '' ?>">

                                <?php if ($candidate['photo_path']): ?>
                                    <img
                                        src="<?= e(url('/../app/helpers/upload.php?f=' . urlencode(basename($candidate['photo_path'])))) ?>"
                                        alt="<?= e($candidate['item_name']) ?>" class="item-photo" loading="lazy"
                                    >
                                <?php else: ?>
                                    <div class="item-photo-empty">
                                        <i class="fa-regular fa-image"></i>
                                        <span>No photo attached</span>
                                    </div>
                                <?php endif; ?>

                                <div class="item-modal-info">

                                    <div class="item-badges">
                                        <span class="badge badge-found">Found</span>
                                    </div>

                                    <h2><?= e($candidate['item_name']) ?></h2>

                                    <p class="muted"><?= e($candidate['category_name']) ?></p>

                                    <div class="item-meta">
                                        <p>
                                            <i class="fa-solid fa-location-dot"></i>
                                            <?= e($candidate['location']) ?>
                                        </p>

                                        <p>
                                            <i class="fa-regular fa-calendar"></i>
                                            <?= e($candidate['event_date']) ?>
                                        </p>

                                        <p>
                                            <i class="fa-solid fa-user"></i>
                                            Reported by <?= e($candidate['reporter_name'] ?? 'Unknown') ?>
                                        </p>
                                    </div>

                                    <div class="item-description">
                                        <h3>Description</h3>
                                        <p><?= nl2br(e($candidate['description'])) ?></p>
                                    </div>

                                    <div class="item-modal-actions">
                                        <a
                                            class="btn btn-primary"
                                            href="<?= e($claimUrl((int)$candidate['item_id'])) ?>"
                                        >
                                            Claim this item
                                        </a>
                                    </div>
                                </div>
                            </article>
                            <?php endforeach; ?>
                        </div>

                        <p class="muted match-notice">
                            <?php if ($isConfirmed): ?>
                                An administrator confirmed this match. Click "Claim this item" and
                                submit your proof of ownership to complete your claim.
                            <?php else: ?>
                                These are only suggested matches. Ownership must still be
                                verified and approved by an administrator.
                            <?php endif; ?>
                        </p>
                    </template>
                <?php endif; ?>

            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <?php endif; ?>

    <!-- item details modal -->
    <div id="itemModal" class="item-modal" hidden role="dialog" aria-modal="true" aria-labelledby="itemModalTitle" data-url="<?= e(url('student/item_view.php')) ?>">
        <div class="item-modal-content">
            <button type="button" class="item-modal-close" id="closeItemModal" aria-label="Close">&times;</button>
            <div id="itemModalBody"></div>
        </div>
    </div>

    <!-- possible matches modal -->
    <div id="matchModal" class="item-modal" hidden role="dialog" aria-modal="true" aria-labelledby="matchModalTitle">
        <div class="item-modal-content">
            <button type="button" class="item-modal-close" id="closeMatchModal" aria-label="Close">&times;</button>
            <div id="matchModalBody"></div>
        </div>
    </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>