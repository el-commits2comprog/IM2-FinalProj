<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/guard.php';
require_once __DIR__ . '/../models/ItemReview.php';

// Only accept POST requests.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    flash_set('error', 'That action is not allowed.');
    redirect(url('admin/review_queue.php'));
}

// Only administrators can review reports.
require_role(ROLE_ADMIN);

// Verify CSRF token.
if (!csrf_verify()) {
    flash_set('error', 'Your session expired. Please try again.');
    redirect(url('admin/review_queue.php'));
}

// Get the currently logged-in administrator.
$adminId = (int) current_user()['user_id'];

// Get form inputs.
$itemId = filter_input(INPUT_POST, 'item_id', FILTER_VALIDATE_INT);
$decision = $_POST['decision'] ?? '';
$remarks = $_POST['remarks'] ?? '';

// Validate inputs.
if (!is_int($itemId) || $itemId <= 0) {
    flash_set('error', 'Invalid item report.');
    redirect(url('admin/review_queue.php'));
}

if (!is_string($decision) ||
    !in_array($decision, ['Approved', 'Rejected'], true)) {
    flash_set('error', 'Choose a valid review decision.');
    redirect(url('admin/review_queue.php'));
}

if (!is_string($remarks)) {
    flash_set('error', 'Invalid review remarks.');
    redirect(url('admin/review_queue.php'));
}

$remarks = trim($remarks);

if (strlen($remarks) > 2000) {
    flash_set('error', 'Remarks must not exceed 2000 characters.');
    redirect(url('admin/review_queue.php'));
}

try {
    // Process the administrator's decision.
    item_review_process(
        $itemId,
        $adminId,
        $decision,
        $remarks === '' ? null : $remarks
    );

    flash_set(
        'success',
        $decision === 'Approved'
            ? 'Item report approved and announced.'
            : 'Item report rejected.'
    );

} catch (InvalidArgumentException | RuntimeException $exception) {
    flash_set('error', $exception->getMessage());

} catch (PDOException $exception) {
    flash_set('error', 'A database error occurred while reviewing the item. Please try again.');
}

redirect(url('admin/review_queue.php'));
