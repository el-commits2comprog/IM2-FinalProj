<?php
declare(strict_types=1);

// Load authentication and shared functions.
require_once __DIR__ . '/../../app/auth/guard.php';

// Load M3's Claim model.
require_once __DIR__ . '/../../app/models/Claim.php';

// Only logged-in students can access this page.
require_role(ROLE_STUDENT);

// Get the logged-in student.
$user = current_user();

// Get all claims submitted by this student.
$claims = claim_all_by_student((int) $user['user_id']);

// Set the page title and navigation.
$page_title = 'My Claims';
$active_nav = 'claims';
$extra_css = [url('assets/css/items.css')];

// Load the shared layout.
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>

<main id="main" class="container">


    <section class="card page-head">
        <div>
            <h1>My Claims</h1>
            <p>View the claims you have submitted and their current status.</p>
        </div>
    </section>

    <?php if (empty($claims)): ?>

        <section class="card empty-state">
            <h2>No claims yet</h2>
            <p>
                You haven't submitted any claims.
                When you claim a found item, it will appear here.
            </p>
        </section>

    <?php else: ?>

        <section class="card table-wrap">

            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Type</th>
                        <th>Proof of Ownership</th>
                        <th>Date Submitted</th>
                        <th>Status</th>
                        <th>Admin Remarks</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($claims as $claim): ?>

                        <tr>
                            <td>
                                <?= e($claim['item_name']) ?>
                            </td>

                            <td>
                                <?= e($claim['item_type']) ?>
                            </td>

                           <td>
    <?= nl2br(e($claim['proof_description'])) ?>

    <?php if (!empty($claim['proof_photo'])): ?>
        <p>
            <a
                href="<?= e(url('claim_proof.php?claim_id=' . (int) $claim['claim_id'])) ?>"
                target="_blank"
                rel="noopener"
            >
                View Proof Photo
            </a>
        </p>
    <?php endif; ?>
</td>

                            <td>
                                <?= e($claim['created_at']) ?>
                            </td>

                            <td>
                                <span class="chip chip-<?= e(strtolower($claim['status'])) ?>">
                                    <?= e($claim['status']) ?>
                                </span>
                            </td>

                            <td>
                                <?= e($claim['review_remarks'] ?? 'No remarks yet.') ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                </tbody>
            </table>

        </section>

    <?php endif; ?>

</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
