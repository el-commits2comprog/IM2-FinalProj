<?php

declare(strict_types=1);

// Load authentication and shared functions.
require_once __DIR__ . '/../../app/auth/guard.php';

// Load M2's Item model.
require_once __DIR__ . '/../../app/models/Item.php';

// Load M3's Claim model.
require_once __DIR__ . '/../../app/models/Claim.php';

// Only logged-in students can access this page.
require_role(ROLE_STUDENT);

// Process the form when the student submits a claim.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_once __DIR__ . '/../../app/handlers/claim_submit_handler.php';
    exit;
}

// Get the logged-in student.
$user = current_user();

// Connect to the database.
$pdo = db();

// Get the item ID from the URL.
$itemId = filter_input(INPUT_GET, 'item_id', FILTER_VALIDATE_INT);

// Check if the item ID is valid.
if (!$itemId || $itemId < 1) {
    flash_set('error', 'Invalid item selected.');
    redirect(url('index.php'));
}

// Find the selected item.
$item = item_find($pdo, $itemId);

// Check if the item exists.
if ($item === null) {
    flash_set('error', 'Item not found.');
    redirect(url('index.php'));
}

// Only Found items can be claimed.
if (
    $item['item_type'] !== ITEM_TYPE_FOUND ||
    $item['status'] !== ITEM_STATUS_APPROVED
) {
    flash_set('error', 'This item is not available for claiming.');
    redirect(url('index.php'));
}

// Students cannot claim their own reported items.
if ((int) $item['user_id'] === (int) $user['user_id']) {
    flash_set('error', 'You cannot claim your own item.');
    redirect(url('index.php'));
}

// Set the page title and navigation.
$page_title = 'Submit a Claim';
$active_nav = 'claims';
$extra_css = [url('assets/css/items.css')];

// Load the shared page layout.
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>

<main id="main" class="container">


    <section class="card report-form-section">

        <h1>Submit a Claim</h1>

        <p class="muted">
            Provide details to help the admin verify that this item belongs to you.
        </p>

        <!-- Display the selected item. -->
        <div class="important-notice">

            <strong>Item you want to claim:</strong>

            <h2><?= e($item['item_name']) ?></h2>

            <p>
                <strong>Type:</strong>
                <?= e($item['item_type']) ?>
            </p>

            <p>
                <strong>Description:</strong>
                <?= e($item['description']) ?>
            </p>

            <p>
                <strong>Date:</strong>
                <?= e($item['event_date']) ?>
            </p>

            <p>
                <strong>Status:</strong>
                <?= e($item['status']) ?>
            </p>

        </div>

        <!-- Claim submission form. -->
        <form
            class="report-form"
            method="POST"
            enctype="multipart/form-data"
            action="<?= e(url('student/claim_new.php?item_id=' . $itemId)) ?>"
        >

            <?= csrf_field() ?>

            <input
                type="hidden"
                name="item_id"
                value="<?= (int) $itemId ?>"
            >

            <div class="field">

                <label for="proof_description">
                    Proof of Ownership
                </label>

                <textarea
                    id="proof_description"
                    name="proof_description"
                    rows="5"
                    maxlength="1000"
                    placeholder="Describe details that can prove this item belongs to you."
                    required
                ></textarea>

                <span class="muted">
                    Describe unique marks, contents, or other details only the owner would know.
                </span>

            </div>

            <div class="field">

                <label for="proof_photo">
                    Proof Photo (Optional)
                </label>

                <input
                    type="file"
                    id="proof_photo"
                    name="proof_photo"
                    accept="image/jpeg,image/png,image/webp"
                >

                <span class="muted">
                    JPG, PNG, or WebP. Maximum file size: 2 MB.
                </span>

            </div>

            <div class="form-actions">

                <a class="btn" href="<?= e(url('index.php')) ?>">
                    Cancel
                </a>

                <button type="submit" class="btn btn-primary">
                    Submit Claim
                </button>

            </div>

        </form>

    </section>

</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
