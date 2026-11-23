<?php
declare(strict_types=1);

// Load authentication and shared functions.
require_once __DIR__ . '/../../app/auth/guard.php';

// Load the M3 Item Review model.
require_once __DIR__ . '/../../app/models/ItemReview.php';

// Only administrators can access this page.
require_role(ROLE_ADMIN);

// Get all pending item reports.
$items = item_review_pending_all();

// Set the page title and navigation.
$page_title = 'Review Queue';
$active_nav = 'review';

// Load the shared layout.
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>

<main id="main" class="container page">

    <div class="page-header">
        <div>
            <p class="eyebrow">Administration</p>
            <h1>Review Queue</h1>
            <p class="muted">
                <?= e((string) count($items)) ?> pending item reports to review.
            </p>
        </div>
    </div>

    <?php if ($items === []): ?>

        <section class="card empty-state">
            <h2>No pending reports</h2>
            <p>There are currently no item reports waiting for review.</p>
        </section>

    <?php else: ?>

        <?php foreach ($items as $item): ?>

            <section class="card" style="margin-bottom: 20px; padding: 20px;">

                <h2><?= e($item['item_name'] ?? '') ?></h2>

                <p>
                    <strong>Item Type:</strong>
                    <?= e($item['item_type'] ?? '') ?>
                </p>

                <p>
                    <strong>Category:</strong>
                    <?= e($item['category_name'] ?? '') ?>
                </p>

                <p>
                    <strong>Reported By:</strong>
                    <?= e($item['reporter_name'] ?? '') ?>
                </p>

                <p>
                    <strong>Location:</strong>
                    <?= e($item['location'] ?? '') ?>
                </p>

                <p>
                    <strong>Event Date:</strong>
                    <?= e($item['event_date'] ?? 'Not provided') ?>
                </p>

                <p>
                    <strong>Date Submitted:</strong>
                    <?= e($item['created_at'] ?? '') ?>
                </p>

                <p>
                    <strong>Description:</strong>
                </p>

                <p><?= nl2br(e($item['description'] ?? '')) ?></p>

                <?php if (!empty($item['photo_path'])): ?>
                    <p><strong>Item Photo:</strong></p>

                    <p>
                        <img
                            src="<?= e(url('/../app/helpers/upload.php?f=' . urlencode(basename($item['photo_path'])))) ?>"
                            alt="<?= e($item['item_name'] ?? 'Reported item') ?>"
                            loading="lazy"
                            style="max-width: 100%; max-height: 260px; object-fit: contain;"
                        >
                    </p>
                <?php else: ?>
                    <p class="muted">No photo attached.</p>
                <?php endif; ?>

                <hr>

                <form
                    method="POST"
                    action="<?= e(handler_url('item_review_handler.php')) ?>"
                >

                    <?= csrf_field() ?>

                    <input
                        type="hidden"
                        name="item_id"
                        value="<?= (int) $item['item_id'] ?>"
                    >

                    <div class="field">
                        <label for="remarks-<?= (int) $item['item_id'] ?>">
                            Admin Remarks
                        </label>

                        <textarea
                            id="remarks-<?= (int) $item['item_id'] ?>"
                            name="remarks"
                            rows="3"
                            maxlength="2000"
                            placeholder="Enter your reason or remarks..."
                        ></textarea>
                    </div>

                    <div class="form-actions">

                        <button
                            type="submit"
                            name="decision"
                            value="Approved"
                            class="btn btn-primary"
                            data-confirm="Approve this item report?"
                        >
                            Approve Report
                        </button>

                        <button
                            type="submit"
                            name="decision"
                            value="Rejected"
                            class="btn btn-danger"
                            data-confirm="Reject this item report?"
                        >
                            Reject Report
                        </button>

                    </div>

                </form>

            </section>

        <?php endforeach; ?>

    <?php endif; ?>

</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
