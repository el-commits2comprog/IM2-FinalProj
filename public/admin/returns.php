<?php
declare(strict_types=1);

// Load authentication and shared functions.
require_once __DIR__ . '/../../app/auth/guard.php';

// Load the M4 transaction model.
require_once __DIR__ . '/../../app/models/Transaction.php';

// Only admins can access this page.
require_role(ROLE_ADMIN);

// Get the items waiting to be handed over, and the items already returned.
$awaiting = transaction_awaiting_return();
$returned = transaction_all(TRANSACTION_TYPE_RETURNED);

// Set the page title and navigation.
$page_title = 'Returns';
$active_nav = 'returns';

// Load the shared layout.
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>

<main id="main" class="container page">

    <div class="page-header">
        <div>
            <p class="eyebrow">Administration</p>
            <h1>Returns</h1>
            <p class="muted">
                <?= e((string) count($awaiting)) ?> items waiting to be handed over,
                <?= e((string) count($returned)) ?> already returned.
            </p>
        </div>
    </div>

    <!-- Section 1: approved claims waiting for the handover -->
    <h2>Waiting to be returned</h2>
    <p class="muted">
        Record the return after you hand the item to its owner. This cannot be undone.
    </p>

    <?php if ($awaiting === []): ?>

        <section class="card empty-state">
            <h2>Nothing to return</h2>
            <p>There are no approved claims waiting for a handover.</p>
        </section>

    <?php else: ?>

        <?php foreach ($awaiting as $row): ?>

            <section class="card" style="margin-bottom: 20px; padding: 20px;">

                <h2><?= e($row['item_name']) ?></h2>

                <p>
                    <strong>Category:</strong>
                    <?= e($row['category_name']) ?>
                </p>

                <p>
                    <strong>Owner:</strong>
                    <?= e($row['claimant_name']) ?>
                    <?php if (!empty($row['claimant_student_number'])): ?>
                        (<?= e($row['claimant_student_number']) ?>)
                    <?php endif; ?>
                </p>

                <p>
                    <strong>Email:</strong>
                    <?= e($row['claimant_email']) ?>
                </p>

                <p>
                    <strong>Claim approved on:</strong>
                    <?= e((string) $row['claim_approved_at']) ?>
                </p>

                <p>
                    <strong>Proof of ownership:</strong>
                </p>

                <p><?= nl2br(e($row['proof_description'])) ?></p>

                <hr>

                <form
                    method="POST"
                    action="<?= e(handler_url('return_handler.php')) ?>"
                >

                    <?= csrf_field() ?>

                    <input
                        type="hidden"
                        name="claim_id"
                        value="<?= (int) $row['claim_id'] ?>"
                    >

                    <div class="field">
                        <label for="remarks-<?= (int) $row['claim_id'] ?>">
                            Remarks (optional)
                        </label>

                        <input
                            type="text"
                            id="remarks-<?= (int) $row['claim_id'] ?>"
                            name="remarks"
                            class="form-control"
                            maxlength="255"
                            placeholder="Example: Owner showed a valid school ID."
                        >
                    </div>

                    <div class="form-actions">
                        <button
                            type="submit"
                            class="btn btn-primary"
                            data-confirm="Record that this item was handed over to the owner?"
                        >
                            Record return
                        </button>
                    </div>

                </form>

            </section>

        <?php endforeach; ?>

    <?php endif; ?>

    <!-- Section 2: items already returned -->
    <h2 style="margin-top: 32px;">Return history</h2>

    <?php if ($returned === []): ?>

        <section class="card empty-state">
            <h2>No returns yet</h2>
            <p>Items you hand back to their owners will be listed here.</p>
        </section>

    <?php else: ?>

        <div class="table-wrap card">
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Returned to</th>
                        <th>Handled by</th>
                        <th>Date</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>

                <?php foreach ($returned as $record): ?>
                    <tr>
                        <td><strong><?= e($record['item_name']) ?></strong></td>
                        <td><?= e($record['claimant_name']) ?></td>
                        <td><?= e($record['admin_name']) ?></td>
                        <td><?= e((string) $record['transaction_date']) ?></td>
                        <td class="muted"><?= e((string) ($record['remarks'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>

                </tbody>
            </table>
        </div>

    <?php endif; ?>

</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>