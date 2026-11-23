<?php
declare(strict_types=1);

// Load authentication and shared functions.
require_once __DIR__ . '/../../app/auth/guard.php';

// Load the Claim model.
require_once __DIR__ . '/../../app/models/Claim.php';

// Only admins can access this page.
require_role(ROLE_ADMIN);

// Get all pending claims.
$claims = claim_all_pending();

// Set the page title and navigation.
$page_title = 'Claims';
$active_nav = 'claims';

// Load the shared layout.
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>

<main id="main" class="container page">


    <div class="page-header">
        <div>
            <p class="eyebrow">Administration</p>
            <h1>Claims</h1>
            <p class="muted">
                <?= e((string) count($claims)) ?> pending claims to review.
            </p>
        </div>
    </div>

    <?php if ($claims === []): ?>

        <section class="card empty-state">
            <h2>No pending claims</h2>
            <p>There are currently no student claims waiting for review.</p>
        </section>

    <?php else: ?>

        <?php foreach ($claims as $claim): ?>

            <section class="card" style="margin-bottom: 20px; padding: 20px;">

                <h2><?= e($claim['item_name']) ?></h2>

                <p>
                    <strong>Item Type:</strong>
                    <?= e($claim['item_type']) ?>
                </p>

                <p>
                    <strong>Claimant:</strong>
                    <?= e($claim['claimant_name']) ?>
                </p>

                <p>
                    <strong>Proof of Ownership:</strong>
                </p>

                <p><?= nl2br(e($claim['proof_description'])) ?></p>

                <p>
                    <strong>Date Submitted:</strong>
                    <?= e($claim['created_at']) ?>
                </p>

<?php if (!empty($claim['proof_photo'])): ?>
    <p>
        <strong>Proof Photo:</strong>
        <a
            href="<?= e(url('claim_proof.php?claim_id=' . (int) $claim['claim_id'])) ?>"
            target="_blank"
            rel="noopener"
        >
            View Submitted Photo
        </a>
    </p>
<?php endif; ?>

                <hr>

                <form
                    method="POST"
                    action="<?= e(handler_url('claim_review_handler.php')) ?>"
                >

                    <?= csrf_field() ?>

                    <input
                        type="hidden"
                        name="claim_id"
                        value="<?= (int) $claim['claim_id'] ?>"
                    >

                    <div class="field">
                        <label for="remarks-<?= (int) $claim['claim_id'] ?>">
                            Admin Remarks
                        </label>

                        <textarea
                            id="remarks-<?= (int) $claim['claim_id'] ?>"
                            name="remarks"
                            rows="3"
                            maxlength="1000"
                            placeholder="Enter your reason or remarks..."
                        ></textarea>
                    </div>

                    <div class="form-actions">

                        <button
                            type="submit"
                            name="decision"
                            value="Approved"
                            class="btn btn-primary"
                            data-confirm="Approve this claim?"
                        >
                            Approve Claim
                        </button>

                        <button
                            type="submit"
                            name="decision"
                            value="Rejected"
                            class="btn btn-danger"
                            data-confirm="Reject this claim?"
                        >
                            Reject Claim
                        </button>

                    </div>

                </form>

            </section>

        <?php endforeach; ?>

    <?php endif; ?>

</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
